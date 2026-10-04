<?php

namespace Tests;

use App\Exceptions\PirepFeedbackException;
use App\Models\Acars;
use App\Models\Enums\AcarsType;
use App\Models\Pirep;
use App\Models\PirepAiFeedback;
use App\Models\Setting;
use App\Services\PirepFeedbackService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ReflectionMethod;

/**
 * Retroalimentacion automatica de PIREPs.
 *
 * Lo que se protege aqui son las dos decisiones que mas caro salen si se
 * rompen: no gastar tokens en PIREPs sin telemetria, y no fiarse de lo que
 * devuelve el modelo (es texto que acaba en la base de datos y, mas adelante,
 * en pantalla).
 */
final class PirepFeedbackServiceTest extends TestCase
{
    /** Bloque de aterrizaje tal y como lo escribe el cliente ACARS. */
    private const TOUCHDOWN_LOG = [
        'DATOS DE ATERRIZAJE:',
        'Aterrizaje registrado: -73 fpm, 1,05 G, Rumbo: 340°, Cabeceo: 7,0°, Alabeo: 0,0°',
        'PISTA 35 | TD: 2331 ft desde umbral | CL: 2 ft de desviación',
    ];

    /** Respuesta bien formada de la API. */
    private function apiResponse(array $overrides = []): array
    {
        return [
            'model'   => 'deepseek-flash',
            'choices' => [
                ['message' => ['content' => json_encode(array_merge([
                    'veredicto'      => 'Aterrizaje suave pero largo',
                    'severidad'      => 1,
                    'puntos_fuertes' => ['VS de -73 fpm'],
                    'errores'        => ['Toma a 2331 ft del umbral'],
                    'accion'         => 'Tocar entre 1000 y 1500 ft',
                ], $overrides), JSON_UNESCAPED_UNICODE)]],
            ],
            'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 2000, 'total_tokens' => 2500],
        ];
    }

    private function mockApi(array $body, int $status = 200): void
    {
        $mock = new MockHandler([
            new Response($status, ['Content-Type' => 'application/json'], json_encode($body)),
        ]);

        app()->instance(Client::class, new Client(['handler' => HandlerStack::create($mock)]));
    }

    private function service(): PirepFeedbackService
    {
        return app(PirepFeedbackService::class);
    }

    private function configureApiKey(string $key = 'test-key'): void
    {
        Setting::where('key', 'general.deepseek_api_key')->update(['value' => $key]);
    }

    /**
     * Crea un PIREP y, opcionalmente, le cuelga filas de log ACARS.
     */
    private function makePirep(array $attrs = [], array $logLines = []): Pirep
    {
        $pirep = Pirep::factory()->create(array_merge([
            'landing_rate' => -73,
            'source'       => 1,
        ], $attrs));

        foreach ($logLines as $i => $line) {
            Acars::factory()->create([
                'pirep_id'   => $pirep->id,
                'type'       => AcarsType::LOG,
                'log'        => $line,
                'created_at' => now()->addSeconds($i),
            ]);
        }

        return $pirep;
    }

    /**
     * Invoca el metodo privado que valida la respuesta del modelo.
     */
    private function normalise(?array $body): array
    {
        $method = new ReflectionMethod(PirepFeedbackService::class, 'normalise');
        $method->setAccessible(true);

        return $method->invoke($this->service(), $body);
    }

    // ---------------------------------------------------------------- payload

    public function test_a_pirep_without_acars_log_has_no_payload(): void
    {
        // landing_rate solo no basta: es un numero suelto presente en casi
        // todos los PIREPs y el modelo solo puede contestar "sin datos".
        $pirep = $this->makePirep(['landing_rate' => -150]);

        $this->assertNull($this->service()->buildPayload($pirep));
    }

    public function test_a_log_without_a_touchdown_block_has_no_payload(): void
    {
        $pirep = $this->makePirep([], ['Tren de aterrizaje: DOWN', 'Luces LANDING ENCENDIDAS']);

        $this->assertNull($this->service()->buildPayload($pirep));
    }

    public function test_a_touchdown_block_produces_landing_telemetry(): void
    {
        $pirep = $this->makePirep([], self::TOUCHDOWN_LOG);

        $payload = $this->service()->buildPayload($pirep);

        $this->assertIsArray($payload);
        $this->assertSame(-73, $payload['aterrizaje']['vs_fpm']);
        $this->assertSame(2331, $payload['aterrizaje']['td_ft']);
        $this->assertSame(2, $payload['aterrizaje']['centerline_ft']);
    }

    public function test_the_payload_carries_no_pilot_identity(): void
    {
        $pirep = $this->makePirep([], self::TOUCHDOWN_LOG);
        $payload = $this->service()->buildPayload($pirep);

        $flat = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($pirep->user->name, $flat);
        $this->assertStringNotContainsString($pirep->user->email, $flat);
    }

    // ------------------------------------------------------------------ cola

    public function test_pending_pireps_only_includes_those_with_telemetry(): void
    {
        $withLog = $this->makePirep(['flight_number' => 'AAA1'], self::TOUCHDOWN_LOG);
        $this->makePirep(['flight_number' => 'BBB2'], []); // sin log

        $pending = $this->service()->pendingPireps(null);

        $this->assertCount(1, $pending);
        // pireps.id es varchar(36): llega como string, no como int.
        $this->assertEquals($withLog->id, $pending->first()->id);
    }

    public function test_pending_pireps_excludes_already_analysed_ones(): void
    {
        $pirep = $this->makePirep([], self::TOUCHDOWN_LOG);
        PirepAiFeedback::create([
            'pirep_id' => $pirep->id,
            'model'    => 'deepseek-flash',
            'severity' => 1,
        ]);

        $this->assertCount(0, $this->service()->pendingPireps(null));
    }

    // ------------------------------------------------- validacion de respuesta

    public function test_severity_out_of_range_is_clamped(): void
    {
        $this->assertSame(1, $this->normalise($this->apiResponse(['severidad' => 99]))['severity']);
        $this->assertSame(1, $this->normalise($this->apiResponse(['severidad' => -5]))['severity']);
        $this->assertSame(2, $this->normalise($this->apiResponse(['severidad' => 2]))['severity']);
        $this->assertSame(0, $this->normalise($this->apiResponse(['severidad' => 0]))['severity']);
    }

    public function test_arrays_are_capped_at_three_items(): void
    {
        $result = $this->normalise($this->apiResponse([
            'puntos_fuertes' => ['a', 'b', 'c', 'd', 'e'],
            'errores'        => ['1', '2', '3', '4'],
        ]));

        $this->assertCount(3, $result['good_points']);
        $this->assertCount(3, $result['errors']);
    }

    public function test_control_characters_are_stripped_from_model_text(): void
    {
        $result = $this->normalise($this->apiResponse([
            'veredicto' => "Aterrizaje\x00 suave\x1b[31m",
            'accion'    => "Tocar\x07 corto",
        ]));

        $this->assertSame('Aterrizaje suave[31m', $result['verdict']);
        $this->assertStringNotContainsString("\x07", $result['action']);
    }

    public function test_overlong_text_is_truncated(): void
    {
        $result = $this->normalise($this->apiResponse([
            'veredicto' => str_repeat('x', 500),
            'accion'    => str_repeat('y', 900),
        ]));

        $this->assertSame(191, mb_strlen($result['verdict']));
        $this->assertSame(400, mb_strlen($result['action']));
    }

    public function test_a_non_scalar_in_the_arrays_is_discarded(): void
    {
        $result = $this->normalise($this->apiResponse([
            'puntos_fuertes' => ['valido', ['anidado' => 'no'], 'otro'],
        ]));

        $this->assertSame(['valido', 'otro'], $result['good_points']);
    }

    public function test_empty_content_is_rejected(): void
    {
        $this->expectException(PirepFeedbackException::class);

        $body = $this->apiResponse();
        $body['choices'][0]['message']['content'] = '';

        $this->normalise($body);
    }

    public function test_malformed_json_is_rejected(): void
    {
        $this->expectException(PirepFeedbackException::class);

        $body = $this->apiResponse();
        $body['choices'][0]['message']['content'] = 'esto no es json';

        $this->normalise($body);
    }

    public function test_an_api_error_payload_is_rejected(): void
    {
        $this->expectException(PirepFeedbackException::class);

        $this->normalise(['error' => ['message' => 'insufficient balance']]);
    }

    public function test_a_missing_body_is_rejected(): void
    {
        $this->expectException(PirepFeedbackException::class);

        $this->normalise(null);
    }

    // ----------------------------------------------------------------- guardar

    public function test_feedback_is_persisted_with_its_token_usage(): void
    {
        $this->configureApiKey();
        $this->mockApi($this->apiResponse());

        $pirep = $this->makePirep([], self::TOUCHDOWN_LOG);
        $feedback = $this->service()->analyseAndStore($pirep);

        $this->assertSame($pirep->id, $feedback->pirep_id);
        $this->assertSame('Aterrizaje suave pero largo', $feedback->verdict);
        $this->assertSame(1, $feedback->severity);
        $this->assertSame(['VS de -73 fpm'], $feedback->good_points);
        $this->assertSame(2500, $feedback->total_tokens);

        $this->assertDatabaseHas('pirep_ai_feedback', ['pirep_id' => $pirep->id]);
    }

    public function test_reanalysing_replaces_the_previous_row(): void
    {
        $this->configureApiKey();
        $this->mockApi($this->apiResponse());

        $pirep = $this->makePirep([], self::TOUCHDOWN_LOG);
        $this->service()->analyseAndStore($pirep);

        $this->mockApi($this->apiResponse(['veredicto' => 'Segundo analisis']));
        $this->service()->analyseAndStore($pirep);

        $this->assertSame(1, PirepAiFeedback::where('pirep_id', $pirep->id)->count());
        $this->assertSame('Segundo analisis', PirepAiFeedback::where('pirep_id', $pirep->id)->first()->verdict);
    }

    public function test_analysing_a_pirep_without_telemetry_throws(): void
    {
        $this->configureApiKey();
        $this->mockApi($this->apiResponse());

        $pirep = $this->makePirep(['landing_rate' => -150]);

        $this->expectException(PirepFeedbackException::class);
        $this->service()->analyseAndStore($pirep);
    }

    public function test_without_an_api_key_the_service_is_not_configured(): void
    {
        $this->configureApiKey('');
        $this->assertFalse($this->service()->isConfigured());

        $this->configureApiKey('clave-de-prueba');
        $this->assertTrue($this->service()->isConfigured());
    }

    // ------------------------------------------------------------------ coste

    /**
     * Tarifas publicas de deepseek-flash: US$0.30/US$1.20 por millon en pico
     * (entrada/salida) y la mitad en valle. El razonamiento se factura como
     * salida, y ya viene dentro de completion_tokens.
     */
    public function test_cost_uses_the_published_peak_and_offpeak_rates(): void
    {
        $service = $this->service();

        // 1M de entrada + 1M de salida = 0.30 + 1.20 en pico, 0.15 + 0.60 en valle.
        $this->assertEqualsWithDelta(1.50, $service->estimatedCost(1_000_000, 1_000_000, true), 0.0001);
        $this->assertEqualsWithDelta(0.75, $service->estimatedCost(1_000_000, 1_000_000, false), 0.0001);

        // Off-peak es exactamente la mitad que pico.
        $this->assertEqualsWithDelta(
            $service->estimatedCost(763, 2502, true) / 2,
            $service->estimatedCost(763, 2502, false),
            0.0000001
        );

        // Medición real de este proyecto: 763 de entrada y 2502 de salida.
        $this->assertEqualsWithDelta(0.003231, $service->estimatedCost(763, 2502, true), 0.000001);
    }
}
