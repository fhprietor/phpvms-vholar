<?php

namespace Tests;

use App\Helpers\FlightAnalysisHelper;
use App\Models\Acars;
use App\Models\Enums\AcarsType;
use App\Models\Pirep;

/**
 * Bloque operacional del parser: lo que ocurre antes y despues del aterrizaje.
 *
 * Los patrones estan copiados literalmente de logs reales de vmsOpenAcars, que
 * es el unico cliente con telemetria en este proyecto.
 */
final class FlightAnalysisOperationsTest extends TestCase
{
    /**
     * @param string[] $lines
     */
    private function makePirep(array $lines): Pirep
    {
        $pirep = Pirep::factory()->create(['source' => 1]);

        foreach ($lines as $i => $line) {
            Acars::factory()->create([
                'pirep_id'   => $pirep->id,
                'type'       => AcarsType::LOG,
                'log'        => $line,
                'created_at' => now()->addSeconds($i * 60),
            ]);
        }

        return $pirep;
    }

    private function operations(Pirep $pirep): array
    {
        return FlightAnalysisHelper::parseLogData($pirep)['operations'] ?? [];
    }

    public function test_payload_is_parsed_from_the_log_line(): void
    {
        $pirep = $this->makePirep(['PAX 159  FUEL 12375  TRIP 9030  CARGO 200  FL380']);

        $payload = $this->operations($pirep)['payload'];

        $this->assertSame(159, $payload['pax']);
        $this->assertSame(200.0, $payload['cargo']);
        $this->assertSame(380, $payload['flight_level']);
        $this->assertSame(12375.0, $payload['block_fuel']);
        $this->assertSame(9030.0, $payload['planned_trip_fuel']);
    }

    public function test_engine_start_is_parsed_with_idle_times_and_oat(): void
    {
        $pirep = $this->makePirep([
            'BEACON ENCENDIDO',
            '? Motores iniciados',
            'Motor: ENG1 idle 1056s [STAB ✓]  ENG2 idle 461s [STAB ✓]  OAT 12°C',
            '✅ Motores estabilizados — aceite en temperatura',
        ]);

        $engines = $this->operations($pirep)['engines'];

        $this->assertSame(['ENG1' => 1056, 'ENG2' => 461], $engines['idle_seconds']);
        $this->assertSame(12, $engines['oat_c']);
        $this->assertTrue($engines['started']);
        $this->assertTrue($engines['oil_stabilized']);
        // El beacon se encendio antes de arrancar: procedimiento correcto.
        $this->assertTrue($engines['beacon_before_start']);
    }

    public function test_beacon_lit_after_start_is_flagged_as_late(): void
    {
        $pirep = $this->makePirep([
            '? Motores iniciados',
            'BEACON ENCENDIDO',
        ]);

        $this->assertFalse($this->operations($pirep)['engines']['beacon_before_start']);
    }

    public function test_single_engine_taxi_and_its_warnings_are_detected(): void
    {
        $pirep = $this->makePirep([
            '? Motor único detectado en Taxi Out',
            '⚠️ Single engine taxi sin bonificación — cool-down insuficiente',
        ]);

        $taxi = $this->operations($pirep)['taxi'];

        $this->assertTrue($taxi['single_engine_out']);
        $this->assertTrue($taxi['cooldown_insufficient']);
    }

    public function test_fuel_ratios_are_computed_from_the_log(): void
    {
        $pirep = $this->makePirep([
            'PAX 159  FUEL 12375  TRIP 9030  CARGO 200  FL380',
            '⛽ Combustible: Taxi 274 kg · Vuelo 8678 kg · Total 8952 kg',
        ]);

        $fuel = $this->operations($pirep)['fuel'];

        $this->assertSame(8952.0, $fuel['total']);
        $this->assertSame(274.0, $fuel['taxi_total']);
        // 8678 frente a los 9030 planificados: quemo de menos.
        $this->assertSame(-3.9, $fuel['trip_vs_planned_pct']);
        $this->assertSame(3.1, $fuel['taxi_share_pct']);
    }

    public function test_failed_fuel_validation_block_is_captured(): void
    {
        $pirep = $this->makePirep([
            "❌ FUEL VALIDATION FAILED\n\nPlanned: 12000 kg\nSimulator: 11100 kg\nDifference: -900 kg\nTolerance: 300 kg",
        ]);

        $validation = $this->operations($pirep)['fuel']['validation'];

        $this->assertTrue($validation['failed']);
        $this->assertSame(12000.0, $validation['planned']);
        $this->assertSame(11100.0, $validation['simulator']);
    }

    /**
     * Un vuelo puede repetir fase (step climb): la secuencia debe conservar
     * ambos marcadores, y los agregados sumarlos en vez de sobreescribirlos.
     */
    public function test_repeated_phases_are_summed_not_overwritten(): void
    {
        $pirep = $this->makePirep([
            '── PUSHBACK ──',
            '── TAXI OUT ──',
            '── TAKEOFF ROLL ──',
            '── TAKEOFF ──',
            '── CLIMB ──',
            '── ENROUTE ──',      // +20 min
            '── CLIMB ──',        // step climb, +60 min
            '── ENROUTE ──',      // +5 min
            '── DESCENT ──',
            '── APPROACH ──',
            '── AFTER LANDING ──',
            '── TAXI IN ──',
            '── ON BLOCK ──',
        ]);

        $timings = $this->operations($pirep)['timings'];

        // El fixture separa cada linea 60 s. Hay dos transiciones
        // climb->enroute (ascenso inicial y step climb), asi que el ascenso
        // suma 120 s en vez de quedarse con la ultima.
        $this->assertSame(2 * 60, $timings['climb_seconds']);
        // Rodaje de salida: pushback->taxi out + taxi out->takeoff roll.
        $this->assertSame(2 * 60, $timings['taxi_out_seconds']);
        // 13 marcadores => 12 transiciones de 60 s.
        $this->assertSame(12 * 60, $timings['block_seconds']);
    }

    public function test_punctuality_and_block_off_are_captured(): void
    {
        $pirep = $this->makePirep([
            '✅ Salida a tiempo (Δ+0 min, STD 09:30 UTC)',
            '⏱️ Block Off registrado a las 09:29:43 UTC',
            '⏱️ Block On registrado a las 14:21:20 UTC',
        ]);

        $timings = $this->operations($pirep)['timings'];

        $this->assertSame(0, $timings['on_time_delta_min']);
        $this->assertSame('09:30', $timings['std_utc']);
        $this->assertSame('09:29:43', $timings['block_off_utc']);
        $this->assertSame('14:21:20', $timings['block_on_utc']);
    }

    public function test_english_logs_are_understood_too(): void
    {
        $pirep = $this->makePirep([
            'Engines started',
            'Engines stabilized',
        ]);

        $engines = $this->operations($pirep)['engines'];

        $this->assertTrue($engines['started']);
        $this->assertTrue($engines['oil_stabilized']);
    }

    /**
     * Sin lineas operativas el bloque existe pero vacio: el feedback debe poder
     * contar con la clave sin romperse.
     */
    public function test_a_log_without_operational_lines_yields_empty_blocks(): void
    {
        $pirep = $this->makePirep(['Tren de aterrizaje: DOWN']);

        $operations = $this->operations($pirep);

        $this->assertArrayHasKey('engines', $operations);
        $this->assertArrayHasKey('fuel', $operations);
        $this->assertArrayHasKey('payload', $operations);
        $this->assertSame([], $operations['payload']);
    }
}
