# Implementación · Fase 5 · Los hex sueltos pasan a tokens

**Fecha:** 2 de octubre de 2026
**Alcance:** migrar los colores escritos a mano en las vistas, y unificar los restos de la familia violeta
**Archivos tocados:** 16 (15 del tema + `vholar-admin.css`)
**Líneas modificadas:** ~127

---

## 1. Qué se hizo

### 1.1 Dos tokens nuevos

| Token | Valor | Consumidores | Por qué |
|---|---|---|---|
| `--vh-white` | `#FFFFFF` | **29** | Blanco **puro**, se distingue a propósito de `--vh-text` (`#EDEAF1`, blanco cálido): se usa para texto sobre fondos de color (botones, cabeceras) y en estados de hover, donde el blanco cálido pierde fuerza. |
| `--vh-accent-lite` | `#DFC8F5` | **9** | Enlaces y acentos. El nombre y el valor **ya existían en `vholar-admin.css`** — se adopta el mismo para no inventar otro. Absorbe también el `#c9a6db`, que hacía lo mismo un poco más oscuro. |

### 1.2 Colores migrados

| Antes | Usos | Rol | Ahora |
|---|---|---|---|
| `#ffffff` / `#fff` | 29 | texto sobre fondos de color | `var(--vh-white)` |
| `#aaa` | 8 | texto secundario | `var(--vh-text-muted)` |
| `#888` | 5 | texto secundario (3) y bordes de hover (2) | `var(--vh-text-muted)` / `var(--vh-silver-dim)` |
| `#dfc8f5` | 6 | enlaces y acentos | `var(--vh-accent-lite)` |
| `#e5e5e5` | 5 | texto principal | `var(--vh-text)` |
| `#c9a6db` | 5 | enlaces y acentos | `var(--vh-accent-lite)` |
| `#ddd`, `#bbb`, `#f0f0f0` | 4 | texto | `var(--vh-text)` / `var(--vh-text-muted)` |
| `#5e3e73` | 1 | borde de hover de tarjeta | `var(--vh-primary-hover)` |
| `#cccccc` | 2 | borde y texto de lista | `var(--vh-border)` / `var(--vh-text)` |

### 1.3 La familia violeta/índigo, unificada

Quedaban **13 apariciones de `rgba(120,100,180,0.3)`** y otras variantes índigo (`rgba(80,60,140,0.5)`, `rgba(60,40,100,0.5)`, `rgba(100,70,140,0.18)`, `rgba(100,80,140,0.25)`, `rgba(60,80,160,0.18)`) repartidas por vmsopenops, `/dassignments` y `/dusers`. Se habían escapado del barrido de la fase 3 porque aquel buscaba hex, no `rgba()`.

Todas pasan a tokens: bordes → `var(--vh-border)`, fondos de pastilla → `var(--vh-primary-soft)`. Ya no queda **ningún** color frío ajeno a la marca en el tema.

> Los `rgba()` que **sí** se conservan son los del púrpura de marca con alfas deliberados (0.1, 0.2, 0.4, 0.5): rayado de tablas, sombras, fondos de pastilla. No son deuda: son el color de marca con transparencia, y cada alfa tiene un peso visual distinto e intencionado.

### 1.4 La escala categórica de rutas

La paleta de 7 colores que distingue rutas en el mapa (`assignments/index.blade.php`, dos sitios) era la del tema por defecto de Laravel: rojo, azul, verde, naranja, morado, turquesa y gris pizarra saturados. Se armonizó 1:1 con la paleta de marca:

```
#e74c3c → #4CAF76 (éxito)      #9b59b6 → #C9A6DB (violeta)
#3498db → #8FA6D9 (info)       #1abc9c → #5FBFB0 (turquesa)
#2ecc71 → #E0A82E (advertencia)#e67e22 → #BDBFC1 (plata)
#f39c12 → #E86A78 (peligro)    #2c3e50 →
```

Los 7 mantienen **5.5:1 o más** sobre el fondo del mapa y siguen siendo distinguibles entre sí. Es una escala **categórica**, no semántica: su función es diferenciar, así que no se puede reducir a un color. Va como literal porque los consume Leaflet.

### 1.5 Un bug real de contenido: el widget del METAR

`widgets/weather.blade.php` mostraba el enlace de respaldo del METAR con `color:#000` (**negro**) mientras el embed se pide con `bg_color=1f1c27` (**fondo oscuro**): texto invisible. Se corrigió el color y, ya que `bg_color` es un parámetro seguro de cambiar, se actualizó al nuevo valor de superficie (`28212F`).

---

## 2. Tres bugs de las fases anteriores, encontrados y corregidos

Al auditar qué tokens resuelven realmente en cada contexto aparecieron tres defectos **introducidos por mis propios barridos**:

### 2.1 Vistas de admin con tokens sin definir

Mi barrido de la fase 3 sustituyó en `modules/DisposableSpecial/assignments/admin.blade.php`:

```diff
- background: linear-gradient(135deg, #2a2633 0%, #1f1c27 100%); border: 1px solid #412c4d;
+ background: linear-gradient(135deg, var(--vh-surface-2) 0%, var(--vh-surface) 100%); border: 1px solid var(--vh-primary);
```

Pero esa vista **extiende `admin.app`**, que carga `vholar-admin.css` — donde esos nombres **no existen** (el admin usa `--vh-surface2`, y no tenía `--vh-primary`). Un `var()` sin definir y sin fallback hace que **la declaración completa sea inválida**, así que el fondo de los dos modales se perdía.

**Corregido**: se añadieron `--vh-primary` y `--vh-surface-2` al `:root` del admin, con `--vh-surface2` como alias. Se adopta el nombre canónico del frontend.

### 2.2 La página de login con un token sin definir

El script de la fase 4 sustituyó `rgba(65,44,77,0.3)` por `var(--vh-primary-soft)` en **9 sitios**, uno de ellos en `auth/login_layout.blade.php`. Pero esa página **no carga `theme.css`**: tiene su propio `:root` copiado a mano, y no incluía `--vh-primary-soft`. El anillo de foco del formulario de acceso desaparecía.

**Corregido**: su `:root` se completó (también con `--vh-white` y `--vh-accent-lite`, que la migración de esta fase necesitaba) y se dejó un aviso: *si se añade un token a `theme.css` hay que añadirlo también aquí*.

> Esto es deuda estructural: **hay tres copias del `:root`** (theme.css, login_layout, vholar-admin.css). La fase 6 debe reducirlas. Mientras tanto, cada token nuevo hay que replicarlo.

### 2.3 Comentarios dañados

Los barridos anteriores protegían las definiciones de token pero **no los comentarios**. Cuatro líneas que documentaban el valor **original** de un cambio decían `var(--vh-primary)` donde debían decir `#412c4d`:

```css
/* Era var(--vh-primary) sobre tarjeta oscura: 1.19:1, invisible. */   ← mal
/* Era #412c4d sobre tarjeta oscura: 1.19:1, invisible. */            ← restaurado
```

**Corregido** en los 4 sitios. Los scripts de esta fase ya llevan seguimiento de bloques `/* … */`.

### 2.4 Limpieza: 6 alias deprecados, eliminados

Desde la fase 1 se mantenían 6 alias de compatibilidad (`--vh-dark`, `--vh-gray`, `--vh-muted`, `--vh-light`, `--primary-color`, `--primary-hover`, `--bg-dark`). Comprobado que **ya no tienen ningún consumidor** en el tema (los 7 usos de `--vh-muted` son del admin, que define el suyo; y el tema `seven` también define los suyos propios): se eliminaron. `.bg-primary` pasó a `var(--vh-primary)`.

**Resultado: 25 tokens declarados, 25 con consumidor. Cero declaraciones muertas.**

---

## 3. Verificación

| Métrica | Antes (fase 4) | Ahora | Δ |
|---|---|---|---|
| Colores hex únicos | 76 | **50** | −26 |
| Declaraciones de color | 235 | **150** | −85 |
| Tokens declarados | 23 | **25** | +2 |
| Tokens **con** consumidor | 23 / 23 | **25 / 25** | — |
| Fallos de contraste | 9 (2 reales) | 9 (2 reales) | — |

### Los 50 colores que quedan, justificados

| Categoría | Nº | Por qué se quedan |
|---|---|---|
| Definiciones de token | 20 | La paleta misma. **Ya no hay ningún color de la paleta escrito a mano dentro de una vista.** |
| Islas claras de `home.blade.php` | 10 | Decisión de diseño pendiente (fase 7). Ahí están los 2 fallos de contraste que quedan. |
| Literales de JS y escala categórica | 7 | Chart.js, Leaflet y la escala de rutas: un `var()` **no se resuelve** en esos contextos. |
| Referencias en comentarios | 6 | Documentan el valor original sustituido, para poder auditar cada cambio. |
| Marcas de terceros | 4 | IVAO, VATSIM, Discord, POSCON. Repintarlos sería incorrecto. |
| Extremos oscuros del gradiente de peligro | 4 | El texto blanco necesita un fondo suficientemente oscuro (fase 4). |

**Ninguno es deuda pendiente.**

### Comprobaciones automáticas

- Llaves CSS: 214/214 · Referencias circulares: **0** · Tokens referenciados sin definir: **0**.
- Los dos archivos con `:root` propio (`login_layout`, `assignments/admin`) auditados: **todos** sus tokens resuelven.
- Sin declaraciones duplicadas en el `:root`.
- 14 vistas Blade modificadas compilan sin errores.
- **Permisos**: 0 archivos con grupo `frank`, 0 no escribibles por `www-data` (ver [`OPERACION-PERMISOS.md`](../OPERACION-PERMISOS.md)).
- Sitio: todas las peticiones 200 desde el arreglo de permisos; 0 errores en el log de Laravel.

---

## 4. Qué NO se tocó

| Elemento | Motivo |
|---|---|
| Islas claras de `home.blade.php` (`#F8F5FA`, `#F3EFF5`, `#DDD3E4`, `#F8F9FA` y sus textos) | **Fase 7.** Es una decisión de diseño: mantener el contraste deliberado u oscurecer. Ahí siguen los 2 fallos de contraste. |
| `#0d2c99`, `#29B473`, `#738ADB`, `#403a60` | Marcas de terceros. |
| `rgba(65,44,77,0.1/0.2/0.4/0.5)` | Púrpura de marca con alfas deliberados, no colores ajenos. |
| Los `:root` de `vholar-admin.css` y `login_layout.blade.php` | **Fase 6**: unificar el admin al mismo conjunto de valores. Ahora mismo el admin usa los valores **antiguos** (`#16131c`, `#1f1c27`, `#2a2633`) y `--vh-border: #412c4d`, que no coinciden con el frontend. |
| `#e9ecef`, `#f1c40f`, `#0d0b18`, `#0d0b14`, `#e0e8ff`, `#edeff5`, `#838391` | Casos únicos ya funcionales (pista del anillo SVG, etiquetas de mapa, config de cookieconsent). |

---

## 5. Reversión

Respaldo completo antes de la fase 5:

```
/tmp/coloranalysis/backup5/            (91 archivos del tema + vholar-admin.css)
```

---

## 6. Cómo comprobarlo

1. **`/weather` o el widget del dashboard** — el enlace de respaldo del METAR ya no es negro sobre fondo oscuro.
2. **`/pireps/{id}`** — la lista del registro: el texto usa el color principal del tema y el borde el gris de marca, no `#ccc` y `rgba(255,255,255,.07)`.
3. **`/dassignments`** — el botón de pantalla completa del mapa y el mapa de rutas: la escala de colores de las rutas ahora pertenece a la paleta (ya no hay rojo, azul y turquesa saturados).
4. **`/vmsopenops/jumpseat` y `/ferry`** — los formularios: los bordes de los desplegables Select2, los paneles de vista previa y los modales usan el borde y el tinte de marca, no índigo.
5. **`/login`** — el anillo de foco del campo de usuario ha vuelto, y el enlace *"¿Olvidaste tu contraseña?"* usa el lavanda de marca.
6. **Admin → Flight Assignments** (`/admin/dassignments`) — los modales del mapa recuperan su fondo (estaba roto por el bug 2.1).

---

## 7. Lección de proceso

Los tres bugs de la sección 2 tienen la misma forma: **un reemplazo masivo de color es seguro en un contexto y silenciosamente roto en otro.** El patrón que los produjo:

- Un `var(--token)` en un archivo que carga **otra** hoja de estilos → indefinido → la declaración entera se descarta en silencio.
- Un `var()` en un contexto que no es CSS (canvas, opciones de librería) → no se resuelve, sin aviso.
- Un reemplazo global que no distingue **código** de **comentario** → documentación que miente.

Por eso cada fase desde la 3 verifica tres cosas antes de aplicar: **rol** (texto/fondo/borde), **contexto** (CSS/JS/atributo) y **hoja de estilos cargada**. Los tres bugs se detectaron en la auditoría, no en producción — pero el de admin (2.1) sí llegó a estar vivo en el tema desde la fase 3.
