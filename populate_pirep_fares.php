<?php

/**
 * ============================================================
 * phpVMS 8 — Populate pirep_fares for migrated PIREPs
 * ============================================================
 * Genera datos de pasajeros/cargo aleatorios con ocupación
 * mínima del 80% para PIREPs migrados que no tienen fares.
 *
 * Uso:
 *   php populate_pirep_fares.php             # procesa todos
 *   php populate_pirep_fares.php --dry-run   # solo muestra stats
 *   php populate_pirep_fares.php --min=85    # mínimo 85%
 *   php populate_pirep_fares.php --max=98    # máximo 98%
 *   php populate_pirep_fares.php --force     # reprocesa ya existentes
 * ============================================================
 */

define('PHPVMS_ROOT', __DIR__);

$autoload  = PHPVMS_ROOT . '/vendor/autoload.php';
$bootstrap = PHPVMS_ROOT . '/bootstrap/app.php';

if (!file_exists($autoload) || !file_exists($bootstrap)) {
    die("[ERROR] No se encontró phpVMS en: " . PHPVMS_ROOT . "\n");
}

require $autoload;
$app    = require $bootstrap;
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pirep;
use App\Models\PirepFare;
use App\Models\Enums\PirepState;
use Illuminate\Support\Facades\DB;

// ── Argumentos ───────────────────────────────────────────────
$opts   = getopt('', ['dry-run', 'force', 'min:', 'max:']);
$dryRun = isset($opts['dry-run']);
$force  = isset($opts['force']);
$minOcc = isset($opts['min']) ? (int) $opts['min'] / 100 : 0.80;
$maxOcc = isset($opts['max']) ? (int) $opts['max'] / 100 : 0.97;

if ($minOcc >= $maxOcc) {
    die("[ERROR] --min debe ser menor que --max\n");
}

// ── Helpers ──────────────────────────────────────────────────
function out(string $msg): void { echo $msg . "\n"; }

function randOccupancy(float $min, float $max): float
{
    return $min + mt_rand(0, 1000) / 1000 * ($max - $min);
}

// ── Cargar config de subfleets con sus fares ─────────────────
// Estructura: subfleet_id → [ [fare_id, capacity, type], ... ]
function loadSubfleetFares(): array
{
    $rows = DB::select("
        SELECT
            sf.subfleet_id,
            sf.fare_id,
            sf.capacity,
            COALESCE(sf.price, f.price) AS price,
            COALESCE(sf.cost,  f.cost)  AS cost,
            f.type AS fare_type,
            f.code
        FROM subfleet_fare sf
        JOIN fares f ON f.id = sf.fare_id
        WHERE f.deleted_at IS NULL
        ORDER BY sf.subfleet_id, f.type
    ");

    $map = [];
    foreach ($rows as $row) {
        $map[$row->subfleet_id][] = [
            'fare_id'   => $row->fare_id,
            'capacity'  => (int) $row->capacity,
            'price'     => (float) $row->price,
            'cost'      => (float) $row->cost,
            'fare_type' => (int) $row->fare_type,  // 0=pax, 1=cargo
            'code'      => $row->code,
        ];
    }
    return $map;
}

// ── Calcular fares para un PIREP ─────────────────────────────
function generateFares(array $subfleetFares, float $minOcc, float $maxOcc): array
{
    $result = [];

    foreach ($subfleetFares as $fare) {
        if ($fare['capacity'] <= 0) continue;

        // Tipo 0 = pasajeros (ocupación normal)
        // Tipo 1 = cargo (ocupación también ≥80% del peso)
        $occupancy = randOccupancy($minOcc, $maxOcc);
        $count     = (int) round($fare['capacity'] * $occupancy);

        // Mínimo 1 para no tener vuelos vacíos
        $count = max(1, $count);

        $result[] = [
            'fare_id' => $fare['fare_id'],
            'count'   => $count,
            'price'   => $fare['price'],
            'cost'    => $fare['cost'],
        ];
    }

    return $result;
}

// ── Main ─────────────────────────────────────────────────────
out('');
out('╔══════════════════════════════════════════════════════╗');
out('║   phpVMS 8 — Populate pirep_fares (migración)        ║');
out('╚══════════════════════════════════════════════════════╝');
out('');
out(sprintf("Ocupación mínima : %d%%", $minOcc * 100));
out(sprintf("Ocupación máxima : %d%%", $maxOcc * 100));
out($dryRun ? "Modo             : DRY-RUN (sin cambios)" : "Modo             : ESCRITURA");
out('');

// Cargar mapa de fares por subfleet
$subfleetFaresMap = loadSubfleetFares();
out("Subfleets con fares configurados: " . count($subfleetFaresMap));

if (empty($subfleetFaresMap)) {
    die("[ERROR] No hay fares configurados en subfleet_fare.\n"
      . "        Ejecuta primero el script fares_setup.sql\n");
}

// Query base de PIREPs
$query = Pirep::where('state', PirepState::ACCEPTED)
              ->whereNotNull('aircraft_id');

if (!$force) {
    $query->doesntHave('fares');
}

$total = $query->count();
out("PIREPs a procesar: $total");
out('');

if ($total === 0) {
    out('✅ Todos los PIREPs ya tienen fares. Usa --force para regenerar.');
    exit(0);
}

if ($dryRun) {
    // Mostrar muestra de lo que se generaría
    out('── Muestra (primeros 5 PIREPs) ────────────────────────');
    $query->with(['aircraft.subfleet'])->limit(5)->get()->each(function ($pirep) use ($subfleetFaresMap, $minOcc, $maxOcc) {
        $subfleetId = $pirep->aircraft->subfleet->id ?? null;
        $fares      = $subfleetId && isset($subfleetFaresMap[$subfleetId])
            ? generateFares($subfleetFaresMap[$subfleetId], $minOcc, $maxOcc)
            : [];

        $totalPax    = array_sum(array_column($fares, 'count'));
        $totalCredit = array_sum(array_map(fn($f) => $f['count'] * $f['price'], $fares));

        out(sprintf(
            "  PIREP %s | %s → %s | subfleet %s | %d pax/kg | ingresos: $%.2f",
            substr($pirep->id, 0, 8) . '...',
            $pirep->dpt_airport_id,
            $pirep->arr_airport_id,
            $subfleetId ?? 'N/A',
            $totalPax,
            $totalCredit
        ));

        foreach ($fares as $f) {
            out(sprintf("    fare_id:%d  count:%d  price:%.2f  subtotal:%.2f",
                $f['fare_id'], $f['count'], $f['price'], $f['count'] * $f['price']));
        }
    });

    out('');
    out('[DRY-RUN] Sin cambios. Quita --dry-run para ejecutar.');
    exit(0);
}

// ── Procesamiento real ───────────────────────────────────────
$procesados     = 0;
$sinSubfleet    = 0;
$sinFaresConfig = 0;
$errores        = 0;
$startTime      = microtime(true);

$query->with(['aircraft.subfleet'])->chunk(100, function ($pireps) use (
    $subfleetFaresMap,
    $minOcc,
    $maxOcc,
    $force,
    $startTime,
    &$procesados,
    &$sinSubfleet,
    &$sinFaresConfig,
    &$errores
) {
    foreach ($pireps as $pirep) {
        try {
            // Obtener el subfleet del avión
            $subfleet = $pirep->aircraft->subfleet ?? null;

            if (!$subfleet) {
                $sinSubfleet++;
                continue;
            }

            $subfleetFares = $subfleetFaresMap[$subfleet->id] ?? null;

            if (!$subfleetFares) {
                $sinFaresConfig++;
                continue;
            }

            // Si force, borrar fares existentes
            if ($force) {
                PirepFare::where('pirep_id', $pirep->id)->delete();
            }

            // Generar y guardar fares
            $fares = generateFares($subfleetFares, $minOcc, $maxOcc);

            foreach ($fares as $fare) {
                PirepFare::create([
                    'pirep_id' => $pirep->id,
                    'fare_id'  => $fare['fare_id'],
                    'count'    => $fare['count'],
                    'price'    => $fare['price'],
                    'cost'     => $fare['cost'],
                ]);
            }

            $procesados++;

        } catch (\Exception $e) {
            $errores++;
            out("\n[ERROR] PIREP {$pirep->id}: " . $e->getMessage());
        }

        // Progreso
        $done = $procesados + $sinSubfleet + $sinFaresConfig + $errores;
        if ($done % 50 === 0) {
            $elapsed = round(microtime(true) - $startTime, 1);
            echo "\r  Procesados: $procesados | Sin subfleet: $sinSubfleet | Sin fares config: $sinFaresConfig | {$elapsed}s   ";
        }
    }
});

$elapsed = round(microtime(true) - $startTime, 1);

out('');
out('');
out('══════════════════════════════════════════════════════');
out("✅ PIREPs con fares generados : $procesados");
out("⚠️  Sin subfleet asignado     : $sinSubfleet");
out("⚠️  Subfleet sin fares config : $sinFaresConfig");
out("❌ Errores                    : $errores");
out("⏱  Tiempo total              : {$elapsed}s");
out('══════════════════════════════════════════════════════');
out('');

if ($sinFaresConfig > 0) {
    out("ℹ️  Hay subfleets sin fares configurados.");
    out("   Verifica con:");
    out("   SELECT DISTINCT s.id, s.type, s.name");
    out("   FROM pireps p");
    out("   JOIN aircraft a ON a.id = p.aircraft_id");
    out("   JOIN subfleets s ON s.id = a.subfleet_id");
    out("   LEFT JOIN subfleet_fare sf ON sf.subfleet_id = s.id");
    out("   WHERE sf.id IS NULL AND p.state = 2;");
    out('');
}

out("Siguiente paso:");
out("  php recalculate_finances.php --dry-run");
out('');
