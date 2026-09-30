<?php

namespace App\Themes\Vholar;

use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registrar namespace de vistas
        $this->loadViewsFrom(resource_path('views/layouts/vholar'), 'vholar');
    }

    public function boot(): void
    {
        // Cargar rutas del tema
        if (file_exists(__DIR__ . '/Routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
        }
    }
}