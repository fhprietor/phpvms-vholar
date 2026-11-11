<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Events\LandingStored;
use App\Exceptions\LandingNotFound;
use App\Exceptions\PirepCancelled;
use App\Exceptions\PirepNotFound;
use App\Exceptions\Unauthorized;
use App\Http\Requests\Acars\AggregateLandingsRequest;
use App\Http\Requests\Acars\StoreLandingRequest;
use App\Models\Pirep;
use App\Models\PirepLanding;
use App\Services\LandingAggregateService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Analisis del aterrizaje (flare) enviado por vmsOpenAcars al filear el PIREP.
 *
 *   POST /api/pireps/{pirep_id}/landing   (una vez, idempotente)
 *   GET  /api/pireps/{pirep_id}/landing   (replay en la web)
 *   GET  /api/landings/aggregate          (linea base entre pilotos, sin IA)
 *
 * Es informativo: nada del ciclo del PIREP lo lee, y no toca ni `pireps.score`
 * ni `pireps.landing_rate`, que siguen siendo los canonicos que puntuan.
 *
 * Autorizacion: escribir solo el dueno del PIREP; leer cualquier piloto
 * autenticado, porque `/pireps/{id}` ya es visible para toda la aerolinea y el
 * objetivo es comparar aterrizajes entre pilotos. Es una decision consciente, no
 * un descuido: el dato no lleva identidad del piloto, solo el flare.
 */
class LandingController extends Controller
{
    /**
     * Metricas que se guardan en columna con su tratamiento. Las distancias van
     * en PIES CRUDOS: no se les aplica DistanceCast (ver la migracion).
     */
    private const FLOAT_METRICS = [
        'g_force',
        'touchdown_dist_ft',
        'centerline_dev_ft',
        'runway_length_ft',
        'runway_true_deg',
        'offset_threshold_ft',
        'vref_kt',
        'landing_weight_lbs',
        // Peso de aterrizaje del PLAN (OFP), al que pertenece la Vref. Separado del medido.
        'planned_landing_weight_lbs',
        'flaps_pct',
        'power_cut_sec',
        'wind_dir_deg',
        'wind_speed_kt',
        'wind_gust_kt',
        'headwind_kt',
        'crosswind_kt',
    ];

    private const INT_METRICS = [
        'landing_rate_fpm',
        'score',
        'flaps_index',
    ];

    private const TEXT_METRICS = [
        'runway_name'       => 16,
        'aircraft_icao'     => 8,
        'aircraft_title'    => 191,
        'aircraft_model'    => 64,
        'flaps_label'       => 32,
        'landing_metar'     => 500,
        'trace_stop_reason' => 32,
    ];

    /**
     * Guarda el analisis del aterrizaje de un PIREP.
     *
     * Es un UPSERT por `pirep_id`: reenviar el mismo payload reemplaza la fila
     * entera (incluida la traza) en vez de duplicarla, porque el cliente manda
     * el analisis completo una sola vez.
     *
     * @throws \App\Exceptions\PirepNotFound
     * @throws \App\Exceptions\PirepCancelled
     * @throws \App\Exceptions\Unauthorized
     */
    public function store(string $id, StoreLandingRequest $request): JsonResponse
    {
        $pirep = Pirep::find($id);
        if (empty($pirep)) {
            throw new PirepNotFound($id);
        }

        $this->checkCancelled($pirep);
        $this->checkOwnership($pirep);

        $metrics = (array) $request->post('metrics', []);
        $trace = $request->post('trace');

        $attrs = [
            'schema_version' => (int) $request->post('schema_version'),
            'source_name'    => $this->cleanText($request->post('source_name'), 64),
            'trace'          => is_array($trace) ? $trace : null,
        ];

        foreach (self::FLOAT_METRICS as $key) {
            $attrs[$key] = $this->toFloat($metrics[$key] ?? null);
        }

        foreach (self::INT_METRICS as $key) {
            $attrs[$key] = $this->toInt($metrics[$key] ?? null);
        }

        foreach (self::TEXT_METRICS as $key => $limit) {
            $attrs[$key] = $this->cleanText($metrics[$key] ?? null, $limit);
        }

        // Se declara aparte de TEXT/FLOAT para no confundirlo con un numero: es
        // null si no llego dato, y NUNCA false por defecto.
        $attrs['flare_capture_armed'] = $this->toBool($metrics['flare_capture_armed'] ?? null);

        // Si el cliente no resuelve el tipo de aeronave —le pasa con algunos
        // addons: el DHC6-300 Twin Otter llego con `aircraft_icao` nulo y el
        // titulo presente— se rellena con el ICAO de la aeronave del PIREP, que
        // es dato NUESTRO y autoritativo. Sin esto el vuelo se queda fuera de la
        // linea base por tipo: justo la comparacion que pedia el pedido.
        if ($attrs['aircraft_icao'] === null && $pirep->aircraft !== null) {
            $attrs['aircraft_icao'] = $this->cleanText($pirep->aircraft->icao, 8);
        }

        $landing = PirepLanding::updateOrCreate(['pirep_id' => $id], $attrs);

        // Aviso para que el analisis de IA se rehaga si ya se habia generado sin
        // el flare. Es barato (el listener solo mira una setting y una fila) y
        // nunca puede romper la ingesta: ver LandingFeedbackListener.
        event(new LandingStored($pirep, $landing));

        $samples = $landing->hasTrace() ? count($landing->trace) : 0;

        return $this->message('Landing stored', $samples, [
            'pirep_id'       => $id,
            'schema_version' => $landing->schema_version,
            'trace_samples'  => $samples,
        ]);
    }

    /**
     * Devuelve `metrics` + `trace` para el replay en el navegador.
     *
     * @throws \App\Exceptions\PirepNotFound
     * @throws \App\Exceptions\LandingNotFound
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $pirep = Pirep::find($id);
        if (empty($pirep)) {
            throw new PirepNotFound($id);
        }

        $landing = PirepLanding::where('pirep_id', $id)->first();
        if (empty($landing)) {
            throw new LandingNotFound($id);
        }

        return $this->message('ok', null, [
            'pirep_id'       => $id,
            'schema_version' => $landing->schema_version,
            'source_name'    => $landing->source_name,
            'metrics'        => $this->metricsPayload($landing),
            'trace'          => $landing->trace ?? [],
        ]);
    }

    /**
     * Linea base entre pilotos: conteo, mediana y cuartiles de las metricas del
     * aterrizaje, filtrable por aeropuerto de llegada, pista y tipo de avion.
     *
     * Es DETERMINISTA y sin IA: esto es lo que compara un aterrizaje con los de
     * la misma pista o el mismo avion. El calculo (y su cache) vive en
     * LandingAggregateService, que es la unica fuente: tambien lo usa el servicio
     * de IA para darle al modelo una linea base.
     */
    public function aggregate(
        AggregateLandingsRequest $request,
        LandingAggregateService $aggregates
    ): JsonResponse {
        $payload = $aggregates->aggregate([
            'airport'  => $request->query('airport'),
            'runway'   => $request->query('runway'),
            'aircraft' => $request->query('aircraft'),
        ]);

        return $this->message('ok', $payload['total_landings'], $payload);
    }

    /**
     * Las metricas tal y como se devuelven al cliente/visor: mismas claves que
     * en la ingesta, para que el replay no tenga que traducir nombres.
     */
    private function metricsPayload(PirepLanding $landing): array
    {
        $metrics = [];

        foreach (self::FLOAT_METRICS as $key) {
            $metrics[$key] = $landing->{$key};
        }

        foreach (self::INT_METRICS as $key) {
            $metrics[$key] = $landing->{$key};
        }

        foreach (array_keys(self::TEXT_METRICS) as $key) {
            $metrics[$key] = $landing->{$key};
        }

        $metrics['flare_capture_armed'] = $landing->flare_capture_armed;

        return $metrics;
    }

    /**
     * El PIREP tiene que ser del piloto autenticado.
     *
     * Se comprueba aqui y no en el FormRequest a proposito: devolver false en
     * authorize() lanza AuthorizationException, que
     * App\Exceptions\Handler::handleApiError() mapea a 503 y no a 403. Es la
     * misma pauta que AcarsController::checkOwnership().
     *
     * @throws \App\Exceptions\Unauthorized
     */
    private function checkOwnership(Pirep $pirep): void
    {
        if ($pirep->user_id !== Auth::id()) {
            throw new Unauthorized(new Exception('PIREP does not belong to the authenticated user'));
        }
    }

    /**
     * @throws \App\Exceptions\PirepCancelled
     */
    private function checkCancelled(Pirep $pirep): void
    {
        if ($pirep->cancelled) {
            throw new PirepCancelled($pirep);
        }
    }

    /**
     * Texto que viene del simulador/cliente: entrada no confiable. Se le quitan
     * los caracteres de control y se acota la longitud, igual que hace
     * PirepFeedbackService, para que no se convierta en un vector de inyeccion
     * cuando la fase de IA lo lea. Vacio -> null (la convencion del contrato).
     */
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
        return is_numeric($value) ? (float) $value : null;
    }

    private function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * `null` se conserva como null: un booleano ausente no es `false`.
     */
    private function toBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
