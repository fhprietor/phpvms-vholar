<?php

namespace App\Console\Commands;

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Pirep;
use App\Models\User;
use App\Repositories\JournalRepository;
use App\Services\Finance\PirepFinanceService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\Csv\Reader;

class MigrateCrewSystemPireps extends Command
{
    protected $signature = 'vholar:migrate-pireps
        {file : Path to the CrewSystem CSV export}
        {--dry-run : Validate and show mapping report without modifying the database}
        {--skip-finance : Skip journal entry calculation}
        {--force : Proceed even if some pilots cannot be mapped}';

    protected $description = 'Replace all pireps with data from a CrewSystem CSV export';

    private array $aircraftMap = []; // UPPER(registration) → aircraft.id
    private array $pilotMap    = []; // UPPER(pilot_id)     → user.id

    public function handle(
        UserService $userSvc,
        PirepFinanceService $financeSvc,
        JournalRepository $journalRepo
    ): int {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        // Pre-load lookup tables
        Aircraft::all()->each(fn ($a) => $this->aircraftMap[strtoupper($a->registration)] = $a->id);
        User::with('airline')->get()->each(fn ($u) => $this->pilotMap[strtoupper($u->ident)] = $u->id);


        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $records = iterator_to_array($csv->getRecords());

        // ─── PHASE 0: VALIDATE ───────────────────────────────────────────
        $this->info('=== Phase 0: Validation ===');

        $pilotStatus    = []; // callsign → bool found
        $unmappedPilots = [];
        $outliers       = [];
        $nullAircraftDryRun = 0;
        $mappedAircraftDryRun = 0;

        foreach ($records as $i => $row) {
            $row = array_map('trim', $row);

            $cs = $this->normalizeCallsign($row['pilot_callsign'] ?? '');
            if (!isset($pilotStatus[$cs])) {
                $found = isset($this->pilotMap[$cs]);
                $pilotStatus[$cs] = $found;
                if (!$found) {
                    $unmappedPilots[] = $cs;
                }
            }

            $dist = (float)($row['distance'] ?? 0);
            if ($dist > 50000) {
                $outliers[] = sprintf('Row %d: %s→%s dist=%.0f NM',
                    $i + 2,
                    $row['dep_icao'] ?? '?',
                    $row['arr_icao'] ?? '?',
                    $dist
                );
            }

            $reg = $this->extractRegistration($row['aircraft'] ?? '');
            if ($reg && isset($this->aircraftMap[$reg])) {
                $mappedAircraftDryRun++;
            } else {
                $nullAircraftDryRun++;
            }
        }

        // Pilot mapping table
        $this->table(['Callsign', 'Status'], array_map(
            fn ($cs, $found) => [$cs, $found ? '✓ found' : '✗ not found'],
            array_keys($pilotStatus),
            $pilotStatus
        ));

        // Aircraft summary
        $total = count($records);
        $this->line(sprintf(
            'Aircraft: %d/%d rows mapped (%.0f%%), %d will use aircraft_id=null',
            $mappedAircraftDryRun, $total,
            $total > 0 ? $mappedAircraftDryRun * 100 / $total : 0,
            $nullAircraftDryRun
        ));

        foreach ($outliers as $o) {
            $this->warn("Distance outlier: $o (will be skipped)");
        }

        if ($unmappedPilots) {
            $this->warn('Unmapped pilots (rows will be skipped): ' . implode(', ', array_unique($unmappedPilots)));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete — no changes made.');
            return 0;
        }

        if ($unmappedPilots && !$this->option('force')) {
            if (!$this->confirm('Some pilots are unmapped and their rows will be skipped. Continue?')) {
                return 1;
            }
        }

        // ─── PHASE 1: CLEAN ──────────────────────────────────────────────
        $this->info('=== Phase 1: Cleaning existing data ===');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('acars')->delete();
        DB::table('pirep_field_values')->delete();
        DB::table('pirep_fares')->delete();
        DB::table('pirep_comments')->delete();
        DB::table('journal_transactions')->where('ref_model', Pirep::class)->delete();
        DB::table('pireps')->delete();
        // Remove jumpseat and ferry charges — pricing history doesn't carry over
        DB::table('journal_transactions')
            ->where(fn ($q) => $q->where('memo', 'like', 'Jumpseat%')->orWhere('memo', 'like', 'Ferry%'))
            ->delete();
        DB::table('users')->update([
            'flights'        => 0,
            'flight_time'    => 0,
            'curr_airport_id' => null,
            'last_pirep_id'  => null,
        ]);
        DB::table('aircraft')->update(['flight_time' => 0]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Database cleaned.');

        // ─── PHASE 2: IMPORT ─────────────────────────────────────────────
        $this->info("=== Phase 2: Importing {$total} rows ===");

        $airlineId = Airline::first()?->id ?? 1;

        $imported       = 0;
        $skipped        = 0;
        $nullAircraft   = 0;

        $latestByPilot    = []; // user_id   → ['pirep_id', 'arr_airport', 'submitted_at']
        $latestByAircraft = []; // aircraft_id → ['airport_id', 'submitted_at']

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($records as $row) {
            $row = array_map('trim', $row);
            $bar->advance();

            // Pilot lookup — skip row if unmapped
            $cs = $this->normalizeCallsign($row['pilot_callsign'] ?? '');
            if (!isset($this->pilotMap[$cs])) {
                $skipped++;
                continue;
            }
            $userId = $this->pilotMap[$cs];

            // Distance outlier — skip
            $dist = (float)($row['distance'] ?? 0);
            if ($dist > 50000) {
                $skipped++;
                continue;
            }

            // Date
            try {
                $date = Carbon::createFromFormat('m/d/Y H:i:s', $row['date']);
            } catch (\Exception) {
                $skipped++;
                continue;
            }

            // Aircraft
            $reg        = $this->extractRegistration($row['aircraft'] ?? '');
            $aircraftId = ($reg && isset($this->aircraftMap[$reg]))
                ? $this->aircraftMap[$reg]
                : null;
            if ($aircraftId === null) {
                $nullAircraft++;
            }

            // Flight number: strip VHR prefix, keep suffix
            $rawFlight  = $row['flight_number'] ?? '';
            $flightNum  = preg_replace('/^VHR/i', '', $rawFlight);
            $flightNum  = ltrim($flightNum, '0') ?: '0';

            $landingRate = (int)($row['landing_rate'] ?? 0);

            $pirepId = Str::random(16);

            DB::table('pireps')->insert([
                'id'                  => $pirepId,
                'user_id'             => $userId,
                'airline_id'          => $airlineId,
                'aircraft_id'         => $aircraftId,
                'flight_number'       => $flightNum,
                'dpt_airport_id'      => strtoupper($row['dep_icao'] ?? ''),
                'arr_airport_id'      => strtoupper($row['arr_icao'] ?? ''),
                'flight_time'         => $this->durationToMinutes($row['duration'] ?? '0:0:0'),
                'planned_flight_time' => $this->durationToMinutes($row['duration'] ?? '0:0:0'),
                'distance'            => $dist,
                'planned_distance'    => $dist,
                'route'               => $row['route'] ?? '',
                'notes'               => $row['comments'] ?? '',
                'fuel_used'           => (float)($row['fuel_lb'] ?? 0),
                'block_fuel'          => (float)($row['fuel_lb'] ?? 0),
                'landing_rate'        => $landingRate,
                'flight_type'         => FlightType::SCHED_PAX,
                'source'              => $landingRate !== 0 ? PirepSource::ACARS : PirepSource::MANUAL,
                'state'               => PirepState::ACCEPTED,
                'status'              => PirepStatus::ARRIVED,
                'submitted_at'        => $date,
                'created_at'          => $date,
                'updated_at'          => now(),
            ]);

            $imported++;

            // Track most recent pirep per pilot
            if (!isset($latestByPilot[$userId]) || $date > $latestByPilot[$userId]['submitted_at']) {
                $latestByPilot[$userId] = [
                    'pirep_id'     => $pirepId,
                    'arr_airport'  => strtoupper($row['arr_icao'] ?? ''),
                    'submitted_at' => $date,
                ];
            }

            // Track most recent pirep per aircraft
            if ($aircraftId) {
                if (!isset($latestByAircraft[$aircraftId]) || $date > $latestByAircraft[$aircraftId]['submitted_at']) {
                    $latestByAircraft[$aircraftId] = [
                        'airport_id'   => strtoupper($row['arr_icao'] ?? ''),
                        'submitted_at' => $date,
                    ];
                }
            }
        }

        $bar->finish();
        $this->newLine();
        $this->line("Imported: {$imported} | Skipped: {$skipped} | No aircraft (null): {$nullAircraft}");

        // ─── PHASE 3a: STATS + RANKS ─────────────────────────────────────
        $this->info('=== Phase 3a: Recalculating user stats and ranks ===');
        $userSvc->recalculateAllUserStats();
        $this->info('Done.');

        // ─── PHASE 3b: FINANCES ──────────────────────────────────────────
        if (!$this->option('skip-finance')) {
            $this->info('=== Phase 3b: Processing finances ===');

            $pireps    = Pirep::with(['user.rank', 'user.journal', 'airline.journal', 'aircraft.subfleet'])->get();
            $finBar    = $this->output->createProgressBar($pireps->count());
            $finErrors = 0;

            $finBar->start();
            foreach ($pireps as $pirep) {
                try {
                    $financeSvc->processFinancesForPirep($pirep);
                } catch (\Throwable $e) {
                    $finErrors++;
                    Log::warning("Finance skipped for pirep {$pirep->id}: " . $e->getMessage());
                }
                $finBar->advance();
            }
            $finBar->finish();
            $this->newLine();

            if ($finErrors > 0) {
                $this->warn("{$finErrors} pireps skipped finance (null aircraft or missing data).");
            }

            // ─── PHASE 3d: JOURNAL BALANCES ──────────────────────────────
            $this->info('=== Phase 3d: Recalculating journal balances ===');
            User::with('journal')->get()->each(function (User $user) use ($journalRepo) {
                if ($user->journal) {
                    $journalRepo->recalculateBalance($user->journal);
                }
            });
            $airline = Airline::with('journal')->first();
            if ($airline?->journal) {
                $journalRepo->recalculateBalance($airline->journal);
            }
            $this->info('Done.');
        }

        // ─── PHASE 3c: PILOT + AIRCRAFT POSITIONS ────────────────────────
        $this->info('=== Phase 3c: Setting pilot and aircraft positions ===');

        foreach ($latestByPilot as $userId => $data) {
            DB::table('users')->where('id', $userId)->update([
                'curr_airport_id' => $data['arr_airport'],
                'last_pirep_id'   => $data['pirep_id'],
            ]);
        }

        foreach ($latestByAircraft as $aircraftId => $data) {
            DB::table('aircraft')->where('id', $aircraftId)->update([
                'airport_id' => $data['airport_id'],
            ]);
        }

        $this->info('Done.');
        $this->info("=== Migration complete: {$imported} pireps imported ===");

        return 0;
    }

    // Normalizes a pilot callsign: strips extra leading zeros from the numeric suffix.
    // VHR0067 → VHR067, VHR001 → VHR001 (unchanged)
    private function normalizeCallsign(string $cs): string
    {
        if (preg_match('/^([A-Z]+)0+(\d{3,})$/', strtoupper($cs), $m)) {
            return $m[1] . $m[2];
        }
        return strtoupper($cs);
    }

    // Extracts the normalized registration (no hyphens, uppercase) from a free-text aircraft string.
    // Returns null if no recognizable registration is found.
    private function extractRegistration(string $text): ?string
    {
        // Level 1: standard HKxxxx, HK-xxxx, Nxxxxxx
        if (preg_match('/\b(HK-?\d{4,5}|N\d{3,6}[A-Z]{1,3})\b/i', $text, $m)) {
            return strtoupper(str_replace('-', '', $m[1]));
        }
        // Level 2: HK with space between prefix and digits (e.g. "HK- 6260")
        if (preg_match('/\bHK-?\s(\d{4,5})\b/i', $text, $m)) {
            return 'HK' . $m[1];
        }
        // Level 3: implicit fleet number in preset name
        // Matches: _VHOLAR_6253 | VHOLAR6253 | AVAHK4549 | clicairHK5331
        if (preg_match('/(?:_VHOLAR_|VHOLAR(?=\d)|AVAHK|clicairHK)(\d{4,5})/i', $text, $m)) {
            return 'HK' . $m[1];
        }

        return null;
    }

    private function durationToMinutes(string $duration): int
    {
        $parts = explode(':', trim($duration));
        if (count($parts) < 2) {
            return 0;
        }
        return (int) $parts[0] * 60 + (int) $parts[1];
    }
}
