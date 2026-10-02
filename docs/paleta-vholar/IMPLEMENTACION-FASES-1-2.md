# Implementación · Fases 1 y 2

**Fecha:** 2 de octubre de 2026
**Alcance:** capa de tokens + corrección de la fuga de azul de Bootstrap + 18 reglas de contraste
**Archivos tocados:** 6

---

## 1. Qué se hizo

### Fase 1 · Capa de tokens y fuga de azul de Bootstrap

`resources/views/layouts/vholar/css/theme.css:5-84` — se reescribió el bloque `:root`.

**Antes** (10 variables, **0 consumidas** con `var()`):

```css
:root {
  --bs-primary: #067ec1 !important;
  --bs-secondary-color: #d0c8dc;
  --primary-color: #412c4d;
  --primary-hover: #5e3e73;
  --bg-dark: #1e1623;
  --vh-primary: #412c4d;
  --vh-dark: #1e1b24;
  --vh-light: #f4f2f6;
  --vh-gray: #2a2633;
  --vh-muted: #d0c8dc;
}
```

**Ahora** (25 tokens, 11 en uso hoy, 14 reservados para las fases 3-4), en tres bloques:

1. **Paleta** — marca, texto, semánticos, bordes y foco, todos derivados de los assets institucionales.
2. **Fuga de Bootstrap** — el arreglo de mayor alcance:

```css
--bs-primary: #412C4D;
--bs-primary-rgb: 65, 44, 77;   /* ← lo que faltaba */
--bs-body-bg: var(--vh-surface-2);
--bs-body-color: var(--vh-text);
--bs-secondary-color: var(--vh-text-muted);
--bs-link-color: #BDBFC1;
--bs-link-hover-color: #FFFFFF;
--bs-border-color: #3A3142;
--bs-focus-ring-color: rgba(189, 191, 193, 0.35);
```

3. **Compatibilidad** — alias de los nombres antiguos para no romper nada durante la migración:

```css
--vh-dark: var(--vh-bg);           /* @deprecated */
--vh-gray: var(--vh-surface-2);    /* @deprecated */
--vh-muted: var(--vh-text-muted);  /* @deprecated */
--vh-light: #f4f2f6;               /* @deprecated */
--primary-color: var(--vh-primary);
--primary-hover: var(--vh-primary-hover);
--bg-dark: var(--vh-bg);
```

Esto además resuelve un defecto que el análisis detectó: `--vh-surface`, `--vh-border` y `--vh-text` **estaban referenciadas en `app.blade.php:415-423` sin estar definidas en el frontend** (solo existían en la hoja del admin). Ahora existen, y también los cuatro nombres que se usaban en el admin.

**Se eliminó el `!important`** de `--bs-primary`. Un `!important` en una custom property impide sobrescribirla después, así que bloqueaba cualquier ajuste futuro.

### Fase 2 · Correcciones de contraste

**18 reglas** migradas de hex suelto a token. En `theme.css`:

| Línea | Regla | Antes | Ahora |
|---|---|---|---|
| 520 | `.route-arrow` | `#412c4d` | `var(--vh-silver-dim)` |
| 1054 | `.vholar-modal-header .modal-title i` | `#412c4d` | `var(--vh-silver-dim)` |
| 1375 | `.map-popup-compact .popup-route i` | `#412c4d` | `var(--vh-silver-dim)` |
| 549 | `.detail-label` | `#888` | `var(--vh-text-muted)` |
| 573 | `.status-time` | `#888` | `var(--vh-text-muted)` |
| 982 | `.flight-details-confirm .flight-route` | `#888` | `var(--vh-text-muted)` |
| 1269 | `.fr24-info-label` | `#666` | `var(--vh-text-muted)` |
| 1395 | `.map-popup-compact .popup-details-label` | `#888` | `var(--vh-text-muted)` |
| 1194 | `.fr24-airline` | `#2c7be5` | `var(--vh-text-muted)` |
| 1228 | `.fr24-route-time` | `#2c7be5` | `var(--vh-info)` |
| 1235 | `.fr24-route-arrow` | `#2c7be5` | `var(--vh-silver-dim)` |
| 823 | `.toast-notification.toast-info` (borde) | `#067ec1` | `var(--vh-info)` |
| 827 | `.toast-notification.toast-info .toast-icon` | `#067ec1` | `var(--vh-info)` |

En las vistas:

| Archivo | Regla | Antes | Ahora |
|---|---|---|---|
| `pireps/logbook-styles.blade.php:89` | `.lb-route-arrow` | `#7878a0` | `var(--vh-silver-dim)` |
| `pireps/logbook-styles.blade.php:151` | `.lb-blocktime-icon` | `#7080b8` | `var(--vh-text-muted)` |
| `widgets/latest_pireps.blade.php:55` | `.lb-widget-route-arrow` | `#3a3a52` | `var(--vh-silver-dim)` |
| `modules/DisposableBasic/pireps/table.blade.php:10` | `.lb-toggle-icon` | `#7878a0` | `var(--vh-silver-dim)` |
| `modules/DisposableBasic/pireps/table.blade.php:112` | icono info | `#5a6a9a` | `var(--vh-text-muted)` |
| `modules/DisposableBasic/pireps/table.blade.php:201,212,227` | iconos ✈ ❄ y sufijo `ft/m` | `#7878a0` | `var(--vh-silver-dim)` |
| `users/table.blade.php:8` | `.lb-pilot-ident` | `#7a6a9a` | `var(--vh-silver)` |
| `modules/DisposableSpecial/assignments/index.blade.php:14` | `.lb-order-badge` | `#7a6a9a` | `var(--vh-silver)` |
| `fleet/grid.blade.php:16` | subtítulo | `#7878a0` | `var(--vh-silver-dim)` |
| `fleet/map.blade.php:9` | subtítulo | `#7878a0` | `var(--vh-silver-dim)` |

### Activación de los tokens base (cambio visual cero)

Para que la capa de tokens no naciera muerta —el defecto que el propio análisis señala— se reescribieron las reglas base cuyo valor **coincide exactamente** con el token:

| Línea | Regla | Valor |
|---|---|---|
| 91 | `body` | `var(--vh-bg)` + `var(--vh-text)` |
| 97 | `.navbar` | `var(--vh-primary)` |
| 133 | `.btn-primary` | `var(--vh-primary)` |
| 143 | `.btn-primary:hover` | `var(--vh-primary-hover)` |
| 151 | `.form-control` | `var(--vh-surface)` |
| 184 | `.card` | `var(--vh-surface-2)` |
| 210 | `.dropdown-menu` | `var(--vh-surface-2)` |
| 226 | `.dropdown-item:hover` | `var(--vh-primary)` |

Dos de estas **sí** cambian algo, deliberadamente:

- **`theme.css:159` `.form-control:focus`** — el anillo de foco era `rgba(65,44,77,0.3)`, es decir púrpura translúcido **sobre fondo oscuro**: prácticamente invisible. Ahora es `var(--vh-focus-ring)` (plata al 35 %), que sí se distingue. Es el único cambio de foco del sistema y cubre todos los inputs.
- **`theme.css:168` `label`** — era `#bbb` y `.detail-label` es ahora `#A79FB2`. Se unificaron en `var(--vh-text-muted)` para no dejar dos grises distintos para el mismo rol.

---

## 2. Verificación

### Contraste

Recuento automático de toda declaración `color:` por debajo de 4.5:1 sobre las superficies reales del tema:

| | Declaraciones bajo 4.5:1 |
|---|---|
| Antes | **23** |
| Después | **10** |
| Resueltas | **13** |

De las 10 restantes:

- **6 son falsos positivos**: `#212529` y `#6c757d` en `theme.css:354,358` son texto oscuro **deliberado** sobre las tarjetas claras `#f8f9fa` de `home.blade.php`; `#0d0b18` / `#0d0b14` en `assignments/index.blade.php:193,224,728,765` son etiquetas de mapa con fondo claro `#c8d8ff`. Todos correctos.
- **4 son reales y quedan para la fase 4**: `theme.css:646,815,943,954` usan `#dc3545` (el rojo de Bootstrap) como color de texto, incluyendo `.bid-remove` sobre su propia píldora roja translúcida (~2.6:1). Se resuelven al unificar los semánticos en `--vh-danger`, que es exactamente el objetivo de la fase 4 — cambiarlo ahora dejaría el rojo de Bootstrap conviviendo con el nuevo en el mismo componente.

### Estado de los tokens

| | Cantidad |
|---|---|
| Tokens declarados | 25 |
| En uso hoy | **11** |
| Reservados a las fases 3-4 | 14 |

Reservados: `--vh-primary-active`, `--vh-primary-soft`, `--vh-white`, `--vh-text-subtle`, `--vh-success`, `--vh-warning`, `--vh-danger`, `--vh-success-soft`, `--vh-warning-soft`, `--vh-danger-soft`, `--vh-info-soft`, `--vh-border`, `--vh-border-strong`, `--vh-surface-3`.

Se recortaron 5 tokens que ninguna fase del plan consume (`--vh-text-on-primary`, `--vh-text-disabled`, `--vh-font-mono`, `--vh-radius-sm`, `--vh-radius`) y 4 más de tipografía/radios, para no repetir el patrón de "declarar por si acaso".

### Integridad

- Llaves balanceadas en `theme.css`: 214 / 214.
- Las dos rutas del CSS —`resources/views/layouts/vholar/css/theme.css` y `public/assets/themes/vholar/css/theme.css`— **son el mismo inodo** (hardlink), así que siguen sincronizadas por construcción. Verificado tras cada edición: mismo md5.
- Las vistas Blade se recompilan solas al cambiar su `mtime`; no hace falta limpiar caché de vistas.
- El CSS se carga con `?v={{ time() }}` (`app.blade.php:64`), así que **no hace falta purgar caché de navegador ni de CDN**.

---

## 3. Qué NO se tocó (y por qué)

| Pendiente | Fase | Motivo |
|---|---|---|
| Migrar los 23 fondos oscuros a los 4 niveles de superficie | 3 | Es un cambio de 93 archivos. Hacerlo a medias dejaría el tema **peor**: mitad de las tarjetas con el neutro viejo (azulado) y mitad con el nuevo (púrpura). Por eso `--vh-bg`, `--vh-surface` y `--vh-surface-2` están fijados a su valor actual, y las mismas reglas base ya apuntan a ellos: la fase 3 solo cambia 4 líneas del `:root`. |
| Migrar la familia `lb-*` completa del celeste al neutro | 3 | Solo se corrigieron los 18 casos con fallo de contraste. El resto de la paleta celeste sigue ahí. |
| Unificar semánticos (10 verdes, 12 rojos, 11 ámbar) | 4 | Incluye los 4 `#dc3545` pendientes. |
| Los ~4 azules literales restantes (`#067ec1` en los mapas, cookieconsent y login) | 3-4 | Requieren decisión sobre el color de ruta de los mapas. |
| `--vh-border`, `--vh-border-strong`, `--vh-surface-3` | 3 | Se activan junto con las superficies: los bordes actuales son `rgba(255,255,255,0.05)`, no los grises púrpura propuestos. |
| Islas claras de `home.blade.php` y los selectores `[style*=...]` | 7 | Requiere una decisión de diseño tuya. |
| Unificar `vholar-admin.css` y el `:root` de `login_layout.blade.php` | 6 | El admin usa Bootstrap 3 y su propio juego de variables. |

---

## 4. Reversión

Respaldos de los 5 archivos originales:

```
/tmp/coloranalysis/theme.css.bak
/tmp/coloranalysis/logbook.bak
/tmp/coloranalysis/latest_pireps.bak
/tmp/coloranalysis/dbasic_table.bak
/tmp/coloranalysis/users_table.bak
```

Para revertir `theme.css` basta con copiar el respaldo sobre el archivo (al ser hardlink, las dos rutas se actualizan juntas).

---

## 5. Cómo comprobarlo

1. **`text-primary` / `border-primary`** — cualquier página con `text-primary`: `/briefing` (iconos de la tabla de contenidos), las páginas de error 500, el spinner de `/livemap`. Deben verse **púrpura**, no azul eléctrico.
2. **Foco visible** — hacer clic en cualquier input (login, perfil, búsqueda): el anillo de foco ahora es plateado y se ve.
3. **Flechas de ruta** — `/flights`: la flecha entre los códigos ICAO de salida y llegada ya no es invisible.
4. **Logbook** — `/dpireps`: la flecha entre aeropuertos y el icono de block time.
5. **Widgets del dashboard** — la flecha de ruta de "Recent Reports" y el badge de ident de `/dusers` y `/dassignments`.

Los ratios exactos antes/después de cada elemento están en el tablero `paleta-vholar-antes-despues.png`.
