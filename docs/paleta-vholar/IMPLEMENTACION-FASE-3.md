# Implementación · Fase 3 · Migración de superficies y neutros

**Fecha:** 2 de octubre de 2026
**Alcance:** migración del tinte neutro (azul → púrpura), de las 23 superficies oscuras a 3 niveles y de la familia celeste al neutro cálido
**Archivos tocados:** 27
**Líneas modificadas:** 212 + arreglos manuales

---

## 1. Qué se hizo

### 1.1 El tinte: de azul a púrpura

El hallazgo central del análisis era que el púrpura de marca (`#412C4D`, **H 278**) convivía con superficies de tono **azulado (H 256–260)**, una discordancia de ~20° que hacía que los paneles "no se sintieran Vholar" aunque el color primario fuera correcto.

`theme.css:76-78` — cuatro valores en el `:root`:

| Token | Antes | Ahora | Tinte |
|---|---|---|---|
| `--vh-bg` | `#1e1b24` | `#16121B` | H 260 → **H 267** |
| `--vh-surface` | `#1f1c27` | `#1E1925` | H 256 → **H 265** |
| `--vh-surface-2` | `#2a2633` | `#28212F` | H 258 → **H 270** |
| `--vh-primary` | `#412c4d` | `#412C4D` | H 278 (sin cambio: ya era exacto) |

La brecha con la marca baja de ~20° a ~8-13°. No se iguala a propósito: una superficie saturada al nivel del púrpura de marca competiría con los botones y los acentos.

Como `body`, `.card`, `.form-control`, `.dropdown-menu` y `.navbar` ya apuntaban a estos tokens desde la fase 1, **estos cuatro valores cambiaron el fondo y las tarjetas de todo el sitio sin tocar ninguna vista**.

### 1.2 Las 23 superficies hardcodeadas → 3 niveles

212 reemplazos en 26 vistas. Mapeo aplicado:

| Hex obsoleto | Usos | → Token |
|---|---|---|
| `#2a2633` | 18 | `var(--vh-surface-2)` |
| `#1f1c27` | 21 | `var(--vh-surface)` |
| `#1a1828` | 18 | `var(--vh-surface)` |
| `#1e1b24` / `#16131c` / `#141118` / `#12101e` / `#0f0f1a` / `#0f1a24` | 10 | `var(--vh-bg)` |
| `#1a1a2e` / `#1e2a3a` | 4 | `var(--vh-surface)` |
| `#2a1f33` | 4 | `var(--vh-primary-active)` |
| `#2a263380` (alfa) | 1 | `#28212F80` |
| `#412c4d` (fondos y bordes) | 39 | `var(--vh-primary)` |

### 1.3 La familia celeste → neutro cálido de marca

| Hex obsoleto | Usos | → Token |
|---|---|---|
| `#9090a8`, `#9898b0`, `#9898b2`, `#a0a8c0`, `#a8a8c0`, `#8898cc`, `#8898c8`, `#7080b8`, `#7a8aaa`, `#b0a0cc`, `#7a90cc`, `#9080b0` | 82 | `var(--vh-text-muted)` |
| `#c0c8e8`, `#c8d8ff`, `#dce4ff`, `#e0e0f0`, `#e0d6f0` | 34 | `var(--vh-text)` |
| `#90aaff` (hover de enlaces) | 9 | `var(--vh-silver)` |
| `#7a6aaa` | 8 | `var(--vh-silver-dim)` · en bordes → `var(--vh-border)` |

Las etiquetas de mapa (Leaflet `divIcon`) pasan de celeste `#c8d8ff` a **plata institucional `#BDBFC1`**, con el mismo texto oscuro. Ahí el valor va como **literal**, no como `var()`: se construyen dentro de cadenas JS y aunque terminarían en estilos inline del DOM, el literal es más seguro y no depende del contexto de herencia.

### 1.4 Tres arreglos manuales

Los tres aparecieron al revisar el dry-run del barrido, antes de aplicarlo:

1. **`app.blade.php:415-423`** — el modal de confirmación global declaraba `var(--vh-surface2, #2a2633)`. **`--vh-surface2` es el nombre que usa el admin, no el frontend** (donde se llama `--vh-surface-2`), así que siempre caía al fallback. Además, tras la fase 1 las tres variables ya están definidas y los fallbacks eran código muerto. Se limpian y se corrige el nombre.
2. **`briefing.blade.php:9`** — el banner del briefing usaba un gradiente índigo `#1a1035 → #2d1b69`, fuera de la paleta. Pasa a `var(--vh-primary-active) → var(--vh-primary)`. Se eligió ese rango y no `→ --vh-primary-hover` porque el subtítulo es `text-white-50` (blanco al 50 %): sobre `#563A63` daría 3.73:1 y no pasaría AA; sobre `#412C4D` da 4.92:1.
3. **`simbrief-dispatch-modal.blade.php`** — el acento violeta `#8b5cf6` (violeta de Tailwind) de la flecha y del foco del campo editable pasa a la plata de marca, para que coincida con el anillo de foco global que se unificó en la fase 2.

### 1.5 Tokens: 25 → 21

- **Consumido**: `--vh-primary-soft` ← 9 apariciones de `rgba(65,44,77,0.3)` (en 4 grafías distintas: `0.3`, `0.30`, `.3`, con y sin espacios).
- **Eliminados por no tener consumidor**: `--vh-white`, `--vh-text-subtle`, `--vh-border-strong`, `--vh-surface-3`. Se declararon en la fase 1 anticipando esta fase, y al llegar aquí resultó que el tema solo necesita **3** niveles de superficie, no 4; y que `--vh-text-subtle` pierde siempre frente a `--vh-text-muted`, que da más contraste sin perder jerarquía. Se quitan en lugar de dejarlos sin usar.

**Resultado: 14 de 21 tokens en uso.** Los 7 restantes son exactamente la familia semántica que consume la fase 4: `--vh-success`, `--vh-warning`, `--vh-danger` y sus cuatro variantes `-soft`.

---

## 2. Verificación

| Métrica | Antes (fase 2) | Ahora | Δ |
|---|---|---|---|
| Colores hex únicos | 112 | **76** | −36 |
| Declaraciones de color | 491 | **235** | −256 |
| Tokens de color declarados | 25 | **21** | −4 |
| Tokens con consumidor real | 12 | **14** | +2 |
| Texto bajo 4.5:1 | 24 | **17** | −7 |

**14 de 21 tokens tienen consumidor.** Los 7 restantes son exactamente la familia semántica que consume la fase 4: `--vh-success`, `--vh-warning`, `--vh-danger` y sus cuatro variantes `-soft`. Ninguno queda declarado "por si acaso".

Los 17 casos restantes, ya identificados y fuera del alcance de esta fase:

| Caso | Ratio | Fase |
|---|---|---|
| `#dc3545` en `.bid-remove`, toast y modal (`theme.css:638`, `live_map:289`) | 4.08 | 4 |
| `#2c7be5` en `.fr24-route-arrow`, banner FR24 y live map (`theme.css:1363`, `app:388`, `live_map:402`) | 4.46 | 4 |
| `#7b6a87` en `home.blade.php:67` sobre tarjeta clara `#F3EFF5` | 4.19 | 7 |
| `#5a4a66` en `home.blade.php:63` sobre la misma tarjeta | 4.37 | 7 |
| `#6c757d` (`.text-muted`) en `theme.css:350`, isla clara | 3.94 | 7 |

### Comprobaciones automáticas

- **Llaves CSS**: 214 / 214, balanceadas.
- **Tokens referenciados sin definir**: 0. (Se verificó cada `var(--vh-…)` contra las definiciones de `theme.css` y `login_layout.blade.php`.)
- **Referencias circulares** (`--x: var(--x)`): 0.
- **`var()` en contextos donde no es válido**: 0. Se protegieron explícitamente las definiciones de token, los fallbacks `var(--x, #hex)` y el `<meta name="theme-color" content="#412c4d">` (donde `var()` no es válido y el navegador lo ignoraría).
- **Colores en canvas de Chart.js**: ninguno de los colores migrados se usa en opciones de Chart.js/Leaflet canvas. Esos contextos reciben `ctx.fillStyle = '...'` y un `var()` ahí **falla silenciosamente**; se comprobó que solo contienen `#aaa`, `#fff`, `#056093`, `#8B008B`, `#067ec1` y `#2c7be5`, fuera del mapa de reemplazo.
- **Compilación Blade**: las 27 vistas modificadas compilan sin errores de sintaxis.
- **Sincronización del CSS**: `resources/views/layouts/vholar/css/theme.css` y `public/assets/themes/vholar/css/theme.css` siguen siendo el mismo inodo.

### Un bug encontrado y corregido durante la verificación

El script que consumía `--vh-primary-soft` reemplazó también **su propia definición**, dejando `--vh-primary-soft: var(--vh-primary-soft)` — una referencia circular que habría invalidado el token y roto los 9 sitios que lo usan. Se detectó al inspeccionar el `:root` resultante y se restauró el valor. Se añadió una comprobación de referencias circulares al conjunto de verificaciones.

---

## 3. Qué NO se tocó (y por qué)

| Pendiente | Motivo |
|---|---|
| Los 4 `#dc3545` y los 3 `#2c7be5` | Fase 4 (semánticos). Cambiarlos ahora dejaría el rojo viejo y el nuevo en el mismo componente. |
| Islas claras de `home.blade.php` (`#F8F5FA`, `#F3EFF5`, `#DDD3E4`) y los selectores `[style*=…]` | Fase 7: es una decisión de diseño tuya (mantener el contraste deliberado u oscurecer). |
| Botones de red externa (IVAO `#0D2C99`, VATSIM `#29B473`, POSCON `#403a60`) | Son colores de marca de terceros. Repintarlos de Vholar sería incorrecto. |
| `vholar-admin.css` | Fase 6. El admin es Bootstrap 3 y no carga `theme.css`: tiene su propio `:root`, con los nombres `--vh-surface2` y `--vh-border` que ahora **solo** existen ahí. |
| Los grises neutros `#aaa`, `#888`, `#bbb` en las vistas | Fase 5, y requiere cuidado: algunos están en opciones de Chart.js donde `var()` no funciona. |
| `rgba(255,255,255,0.05)` como borde (≈60 usos) | Fase 5. Actualmente funciona como borde translúcido adaptativo; sustituirlo por un valor fijo cambia matices según la superficie. |

---

## 4. Reversión

Respaldo completo del tema **antes** de la fase 3 (91 archivos) en:

```
/tmp/coloranalysis/backup3/resources/views/layouts/vholar/
```

Para revertir un archivo concreto:

```bash
cp /tmp/coloranalysis/backup3/resources/views/layouts/vholar/<ruta> \
   resources/views/layouts/vholar/<ruta>
```

Ojo: `theme.css` es un hardlink, así que copiar sobre cualquiera de las dos rutas actualiza las dos. Los respaldos de la fase 1-2 siguen en `/tmp/coloranalysis/*.bak`.

---

## 5. Cómo comprobarlo

El cambio es de tinte, no de estructura: nada se mueve de sitio ni cambia de tamaño. Lo que debe notarse es que **todo el tema se ve más cálido y cohesionado**, y que los paneles ya no tienen ese matiz azulado.

1. **Dashboard** — el fondo y las tarjetas deben verse del mismo violeta apagado que el navbar, no gris-azulados.
2. **`/dpireps`** (logbook) — las cabeceras, los códigos ICAO y las horas pasan del celeste al neutro cálido.
3. **`/fleetmap`** y **`/dassignments`** — las etiquetas de aeropuerto sobre el mapa ahora son plateadas, no celestes.
4. **`/briefing`** — el banner superior ya no es índigo.
5. **Login** (`/login`) — el fondo y la tarjeta siguen el mismo tinte (la página tiene su propio `:root`, actualizado a mano).
6. **`/vmsopenops/jumpseat` y `/ferry`** — son los archivos con más cambios (34 y 28 líneas).
