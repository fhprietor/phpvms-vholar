<?php

namespace Modules\VmsOpenFileManager\Providers;

use App\Services\ModuleService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private $moduleSvc;

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'vmsopenfilemanager');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'vmsopenfilemanager');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->moduleSvc = app(ModuleService::class);
        $this->moduleSvc->addAdminLink('File Manager', '/admin/vmsopenfilemanager', 'pe-7s-folder');
        $this->moduleSvc->addAdminLink('Liveries', '/admin/liveries', 'pe-7s-plane');
        $this->moduleSvc->addAdminLink('Manufacturers', '/admin/manufacturers', 'pe-7s-culture');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'vmsopenfilemanager'
        );
    }
}