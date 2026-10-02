<?php

/**
 * Identidad y version de la instalacion Vholar (fork privado de phpVMS).
 *
 * `release` debe coincidir SIEMPRE con el `release:` de deploy/versions.yml: lo
 * vigila tests/DeployManifestTest.php. Se muestra en los pies de pagina junto a
 * la version de phpVMS, que NO vive aqui: la base se declara en
 * config/version.yml y se lee con App\Services\VersionService.
 */
return [
    'release' => 'vholar-1.1.1',
    'theme' => 'vholar',
];
