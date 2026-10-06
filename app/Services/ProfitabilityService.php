<?php

namespace App\Services;

use App\Contracts\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Utilidad (ingresos - costes) de los PIREPs, por periodo, para los graficos.
 *
 * El libro diario esta en centimos: ingreso = apuntes de tarifas (memo con "fare") y
 * coste = todos los apuntes al debe (mismo criterio que PirepEconomicsService::costs(),
 * que usa el analisis de rentabilidad). Se puede acotar a un piloto (su propia economia).
 *
 * AVISO DE CALIDAD DEL DATO: los PIREPs importados del CrewSystem (5.718) y los manuales
 * (227) no tienen telemetria ACARS, asi que no se les puede derivar el ingreso y sus
 * periodos salen peor de lo que fueron. `telemetryCoverage()` devuelve esa cobertura por
 * año para poder avisar en pantalla.
 */
class ProfitabilityService extends Service
{
    private const LEDGER_MODEL = 'App\Models\Pirep';

    /**
     * Series de utilidad: anual (ultimos 5 años), mensual del año en curso y diaria del
     * mes en curso. Si se pasa $userId, solo sus vuelos.
     *
     * @return array<string, mixed>
     */
    public function series(?string $userId = null): array
    {
        $year = (int) date('Y');
        $month = date('Y-m');

        return [
            'annual'   => $this->grouped($userId, '%Y', $year - 4),
            'monthly'  => $this->grouped($userId, '%Y-%m', null, $year.'-%'),
            'daily'    => $this->grouped($userId, '%Y-%m-%d', null, $month.'-%'),
            'year'     => $year,
            'month'    => $month,
            'totals'   => [
                'year'  => $this->total($userId, $year.'-%'),
                'month' => $this->total($userId, $month.'-%'),
            ],
        ];
    }

    /**
     * Cobertura de ingresos por año: PIREPs aceptados frente a los que tienen ingreso.
     *
     * @return Collection<int, object>
     */
    public function telemetryCoverage(): Collection
    {
        $accepted = DB::table('pireps')->where('state', 2)
            ->selectRaw("date_format(submitted_at, '%Y') as y, count(*) as n")
            ->groupBy('y')->pluck('n', 'y');

        $withIncome = DB::table('journal_transactions')
            ->where('ref_model', self::LEDGER_MODEL)
            ->where('memo', 'like', '%fare%')
            ->join('pireps', 'pireps.id', '=', 'journal_transactions.ref_model_id')
            ->where('pireps.state', 2)
            ->selectRaw("date_format(pireps.submitted_at, '%Y') as y, count(distinct pireps.id) as n")
            ->groupBy('y')->pluck('n', 'y');

        return $accepted->map(fn ($n, $y) => (object) [
            'year'     => $y,
            'accepted' => (int) $n,
            'with_income' => (int) ($withIncome[$y] ?? 0),
            'coverage' => $n > 0 ? (int) round(100 * ($withIncome[$y] ?? 0) / $n) : 0,
        ])->values();
    }

    /**
     * Analisis financiero de un PIREP concreto: ingresos por tarifas, desglose de costes
     * (via PirepEconomicsService::costs()) y resultado.
     *
     * `revenue` es el bruto de las tarifas (credito de los apuntes con "fare"); los costes
     * incluyen el coste de esas mismas tarifas y todos los demas apuntes al debe, que es el
     * mismo criterio del analisis de rentabilidad y de PirepEconomicsService::costs().
     *
     * @return array<string, mixed>
     */
    public function forPirep(\App\Models\Pirep $pirep): array
    {
        $income = "sum(case when lower(memo) like '%fare%' then credit else 0 end)";

        $row = DB::table('journal_transactions')
            ->where('ref_model', self::LEDGER_MODEL)
            ->where('ref_model_id', $pirep->id)
            ->selectRaw("round({$income} / 100) as income")
            ->selectRaw('round(sum(debit) / 100) as cost')
            ->first();

        $revenue = (float) ($row->income ?? 0);

        $breakdown = $this->costBreakdown($pirep);
        $total = (float) array_sum($breakdown);

        $hours = max((float) $pirep->flight_time / 60, 0.01);
        $profit = round($revenue - $total, 2);

        return [
            'revenue'      => $revenue,
            'costs_total'  => $total,
            'profit'       => $profit,
            'margin'       => $total > 0 ? round(100 * $profit / $total, 1) : null,
            'breakdown'    => $breakdown,
            'hours'        => round($hours, 2),
            'revenue_hour' => round($revenue / $hours),
            'cost_hour'    => round($total / $hours),
            'has_ledger'   => $revenue !== 0.0 || $total > 0.0,
            'fares'        => DB::table('pirep_fares')->where('pirep_id', $pirep->id)
                ->get(['code', 'count', 'price', 'cost', 'type']),
        ];
    }

    /**
     * Costes del PIREP clasificados y con etiqueta legible.
     *
     * Se clasifica aqui (y no con PirepEconomicsService::concept(), que devuelve claves
     * internas como `block_time`) para que la tarjeta se lea en cristiano y para no depender
     * de un fichero en curso de otro hilo. El criterio de fondo es el mismo: TODOS los
     * apuntes al debe del PIREP.
     *
     * @return array<string, float>
     */
    private function costBreakdown(\App\Models\Pirep $pirep): array
    {
        $labels = [
            'tarifas'  => 'Coste de las tarifas',
            'fuel'     => 'Combustible',
            'bloque'   => 'Coste de bloque',
            'piloto'   => 'Pago al piloto',
            'handling' => 'Handling',
            'otros'    => 'Otros',
        ];

        $rows = DB::table('journal_transactions')
            ->where('ref_model', self::LEDGER_MODEL)
            ->where('ref_model_id', $pirep->id)
            ->where('debit', '>', 0)
            ->get(['memo', 'debit']);

        $buckets = [];
        foreach ($rows as $row) {
            $memo = strtolower((string) $row->memo);

            if (str_contains($memo, 'fare')) {
                $key = 'tarifas';
            } elseif (str_contains($memo, 'fuel')) {
                $key = 'fuel';
            } elseif (str_contains($memo, 'block time')) {
                $key = 'bloque';
            } elseif (str_contains($memo, 'pilot payment')) {
                $key = 'piloto';
            } elseif (str_contains($memo, 'handling')) {
                $key = 'handling';
            } else {
                $key = 'otros';
            }

            $buckets[$key] = round(($buckets[$key] ?? 0) + ((int) $row->debit) / 100, 2);
        }

        arsort($buckets);

        $out = [];
        foreach ($buckets as $key => $amount) {
            $out[$labels[$key]] = $amount;
        }

        return $out;
    }

    /**
     * @return array<int, array{label: string, income: float, cost: float, profit: float}>
     */
    private function grouped(?string $userId, string $format, ?int $fromYear = null, ?string $like = null): array
    {
        $income = "sum(case when lower(journal_transactions.memo) like '%fare%' then journal_transactions.credit else 0 end)";

        $query = DB::table('journal_transactions')
            ->where('ref_model', self::LEDGER_MODEL)
            ->join('pireps', 'pireps.id', '=', 'journal_transactions.ref_model_id')
            ->selectRaw("date_format(pireps.submitted_at, '{$format}') as p")
            ->selectRaw("round({$income} / 100) as income")
            ->selectRaw('round(sum(journal_transactions.debit) / 100) as cost')
            ->groupBy('p')
            ->orderBy('p');

        if ($userId !== null) {
            $query->where('pireps.user_id', $userId);
        }

        if ($fromYear !== null) {
            $query->whereYear('pireps.submitted_at', '>=', $fromYear);
        }

        if ($like !== null) {
            $query->where('pireps.submitted_at', 'like', $like.'%');
        }

        return $query->get()->map(fn ($row) => [
            'label'  => (string) $row->p,
            'income' => (float) $row->income,
            'cost'   => (float) $row->cost,
            'profit' => (float) $row->income - (float) $row->cost,
        ])->all();
    }

    private function total(?string $userId, string $like): array
    {
        $income = "sum(case when lower(journal_transactions.memo) like '%fare%' then journal_transactions.credit else 0 end)";

        $query = DB::table('journal_transactions')
            ->where('ref_model', self::LEDGER_MODEL)
            ->join('pireps', 'pireps.id', '=', 'journal_transactions.ref_model_id')
            ->where('pireps.submitted_at', 'like', $like.'%')
            ->selectRaw("round({$income} / 100) as income")
            ->selectRaw('round(sum(journal_transactions.debit) / 100) as cost');

        if ($userId !== null) {
            $query->where('pireps.user_id', $userId);
        }

        $row = $query->first();

        return [
            'income' => (float) ($row->income ?? 0),
            'cost'   => (float) ($row->cost ?? 0),
            'profit' => (float) ($row->income ?? 0) - (float) ($row->cost ?? 0),
        ];
    }
}
