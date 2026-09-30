<?php

use App\Models\Enums\PirepFieldSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Custom PIREP fields filled in by ACARS clients.
     *
     * `departure-runway` / `arrival-runway` follow the convention already used by
     * DisposableBasic (its /dbapi/pireps endpoint reads those slugs). `taxi-route`
     * holds the taxi clearance as typed by the pilot, verbatim.
     *
     * They are declared as not required so manual PIREP filing is not affected:
     * CreatePirepRequest adds a rule for every field regardless of its source.
     */
    private array $fields = [
        'departure-runway' => [
            'name'        => 'Departure Runway',
            'description' => 'Runway used for departure, as reported by the ACARS client',
        ],
        'arrival-runway' => [
            'name'        => 'Arrival Runway',
            'description' => 'Runway used for arrival, as reported by the ACARS client',
        ],
        'taxi-route' => [
            'name'        => 'Taxi Route',
            'description' => 'Taxi clearance as typed by the pilot',
        ],
    ];

    public function up(): void
    {
        foreach ($this->fields as $slug => $field) {
            $exists = DB::table('pirep_fields')->where('slug', $slug)->exists();
            if ($exists) {
                continue;
            }

            DB::table('pirep_fields')->insert([
                'name'         => $field['name'],
                'slug'         => $slug,
                'description'  => $field['description'],
                'required'     => false,
                'pirep_source' => PirepFieldSource::ACARS,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pirep_fields')->whereIn('slug', array_keys($this->fields))->delete();
    }
};
