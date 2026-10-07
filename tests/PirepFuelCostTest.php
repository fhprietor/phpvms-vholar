<?php

namespace Tests;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Flight;
use App\Models\JournalTransaction;
use App\Models\Pirep;
use App\Models\User;
use App\Services\Finance\PirepFinanceService;

/**
 * Coste del combustible de un PIREP.
 *
 * Lo que se protege aqui: se cobra el combustible QUEMADO (`fuel_used`), no el
 * repostado. El modo antiguo (`pireps.advanced_fuel`) calculaba el repostaje como
 * `block_fuel` menos el remanente del vuelo anterior, asi que dos vuelos iguales
 * pagaban distinto segun lo que hubiera repostado otro antes.
 */
final class PirepFuelCostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');
        $this->addData('fleet');
    }

    public function test_the_fuel_cost_is_what_the_flight_burned(): void
    {
        // El vuelo quema 15.000 lb, pero solo reposta 5.000 (le quedaban 20.000
        // del vuelo anterior de 30.000 de block y 10.000 quemadas).
        $pirep = $this->scenario(burned: 15000, blockFuel: 25000, previousBlock: 30000, previousBurned: 10000);

        app(PirepFinanceService::class)->payFuelCosts($pirep);

        // 15.000 lb x 2,0 $/lb = 30.000 $ = 3.000.000 centavos. Si se cobrara el
        // repostaje serian 5.000 lb = 1.000.000.
        $this->assertSame(3000000, (int) $this->fuelDebit($pirep));
    }

    public function test_without_a_previous_flight_it_still_charges_what_was_burned(): void
    {
        $pirep = $this->scenario(burned: 8000, blockFuel: 20000);

        app(PirepFinanceService::class)->payFuelCosts($pirep);

        // 8.000 lb x 2,0 = 1.600.000 centavos (el block fuel serian 4.000.000).
        $this->assertSame(1600000, (int) $this->fuelDebit($pirep));
    }

    private function fuelDebit(Pirep $pirep): int
    {
        return (int) JournalTransaction::where('ref_model', Pirep::class)
            ->where('ref_model_id', $pirep->id)
            ->where('memo', 'like', 'Fuel Cost%')
            ->sum('debit');
    }

    /**
     * Un PIREP con un vuelo anterior (o sin el) sobre el mismo avion, con la
     * salida a 2 $/lb de Jet A.
     */
    private function scenario(int $burned, int $blockFuel, ?int $previousBlock = null, ?int $previousBurned = null): Pirep
    {
        $subfleet = $this->createSubfleetWithAircraft(2);
        $subfleet['subfleet']->cost_block_hour = 10;
        $subfleet['subfleet']->save();

        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);
        $rank->save();

        $user = User::factory()->create(['rank_id' => $rank->id]);
        $user->airline->initJournal('USD');

        $dpt = Airport::factory()->create(['ground_handling_cost' => 0, 'fuel_jeta_cost' => 2.0]);
        $arr = Airport::factory()->create(['ground_handling_cost' => 0, 'fuel_jeta_cost' => 2.0]);

        $flight = Flight::factory()->create([
            'airline_id'     => $user->airline_id,
            'dpt_airport_id' => $dpt->icao,
            'arr_airport_id' => $arr->icao,
        ]);

        $aircraft = $subfleet['aircraft']->first();

        $attrs = [
            'flight_number'  => $flight->flight_number,
            'flight_type'    => FlightType::SCHED_PAX,
            'route_code'     => null,
            'dpt_airport_id' => $dpt->id,
            'arr_airport_id' => $arr->id,
            'user_id'        => $user->id,
            'airline_id'     => $user->airline_id,
            'aircraft_id'    => $aircraft->id,
            'flight_id'      => $flight->id,
            'source'         => PirepSource::ACARS,
            'flight_time'    => 120,
        ];

        if ($previousBlock !== null) {
            Pirep::factory()->create($attrs + [
                'block_fuel'   => $previousBlock,
                'fuel_used'    => $previousBurned,
                'state'        => PirepState::ACCEPTED,
                'status'       => PirepStatus::ARRIVED,
                'submitted_at' => now()->subHour(),
            ]);
        }

        return Pirep::factory()->create($attrs + [
            'block_fuel'   => $blockFuel,
            'fuel_used'    => $burned,
            'state'        => PirepState::PENDING,
            'status'       => PirepStatus::ARRIVED,
            'submitted_at' => now(),
        ]);
    }
}
