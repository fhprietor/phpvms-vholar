<?php

use Illuminate\Support\Facades\Route;
use Modules\VmsOpenFileManager\Http\Controllers\Admin\FileManagerController;
use Modules\VmsOpenFileManager\Http\Controllers\Admin\LiveryController as AdminLiveryController;
use Modules\VmsOpenFileManager\Http\Controllers\Admin\ManufacturerController;

// ==================== MANUFACTURERS (Admin) ====================
Route::prefix('manufacturers')->name('admin.manufacturers.')->group(function () {
    Route::get('/',           [ManufacturerController::class, 'index'])->name('index');
    Route::post('/',          [ManufacturerController::class, 'store'])->name('store');
    Route::put('/{manufacturer}',    [ManufacturerController::class, 'update'])->name('update');
    Route::delete('/{manufacturer}', [ManufacturerController::class, 'destroy'])->name('destroy');
});

// ==================== FILE MANAGER (Admin) ====================
Route::prefix('vmsopenfilemanager')->name('admin.vmsopenfilemanager.')->group(function () {
    Route::get('/', [FileManagerController::class, 'index'])->name('index');
    Route::post('/folder', [FileManagerController::class, 'createFolder'])->name('folder.create');
    Route::post('/upload', [FileManagerController::class, 'uploadFile'])->name('file.upload');
    Route::delete('/file/{id}', [FileManagerController::class, 'deleteFile'])->name('file.delete');
    Route::delete('/folder/{id}', [FileManagerController::class, 'deleteFolder'])->name('folder.delete');
});

// ==================== LIVERIES (Admin) ====================
Route::prefix('liveries')->name('admin.liveries.')->group(function () {
    Route::get('/', [AdminLiveryController::class, 'index'])->name('index');
    Route::post('/upload', [AdminLiveryController::class, 'upload'])->name('upload');
    Route::delete('/{id}', [AdminLiveryController::class, 'delete'])->name('delete');
    Route::post('/{id}/toggle', [AdminLiveryController::class, 'toggleActive'])->name('toggle');
    Route::post('/update-order', [AdminLiveryController::class, 'updateOrder'])->name('update-order');
    Route::get('/{id}/edit', [AdminLiveryController::class, 'edit'])->name('edit');
    Route::put('/{id}', [AdminLiveryController::class, 'update'])->name('update');
});