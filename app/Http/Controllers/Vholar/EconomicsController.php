<?php

namespace App\Http\Controllers\Vholar;

use App\Contracts\Controller;
use App\Services\ProfitabilityService;
use Illuminate\View\View;

/**
 * Economia de la compania (solo staff): utilidad anual, mensual del año en curso y
 * diaria del mes en curso, a partir del libro diario de los PIREPs.
 */
class EconomicsController extends Controller
{
    public function index(ProfitabilityService $profitability): View
    {
        return view('vholar::economics', [
            'series'   => $profitability->series(),
            'coverage' => $profitability->telemetryCoverage(),
        ]);
    }
}
