<?php

namespace App\Http\Controllers\Vholar;

use App\Contracts\Controller;
use App\Services\PassportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pasaporte del piloto: sellos de los aeropuertos internacionales visitados.
 */
class PassportController extends Controller
{
    public function index(Request $request, PassportService $passport): View
    {
        return view('vholar::passport', [
            'passport' => $passport->forUser($request->user()),
        ]);
    }
}
