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
  `deploy/versions.yml`.

## Despliegue

Ver la seccion `deploy` de [versions.yml](versions.yml). Resumen:

1. `git checkout <tag>` + `git submodule update --init --recursive`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run production`
4. `php artisan migrate --force` (ver aviso de VmsOpenFileManager en versions.yml)
5. Visitar `/update` para sincronizar settings, permisos y modulos.
6. Limpiar caches. Aplicar a mano la lista `modules.enabled` si hace falta.

## Modulos de terceros con parches locales

`DisposableSpecial` y `CHJumpSeat` **si** se versionan aqui, con sus
modificaciones locales ya aplicadas. Es una **decision expresa del mantenedor**:
sus licencias prohiben redistribuir el codigo, pero el repositorio central es
privado y la responsabilidad se asume. No revertir sin hablarlo antes.

- **DisposableSpecial** (B.Fatih KOZ): "Redistributions NOT allowed WITHOUT
  written approval of copyright holder".
- **CHJumpSeat** (Cardinal Horizon): licencia comercial cuyo proposito explicito
  es evitar que el software y sus modificaciones se redistribuyan.

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
| `modules/DisposableBasic` | `FatihKoz/DisposableBasic` | tercero, sin cambios propios |
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

## Pendiente (siguiente iteracion)

1. ~~Extraer los modulos propios (`VmsOpenOps`, `VmsOpenFileManager`) a repos
   propios y pinarlos como submodulos~~ **Hecho**: viven en
   `fhprietor/vmsOpenOps` (587bda0) y `fhprietor/vmsOpenFileManager` (f174900),
   ambos con README propio.
   `TestABC` sigue vendorizado por ser un stub: decidir si se mantiene.
2. ~~Estado de modulos~~ **Hecho**: vive en `app/Database/seeds/modules.yml`
   (activador `database`) y se aplica en `/update`. Quedan dos filas huerfanas
   en la tabla (`VMSAcars`, `TestModule`) que no corresponden a modulos
   instalados; se pueden borrar cuando convenga.
3. ~~Convertir a migracion el backfill de `pireps.source_name`~~ **Hecho**:
   `app/Database/migrations/2026_09_30_120000_backfill_pireps_source_name.php`.
4. **Reponer los `.git` de los modulos** si se quiere volver a actualizarlos in
   situ: estan respaldados en `/tmp/phpvms-module-git/` (temporal).
