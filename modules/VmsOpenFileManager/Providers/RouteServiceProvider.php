<?php

namespace Modules\VmsOpenFileManager\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutes();
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/web.php');

        Route::middleware(['web', 'auth', 'role:admin'])
            ->prefix('admin')
            ->group(__DIR__ . '/../Routes/admin.php');
    }

    protected function mapAdminRoutes(): void
{
    Route::middleware(['web', 'auth', 'role:admin'])
        ->prefix('admin')
        ->namespace('Modules\VmsOpenFileManager\Http\Controllers\Admin')
        ->group(__DIR__ . '/../Routes/admin.php');
}
}