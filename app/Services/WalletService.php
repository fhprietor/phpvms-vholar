<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Billetera del piloto: saldo y movimientos de su diario (`journals.type = USER`).
 *
 * El dinero del piloto sale de la contabilidad normal: el "Pilot Payment" de cada PIREP le
 * acredita (PirepFinanceService::payPilotPay) y los jumpseats/ferry/market le debitan.
 *
 * AQUI SOLO SE LEE. Los cobros los siguen haciendo los modulos con su propio flujo (que ya
 * comprueba el saldo y registra el asiento), para no tener dos sitios donde se mueve dinero.
 */
class WalletService extends Service
{
    /** Movimientos por pagina. */
    public const PER_PAGE = 20;

    /**
     * Resumen de la billetera: saldo actual, total acreditado y total debitado.
     *
     * @return array{balance: float, received: float, spent: float, count: int}
     */
    public function summary(User $user): array
    {
        $journal = $user->journal ?? null;

        if ($journal === null) {
            return ['balance' => 0.0, 'received' => 0.0, 'spent' => 0.0, 'count' => 0];
        }

        $row = DB::table('journal_transactions')
            ->where('journal_id', $journal->id)
            ->selectRaw('coalesce(sum(credit), 0) as c, coalesce(sum(debit), 0) as d, count(*) as n')
            ->first();

        return [
            'balance'  => round($journal->balance->getAmount() / 100, 2),
            'received' => round((int) ($row->c ?? 0) / 100, 2),
            'spent'    => round((int) ($row->d ?? 0) / 100, 2),
            'count'    => (int) ($row->n ?? 0),
        ];
    }

    /**
     * Movimientos del diario del piloto, del mas reciente al mas antiguo.
     */
    public function movements(User $user): LengthAwarePaginator
    {
        $journal = $user->journal ?? null;

        if ($journal === null) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, self::PER_PAGE);
        }

        return DB::table('journal_transactions')
            ->where('journal_id', $journal->id)
            ->orderByDesc('post_date')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Neto mensual de los ultimos meses, para el grafico.
     *
     * @return Collection<int, array{label: string, net: float}>
     */
    public function monthly(User $user, int $months = 12): Collection
    {
        $journal = $user->journal ?? null;

        if ($journal === null) {
            return collect();
        }

        $from = now()->subMonths($months - 1)->startOfMonth();

        return DB::table('journal_transactions')
            ->where('journal_id', $journal->id)
            ->where('post_date', '>=', $from->toDateString())
            ->selectRaw("date_format(post_date, '%Y-%m') as p")
            ->selectRaw('round((coalesce(sum(credit), 0) - coalesce(sum(debit), 0)) / 100, 2) as net')
            ->groupBy('p')
            ->orderBy('p')
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->p, 'net' => (float) $row->net]);
    }
}
