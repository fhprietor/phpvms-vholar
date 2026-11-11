<?php

namespace Tests;

use App\Models\Acars;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Models\PirepLanding;
use App\Models\User;

/**
 * Analisis del aterrizaje (flare) de vmsOpenAcars
 * (POST/GET /api/pireps/{pirep_id}/landing).
 *
 * Lo critico que se protege aqui:
 *  - que reenviar el analisis sea IDEMPOTENTE: el cliente lo manda una vez y un
 *    reintento no puede duplicar filas;
 *  - que `null` se conserve como null y NUNCA como 0: en el contrato del cliente
 *    un 0 de radioaltimetro es una medida falsa. Aplica tambien a los booleanos
 *    de la traza (`spoilers`, `on_ground`);
 *  - que las distancias sean PIES CRUDOS: `touchdown_dist_ft` y el `dist_ft` de
 *    cada muestra (negativo pasado el umbral) no pueden pasar por DistanceCast,
 *    o dejarian de ser pies;
 *  - que la ingesta NO toque `pireps.score` ni `pireps.landing_rate`, que siguen
 *    siendo los canonicos que puntuan;
 *  - que la traza no acabe en la tabla `acars`, de la que
 *    FlightAnalysisHelper::parseLogData() deriva el score;
 *  - que solo el dueno del PIREP pueda escribir, y que la lectura este abierta a
 *    cualquier piloto autenticado (decision consciente: el dato no lleva
 *    identidad y la comparacion entre pilotos es el objetivo).
 */
final class PirepLandingTest extends TestCase
{
    // ------------------------------------------------------------------ ayudas

    private function newPirep(array $attrs = []): Pirep
    {
        $this->createPirep();

        return Pirep::factory()->create(array_merge(['user_id' => $this->user->id], $attrs));
    }

    /**
     * El payload real del vuelo 44 del pedido (SKCC -> SKBG, pista 17).
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'schema_version' => 1,
            'source_name'    => 'vmsOpenAcars/0.9.37',
            'metrics'        => [
                'landing_rate_fpm'    => -789,
                'g_force'             => 2.5,
                'touchdown_dist_ft'   => 202.3,
                'centerline_dev_ft'   => 24.4,
                'score'               => 42,
                'runway_name'         => '17',
                'runway_length_ft'    => 7362.0,
                'runway_true_deg'     => 173.2,
                'landing_weight_lbs'  => 150821.6,
                'aircraft_icao'       => 'B38M',
                'aircraft_title'      => 'iFly B38M VHR N665VH (178Seat)',
                'aircraft_model'      => '737 MAX 8',
                'flaps_label'         => 'FLAPS 15',
                'flaps_pct'           => 62.5,
                'flaps_index'         => 5,
                'power_cut_sec'       => 4.2,
                'flare_capture_armed' => true,
                'landing_metar'       => 'SKBG 091100Z 01003KT 9999 SCT020 25/20 Q1013',
                'wind_dir_deg'        => 10,
                'wind_speed_kt'       => 3,
                'wind_gust_kt'        => null,
                'headwind_kt'         => 1.4,
                'crosswind_kt'        => -2.7,
            ],
            'trace' => [
                $this->sample(['ts_utc' => '2026-10-09T11:26:39.733Z', 'dist_ft' => 1482.8, 'agl_ft' => 95.0]),
                $this->sample(['ts_utc' => '2026-10-09T11:26:40.733Z', 'dist_ft' => 500.0, 'agl_ft' => 40.0]),
                $this->sample(['ts_utc' => '2026-10-09T11:26:41.733Z', 'dist_ft' => -12.5, 'agl_ft' => 0.0]),
            ],
        ], $overrides);
    }

    private function sample(array $overrides = []): array
    {
        return array_merge([
            'ts_utc'       => '2026-10-09T11:26:39.733Z',
            'dist_ft'      => 1482.8,
            'agl_ft'       => 95.0,
            'radar_alt_ft' => 362.8,
            'ias_kt'       => 149.0,
            'vs_fpm'       => -850,
            'pitch_deg'    => 2.5,
            'bank_deg'     => 0.3,
            'gs_kt'        => 145,
            'n1_1_pct'     => 55.0,
            'n1_2_pct'     => 55.0,
            'n2_1_pct'     => null,
            'n2_2_pct'     => null,
            'flaps_pct'    => 62.5,
            'flaps_index'  => 5,
            'spoilers'     => false,
            'on_ground'    => false,
        ], $overrides);
    }

    // -------------------------------------------------------------------- POST

    public function test_it_stores_the_metrics_and_the_trace(): void
    {
        $pirep = $this->newPirep();

        $response = $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload());

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertSame(3, $body['count']);
        $this->assertSame(3, $body['attrs']['trace_samples']);

        $row = PirepLanding::where('pirep_id', $pirep->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, $row->schema_version);
        $this->assertSame('vmsOpenAcars/0.9.37', $row->source_name);
        $this->assertSame(-789, $row->landing_rate_fpm);
        $this->assertSame(2.5, $row->g_force);
        $this->assertSame('17', $row->runway_name);
        $this->assertSame('B38M', $row->aircraft_icao);
        $this->assertTrue($row->flare_capture_armed);
        $this->assertCount(3, $row->trace);
        $this->assertSame(-850, $row->trace[0]['vs_fpm']);
    }

    /**
     * Reenviar el analisis reemplaza la fila, no la duplica: es la garantia de
     * idempotencia del contrato.
     */
    public function test_reposting_replaces_instead_of_duplicating(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload())->assertStatus(200);

        $second = $this->payload([
            'metrics' => ['landing_rate_fpm' => -120, 'runway_name' => '35'],
            'trace'   => [$this->sample(['ts_utc' => '2026-10-09T12:00:00Z', 'dist_ft' => -3.0])],
        ]);
        $this->post('/api/pireps/'.$pirep->id.'/landing', $second)->assertStatus(200);

        $this->assertSame(1, PirepLanding::where('pirep_id', $pirep->id)->count());

        $row = PirepLanding::where('pirep_id', $pirep->id)->first();
        $this->assertSame(-120, $row->landing_rate_fpm);
        $this->assertSame('35', $row->runway_name);
        $this->assertCount(1, $row->trace);
        // Reemplazo total: lo que no vino en el segundo payload queda null.
        $this->assertNull($row->g_force);
    }

    public function test_a_repost_without_trace_clears_it(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload())->assertStatus(200);

        $no_trace = $this->payload();
        unset($no_trace['trace']);
        $this->post('/api/pireps/'.$pirep->id.'/landing', $no_trace)->assertStatus(200);

        $this->assertNull(PirepLanding::where('pirep_id', $pirep->id)->first()->trace);
    }

    // ------------------------------------------------------- convencion de nulos

    /**
     * null = sin dato, nunca 0. Es la convencion mas facil de romper por
     * accidente (un `?? 0` o un cast a float de un valor ausente).
     */
    public function test_null_metrics_stay_null_and_are_not_zero(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', [
            'schema_version' => 1,
            'metrics'        => [
                'landing_rate_fpm' => null,
                'g_force'          => null,
                'wind_gust_kt'     => null,
            ],
        ])->assertStatus(200);

        $row = PirepLanding::where('pirep_id', $pirep->id)->first();
        $this->assertNull($row->landing_rate_fpm);
        $this->assertNull($row->g_force);
        $this->assertNull($row->wind_gust_kt);
        // Y las que ni siquiera vinieron.
        $this->assertNull($row->touchdown_dist_ft);
        $this->assertNull($row->runway_name);
    }

    /**
     * Un booleano ausente o nulo NO es `false`: un `spoilers`/`on_ground`/
     * `flare_capture_armed` desconocido no puede leerse como "no desplegado".
     */
    public function test_a_null_boolean_stays_null(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', [
            'schema_version' => 1,
            'metrics'        => [],
            'trace'          => [
                $this->sample(['ts_utc' => '2026-10-09T11:26:39Z', 'spoilers' => null, 'on_ground' => null]),
            ],
        ])->assertStatus(200);

        $row = PirepLanding::where('pirep_id', $pirep->id)->first();
        $this->assertNull($row->flare_capture_armed);
        $this->assertNull($row->trace[0]['spoilers']);
        $this->assertNull($row->trace[0]['on_ground']);
    }

    public function test_a_false_boolean_is_kept_as_false(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', [
            'schema_version' => 1,
            'metrics'        => ['flare_capture_armed' => false],
            'trace'          => [$this->sample(['ts_utc' => '2026-10-09T11:26:39Z', 'on_ground' => true])],
        ])->assertStatus(200);

        $row = PirepLanding::where('pirep_id', $pirep->id)->first();
        $this->assertFalse($row->flare_capture_armed);
        $this->assertTrue($row->trace[0]['on_ground']);
    }

    // ----------------------------------------------------------------- unidades

    /**
     * Regresion de la trampa DistanceCast: las distancias se guardan en pies
     * crudos y con su signo. `dist_ft` es NEGATIVO pasado el umbral.
     */
    public function test_distances_are_stored_in_raw_feet_with_their_sign(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload())->assertStatus(200);

        $row = PirepLanding::where('pirep_id', $pirep->id)->first();
        $this->assertSame(202.3, $row->touchdown_dist_ft);
        $this->assertSame(24.4, $row->centerline_dev_ft);

        // Positivo acercandose, negativo sobre la pista.
        $this->assertSame(1482.8, $row->trace[0]['dist_ft']);
        $this->assertSame(-12.5, $row->trace[2]['dist_ft']);
    }

    // ------------------------------------------------------------- texto saneado

    public function test_text_from_the_simulator_is_sanitised(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => [
                'runway_name'    => "17\x07",
                'aircraft_title' => "iFly\x00 B38M",
            ],
        ]))->assertStatus(200);

        $row = PirepLanding::where('pirep_id', $pirep->id)->first();
        $this->assertSame('17', $row->runway_name);
        $this->assertSame('iFly B38M', $row->aircraft_title);
    }

    // ---------------------------------------------------------- score canonico

    /**
     * El analisis es informativo: no toca el score ni el landing_rate canonicos
     * del PIREP, que son los que puntuan y acreditan.
     */
    public function test_the_canonical_pirep_score_is_not_touched(): void
    {
        $pirep = $this->newPirep(['score' => 77, 'landing_rate' => -210]);

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => ['score' => 42, 'landing_rate_fpm' => -789],
        ]))->assertStatus(200);

        $fresh = $pirep->fresh();
        $this->assertSame(77, $fresh->score);
        // `landing_rate` es decimal en `pireps`, asi que vuelve como float.
        $this->assertEqualsWithDelta(-210.0, (float) $fresh->landing_rate, 0.001);
    }

    // ------------------------------------------------------------ aislamiento

    /**
     * La traza vive en su propia tabla: no puede crear filas en `acars` ni, por
     * tanto, llegar a parseLogData() y alterar el score.
     */
    public function test_the_landing_never_lands_in_the_acars_table(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => ['landing_metar' => 'PENALTY: esto no es una penalizacion'],
        ]))->assertStatus(200);

        $this->assertSame(1, PirepLanding::where('pirep_id', $pirep->id)->count());
        $this->assertSame(0, Acars::where('pirep_id', $pirep->id)->count());
    }

    // ---------------------------------------------------------------- validacion

    public function test_a_missing_metrics_block_is_rejected(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', ['schema_version' => 1])
            ->assertStatus(400);

        $this->assertSame(0, PirepLanding::where('pirep_id', $pirep->id)->count());
    }

    public function test_a_missing_or_invalid_schema_version_is_rejected(): void
    {
        $pirep = $this->newPirep();

        $no_version = $this->payload();
        unset($no_version['schema_version']);
        $this->post('/api/pireps/'.$pirep->id.'/landing', $no_version)->assertStatus(400);

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload(['schema_version' => 0]))
            ->assertStatus(400);
    }

    public function test_a_trace_sample_without_timestamp_is_rejected(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'trace' => [['dist_ft' => 100.0]],
        ]))->assertStatus(400);
    }

    public function test_a_trace_over_the_sample_cap_is_rejected(): void
    {
        $pirep = $this->newPirep();

        $trace = [];
        for ($i = 0; $i <= 2000; $i++) {
            $trace[] = $this->sample(['ts_utc' => '2026-10-09T11:26:39Z']);
        }

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload(['trace' => $trace]))
            ->assertStatus(400);

        $this->assertSame(0, PirepLanding::where('pirep_id', $pirep->id)->count());
    }

    public function test_out_of_range_metrics_are_rejected(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => ['g_force' => 999],
        ]))->assertStatus(400);
    }

    /**
     * El METAR se acepta hasta 500 caracteres (los bloques RMK de EEUU pasan de
     * 400 sin ser anormales). El tope lo declara el servidor porque el cliente
     * recorta al valor que le digamos: si se queda corto, el vuelo pierde el
     * METAR; si nos pasamos, pierde la traza entera por la validacion todo-o-nada.
     */
    public function test_a_metar_up_to_five_hundred_characters_is_accepted(): void
    {
        $pirep = $this->newPirep();

        $metar = str_repeat('A', 500);

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => ['landing_metar' => $metar],
        ]))->assertStatus(200);

        $this->assertSame(500, strlen(PirepLanding::where('pirep_id', $pirep->id)->first()->landing_metar));
    }

    public function test_a_metar_over_five_hundred_characters_is_rejected(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => ['landing_metar' => str_repeat('A', 501)],
        ]))->assertStatus(400);
    }

    /**
     * Los dos campos que pedimos al cliente. Se guardan como cualquier otra
     * metrica, y son opcionales: los vuelos ya subidos no los traen.
     */
    public function test_it_stores_the_offset_threshold_and_the_reference_speed(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => ['offset_threshold_ft' => 496.0, 'vref_kt' => 137.0],
        ]))->assertStatus(200);

        $row = PirepLanding::where('pirep_id', $pirep->id)->first();
        $this->assertSame(496.0, $row->offset_threshold_ft);
        $this->assertSame(137.0, $row->vref_kt);
    }

    public function test_it_stores_the_trace_stop_reason(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => ['trace_stop_reason' => 'stopped:timeout'],
        ]))->assertStatus(200);

        $this->assertSame(
            'stopped:timeout',
            PirepLanding::where('pirep_id', $pirep->id)->first()->trace_stop_reason
        );
    }

    /**
     * Si el cliente no resuelve el tipo de aeronave —le pasa con addons como el
     * DHC6-300 Twin Otter, que llego con `aircraft_icao` nulo y el titulo
     * presente— se usa el ICAO de la aeronave del PIREP. Sin esto el vuelo se
     * queda fuera de la linea base por tipo, que es justo la comparacion que
     * queria el pedido.
     */
    public function test_it_falls_back_to_the_pirep_aircraft_type(): void
    {
        $pirep = $this->newPirep();
        $expected = $pirep->aircraft->icao;

        $payload = $this->payload();
        unset($payload['metrics']['aircraft_icao']);

        $this->post('/api/pireps/'.$pirep->id.'/landing', $payload)->assertStatus(200);

        $this->assertSame($expected, PirepLanding::where('pirep_id', $pirep->id)->first()->aircraft_icao);
    }

    /**
     * El ejemplo LITERAL que dio el cliente en su respuesta (11), con el sufijo
     * del margen: se guarda tal cual, sin recortar. Es la verificacion de que la
     * clave `metrics.trace_stop_reason` esta bien mapeada y de que un campo con
     * sufijo no se pierde por el camino.
     */
    public function test_it_stores_the_stop_reason_with_its_margin_suffix(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => ['trace_stop_reason' => 'stopped:post-touchdown 2.0s'],
        ]))->assertStatus(200);

        $this->assertSame(
            'stopped:post-touchdown 2.0s',
            PirepLanding::where('pirep_id', $pirep->id)->first()->trace_stop_reason
        );
    }

    /**
     * Documenta un riesgo real del que el cliente YA se protege: nuestra
     * validacion es todo-o-nada, asi que un motivo de 33 caracteres no se
     * recorta — tumba el envio entero y se pierde tambien la traza. Por eso ellos
     * recortan en su lado con NUESTRO numero. Si algun dia se cambia a recortar
     * en vez de rechazar, este test caera y habra que avisarles de que el margen
     * ya no hace falta.
     */
    public function test_a_stop_reason_over_the_cap_kills_the_whole_payload(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload([
            'metrics' => ['trace_stop_reason' => str_repeat('x', 33)],
        ]))->assertStatus(400);

        $this->assertNull(PirepLanding::where('pirep_id', $pirep->id)->first());
    }

    public function test_the_offset_and_the_reference_speed_are_optional(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload())->assertStatus(200);

        $row = PirepLanding::where('pirep_id', $pirep->id)->first();
        $this->assertNull($row->offset_threshold_ft);
        $this->assertNull($row->vref_kt);
    }

    // -------------------------------------------------------------- autorizacion

    public function test_another_pilots_pirep_cannot_be_written(): void
    {
        $this->createPirep();
        $other = User::factory()->create();
        $theirs = Pirep::factory()->create(['user_id' => $other->id]);

        $this->post('/api/pireps/'.$theirs->id.'/landing', $this->payload())
            ->assertStatus(403);

        $this->assertSame(0, PirepLanding::where('pirep_id', $theirs->id)->count());
    }

    public function test_a_cancelled_pirep_is_rejected(): void
    {
        $pirep = $this->newPirep(['state' => PirepState::CANCELLED]);

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload())
            ->assertStatus(400);

        $this->assertSame(0, PirepLanding::where('pirep_id', $pirep->id)->count());
    }

    public function test_an_unknown_pirep_is_not_found(): void
    {
        $this->createPirep();

        $this->post('/api/pireps/DOESNTEXIST/landing', $this->payload())
            ->assertStatus(404);
    }

    // --------------------------------------------------------------------- GET

    public function test_the_get_endpoint_returns_metrics_and_trace(): void
    {
        $pirep = $this->newPirep();

        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload())->assertStatus(200);

        $response = $this->get('/api/pireps/'.$pirep->id.'/landing');
        $response->assertStatus(200);

        $attrs = $response->json()['attrs'];
        $this->assertSame(1, $attrs['schema_version']);
        $this->assertSame(-789, $attrs['metrics']['landing_rate_fpm']);
        $this->assertSame('17', $attrs['metrics']['runway_name']);
        $this->assertSame(202.3, $attrs['metrics']['touchdown_dist_ft']);
        $this->assertCount(3, $attrs['trace']);
        $this->assertSame(-12.5, $attrs['trace'][2]['dist_ft']);
    }

    public function test_the_get_endpoint_is_not_found_without_a_stored_landing(): void
    {
        $pirep = $this->newPirep();

        $this->get('/api/pireps/'.$pirep->id.'/landing')->assertStatus(404);
    }

    public function test_the_get_endpoint_is_not_found_for_an_unknown_pirep(): void
    {
        $this->createPirep();

        $this->get('/api/pireps/DOESNTEXIST/landing')->assertStatus(404);
    }

    /**
     * La LECTURA esta abierta a cualquier piloto autenticado: el dato no lleva
     * identidad y la comparacion entre pilotos es el objetivo. Se fija aqui para
     * que un cambio futuro sea una decision, no un accidente.
     */
    public function test_any_authenticated_pilot_can_read_a_landing(): void
    {
        $pirep = $this->newPirep();
        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload())->assertStatus(200);

        $other = User::factory()->create();

        $response = $this->get('/api/pireps/'.$pirep->id.'/landing', [], $other);
        $response->assertStatus(200);
        $this->assertSame(-789, $response->json()['attrs']['metrics']['landing_rate_fpm']);
    }

    // ------------------------------------------------------------------ relacion

    public function test_the_pirep_relation_returns_the_landing(): void
    {
        $pirep = $this->newPirep();
        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload())->assertStatus(200);

        $landing = $pirep->fresh()->landing;
        $this->assertInstanceOf(PirepLanding::class, $landing);
        $this->assertSame('17', $landing->runway_name);
        $this->assertTrue($landing->hasTrace());
    }

    public function test_the_pirep_relation_is_null_without_a_landing(): void
    {
        $pirep = $this->newPirep();

        $this->assertNull($pirep->fresh()->landing);
    }

    /**
     * Pirep usa SoftDeletes, asi que `delete()` no borra la fila y la cascada de
     * la clave foranea no se dispara: el analisis se conserva.
     */
    public function test_soft_deleting_the_pirep_keeps_the_landing(): void
    {
        $pirep = $this->newPirep();
        $this->post('/api/pireps/'.$pirep->id.'/landing', $this->payload())->assertStatus(200);

        $pirep_id = $pirep->id;
        $pirep->delete();

        $this->assertSame(1, PirepLanding::where('pirep_id', $pirep_id)->count());
    }
    public function test_planned_landing_weight_is_stored_apart_from_the_measured_one(): void
    {
        $pirep = $this->newPirep();

        $payload = $this->payload();
        $payload['source_name'] = 'vmsOpenACars/0.9.72';
        $payload['metrics']['vref_kt'] = 136;
        $payload['metrics']['planned_landing_weight_lbs'] = 405500.0;

        $this->post('/api/pireps/'.$pirep->id.'/landing', $payload)->assertStatus(200);

        $landing = PirepLanding::where('pirep_id', $pirep->id)->firstOrFail();

        $this->assertSame(405500.0, (float) $landing->planned_landing_weight_lbs);
        // El medido no se sustituye por el planeado: son dos datos distintos.
        $this->assertSame(150821.6, (float) $landing->landing_weight_lbs);
    }
}
