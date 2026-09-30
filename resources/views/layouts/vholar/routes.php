<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VHOLAR Theme Routes
|--------------------------------------------------------------------------
|
| Estas rutas son específicas del tema Vholar y se cargan automáticamente
| cuando el tema está activo.
|
*/

// Página de OM-A (para crear después)
Route::get('/page/oma', function () {
    return view('layouts.vholar.pages.oma');
})->name('oma');

// Página de wallet (para desarrollar después)
Route::get('/page/wallet', function () {
    return view('layouts.vholar.pages.wallet');
})->name('wallet');