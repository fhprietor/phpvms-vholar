<?php

namespace Tests;

use App\Http\Resources\Pirep as PirepResource;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\AircraftState;
use App\Models\Enums\PirepFieldSource;
use App\Models\Enums\PirepSource;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\AirspaceService;
use App\Services\PirepService;

/**
 * Prefile con `route_navlog` (contrato v1 de vmsOpenAcars): el servidor avisa si la ruta
 * cruza un area restringida, usando la altitud de cada punto; `route_ack` se guarda tal cual.
 *
 * No toca la red: el cache de areas se inyecta como fichero temporal con la cobertura ya
 * resuelta, y la altitud viaja en el propio navlog.
 */
final class RouteNavlogPrefileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');

        $settings = app(SettingRepository::class);
        $settings->store('pireps.restrict_aircraft_to_rank', false);
        $settings->store('pireps.restrict_aircraft_to_typerating', false);
        $settings->store('pireps.only_aircraft_at_dpt_airport', false);
        $settings->store('pireps.only_flights_from_current', false);
    }

    /**
     * Caja 9.5..9.8 N, 74.2..73.9 W alrededor de la ruta 9.4N -> 9.9N.
     *
     * @return array<int, array<int, float>>
     */
    private function box(): array
    {
        return [[-74.2, 9.5], [-73.9, 9.5], [-73.9, 9.8], [-74.2, 9.8], [-74.2, 9.5]];
    }

    private function bindAirspaceService(string $upper = 'FL999'): void
    {
        $areas = ['SKR99 / TEST' => [
            'name'  => 'SKR99 / TEST', 'type' => 'Restricted', 'lower' => 'GND', 'upper' => $upper,
            'rings' => [$this->box()],
        ]];

        // Celdas de los dos puntos de la ruta (0,25 grados): filas 37 y 39, columna -296.
        $cells = ['37,-296' => ['SKR99 / TEST'], '39,-296' => ['SKR99 / TEST']];

        $file = tempnam(sys_get_temp_dir(), 'asp').'.json';
        file_put_contents($file, json_encode(['areas' => $areas, 'cells' => $cells]));

        $this->app->instance(AirspaceService::class, new AirspaceService($file));
    }

    /**
     * @param array<string, mixed>  $attrs
     * @param array<string, string> $fields
     */
    private function prefile(array $attrs = [], array $fields = []): Pirep
    {
        foreach (['SKBO' => 'Bogota', 'SKCG' => 'Cartagena'] as $id => $name) {
            if (Airport::find($id) === null) {
                Airport::factory()->create(['id' => $id, 'name' => $name]);
            }
        }

        $flight = Flight::factory()->create([
            'airline_id'     => 1,
            'flight_number'  => 863,
            'dpt_airport_id' => 'SKBO',
            'arr_airport_id' => 'SKCG',
        ]);

        $aircraft = Aircraft::factory()->create([
            'airport_id' => 'SKBO',
            'state'      => AircraftState::PARKED,
        ]);

        $user = User::factory()->create([
            'airline_id'      => 1,
            'curr_airport_id' => 'SKBO',
        ]);

        $field_values = [];
        foreach ($fields as $name => $value) {
            $field_values[] = ['name' => $name, 'value' => $value, 'source' => PirepFieldSource::ACARS];
        }

        return app(PirepService::class)->prefile($user, array_merge([
            'airline_id'     => 1,
            'aircraft_id'    => $aircraft->id,
            'flight_id'      => $flight->id,
            'flight_number'  => 863,
            'dpt_airport_id' => 'SKBO',
            'arr_airport_id' => 'SKCG',
            'source'         => PirepSource::ACARS,
            'source_name'    => 'RouteNavlogPrefileTest',
            'flight_time'    => 90,
            'distance'       => 400,
            'fuel_used'      => 0,
        ], $attrs), $field_values);
    }

    public function test_a_crossing_navlog_leaves_the_warning_fields_and_the_response(): void
    {
        $this->bindAirspaceService();

        $pirep = $this->prefile([
            'route_navlog'         => 'AAAA,9.4,-74.0,11500;BBBB,9.9,-74.0,11500',
            'route_navlog_version' => 1,
        ]);

        $fields = $pirep->field_values()->pluck('value', 'name');

        $this->assertSame('SKR99 / TEST', $fields['route_airspace'] ?? null);
        $this->assertSame('GND–FL999', $fields['route_limits'] ?? null);
        $this->assertSame('AAAA–BBBB', $fields['route_leg'] ?? null);

        // Contrato con el cliente: route_warning viaja en el recurso del PIREP.
        $resource = (new PirepResource($pirep->fresh()))->resolve();
        $this->assertSame('SKR99 / TEST', $resource['route_warning']['area'] ?? null);
        $this->assertSame('AAAA–BBBB', $resource['route_warning']['leg'] ?? null);
    }

    public function test_a_navlog_above_the_area_ceiling_does_not_warn(): void
    {
        // El caso SVR2508: area GND-3000ft MSL, SID a 11.500ft.
        $this->bindAirspaceService('3000ft MSL');

        $pirep = $this->prefile([
            'route_navlog'         => 'AAAA,9.4,-74.0,11500;BBBB,9.9,-74.0,11500',
            'route_navlog_version' => 1,
        ]);

        $this->assertNull($pirep->field_values()->where('name', 'route_airspace')->first());
    }

    public function test_without_a_navlog_there_is_no_warning(): void
    {
        $this->bindAirspaceService();

        $pirep = $this->prefile();

        $this->assertNull($pirep->field_values()->where('name', 'route_airspace')->first());
    }

    public function test_a_future_navlog_version_is_ignored(): void
    {
        $this->bindAirspaceService();

        $pirep = $this->prefile([
            'route_navlog'         => 'AAAA,9.4,-74.0,11500;BBBB,9.9,-74.0,11500',
            'route_navlog_version' => 2,
        ]);

        $this->assertNull($pirep->field_values()->where('name', 'route_airspace')->first());
    }

    public function test_route_ack_is_saved_verbatim(): void
    {
        $this->bindAirspaceService();

        $pirep = $this->prefile([], ['route_ack' => '1']);

        $this->assertSame('1', $pirep->field_values()->where('name', 'route_ack')->value('value'));
    }

    public function test_the_level_can_come_as_a_flight_level_string(): void
    {
        $this->bindAirspaceService();

        $pirep = $this->prefile(['level' => 'FL340']);

        $this->assertSame(34000, (int) $pirep->level);
    }

    public function test_the_api_accepts_the_clients_navlog_and_flight_level(): void
    {
        $this->bindAirspaceService();

        $airport = Airport::factory()->create();
        $airline = Airline::factory()->create();
        $aircraft = Aircraft::factory()->create(['airport_id' => $airport->id]);

        $this->user = User::factory()->create([
            'airline_id'      => $airline->id,
            'curr_airport_id' => $airport->id,
        ]);

        $response = $this->post('/api/pireps/prefile', [
            'airline_id'           => $airline->id,
            'aircraft_id'          => $aircraft->id,
            'dpt_airport_id'       => $airport->id,
            'arr_airport_id'       => $airport->id,
            'flight_number'        => '863',
            'level'                => 'FL340',
            'route_navlog'         => 'AAAA,9.4,-74.0,11500;BBBB,9.9,-74.0,11500',
            'route_navlog_version' => 1,
            'source_name'          => 'RouteNavlogPrefileTest',
        ]);

        $response->assertStatus(200);
        $this->assertSame('SKR99 / TEST', $response->json('data.route_warning.area'));
        $this->assertSame('GND–FL999', $response->json('data.route_warning.limits'));

        $this->assertSame(34000, (int) Pirep::where('flight_number', '863')->first()->level);
    }
}
