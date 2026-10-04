<?php

namespace Tests;

use App\Models\Acars;
use App\Models\Enums\AcarsType;
use App\Models\Pirep;
use App\Services\PirepBenchmarkService;
use Illuminate\Support\Facades\DB;

/**
 * Comparativa contra la empresa en el mismo trayecto.
 *
 * Es la pieza que convierte una cifra en un juicio ("consumiste un 22% mas que
 * tus companeros"), asi que lo que se fija aqui es que la comparacion sea
 * HONESTA: que no se compare uno consigo mismo, que no se mezclen clientes
 * ACARS con telemetria no homogenea, que sin companeros no se invente una media,
 * y que las metricas que ya son porcentajes no se comparren como porcentaje de
 * un porcentaje.
 */
final class PirepBenchmarkServiceTest extends TestCase
{
    private PirepBenchmarkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PirepBenchmarkService::class);
    }

    /**
     * PIREP comparable: ruta, piloto, cliente ACARS y telemetria de consumo.
     *
     * @param array<string, mixed> $attrs
     */
    private function makePirep(array $attrs = [], array $log = []): Pirep
    {
        $pirep = Pirep::factory()->create(array_merge([
            'source'       => 1,
            'source_name'  => 'vmsOpenAcars/0.9.24',
            'dpt_airport_id' => 'SKBO',
            'arr_airport_id' => 'SKCL',
            'flight_time'  => 60,
            'distance'     => 100,
            'fuel_used'    => 400,
            'block_fuel'   => 500,
            'landing_rate' => -100,
        ], $attrs));

        // La telemetria es obligatoria para ser comparable.
        $lines = $log ?: [
            'PAX 100  FUEL 500  TRIP 450  CARGO 0  FL350',
            '⛽ Combustible: Taxi 20 kg · Vuelo 430 kg · Total 450 kg',
        ];

        foreach ($lines as $i => $line) {
            Acars::factory()->create([
                'pirep_id'   => $pirep->id,
                'type'       => AcarsType::LOG,
                'log'        => $line,
                'created_at' => now()->addSeconds($i),
            ]);
        }

        return $pirep->fresh();
    }

    private function operationsFor(Pirep $pirep): array
    {
        return \App\Helpers\FlightAnalysisHelper::parseLogData($pirep)['operations'] ?? [];
    }

    private function benchmark(Pirep $pirep): ?array
    {
        return $this->service->routeBenchmark($pirep, $this->operationsFor($pirep));
    }

    // ------------------------------------------------------------- companeros

    public function test_without_peers_there_is_no_benchmark(): void
    {
        // Ruta unica: nadie mas la ha volado.
        $pirep = $this->makePirep();

        $this->assertNull($this->benchmark($pirep));
    }

    public function test_a_peer_is_another_pilot_on_the_same_route(): void
    {
        $pirep = $this->makePirep();
        $this->makePirep();  // el factory ya crea otro piloto

        $benchmark = $this->benchmark($pirep);

        $this->assertNotNull($benchmark);
        $this->assertSame(1, $benchmark['vuelos_comparados']);
        $this->assertSame('SKBO-SKCL', $benchmark['trajecto']);
        $this->assertSame(2, $benchmark['pilotos_distintos']);
    }

    /**
     * Medirse contra uno mismo desplazaria la media hacia el propio vuelo y
     * disfrazaria la desviacion real.
     */
    public function test_the_flight_itself_is_not_part_of_the_average(): void
    {
        $mine = $this->makePirep(['fuel_used' => 400, 'distance' => 100]); // 400/100nm
        $this->makePirep(['fuel_used' => 200, 'distance' => 100]);        // 200/100nm

        $benchmark = $this->benchmark($mine);
        $metric = $benchmark['comparativa']['consumo_por_100nm'];

        // La media debe ser la del companero (200), no (400+200)/2 = 300.
        $this->assertSame(200.0, $metric['media_compania']);
        $this->assertSame(400.0, $metric['tu']);
        $this->assertSame(100.0, $metric['diferencia']);
        $this->assertFalse($metric['mejor_que_media']);
    }

    /**
     * Otro vuelo del MISMO piloto no es un companero: la comparacion es contra
     * la empresa, no contra el historial propio.
     */
    public function test_the_pilots_own_other_flights_are_not_peers(): void
    {
        $pirep = $this->makePirep();

        $otroMio = $this->makePirep(['user_id' => $pirep->user_id]);

        $this->assertNull($this->benchmark($pirep));
        $this->assertSame($pirep->user_id, $otroMio->user_id);
    }

    public function test_the_route_counts_in_both_directions(): void
    {
        $mine = $this->makePirep(['dpt_airport_id' => 'SKBO', 'arr_airport_id' => 'SKCL']);
        // El companero lo volo en sentido contrario.
        $this->makePirep(['dpt_airport_id' => 'SKCL', 'arr_airport_id' => 'SKBO']);

        $benchmark = $this->benchmark($mine);

        $this->assertSame(1, $benchmark['vuelos_comparados']);
        // Pero el bloque declara cuantos iban en el mismo sentido.
        $this->assertSame(0, $benchmark['mismo_sentido']);
    }

    public function test_a_different_route_is_not_a_peer(): void
    {
        $mine = $this->makePirep();
        $this->makePirep(['dpt_airport_id' => 'SKRG', 'arr_airport_id' => 'SKMD']);

        $this->assertNull($this->benchmark($mine));
    }

    // ------------------------------------------------------------ filtros duros

    /**
     * Los vuelos importados no traen telemetria homogenea: mezclarlos daria
     * medias enganosas.
     */
    public function test_pireps_from_another_acars_client_are_not_peers(): void
    {
        $mine = $this->makePirep();
        $this->makePirep(['source_name' => 'CrewSystem/import']);

        $this->assertNull($this->benchmark($mine));
    }

    public function test_a_peer_without_telemetry_is_ignored(): void
    {
        $mine = $this->makePirep();

        // Mismo vuelo y ruta, pero sin ninguna fila de log.
        $sinTelemetria = Pirep::factory()->create([
            'source'         => 1,
            'source_name'    => 'vmsOpenAcars/0.9.24',
            'dpt_airport_id' => 'SKBO',
            'arr_airport_id' => 'SKCL',
        ]);
        $this->assertNotNull($sinTelemetria);

        $this->assertNull($this->benchmark($mine));
    }

    // -------------------------------------------------------------- matematicas

    /**
     * Regresion: `desvio_plan_pct` ya es un porcentaje. Compararlo con una
     * variacion relativa daba numeros sin sentido (de -1% a -3,67% salia 72,8%).
     * Debe ir en PUNTOS porcentuales.
     */
    public function test_percentage_metrics_are_compared_in_points(): void
    {
        // Yo quemo un 1% menos de lo planificado; el companero, un 10% menos.
        $mine = $this->makePirep([], [
            'PAX 100  FUEL 500  TRIP 100  CARGO 0  FL350',
            '⛽ Combustible: Taxi 0 kg · Vuelo 99 kg · Total 99 kg',
        ]);
        $this->makePirep([], [
            'PAX 100  FUEL 500  TRIP 100  CARGO 0  FL350',
            '⛽ Combustible: Taxi 0 kg · Vuelo 90 kg · Total 90 kg',
        ]);

        $metric = $this->benchmark($mine)['comparativa']['desvio_plan_pct'];

        $this->assertSame('puntos_porcentuales', $metric['unidad_diferencia']);
        // -1% mio frente a -10% del companero => +9 puntos.
        $this->assertSame(-1.0, $metric['tu']);
        $this->assertSame(-10.0, $metric['media_compania']);
        $this->assertSame(9.0, $metric['diferencia']);
        // El companero ahorro mas, asi que yo no estoy mejor que la media.
        $this->assertFalse($metric['mejor_que_media']);
    }

    public function test_absolute_metrics_are_compared_as_a_percentage(): void
    {
        $mine = $this->makePirep(['fuel_used' => 500, 'distance' => 100]);
        $this->makePirep(['fuel_used' => 400, 'distance' => 100]);

        $metric = $this->benchmark($mine)['comparativa']['consumo_por_100nm'];

        $this->assertSame('por_ciento', $metric['unidad_diferencia']);
        // 500 frente a 400 => +25%.
        $this->assertSame(25.0, $metric['diferencia']);
    }

    /**
     * "Mejor" depende de la metrica: menos consumo es mejor, mas pasajeros es
     * mejor. Se decide sobre la magnitud, no sobre el signo de la diferencia.
     */
    public function test_more_is_better_metrics_are_flagged_correctly(): void
    {
        $mine = $this->makePirep([], [
            'PAX 200  FUEL 500  TRIP 450  CARGO 0  FL350',
            '⛽ Combustible: Taxi 20 kg · Vuelo 430 kg · Total 450 kg',
        ]);
        $this->makePirep([], [
            'PAX 100  FUEL 500  TRIP 450  CARGO 0  FL350',
            '⛽ Combustible: Taxi 20 kg · Vuelo 430 kg · Total 450 kg',
        ]);

        $pasajeros = $this->benchmark($mine)['comparativa']['pasajeros'];

        // 200 pasajeros frente a 100: mas es mejor.
        $this->assertSame(200.0, $pasajeros['tu']);
        $this->assertSame(100.0, $pasajeros['media_compania']);
        $this->assertSame(100.0, $pasajeros['diferencia']);
        $this->assertTrue($pasajeros['mejor_que_media']);
    }

    public function test_each_metric_reports_how_many_peers_it_used(): void
    {
        $mine = $this->makePirep();
        $this->makePirep();
        $this->makePirep();

        $metric = $this->benchmark($mine)['comparativa']['consumo_por_100nm'];

        // El instructor necesita saber si compara con 1 vuelo o con 20.
        $this->assertSame(2, $metric['companeros']);
        $this->assertSame(3, $this->benchmark($mine)['pilotos_distintos']);
    }

    public function test_a_peer_without_fuel_data_is_skipped_for_that_metric(): void
    {
        $mine = $this->makePirep(['fuel_used' => 400, 'distance' => 100]);

        // Companero con telemetria de payload pero sin dato de consumo. El
        // factory no deja el campo a nulo de forma fiable, asi que se fuerza en
        // la base de datos para que la prueba sea determinista.
        $peer = $this->makePirep([], [
            'PAX 100  FUEL 500  TRIP 450  CARGO 50  FL350',
        ]);
        DB::table('pireps')->where('id', $peer->id)->update(['fuel_used' => null]);

        $comparison = $this->benchmark($mine)['comparativa'];

        // Sin dato de consumo del companero, esa metrica no se compara...
        $this->assertArrayNotHasKey('consumo_por_100nm', $comparison);
        // ...pero el payload si, porque ambos lo traen.
        $this->assertArrayHasKey('pasajeros', $comparison);
    }

    /**
     * Sin asientos contables no hay metricas economicas, pero la comparativa de
     * combustible y payload debe seguir saliendo.
     */
    public function test_fuel_and_payload_are_compared_without_ledger_entries(): void
    {
        $mine = $this->makePirep(['fuel_used' => 400, 'distance' => 100]);
        $this->makePirep(['fuel_used' => 300, 'distance' => 100]);

        $comparison = $this->benchmark($mine)['comparativa'];

        $this->assertSame(0, DB::table('journal_transactions')->where('ref_model_id', $mine->id)->count());
        $this->assertArrayHasKey('consumo_por_100nm', $comparison);
        $this->assertArrayHasKey('pasajeros', $comparison);
        $this->assertArrayNotHasKey('coste_por_pasajero', $comparison);
    }
}
