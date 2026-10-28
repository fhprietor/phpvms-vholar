<?php

namespace Tests;

use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Enums\AircraftState;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepFieldSource;
use App\Models\Enums\PirepState;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\PirepService;

/**
 * Espacio restringido: un PIREP que el cliente marca en los `fields` NO se auto-aprueba
 * aunque el rango del piloto tenga `auto_approve_acars`. Sin la marca, se comporta como
 * siempre (auto-aprobado).
 */
final class AirspaceReviewTest extends TestCase
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
     * @param array<string, string> $fields
     */
    private function filePirep(array $fields = []): Pirep
    {
        foreach (['SKBO' => 'Bogota', 'SKCG' => 'Cartagena'] as $id => $name) {
            if (Airport::find($id) === null) {
                Airport::factory()->create(['id' => $id, 'name' => $name]);
            }
        }

        $flight = Flight::factory()->create([
            'airline_id'     => 1,
            'flight_number'  => 910,
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

        // El rango auto-aprueba: es justo la decision que la marca debe anular.
        $user->rank->update(['auto_approve_acars' => true]);

        $pirepSvc = app(PirepService::class);

        $pirep = $pirepSvc->prefile($user, [
            'airline_id'     => 1,
            'aircraft_id'    => $aircraft->id,
            'flight_id'      => $flight->id,
            'flight_number'  => 910,
            'dpt_airport_id' => 'SKBO',
            'arr_airport_id' => 'SKCG',
            'source'         => PirepSource::ACARS,
            'source_name'    => 'AirspaceReviewTest',
            // Datos minimos para que el camino de accept() (pirep limpio) no falle.
            'flight_time'    => 90,
            'distance'       => 400,
            'fuel_used'      => 0,
        ]);

        // La API y PirepService::updateCustomFields esperan [{name, value, source}]
        $field_values = [];
        foreach ($fields as $name => $value) {
            $field_values[] = ['name' => $name, 'value' => $value, 'source' => PirepFieldSource::ACARS];
        }

        $pirep = $pirepSvc->file($pirep, [], $field_values, []);

        // La API hace file() y luego submit(): submit() es donde se decide el estado
        // (auto-aprobacion del rango y revision por espacio restringido).
        $pirepSvc->submit($pirep);

        return $pirep->refresh();
    }

    public function test_a_marked_pirep_stays_pending_even_if_the_rank_auto_approves(): void
    {
        $pirep = $this->filePirep(['restricted_airspace' => 'SKEZ']);
        fwrite(STDERR, "\n[diag-mem] notes=".var_export($pirep->notes, true)." state=".$pirep->state."\n");
        $pirep->refresh();

        fwrite(STDERR, sprintf(
            "\n[diag] state=%s fields=%s violacion=%s notes=%s\n",
            $pirep->state,
            \App\Models\PirepFieldValue::where('pirep_id', $pirep->id)->count(),
            var_export(app(\App\Services\AirspaceReviewService::class)->violationFor($pirep), true),
            var_export($pirep->notes, true)
        ));

        $this->assertSame(PirepState::PENDING, $pirep->state);
        $this->assertStringContainsString('Espacio restringido: SKEZ', (string) $pirep->notes);
    }

    public function test_the_service_reads_the_mark_from_the_saved_fields(): void
    {
        $pirep = $this->filePirep(['restricted_zone' => 'SKCG exclusion']);
        $svc = app(\App\Services\AirspaceReviewService::class);

        $this->assertSame('SKCG exclusion', $svc->violationFor($pirep));
        $this->assertTrue($svc->requiresReview($pirep));
    }

    public function test_a_clean_pirep_has_no_violation_and_no_review_note(): void
    {
        $pirep = $this->filePirep();
        $pirep->refresh();
        $svc = app(\App\Services\AirspaceReviewService::class);

        $this->assertNull($svc->violationFor($pirep));
        $this->assertFalse($svc->requiresReview($pirep));
        $this->assertStringNotContainsString('Espacio restringido', (string) $pirep->notes);
    }

    public function test_a_zero_or_empty_mark_is_not_a_violation(): void
    {
        foreach (['0', '', '   '] as $value) {
            $pirep = $this->filePirep(['restricted_airspace' => $value]);
            $pirep->refresh();
            $svc = app(\App\Services\AirspaceReviewService::class);

            $this->assertNull($svc->violationFor($pirep), 'Valor '.var_export($value, true));
            $this->assertStringNotContainsString('Espacio restringido', (string) $pirep->notes);
        }
    }
}
