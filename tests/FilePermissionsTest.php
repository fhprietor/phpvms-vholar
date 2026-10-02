<?php

namespace Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * La aplicacion corre como www-data y lee ficheros del repo (config, vistas,
 * assets). Un fichero sin permiso de lectura para "otros" tumba la web entera
 * con un 500: paso el 2026-10-02 con `config/vholar.php` en modo 600, porque
 * Laravel hace `require` de todo `config/` al arrancar.
 *
 * Este test automatiza el mismo `find` del runbook (docs/OPERACION-PERMISOS.md)
 * para que el fallo salte en la suite y no en produccion.
 */
final class FilePermissionsTest extends TestCase
{
    /**
     * Directorios que la aplicacion lee en caliente. Fuera quedan vendor,
     * node_modules, storage (lo escribe www-data) y los que no lee la web.
     */
    private const ROOTS = [
        'app',
        'bootstrap',
        'config',
        'database',
        'public',
        'resources',
        'routes',
        // Los modulos tambien se leen en caliente (controladores, rutas y vistas):
        // un fichero en 600 ahi tambien tumba la pagina que lo carga.
        'modules',
    ];

    public function test_files_the_app_reads_are_world_readable(): void
    {
        $unreadable = [];

        foreach (self::ROOTS as $root) {
            $path = base_path($root);
            if (!is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($files as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                // Bit de lectura para "otros" (o+r): www-data no es el dueno
                if ((fileperms($file->getPathname()) & 0o004) === 0) {
                    $unreadable[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        sort($unreadable);

        $this->assertSame(
            [],
            $unreadable,
            "Hay ficheros que www-data no puede leer y pueden tumbar la web (arreglo: chmod 644 <fichero>):\n"
            .implode("\n", array_slice($unreadable, 0, 20))
        );
    }

    /**
     * Los ficheros de `config/` se cargan en el arranque: si uno no se puede
     * leer, todas las peticiones devuelven 500.
     */
    public function test_config_files_are_readable(): void
    {
        $unreadable = [];

        foreach (glob(config_path('*.php')) ?: [] as $file) {
            if ((fileperms($file) & 0o004) === 0) {
                $unreadable[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $unreadable,
            "config/*.php sin permiso de lectura para www-data (chmod 644):\n".implode("\n", $unreadable)
        );
    }
}
