<?php
// Cargar Laravel bootstrap
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pirep;
use App\Services\Finance\PirepFinanceService;

$financeService = app(PirepFinanceService::class);
$procesados = 0;
$errores = 0;

echo "Iniciando procesamiento de finanzas...\n";

Pirep::where('state', 2)->chunk(100, function($pireps) use ($financeService, &$procesados, &$errores) {
    foreach ($pireps as $pirep) {
        try {
            // Limpiar transactions previas para evitar duplicados
            $pirep->transactions()->delete();
            $financeService->processFinancesForPirep($pirep);
            $procesados++;
            if ($procesados % 50 === 0) {
                echo "Procesados: $procesados\n";
            }
        } catch (\Exception $e) {
            $errores++;
            echo "ERROR PIREP {$pirep->id}: " . $e->getMessage() . "\n";
        }
    }
});

echo "\n✅ Completado. Procesados: $procesados | Errores: $errores\n";

