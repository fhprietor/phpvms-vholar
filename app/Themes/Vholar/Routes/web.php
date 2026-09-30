<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Vholar\FleetGridController;

Route::middleware(['web'])->group(function () {
    Route::get('fleetgrid', [FleetGridController::class, 'index'])->name('vholar.fleet.grid');
    Route::get('fleetmap',  [FleetGridController::class, 'fleetMap'])->name('vholar.fleet.map');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('pilot-briefing', fn () => view('vholar::briefing'))->name('vholar.briefing');
});