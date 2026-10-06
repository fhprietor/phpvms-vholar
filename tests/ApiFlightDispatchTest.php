<?php

namespace Tests;

use App\Contracts\Metar as MetarContract;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Enums\FareType;
use App\Models\Flight;
use App\Models\Rank;
use App\Models\Setting;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\SimBriefUrlService;
use Illuminate\Support\Facades\DB;

/**
 * Despacho por API para el cliente ACARS: `GET /api/flights/{id}/dispatch`.
 *
 * Lo que se protege aqui:
 *  - que el cliente reciba la URL de SimBrief ya montada y con el sugerido;
 *  - que el `fl` viaje en PIES (el bug que costo caro: 330 para 33.000 ft);
 *  - la disponibilidad: vuelo inexistente, avion que no le toca, sin avion.
 */
final class ApiFlightDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sin METAR de verdad: el endpoint no debe salir a la red en los tests.
        $this->app->bind(MetarContract::class, fn () => new class extends MetarContract {
            protected function get_metar($icao): string
            {
                return '';
            }

            protected function get_taf($icao): string
            {
                return '';
            }
        });

        // El seeder trae 0,7 de precio de combustible; la web opera con 0,9. Se
        // fija aqui para que las cifras esperadas sean deterministas.
        Setting::where('key', 'airports.default_jet_a_fuel_cost')->update(['value' => 0.9]);
        Setting::where('key', 'finance.revenue_pax_mix')->update(['value' => '80,15,5']);
        Setting::where('key', 'finance.revenue_pax_mix_by_type')->update(['value' => '']);
        Setting::where('key', 'simbrief.baggage_by_class')->update(['value' => '10,23,46']);
        Setting::where('key', 'simbrief.noncharter_pax_weight')->update(['value' => 170]);
    }

    /**
     * @param array<string, mixed> $flightAttrs
     *
     * @return array{0: User, 1: Flight, 2: Aircraft}
     */
    private function scenario(array $flightAttrs = []): array
    {
        $subfleet = Subfleet::factory()->create([
            'type'            => 'A320',
            'cost_block_hour' => 4829,
        ]);

        // Tarifa de rango a 0: el sugerido no debe depender del azar del factory.
        $rank = Rank::factory()->create([
            'acars_base_pay_rate'  => 0,
            'manual_base_pay_rate' => 0,
        ]);
        $rank->subfleets()->syncWithoutDetaching([$subfleet->id]);

        $user = User::factory()->create(['rank_id' => $rank->id, 'name' => 'PILOTO DE PRUEBA']);

        $aircraft = Aircraft::factory()->create([
            'subfleet_id'   => $subfleet->id,
            'icao'          => 'A320',
            'registration'  => 'HK6251',
        ]);

        $dpt = Airport::factory()->create(['id' => 'D001', 'icao' => 'D001', 'elevation' => 0]);
        $arr = Airport::factory()->create(['id' => 'A001', 'icao' => 'A001', 'elevation' => 0]);

        $flight = Flight::factory()->create(array_merge([
            'flight_number'  => 378,
            'route_code'     => null,
            'dpt_airport_id' => $dpt->id,
            'arr_airport_id' => $arr->id,
            'flight_time'    => 60,
            'level'          => 0,
            'route'          => null,
            'pilot_pay'      => null,
        ], $flightAttrs));

        // Una tarifa de pasaje para que el sugerido sea calculable.
        $fareId = DB::table('fares')->insertGetId([
            'code' => 'Y', 'name' => 'Y', 'price' => 180.0, 'cost' => 15.0,
            'capacity' => 1, 'type' => FareType::PASSENGER, 'active' => true,
        ]);
        DB::table('flight_fare')->insert(['flight_id' => $flight->id, 'fare_id' => $fareId]);

        return [$user->fresh(), $flight, $aircraft];
    }

    private function callApi(User $user, string $flightId, array $query = [])
    {
        return $this->withHeaders($this->headers($user))
            ->getJson('/api/flights/'.$flightId.'/dispatch?'.http_build_query($query));
    }

    public function test_it_returns_the_simbrief_url_with_the_suggested_load(): void
    {
        [$user, $flight, $aircraft] = $this->scenario();

        $response = $this->callApi($user, $flight->id, ['aircraft_id' => $aircraft->id]);

        $response->assertOk();

        // 1 h de A320: coste fijo 1 x (4.829 + 5.080 lb/h x 0,9) + 15 = 9.416 ->
        // el menor pasaje que cubre +20 % con una tarifa de 180 y coste 15 es 70.
        $this->assertSame(70, $response->json('suggestion.pax'));
        $this->assertSame('70', $response->json('simbrief.params.pax'));
        $this->assertSame('A320', $response->json('simbrief.params.type'));
        $this->assertSame('HK6251', $response->json('simbrief.params.reg'));
        $this->assertSame('PILOTO DE PRUEBA', $response->json('simbrief.params.cpt'));
        $this->assertStringStartsWith(SimBriefUrlService::BASE_URL.'?', $response->json('simbrief.url'));

        // Sin carga que completar, no se manda el parametro.
        $this->assertArrayNotHasKey('cargo', $response->json('simbrief.params'));

        // Parametros segun la tabla oficial y el formulario del core.
        $params = $response->json('simbrief.params');
        $this->assertSame('detail', $params['maps']);
        $this->assertArrayNotHasKey('static_url', $params);

        // El Item 18 sale del setting, con el remark de la aerolinea por defecto.
        $this->assertSame('CS/VHOLAR IVAOVA/VHR OPR/VHR', $params['extrarmk']);

        // `acdata` lleva NUESTROS pesos medios: 170 lb de pasajero y, con una
        // sola clase, el primer tramo de equipaje (10 kg -> 22 lb). Sin esto
        // SimBrief planifica con 175/55 lb y recorta la carga.
        $this->assertSame('{"paxwgt":170,"bagwgt":22}', $params['acdata']);
    }

    public function test_the_baggage_average_goes_in_the_acdata(): void
    {
        [$user, $flight, $aircraft] = $this->scenario();

        // Tres clases (la mas barata primero) y mix 80/15/5 -> 13,75 kg de media
        // -> 30 lb. Es el caso real de las tarifas L/C/F de la aerolinea.
        foreach ([['L', 100.0], ['C', 200.0], ['F', 400.0]] as [$code, $price]) {
            $fareId = DB::table('fares')->insertGetId([
                'code' => $code, 'name' => $code, 'price' => $price, 'cost' => 15.0,
                'capacity' => 1, 'type' => FareType::PASSENGER, 'active' => true,
            ]);
            DB::table('flight_fare')->insert(['flight_id' => $flight->id, 'fare_id' => $fareId]);
        }

        $response = $this->callApi($user, $flight->id, ['aircraft_id' => $aircraft->id]);

        $response->assertOk();
        $this->assertStringContainsString('"bagwgt":30', $response->json('simbrief.params.acdata'));
    }

    /**
     * `cargo` va en MILES de la unidad de `units`: con `units=kgs`, 13.498 kg se
     * manda como `cargo=13.498`. En kg SimBrief lo lee como 13.498.000 kg y lo
     * recorta al maximo (el campo Freight abria con el payload maximo).
     */
    public function test_the_cargo_goes_in_thousands_of_the_selected_unit(): void
    {
        [$user, $flight, $aircraft] = $this->scenario(['flight_time' => 300]);

        // Una tarifa de carga, que es lo que hace que el sugerido lleve carga.
        $cargoFareId = DB::table('fares')->insertGetId([
            'code' => 'CGO', 'name' => 'CGO', 'price' => 2.8, 'cost' => 0.65,
            'capacity' => 1, 'type' => FareType::CARGO, 'active' => true,
        ]);
        DB::table('subfleet_fare')->insert([
            'subfleet_id' => $aircraft->subfleet_id,
            'fare_id'     => $cargoFareId,
        ]);

        $response = $this->callApi($user, $flight->id, ['aircraft_id' => $aircraft->id])->assertOk();

        $cargoKg = (float) $response->json('suggestion.cargo');
        $this->assertGreaterThan(1000, $cargoKg, 'el caso de prueba debe tener carga que cubrir');
        $this->assertTrue($response->json('suggestion.target_reached'));

        $this->assertEqualsWithDelta(
            round($cargoKg / 1000, 3),
            (float) $response->json('simbrief.params.cargo'),
            0.001
        );
    }

    public function test_the_item_18_remark_is_configurable(): void
    {
        Setting::where('key', 'simbrief.extrarmk')->update(['value' => 'RMK/PRUEBA VHR OPR/VHR']);

        [$user, $flight, $aircraft] = $this->scenario();

        $this->callApi($user, $flight->id, ['aircraft_id' => $aircraft->id])
            ->assertOk()
            ->assertJsonPath('simbrief.params.extrarmk', 'RMK/PRUEBA VHR OPR/VHR');

        // Vacio = no se manda el parametro.
        Setting::where('key', 'simbrief.extrarmk')->update(['value' => '']);

        $params = $this->callApi($user, $flight->id, ['aircraft_id' => $aircraft->id])
            ->assertOk()->json('simbrief.params');

        $this->assertArrayNotHasKey('extrarmk', $params);
    }

    public function test_the_flight_level_is_sent_in_feet(): void
    {
        [$user, $flight, $aircraft] = $this->scenario();

        // Sin nivel en el vuelo: el criterio del modal para un A320 es 35.000.
        $this->callApi($user, $flight->id, ['aircraft_id' => $aircraft->id])
            ->assertOk()
            ->assertJsonPath('simbrief.params.fl', '35000');

        // Con nivel en el vuelo, manda el del vuelo.
        DB::table('flights')->where('id', $flight->id)->update(['level' => 29000]);

        $url = $this->callApi($user, $flight->id, ['aircraft_id' => $aircraft->id])
            ->assertOk()
            ->assertJsonPath('simbrief.params.fl', '29000')
            ->json('simbrief.url');

        // Y en la URL tampoco aparece en centenas.
        $this->assertStringContainsString('fl=29000', $url);
        $this->assertStringNotContainsString('fl=290&', $url);
    }

    public function test_the_route_and_the_departure_time_are_optional(): void
    {
        [$user, $flight, $aircraft] = $this->scenario(['route' => 'DCT CMK V374 DENNA']);

        $params = $this->callApi($user, $flight->id, [
            'aircraft_id' => $aircraft->id,
            'dep_time'    => '0830',
        ])->assertOk()->json('simbrief.params');

        $this->assertSame('DCT CMK V374 DENNA', $params['route']);
        $this->assertSame('08', $params['deph']);
        $this->assertSame('30', $params['depm']);

        // Sin ruta no se manda el parametro (SimBrief genera la suya).
        DB::table('flights')->where('id', $flight->id)->update(['route' => null]);

        $params = $this->callApi($user, $flight->id, ['aircraft_id' => $aircraft->id])
            ->assertOk()->json('simbrief.params');

        $this->assertArrayNotHasKey('route', $params);
    }

    public function test_a_charter_gets_a_url_but_no_suggested_load(): void
    {
        [$user, $flight, $aircraft] = $this->scenario(['route_code' => 'CH']);

        $response = $this->callApi($user, $flight->id, ['aircraft_id' => $aircraft->id]);

        $response->assertOk()->assertJsonPath('applicable', false);

        $params = $response->json('simbrief.params');
        $this->assertArrayNotHasKey('pax', $params);
        $this->assertArrayNotHasKey('cargo', $params);
        $this->assertStringStartsWith(SimBriefUrlService::BASE_URL.'?', $response->json('simbrief.url'));
    }

    public function test_an_unknown_flight_is_a_clean_404(): void
    {
        [$user] = $this->scenario();

        $this->callApi($user, 'no-existe')
            ->assertStatus(404)
            ->assertJsonPath('error', 'flight_not_found');
    }

    public function test_without_an_aircraft_it_is_a_422(): void
    {
        [$user, $flight] = $this->scenario();

        $this->callApi($user, $flight->id)
            ->assertStatus(422)
            ->assertJsonPath('error', 'aircraft_not_found');

        $this->callApi($user, $flight->id, ['aircraft_id' => 'no-existe'])
            ->assertStatus(422);
    }

    public function test_an_aircraft_the_pilot_cannot_fly_is_rejected(): void
    {
        [$user, $flight] = $this->scenario();

        // Otra subflota, fuera del rango del piloto.
        $other = Subfleet::factory()->create(['type' => 'B738']);
        $otherAircraft = Aircraft::factory()->create(['subfleet_id' => $other->id, 'icao' => 'B738']);

        $this->callApi($user, $flight->id, ['aircraft_id' => $otherAircraft->id])
            ->assertStatus(403)
            ->assertJsonPath('error', 'aircraft_not_allowed');
    }

    public function test_the_url_encodes_the_parameters(): void
    {
        $url = app(SimBriefUrlService::class)->url([
            'airline'  => 'VHR',
            'extrarmk' => 'OPR/VHR CS/X',
        ]);

        $this->assertStringStartsWith(SimBriefUrlService::BASE_URL.'?', $url);
        $this->assertStringContainsString('airline=VHR', $url);
        $this->assertStringContainsString('extrarmk=OPR%2FVHR+CS%2FX', $url);
    }
}
