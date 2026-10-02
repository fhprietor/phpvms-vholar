# Análisis de la paleta de color del tema Vholar

**Fecha:** 2 de octubre de 2026
**Alcance auditado:** `resources/views/layouts/vholar/`, `public/assets/themes/vholar/`, `public/assets/admin/css/vholar-admin.css`
**Base de la marca:** las imágenes institucionales entregadas (`logo.png`, `CARGO.png`, `COLOMBIA.png`, `VHOLAR nombre - small.png`)

> **Estado:** fases **1 a 7 implementadas · trabajo completo** (76 archivos). Ver los informes
> [`IMPLEMENTACION-FASES-1-2.md`](IMPLEMENTACION-FASES-1-2.md),
> [`IMPLEMENTACION-FASE-3.md`](IMPLEMENTACION-FASE-3.md),
> [`IMPLEMENTACION-FASE-4.md`](IMPLEMENTACION-FASE-4.md) y
> [`IMPLEMENTACION-FASE-5.md`](IMPLEMENTACION-FASE-5.md),
> [`IMPLEMENTACION-FASE-6.md`](IMPLEMENTACION-FASE-6.md) y
> [`IMPLEMENTACION-FASE-7.md`](IMPLEMENTACION-FASE-7.md) para el detalle de cambios,
> verificación y reversión. Las líneas citadas en este documento son las del **estado
> auditado (antes)**; las líneas actuales están en los informes de implementación.

## Resultado final

| | Al empezar | Ahora |
|---|---|---|
| Colores hex únicos | **116** | **40** |
| Declaraciones de color | **527** | **121** |
| Fallos de contraste en texto | **23** | **0** |
| Archivos que declaran tokens | 3 | **1** |
| Tokens con consumidor | — | **26 / 26** |
| Reglas que dependen de un estilo inline | 4 | **0** |

De los 40 colores que quedan, **ninguno es deuda**: 20 son las definiciones de la paleta,
7 son referencias en comentarios para poder auditar los cambios, 4 son marcas de terceros,
4 son los extremos oscuros del gradiente de peligro, 3 son literales de JS (donde `var()` no
se resuelve) y 2 son el texto oscuro de las etiquetas de mapa.

---

## 1. Resumen ejecutivo

El tema **no tiene una paleta: tiene 145 decisiones de color acumuladas.**

| Métrica | Valor |
|---|---|
| Archivos con color declarado | 93 |
| Colores hex únicos | **116** |
| Declaraciones hex | 527 |
| Valores `rgba()` no-neutros únicos | 29 |
| **Tokens de color totales en alcance real** | **145** |
| Fondos oscuros distintos | 23 |
| Tokens distintos de "texto atenuado" | 13+ |
| Verdes distintos · rojos distintos · ámbar distintos | 10 · 12 · 11 |
| Variables CSS definidas en el tema | 10 |
| Variables CSS **usadas** con `var()` en el tema | **0** |

La marca define **cuatro** colores. El tema usa 145.

Los tres síntomas que reportas tienen causas concretas y distintas:

1. **"Textos grises oscuros sobre violeta casi oscuro que casi no se ven"** → hay **7 fallos de contraste WCAG por debajo de 4.5:1**, dos de ellos con ratio **1.19:1** (literalmente púrpura `#412c4d` sobre púrpura oscuro), que es texto funcionalmente invisible.
2. **"Muchas variantes"** → 13 tokens distintos de gris/azul-gris para el *mismo* rol semántico ("texto secundario"), y 23 fondos oscuros casi idénticos repartidos entre sí.
3. **"Inconsistencias visuales"** → el tema arrastra **63 colores azul-fríos** (313 usos, el **54 % de todas las declaraciones de color**) que no pertenecen a la marca; un azul de Bootstrap que se filtra en 25 sitios; y bloques de **fondo claro dentro de un tema oscuro**.

**Lo que está bien:** el púrpura institucional real es `#412C4D` y el tema **ya lo usa exactamente** como primario. El problema nunca fue el tono de marca, sino todo lo que se construyó alrededor.

---

## 2. Paleta institucional (extraída de las imágenes)

Extraje los colores por histograma exacto de píxel (ignorando el canal alfa y el blanco de fondo) sobre las cuatro imágenes entregadas.

### 2.1 Resultado del análisis

| Color | Hex | Evidencia |
|---|---|---|
| **Púrpura VHolar** | `#412C4D` | 38,2 % de `logo.png` (modo exacto `#422D4D` + `#412C4C`); 60 % del wordmark; 19,7 % de `vholar_logoweb.png` |
| **Plata VHolar** | `#BDBFC1` | 2,0 % de `logo.png` — anillo exterior; también en `vholar_logoweb.png` |
| **Gris CARGO** | `#B1B0B5` | 100 % del texto de `CARGO.png` (variante gris del wordmark) |
| **Blanco** | `#FFFFFF` / `#FEFEFE` | Ave del logo; 22 % de `logo.png` |
| **Negro** | `#000000` | 100 % del texto de `COLOMBIA.png` |

**Verificación de identidad de marca:**
`public/images/logo.png` (el asset real en disco) → modo exacto `#412C4D` al **38,2 %**.
`public/images/vholar_logoweb.png` (logo del navbar) → `#412C4D`.
El wordmark `VHOLAR` → `#412C4C`.

El valor actual del tema, `#412C4D`, coincide con los tres. **No hay que tocar el primario.**

> **Nota:** el wordmark de marca contiene ya *dos* platas distintas (`#BDBFC1` en el logo, `#B1B0B5` en `CARGO`). Conviene formalizar `#BDBFC1` como plata principal y `#B1B0B5` como su variante atenuada, en lugar de elegir una y descartar la otra.

### 2.2 La fuga: 63 azules que no son de la marca

Clasificando los 116 hex por tono (H), saturación (S) y luminosidad (L):

| Familia | Colores únicos | Usos | % del total |
|---|---|---|---|
| Azul / cian frío (H 170–265) | **63** | **313** | **54 %** |
| Púrpura / violeta (H 265–345) | 20 | 97 | 17 % |
| Semánticos (verde/rojo/ámbar) | 33 | 80 | 14 % |
| Neutros puros | 12 | 39 | 7 % |
| Blancos / grises claros | 4 | 51 | 9 % |

Más de la mitad de todo el color del tema vive en una familia fría (`#9090a8`, `#9898b0`, `#a0a8c0`, `#c0c8e8`, `#c8d8ff`, `#90aaff`, `#2c7be5`, `#067ec1`, `#1a1828`, `#141118`…) que **no existe en ningún asset de la marca**. Es el residuo de la paleta "slate-blue" del tema Disposable, sobre la que se construyó Vholar.

La consecuencia práctica: el púrpura `#412C4D` es un tono **cálido-violeta (H 278)**, mientras las superficies oscuras del tema son **frías-azuladas (H 256–258)**. Esa discordancia de ~20° de tono es la razón por la que los paneles "no se sienten Vholar" aunque el color primario sea correcto.

---

## 3. Estado actual: las seis causas de la inconsistencia

### 3.1 Las 10 variables CSS están muertas

`resources/views/layouts/vholar/css/theme.css:5-16` define una capa de tokens… que nada consume:

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

Comprobación de uso (`grep -o 'var(--<token>'` sobre todo el tema):

| Token | Definido | Usado con `var()` |
|---|---|---|
| `--bs-primary` | sí | 0 (solo lo consume Bootstrap) |
| `--bs-secondary-color` | sí | 0 |
| `--primary-color` | sí | 0 |
| `--primary-hover` | sí | 0 |
| `--bg-dark` | sí | 0 |
| `--vh-primary` | sí | 0 |
| `--vh-dark` | sí | 0 |
| `--vh-light` | sí | 0 |
| `--vh-gray` | sí | 0 |
| `--vh-muted` | sí | 0 |

Hay **un solo** `var()` real en todo el tema (`theme.css:20`, `body { background-color: var(--vh-dark) }`), y `--primary-hover` / `--bg-dark` / `--vh-light` / `--vh-gray` no se usan **ni una vez en ningún formato**.

De los 527 usos de color en alcance, **144** están en `theme.css`, **31** en `vholar-admin.css` y los **352** restantes repartidos en 90 vistas `.blade.php`, todos como literales `#hex` repetidos a mano.

### 3.2 Dos sistemas de variables paralelos, y uno de ellos no existe

- `theme.css` (frontend) define: `--vh-primary`, `--vh-dark`, `--vh-light`, `--vh-gray`, `--vh-muted`
- `public/assets/admin/css/vholar-admin.css:8-17` (admin) define: `--vh-bg`, `--vh-surface`, `--vh-surface2`, `--vh-border`, `--vh-accent`, `--vh-accent-lite`, `--vh-text`, `--vh-muted`
- `app.blade.php:415-423` (frontend) **usa** `--vh-surface`, `--vh-surface2`, `--vh-border`, `--vh-text`

Es decir: el modal de confirmación global del frontend pide cuatro variables que **solo existen en la hoja del admin**, que nunca se carga en el frontend. Siempre caen a sus fallbacks hardcodeados. Y `--vh-muted` existe en los dos sistemas con el mismo valor pero significados distintos.

Encima hay dos nombres distintos para el mismo rol: `--vh-dark` (frontend) y `--vh-bg` (admin).

### 3.3 Fuga del azul de Bootstrap en 25 sitios

`theme.css:6` declara `--bs-primary: #067ec1 !important` (azul). Pero:

- `.bg-primary` **sí** se corrige a púrpura (`theme.css:131`) ✅
- `.text-primary` **no** se corrige → Bootstrap 5.3 lo resuelve con `rgba(var(--bs-primary-rgb))`, y `--bs-primary-rgb` **nunca se redefine** → renderiza el azul por defecto de Bootstrap `#0D6EFD` ❌
- `.border-primary` **no** se corrige → azul ❌

Sitios afectados: **22 usos de `text-primary`** (`briefing.blade.php`, `errors/500.blade.php`, `fleet/grid.blade.php`, `external_redirect_modal.blade.php`, spinners) y **3 de `border-primary`**. También los `form-check-input` (9 usos) y los anillos de foco heredan el azul.

Resultado: hay pantallas donde conviven el púrpura de marca y un azul eléctrico `#0D6EFD` que no está en ninguna imagen institucional.

### 3.4 Islas claras dentro de un tema oscuro

`home.blade.php` inserta tres secciones de fondo claro en medio de la página oscura:

| Línea | Fondo | Texto |
|---|---|---|
| `home.blade.php:56` | `#F8F5FA` | — |
| `home.blade.php:61` | `#F3EFF5` | `#5a4a66` / `#2c1e35` / `#7b6a87` |
| `home.blade.php:288` | `#DDD3E4` | `#412c4d` |

Eso obliga a un tema de color *invertido* a mitad de la página, con su propio juego de grises (`#5a4a66`, `#2c1e35`, `#7b6a87`) que **no se usan en ningún otro lugar del tema**. Además, para sostener este parche, `theme.css` usa selectores por atributo sobre estilos inline:

```css
body.home-page .card[style*="background-color"] { background-color: #DDD3E4 !important; }
body.home-page .card[style*="background-color: #f8f9fa"] { ... }
```

Son overrides que dependen de que el HTML siga teniendo ese estilo inline exacto: cualquier ajuste futuro los rompe en silencio.

### 3.5 Trece (o más) tokens distintos para "texto secundario"

Todos estos colores se usan para el mismo rol semántico — texto de apoyo, etiquetas, metadatos:

| Token | Usos | Dónde |
|---|---|---|
| `#9090a8` | 31 | logbook, widgets, vmsopenops, fleet, users |
| `#9898b0` | 18 | `thead` del logbook, cabeceras vmsopenops |
| `#c0c8e8` | 16 | números de vuelo del logbook |
| `#a0a8c0` | 13 | matrícula y hora del logbook |
| `#aaa` | 12 | stats-badge, toast, modales |
| `#c8d8ff` | 13 | valores del logbook |
| `#888` | 9 | `detail-label`, `status-time`, popups |
| `#90aaff` | 9 | hover de enlaces |
| `#7a6aaa` | 8 | iconos vmsopenops |
| `#7878a0` | 7 | flechas de ruta |
| `#9898b2` | 6 | totales y aerolínea del logbook |
| `#dce4ff` | 6 | códigos ICAO |
| `#a8a8c0`, `#b0adc5`, `#7a8aaa`, `#8898cc`, `#8898c8`, `#7080b8`, `#e0e0f0`, `#6c757d`, `#74787e`, `#838391`, `#d0c8dc` | 24 | resto |

No es solo desorden: rompe la jerarquía. `#c8d8ff` (más claro que `#9090a8`) se usa para etiquetas *y* para valores, `#888` para etiquetas en un sitio y `#9090a8` para las mismas etiquetas en otro. El usuario no puede aprender qué gris significa qué.

### 3.6 Semánticos sin significado: 10 verdes, 12 rojos, 11 ámbar

| Rol | Variantes encontradas |
|---|---|
| Éxito | `#28a745` `#48bb78` `#29b473` `#4caf76` `#2ecc71` `#1abc9c` `#1a7a4a` `#22bc66` `#6bd098` `#0d2e1a` |
| Peligro | `#dc3545` `#e53e3e` `#e05060` `#e08080` `#e74c3c` `#8a1a1a` `#dc4d2f` `#b02a37` `#c82333` `#a71d2a` `#f5856e` `#2e0d0d` |
| Advertencia | `#ffc107` `#e8d080` `#e6a817` `#b8a060` `#f39c12` `#e67e22` `#8a6d00` `#f1c40f` `#ffd966` `#ffd166` `#2e250d` |

Consecuencia concreta: en la tabla de PIREPs (`logbook-styles.blade.php:160-162`) la puntuación usa `#4caf76` / `#e6a817` / `#e05060`, mientras en `theme.css:556-575` los botones de bid usan `#28a745` / `#dc3545` y el sistema de toasts (`theme.css:724-754`) usa `#28a745` / `#dc3545` / `#ffc107` / `#067ec1`. Un verde de éxito en la misma pantalla y otro distinto en la de al lado.

**23 fondos oscuros distintos** (`#1f1c27` ×21, `#2a2633` ×18, `#1a1828` ×18, `#412c4d` ×11, `#2a1f33` ×4, `#16131c`, `#1a1a2e`, `#12101e`, `#141118`, `#3d3547`, `#0f0f1a`, `#1e2a3a`, `#0f1a24`, `#1a1230`, `#1a1035`, `#2d1b69`, `#403a60`, `#0d0b14`…) para lo que deberían ser 4 niveles de superficie.

---

## 4. Fallos de contraste reales

Ratio calculado con la fórmula de luminancia relativa WCAG 2.1. Los fondos son los que realmente están detrás de cada texto.

| # | Texto | Fondo | Ratio | Veredicto | Ubicación |
|---|---|---|---|---|---|
| 1 | `#412c4d` | `#2a2633` | **1.19:1** | ✗ invisible | `theme.css:448` `.route-arrow` (flecha entre aeropuertos) |
| 2 | `#412c4d` | `#2a2633` | **1.19:1** | ✗ invisible | `theme.css:1301` `.map-popup-compact .popup-route i` |
| 3 | `#412c4d` | `#2a2633` | **1.19:1** | ✗ invisible | `theme.css:980` icono del título del modal de aeronave |
| 4 | `#3a3a52` | `#2a2633` | **1.34:1** | ✗ invisible | `latest_pireps.blade.php:55` `.lb-widget-route-arrow` |
| 5 | `#5a6a9a` | `#2a2633` | **2.78:1** | ✗ falla | `DisposableBasic/pireps/table.blade.php:112` icono info de `/dpireps` |
| 6 | `#666666` | `#1a1a2e` | **2.97:1** | ✗ falla | `theme.css:1198` `.fr24-info-label` |
| 7 | `#7a6a9a` | `#2a2633` | **3.06:1** | ⚠ solo texto grande | `users/table.blade.php:8` `.lb-pilot-ident` (badge de 0,65 rem) |
| 8 | `#888888` | `#2a2633` | **4.16:1** | ⚠ solo texto grande | `theme.css:479` `.detail-label` (0,7 rem) |
| 9 | `#888888` | `#2a2633` | **4.16:1** | ⚠ solo texto grande | `theme.css:500` `.status-time` |
| 10 | `#888888` | `#2a2633` | **4.16:1** | ⚠ solo texto grande | `theme.css:1319` `.popup-details-label` |
| 11 | `#888888` | `#2d2736` | **4.07:1** | ⚠ solo texto grande | `theme.css:909` `.flight-details-confirm .flight-route` |
| 12 | `#7878a0` | `#141118` | **4.45:1** | ⚠ solo texto grande | `logbook-styles.blade.php:89` `.lb-route-arrow` |
| 13 | `#7080b8` | `#2a2633` | **3.84:1** | ⚠ solo texto grande | `logbook-styles.blade.php:151` iconos de block time |
| 14 | `#2c7be5` | `#151524` | **4.35:1** | ⚠ solo texto grande | `theme.css:1154` `.fr24-route-time` |
| 15 | `#2c7be5` | `#1e2a3a` | **3.50:1** | ⚠ solo texto grande | `theme.css:1120` `.fr24-airline` |

Los casos 7 a 15 son todos textos de **0,6–0,8 rem** (≈ 10–13 px). A ese tamaño el umbral AA exige **4.5:1**, así que la excepción de "texto grande" **no aplica**: los 15 son fallos o están al borde.

**Los dos patrones que producen el síntoma que describes:**

- **Púrpura como color de texto/icono sobre superficie oscura** (casos 1–3, ratio 1.19): un `#412c4d` decorativo que se lee perfecto sobre fondo blanco pero es literalmente invisible sobre `#2a2633`. Es lo más grave del tema.
- **Gris oscuro sobre oscuro** (casos 4–6, 8–15): el tema decide "atenuar" bajando la luminosidad del gris (`#aaa` → `#888` → `#777` → `#666`) sin darse cuenta de que el fondo **también** es oscuro. Ahí es donde se pierden las etiquetas.

---

## 5. Diagnóstico

El tema llegó a este estado por acumulación, no por un error de diseño:

1. **Se partió del tema Disposable**, cuya paleta oscura es azul-fría y cuya escala de grises está pensada para fondo blanco. Vholar se construyó encima sin reemplazar la base.
2. **Cada vista nueva resolvió su color localmente** con estilos inline y bloques `<style>` embebidos (la familia `lb-*` del logbook y los widgets, los `create.blade.php` de vmsopenops, `fleet/grid`, `fleet/map`, `users/table`), en lugar de consumir un token.
3. **El `:root` se escribió pero nunca se adoptó**: se definieron variables "por si acaso" y se siguió escribiendo hex.
4. **La corrección de Bootstrap quedó a medias**: se arregló `--bs-primary` y `.bg-primary` pero no `.text-primary` ni `--bs-primary-rgb`.

---

## 6. Propuesta

### 6.1 Paleta institucional formalizada

| Token | Hex | Origen |
|---|---|---|
| `--vh-primary` | `#412C4D` | Púrpura VHolar (asset, exacto) |
| `--vh-primary-hover` | `#563A63` | derivado (aclarado) |
| `--vh-primary-active` | `#2D1E36` | derivado (oscurecido) |
| `--vh-silver` | `#BDBFC1` | Plata VHolar (anillo del logo) |
| `--vh-silver-dim` | `#B1B0B5` | Gris CARGO |
| `--vh-white` | `#FFFFFF` | Blanco (ave) |
| `--vh-black` | `#000000` | Negro (wordmark COLOMBIA) — solo sobre fondos claros |

### 6.2 Superficies: 23 → 3, con el tinte corregido

El punto clave: **pasar los neutros de H 256 (azul) a H 265-270 (púrpura)**, para que las superficies y la marca (H 278) compartan familia de tono. La brecha baja de ~20° a ~8-13°; no se iguala a propósito, porque una superficie tan saturada como el primario competiría con los botones.

| Token | Actual (fragmentado) | Aplicado |
|---|---|---|
| `--vh-bg` | `#1e1b24`, `#141118`, `#16131c`, `#12101e`, `#0f0f1a`, `#141118`… | `#16121B` (H 267) |
| `--vh-surface` | `#1f1c27`, `#1a1828`, `#1a1a2e`, `#1e2a3a`… | `#1E1925` (H 265) |
| `--vh-surface-2` | `#2a2633`, `#1a1a2e`… | `#28212F` (H 270) |
| `--vh-primary-active` | `#2a1f33` | `#2D1E36` |

> **Corrección respecto al plan original:** se proyectó un cuarto nivel `--vh-surface-3` (`#332B3C`). Al migrar las 23 superficies reales quedó claro que el tema solo necesita **3 niveles**; el cuarto se eliminó en lugar de dejarlo declarado sin uso. El análisis original también proponía `--vh-text-disabled` y `--vh-text-subtle`, que resultaron innecesarios (ver 6.3).

### 6.3 Texto y acentos: 13+ grises → 3 niveles + 1 acento

| Token | Hex | Contraste sobre `--vh-surface-2` | Uso |
|---|---|---|---|
| `--vh-text` | `#EDEAF1` | **13.08:1** | texto principal, valores |
| `--vh-text-muted` | `#A79FB2` | **6.11:1** | etiquetas, metadatos, texto de apoyo |
| `--vh-white` | `#FFFFFF` | 15.57:1 | blanco **puro**, solo sobre fondos de color (botones, cabeceras) y en hover |
| `--vh-accent-lite` | `#DFC8F5` | 10.05:1 | enlaces y acentos. Mismo nombre y valor que el admin |

`--vh-white` se distingue a propósito de `--vh-text`: este último es un blanco cálido para texto sobre superficie oscura; el blanco puro solo tiene sentido sobre un fondo de color, donde el cálido perdería fuerza. `--vh-accent-lite` unifica los dos lavandas que hacían lo mismo (`#dfc8f5` y `#c9a6db`) y adopta el nombre que **ya existía en `vholar-admin.css`**.

> El plan original contemplaba dos niveles más de texto (`--vh-text-subtle` `#9A90A5` y `--vh-text-disabled` `#6E6478`). Al migrar el tema se comprobó que **ninguno gana a `--vh-text-muted`**: `#9A90A5` da 4.45:1 sobre `--vh-surface-2` (por debajo de AA) y `#6E6478` queda en 2.7:1. Se eliminaron: dos niveles bastan y ambos pasan AA.

Reemplaza los 13+ grises/azules anteriores **sin perder jerarquía**: sigue habiendo tres niveles, pero ahora cada uno tiene un umbral de contraste declarado y comprobable.

### 6.4 Semánticos: 33 → 11 piezas en 4 familias

Cada familia tiene **tres piezas con una regla explícita**, no un solo color:

| Pieza | Rol | Regla |
|---|---|---|
| `<color>` | texto, iconos, bordes | AA ≥ 4.5:1 sobre superficie oscura |
| `<color>-soft` | fondo de pastilla o badge | alfa **0.10**; el texto es `<color>` |
| `<color>-strong` | **solo** fondo sólido | para texto **blanco** encima |

| Familia | `<color>` | `<color>-soft` | `<color>-strong` |
|---|---|---|---|
| Éxito | `#4CAF76` (5.71:1) | `rgba(76,175,118,.10)` | `#2F7A50` (blanco 5.22:1) |
| Advertencia | `#E0A82E` (7.27:1) | `rgba(224,168,46,.10)` | — sin consumidor |
| Peligro | `#E86A78` (5.76:1) | `rgba(232,106,120,.10)` | `#B8444F` (blanco 5.28:1) |
| Información | `#8FA6D9` (6.39:1) | `rgba(143,166,217,.10)` | — sin consumidor |

`--vh-info` sustituye de una vez a `#067ec1`, `#2c7be5`, `#0d2c99`, `#3498db` y `#3869d4`. Es un azul desaturado que **convive** con el púrpura en lugar de competir con él.

> **Dos correcciones al plan original, ambas medidas:**
>
> 1. **El peligro proyectado (`#E05A6A`) no pasaba AA.** Da 4.33:1 sobre `--vh-surface-2`, y se iba a usar en textos de 0.78 rem. Se cambió a `#E86A78` (5.76:1) **antes** de consumirlo, mientras aún era gratis.
> 2. **El alfa de los `-soft` bajó de 0.16 a 0.10.** Con 0.16, tres de las cuatro familias fallaban AA en su propia pastilla (`--vh-success-soft` daba 4.42:1). El alfa no es una decisión estética: es el máximo que permite que el texto del mismo color pase sobre su propio fondo.

Los dos `-strong` existen porque el patrón "fondo sólido del color de la familia + texto blanco" es imposible con el color de texto: blanco sobre `#4CAF76` da 2.6:1 y sobre `#E86A78`, 3.1:1. Solo se declararon los dos que tienen consumidor.

### 6.5 Corrección de la fuga de Bootstrap

```css
:root {
  --bs-primary: #412C4D;
  --bs-primary-rgb: 65, 44, 77;      /* ← lo que faltaba */
  --bs-body-bg: #16121B;
  --bs-body-color: #EDEAF1;
  --bs-secondary-color: #A79FB2;
  --bs-border-color: #3A3142;
}
```

Con `--bs-primary-rgb` definido, `.text-primary`, `.border-primary`, los `form-check` y los anillos de foco pasan a púrpura de marca **sin tocar ninguna vista**.

### 6.6 Tabla de sustitución de los fallos de contraste

| Archivo:línea | Actual | Ratio | → Propuesto | Ratio |
|---|---|---|---|---|
| `theme.css:448` `.route-arrow` | `#412c4d` | 1.19 | `var(--vh-silver)` | 8.44 |
| `theme.css:980` icono modal | `#412c4d` | 1.19 | `var(--vh-silver)` | 8.44 |
| `theme.css:1301` `.popup-route i` | `#412c4d` | 1.19 | `var(--vh-silver)` | 8.44 |
| `latest_pireps:55` `.lb-widget-route-arrow` | `#3a3a52` | 1.34 | `var(--vh-text-subtle)` | 5.12 |
| `DBasic/table:112` icono info | `#5a6a9a` | 2.78 | `var(--vh-text-muted)` | 6.11 |
| `theme.css:1198` `.fr24-info-label` | `#666` | 2.97 | `var(--vh-text-muted)` | 6.11 |
| `users/table:8` `.lb-pilot-ident` | `#7a6a9a` | 3.06 | `var(--vh-silver)` | 8.44 |
| `theme.css:479` `.detail-label` | `#888` | 4.16 | `var(--vh-text-muted)` | 6.11 |
| `theme.css:500` `.status-time` | `#888` | 4.16 | `var(--vh-text-muted)` | 6.11 |
| `theme.css:1319` `.popup-details-label` | `#888` | 4.16 | `var(--vh-text-muted)` | 6.11 |
| `logbook-styles:89` `.lb-route-arrow` | `#7878a0` | 4.45 | `var(--vh-silver-dim)` | 7.23 |
| `logbook-styles:151` iconos | `#7080b8` | 3.84 | `var(--vh-text-muted)` | 6.11 |
| `theme.css:1120` `.fr24-airline` | `#2c7be5` | 3.50 | `var(--vh-text-muted)` | 6.11 |
| `theme.css:1154` `.fr24-route-time` | `#2c7be5` | 4.35 | `var(--vh-info)` | 6.39 |
| `theme.css:1161` `.fr24-route-arrow` | `#2c7be5` | 4.35 | `var(--vh-silver-dim)` | 7.23 |

> **Nota de implementación:** `.lb-widget-route-arrow` se resolvió finalmente con
> `var(--vh-silver-dim)` y no con `var(--vh-text-subtle)`. El 5.12 de la tabla se calculó
> sobre la superficie objetivo de la fase 3 (`#1E1925`); hasta que esa migración ocurra, el
> widget se renderiza sobre `#2a2633`, donde `#9A90A5` da 4.25:1 e incumpliría. `--vh-text-subtle`
> queda reservado precisamente hasta la fase 3.

Además, la familia `lb-*` completa migra del celeste al neutro cálido, manteniendo los mismos niveles de jerarquía:

| Actual (celeste) | → | Propuesto |
|---|---|---|
| `#c0c8e8` (nº de vuelo) | → | `var(--vh-text)` |
| `#c8d8ff` (valores, block time) | → | `var(--vh-text)` |
| `#dce4ff` (códigos ICAO) | → | `var(--vh-text)` |
| `#a0a8c0`, `#8898cc`, `#8898c8` | → | `var(--vh-text-muted)` |
| `#9090a8`, `#9898b0`, `#9898b2`, `#7080b8` | → | `var(--vh-text-muted)` |
| `#90aaff` (hover) | → | `var(--vh-silver)` |
| `#7878a0`, `#7a6aaa`, `#7a6a9a` | → | `var(--vh-silver-dim)` |

`#9A90A5` (`--vh-text-subtle`) deja de ser decorativo y pasa a tener un único uso legítimo: flechas y separadores sobre el fondo más oscuro.

### 6.7 Archivos entregados

- **[`IMPLEMENTACION-FASES-1-2.md`](IMPLEMENTACION-FASES-1-2.md)** — capa de tokens, corrección de la fuga de azul de Bootstrap y las 18 reglas de contraste.
- **[`IMPLEMENTACION-FASE-3.md`](IMPLEMENTACION-FASE-3.md)** — migración del tinte, de las 23 superficies y de la familia celeste: 27 archivos, verificación y los 3 bugs que se detectaron y corrigieron durante el proceso.
- **[`IMPLEMENTACION-FASE-4.md`](IMPLEMENTACION-FASE-4.md)** — unificación de los semánticos: el modelo de tres piezas, la corrección medida del rojo y del alfa de los fondos suaves, y los tres contextos que no admiten `var()`.
- **[`paleta-vholar-auditoria.png`](paleta-vholar-auditoria.png)** — la auditoría visual: paleta institucional extraída, los 116 colores originales por frecuencia, los tokens propuestos y los fallos de contraste con su ratio.
- **[`paleta-vholar-antes-despues.png`](paleta-vholar-antes-despues.png)** — resultado de las fases 1 y 2: la corrección del azul de Bootstrap y los 13 fallos de contraste resueltos.
- **[`paleta-vholar-fase3.png`](paleta-vholar-fase3.png)** — resultado de la fase 3: la escalera de superficies con sus valores de tono, la familia celeste migrada y las métricas.
- **[`paleta-vholar-fase4.png`](paleta-vholar-fase4.png)** — resultado de la fase 4: los 33 colores sueltos → 4 familias, las 11 piezas con su regla y su contraste medido.
- **[`IMPLEMENTACION-FASE-5.md`](IMPLEMENTACION-FASE-5.md)** — los hex sueltos a tokens, la escala categórica armonizada y los 3 bugs de fases anteriores que se encontraron al auditar.
- **[`paleta-vholar-fase5.png`](paleta-vholar-fase5.png)** — resultado de la fase 5: qué se migró, los bugs corregidos y por qué cada uno de los 50 colores que quedan está justificado.
- **[`IMPLEMENTACION-FASE-6.md`](IMPLEMENTACION-FASE-6.md)** — las tres copias del `:root` → un archivo compartido, y la alineación completa del panel de administración.
- **[`paleta-vholar-fase6.png`](paleta-vholar-fase6.png)** — resultado de la fase 6: la fuente única, los valores antes/después del admin y los semánticos unificados.
- **[`IMPLEMENTACION-FASE-7.md`](IMPLEMENTACION-FASE-7.md)** — la home al tema oscuro, los hacks `[style*=…]` eliminados y el tercer fallo de contraste que apareció (el banner de cookies, 3.25:1).
- **[`paleta-vholar-fase7.png`](paleta-vholar-fase7.png)** — resultado de la fase 7 y **el resultado global de las siete fases**.

La capa de tokens ya **no** es un archivo aparte: vive en `theme.css:5-84`, activa.

---

## 7. Plan de implementación sugerido

| Fase | Trabajo | Archivos | Impacto | Estado |
|---|---|---|---|---|
| **1** | Capa de tokens en `theme.css` + `--bs-primary-rgb` y las variables de Bootstrap | 1 | Elimina la fuga de azul en 25 sitios de golpe, sin tocar vistas | ✅ **hecha** |
| **2** | Corregir los fallos de contraste de la tabla 6.6 | `theme.css` + 5 vistas | Resuelve el síntoma principal reportado | ✅ **hecha** (13 resueltos) |
| **3** | Migrar las 23 superficies oscuras y la familia `lb-*` del celeste a los tokens neutros | 27 archivos | Elimina ~50 de los 63 azules fríos | ✅ **hecha** |
| **4** | Unificar los semánticos (éxito/peligro/ámbar) y la escala de puntuación | 18 archivos | Un verde, un rojo, un ámbar | ✅ **hecha** |
| **5** | Reemplazar los hex sueltos de las vistas restantes por `var()` | 16 archivos | 76 colores → 50; 25/25 tokens en uso | ✅ **hecha** |
| **6** | Unificar las tres copias del `:root` en un archivo compartido y alinear el admin | 6 (1 nuevo) | Un solo origen de verdad | ✅ **hecha** |
| **7** | Oscurecer las islas claras de `home.blade.php` y eliminar los selectores `[style*=...]` | 4 | Quita deuda frágil + los 3 fallos que quedaban | ✅ **hecha** |

**Las siete fases están cerradas: 76 archivos.** El problema de color está resuelto de extremo a extremo: una paleta extraída de los assets institucionales, declarada en un único archivo, con tres familias semánticas de reglas explícitas, cero colores de paleta escritos a mano en las vistas, cero tokens sin consumidor y cero fallos de contraste.

> **Aviso para cualquier trabajo futuro sobre color:** la paleta se declara **solo** en
> [`public/assets/themes/vholar/css/tokens.css`](../../public/assets/themes/vholar/css/tokens.css).
> Ninguna vista ni hoja debe declarar un `:root` propio. Si hace falta un color nuevo, se añade
> allí.
>
> Y un `var(--token)` **no se resuelve** en opciones de Chart.js (`ctx.fillStyle`), en opciones
> de Leaflet ni en atributos SVG: falla en silencio. Por eso cada barrido de color debe
> verificar tres cosas antes de aplicarse: **rol** (texto/fondo/borde), **contexto**
> (CSS/JS/atributo) y **hoja de estilos cargada**. Los tres bugs de la fase 5 salieron de no
> comprobar la tercera.

---

## Anexo A · Variables CSS: mapa completo

**Frontend (`theme.css:5-16`)** — 10 definidas, 0 usadas
`--bs-primary` `--bs-secondary-color` `--primary-color` `--primary-hover` `--bg-dark` `--vh-primary` `--vh-dark` `--vh-light` `--vh-gray` `--vh-muted`

**Admin (`vholar-admin.css:8-17`)** — 8 definidas, sí usadas, pero aisladas del frontend
`--vh-bg` `--vh-surface` `--vh-surface2` `--vh-border` `--vh-accent` `--vh-accent-lite` `--vh-text` `--vh-muted`

**Referenciadas sin definir en el frontend (`app.blade.php:415-423`)**
`--vh-surface` `--vh-surface2` `--vh-border` `--vh-text`

**Colisiones de nombre:** `--vh-muted` (frontend `#d0c8dc` / admin `#d0c8dc`, mismo valor, distinto consumidor) · `--vh-dark` (frontend) vs `--vh-bg` (admin) para el mismo rol.

## Anexo B · Inventario de fondos oscuros (23 únicos)

```
#1f1c27 ×21   #2a2633 ×18   #1a1828 ×18   #412c4d ×11   #0d2c99 ×4
#2a1f33 ×4    #16131c ×3    #1a1a2e ×3    #12101e ×2    #563a63 ×2
#0d0b14 ×1    #1a1035 ×1    #2d1b69 ×1    #403a60 ×1    #141118 ×1
#0f0f1a ×1    #1e2a3a ×1    #0f1a24 ×1    #3d3547 ×1    #0d2e1a ×1
#2e0d0d ×1    #2e250d ×1    #1a1230 ×1
```

## Anexo C · Fondos claros (6 únicos, 15 usos)

```
#f3eff5 ×4   #f8f9fa ×4   #c8d8ff ×3   #ddd3e4 ×2   #f8f5fa ×1   #ffffff ×1
```

`#c8d8ff` funciona como **fondo** en `fleet/map.blade.php:63` (badge de aeropuerto, texto `#0d0b18`) — otro uso cruzado de la paleta celeste.

---

*Los ratios de contraste se calcularon con la fórmula de luminancia relativa de WCAG 2.1 (0.2126·R + 0.7152·G + 0.0722·B sobre canales linealizados). Los colores institucionales se extrajeron por histograma exacto de píxel sobre los assets entregados, descartando píxeles con alfa > 20 y el blanco de fondo.*
