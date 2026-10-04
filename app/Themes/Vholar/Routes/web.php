<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Vholar\FleetGridController;
use App\Http\Controllers\Vholar\NetworkMapController;

Route::middleware(['web'])->group(function () {
    Route::get('fleetgrid', [FleetGridController::class, 'index'])->name('vholar.fleet.grid');
    Route::get('fleetmap',  [FleetGridController::class, 'fleetMap'])->name('vholar.fleet.map');
    Route::get('networkmap', [NetworkMapController::class, 'index'])->name('vholar.network.map');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('pilot-briefing', fn () => view('vholar::briefing'))->name('vholar.briefing');
});