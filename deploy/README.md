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
| Modulos de terceros con parches | `modules/DisposableSpecial`, `modules/CHJumpSeat` |
| Submodulo de terceros | `modules/DisposableBasic` |
| Parches de terceros | `patches/` (con su base upstream) |
| Documentacion | `CLAUDE.md`, `api_vms.md`, `docs/` |

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

## Pendiente (siguiente iteracion)

1. **Extraer los modulos propios** (`VmsOpenOps`, `VmsOpenFileManager`) a repos
   propios y pinarlos como submodulos.
2. **Forkear `DisposableSpecial` y `CHJumpSeat`** en repos propios (privados),
   commitear alli los parches de `patches/` y convertir los modulos en
   submodulos. Hoy estan vendorizados con sus parches exportados.
3. **Versionar el estado de modulos** (`config/modules_statuses.json` no
   existe): decidir si se versiona o se documenta.
4. **Convertir a migracion** los cambios de datos puntuales (el backfill de
   `pireps.source_name` de 2026-09-29).
