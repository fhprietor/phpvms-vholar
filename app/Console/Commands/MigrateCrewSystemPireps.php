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
use App\Services\FinanceService;
use App\Services\UserService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\Csv\Reader;
use Modules\VmsOpenOps\Models\OperationRequest;

class MigrateCrewSystemPireps extends Command
{
    protected $signature = 'vholar:migrate-pireps
        {file : Path to the CrewSystem CSV export}
        {--dry-run : Validate and show mapping report without modifying the database}
        {--skip-finance : Skip journal entry calculation}
        {--force : Proceed even if some pilots cannot be mapped}';

    protected $description = 'Replace all pireps with data from a CrewSystem CSV export';

    private array $aircraftMap = []; // UPPER(registration) → aircraft.id
    private array $pilotMap    = []; // UPPER(ident)        → user.id

    public function handle(
        UserService $userSvc,
        PirepFinanceService $pirepFinanceSvc,
        FinanceService $financeSvc,
        JournalRepository $journalRepo
    ): int {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        Aircraft::all()->each(fn ($a) => $this->aircraftMap[strtoupper($a->registration)] = $a->id);
        User::with('airline')->get()->each(fn ($u) => $this->pilotMap[strtoupper($u->ident)] = $u->id);

        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $records = iterator_to_array($csv->getRecords());

        // ─── PHASE 0: VALIDATE ───────────────────────────────────────────
        $this->info('=== Phase 0: Validation ===');

        $pilotStatus    = [];
        $unmappedPilots = [];
        $outliers       = [];
        $nullAircraftDryRun   = 0;
        $mappedAircraftDryRun = 0;

        // Per-pilot and per-aircraft sequences for dry-run estimates
        $pirepsByPilotDry    = []; // userId    → [{dpt, arr, at}]
        $flightsByAircraftDry = []; // regKey   → [{dpt, arr, at}]

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
                    $i + 2, $row['dep_icao'] ?? '?', $row['arr_icao'] ?? '?', $dist);
            }

            $reg = $this->extractRegistration($row['aircraft'] ?? '');
            if ($reg && isset($this->aircraftMap[$reg])) {
                $mappedAircraftDryRun++;
            } else {
                $nullAircraftDryRun++;
            }

            // Collect sequences for estimates (only valid rows)
            if (isset($this->pilotMap[$cs]) && $dist <= 50000) {
                try {
                    $dt     = Carbon::createFromFormat('m/d/Y H:i:s', $row['date']);
                    $userId = $this->pilotMap[$cs];
                    $dpt    = strtoupper($row['dep_icao'] ?? '');
                    $arr    = strtoupper($row['arr_icao'] ?? '');

                    $pirepsByPilotDry[$userId][] = ['dpt' => $dpt, 'arr' => $arr, 'at' => $dt];

                    if ($reg) {
                        $flightsByAircraftDry[$reg][] = ['dpt' => $dpt, 'arr' => $arr, 'at' => $dt];
                    }
                } catch (\Exception) {}
            }
        }

        // Pilot mapping table
        $this->table(['Callsign', 'Status'], array_map(
            fn ($cs, $found) => [$cs, $found ? '✓ found' : '✗ not found'],
            array_keys($pilotStatus), $pilotStatus
        ));

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

        $estJs = $this->countInferredJumpseats($pirepsByPilotDry);
        $estFr = $this->countInferredFerries($flightsByAircraftDry);
        $this->line("Historical jumpseats to generate: {$estJs} (× \$50.00 = \$" . number_format($estJs * 50, 2) . ')');
        $this->line("Historical ferries to generate:   {$estFr} (× \$0.00 — no charge)");

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
        // Remove all jumpseat / ferry journal charges
        DB::table('journal_transactions')
            ->where(fn ($q) => $q->where('memo', 'like', 'Jumpseat%')->orWhere('memo', 'like', 'Ferry%'))
            ->delete();
        // Remove all operation requests and reset sequence
        DB::table('vms_open_ops_requests')->delete();
        DB::statement('ALTER TABLE vms_open_ops_requests AUTO_INCREMENT = 1');
        DB::table('users')->update([
            'flights'         => 0,
            'flight_time'     => 0,
            'curr_airport_id' => null,
            'last_pirep_id'   => null,
        ]);
        DB::table('aircraft')->update(['flight_time' => 0]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Database cleaned.');

        // ─── PHASE 2: IMPORT PIREPS ──────────────────────────────────────
        $this->info("=== Phase 2: Importing {$total} rows ===");

        $airlineId = Airline::first()?->id ?? 1;

        // Pre-load airports for distance calculations (keyed by ICAO id)
        $airportCoords = DB::table('airports')->get(['id', 'lat', 'lon'])->keyBy('id');

        $imported     = 0;
        $skipped      = 0;
        $nullAircraft = 0;

        $pirepsByPilot    = []; // userId     → [{dpt, arr, at}]
        $flightsByAircraft = []; // aircraftId → [{dpt, arr, at, userId}]
        $latestByPilot    = []; // userId     → [pirep_id, arr_airport, submitted_at]
        $latestByAircraft = []; // aircraftId → [airport_id, submitted_at]

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($records as $row) {
            $row = array_map('trim', $row);
            $bar->advance();

            $cs = $this->normalizeCallsign($row['pilot_callsign'] ?? '');
            if (!isset($this->pilotMap[$cs])) { $skipped++; continue; }
            $userId = $this->pilotMap[$cs];

            $dist = (float)($row['distance'] ?? 0);
            if ($dist > 50000) { $skipped++; continue; }

            try {
                $date = Carbon::createFromFormat('m/d/Y H:i:s', $row['date']);
            } catch (\Exception) { $skipped++; continue; }

            $reg        = $this->extractRegistration($row['aircraft'] ?? '');
            $aircraftId = ($reg && isset($this->aircraftMap[$reg])) ? $this->aircraftMap[$reg] : null;
            if ($aircraftId === null) { $nullAircraft++; }

            $rawFlight = $row['flight_number'] ?? '';
            $flightNum = ltrim(preg_replace('/^VHR/i', '', $rawFlight), '0') ?: '0';
            $landingRate = (int)($row['landing_rate'] ?? 0);
            $pirepId   = Str::random(16);
            $dptAirport = strtoupper($row['dep_icao'] ?? '');
            $arrAirport = strtoupper($row['arr_icao'] ?? '');

            DB::table('pireps')->insert([
                'id'                  => $pirepId,
                'user_id'             => $userId,
                'airline_id'          => $airlineId,
                'aircraft_id'         => $aircraftId,
                'flight_number'       => $flightNum,
                'dpt_airport_id'      => $dptAirport,
                'arr_airport_id'      => $arrAirport,
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

            // Track sequences for historical operation inference
            $pirepsByPilot[$userId][] = ['dpt' => $dptAirport, 'arr' => $arrAirport, 'at' => $date];

            if ($aircraftId) {
                $flightsByAircraft[$aircraftId][] = [
                    'dpt'    => $dptAirport,
                    'arr'    => $arrAirport,
                    'at'     => $date,
                    'userId' => $userId,
                ];
            }

            // Track most recent pirep per pilot / aircraft
            if (!isset($latestByPilot[$userId]) || $date > $latestByPilot[$userId]['submitted_at']) {
                $latestByPilot[$userId] = [
                    'pirep_id'     => $pirepId,
                    'arr_airport'  => $arrAirport,
                    'submitted_at' => $date,
                ];
            }
            if ($aircraftId && (!isset($latestByAircraft[$aircraftId]) || $date > $latestByAircraft[$aircraftId]['submitted_at'])) {
                $latestByAircraft[$aircraftId] = ['airport_id' => $arrAirport, 'submitted_at' => $date];
            }
        }

        $bar->finish();
        $this->newLine();
        $this->line("Imported: {$imported} | Skipped: {$skipped} | No aircraft (null): {$nullAircraft}");

        // ─── PHASE 3: HISTORICAL JUMPSEATS ───────────────────────────────
        $this->info('=== Phase 3: Generating historical jumpseats ($50 each) ===');

        $jumpseats = 0;
        $jsErrors  = 0;
        $jsCost    = new Money(5000); // $50.00

        foreach ($pirepsByPilot as $userId => $flights) {
            usort($flights, fn ($a, $b) => $a['at']->timestamp <=> $b['at']->timestamp);

            $user = User::with('journal')->find($userId);
            if (!$user || !$user->journal) {
                continue;
            }

            $currentAirport = $flights[0]['dpt'];

            foreach ($flights as $flight) {
                if ($currentAirport && $flight['dpt'] !== $currentAirport) {
                    try {
                        $jsAt    = $flight['at']->copy()->subMinutes(1);
                        $fromKey = substr($currentAirport, 0, 5);
                        $toKey   = substr($flight['dpt'], 0, 5);
                        $jsDist  = $this->distanceBetween($airportCoords, $fromKey, $toKey);
                        $opReq = OperationRequest::create([
                            'operation_type'  => 'jumpseat',
                            'user_id'         => $userId,
                            'from_airport_id' => $fromKey,
                            'to_airport_id'   => $toKey,
                            'distance'        => $jsDist,
                            'cost'            => 5000,
                            'reason'          => 'Migración histórica CrewSystem',
                            'type'            => 0,
                            'status'          => 1,
                            'approved_at'     => $jsAt,
                            'created_at'      => $jsAt,
                            'updated_at'      => now(),
                        ]);

                        $financeSvc->debitFromJournal(
                            $user->journal,
                            $jsCost,
                            $opReq,
                            "Jumpseat: {$currentAirport} → {$flight['dpt']}",
                            null,
                            null
                        );

                        $jumpseats++;
                    } catch (\Throwable $e) {
                        Log::warning("Jumpseat gen failed for user {$userId}: " . $e->getMessage());
                        $jsErrors++;
                    }
                }
                $currentAirport = $flight['arr'];
            }
        }

        $this->line(sprintf(
            'Jumpseats created: %d%s',
            $jumpseats,
            $jsErrors > 0 ? " ({$jsErrors} errors — see laravel.log)" : ''
        ));

        // ─── PHASE 4: HISTORICAL FERRIES ─────────────────────────────────
        $this->info('=== Phase 4: Generating historical ferries ($0 — no charge) ===');

        // Pre-load aircraft models for subfleet_id lookup
        $aircraftModels = Aircraft::whereIn('id', array_keys($flightsByAircraft))
            ->get()
            ->keyBy('id');

        $ferries    = 0;
        $ferryErrors = 0;

        foreach ($flightsByAircraft as $aircraftId => $flights) {
            usort($flights, fn ($a, $b) => $a['at']->timestamp <=> $b['at']->timestamp);

            $aircraft = $aircraftModels[$aircraftId] ?? null;

            for ($i = 1; $i < count($flights); $i++) {
                $prev = $flights[$i - 1];
                $curr = $flights[$i];

                if ($prev['arr'] === $curr['dpt']) {
                    continue; // aircraft was already in the right place
                }

                try {
                    $frAt    = $curr['at']->copy()->subMinutes(1);
                    $fromKey = substr($prev['arr'], 0, 5);
                    $toKey   = substr($curr['dpt'], 0, 5);
                    $frDist  = $this->distanceBetween($airportCoords, $fromKey, $toKey);

                    OperationRequest::create([
                        'operation_type'   => 'ferry',
                        'user_id'          => $curr['userId'],
                        'from_airport_id'  => $fromKey,
                        'to_airport_id'    => $toKey,
                        'aircraft_id'      => $aircraftId,
                        'subfleet_id'      => $aircraft?->subfleet_id,
                        'aircraft_distance' => $frDist,
                        'distance'         => $frDist,
                        'cost'             => 0,
                        'reason'           => 'Migración histórica CrewSystem',
                        'type'             => 0,
                        'status'           => 1,
                        'approved_at'      => $frAt,
                        'created_at'       => $frAt,
                        'updated_at'       => now(),
                    ]);

                    $ferries++;
                } catch (\Throwable $e) {
                    Log::warning("Ferry gen failed for aircraft {$aircraftId}: " . $e->getMessage());
                    $ferryErrors++;
                }
            }
        }

        $this->line(sprintf(
            'Ferries created: %d%s',
            $ferries,
            $ferryErrors > 0 ? " ({$ferryErrors} errors — see laravel.log)" : ''
        ));

        // ─── PHASE 5a: STATS + RANKS ─────────────────────────────────────
        $this->info('=== Phase 5a: Recalculating user stats and ranks ===');
        $userSvc->recalculateAllUserStats();
        $this->info('Done.');

        // ─── PHASE 5b: FINANCES ──────────────────────────────────────────
        if (!$this->option('skip-finance')) {
            $this->info('=== Phase 5b: Processing pirep finances ===');

            $pireps    = Pirep::with(['user.rank', 'user.journal', 'airline.journal', 'aircraft.subfleet'])->get();
            $finBar    = $this->output->createProgressBar($pireps->count());
            $finErrors = 0;

            $finBar->start();
            foreach ($pireps as $pirep) {
                try {
                    $pirepFinanceSvc->processFinancesForPirep($pirep);
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

            // ─── PHASE 5d: JOURNAL BALANCES ──────────────────────────────
            $this->info('=== Phase 5d: Recalculating journal balances ===');
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

        // ─── PHASE 5c: PILOT + AIRCRAFT POSITIONS ────────────────────────
        $this->info('=== Phase 5c: Setting pilot and aircraft positions ===');

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
        $this->info("=== Migration complete: {$imported} pireps, {$jumpseats} jumpseats, {$ferries} ferries ===");

        return 0;
    }

    private function distanceBetween($airports, string $fromId, string $toId): float
    {
        $from = $airports[$fromId] ?? null;
        $to   = $airports[$toId]   ?? null;
        if (!$from || !$to) {
            return 0.0;
        }
        $R    = 3440.07;
        $dLat = deg2rad($to->lat - $from->lat);
        $dLon = deg2rad($to->lon - $from->lon);
        $a    = sin($dLat/2)**2 + cos(deg2rad($from->lat)) * cos(deg2rad($to->lat)) * sin($dLon/2)**2;
        return round($R * 2 * atan2(sqrt($a), sqrt(1-$a)), 2);
    }

    private function countInferredJumpseats(array $pirepsByPilot): int
    {
        $count = 0;
        foreach ($pirepsByPilot as $flights) {
            usort($flights, fn ($a, $b) => $a['at']->timestamp <=> $b['at']->timestamp);
            $current = $flights[0]['dpt'] ?? null;
            foreach ($flights as $f) {
                if ($current && $f['dpt'] !== $current) {
                    $count++;
                }
                $current = $f['arr'];
            }
        }
        return $count;
    }

    private function countInferredFerries(array $flightsByAircraft): int
    {
        $count = 0;
        foreach ($flightsByAircraft as $flights) {
            usort($flights, fn ($a, $b) => $a['at']->timestamp <=> $b['at']->timestamp);
            for ($i = 1; $i < count($flights); $i++) {
                if ($flights[$i - 1]['arr'] !== $flights[$i]['dpt']) {
                    $count++;
                }
            }
        }
        return $count;
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
        if (preg_match('/\b(HK-?\d{4,5}|N\d{3,6}[A-Z]{1,3})\b/i', $text, $m)) {
            return strtoupper(str_replace('-', '', $m[1]));
        }
        if (preg_match('/\bHK-?\s(\d{4,5})\b/i', $text, $m)) {
            return 'HK' . $m[1];
        }
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
