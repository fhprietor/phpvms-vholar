# Versionado y despliegue

Este repo es la fuente de verdad de la instalacion Vholar sobre phpVMS 7.
El punto de partida y las versiones exactas de cada pieza estan en
[versions.yml](versions.yml).

## Que hay versionado aqui

| Pieza | Donde |
|---|---|
| Parches al core de phpVMS | el propio repo (fork de `phpvms/phpvms`) |
| Tema `vholar` | `resources/views/layouts/vholar` + `app/Themes/Vholar` |
| Assets del tema | `public/disposable`, `public/image`, `public/images` |
| Modulos propios (submodulos) | `modules/VmsOpenOps`, `modules/VmsOpenFileManager` |
| Modulo propio vendorizado (stub) | `modules/TestABC` |
| Modulos de terceros con parches | `modules/DisposableSpecial`, `modules/CHJumpSeat` |
| Submodulo de terceros | `modules/DisposableBasic` |
| Parches de terceros (referencia) | `patches/` (con su base upstream) |
| Documentacion | `CLAUDE.md`, `api_vms.md`, `docs/` |
| Estado de modulos | `app/Database/seeds/modules.yml` |

## Que NO se versiona (a proposito)

- `.env` y cualquier secreto (claves de CARTO/NavData viven en la BD, no aqui).
- `vendor/`, `node_modules/`, `storage/` (incluye los configs de cliente con la
  API key de NavData) y `public/uploads` (contenido de pilotos).

## Tags

- Los tags `7.0.x` son de phpVMS upstream (base).
- Los releases de la VA usan el prefijo `vholar-` (p. ej. `vholar-1.0.0`) y
  deben ir acompanados de una entrada en `CHANGELOG.md` y de la actualizacion de
  **tres** sitios que han de decir lo mismo:
  1. `deploy/versions.yml` (`release:`),
  2. `config/vholar.php` (`release`, que es lo que se ve en los pies de pagina), y
  3. `config/version.yml` si el release sube la base de phpVMS.
  Lo vigila `tests/DeployManifestTest.php`: si se desincronizan, el test falla.

## Despliegue

Ver la seccion `deploy` de [versions.yml](versions.yml). Resumen:

1. `git checkout <tag>` + `git submodule update --init --recursive`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run production`
4. `php artisan migrate --force`
5. Visitar `/update` para sincronizar settings, permisos y modulos.
6. Limpiar caches. Aplicar a mano la lista `modules.enabled` si hace falta.

## Verificacion

- **Suite de tests**: `vendor/bin/phpunit`. Usa **SQLite en memoria**
  (`DB_CONNECTION=memory` en `phpunit.xml`), asi que no toca la BD real; requiere
  la extension `pdo_sqlite`. Estado en `vholar-1.1.1` (phpVMS 7.0.10):
  `OK (216 tests, 1223 assertions)`.
- **Aislamiento**: la suite usa su **propio KVP**
  (`storage/framework/testing/kvp.json`) y su **propio directorio de logs**
  (`storage/framework/testing/logs`). Importa porque el KVP real es
  `storage/app/kvp.json` (de ahi sale el aviso de "nueva version" del panel) y
  porque `CronCommand::redirectLoggingToFile('cron')` empalma el canal `cron` en
  el logger raiz, con lo que los tests volcaban miles de lineas `testing.` en
  `storage/logs/cron-*.log`. Lo vigila `tests/TestCaseIsolationTest.php`.
- **Smoke test manual**: dashboard de admin (sin el aviso de nueva version),
  `/admin/flights`, `/admin/pireps`, `/admin/users`, asignaciones, VmsOpenOps,
  VmsOpenFileManager, dashboard de piloto, listado y detalle de un PIREP
  (analisis ACARS), perfil y flights.
- Cuidado al tocar ACARS: el detalle del PIREP depende del parseo de los logs
  (`type=2`), asi que conviene revisar un PIREP con log tras cualquier cambio ahi.

## Modulos de terceros con parches locales

`DisposableSpecial`, `DisposableBasic` y `CHJumpSeat` **si** se versionan aqui,
con sus modificaciones locales ya aplicadas. No revertir sin hablarlo antes.

- **DisposableSpecial** (B.Fatih KOZ): "Redistributions NOT allowed WITHOUT
  written approval of copyright holder". Es una **decision expresa del
  mantenedor**: el repositorio central es privado y la responsabilidad se asume.
- **CHJumpSeat** (Cardinal Horizon): licencia comercial cuyo proposito explicito
  es evitar que el software y sus modificaciones se redistribuyan; misma decision
  expresa del mantenedor.
- **DisposableBasic** (B.Fatih KOZ): **BSD-3-Clause**, redistribuible conservando
  el aviso de copyright, con dos condiciones extra (nombre y enlace visibles en el
  pie de todas las paginas, y una lista de aerolineas virtuales excluidas donde
  Vholar no esta). Pasa de **submodulo a vendorizado** el 2026-10-02: su remoto es
  upstream (`FatihKoz/DisposableBasic`), asi que las vistas migradas a Bootstrap 5
  no se podian empujar alli. Parche:
  `patches/DisposableBasic-f0b03db.patch`.

`DisposableBasic` se versiona como **submodulo** que apunta a su repo upstream:
no redistribuye nada.

### Actualizar un modulo vendorizado

Al vendorizarlos se retiraron sus `.git` del arbol de trabajo (respaldados fuera
del repo), asi que ya no son clones actualizables in situ. Para subir de version:

1. Clonar upstream en un directorio temporal: `git clone <upstream> /tmp/mod`.
2. Ver el diff frente a la base anotada en `deploy/versions.yml`.
3. Reaplicar el parche propio: `git -C /tmp/mod apply <patches/...patch>` (puede
   requerir ajustes si upstream cambio esas lineas; revisar a mano).
4. Copiar el resultado sobre `modules/<Modulo>` y actualizar `base_commit` y el
   fichero de `patches/` en `versions.yml`.
5. Regenerar el parche contra la nueva base para dejar constancia.

## Submodulos

| Submodulo | Repo | Para que |
|---|---|---|
| `modules/VmsOpenOps` | `fhprietor/vmsOpenOps` | modulo propio |
| `modules/VmsOpenFileManager` | `fhprietor/vmsOpenFileManager` | modulo propio |

- Traer un clon completo: `git submodule update --init --recursive`.
- **Cambiar un modulo**: se edita en su repo, se empuja, y aqui se sube el pin
  (`git add modules/<Modulo> && git commit`). No se edita a mano en el central.
- **Nota de permisos**: los directorios de modulo pertenecen a `www-data` (los
  escribe el instalador de la web), asi que git exige `safe.directory`. Si
  `~/.gitconfig` es escribible:
  `git config --global --add safe.directory /var/www/phpvms/modules/VmsOpenOps`
  (idem para el resto). Si no, pasar `-c safe.directory=<ruta>` en cada comando.

## Ruta a phpVMS 8 (investigacion, 2026-10-02)

Comprobado contra upstream en `73199ca2` (14-sep-2026) y
[docs.phpvms.net/8.x](https://docs.phpvms.net/8.x/whats-new). **phpVMS 8 NO esta
publicado**: no hay tags `8.x`, `main` declara `8.0.0` y la documentacion de la
8.x esta marcada como *unreleased*. Upstream guarda ademas una guia interna de
migracion todavia en **draft** (`docs/upgrading-to-8.0.md`).

### Distancia desde esta base (7.0.10)

| Metrica | Valor |
|---|---|
| Commits / ficheros | 973 / 3.261 |
| Migraciones nuevas | 105 |
| PHP requerido | >= 8.4.1 (aqui: 8.3.35) |
| Framework | Laravel 10 -> 13 |
| Admin | Rehecho en Filament 5; el admin Blade de 7.x desaparece |
| Build / tests | Vite + bun (adios webpack.mix); Pest 4 en vez de PHPUnit |

### Impacto en lo nuestro

| Personalizacion | En phpVMS 8 |
|---|---|
| Admin BS3 + `vholar-admin.css` + overrides de admin | Se pierde: admin Filament, URLs nuevas |
| Modulos (propios y de terceros) | `nwidart/laravel-modules` eliminado: sistema de addons (`addons`, `AddonServiceProvider`, `module.json` validado) |
| Activador por BD (`DatabaseActivator` + `modules.yml`) | Sustituido por el gestor de addons |
| Laratrust (`ability:admin,admin-access`, `role:admin`) | `spatie/laravel-permission`, permisos renombrados |
| `PirepRepository` (filtro `source_name` de vmsOpenAcars) | El patron repositorio se elimina: rehacer con Query classes |
| `App\Models\Enums\*` (`AcarsType`, `PirepState`...) | Pasan a `App\Enums\*` como enums nativos |
| `app/Http/Kernel.php` + `RouteServiceProvider` | `bootstrap/app.php`: no hay Kernel |
| `flights.active` | Renombrada a `flights.enabled` (la vista `month_assignments` la consulta) |
| ACARS | Nueva tabla `pirep_positions`; migracion destructiva que purga huerfanos de `acars` |
| Notificaciones Discord | Settings `*_webhook_url` -> `*_route` |
| PIREP fields (los 3 de vmsOpenAcars) | Pasan a ser tipados (`type` / `units`) |

### Lo que sobrevive (y lo que no rompe)

- **Temas**: el sistema sigue igual (`igaster/laravel-theme`,
  `resources/views/layouts/<nombre>`), asi que la arquitectura del tema `vholar`
  es reutilizable; habria que portar los assets a Vite y refrescar la base
  Disposable.
- **Clientes ACARS**: la superficie de la API no cambia (salvo
  `airports/{id}` -> `airports/{airport}` y un endpoint nuevo) y las API keys por
  usuario siguen funcionando (OAuth2 se anade, no sustituye). vmsOpenAcars y
  vmsACARS no se romperian por la API.

### Plan

1. **Ahora**: quieto en 7.0.10, el final de la linea 7.x (suite en verde).
2. **Cuando salga RC o tag de 8.0**: spike aislado (contenedor PHP 8.4 + 8.0
   limpio) portando solo el tema y un modulo, para medir el esfuerzo real.
3. **Si se migra**, orden: infra PHP 8.4 + renombres de `.env`
   (`CACHE_DRIVER`->`CACHE_STORE`, etc.) -> tema a Vite -> roles/permisos ->
   addons -> admin a Filament -> rehacer el filtro `source_name` y arreglar
   `month_assignments` con `flights.enabled`.
4. **Dependencia externa**: el tema va sobre Disposable Theme v3 y hay 4-5
   modulos Disposable/CHJumpSeat; sin versiones para 8 no hay migracion posible.

## Pendiente (siguiente iteracion)

1. ~~Extraer los modulos propios (`VmsOpenOps`, `VmsOpenFileManager`) a repos
   propios y pinarlos como submodulos~~ **Hecho**: viven en
   `fhprietor/vmsOpenOps` (8a2f990) y `fhprietor/vmsOpenFileManager` (f174900),
   ambos con README propio.
   `TestABC` sigue vendorizado por ser un stub: decidir si se mantiene.
2. ~~Estado de modulos~~ **Hecho**: vive en `app/Database/seeds/modules.yml`
   (activador `database`) y se aplica en `/update`. Las dos filas huerfanas de la
   tabla (`VMSAcars`, `TestModule`, sin modulo en disco) se eliminaron el
   2026-10-02: la tabla queda con las **10 filas que coinciden exactamente con
   `modules/`** y con la semilla. `Sample` **si** es un modulo real (upstream lo
   elimina en 8.0, ver la ruta a phpVMS 8).
3. ~~Convertir a migracion el backfill de `pireps.source_name`~~ **Hecho**:
   `app/Database/migrations/2026_09_30_120000_backfill_pireps_source_name.php`.
4. **Reponer los `.git` de los modulos** si se quiere volver a actualizarlos in
   situ: estan respaldados en `/tmp/phpvms-module-git/` (temporal).
