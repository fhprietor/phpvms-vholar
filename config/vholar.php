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
    'theme'   => 'vholar',

    /*
     * Entrega de la clave de NavData a los clientes ACARS (GET /api/navdata).
     *
     * El valor real de la clave y la URL del servicio viven en la tabla
     * `settings` (Admin > Settings): general.navdata_api_key y
     * general.navdata_api_url. Aqui solo va el sobre criptografico.
     *
     * El cliente descifra con la MISMA api_key con la que se autentica
     * (header X-API-KEY), asi que `kdf_salt` y `kdf_info` son constantes
     * publicas: cualquiera puede leerlas, la clave la aporta el piloto.
     */
    'navdata' => [
        // Validez del sobre entregado, en segundos. Pasado ese plazo el
        // cliente debe volver a pedirlo (asi recoge rotaciones de la clave).
        'envelope_ttl' => (int) env('VHOLAR_NAVDATA_ENVELOPE_TTL', 21600),

        // HKDF-SHA256 (RFC 5869): IKM = api_key del piloto.
        'kdf_salt' => 'vmsopenacars/navdata/v1',
        'kdf_info' => 'navdata-api-key',

        // Datos autenticados asociados (AAD) del sobre. Contexto publico que
        // ata el cifrado a este uso concreto.
        'aad' => 'vmsopenacars/navdata/v1',
    ],
];
