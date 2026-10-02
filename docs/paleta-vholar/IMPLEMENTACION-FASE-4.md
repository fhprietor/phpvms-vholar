# Implementación · Fase 4 · Unificación de los colores semánticos

**Fecha:** 2 de octubre de 2026
**Alcance:** éxito / peligro / advertencia / información — de 33 colores sueltos a 11 piezas con reglas
**Archivos tocados:** 18
**Líneas modificadas:** ~100

---

## 1. El problema

El análisis detectó que el tema no tenía semántica, tenía colores:

| Familia | Colores distintos antes |
|---|---|
| Éxito | **10** (`#28a745`, `#48bb78`, `#29b473`, `#4caf76`, `#2ecc71`, `#1abc9c`, `#1a7a4a`, `#22bc66`, `#6bd098`, `#0d2e1a`) |
| Peligro | **12** (`#dc3545`, `#e53e3e`, `#e05060`, `#e08080`, `#e74c3c`, `#8a1a1a`, `#dc4d2f`, `#b02a37`, `#c82333`, `#a71d2a`, `#f5856e`, `#2e0d0d`) |
| Advertencia | **11** (`#ffc107`, `#e8d080`, `#e6a817`, `#b8a060`, `#f39c12`, `#e67e22`, `#8a6d00`, `#f1c40f`, `#ffd966`, `#ffd166`, `#2e250d`) |
| Información | **7** (`#067ec1`, `#2c7be5`, `#0d2c99`, `#3498db`, `#3869d4`, `#056093`, `#17a2b8`) |

La misma pantalla podía mostrar tres verdes distintos: la puntuación del logbook usaba `#4caf76`, los botones de reserva `#28a745` y los avisos de vmsopenops `#29b473`/`#22bc66`.

---

## 2. El modelo: tres piezas por familia

Cada familia semántica pasa a tener **tres piezas con una regla explícita**, documentada en el propio `:root`:

| Pieza | Rol | Regla |
|---|---|---|
| `<color>` | texto, iconos, bordes | AA ≥ 4.5:1 sobre superficie oscura |
| `<color>-soft` | fondo de pastilla o badge | alfa **0.10**; el texto es `<color>` |
| `<color>-strong` | **solo** fondo sólido | para texto **blanco** encima |

`theme.css:29-52`:

```css
--vh-success: #4CAF76;              /* 5.71:1 sobre --vh-surface-2 */
--vh-warning: #E0A82E;              /* 7.27:1 */
--vh-danger: #E86A78;               /* 5.01:1  (era #E05A6A = 4.33, no pasaba AA) */
--vh-info: #8FA6D9;                 /* 6.39:1 */

--vh-success-soft: rgba(76, 175, 118, 0.10);
--vh-warning-soft: rgba(224, 168, 46, 0.10);
--vh-danger-soft: rgba(232, 106, 120, 0.10);
--vh-info-soft: rgba(143, 166, 217, 0.10);

/* Solo para fondos sólidos con texto blanco encima */
--vh-success-strong: #2F7A50;       /* blanco encima: 5.22:1 */
--vh-danger-strong: #B8444F;        /* blanco encima: 5.28:1 */
```

### Por qué el alfa de los `-soft` es exactamente 0.10

Es una restricción medida, no estética. Una pastilla con fondo `<color>-soft` y texto `<color>` tiene menos contraste que el texto sobre la superficie normal, porque el fondo se acerca al color del texto. Al Subir el alfa, el contraste cae:

| Alfa | Éxito | Advertencia | Peligro | Info |
|---|---|---|---|---|
| 0.10 | 4.91 ✓ | 6.03 ✓ | **4.38** ✗ | 5.39 ✓ |
| 0.12 | 4.75 ✓ | 5.82 ✓ | **4.23** ✗ | 5.18 ✓ |
| **0.16** (era) | **4.42** ✗ | 5.33 ✓ | **3.96** ✗ | 4.81 ✓ |
| 0.20 | **4.15** ✗ | 4.90 ✓ | **3.72** ✗ | **4.39** ✗ |

Con el alfa anterior (0.16), **tres de las cuatro familias fallaban AA en su propia pastilla**. 0.10 es el único valor que pasa en las cuatro.

### Corrección del color de peligro

`--vh-danger` se declaró en la fase 1 como `#E05A6A` y estaba **reservado, sin consumir**. Al irlo a usar en textos de 0.78 rem se comprobó que **no pasaba AA**:

| Color | sobre `--vh-bg` | sobre `--vh-surface` | sobre `--vh-surface-2` |
|---|---|---|---|
| `#E05A6A` (anterior) | 5.14 | 4.78 | **4.33** ✗ |
| `#E86A78` (aplicado) | 6.84 | 6.37 | **5.76** ✓ |

Se corrigió **antes** de consumirlo, mientras seguía siendo gratis. El `--vh-danger-soft` pasó a `rgba(232, 106, 120, 0.10)` para coincidir con el nuevo RGB.

### Los dos `-strong`

Hacen falta porque el patrón "fondo sólido del color de la familia + texto blanco" es imposible de hacer accesible con el color de texto: el blanco sobre `#4CAF76` da 2.6:1 y sobre `#E86A78`, 3.1:1. Se usan en:

- `--vh-success-strong` → `.bid-add:hover`
- `--vh-danger-strong` → `.bid-remove:hover`, `.vholar-confirm-modal .btn-confirm`

Se añadieron **solo estos dos**: advertencia e información no tienen ningún consumidor de fondo sólido con texto blanco, así que no se declararon.

---

## 3. Qué cambió, por contexto

Los colores no se pueden migrar todos igual: hay tres contextos y cada uno necesita una forma distinta.

### a) Reglas CSS → `var()`

En `theme.css`: `.bid-add`, `.bid-remove`, los cuatro `.toast-*`, `.confirm-icon`, `.modal-title i`, `.carousel-caption a`, `.map-popup-compact .popup-route`, `.btn-confirm` y `.fr24-status`.

En las vistas: `.lb-score-good/ok/bad` (logbook), `.lrate-good/ok/hard` (`/dpireps`), `.lb-action-btn.btn-bid-add/rem` (`/dassignments`), las alertas y avisos de `/vmsopenops/jumpseat` y `/ferry`, y el icono de `.flight-modal`.

### b) Contextos JS o atributo → **literal**, no `var()`

Un `var(--token)` **no se resuelve** cuando el valor no pasa por el árbol CSS. En esos sitios se usó el hex resuelto del token:

| Sitio | Motivo |
|---|---|
| `pireps/show.blade.php:490` `borderColor` | Chart.js dibuja en `<canvas>`: `ctx.strokeStyle = 'var(--x)'` **falla en silencio** |
| `widgets/live_map.blade.php` `fr24StatusColors` | Alimenta estilos de marcadores de Leaflet |
| `pireps/map.blade.php`, `flights/map.blade.php` `flown_route_color` / `circle_color` / `flightplan_route_color` | Opciones de una librería JS |
| `assignments/admin.blade.php:159` `stroke="#28a745"` | Atributo de presentación SVG: `var()` no es fiable entre navegadores |
| `flights/table.blade.php:107` `$statusColor` | **Se le concatena el alfa**: `style="background: {{ $statusColor }}20"`. Con un `var()` el resultado sería `var(--vh-info)20`, inválido |
| `app.blade.php:196` cookieconsent | Configuración de una librería JS |

Este patrón de "concatenar el alfa" es la trampa más silenciosa: `{{ $statusColor }}20` funciona con `#8FA6D9` y se rompe con cualquier otra forma. Quedó comentado en el archivo.

### c) Paletas categóricas → **no se tocaron**

`assignments/index.blade.php:200,735` usan `['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#2c3e50']` para colorear rutas distintas. Eso **no es semántica, es categórico**: si se unifican, las rutas dejan de distinguirse. Requiere una decisión de diseño propia y queda para la fase 5.

### d) Aprovechando el viaje: el resto de la familia azulada

Al tocar `.fr24-status` aparecieron los últimos restos de la paleta celeste de la fase 3, que estaban como `rgba()` y por eso se escaparon del barrido por hex:

| Sitio | Antes | Ahora |
|---|---|---|
| `.fr24-status` (píldora de estado) | `rgba(44,123,229,0.15)` | `var(--vh-info-soft)` + `color: var(--vh-info)` |
| `.flight-modal .modal-content` borde | `rgba(44,123,229,0.3)` | `var(--vh-border)` |
| `.lb-pilot-rank`, `.lb-ac-type` (badges) | `rgba(100,120,200,0.15)` | `var(--vh-primary-soft)` — tinte de marca, no azul |
| Panel de vista previa de vmsopenops | `rgba(40,60,100,0.18)` | `var(--vh-surface)` / `var(--vh-border)` |
| Bordes de las etiquetas de mapa | `rgba(80,100,200,0.5)` | `rgba(13,11,24,0.35)` — el fondo ya es plata |

Ya no queda **ningún** `rgba()` azulado en el tema.

### e) Avance de la fase 5 en el gráfico de altitud

El bloque de Chart.js de `pireps/show.blade.php` se ajustó de paso, porque estaba en el mismo sitio y son literales sin riesgo: `#2c7be5` → `#8FA6D9`, su relleno `rgba(44,123,229,0.1)` → `rgba(143,166,217,0.1)`, y los grises del gráfico `#aaa`/`#fff` → `#A79FB2`/`#EDEAF1`.

---

## 4. Verificación

| Métrica | Antes (fase 3) | Ahora | Δ |
|---|---|---|---|
| Colores hex únicos | 76 | **66** | −10 |
| Declaraciones de color | 235 | **204** | −31 |
| **Texto bajo 4.5:1** | **17** | **9** | **−8** |
| Tokens con consumidor | 14 | **23 / 23** | +9 |

**Los 23 tokens están en uso. No queda ninguno declarado sin consumidor.**

### Los 9 casos que quedan: solo 2 son reales

De los 9, **7 son falsos positivos** de la medición: son texto oscuro sobre las tarjetas **claras** de `home.blade.php`, y el medidor los evalúa contra las superficies oscuras. Comprobados contra su fondo real:

| Caso | Fondo real | Ratio | Veredicto |
|---|---|---|---|
| `home.blade.php:63` `#5a4a66` | `#F3EFF5` | 7.09 | correcto — falso positivo |
| `home.blade.php:292` `#412c4d` | `#DDD3E4` | 8.58 | correcto — falso positivo |
| `home.blade.php:67` `#7b6a87` | `#F3EFF5` | **4.34** | **falla real** (14 px) |
| `theme.css:362` `#6c757d` | `#f8f9fa` | **4.45** | **falla real** (`.text-muted`) |

**Quedan 2 fallos reales en todo el tema, y los dos están en las islas claras** — exactamente el alcance de la fase 7. El tema oscuro ya no tiene ningún fallo de contraste.

### Comprobaciones automáticas

- Llaves CSS: 214/214.
- Referencias circulares: **0**.
- Tokens referenciados sin definir: **0**.
- `var()` en contextos donde no se resuelve: **0**.
- `rgba()` azulados de la familia celeste restantes: **0**.
- 17 vistas Blade modificadas compilan sin errores.
- **Permisos verificados tras ejecutar los scripts**: 0 archivos con grupo `frank` y 0 archivos no escribibles por `www-data` (ver [`OPERACION-PERMISOS.md`](../OPERACION-PERMISOS.md)).

---

## 5. Qué NO se tocó (y por qué)

| Elemento | Motivo |
|---|---|
| `#0d2c99` (IVAO), `#29B473` (VATSIM), `#738ADB` (Discord), `#403a60` (POSCON) | Son **colores de marca de terceros**. Repintarlos de Vholar sería incorrecto: el piloto espera reconocer la red en el botón. |
| Paletas categóricas de rutas (7 colores × 2 sitios) | Es una escala **categórica**, no semántica. Unificarla haría indistinguibles las rutas. Fase 5. |
| `#f1c40f` (badge base del mapa), `#e9ecef` (pista del anillo SVG) | Casos únicos y ya funcionales. |
| Los grises `#aaa` / `#888` / `#bbb` de las vistas | Fase 5, y **con cuidado**: algunos están en opciones de Chart.js donde `var()` no se resuelve. |
| Islas claras de `home.blade.php` | Fase 7 (los 2 fallos que quedan). |
| `vholar-admin.css` | Fase 6. |

---

## 6. Reversión

Respaldo completo del tema antes de la fase 4 (91 archivos):

```
/tmp/coloranalysis/backup4/resources/views/layouts/vholar/
```

---

## 7. Cómo comprobarlo

1. **`/flights`** — los botones de reservar/quitar de la lista: fondo de pastilla suave, texto en el verde o el rojo de marca, y al pasar el ratón un fondo sólido **oscuro** con texto blanco legible (antes el hover era un verde brillante con blanco encima, 2.6:1).
2. **`/dpireps`** — la columna de puntuación y la de landing rate: los tres niveles deben ser exactamente los mismos verdes/ámbar/rojos que en el dashboard y en `/dassignments`.
3. **Cualquier borrado con el modal de confirmación** — el botón "Sí, cancelar reserva" ahora es un rojo más profundo con blanco legible, y el icono de aviso ya no es el rojo de Bootstrap.
4. **`/vmsopenops/jumpseat`** — la alerta ámbar: fondo suave, título en blanco y detalle en ámbar. Antes los dos textos eran ámbar apagado sobre ámbar.
5. **`/pireps/{id}`** — el gráfico de altitud: línea azul-acero de marca en vez del azul eléctrico, y los ejes en el gris cálido del tema.
6. **`/livemap`** — los colores de estado de los vuelos (en ruta, aproximación, aterrizado) siguen distinguiéndose, pero dentro de la paleta.
7. **`/dusers` y `/dpireps`** — los badges de rango y de tipo de aeronave ahora tienen tinte púrpura de marca, no azulado.
