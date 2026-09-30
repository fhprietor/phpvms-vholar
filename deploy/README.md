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
| Modulos propios | `modules/VmsOpenOps`, `modules/VmsOpenFileManager`, `modules/TestABC` |
| Submodulo de terceros | `modules/DisposableBasic` |
| Parches de terceros no versionados | `patches/` (con su base upstream) |
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

## Modulos de terceros: que NO se versiona y por que

`DisposableSpecial` y `CHJumpSeat` **no** estan en este repo aunque se usen:

- **DisposableSpecial** (B.Fatih KOZ): "Redistributions NOT allowed WITHOUT
  written approval of copyright holder". Solo se pueden redistribuir sus vistas
  blade desde un tema de terceros.
- **CHJumpSeat** (Cardinal Horizon): licencia comercial cuyo proposito explicito
  es evitar que el software y sus modificaciones se redistribuyan.

Ambos se instalan aparte (son clones en `modules/`) y sus parches propios se
guardan en `patches/`. `DisposableBasic` si se versiona, pero como **submodulo**
que apunta a su repo upstream: no redistribuye nada.

## Pendiente (siguiente iteracion)

1. **Extraer los modulos propios** (`VmsOpenOps`, `VmsOpenFileManager`) a repos
   propios y pinarlos como submodulos. `TestABC` es un stub: decidir si se
   mantiene.
2. **Pedir aprobacion escrita** a los autores de DisposableSpecial (y valorar el
   caso de CHJumpSeat) si se quiere versionar su codigo o un fork. Mientras no
   haya permiso, se quedan fuera y se reaplican los parches.
3. ~~Versionar el estado de modulos~~ **Hecho**: vive en
   `app/Database/seeds/modules.yml` (activador `database`) y se aplica en
   `/update`. Quedan dos filas huerfanas en la tabla (`VMSAcars`, `TestModule`)
   que no corresponden a modulos instalados; se pueden borrar cuando convenga.
4. ~~Convertir a migracion el backfill de `pireps.source_name`~~ **Hecho**:
   `app/Database/migrations/2026_09_30_120000_backfill_pireps_source_name.php`.
