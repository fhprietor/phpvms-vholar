<?php

namespace App\Console\Commands;

use App\Models\Enums\AcarsType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Auditoria (SOLO LECTURA) de las sanciones de compuerta de aproximacion que el cliente
 * vmsOpenAcars emitio ANTES de la 0.9.71.
 *
 * Antes de esa version la compuerta usaba una banda fija por categoria PANS-OPS
 * ([140-185] kt para cualquier wide-body) e ignoraba el Vref que el propio cliente enviaba.
 * Desde la 0.9.71 la banda es [Vref - 5, Vref + 15] y la linea del veredicto incluye el Vref.
 *
 * Este comando no modifica nada: cruza el log con la Vref registrada en `pirep_landings` y
 * dice que sanciones antiguas habrian pasado con la regla nueva.
 *
 *   php artisan phpvms:pireps-gate-audit
 *   php artisan phpvms:pireps-gate-audit --type=A339 --since=2026-10-01
 *   php artisan phpvms:pireps-gate-audit --json > heavies.json
 */
class PirepsGateAudit extends Command
{
    protected $signature = 'phpvms:pireps-gate-audit
        {--source=vmsOpenAcars : Filtrar por source_name (LIKE)}
        {--since= : Solo PIREPs presentados desde esta fecha (YYYY-MM-DD)}
        {--type= : Solo este tipo de subflota (p. ej. A339)}
        {--limit= : Maximo de PIREPs a revisar (0 = todos)}
        {--json : Salida JSON, para pasarsela al equipo del cliente}';

    protected $description = 'Lista sanciones de compuerta anteriores a vmsOpenAcars 0.9.71 que contradicen la Vref registrada (solo lectura)';

    public function handle(): int
    {
        $rows = $this->audit();

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $contradicen = array_values(array_filter($rows, fn ($r) => $r['veredicto'] === 'contradice'));

        $this->newLine();
        $this->info('Auditoria de compuertas anteriores a vmsOpenAcars 0.9.71 (solo lectura)');
        $this->line(sprintf(
            'PIREPs revisados: %d · con sancion de compuerta antigua: %d · contradicen la Vref: %d',
            count($rows),
            count(array_filter($rows, fn ($r) => $r['sancion_antigua'])),
            count($contradicen)
        ));

        if ($contradicen) {
            $this->newLine();
            $this->table(
                ['PIREP', 'Vuelo', 'Fecha', 'Cliente', 'Tipo', 'Vref', 'Volo', 'Ventana nueva'],
                array_map(fn ($r) => [
                    $r['id'],
                    $r['flight_number'],
                    $r['date'],
                    $r['cliente'],
                    $r['tipo'],
                    $r['vref'],
                    $r['velocidad'],
                    $r['ventana_nueva'],
                ], $contradicen)
            );
        }

        $resumen = [];
        foreach ($rows as $r) {
            $resumen[$r['veredicto']] = ($resumen[$r['veredicto']] ?? 0) + 1;
        }

        $this->newLine();
        $this->line('Veredictos:');
        foreach ($resumen as $veredicto => $n) {
            $this->line(sprintf('  %-12s %d', $veredicto, $n));
        }

        $this->newLine();
        $this->line('  contradice   = habria pasado con [Vref-5, Vref+15]: la sancion vieja era un falso positivo probable');
        $this->line('  confirma     = fuera tambien de la ventana nueva: la sancion se sostiene');
        $this->line('  sin_velocidad= la compuerta fallo por otro criterio (o el log no trae la velocidad)');
        $this->line('  sin_vref     = no hay fila de aterrizaje con Vref: no se puede juzgar');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function audit(): array
    {
        $query = DB::table('pireps')
            ->leftJoin('aircraft', 'aircraft.id', '=', 'pireps.aircraft_id')
            ->leftJoin('subfleets', 'subfleets.id', '=', 'aircraft.subfleet_id')
            ->whereNotNull('pireps.submitted_at')
            ->orderBy('pireps.submitted_at');

        if ($source = $this->option('source')) {
            $query->where('pireps.source_name', 'like', $source.'%');
        }

        if ($since = $this->option('since')) {
            $query->where('pireps.submitted_at', '>=', $since.' 00:00:00');
        }

        if ($type = $this->option('type')) {
            $query->where('subfleets.type', $type);
        }

        if ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        // Solo PIREPs cuyo log tiene un veredicto de compuerta: sin esto el comando recorre
        // los 6.000 PIREPs y sus logs, y tarda minutos para encontrar un puñado.
        $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('acars')
                ->whereColumn('acars.pirep_id', 'pireps.id')
                ->where('acars.type', AcarsType::LOG)
                ->where(function ($q) {
                    $q->where('acars.log', 'like', '%INESTABLE%')
                        ->orWhere('acars.log', 'like', '%UNSTABILIZED%');
                });
        });

        $pireps = $query->get([
            'pireps.id',
            'pireps.flight_number',
            'pireps.dpt_airport_id',
            'pireps.arr_airport_id',
            'pireps.submitted_at',
            'pireps.source_name',
            'subfleets.type as aircraft_type',
        ]);

        $rows = [];

        foreach ($pireps as $pirep) {
            $lines = DB::table('acars')
                ->where('pirep_id', $pirep->id)
                ->where('type', AcarsType::LOG)
                ->pluck('log')
                ->map(fn ($line) => (string) $line)
                ->implode("\n");

            // 0.9.71 en adelante: la linea del veredicto trae la Vref; ahi no hay nada que auditar.
            $traeVref = (bool) preg_match('/Vref\s+\d+/i', $lines);

            $velocidad = null;
            if (preg_match('/Velocidad\s+([0-9.]+)\s*kts\s+fuera de\s+\[([0-9]+)[^0-9]+([0-9]+)\]/i', $lines, $m)) {
                $velocidad = (float) $m[1];
            }

            $sancionAntigua = (bool) preg_match(
                '/(?:COMPUERTA|APPROACH GATE)[\s\S]{0,120}?(?:INESTABILIZAD|UNSTABILIZED)/iu',
                $lines
            ) && !$traeVref;

            $landing = DB::table('pirep_landings')
                ->where('pirep_id', $pirep->id)
                ->first(['vref_kt', 'landing_weight_lbs']);

            $vref = $landing?->vref_kt !== null ? (float) $landing->vref_kt : null;

            if ($velocidad === null) {
                $veredicto = 'sin_velocidad';
                $ventana = null;
            } elseif ($vref === null) {
                $veredicto = 'sin_vref';
                $ventana = null;
            } else {
                $min = $vref - 5;
                $max = $vref + 15;
                $ventana = sprintf('[%d–%d]', $min, $max);
                $veredicto = ($velocidad >= $min && $velocidad <= $max) ? 'contradice' : 'confirma';
            }

            $rows[] = [
                'id'              => $pirep->id,
                'flight_number'   => $pirep->flight_number,
                'route'           => $pirep->dpt_airport_id.'-'.$pirep->arr_airport_id,
                'date'            => substr((string) $pirep->submitted_at, 0, 10),
                'cliente'         => $pirep->source_name,
                'tipo'            => $pirep->aircraft_type,
                'sancion_antigua' => $sancionAntigua,
                'vref'            => $vref,
                'velocidad'       => $velocidad,
                'ventana_nueva'   => $ventana,
                'trae_vref_log'   => $traeVref,
                'veredicto'       => $veredicto,
            ];
        }

        return $rows;
    }
}
