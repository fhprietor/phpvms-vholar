<?php

namespace App\Services;

use App\Contracts\Service;
use App\Exceptions\PirepFeedbackException;
use App\Helpers\FlightAnalysisHelper;
use App\Models\Enums\AcarsType;
use App\Models\Pirep;
use App\Models\PirepAiFeedback;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automatic flight-instructor feedback for a filed PIREP.
 *
 * Sends ACARS telemetry to the DeepSeek API and stores the answer as a
 * PirepAiFeedback row. Advisory only: it never participates in the PIREP
 * lifecycle, so a failed call cannot affect a flight.
 *
 * Two deliberate constraints:
 *
 * 1. Only already-parsed, numeric telemetry is sent. The raw ACARS log is
 *    written by the pilot's simulator, so it is untrusted input and is never
 *    forwarded verbatim (it would be a prompt-injection vector). Free-text
 *    fields that do come from the log (runway, wind) are sanitised first.
 * 2. No pilot identity is sent. The payload carries aggregate statistics of
 *    the pilot's own history, but never the name, id or email.
 */
class PirepFeedbackService extends Service
{
    /**
     * landing_rate is a decimal in pireps; this value is a sentinel written by
     * some clients meaning "no measurement", not a real 999 fpm touchdown.
     */
    private const LANDING_RATE_SENTINEL = -999;

    private const MAX_STRING_FIELD = 40;

    private const MAX_ARRAY_ITEMS = 3;

    private const MAX_TEXT_FIELD = 400;

    /**
     * Valoracion por area: cuantas se aceptan como maximo, y limites de la
     * etiqueta y de la nota de cada una.
     */
    private const MAX_AREAS = 6;

    private const MAX_AREA_NAME = 20;

    private const MAX_AREA_NOTE = 300;

    /**
     * Ceiling for the answer. Measured on the same 4 real PIREPs at two
     * settings, the reasoning pass uses whatever it needs and ignores the
     * extra room: 1.806-3.870 tokens (mean 2.428) at 6000, and 1.750-2.880
     * (mean 2.310) at 16000. Raising the cap therefore does NOT raise the
     * bill - only the tail risk of an empty answer does.
     *
     * The tail risk is what this value guards against. At 3000 the reasoning
     * consumed the whole allowance and returned finish_reason=length with
     * EMPTY content in 3 of 4 calls (a 75% failure rate that then pays for a
     * retry). 16000 is ~4x the highest reasoning run ever observed, so a
     * PIREP that trips this would be pathological. A budget that looks
     * generous is the cheap option here.
     */
    private const MAX_COMPLETION_TOKENS = 16000;

    /**
     * Second attempt gets double the room, for the rare PIREP whose reasoning
     * runs longer than anything observed so far.
     */
    private const RETRY_TOKEN_MULTIPLIER = 2;

    private const REQUEST_TIMEOUT = 180;

    public function __construct(
        private readonly GuzzleClient $httpClient
    ) {}

    /**
     * Is the feature usable? Needs an API key; the enabled flag is honoured by
     * the caller (the scheduled job), not here, so the command can be run by
     * hand on a disabled install for testing.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function modelName(): string
    {
        $model = setting('general.deepseek_model', 'deepseek-flash');

        return is_string($model) && trim($model) !== '' ? trim($model) : 'deepseek-flash';
    }

    public function isEnabled(): bool
    {
        return (bool) setting('general.deepseek_feedback_enabled', false);
    }

    /**
     * Published rates for deepseek-flash, in USD per 1M tokens.
     *
     * Source: https://api-docs.deepseek.com/quick_start/pricing
     * Peak is 01:00-04:00 and 06:00-10:00 UTC, Monday to Friday, excluding
     * Chinese public holidays. Everything else - weekends and holidays in
     * full included - is off-peak, at half the price.
     */
    private const PRICE_PER_MILLION = [
        'peak'    => ['input' => 0.30, 'output' => 1.20],
        'offpeak' => ['input' => 0.15, 'output' => 0.60],
    ];

    /**
     * Estimated USD cost of a call. `completion` already includes the
     * reasoning tokens, which are billed as output.
     */
    public function estimatedCost(int $promptTokens, int $completionTokens, bool $peak = true): float
    {
        $rates = self::PRICE_PER_MILLION[$peak ? 'peak' : 'offpeak'];

        return ($promptTokens / 1_000_000) * $rates['input']
            + ($completionTokens / 1_000_000) * $rates['output'];
    }

    /**
     * PIREPs that carry real ACARS telemetry and have no feedback yet, oldest
     * first.
     *
     * The `acars` LOG check is not optional. Nearly every PIREP in this install
     * carries a `landing_rate` (5.745 of them), but only a few dozen carry the
     * parsed telemetry, and on a PIREP without it the model can only answer
     * "sin datos de aterrizaje" - which still costs ~2.5k tokens. Requiring the
     * LOG row is what keeps the scheduled run from burning tokens on nothing.
     *
     * @param  int|null                                   $limit null to count/list every pending PIREP
     * @return \Illuminate\Support\Collection<int, Pirep>
     */
    public function pendingPireps(?int $limit = 10, bool $includeAnalysed = false, ?string $source = null)
    {
        $query = Pirep::query();

        if (!$includeAnalysed) {
            $query->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('pirep_ai_feedback')
                    ->whereColumn('pirep_ai_feedback.pirep_id', 'pireps.id');
            });
        }

        $query->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('acars')
                ->whereColumn('acars.pirep_id', 'pireps.id')
                ->where('acars.type', AcarsType::LOG);
        });

        // Acota a un cliente ACARS concreto. Es el filtro que separa los vuelos
        // reales de la importacion historica (`CrewSystem/import`).
        if ($source !== null && $source !== '') {
            $query->where('source_name', 'like', $source.'%');
        }

        $query->orderBy('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Analyse a PIREP and persist the result.
     *
     * @throws PirepFeedbackException when the PIREP is not analysable or the API fails
     */
    public function analyseAndStore(Pirep $pirep): PirepAiFeedback
    {
        $payload = $this->buildPayload($pirep);

        if ($payload === null) {
            throw new PirepFeedbackException('El PIREP no tiene telemetria analizable');
        }

        $result = $this->requestFeedback($payload);

        return PirepAiFeedback::updateOrCreate(
            ['pirep_id' => $pirep->id],
            [
                'model'             => $result['model'],
                'verdict'           => $result['verdict'],
                'severity'          => $result['severity'],
                'good_points'       => $result['good_points'],
                'errors'            => $result['errors'],
                'action'            => $result['action'],
                'areas'             => $result['areas'],
                'summary'           => $result['summary'],
                'prompt_tokens'     => $result['prompt_tokens'],
                'completion_tokens' => $result['completion_tokens'],
                'total_tokens'      => $result['total_tokens'],
            ]
        );
    }

    /**
     * Build the telemetry payload, or null when there is nothing to analyse.
     *
     * A `landing_rate` on its own is NOT enough: it is a single number present
     * on most PIREPs and the model can only reply "sin datos de aterrizaje"
     * with it, at full token cost. Real touchdown telemetry is required.
     */
    public function buildPayload(Pirep $pirep): ?array
    {
        $logData = FlightAnalysisHelper::parseLogData($pirep);
        $landing = $logData['landing'] ?? [];

        // vs_fpm is the touchdown rate; without it (or the G reading) there is
        // no touchdown block to comment on.
        if (empty($landing) || (!isset($landing['vs_fpm']) && !isset($landing['gforce']))) {
            return null;
        }

        $payload = [
            'vuelo' => [
                'ruta'         => $pirep->dpt_airport_id.'-'.$pirep->arr_airport_id,
                'aeronave'     => $this->cleanString(optional($pirep->aircraft)->icao),
                'distancia_nm' => $this->toFloat($pirep->getRawOriginal('distance')),
                'minutos'      => $this->toInt($pirep->getRawOriginal('flight_time')),
                'nivel_ft'     => $this->toInt($pirep->getRawOriginal('level')),
            ],
            'aterrizaje' => $this->cleanTelemetry($landing),
        ];

        if (isset($logData['takeoff'])) {
            $payload['despegue'] = $this->cleanTelemetry($logData['takeoff']);
        }

        if (isset($logData['approach'])) {
            $payload['aproximacion'] = $logData['approach'];
        }

        if (!empty($logData['penalties'])) {
            $payload['penalizaciones'] = $this->cleanList($logData['penalties']);
        }

        if (!empty($logData['bonuses'])) {
            $payload['bonificaciones'] = $this->cleanList($logData['bonuses']);
        }

        if (isset($logData['score'])) {
            $payload['score'] = $logData['score'];
        }

        // ── Operaciones: arranque, rodaje, combustible, payload y fases ──
        $operations = $logData['operations'] ?? [];

        $payload['operaciones'] = [
            'arranque' => [
                'motores_iniciados'        => $operations['engines']['started'] ?? null,
                'aceite_estabilizado'      => $operations['engines']['oil_stabilized'] ?? null,
                'idle_segundos'            => $operations['engines']['idle_seconds'] ?? null,
                'oat_c'                    => $operations['engines']['oat_c'] ?? null,
                'pre_arrancado'            => $operations['engines']['pre_started'] ?? null,
                'beacon_antes_de_arrancar' => $operations['engines']['beacon_before_start'] ?? null,
            ],
            'rodaje' => [
                'motor_unico_salida'         => $operations['taxi']['single_engine_out'] ?? null,
                'motor_unico_llegada'        => $operations['taxi']['single_engine_in'] ?? null,
                'calentamiento_insuficiente' => $operations['taxi']['warmup_insufficient'] ?? null,
                'enfriamiento_insuficiente'  => $operations['taxi']['cooldown_insufficient'] ?? null,
                'ciclos_freno_parqueo'       => $operations['taxi']['parking_brake_cycles'] ?? null,
            ],
            'payload' => [
                'pasajeros'   => $operations['payload']['pax'] ?? null,
                'carga'       => $operations['payload']['cargo'] ?? null,
                'nivel_vuelo' => $operations['payload']['flight_level'] ?? null,
            ],
            'tiempos_segundos' => $operations['timings'] ?? null,
        ];

        // ── Combustible ──
        // Solo se envia la magnitud en la unidad que la VA muestra al piloto
        // (`units.fuel`), con la unidad declarada al lado, mas los ratios. Los
        // valores absolutos del log no se reenvian: comparten numero con la BD
        // pero el cliente los rotula de otra forma, y mezclar unidades daria
        // cifras sin sentido.
        $fuel = $operations['fuel'] ?? [];
        $payload['combustible'] = $this->fuelEfficiency($pirep, $fuel);

        // ── Economia: coste real + ingreso estimado ──
        $economics = app(PirepEconomicsService::class)->summary($pirep, $operations);
        if ($economics !== null) {
            $payload['economia'] = $this->cleanEconomics($economics);
        }

        // ── Comparativa contra la empresa en el mismo trayecto ──
        // Es lo que convierte una cifra en un juicio: no "consumiste 221
        // unidades por 100 nm" sino "consumiste un 7,6% mas que tus companeros
        // en ese trayecto". Solo compara con vuelos del mismo cliente ACARS.
        $benchmark = app(PirepBenchmarkService::class)->routeBenchmark($pirep, $operations);
        if ($benchmark !== null) {
            $payload['comparativa_empresa'] = $benchmark;
        }

        $payload['historico_piloto'] = $this->pilotHistory($pirep);

        return $payload;
    }

    /**
     * Consumo y eficiencia de combustible, en la unidad que la VA muestra.
     *
     * @param  array<string, mixed> $fuel Bloque `fuel` de las operaciones
     * @return array<string, mixed>
     */
    private function fuelEfficiency(Pirep $pirep, array $fuel): array
    {
        $unit = setting('units.fuel', 'lbs');
        $hours = ((int) $pirep->getRawOriginal('flight_time')) / 60;
        $distance = (float) $pirep->getRawOriginal('distance');

        // `fuel_used` es un objeto de unidades: `local()` lo devuelve en la
        // unidad configurada para mostrar.
        $used = $pirep->fuel_used !== null ? (float) $pirep->fuel_used->local() : null;
        $boarded = $pirep->block_fuel !== null ? (float) $pirep->block_fuel->local() : null;

        return array_filter([
            'unidad'                  => $unit,
            'quemado_total'           => $used !== null ? round($used, 1) : null,
            'embarcado'               => $boarded !== null ? round($boarded, 1) : null,
            'por_hora'                => ($used !== null && $hours > 0) ? round($used / $hours, 1) : null,
            'por_100nm'               => ($used !== null && $distance > 0) ? round($used / $distance * 100, 1) : null,
            'rodaje_pct_del_total'    => $fuel['taxi_share_pct'] ?? null,
            'planificado_vs_real_pct' => $fuel['trip_vs_planned_pct'] ?? null,
            'validacion_plan_fallida' => $fuel['validation']['failed'] ?? null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * La economia se envia con la marca de estimacion bien visible: el coste es
     * real, el ingreso no. Sin esa marca el instructor afirmaria beneficios
     * como si estuvieran contabilizados.
     *
     * @param  array<string, mixed> $economics
     * @return array<string, mixed>
     */
    private function cleanEconomics(array $economics): array
    {
        $clean = ['moneda' => setting('units.currency', 'USD')];

        if (isset($economics['costs'])) {
            $clean['coste_real'] = [
                'total'    => $economics['costs']['total'],
                'desglose' => $economics['costs']['breakdown'],
            ];
        }

        if (isset($economics['revenue'])) {
            $clean['ingreso_estimado'] = [
                'total'       => $economics['revenue']['total'],
                'es_estimado' => true,
                'pasajeros'   => $economics['revenue']['passengers'],
                'carga'       => $economics['revenue']['cargo'],
            ];
        }

        foreach (['profit'          => 'beneficio', 'margin_pct' => 'margen_pct', 'cost_per_pax' => 'coste_por_pasajero',
            'cost_per_payload_unit' => 'coste_por_unidad_carga', 'cost_per_hour' => 'coste_por_hora',
            'cost_per_nm'           => 'coste_por_nm'] as $key => $label) {
            if (isset($economics[$key])) {
                $clean[$label] = $economics[$key];
            }
        }

        return $clean;
    }

    /**
     * Aggregate statistics of the pilot's own measured landings. Gives the
     * model a baseline so it can tell "firm for you" from "firm in general",
     * without sending any identifying data.
     */
    private function pilotHistory(Pirep $pirep): array
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS n,
                    ROUND(AVG(landing_rate)) AS media,
                    ROUND(SUM(landing_rate <= -300) / COUNT(*) * 100, 1) AS pct_duros,
                    ROUND(SUM(landing_rate > -150) / COUNT(*) * 100, 1) AS pct_suaves
               FROM pireps
              WHERE user_id = ?
                AND deleted_at IS NULL
                AND source = 1
                AND landing_rate IS NOT NULL
                AND landing_rate > ?',
            [$pirep->user_id, self::LANDING_RATE_SENTINEL]
        );

        if ($row === null || (int) $row->n === 0) {
            return [];
        }

        return [
            'aterrizajes_medidos'  => (int) $row->n,
            'media_fpm'            => (int) $row->media,
            'pct_menor_300fpm'     => (float) $row->pct_duros,
            'pct_suaves_mayor_150' => (float) $row->pct_suaves,
        ];
    }

    /**
     * Call the API and normalise the answer.
     *
     * Retries once with double the token budget: the only failure seen in
     * practice is the reasoning pass consuming the whole allowance and leaving
     * empty content, and more room is exactly what fixes it.
     */
    private function requestFeedback(array $payload): array
    {
        $attempts = 2;
        $lastError = null;
        $maxTokens = self::MAX_COMPLETION_TOKENS;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->httpClient->post('https://api.deepseek.com/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer '.$this->apiKey(),
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ],
                    'json' => [
                        'model'    => $this->modelName(),
                        'messages' => [
                            ['role' => 'system', 'content' => $this->systemPrompt()],
                            ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                        ],
                        'max_tokens'  => $maxTokens,
                        'temperature' => 0.2,
                        // The catalogue names this level low/high/max and
                        // defaults to high; without it the reasoning pass
                        // roughly doubles the token count.
                        'reasoning_effort' => 'low',
                        'response_format'  => ['type' => 'json_object'],
                    ],
                    'timeout'         => self::REQUEST_TIMEOUT,
                    'connect_timeout' => 15,
                ]);

                $body = json_decode((string) $response->getBody(), true);

                return $this->normalise($body);
            } catch (Throwable $e) {
                $lastError = $e;
                Log::warning('PirepFeedbackService: intento '.$attempt.' fallido', [
                    'error'      => $e->getMessage(),
                    'max_tokens' => $maxTokens,
                ]);

                $maxTokens *= self::RETRY_TOKEN_MULTIPLIER;
            }
        }

        throw new PirepFeedbackException(
            'No se pudo obtener retroalimentacion: '.($lastError?->getMessage() ?? 'error desconocido'),
            0,
            $lastError
        );
    }

    /**
     * Validate and flatten the API answer. Throws so the caller can retry when
     * the model returns something that cannot be stored.
     */
    private function normalise(?array $body): array
    {
        if (!is_array($body)) {
            throw new PirepFeedbackException('Respuesta vacia o no JSON de la API');
        }

        if (isset($body['error'])) {
            $message = $body['error']['message'] ?? json_encode($body['error']);
            throw new PirepFeedbackException('Error de la API: '.$message);
        }

        $content = $body['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new PirepFeedbackException('La API devolvio contenido vacio (posible agotamiento del presupuesto de razonamiento)');
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new PirepFeedbackException('El contenido devuelto no es JSON valido');
        }

        // severity drives the staff filter, so it must be a usable integer.
        $severity = (int) ($data['severidad'] ?? PirepAiFeedback::SEVERITY_IMPROVABLE);
        if ($severity < 0 || $severity > 2) {
            $severity = PirepAiFeedback::SEVERITY_IMPROVABLE;
        }

        $usage = $body['usage'] ?? [];

        return [
            'model'             => (string) ($body['model'] ?? $this->modelName()),
            'verdict'           => $this->cleanText($data['veredicto'] ?? null, 191),
            'severity'          => $severity,
            'good_points'       => $this->cleanStringList($data['puntos_fuertes'] ?? []),
            'errors'            => $this->cleanStringList($data['errores'] ?? []),
            'action'            => $this->cleanText($data['accion'] ?? null, self::MAX_TEXT_FIELD),
            'areas'             => $this->cleanAreas($data['areas'] ?? []),
            'summary'           => $content,
            'prompt_tokens'     => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            'completion_tokens' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
            'total_tokens'      => isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null,
        ];
    }

    /**
     * Valoracion por area. Misma escala de gravedad que `severidad` para poder
     * reutilizar el esquema de color del tema en las tres superficies.
     *
     * @return array<int, array{area: string, valoracion: int, nota: string}>
     */
    private function cleanAreas(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $clean = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $area = strtolower(trim((string) ($item['area'] ?? '')));
            if ($area === '') {
                continue;
            }

            $note = $this->cleanText($item['nota'] ?? null, self::MAX_AREA_NOTE);
            if ($note === null) {
                continue;
            }

            $rating = (int) ($item['valoracion'] ?? PirepAiFeedback::SEVERITY_IMPROVABLE);
            if ($rating < 0 || $rating > 2) {
                $rating = PirepAiFeedback::SEVERITY_IMPROVABLE;
            }

            $clean[] = [
                'area'       => mb_substr($area, 0, self::MAX_AREA_NAME),
                'valoracion' => $rating,
                'nota'       => $note,
            ];

            if (count($clean) >= self::MAX_AREAS) {
                break;
            }
        }

        return $clean;
    }

    private function systemPrompt(): string
    {
        return 'Eres instructor de vuelo (TRI) de una aerolinea virtual y revisas la telemetria ACARS de un '
            .'vuelo ya efectuado. Ya no solo el aterrizaje: tambien el arranque de motores, el rodaje (incluido '
            .'el taxi a un motor), el despegue, el uso del combustible y la economia del vuelo. Responde SOLO '
            .'con un objeto JSON valido, sin markdown, con exactamente estas claves: "veredicto" (texto, maximo '
            .'8 palabras), "severidad" (entero: 0 = correcto, 1 = mejorable, 2 = critico), "puntos_fuertes" '
            .'(array de hasta 3 textos, cada uno citando una metrica concreta), "errores" (array de hasta 3 '
            .'textos, cada uno citando el valor numerico que lo demuestra), "accion" (un unico texto con una '
            .'accion concreta y medible para el proximo vuelo) y "areas" (array de hasta 6 objetos, uno por '
            .'area que puedas valorar, con las claves "area" (una de: arranque, rodaje, despegue, aterrizaje, '
            .'combustible, economia), "valoracion" (misma escala 0/1/2) y "nota" (una frase, maximo 25 '
            .'palabras, citando una cifra). '
            .'Como leer los datos: "operaciones.arranque.idle_segundos" son los segundos que cada motor paso al '
            .'ralenti. OJO: una diferencia entre los dos motores es HABITUAL y no es un fallo por si sola, '
            .'porque el arranque es secuencial y el rodaje a un motor tambien la produce; en esta flota la '
            .'mediana ronda los 8 minutos y el 90% de los vuelos supera los 2 minutos. NO la interpretes como '
            .'"arranque asimetrico" ni la uses como error salvo que haya una senal que lo respalde '
            .'("pre_arrancado" o calentamiento/enfriamiento insuficiente). Lo que si es accionable en el '
            .'arranque: "beacon_antes_de_arrancar" debe ser true y "pre_arrancado" es un arranque antes de '
            .'tiempo. En '
            .'"operaciones.rodaje", "motor_unico_salida" o "motor_unico_llegada" a true es buena practica y '
            .'ahorra combustible, pero si aparece "calentamiento_insuficiente" o "enfriamiento_insuficiente" '
            .'la practica se hizo mal. "operaciones.tiempos_segundos" da la duracion de cada fase. En '
            .'"combustible", "planificado_vs_real_pct" negativo significa que quemo MENOS de lo planificado '
            .'(bueno) y "rodaje_pct_del_total" alto delata un rodaje largo. En "economia", "coste_real" es '
            .'dato contable exacto del libro mayor, pero "ingreso_estimado" y todo lo que cuelga de el '
            .'(beneficio, margen, coste por pasajero) son ESTIMACIONES: dilo asi cada vez que los menciones, '
            .'nunca los presentes como contabilidad cerrada. '
            .'El bloque "comparativa_empresa" es la parte que permite al piloto medirse con la empresa: compara '
            .'este vuelo con los de otros pilotos en el MISMO trayecto (en cualquier sentido). Cada metrica trae '
            .'"tu" valor, "media_compania", "diferencia", "unidad_diferencia" ("por_ciento" o '
            .'"puntos_porcentuales") y "mejor_que_media". Cita la diferencia CON SU UNIDAD, sin convertirla. Usa '
            .'"mejor_que_media" para decir con claridad si fue mas o menos eficiente que la media y en que '
            .'metrica; "companeros" dice con cuantos vuelos se compara, asi que si es 1 o 2 presentalo como '
            .'indicativo y no como veredicto. Si el bloque no aparece, no hay companeros en ese trayecto: no '
            .'inventes ninguna comparacion. '
            .'Calibra la gravedad, no todo vuelo tiene algo malo: 0 (correcto) cuando no hay ningun hallazgo '
            .'relevante; 1 (mejorable) para desviaciones menores o de eficiencia; 2 (critico) SOLO para '
            .'seguridad o impacto economico serio (aterrizaje con G alta o muy duro, aproximacion claramente '
            .'inestable, sobreconsumo muy por encima del plan). Si los datos no muestran nada relevante, usa 0 '
            .'y dilo con naturalidad. '
            .'Usa exclusivamente los datos del JSON de entrada: no inventes cifras, no supongas datos '
            .'ausentes y no comentes lo que no aparezca; si un area no trae datos, no la incluyas en "areas". '
            .'El bloque "historico_piloto" es la media del propio piloto y sirve de referencia para juzgar si '
            .'el resultado es bueno o malo para el. Escribe en espanol, tono tecnico y directo, sin adulacion. '
            .'Los valores de "penalizaciones" y "bonificaciones" ya vienen evaluados por el cliente ACARS: '
            .'usalos como evidencia, no los recalcules.';
    }

    /**
     * Keep only scalar telemetry. Anything nested or unknown is dropped rather
     * than forwarded, so a crafted log cannot smuggle instructions into the
     * prompt through an unexpected key.
     */
    private function cleanTelemetry(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $clean[$key] = $this->cleanString($value);
            } elseif (is_int($value) || is_float($value)) {
                $clean[$key] = $value;
            } elseif (is_bool($value)) {
                $clean[$key] = $value;
            } elseif (is_array($value) && $this->isScalarList($value)) {
                $clean[$key] = array_map(fn ($item) => is_string($item) ? $this->cleanString($item) : $item, $value);
            }
        }

        return $clean;
    }

    private function isScalarList(array $value): bool
    {
        foreach ($value as $item) {
            if (!is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array{points: int, reason: string}>
     */
    private function cleanList(array $items): array
    {
        $clean = [];

        foreach (array_slice($items, 0, self::MAX_ARRAY_ITEMS * 2) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $clean[] = [
                'points' => (int) ($item['points'] ?? 0),
                // 191, not MAX_STRING_FIELD: the reason carries the runway and
                // the measured value the model must quote ("... 2331 ft from
                // threshold (RWY 35)"), and truncating it strips the evidence.
                'reason' => $this->cleanText($item['reason'] ?? '', 191) ?? '',
            ];
        }

        return $clean;
    }

    /**
     * @return string[]
     */
    private function cleanStringList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $clean = [];

        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $text = $this->cleanText($item, self::MAX_TEXT_FIELD);
            if ($text !== null) {
                $clean[] = $text;
            }

            if (count($clean) >= self::MAX_ARRAY_ITEMS) {
                break;
            }
        }

        return $clean;
    }

    /**
     * Neutralise control characters and cap the length of a free-text field.
     */
    private function cleanString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value) ?? '';
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, self::MAX_STRING_FIELD);
    }

    private function cleanText(mixed $value, int $limit): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '';
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function apiKey(): ?string
    {
        $key = setting('general.deepseek_api_key', null);

        if (!is_string($key)) {
            return null;
        }

        $key = trim($key);

        return $key === '' ? null : $key;
    }
}
