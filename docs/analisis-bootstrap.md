# Análisis · Versiones de Bootstrap en el proyecto

**Fecha:** 2 de octubre de 2026
**Sospecha:** hay mezcla de versiones. **Confirmada.** Y la mezcla es peor de lo que parece, porque **una versión está tapando los fallos de otra**.

> **Estado: arreglado el 2 de octubre.** El frontend vholar ya carga **un solo** Bootstrap. Ver **§9 · Qué se hizo**.

---

## 1. Resumen

| | |
|---|---|
| Versiones distintas de Bootstrap **en el repositorio** | **5** · 3.3.5 · 3.3.7 · 3.4.1 · 4.3.1 · 5.1.3 · 5.3.3 |
| Versiones cargadas en el **frontend** | **2**: Bootstrap 5.3.3 (CSS + JS) **y** Bootstrap 4.3.1 (JS) |
| Versiones cargadas en el **admin** | 1: Bootstrap 3.4.1 |
| Versiones cargadas en el **login** | 1: Bootstrap 5.3.3 ✅ |
| Archivos huérfanos | 1 CSS de 324 KB (`admin.min.css`) |
| Assets duplicados | El instalador carga Bootstrap 4.3.1 **dos veces** |

**El caso grave está en el frontend**: dos librerías JS de Bootstrap distintas en la misma página, y la de Bootstrap 4 no es un residuo inofensivo — **está sosteniendo markup de Bootstrap 3 que de otro modo no funcionaría**.

---

## 2. Inventario completo

### 2.1 Versiones que existen

| Versión | Dónde vive | ¿Se carga? |
|---|---|---|
| **3.3.5** | `vendor/datatables/print.blade.php` (CDN maxcdn) | Solo al imprimir una tabla |
| **3.3.7** | `assets/admin/css/admin.min.css` (324 KB) | **NO — huérfano** |
| **3.4.1** | `assets/admin/css/vendor.css` + `assets/admin/js/vendor.js` | Sí, en el admin |
| **4.3.1** | `assets/frontend/css/bootstrap.min.css`, `assets/installer/css/vendor.css` y **dentro de `assets/frontend/js/vendor.js`** | Sí, en el frontend (JS) y en el instalador (CSS) |
| **5.1.3** | `layouts/Disposable_v3/app.blade.php` | No (tema inactivo) |
| **5.3.3** | CDN, en vholar, seven y login | Sí |

### 2.2 Dependencias declaradas en `package.json`

Cuatro paquetes de Bootstrap, de tres versiones mayores distintas:

```json
"bootstrap":        "~4.3",          // Bootstrap 4
"bootstrap-sass":   "^3.4.1",        // Bootstrap 3
"bootstrap3":       "npm:bootstrap@~3.4",   // Bootstrap 3, aliasado
"paper-dashboard":  "^1.1.0",        // arrastra Bootstrap 3
"eonasdan-bootstrap-datetimepicker": "^4.17.47"   // exige Bootstrap 3
```

> `node_modules/` **no está instalado**, así que no se pueden verificar las versiones realmente resueltas. Los bundles servidos sí se pueden leer, y es lo que se documenta aquí.

### 2.3 Qué carga cada superficie (verificado)

| Superficie | Bootstrap CSS | Bootstrap JS | Veredicto |
|---|---|---|---|
| **Frontend vholar** | 5.3.3 (CDN) | 5.3.3 (CDN) **+ 4.3.1** (`frontend/js/vendor.js`) | ⛔ **mezcla** |
| **Admin** | 3.4.1 (`admin/css/vendor.css`) | 3.4.1 (`admin/js/vendor.js`) | ⚠️ duplica plugins |
| **Login** | 5.3.3 (CDN) | 5.3.3 (CDN) | ✅ limpio |
| **Instalador / updater** | 4.3.1 **×2** | — | ⚠️ duplicado |
| **Errores del sistema** | 4.3.1 | — | ✅ coherente |
| `vendor/datatables/print` | 3.3.5 | — | ⚠️ tercera versión de BS3 |

---

## 3. El conflicto del frontend, con evidencia

### 3.1 Qué dice la configuración de build

`webpack.mix.js`, en `buildFrontendAssets()`:

```js
mix.scripts([
  'node_modules/moment/moment.js',
  'node_modules/popper.js/dist/umd/popper.js',
  'node_modules/popper.js/dist/umd/popper-utils.js',
  'node_modules/bootstrap/dist/js/bootstrap.js',   // ← bootstrap ~4.3
], 'public/assets/frontend/js/vendor.js');
```

### 3.2 Qué hay de verdad dentro del bundle servido

```
assets/frontend/js/vendor.js   →  "Bootstrap's JavaScript requires at least
                                   jQuery v1.9.1 but less than v4.0.0"
                               →  "Bootstrap's dropdowns require Popper.js (https://popper.js.org/)"
                               →  e.Util=u, e.Alert=v, e.Button=x, e.Carousel=ue, e.Collapse=...
```

Las exportaciones `Util / Alert / Button / Carousel / Collapse` y el mensaje de Popper.js son la firma de **Bootstrap 4.3.x**. No hay ningún marcador de Bootstrap 5 (`data-bs-` aparece **0** veces).

### 3.3 Qué sirve el sitio realmente

Del HTML en producción:

```html
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
...
<script src="http://vholar.co/assets/global/js/vendor.js?id=bb6910..."></script>   <!-- jQuery 3.6.0 -->
<script src="http://vholar.co/assets/frontend/js/vendor.js?id=a3e452..."></script> <!-- Bootstrap 4.3.1 -->
```

**Dos Bootstrap JS en la misma página, y el de Bootstrap 5 carga primero.**

### 3.4 Por qué nadie lo ha notado

Tres razones que se combinan:

1. **Bootstrap 5 es vanilla** — no toca `$.fn`. No hay colisión de nombres de plugin a nivel de DOM.
2. **Los data-attributes no se pisan.** Bootstrap 4 escucha `[data-toggle="dropdown"]`; el tema usa `data-bs-toggle` (32 apariciones). No compiten por el mismo elemento.
3. **Bootstrap 4 está tapando el problema real** (ver §4).

### 3.5 El riesgo que sí existe

Bootstrap 4 **registra** `$.fn.modal`, `$.fn.dropdown`, `$.fn.tooltip`, `$.fn.collapse`, `$.fn.tab`, `$.fn.carousel`… Su versión sustituye a la de Bootstrap 3 que hubiera, pero **no hay ninguna de Bootstrap 5 en jQuery** (BS5 no usa jQuery).

Es decir: **cualquier llamada legacy `$(...).modal()` ejecuta la implementación de Bootstrap 4** contra markup gestionado por Bootstrap 5. Los síntomas típicos son modales que no cierran, `backdrop` que se queda pegado o `.show` que sobrevive al cierre.

Lo bueno: en el frontend hay **muy pocas** llamadas de ese tipo — una, y está comentada:

```
modules/DisposableBasic/Resources/views/scenery/flights_scripts.blade.php:17:  // $('#bidModal').modal();
```

---

## 4. El hallazgo contraintuitivo: Bootstrap 4 sostiene markup de Bootstrap 3

Hay **8 vistas del frontend** con atributos del estilo Bootstrap 3/4, que **Bootstrap 5 ignora**:

| Vista | Atributos |
|---|---|
| `layouts/vholar/flights/simbrief_briefing.blade.php` | `data-toggle="modal" data-target="#OFP_Edit"` + `data-backdrop="static" data-keyboard="false"` |
| `modules/DisposableBasic/.../widgets/fuel_calculator.blade.php` | `data-toggle` / `data-target` |
| `modules/DisposableBasic/.../widgets/map.blade.php` | `data-toggle` / `data-target` |
| `modules/DisposableBasic/.../ranks/index.blade.php` | `data-toggle` / `data-target` |
| `modules/DisposableSpecial/.../widgets/notams.blade.php` | `data-toggle` / `data-target` |
| `modules/DisposableSpecial/.../tours/index.blade.php` | `data-toggle` / `data-target` |
| `modules/DisposableSpecial/.../tours/show.blade.php` | `data-toggle` / `data-target` |
| `modules/VmsOpenOps/.../frontend/{ferry,jumpseat}/index.blade.php` | `data-toggle` / `data-target` |

En el tema vholar hay **32 `data-bs-toggle`** (Bootstrap 5, correcto) y **1 `data-toggle`** — el "Edit OFP" del briefing de SimBrief. Ese enlace y su modal **funcionan hoy únicamente porque Bootstrap 4 está cargado**.

> **Esto es lo importante para planificar:** no se puede simplemente borrar el bundle de Bootstrap 4. Primero hay que migrar esos 8 sitios a `data-bs-*`, o el modal "Edit OFP" y esos widgets dejan de abrirse. La mezcla actual es un andamio, no un residuo.

---

## 5. Otros hallazgos

### 5.1 `admin.min.css` es huérfano

`public/assets/admin/css/admin.min.css` pesa **324 KB** y contiene **Bootstrap 3.3.7**. Ninguna vista, hoja ni `webpack.mix.js` lo referencia. Es una versión de Bootstrap **completa y sin usar**, que además es distinta de la que sí usa el admin (3.4.1). Conviene borrarlo: además de peso muerto en el repositorio, invita a confundir qué versión se está aplicando.

### 5.2 El admin duplica dos plugins dentro de su propio bundle

`buildAdminAssets()` concatena:

```js
'node_modules/bootstrap3/dist/js/bootstrap.js',   // el bundle COMPLETO
'node_modules/bootstrap3/js/collapse.js',         // ya está dentro del completo
'node_modules/bootstrap3/js/transition.js',       // ya está dentro del completo
```

`mix.scripts()` solo concatena, así que `Collapse` y `Transition` quedan registrados dos veces. No rompe nada de forma visible (la segunda definición gana y es idéntica), pero son ~10 KB y una trampa para quien lo lea.

### 5.3 El instalador carga Bootstrap 4 dos veces

`resources/views/system/installer/app.blade.php`:

```html
<link href="{{ public_asset('/assets/frontend/css/bootstrap.min.css') }}" rel="stylesheet"/>  <!-- BS 4.3.1 -->
<link href="{{ public_asset('/assets/frontend/css/now-ui-kit.css') }}" rel="stylesheet"/>
<link href="{{ public_asset('/assets/installer/css/vendor.css') }}" rel="stylesheet"/>       <!-- BS 4.3.1 + now-ui-kit otra vez -->
```

Y `buildInstallerAssets()` incluye explícitamente `bootstrap.css` **y** `now-ui-kit.css` en `installer/css/vendor.css`. Los dos primeros `<link>` son redundantes.

### 5.4 Las vistas de paginación del tema no están cableadas

`resources/views/layouts/vholar/pagination/` tiene **7** archivos (`bootstrap-4`, `bootstrap-5`, `simple-bootstrap-4`, `simple-bootstrap-5`, `default`, `simple-default`, `semantic-ui`). Ninguno se usa:

- `AppServiceProvider` llama a `Paginator::useBootstrap()`, que en Laravel 10 delega en **`useBootstrapFour()`** → vistas `pagination::bootstrap-4` del framework.
- El `ThemeViewFinder` no remapea el namespace `pagination::`, y no hay ningún `Paginator::defaultView(...)` que apunte a las del tema.

En la práctica **no se rompe**: el markup de paginación de Bootstrap 4 y 5 es idéntico (`ul.pagination > li.page-item > a.page-link`) y el CSS de BS5 lo estiliza igual. Pero son 7 archivos que sugieren una configuración que no existe, y el `bootstrap-5.blade.php` invita a creer que la paginación ya está en BS5 cuando no lo está.

### 5.5 `admin/css/admin.css`: un color fuera de paleta

Un archivo de 1,6 KB que **no** pasó por la auditoría de la paleta (no es `vholar-admin.css`):

```css
.border-blue-bottom { border-bottom: 3px solid #067ec1; }   /* ← azul de Bootstrap */
```

Es el azul `--bs-primary` original del tema Disposable, el mismo que se eliminó del frontend en la fase 1.

---

## 6. Recomendaciones, por orden de valor

### Prioridad 1 · Terminar la migración a Bootstrap 5 en el frontend

El objetivo es que el frontend tenga **un solo** Bootstrap. El orden importa:

1. Migrar los **8 sitios** de `data-toggle` / `data-target` / `data-backdrop` a `data-bs-toggle` / `data-bs-target` / `data-bs-backdrop`.
2. Comprobar que cada modal sigue abriendo y cerrando.
3. **Solo entonces**, quitar de `buildFrontendAssets()`:
   - `node_modules/bootstrap/dist/js/bootstrap.js` (Bootstrap 4)
   - `node_modules/popper.js/...` si nada más lo usa (Bootstrap 5 ya trae Popper 2 dentro del bundle)
   - `node_modules/moment/moment.js` si no lo consume código propio
4. Recompilar con `npm run production`.

**Efecto:** se elimina la clase de bug "funciona por accidente" y una fuente de verdad duplicada. El bundle completo (`frontend/js/vendor.js`) son 148 KB e incluye también `moment` y Popper 1.x, así que el ahorro real depende de cuánto de eso siga usándose.

> Hacerlo al revés —quitar primero el bundle— deja el modal "Edit OFP" y varios widgets de módulos sin funcionar, y el síntoma aparecería en módulos que no se tocan a menudo.

### Prioridad 2 · Decidir qué pasa con `admin.min.css`

Borrar los 324 KB de Bootstrap 3.3.7 huérfano. Si en algún momento hizo falta para algo, hay que referenciarlo explícitamente y documentar por qué.

### Prioridad 3 · Limpiar las duplicaciones del build

- Quitar `collapse.js` y `transition.js` del bundle del admin (ya van dentro de `bootstrap.js`).
- Quitar los dos `<link>` redundantes del instalador (`bootstrap.min.css` y `now-ui-kit.css` sueltos, porque ya van dentro de `installer/vendor.css`).

### Prioridad 4 · Alinear la paginación

Elegir una:
- **Cambiar a `Paginator::useBootstrapFive()`** y borrar las vistas de paginación del tema, o
- cablear las del tema con `Paginator::defaultView()` y quedarse solo las que se usen.

### Prioridad 5 · Casos menores

- `vendor/datatables/print.blade.php`: cargar Bootstrap 3.3.5 desde un CDN externo es una dependencia de red sin control de versión. Mejor servirlo local, o migrar esa vista de impresión.
- `admin/css/admin.css`: `#067ec1` → `var(--vh-primary)`.
- Temas inactivos (`Disposable_v3` con 5.1.3, `beta` con BS4): si no se van a usar, borrarlos; si sí, alinearlos. Ahora mismo son versiones que nadie prueba.

---

## 7. Lo que **no** es un problema

Conviene decirlo para no arreglar lo que funciona:

- **El login** carga un único Bootstrap (5.3.3) y jQuery. Limpio.
- **El admin** carga un único Bootstrap (3.4.1). Es correcto que un panel Bootstrap 3 use Bootstrap 3; la migración del admin a BS5 es un proyecto aparte, no un bug.
- **La paginación** se ve bien aunque use las vistas de Bootstrap 4, porque el markup es idéntico al de 5.
- **`now-ui-kit.css`** contiene la cadena `Datepicker for Bootstrap v1.7.0-dev`: es el banner de **bootstrap-datepicker**, no una versión de Bootstrap. Falso positivo descartado.

---

## 8. Cómo reproducir esta auditoría

```bash
# 1. Versiones declaradas
grep -E 'bootstrap' package.json

# 2. Versión de Bootstrap dentro de cada asset compilado
for f in $(find public/assets -name '*.css' -o -name '*.js'); do
  v=$(grep -oiE 'Bootstrap v[0-9]+\.[0-9]+\.[0-9]+' "$f" | head -1)
  [ -n "$v" ] && echo "$v  ${f#public/}"
done

# 3. Firma de Bootstrap 4 en un bundle (exportaciones + aviso de Popper)
grep -o "require Popper.js" public/assets/frontend/js/vendor.js
grep -o "e\.Util=u" public/assets/frontend/js/vendor.js

# 4. Qué sirve el sitio de verdad (nginx responde a vholar.co en localhost)
curl -s -H "Host: vholar.co" http://127.0.0.1/ | grep -oE 'src="[^"]*bootstrap[^"]*"'

# 5. Markup de Bootstrap 3/4 en el frontend (BS5 lo ignora)
grep -rn 'data-toggle=\|data-target=\|data-backdrop=' resources/views/layouts/vholar/ modules/*/Resources/views/
```

---

# 9. Qué se hizo

Aplicado el 2 de octubre de 2026. **El frontend vholar ya carga un solo Bootstrap.**

## 9.1 Migración del markup a Bootstrap 5 (27 atributos, 9 ficheros)

| Fichero | Atributos migrados |
|---|---|
| `layouts/vholar/flights/simbrief_briefing.blade.php` | 5 (`toggle`, `target`, `dismiss`, `backdrop`, `keyboard`) |
| `DisposableBasic/.../widgets/fuel_calculator.blade.php` | 5 |
| `DisposableBasic/.../widgets/map.blade.php` | 5 |
| `DisposableBasic/.../ranks/index.blade.php` | 2 |
| `DisposableSpecial/.../widgets/notams.blade.php` | 2 |
| `DisposableSpecial/.../tours/index.blade.php` | 4 (`data-toggle="pill"`) |
| `DisposableSpecial/.../tours/show.blade.php` | 4 |
| `VmsOpenOps/.../frontend/ferry/index.blade.php` | 3 |
| `VmsOpenOps/.../frontend/jumpseat/index.blade.php` | 3 |

La sustitución es segura aunque el fichero ya tuviera atributos de Bootstrap 5, porque
`data-toggle=` **no** es subcadena de `data-bs-toggle=`.

> **Trampa documentada en el propio código.** `home.blade.php` tiene `data-target` en los
> `<span class="counter">`, y **no es un atributo de Bootstrap**: lo lee el
> `IntersectionObserver` de la misma vista (`getAttribute('data-target')`) para animar el
> contador. Un reemplazo ciego `data-target → data-bs-target` habría roto los contadores de
> la portada. Se dejó intacto y con un aviso en el fichero.

## 9.2 El tema vholar deja de cargar el bundle con Bootstrap 4

En `layouts/vholar/app.blade.php` se eliminó:

```html
<script src="{{ public_mix('/assets/frontend/js/vendor.js') }}"></script>
```

Verificado antes de quitarlo:

- El tema usa la **API vanilla de Bootstrap 5** — `bootstrap.Modal` (7), `Collapse`, `Dropdown`, `Popover`. Cero llamadas jQuery de Bootstrap.
- `resources/js/frontend/app.js` (582 B) no referencia `bootstrap`, plugins jQuery, `Popper` ni `moment`.
- Ya no queda ni un atributo BS3/4 en las vistas del frontend.

**Resultado**, del HTML que sirve el sitio:

```html
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="http://vholar.co/assets/global/js/vendor.js?id=..."></script>   <!-- jQuery, lodash, select2, cookieconsent -->
```

Un solo Bootstrap.

## 9.3 Por qué NO se tocó `buildFrontendAssets()` en `webpack.mix.js`

**Este es el hallazgo que cambió el plan.** `frontend/js/vendor.js` lo cargan **cuatro** layouts:

| Layout | Bootstrap | ¿Necesita el bundle? |
|---|---|---|
| `vholar` (activo) | 5.3.3 | **No** — se le quitó |
| `seven` | 5.3.3 | **No** — se le quitó después (ver §10) |
| `Disposable_v3` | 5.1.3 | No |
| **`beta`** | **4.3.1** | **SÍ** |

`beta` usa atributos de Bootstrap 3/4 en **16 sitios** (`data-dismiss="modal"` ×9, `data-toggle="dropdown"` ×3, `collapse`, `modal`, `data-target`). Sin el bundle, sus menús y modales dejan de funcionar.

Y `beta` y `seven` **no son código del proyecto**: son los **temas del núcleo de phpVMS** (fechas de la instalación base), y además `seven` es el tema de respaldo — `SetActiveTheme.php` hace `setting('general.theme', 'seven')`.

**Decisión:** quitar Bootstrap 4 del build habría roto silenciosamente un tema del núcleo, y cualquier actualización de phpVMS lo restauraría. Se optó por lo quirúrgico — el tema activo deja de cargarlo — y se dejó el motivo escrito en `webpack.mix.js`:

```js
// OJO: este bundle lleva Bootstrap 4.3.1 + moment + Popper 1.x, y lo unico que
// lo justifica es el tema del nucleo `beta` (Bootstrap 4). El tema activo
// (vholar) y `seven`/`Disposable_v3` son Bootstrap 5 y YA NO lo cargan.
// Cuando `beta` se migre o se descarte, borrar este mix.scripts() y el fichero.
```

**Para terminar la limpieza** hace falta una decisión previa: migrar `beta` a Bootstrap 5, o descartarlo. Entonces sí se puede borrar el `mix.scripts()` y el fichero.

## 9.4 Otras limpiezas aplicadas

| Qué | Dónde | Efecto |
|---|---|---|
| Plugins duplicados en el bundle del admin | `webpack.mix.js` | Se quitaron `bootstrap3/js/collapse.js` y `js/transition.js`: ya van dentro de `bootstrap3/dist/js/bootstrap.js`. **Recompilado.** |
| Bootstrap 4.3.1 cargado dos veces | `system/installer/app.blade.php` | Se quitaron los dos `<link>` sueltos (`bootstrap.min.css` y `now-ui-kit.css`): ya van dentro de `installer/vendor.css`. |
| 324 KB de Bootstrap 3.3.7 huérfano | `public/assets/admin/css/admin.min.css` | Movido a respaldo. Ninguna referencia en código, vistas, mix, manifest ni mapas. |
| Azul de Bootstrap en el admin | `public/assets/admin/css/admin.css` | `#067ec1` → `var(--vh-primary)`. Se le añadió cache-bust. |

> La regla `border-blue-bottom` que contenía ese azul se usa solo en el tema `beta`, y `admin.css` se carga solo en el panel de administración, así que el cambio es **higiene, no una corrección visible**.

## 9.5 La recompilación

Se recompiló con `npm ci` + `npm run production`.

**`npm ci`, no `npm install`.** El proyecto tiene `package-lock.json` (del mismo 26 de febrero que los bundles), así que se instalaron las **versiones exactas** del build original en vez de resolver los rangos semver. Verificado: `bootstrap 4.3.1` y `bootstrap3 3.4.1`, las mismas que el lockfile. Eso elimina el riesgo de que entraran parches nuevos.

> **La caché de npm hay que redirigirla.** El primer intento falló con `EACCES` y un mensaje engañoso sobre "root-owned files" en `~/.npm`. La causa real es que `~/.npm` está fuera del espacio de trabajo permitido, así que el proceso no puede escribir ahí. Se resuelve con `npm ci --cache /tmp/npm-cache`.

**Resultado: 1106 paquetes, 0 errores, compilado en 31 s** (Node 22.23.3, Laravel Mix 6.0.49).

### Qué cambió exactamente

De los 2444 ficheros CSS/JS, **cambiaron 4**:

| Fichero | Antes | Después | Δ |
|---|---|---|---|
| `admin/js/vendor.js` | 510 955 B | **506 821 B** | **−4 134** ✅ la deduplicación |
| `admin/js/app.js` | 419 195 B | 426 065 B | +6 870 |
| `frontend/js/app.js` | 414 130 B | 421 000 B | +6 870 |
| `installer/js/app.js` | 86 547 B | 93 250 B | +6 703 |

**El CSS no cambió ni un byte.** `admin/css/vendor.css`, `vendor.min.css`, `frontend/css/now-ui-kit.css` e `installer/css/vendor.css` quedaron byte a byte idénticos → **el aspecto del admin y del instalador es exactamente el mismo**. Ese era el riesgo principal de recompilar, y no se materializó.

La deduplicación del admin se confirma por dentro: `emulateTransitionEnd` 15 → 12 y `Collapse` 6 → 3, exactamente la mitad — el registro duplicado desapareció.

`frontend/js/vendor.js` quedó **idéntico** (`cmp -s`), así que sigue conteniendo Bootstrap 4 para el tema `beta`, como estaba previsto.

### El crecimiento de los `app.js`: artefacto del toolchain

Los tres `app.js` crecieron ~2 % (gzip: 122 731 → 125 267 B en el del frontend). Comprobado que **no es un problema**:

- Misma minificación (2 líneas antes y después).
- `app.js.LICENSE.txt` **idéntico** → exactamente las mismas dependencias empaquetadas.
- Sin duplicación: `webpackChunk` 2→2, `rivets` 3→3, `sourceMappingURL` 1→1.
- El fuente (`resources/js/frontend/app.js`, 582 B) no se tocó.
- Los cuatro JS pasan `node --check`.

Es cómo webpack/terser emiten ciertas construcciones bajo una versión de Node distinta de la que compiló en febrero (Node 22.23.3 ahora). Sin impacto funcional.

## 9.6 Lo que **no** se hizo, y por qué

**No se quitó Bootstrap 4 de `buildFrontendAssets()`**, aunque ahora sería posible recompilar. El motivo está en §9.3: el tema del núcleo `beta` (Bootstrap 4) **sí necesita** ese bundle. Quitarlo rompería silenciosamente un tema del núcleo, y una actualización de phpVMS lo restauraría.

Queda documentado en `webpack.mix.js` con la condición exacta para terminarlo: *cuando `beta` se migre o se descarte, borrar ese `mix.scripts()` y el fichero*.

## 9.7 Reversión

```
/tmp/coloranalysis/backup-bs/    vholar/app.blade.php, webpack.mix.js, admin.css,
                                 installer/app.blade.php y las 9 vistas migradas
/tmp/coloranalysis/backup-bs/orphan/admin.min.css
/tmp/assets-backup/assets-antes/ los 38 MB de assets compilados ANTES de recompilar
```

Para revertir la recompilación: `cp -a /tmp/assets-backup/assets-antes/. public/assets/`.

## 9.8 Verificación

| Comprobación | Resultado |
|---|---|
| Atributos BS3/4 en el frontend | **0** |
| `webpack.mix.js` como JS válido | **OK** |
| Vistas modificadas que compilan | **13 / 13** |
| Bootstrap cargado por el frontend vholar | **1** (5.3.3) |
| Bootstrap cargado por el login | **1** (5.3.3) |
| Versiones instaladas vs `package-lock.json` | **idénticas** |
| CSS cambiado por la recompilación | **ninguno** |
| Entradas rotas en `mix-manifest.json` | **0** de 3288 |
| JS que pasa `node --check` | **4 / 4** |
| `/`, `/login`, `/fleetgrid`, `/livemap` | **200** |
| Admin, instalador y frontend: todos los assets | **200** |
| Ficheros no legibles por `www-data` | **0** |
| Ficheros no escribibles en `storage/` | **0** |
| Errores nuevos de Laravel | **0** |
| `admin.min.css` tras moverlo | 404 (nadie lo pedía) |

---

# 10. Migración del tema `seven`

Aplicada después, el mismo 2 de octubre. `seven` es el **tema de respaldo** y era el último tema Bootstrap 5 que seguía arrastrando el bundle de Bootstrap 4.

## 10.1 Qué se encontró

El tema tenía **solo 3 atributos** del estilo BS3/4, los tres en `flights/simbrief_briefing.blade.php` — el mismo patrón exacto que tenía vholar (los dos temas comparten ancestro). En cambio sí cargaba:

- **Bootstrap 4.3.1** dentro de `frontend/js/vendor.js` (innecesario).
- **Popper 2.11.8 suelto desde CDN**, justo antes de `bootstrap.bundle.min.js`… que **ya incluye Popper**. Cargado dos veces.

Verificado que ninguno hacía falta: `seven` usa la API vanilla de Bootstrap 5 (`bootstrap.Modal`, `bootstrap.Popover`), tiene 11 `data-bs-toggle` y 10 `data-bs-dismiss`, **cero llamadas jQuery de Bootstrap**, y nadie usa el global `Popper` ni `moment`.

## 10.2 Qué se hizo

| Cambio | Detalle |
|---|---|
| 5 atributos → BS5 | `data-toggle`, `data-target`, `data-dismiss`, `data-backdrop`, `data-keyboard` en `flights/simbrief_briefing.blade.php` |
| Fuera el bundle de BS4 | `frontend/js/vendor.js` ya no se carga |
| Fuera el Popper duplicado | 3 líneas; el `.bundle` de Bootstrap ya lo trae |

Los scripts de `seven/app.blade.php` quedan: Bootstrap 5.3.3 (bundle, con Popper), tom-select, el bundle global (jQuery, lodash, select2, cookieconsent), `app.js` y el `gtag` de Google. **Un solo Bootstrap.**

## 10.3 Verificación

| Comprobación | Resultado |
|---|---|
| Atributos BS3/4 en `seven` | **0** |
| Vistas de `seven` que compilan | **105 / 105** |
| `data-target` sin `data-bs` | **0** |
| Bootstrap cargado | **1** (5.3.3) |
| Bundle de BS4 / Popper suelto | **0 / 0** |

No se pudo comprobar **renderizando**: `seven` no es el tema activo y usa el paquete `Igaster\LaravelTheme`, que necesita el ciclo de petición. Se verificó de forma estática (compilación de las 105 vistas + comprobación de que cada `data-bs-target` tiene su destino). Para verlo en vivo habría que activarlo un momento desde el admin.

## 10.4 Hallazgo aparte: `seven` no tiene menú de navegación

La comprobación de `data-bs-target` destapó algo que **no es de Bootstrap**:

`seven/nav.blade.php` son **11 líneas**: el logo y un botón hamburguesa que apunta a `#navbarSupportedContent`… **un id que no existe en ninguna vista del tema**. No hay menú.

No es un accidente: lo vació el commit `4b091e7a` — *"feat: personalizaciones del core para Vholar"*, por fhprietor, el 30/09/2026. Para comparar, `vholar/nav.blade.php` tiene 156 líneas y `beta/nav.blade.php` 149.

**La consecuencia a tener en cuenta:** `seven` es el tema de respaldo — `SetActiveTheme.php` hace `setting('general.theme', 'seven')`. Si algún día se pierde el ajuste del tema, el sitio caería en un tema **sin navegación**. No se ha tocado porque es una decisión de diseño ya tomada, pero conviene saberlo.



