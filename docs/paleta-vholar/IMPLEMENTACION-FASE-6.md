# Implementación · Fase 6 · Una sola fuente de verdad para los tokens

**Fecha:** 2 de octubre de 2026
**Alcance:** unificar las tres copias del `:root` y alinear el panel de administración con el frontend
**Archivos tocados:** 5 + 1 nuevo

---

## 1. El problema

Los tokens estaban declarados **tres veces**, cada copia mantenida a mano:

| Documento | Tokens propios | Problema |
|---|---|---|
| `theme.css` | 25 | La referencia, pero era solo del frontend |
| `login_layout.blade.php` | 12 | Copia parcial; le faltaban los que la fase 4 empezó a usar |
| `vholar-admin.css` | 9 | **Valores de superficie antiguos** (`#16131c`, `#1f1c27`, `#2a2633`) y nombres distintos (`--vh-surface2`, `--vh-accent`, `--vh-muted`) |

Esa duplicación **no es teórica: produjo dos bugs reales** que se encontraron y corrigieron en la fase 5 — una vista de admin y la página de login referenciando tokens que su hoja no definía, con la declaración descartada en silencio.

Y el panel de administración **nunca recibió el trabajo de la fase 3**: seguía con el neutro azulado (H 256-260) mientras el frontend ya usaba el púrpura (H 265-270). Dos productos visualmente distintos.

---

## 2. La solución: `tokens.css`

Nuevo archivo **`public/assets/themes/vholar/css/tokens.css`** (26 tokens), que contiene toda la paleta más las reglas de uso documentadas en su cabecera. Lo cargan las tres superficies:

| Superficie | Dónde se enlaza |
|---|---|
| Frontend | `layouts/vholar/app.blade.php` — **antes** de `theme.css` |
| Página de login | `layouts/vholar/auth/login_layout.blade.php` — ya no tiene `:root` propio |
| Panel admin | `admin/app.blade.php` — **antes** de `vholar-admin.css` |

> El directorio `public/assets/themes/vholar/css/` **es un symlink** a `resources/views/layouts/vholar/css/`. No son dos copias: es el mismo archivo. Eso explica por qué `theme.css` siempre aparecía con el mismo inodo en las verificaciones anteriores.

`theme.css` ya **no declara ningún token** (`--vh-*`: 0). Solo conserva los 9 ajustes de variables de **Bootstrap 5.3**, que son específicos del frontend:

```css
:root {
  --bs-primary: #412C4D;
  --bs-primary-rgb: 65, 44, 77;
  --bs-body-bg: var(--vh-surface-2);
  ...
}
```

### Token nuevo

`--vh-warning-strong: #8A6D00` (blanco encima: 4.92:1). El admin lo necesitaba para sus badges y bordes de alerta de advertencia; la familia de advertencia era la única que no tenía variante `-strong` porque hasta ahora no tenía consumidor.

---

## 3. Lo que cambió en el panel de administración

### 3.1 Nombres alineados

| Nombre en el admin | Ahora | Motivo |
|---|---|---|
| `--vh-surface2` (14 usos) | `--vh-surface-2` | El frontend usa el guion |
| `--vh-accent` (9 usos) | `--vh-primary` | Era el color de botones primarios; en el frontend ese rol es `--vh-primary` |
| `--vh-muted` (7 usos) | `--vh-text-muted` | Mismo nombre en todas partes |

### 3.2 Valores alineados

| Rol | Antes en el admin | Ahora | Cambio |
|---|---|---|---|
| Fondo de página | `#16131c` | `#16121B` | tinte púrpura |
| Tarjeta / panel | `#1f1c27` | `#1E1925` | tinte púrpura |
| Cabecera, modal | `#2a2633` | `#28212F` | tinte púrpura |
| Texto | `#e5e5e5` | `#EDEAF1` | más claro y cálido |
| Texto secundario | `#d0c8dc` | `#A79FB2` | unificado con el frontend |
| Botón primario | `#6b3fa0` | `#412C4D` | **ahora coincide con el botón del sitio** |
| Enlaces y acentos | `#dfc8f5` | `#DFC8F5` | sin cambio (ya coincidía) |

### 3.3 Una decisión de diseño: los bordes

El admin usaba `#412c4d` — **el púrpura de marca** — para sus 23 bordes estructurales (sidebar, logo, inputs, botones, breadcrumb, paginación). El token del frontend `--vh-border` vale `#3A3142`, un gris púrpura sutil.

Un reemplazo directo por `--vh-border` habría dejado el panel notablemente más plano. Como esos bordes **son** el púrpura de marca, se mapearon a **`var(--vh-primary)`**: valor idéntico, nombre semánticamente correcto y **cero cambio visual**. El panel conserva su carácter, pero ahora expresa *por qué* es de ese color.

### 3.4 Los semánticos, unificados

El admin tenía sus propios verdes, rojos y ámbar, todos más apagados que los del frontend:

| Familia | Antes (fondo / borde / texto) | Ahora |
|---|---|---|
| Éxito | `#0d2e1a` / `#1a7a4a` / `#6bd098` | `--vh-success-soft` / `--vh-success-strong` / `--vh-success` |
| Peligro | `#2e0d0d` / `#8a1a1a` / `#f5856e` | `--vh-danger-soft` / `--vh-danger-strong` / `--vh-danger` |
| Advertencia | `#2e250d` / `#8a6d00` / `#ffd166` | `--vh-warning-soft` / `--vh-warning-strong` / `--vh-warning` |
| Información | `#1a1230` / `#6b3fa0` / `#dfc8f5` | `--vh-info-soft` / `--vh-info` |
| Neutro | `rgba(65,44,77,…)`, `rgba(107,63,160,…)` | `--vh-primary-soft` |
| Foco | `rgba(107,63,160,0.25)` | `--vh-focus-ring` — igual que el frontend |

### 3.5 Un bug corregido en el camino

Al reemplazar `--vh-accent` por `--vh-primary`, `.badge-info` y `.badge-secondary` **quedaron del mismo color** (ambos `#412C4D`), cuando antes eran distintos. Se separaron:

- `.badge-info` → `var(--vh-info)` **con texto oscuro** añadido. Es un color claro: el blanco por defecto de Bootstrap 3 sobre `#8FA6D9` daría 1.9:1. Con `--vh-primary-active` encima: 6.39:1.
- `.badge-secondary` → `var(--vh-border)`, neutro.

Los cinco badges del admin verificados:

| Badge | Texto sobre fondo | Ratio |
|---|---|---|
| success | `#FFFFFF` sobre `#2F7A50` | 5.22 ✓ |
| warning | `#FFFFFF` sobre `#8A6D00` | 4.92 ✓ |
| danger | `#FFFFFF` sobre `#B8444F` | 5.28 ✓ |
| info | `#2D1E36` sobre `#8FA6D9` | 6.39 ✓ |
| secondary | `#FFFFFF` sobre `#3A3142` | 12.37 ✓ |

---

## 4. Verificación

### El panel de administración

| Métrica | Antes | Ahora | Δ |
|---|---|---|---|
| Colores hex crudos | 22 | **0** | −22 |
| Valores `rgba()` no negros | 6 | **0** | −6 |
| Referencias a tokens | 85 | **115** | +30 |
| Tokens distintos usados | 9 | **24** | +15 |
| Copias del `:root` | 1 | **0** | −1 |

El único `#hex` que queda en `vholar-admin.css` está **dentro de un comentario** (una nota sobre el `#212120` que usa Paper Dashboard). Los tres `rgba(0,0,0,…)` restantes son sombras.

### Global

| Métrica | Antes | Ahora |
|---|---|---|
| Documentos que declaran tokens (tema vholar) | 3 | **1** (`tokens.css`) |
| Tokens declarados | 25 | **26** |
| Tokens **con** consumidor | 25 / 25 | **26 / 26** |
| Referencias a tokens sin definir | — | **0** |

### Comprobaciones automáticas

- Llaves CSS: `tokens.css` 1/1 · `theme.css` 214/214 · `vholar-admin.css` 67/67.
- **Todo el que carga `theme.css` carga `tokens.css` antes**: verificado (`app.blade.php` es el único que lo carga).
- Las 3 vistas modificadas compilan sin errores.
- **Permisos**: 0 archivos con grupo `frank`, 0 no escribibles por `www-data` (ver [`OPERACION-PERMISOS.md`](../OPERACION-PERMISOS.md)).
- Sitio: peticiones 200, 0 errores en el log de Laravel tras el cambio.

---

## 5. Qué NO se tocó

| Elemento | Motivo |
|---|---|
| Islas claras de `home.blade.php` y los selectores `[style*=…]` | **Fase 7.** Ahí están los 2 fallos de contraste que quedan. |
| `layouts/seven/app.blade.php` | El tema `seven` es **otra plantilla** (inactiva) con sus propios tokens. No comparte el sistema de tokens de vholar. |
| Los estilos propios de Paper Dashboard en `vendor.css` / `admin.css` | Fuera del alcance: `vholar-admin.css` los sobrescribe donde importa. |
| `rgba(0,0,0,0.3/0.4/0.6)` del admin | Sombras negras, no colores de la paleta. |

---

## 6. Reversión

```
/tmp/coloranalysis/backup6/     (vholar-admin.css, theme.css, login_layout.blade.php,
                                 app.blade.php, admin/app.blade.php)
```

`tokens.css` es nuevo: para revertir por completo hay que borrarlo y restaurar los 5 respaldos.

---

## 7. Cómo comprobarlo

1. **Cualquier página del admin** (`/admin`) — el fondo, las tarjetas y las tablas ahora tienen el **mismo tinte púrpura** que el sitio público, no el gris azulado anterior.
2. **Admin → cualquier tabla** — las cabeceras siguen subrayadas en púrpura de marca (era intencionado), y las filas alternas usan el tinte de marca.
3. **Admin → botones primarios** — ahora son el mismo púrpura `#412C4D` que los botones del frontend, no el violeta `#6b3fa0`.
4. **Admin → alertas** (`alert-success`, `alert-danger`, `alert-warning`) — usan los mismos verdes, rojos y ámbar del frontend.
5. **Admin → los badges de estado** — cinco colores distintos y todos legibles. Antes `badge-info` y `badge-secondary` eran iguales.
6. **Admin → cualquier input al enfocar** — el anillo de foco es el mismo plateado que en el frontend.
7. **`/login`** — sin cambios visuales: ya cargaba sus tokens correctamente, pero ahora los toma del archivo compartido en vez de una copia a mano.

---

## 8. Lo que esto cierra

A partir de ahora, **añadir un color al producto es tocar un solo archivo**. La clase de bug que produjo los dos defectos de la fase 5 (una superficie referenciando un token que su hoja no definía) ya no puede ocurrir por olvido de sincronización: solo hay una lista que sincronizar, y es la que todas cargan.

Queda la fase 7: las tarjetas claras de `home.blade.php` — la única deuda de color que sobrevive, con los 2 fallos de contraste reales que quedan en todo el tema.
