<?php

use Illuminate\Support\Facades\Route;
use Modules\VmsOpenFileManager\Http\Controllers\Pilot\FileDownloadController;
use Modules\VmsOpenFileManager\Http\Controllers\Pilot\LiveryController as PilotLiveryController;

// ==================== DOWNLOADS (File Manager) ====================
Route::middleware(['auth'])->group(function () {
    Route::get('/vmsOpenDownloads', [FileDownloadController::class, 'index'])->name('vmsopenfilemanager.downloads.index');
    Route::get('/vmsOpenDownloads/folder/{folderId}', [FileDownloadController::class, 'folder'])->name('vmsopenfilemanager.downloads.folder');
    Route::get('/vmsOpenDownloads/file/{fileId}', [FileDownloadController::class, 'download'])->name('vmsopenfilemanager.downloads.file');
});

// ==================== LIVERIES (Pilot) ====================
Route::middleware(['auth'])->prefix('liveries')->name('vmsopenfilemanager.liveries.')->group(function () {
    // Main pages
    Route::get('/', [PilotLiveryController::class, 'index'])->name('index');
    Route::get('/simulator/{simulator}', [PilotLiveryController::class, 'bySimulator'])->name('simulator');
    Route::get('/simulator/{simulator}/subfleet/{subfleet}', [PilotLiveryController::class, 'bySubfleet'])->name('subfleet');
    
    // Actions
    Route::get('/download/{id}', [PilotLiveryController::class, 'download'])->name('download');
    Route::get('/info/{id}', [PilotLiveryController::class, 'info'])->name('info');
    Route::get('/search', [PilotLiveryController::class, 'search'])->name('search');
    Route::get('/featured/{limit?}', [PilotLiveryController::class, 'featured'])->name('featured');
});
