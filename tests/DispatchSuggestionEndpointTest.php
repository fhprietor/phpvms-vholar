<?php

namespace Tests;

use App\Contracts\Metar as MetarContract;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Enums\FareType;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint del despacho: el airframe que viaja a SimBrief.
 *
 * Importa la CADENA COMPLETA por HTTP (no solo el servicio): que el campo de
 * perfil se guarde bajo el slug que espera el servicio, que sin campo no se
 * rompa nada, y que el aviso del airframe no se coma las notas del sugerido.
 */
final class DispatchSuggestionEndpointTest extends TestCase
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
    }

    /**
     * @return array{0: User, 1: Flight, 2: Aircraft}
     */
    private function scenario(?string $airframeField, ?string $subfleetSimbriefType = null): array
    {
        $subfleet = Subfleet::factory()->create([
            'type'          => 'A320',
            'cost_block_hour' => 4829,
            'simbrief_type' => $subfleetSimbriefType,
        ]);

        $aircraft = Aircraft::factory()->create([
            'subfleet_id'   => $subfleet->id,
            'icao'          => 'A320',
            'simbrief_type' => null,
        ]);

        $dpt = Airport::factory()->create(['id' => 'D001', 'icao' => 'D001', 'elevation' => 0]);
        $arr = Airport::factory()->create(['id' => 'A001', 'icao' => 'A001', 'elevation' => 0]);

        $flight = Flight::factory()->create([
            'flight_number'  => 100,
            'route_code'     => null,
            'dpt_airport_id' => $dpt->id,
            'arr_airport_id' => $arr->id,
            'flight_time'    => 60,
            'pilot_pay'      => null,
        ]);

        $fareId = DB::table('fares')->insertGetId([
            'code' => 'Y', 'name' => 'Y', 'price' => 180.0, 'cost' => 15.0,
            'capacity' => 1, 'type' => FareType::PASSENGER, 'active' => true,
        ]);
        DB::table('flight_fare')->insert(['flight_id' => $flight->id, 'fare_id' => $fareId]);

        $user = User::factory()->create();

        if ($airframeField !== null) {
            // El campo lo crea la migracion: se reutiliza, que es lo que hace el
            // piloto en su perfil. Asi el test ata migracion y servicio.
            $field = UserField::where('name', 'SimBrief Airframe IDs')->first()
                ?? UserField::create(['name' => 'SimBrief Airframe IDs', 'private' => 1, 'internal' => 0, 'active' => 1]);

            UserFieldValue::create([
                'user_field_id' => $field->id,
                'user_id'       => $user->id,
                'value'         => $airframeField,
            ]);
        }

        return [$user->fresh(), $flight, $aircraft];
    }

    private function suggestFor(User $user, Flight $flight, Aircraft $aircraft)
    {
        return $this->actingAs($user)->getJson(route('vholar.dispatch.suggestion', [
            'flight_id'   => $flight->id,
            'aircraft_id' => $aircraft->id,
            'actype'      => 'A320',
        ]));
    }

    /**
     * @param array<int, array{level: string, text: string}> $notes
     */
    private function airframeNotes(array $notes): array
    {
        return array_values(array_filter($notes, fn ($n) => str_contains($n['text'], 'airframe de SimBrief')));
    }

    public function test_without_the_profile_field_it_sends_the_plain_icao(): void
    {
        [$user, $flight, $aircraft] = $this->scenario(null);

        $response = $this->suggestFor($user, $flight, $aircraft);

        $response->assertOk()
            ->assertJsonPath('simbrief.type', 'A320')
            ->assertJsonPath('simbrief.airframe', null)
            ->assertJsonPath('simbrief.source', 'icao');

        // El sugerido sigue llegando y sin avisos de airframe.
        $this->assertIsInt($response->json('suggestion.pax'));
        $this->assertSame([], $this->airframeNotes($response->json('notes')));
    }

    public function test_with_the_profile_field_it_sends_the_pilot_airframe(): void
    {
        [$user, $flight, $aircraft] = $this->scenario('A320=1199171_1700000000000');

        $response = $this->suggestFor($user, $flight, $aircraft);

        $response->assertOk()
            ->assertJsonPath('simbrief.type', '1199171_1700000000000')
            ->assertJsonPath('simbrief.airframe', '1199171_1700000000000')
            ->assertJsonPath('simbrief.source', 'pilot');

        $notes = $response->json('notes');

        // El aviso del airframe viaja...
        $airframeNotes = $this->airframeNotes($notes);
        $this->assertCount(1, $airframeNotes);
        $this->assertStringContainsString('1199171_1700000000000', $airframeNotes[0]['text']);

        // ...SIN comerse las notas del sugerido (regresion del operador `+`).
        $this->assertGreaterThan(1, count($notes));
        $this->assertNotEmpty(array_filter($notes, fn ($n) => str_contains($n['text'], 'Margen')));
    }

    public function test_a_pilot_field_for_another_type_does_not_apply(): void
    {
        [$user, $flight, $aircraft] = $this->scenario('B738=1199171_1700000000000');

        $this->suggestFor($user, $flight, $aircraft)
            ->assertOk()
            ->assertJsonPath('simbrief.type', 'A320')
            ->assertJsonPath('simbrief.source', 'icao');
    }

    public function test_the_subfleet_airframe_is_used_when_the_pilot_has_none(): void
    {
        [$user, $flight, $aircraft] = $this->scenario(null, 'sf_airframe_1234');

        $response = $this->suggestFor($user, $flight, $aircraft);

        $response->assertOk()
            ->assertJsonPath('simbrief.type', 'sf_airframe_1234')
            ->assertJsonPath('simbrief.source', 'subfleet');

        $this->assertCount(1, $this->airframeNotes($response->json('notes')));
    }

    public function test_an_unknown_flight_is_a_clean_404(): void
    {
        [$user] = $this->scenario(null);

        $this->actingAs($user)
            ->getJson(route('vholar.dispatch.suggestion', ['flight_id' => 'no-existe']))
            ->assertStatus(404)
            ->assertJsonPath('ok', false);
    }
}
