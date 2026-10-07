<?php

namespace Tests;

use App\Models\Aircraft;
use App\Models\Subfleet;
use Illuminate\Support\Facades\DB;

/**
 * Alineacion de los pesos de la flota con el catalogo de airframes de SimBrief.
 *
 * Lo que se protege aqui:
 *  - que el comando NO escriba nada sin `--apply`;
 *  - que los pesos del catalogo se normalicen a kg (cada airframe declara su
 *    unidad en `wgtunits`);
 *  - que se guarde en la unidad interna del modelo (libras), no en kg;
 *  - que los cargueros no hereden los pesos del avion de pasaje del mismo ICAO.
 */
final class AlignAircraftWeightsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('simbrief_airframes')->delete();
    }

    public function test_without_apply_it_only_reports(): void
    {
        $aircraft = $this->aircraft();

        $this->artisan('phpvms:align-aircraft-weights', ['--type' => ['B38M']])
            ->assertExitCode(0);

        // 42.184 kg (93.000 lb) como estaba: no se ha tocado.
        $this->assertEqualsWithDelta(93000, (float) DB::table('aircraft')->where('id', $aircraft->id)->value('dow'), 1);
    }

    public function test_with_apply_it_copies_the_catalogue_weights(): void
    {
        // Airframe en libras, como el que usa SimBrief para el B38M.
        $this->airframe('B38M', 'LBS', ['oew' => 99360, 'mzfw' => 145400, 'mtow' => 181000, 'mlw' => 153000]);
        $aircraft = $this->aircraft();

        $this->artisan('phpvms:align-aircraft-weights', ['--type' => ['B38M'], '--apply' => true])
            ->assertExitCode(0);

        $raw = (float) DB::table('aircraft')->where('id', $aircraft->id)->value('dow');
        $this->assertEqualsWithDelta(99360, $raw, 1, 'se guarda en libras (unidad interna)');

        // 99.360 lb son 45.069 kg. Se pide el kg explicitamente porque la unidad
        // de visualizacion depende del ajuste `units.weight` (en tests, libras).
        $this->assertEqualsWithDelta(45069, Aircraft::find($aircraft->id)->dow->toUnit('kg'), 1);
    }

    public function test_a_kgs_airframe_is_not_converted(): void
    {
        $this->airframe('B38M', 'KGS', ['oew' => 45069, 'mzfw' => 65952, 'mtow' => 82191, 'mlw' => 69309]);
        $aircraft = $this->aircraft();

        $this->artisan('phpvms:align-aircraft-weights', ['--type' => ['B38M'], '--apply' => true])
            ->assertExitCode(0);

        $this->assertEqualsWithDelta(45069, Aircraft::find($aircraft->id)->dow->toUnit('kg'), 1);
    }

    /**
     * `B77F` y `B77L` comparten ICAO en el catalogo: si el carguero cogiera la
     * mediana heredaria el MZFW del 777-200LR (209 t) en vez del suyo (248 t).
     */
    public function test_a_freighter_takes_its_own_variant(): void
    {
        $this->airframe('B77L', 'LBS', ['oew' => 345900, 'mzfw' => 461000, 'mtow' => 768000, 'mlw' => 492000], 'PMDG - Standard');
        $this->airframe('B77L', 'LBS', ['oew' => 318680, 'mzfw' => 547000, 'mtow' => 766800, 'mlw' => 575000], 'PMDG - 766,800 MTOW');
        $aircraft = $this->aircraft('B77F');

        $this->artisan('phpvms:align-aircraft-weights', ['--type' => ['B77F'], '--apply' => true])
            ->assertExitCode(0);

        // 547.000 lb = 248.115 kg, el MZFW del 777F (no los 209.106 kg del 200LR).
        $zfw = Aircraft::find($aircraft->id)->zfw->toUnit('kg');
        $this->assertEqualsWithDelta(248115, $zfw, 5);
    }

    /**
     * @param array<string, float> $weights
     */
    private function airframe(string $icao, string $units, array $weights, string $name = 'Airframe de prueba'): void
    {
        DB::table('simbrief_airframes')->insert([
            'icao'        => $icao,
            'name'        => $name,
            'airframe_id' => '123_456',
            'options'     => json_encode($weights + ['wgtunits' => $units]),
            'details'     => '{}',
        ]);
    }

    private function aircraft(string $type = 'B38M'): Aircraft
    {
        $subfleet = Subfleet::factory()->create(['type' => $type]);

        return Aircraft::factory()->create([
            'subfleet_id' => $subfleet->id,
            // Los valores crudos van en libras (unidad interna de masa).
            'dow'         => 93000,
            'zfw'         => 144000,
            'mtow'        => 181000,
            'mlw'         => 153000,
        ]);
    }
}
