<?php

namespace Tests;

use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Enums\FareType;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\Setting;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\DispatchSuggestionService;
use App\Services\PirepEconomicsService;
use Illuminate\Support\Facades\DB;

/**
 * Sugerido de PAX/carga del despacho.
 *
 * Lo que se protege aqui:
 *  - el objetivo es el MENOR numero de PAX que cubre coste + 20 %, no una media
 *    de tarifas (el reparto por clases es el mismo que el del PIREP);
 *  - el coste lleva el combustible medido por tipo, el pago al piloto y el
 *    coste por pasajero/carga del libro, no la aproximacion del traspaso;
 *  - el recorte de plazas por elevacion y temperatura;
 *  - la carga como complemento cuando el avion no da para el objetivo;
 *  - CH/FR excluidos por sufijo.
 */
final class DispatchSuggestionServiceTest extends TestCase
{
    private DispatchSuggestionService $service;

    /** Los ICAO de los aeropuertos de prueba deben ser unicos entre llamadas. */
    private static int $airportSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DispatchSuggestionService::class);

        Setting::where('key', 'airports.default_jet_a_fuel_cost')->update(['value' => 0.9]);
        Setting::where('key', 'finance.revenue_pax_mix')->update(['value' => '80,15,5']);
        Setting::where('key', 'finance.revenue_pax_mix_by_type')->update(['value' => '']);
        Setting::where('key', 'finance.revenue_estimate_enabled')->update(['value' => 1]);
    }

    /**
     * @param array{type?:string, block_hour?:float, flight_time?:int, elevation?:int} $opts
     *
     * @return array{flight: Flight, aircraft: Aircraft, subfleet: Subfleet}
     */
    private function setupFlight(array $opts = []): array
    {
        $type = $opts['type'] ?? 'A320';
        $blockHour = $opts['block_hour'] ?? 4829.0;
        $minutes = $opts['flight_time'] ?? 60;
        $elevation = $opts['elevation'] ?? 0;

        $subfleet = Subfleet::factory()->create([
            'type'            => $type,
            'cost_block_hour' => $blockHour,
        ]);

        $aircraft = Aircraft::factory()->create([
            'subfleet_id' => $subfleet->id,
            'icao'        => $type,
        ]);

        $dptId = 'D'.str_pad((string) ++self::$airportSeq, 3, '0', STR_PAD_LEFT);
        $arrId = 'A'.str_pad((string) ++self::$airportSeq, 3, '0', STR_PAD_LEFT);

        $dpt = Airport::factory()->create(['id' => $dptId, 'icao' => $dptId, 'elevation' => $elevation]);
        $arr = Airport::factory()->create(['id' => $arrId, 'icao' => $arrId, 'elevation' => 0]);

        $flight = Flight::factory()->create([
            'flight_number'  => 100,
            'route_code'     => null,
            'dpt_airport_id' => $dpt->id,
            'arr_airport_id' => $arr->id,
            'flight_time'    => $minutes,
            'pilot_pay'      => null,
        ]);

        return ['flight' => $flight, 'aircraft' => $aircraft->load('subfleet'), 'subfleet' => $subfleet];
    }

    private function addPassengerFare(Flight $flight, string $code, float $price, float $cost = 15.0): void
    {
        $id = DB::table('fares')->insertGetId([
            'code' => $code, 'name' => $code, 'price' => $price, 'cost' => $cost,
            'capacity' => 1, 'type' => FareType::PASSENGER, 'active' => true,
        ]);

        DB::table('flight_fare')->insert(['flight_id' => $flight->id, 'fare_id' => $id]);
    }

    private function addCargoFare(Subfleet $subfleet, string $code, float $price, float $cost): void
    {
        $id = DB::table('fares')->insertGetId([
            'code' => $code, 'name' => $code, 'price' => $price, 'cost' => $cost,
            'capacity' => 1, 'type' => FareType::CARGO, 'active' => true,
        ]);

        DB::table('subfleet_fare')->insert(['subfleet_id' => $subfleet->id, 'fare_id' => $id]);
    }

    // --------------------------------------------------------------- minimo rentable

    public function test_pax_is_the_smallest_load_that_covers_cost_plus_margin(): void
    {
        $ctx = $this->setupFlight(['flight_time' => 60, 'block_hour' => 4829]);
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0, 15.0);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 15.0);

        $this->assertTrue($result['applicable']);

        // Coste fijo: 1 h x (4.829 bloque + 5.080 lb/h x 0,9 = 4.572) + 15 = 9.416.
        $this->assertEqualsWithDelta(9416.0, $result['costs']['fixed_total'], 0.01);
        $this->assertEqualsWithDelta(4572.0, $result['costs']['fuel_hour'], 0.01);

        // 180n >= 1,2 x (9.416 + 15n) -> n >= 69,75 -> 70.
        $this->assertSame(70, $result['suggestion']['pax']);
        $this->assertSame(0, $result['suggestion']['cargo']);
        $this->assertTrue($result['suggestion']['target_reached']);
        $this->assertGreaterThanOrEqual(DispatchSuggestionService::MARGIN * 100 - 100, $result['suggestion']['margin_pct']);
    }

    public function test_one_fewer_passenger_would_not_reach_the_target(): void
    {
        $ctx = $this->setupFlight(['flight_time' => 60, 'block_hour' => 4829]);
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0, 15.0);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 15.0);
        $pax = $result['suggestion']['pax'];

        $this->assertSame(70, $pax);
        // 69 x 180 = 12.420 < 1,2 x (9.416 + 69 x 15) = 12.541,20.
        $this->assertLessThan(1.2 * (9416.0 + 69 * 15.0), 69 * 180.0);
    }

    public function test_the_suggested_revenue_matches_the_pirep_income_estimator(): void
    {
        $ctx = $this->setupFlight(['flight_time' => 60]);
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0, 15.0);
        $this->addPassengerFare($ctx['flight'], 'C', 520.0, 15.0);
        $this->addCargoFare($ctx['subfleet'], 'CGO', 2.8, 0.65);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 15.0);

        $pirep = Pirep::factory()->create([
            'flight_id'   => $ctx['flight']->id,
            'aircraft_id' => $ctx['aircraft']->id,
            'flight_time' => 60,
            'source'      => 1,
        ]);

        $estimate = app(PirepEconomicsService::class)
            ->estimateRevenue($pirep->fresh(), ['pax' => $result['suggestion']['pax'], 'cargo' => 0]);

        $this->assertNotNull($estimate);
        $this->assertEqualsWithDelta($estimate['total'], $result['suggestion']['pax_revenue'], 0.01);
    }

    // ------------------------------------------------- operaciones no regulares

    /**
     * La marca real la pone el Centro de Operaciones en `flights.route_code`
     * (`CharterController::create()`): CH charter de pasaje, CA charter de carga,
     * PS posicionamiento y FR ferry. Ninguna es una operacion comercial de
     * pasaje, asi que las cuatro quedan fuera. El formulario tambien deja el
     * callsign como `<user_id><codigo>`.
     */
    public function test_non_scheduled_route_codes_are_excluded(): void
    {
        $ctx = $this->setupFlight();
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0);

        foreach (['CH', 'CA', 'PS', 'FR'] as $code) {
            DB::table('flights')->where('id', $ctx['flight']->id)->update(['route_code' => $code]);

            $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 15.0);

            $this->assertFalse($result['applicable'], $code.' deberia quedar fuera');
            $this->assertSame('charter_or_ferry', $result['reason']);
            $this->assertSame($code, $result['route_code']);
        }
    }

    /**
     * Un vuelo regular no lleva marca: el sugerido se calcula. Es el contraste
     * que impide que la comprobacion se coma toda la programacion.
     */
    public function test_a_scheduled_flight_without_route_code_gets_a_suggestion(): void
    {
        $ctx = $this->setupFlight();
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 15.0);

        $this->assertTrue($result['applicable']);
        $this->assertNull($result['route_code']);
    }

    /**
     * Respaldo: si el numero de vuelo llegase con sufijo (hoy es entero y no
     * puede), tambien queda fuera. Se prueba sobre el atributo crudo, igual que
     * los PIREPs "025CH".
     */
    public function test_a_suffixed_flight_number_is_excluded_as_a_fallback(): void
    {
        $ctx = $this->setupFlight();
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0);

        foreach (['100CH', '100FR'] as $number) {
            $flight = $ctx['flight']->fresh();
            $flight->flight_number = $number;
            $flight->syncOriginalAttribute('flight_number');

            $result = $this->service->suggest($flight, $ctx['aircraft'], null, 15.0);

            $this->assertFalse($result['applicable'], $number.' deberia quedar fuera');
            $this->assertSame('charter_or_ferry', $result['reason']);
        }
    }

    // ------------------------------------------------------------------ rendimiento

    public function test_elevation_and_temperature_cut_the_available_seats(): void
    {
        $ctx = $this->setupFlight(['elevation' => 8361]);
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0);

        // ISA en SKBO (8.361 ft) = 15 - 2 x 8,361 = -1,7 ºC; 14 ºC -> 15,7 de delta.
        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 14.0);

        $this->assertSame(180, $result['limits']['seats']);
        $this->assertSame(159, $result['limits']['seats_max']);
        $this->assertEqualsWithDelta(8.4, $result['limits']['elevation_pct'], 0.1);
        $this->assertEqualsWithDelta(3.1, $result['limits']['temperature_pct'], 0.1);
        $this->assertEqualsWithDelta(-1.7, $result['weather']['isa_c'], 0.1);

        $texts = implode(' ', array_column($result['notes'], 'text'));
        $this->assertStringContainsString('Rendimiento', $texts);
        // Convenio es-CO, el mismo que pinta el modal en el titulo.
        $this->assertStringContainsString('8.361 ft', $texts);
    }

    public function test_a_cold_day_at_altitude_does_not_penalise_temperature(): void
    {
        $ctx = $this->setupFlight(['elevation' => 8361]);
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0);

        // -5 ºC esta por debajo de la ISA del campo: solo recorta la elevacion.
        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, -5.0);

        $this->assertSame(0.0, $result['limits']['temperature_pct']);
        $this->assertSame(164, $result['limits']['seats_max']);
    }

    // ------------------------------------------------------------------ carga

    public function test_cargo_fills_the_gap_when_seats_are_not_enough(): void
    {
        // 5 h: el pasaje nunca cubre el objetivo, la bodega si.
        $ctx = $this->setupFlight(['flight_time' => 300, 'block_hour' => 4829]);
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0, 15.0);
        $this->addCargoFare($ctx['subfleet'], 'CGO', 2.8, 0.65);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 15.0);

        $this->assertSame(180, $result['suggestion']['pax'], 'el pasaje va al maximo de plazas');
        $this->assertGreaterThan(0, $result['suggestion']['cargo']);
        $this->assertTrue($result['suggestion']['target_reached']);
        $this->assertGreaterThanOrEqual(19.9, $result['suggestion']['margin_pct']);
    }

    public function test_a_freighter_goes_all_cargo(): void
    {
        $ctx = $this->setupFlight(['type' => 'A306', 'block_hour' => 13000, 'flight_time' => 300]);
        $this->addCargoFare($ctx['subfleet'], 'CGO', 2.8, 0.65);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 15.0);

        $this->assertTrue($result['applicable']);
        $this->assertSame(0, $result['limits']['seats']);
        $this->assertSame(0, $result['suggestion']['pax']);
        $this->assertGreaterThan(0, $result['suggestion']['cargo']);
        $this->assertTrue($result['suggestion']['target_reached']);
    }

    public function test_without_a_cargo_fare_the_target_is_reported_unreachable(): void
    {
        $ctx = $this->setupFlight(['flight_time' => 300, 'block_hour' => 4829]);
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0, 15.0);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 15.0);

        $this->assertFalse($result['suggestion']['target_reached']);
        $this->assertSame(0, $result['suggestion']['cargo']);

        $texts = implode(' ', array_column($result['notes'], 'text'));
        $this->assertStringContainsString('no alcanzable', $texts);
    }

    public function test_an_unknown_type_gets_no_suggestion(): void
    {
        $ctx = $this->setupFlight(['type' => 'ZZZZ']);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], null, 15.0);

        $this->assertFalse($result['applicable']);
        $this->assertSame('unknown_type', $result['reason']);
    }

    // ------------------------------------------------------------------ pago al piloto

    public function test_the_pilot_pay_of_the_rank_enters_the_cost(): void
    {
        $rank = Rank::factory()->create(['acars_base_pay_rate' => 200]);
        $user = User::factory()->create(['rank_id' => $rank->id]);

        $ctx = $this->setupFlight(['flight_time' => 60]);
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], $user, 15.0);

        $this->assertEqualsWithDelta(200.0, $result['costs']['pilot_hour'], 0.01);
        // 4.829 bloque + 4.572 combustible + 200 piloto + 15 fijo = 9.616.
        $this->assertEqualsWithDelta(9616.0, $result['costs']['fixed_total'], 0.01);
    }

    public function test_a_fixed_pilot_pay_on_the_flight_wins_over_the_rank_rate(): void
    {
        $rank = Rank::factory()->create(['acars_base_pay_rate' => 200]);
        $user = User::factory()->create(['rank_id' => $rank->id]);

        $ctx = $this->setupFlight(['flight_time' => 60]);
        DB::table('flights')->where('id', $ctx['flight']->id)->update(['pilot_pay' => 500]);
        $this->addPassengerFare($ctx['flight'], 'Y', 180.0);

        $result = $this->service->suggest($ctx['flight']->fresh(), $ctx['aircraft'], $user, 15.0);

        $this->assertSame(0.0, $result['costs']['pilot_hour']);
        $this->assertEqualsWithDelta(500.0, $result['costs']['pilot_fixed'], 0.01);
        $this->assertEqualsWithDelta(4829.0 + 4572.0 + 500.0 + 15.0, $result['costs']['fixed_total'], 0.01);
    }
}
