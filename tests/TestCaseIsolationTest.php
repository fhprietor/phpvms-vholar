<?php

namespace Tests;

use App\Repositories\KvpRepository;
use Illuminate\Support\Facades\Log;
use Spatie\Valuestore\Valuestore;

/**
 * La suite no debe tocar el estado de produccion.
 *
 * Motivo: dos recursos que la app comparte entre web, cron y tests.
 *
 * - El KVP (`App\Repositories\KvpRepository`) es un fichero JSON en
 *   `storage/app/kvp.json` y los tests lo sobrescribian: `VersionTest` dejaba
 *   `new_version_available = true` con `latest_version_tag = 7.0.0-beta`, y el
 *   panel de admin avisaba de "New version 7.0.0-beta is available!".
 * - Los logs: `App\Contracts\CronCommand` empalma los handlers del canal `cron`
 *   en el logger raiz (singleton), asi que la suite volcaba miles de lineas
 *   `testing.` en `storage/logs/cron-*.log`.
 */
final class TestCaseIsolationTest extends TestCase
{
    private string $productionKvpPath;

    private string $productionLogsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productionKvpPath = storage_path('app/kvp.json');
        $this->productionLogsPath = storage_path('logs');
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

    public function test_log_channels_are_redirected_to_the_testing_directory(): void
    {
        foreach (['daily', 'single', 'cron_rotating'] as $channel) {
            $path = config("logging.channels.$channel.path");

            $this->assertStringContainsString('framework/testing', $path, "El canal '$channel' no esta aislado");
            $this->assertStringNotContainsString(
                $this->productionLogsPath,
                $path,
                "El canal '$channel' escribe en los logs de produccion"
            );
        }
    }

    public function test_log_writes_during_tests_do_not_reach_production(): void
    {
        $marker = 'isolation_probe_'.uniqid();

        Log::info($marker);

        // La marca debe estar en el directorio de logs de los tests...
        $found = false;
        foreach (glob(storage_path('framework/testing/logs/*')) ?: [] as $file) {
            if (str_contains((string) file_get_contents($file), $marker)) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'La marca de log no ha llegado al directorio de testing');

        // ...y en ninguno de produccion.
        foreach (glob($this->productionLogsPath.'/*.log') ?: [] as $file) {
            $this->assertStringNotContainsString(
                $marker,
                (string) file_get_contents($file),
                "El log de produccion '$file' ha recibido una escritura de los tests"
            );
        }
    }
}
