<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Vholar\DispatchSuggestionController;
use App\Http\Controllers\Vholar\FleetGridController;
use App\Http\Controllers\Vholar\NetworkMapController;

Route::middleware(['web'])->group(function () {
    Route::get('fleetgrid', [FleetGridController::class, 'index'])->name('vholar.fleet.grid');
    Route::get('fleetmap',  [FleetGridController::class, 'fleetMap'])->name('vholar.fleet.map');
    Route::get('networkmap', [NetworkMapController::class, 'index'])->name('vholar.network.map');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('pilot-briefing', fn () => view('vholar::briefing'))->name('vholar.briefing');

    // Sugerido de PAX/carga del modal de despacho (SimBrief).
    Route::get('dispatch/suggestion', [DispatchSuggestionController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('vholar.dispatch.suggestion');
});