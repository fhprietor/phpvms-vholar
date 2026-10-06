<?php
/**
 * Corrige el coste de handling de tierra.
 *
 * 1) subfleets.ground_handling_multiplier: el core lo lee como PORCENTAJE
 *    (PirepFinanceService::getGroundHandlingCost). Las subflotas con 1.00 pagaban el 1 %
 *    de la tarifa del aeropuerto; la intencion era 100 (100 %). Las que estan a 0.50, 0.70,
 *    2.50, 3.00 o 100.00 se respetan: parecen decisiones deliberadas.
 * 2) airports.ground_handling_cost: los internacionales con trafico real seguian en el valor
 *    de sembrado (300) y un regional estaba a 100. Se ajustan por categoria.
 *
 * Idempotente: solo escribe lo que cambia, se puede repetir sin efecto acumulado.
 * NO reescribe libros historicos: afecta a los PIREPs que se registren a partir de ahora.
 *
 * Uso:  php deploy/scripts/fix-ground-handling.php            (simulacion)
 *       php deploy/scripts/fix-ground-handling.php --apply    (aplica)
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);

echo $apply ? "== APLICANDO ==\n" : "== SIMULACION (nada se escribe; usa --apply) ==\n";

// ---------------------------------------------------------------- multiplicadores
$bad = DB::table('subfleets')->select('id', 'type', 'ground_handling_multiplier')
    ->where('ground_handling_multiplier', 1)
    ->orderBy('type')->get();

echo "\n1) Multiplicador por subflota: ".count($bad)." con 1.00 (= 1 %, deberia ser 100 %)\n";

foreach ($bad as $sf) {
    echo "   {$sf->type}: 1.00 -> 100.00\n";
    if ($apply) {
        DB::table('subfleets')->where('id', $sf->id)->update(['ground_handling_multiplier' => 100]);
    }
}

$kept = DB::table('subfleets')->selectRaw('ground_handling_multiplier m, count(*) n')
    ->where('ground_handling_multiplier', '!=', 1)->groupBy('m')->orderBy('m')->get();
echo "   (respetadas: ".$kept->map(fn ($k) => $k->m.' x'.$k->n)->implode(', ').")\n";

// ---------------------------------------------------------------- aeropuertos
$airports = [
    'SKBO' => [1000, 'hub principal'],
    'KMIA' => [700, 'internacional grande (estaba en el valor de sembrado)'],
    'MPTO' => [700, 'internacional (estaba en el valor de sembrado)'],
    'SKRG' => [700, 'internacional grande'],
    'SKVV' => [250, 'regional menor (estaba a 100)'],
];

echo "\n2) Tarifa de handling por aeropuerto:\n";
$changed = 0;

foreach ($airports as $icao => [$cost, $why]) {
    $row = DB::table('airports')->where('id', $icao)->first(['id', 'ground_handling_cost', 'name']);

    if ($row === null) {
        echo "   {$icao}: no existe, se omite\n";
        continue;
    }

    if ((float) $row->ground_handling_cost === (float) $cost) {
        echo "   {$icao}: ya esta en {$cost}, sin cambio\n";
        continue;
    }

    $changed++;
    echo "   {$icao} ({$row->name}): {$row->ground_handling_cost} -> {$cost}   [{$why}]\n";

    if ($apply) {
        DB::table('airports')->where('id', $icao)->update(['ground_handling_cost' => $cost]);
    }
}

echo "\n   Aeropuertos a cambiar: {$changed}\n";

// ---------------------------------------------------------------- efecto
$volados = DB::table('pireps')->where('submitted_at', '>=', now()->subDays(30))->count();

echo "\n3) Efecto:\n";
echo "   Ejemplo real (B38M, SKRG 800 + SKBG 400): antes 12 USD, ahora 1200 USD\n";
echo "   PIREPs en los ultimos 30 dias: {$volados} (a partir de ahora pagaran la tarifa real)\n";
echo "   Los libros historicos NO se reescriben.\n";

echo $apply ? "\nHecho.\n" : "\nSimulacion: no se ha escrito nada.\n";
