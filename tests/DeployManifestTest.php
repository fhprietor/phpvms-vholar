<?php

namespace Tests;

use Symfony\Component\Yaml\Yaml;

/**
 * El manifiesto de despliegue es la fuente de verdad del tag, pero se lee a
 * mano: si deja de ser YAML valido nadie se entera hasta que alguien intenta
 * usarlo. Estos tests lo mantienen parseable y coherente.
 *
 * Motivo: `deploy/versions.yml` estuvo un tiempo con `caveats:` dentro de la
 * lista `deploy:` ("You cannot define a mapping item when in a sequence").
 */
final class DeployManifestTest extends TestCase
{
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifest = Yaml::parseFile(base_path('deploy/versions.yml'));
    }

    public function test_manifest_parses_and_has_its_sections(): void
    {
        $this->assertIsArray($this->manifest);

        foreach (['release', 'phpvms', 'theme', 'modules', 'deploy'] as $section) {
            $this->assertArrayHasKey($section, $this->manifest, "Falta la seccion '$section'");
        }
    }

    public function test_release_uses_the_va_tag_convention(): void
    {
        $this->assertMatchesRegularExpression(
            '/^vholar-\d+\.\d+\.\d+$/',
            $this->manifest['release']
        );
    }

    public function test_deploy_section_keeps_steps_and_caveats_as_siblings(): void
    {
        $deploy = $this->manifest['deploy'];

        $this->assertIsArray($deploy['steps'], 'deploy.steps debe ser una lista');
        $this->assertNotEmpty($deploy['steps']);
        $this->assertIsArray($deploy['caveats'], 'deploy.caveats debe ser una lista');
    }

    /**
     * El panel de admin avisa de "nueva version" comparando config/version.yml
     * con las releases de upstream: si el manifiesto sube la base y el fichero
     * se queda atras, el aviso reaparece (paso con 7.0.10).
     */
    public function test_declared_version_matches_the_phpvms_base(): void
    {
        $current = Yaml::parseFile(config_path('version.yml'))['current'];
        $declared = "{$current['major']}.{$current['minor']}.{$current['patch']}";

        $this->assertSame(
            $this->manifest['phpvms']['base_tag'],
            $declared,
            'config/version.yml debe coincidir con phpvms.base_tag del manifiesto'
        );
    }
}
