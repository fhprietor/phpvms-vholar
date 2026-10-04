<?php

namespace App\Services;

use App\Contracts\Service;
use App\Helpers\FlightAnalysisHelper;
use App\Models\Enums\AcarsType;
use App\Models\Pirep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comparativa contra la empresa en el mismo trayecto.
 *
 * La idea es que el piloto pueda medirse: no basta con decirle que su consumo
 * fue de 221 kg/100nm, hay que decirle si eso es bueno o malo PARA ESE TRAYECTO
 * comparado con sus companeros.
 *
 * Reglas de la comparacion:
 *  - Solo se comparan vuelos del MISMO cliente ACARS (`source`), porque los
 *    importados no tienen telemetria homogenea. Hoy eso significa vmsOpenAcars.
 *  - El trayecto es el PAR DE AEROPUERTOS, en cualquier sentido: hay muy pocos
 *    vuelos por sentido exacto y el mismo par es el mismo trayecto a efectos de
 *    consumo y productividad. El sentido se informa aparte por si acaso.
 *  - El vuelo analizado se EXCLUYE de la media: se mide contra los companeros,
 *    no contra si mismo.
 *  - Sin companeros no hay comparativa: devuelve null en vez de inventar una
 *    media con un solo vuelo.
 */
class PirepBenchmarkService extends Service
{
    /**
     * Cliente ACARS cuyos vuelos se consideran comparables.
     */
    public const DEFAULT_SOURCE = 'vmsOpenAcars';

    /**
     * Metricas que se comparan: clave => [etiqueta, como se extrae].
     * Menos es mejor en consumo y coste; mas es mejor en pasajeros y beneficio.
     */
    private const METRICS = [
        'consumo_por_100nm'  => ['label' => 'Consumo por 100 nm', 'lower_is_better' => true],
        'consumo_por_hora'   => ['label' => 'Consumo por hora', 'lower_is_better' => true],
        // Esta metrica YA es un porcentaje. Comparar dos porcentajes con una
        // diferencia relativa da numeros sin sentido (-1% frente a -3,67%
        // saldria como "72,8%"), asi que las metricas porcentuales se comparan
        // en PUNTOS porcentuales.
        'desvio_plan_pct'    => ['label' => 'Desvio sobre lo planificado', 'lower_is_better' => true, 'is_percentage' => true],
        'pasajeros'          => ['label' => 'Pasajeros', 'lower_is_better' => false],
        'carga'              => ['label' => 'Carga', 'lower_is_better' => false],
        'coste_por_pasajero' => ['label' => 'Coste por pasajero', 'lower_is_better' => true],
        'coste_por_nm'       => ['label' => 'Coste por nm', 'lower_is_better' => true],
        'beneficio'          => ['label' => 'Beneficio estimado', 'lower_is_better' => false],
        'margen_pct'         => ['label' => 'Margen estimado', 'lower_is_better' => false, 'is_percentage' => true],
    ];

    public function __construct(
        private readonly PirepEconomicsService $economics
    ) {}

    /**
     * Compara el vuelo con los de otros pilotos en el mismo trayecto.
     *
     * @param  array<string, mixed>      $operations Operaciones ya parseadas del vuelo
     * @return array<string, mixed>|null null si no hay companeros
     */
    public function routeBenchmark(Pirep $pirep, array $operations): ?array
    {
        $peers = $this->peers($pirep);

        if ($peers->isEmpty()) {
            return null;
        }

        $mine = $this->metricsFor($pirep, $operations);

        $peerValues = [];
        foreach ($peers as $peer) {
            $peerMetrics = $this->metricsFor($peer, FlightAnalysisHelper::parseLogData($peer)['operations'] ?? []);
            foreach ($peerMetrics as $key => $value) {
                if ($value !== null) {
                    $peerValues[$key][] = $value;
                }
            }
        }

        $averages = [];
        $comparison = [];

        foreach (self::METRICS as $key => $meta) {
            $values = $peerValues[$key] ?? [];
            if (empty($values) || ($mine[$key] ?? null) === null) {
                continue;
            }

            $average = round(array_sum($values) / count($values), 2);
            $averages[$key] = $average;

            $isPercentage = $meta['is_percentage'] ?? false;
            $gap = $mine[$key] - $average;

            // Las metricas porcentuales se comparan en puntos porcentuales; el
            // resto, en variacion relativa frente a la media.
            $delta = $isPercentage
                ? round($gap, 1)
                : ($average != 0.0 ? round(($gap / abs($average)) * 100, 1) : null);

            // "Mejor" se decide sobre la magnitud, no sobre la diferencia: menos
            // consumo es mejor, mas pasajeros es mejor.
            $better = $meta['lower_is_better'] ? $gap < 0 : $gap > 0;

            $comparison[$key] = [
                'label'             => $meta['label'],
                'tu'                => round((float) $mine[$key], 1),
                'media_compania'    => $average,
                'diferencia'        => $delta,
                'unidad_diferencia' => $isPercentage ? 'puntos_porcentuales' : 'por_ciento',
                'mejor_que_media'   => $better,
                'companeros'      => count($values),
            ];
        }

        if (empty($comparison)) {
            return null;
        }

        return [
            'trajecto'          => $this->routeKey($pirep),
            'mismo_sentido'     => $peers->where('dpt_airport_id', $pirep->dpt_airport_id)->count(),
            'vuelos_comparados' => $peers->count(),
            'pilotos_distintos' => $peers->pluck('user_id')->unique()->count() + 1,
            'comparativa'       => $comparison,
        ];
    }

    /**
     * Vuelos comparables: mismo par de aeropuertos, mismo cliente ACARS, otro
     * piloto, excluyendo el propio vuelo.
     *
     * @return Collection<int, Pirep>
     */
    private function peers(Pirep $pirep): Collection
    {
        $dpt = $pirep->dpt_airport_id;
        $arr = $pirep->arr_airport_id;

        if (!$dpt || !$arr) {
            return collect();
        }

        $sourcePattern = $this->sourcePattern($pirep->source_name);

        return Pirep::query()
            ->where('id', '!=', $pirep->id)
            ->where('user_id', '!=', $pirep->user_id)   // contra companeros, no contra uno mismo
            ->where(function ($query) use ($dpt, $arr) {
                // Mismo trayecto en cualquier sentido.
                $query->where(function ($q) use ($dpt, $arr) {
                    $q->where('dpt_airport_id', $dpt)->where('arr_airport_id', $arr);
                })->orWhere(function ($q) use ($dpt, $arr) {
                    $q->where('dpt_airport_id', $arr)->where('arr_airport_id', $dpt);
                });
            })
            ->when($sourcePattern !== null, fn ($q) => $q->where('source_name', 'like', $sourcePattern))
            // Sin telemetria no se puede comparar consumo ni payload.
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('acars')
                    ->whereColumn('acars.pirep_id', 'pireps.id')
                    ->where('acars.type', AcarsType::LOG);
            })
            ->get();
    }

    /**
     * Metricas comparables de un vuelo. Las de combustible y payload salen del
     * log; las economicas, del libro mayor y la estimacion de ingreso.
     *
     * @param  array<string, mixed>      $operations
     * @return array<string, float|null>
     */
    private function metricsFor(Pirep $pirep, array $operations): array
    {
        $payload = $operations['payload'] ?? [];
        $fuel = $operations['fuel'] ?? [];

        $hours = ((int) $pirep->getRawOriginal('flight_time')) / 60;
        $distance = (float) $pirep->getRawOriginal('distance');

        // `fuel_used` es un objeto de unidades; `local()` lo da en la unidad que
        // la VA muestra al piloto. Un consumo de cero es fisicamente imposible y
        // el cast puede devolver un objeto a cero cuando la columna esta nula,
        // asi que todo lo que no sea positivo se trata como SIN DATO: si no, ese
        // cero entraria en la media de la compania y la falsearia a la baja.
        $used = null;
        if ($pirep->fuel_used !== null) {
            $local = (float) $pirep->fuel_used->local();
            $used = $local > 0 ? $local : null;
        }

        $metrics = [
            'consumo_por_100nm' => ($used !== null && $distance > 0) ? round($used / $distance * 100, 1) : null,
            'consumo_por_hora'  => ($used !== null && $hours > 0) ? round($used / $hours, 1) : null,
            'desvio_plan_pct'   => $fuel['trip_vs_planned_pct'] ?? null,
            'pasajeros'         => isset($payload['pax']) ? (float) $payload['pax'] : null,
            'carga'             => $payload['cargo'] ?? null,
        ];

        $summary = $this->economics->summary($pirep, $operations);
        if ($summary !== null) {
            $metrics['coste_por_pasajero'] = $summary['cost_per_pax'] ?? null;
            $metrics['coste_por_nm'] = $summary['cost_per_nm'] ?? null;
            $metrics['beneficio'] = $summary['profit'] ?? null;
            $metrics['margen_pct'] = $summary['margin_pct'] ?? null;
        }

        return $metrics;
    }

    /**
     * "SKBO-SKCL". El orden se normaliza para que el par tenga una sola clave.
     */
    private function routeKey(Pirep $pirep): string
    {
        $a = (string) $pirep->dpt_airport_id;
        $b = (string) $pirep->arr_airport_id;

        return min($a, $b).'-'.max($a, $b);
    }

    /**
     * Patrón LIKE del cliente ACARS. Se reutiliza el del propio vuelo cuando lo
     * tiene, y si no el de por defecto.
     */
    private function sourcePattern(?string $sourceName): ?string
    {
        $name = $sourceName ?: self::DEFAULT_SOURCE;

        // "vmsOpenAcars/0.9.24" -> "vmsOpenAcars"
        $base = explode('/', $name)[0];

        return $base !== '' ? $base.'%' : null;
    }
}
