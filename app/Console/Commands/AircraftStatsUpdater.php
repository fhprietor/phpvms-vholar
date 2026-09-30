<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Aircraft;
use App\Models\Pirep;
use App\Models\Enums\PirepState;
use Illuminate\Support\Facades\DB;

class AircraftStatsUpdater extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vholar:aircraft-stats-update 
                            {--aircraft-id= : Actualizar solo un avión específico}
                            {--dry-run : Simular sin guardar cambios}
                            {--show-details : Mostrar información detallada de cada avión}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza estadísticas de aviones basado en PIREPs (horas, última ubicación)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $aircraftId = $this->option('aircraft-id');
        $dryRun = $this->option('dry-run');
        $showDetails = $this->option('show-details');

        if ($dryRun) {
            $this->warn('🔍 MODO SIMULACIÓN: No se guardarán cambios');
        }

        // Obtener aviones a procesar
        $query = Aircraft::query();
        if ($aircraftId) {
            $query->where('id', $aircraftId);
            $this->info("Procesando avión ID: {$aircraftId}");
        } else {
            $this->info("Procesando TODOS los aviones con PIREPs...");
        }

        $aircraft = $query->get();
        $total = $aircraft->count();
        $updated = 0;
        $errors = 0;

        $this->newLine();
        $this->line("📊 Aviones encontrados: {$total}");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('verbose');
        $bar->start();

        foreach ($aircraft as $ac) {
            try {
                $result = $this->updateAircraftStats($ac, $dryRun, $showDetails);
                if ($result) {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors++;
                if ($showDetails) {
                    $this->newLine();
                    $this->error("❌ Error con {$ac->registration}: " . $e->getMessage());
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Resumen
        $this->table(
            ['Ítem', 'Cantidad'],
            [
                ['Aviones procesados', $total],
                ['Actualizados', $updated],
                ['Errores', $errors],
            ]
        );

        if ($dryRun) {
            $this->warn('🔍 Modo simulación activado - No se guardaron cambios');
        } else {
            $this->info('✅ Proceso completado');
        }

        return 0;
    }

    /**
     * Actualizar estadísticas de un avión
     */
    private function updateAircraftStats($aircraft, $dryRun, $showDetails)
    {
        if ($showDetails) {
            $this->newLine();
            $this->line("🔧 Procesando {$aircraft->registration}...");
        }

        // Obtener todos los PIREPs aprobados de este avión
        $pireps = $aircraft->pireps()
            ->where('state', PirepState::ACCEPTED)
            ->orderBy('submitted_at', 'asc')
            ->get();

        if ($pireps->isEmpty()) {
            if ($showDetails) {
                $this->line("   ⚠️  No hay PIREPs para {$aircraft->registration}");
            }
            return false;
        }

        // 1. Calcular tiempo total de vuelo
        $totalFlightTime = $pireps->sum('flight_time');

        // 2. Obtener último PIREP (más reciente)
        $lastPirep = $pireps->last();

        // 3. Obtener ubicación actual (aeropuerto del último vuelo)
        $currentAirport = $lastPirep->arr_airport_id;

        // 4. Obtener última hora de aterrizaje
        $landingTime = $lastPirep->block_on_time ?? $lastPirep->submitted_at;

        if ($showDetails) {
            $this->line("   📍 Ubicación actual: {$currentAirport}");
            $this->line("   🕒 Último aterrizaje: {$landingTime}");
            $this->line("   ⏱️  Tiempo total: " . round($totalFlightTime / 60, 1) . " horas");
            $this->line("   📊 PIREPs procesados: {$pireps->count()}");
        }

        // Si es modo simulación, no guardar
        if ($dryRun) {
            return true;
        }

        // Actualizar el avión
        $aircraft->airport_id = $currentAirport;
        $aircraft->landing_time = $landingTime;
        $aircraft->flight_time = $totalFlightTime; // En minutos
        $aircraft->save();

        if ($showDetails) {
            $this->line("   ✅ {$aircraft->registration} actualizado");
        }

        return true;
    }
}