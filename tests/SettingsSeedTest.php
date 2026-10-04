<?php

namespace Tests;

use App\Services\Installer\SeederService;
use Symfony\Component\Yaml\Yaml;

/**
 * El sync de settings decide si la app manda a /update: si un campo no cabe en
 * su columna, MySQL lo trunca, el valor guardado deja de coincidir con el del
 * YAML y `seedsPending()` devuelve true PARA SIEMPRE (entrar en /admin
 * redirige a /update en bucle, y visitarlo no arregla nada porque vuelve a
 * truncar igual).
 *
 * Paso con general.navdata_api_key: 196 caracteres de descripcion en una
 * columna de 191. SQLite (el motor de los tests) no impone la longitud, asi
 * que aqui se comprueba el limite declarado en AppServiceProvider
 * (`Schema::defaultStringLength(191)`).
 */
final class SettingsSeedTest extends TestCase
{
    /** Limite real de las columnas string de `settings`. */
    private const COLUMN_LIMIT = 191;

    private const LIMITED_COLUMNS = ['key', 'name', 'value', 'group', 'type', 'description'];

    public function test_every_seed_field_fits_in_its_column(): void
    {
        $violations = [];

        foreach (Yaml::parseFile(database_path('seeds/settings.yml')) as $setting) {
            foreach (self::LIMITED_COLUMNS as $column) {
                if (!isset($setting[$column])) {
                    continue;
                }

                $length = mb_strlen((string) $setting[$column]);
                if ($length > self::COLUMN_LIMIT) {
                    $violations[] = $setting['key'].'.'.$column.' ('.$length.' > '.self::COLUMN_LIMIT.')';
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Campos que MySQL truncaria: dejarian /update pendiente para siempre'
        );
    }

    public function test_the_seeds_converge_after_a_sync(): void
    {
        // TestCase::setUp() ya ejecuta syncAllSeeds(): no debe quedar nada
        // pendiente, o el panel de admin seria inalcanzable.
        $this->assertFalse(app(SeederService::class)->seedsPending());
    }
}
