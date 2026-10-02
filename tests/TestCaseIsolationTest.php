<?php

namespace Tests;

use App\Repositories\KvpRepository;
use Spatie\Valuestore\Valuestore;

/**
 * La suite no debe tocar el estado de produccion.
 *
 * Motivo: el KVP (`App\Repositories\KvpRepository`) es un fichero JSON en
 * `storage/app/kvp.json` y los tests lo compartian con la web. `VersionTest`
 * dejaba `new_version_available = true` y `latest_version_tag = 7.0.0-beta`, con
 * lo que el panel de admin avisaba de "New version 7.0.0-beta is available!".
 */
final class TestCaseIsolationTest extends TestCase
{
    private string $productionKvpPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productionKvpPath = storage_path('app/kvp.json');
    }

    public function test_kvp_store_used_by_tests_is_not_the_production_one(): void
    {
        $this->assertNotSame(
            $this->productionKvpPath,
            config('phpvms.kvp_storage_path'),
            'Los tests no deben escribir en el KVP de produccion'
        );

        $this->assertStringContainsString('testing', config('phpvms.kvp_storage_path'));
    }

    public function test_kvp_writes_during_tests_do_not_reach_production(): void
    {
        $key = 'isolation_probe';

        app(KvpRepository::class)->save($key, 'test');

        // Se escribe en el store de los tests...
        $this->assertTrue(Valuestore::make(config('phpvms.kvp_storage_path'))->has($key));

        // ...y no en el de produccion (si existe; solo se lee).
        if (file_exists($this->productionKvpPath)) {
            $this->assertFalse(
                Valuestore::make($this->productionKvpPath)->has($key),
                'El KVP de produccion ha recibido una escritura de los tests'
            );
        }
    }
}
