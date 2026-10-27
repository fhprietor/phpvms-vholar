<?php

namespace App\Http\Controllers\Vholar;

use App\Contracts\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Billetera del piloto: saldo, movimientos y accesos a las compras que ya cobran los modulos
 * (jumpseat, ferry, charter, market). No mueve dinero por si misma.
 */
class WalletController extends Controller
{
    public function index(Request $request, WalletService $wallet): View
    {
        $user = $request->user();

        return view('vholar::wallet', [
            'summary'   => $wallet->summary($user),
            'movements' => $wallet->movements($user),
            'monthly'   => $wallet->monthly($user),
        ]);
    }
}
