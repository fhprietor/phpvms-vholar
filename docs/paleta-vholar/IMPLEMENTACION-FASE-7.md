# Implementación · Fase 7 · La home pasa al tema oscuro

**Fecha:** 2 de octubre de 2026
**Alcance:** eliminar las islas claras de `home.blade.php`, los hacks `[style*=…]` y los últimos fallos de contraste
**Archivos tocados:** 4
**Decisión de diseño:** oscurecerlo todo (elegida por el usuario)

---

## 1. Qué había

La home era **el único sitio claro de todo el producto**: una banda de 4 tarjetas de estadísticas sobre fondo `#F8F5FA` y una banda de CTA sobre `#DDD3E4`, dentro de un tema que es oscuro en todas partes (`data-bs-theme="dark"`).

Eso obligaba a mantener **una escala de color invertida** solo para esas dos zonas (texto oscuro sobre fondo claro) y a sostenerla con selectores por atributo:

```css
body.home-page .card[style*="background-color"] { background-color: #DDD3E4 !important; }
```

---

## 2. Lo que se cambió

### 2.1 Las dos zonas claras, a la escalera oscura

| Elemento | Antes | Ahora |
|---|---|---|
| Banda de estadísticas | `#F8F5FA` | `var(--vh-surface)` |
| Tarjeta de estadística | `#F3EFF5` | `var(--vh-surface-2)` |
| Etiqueta de la tarjeta | `#5a4a66` | `var(--vh-text-muted)` |
| Cifra de la tarjeta | `#2c1e35` | `var(--vh-text)` |
| Detalle mensual | `#7b6a87` | `var(--vh-text-muted)` |
| Banda de CTA | `#DDD3E4` | `var(--vh-surface)` |
| Título de la CTA | `#412c4d` | `var(--vh-text)` |
| Borde de las tarjetas | `rgba(65,44,77,0.15)` | `var(--vh-border)` |

La banda usa `--vh-surface` y las tarjetas `--vh-surface-2`: las tarjetas quedan **elevadas** sobre la banda en lugar de hundidas, que es el mismo criterio que el resto del tema.

`home.blade.php` ya **no tiene ni un solo hex**: solo tokens.

### 2.2 El título de la CTA: un bug que introdujo este mismo cambio

El título de la CTA era `style="color: var(--vh-primary)"` — correcto cuando la banda era clara (púrpura oscuro sobre lila), pero **invisible** en cuanto la banda pasó a oscura (contraste ~1.2:1). Se detectó al revisar el resultado del reemplazo, no en producción, y se corrigió a `var(--vh-text)`.

Es el mismo patrón que ya apareció en las fases 3 a 5: **cambiar el fondo invalida los colores de texto que dependían de él.** Por eso cada zona clara que se oscurece hay que revisar el texto que contenía, no solo el fondo.

### 2.3 Los hacks `[style*=…]`, eliminados

| Regla | Qué hacía |
|---|---|
| `body.home-page .card[style*="background-color"]` | **Hacía daño.** Sobrescribía *cualquier* tarjeta con background inline. Las 4 tarjetas de estadísticas declaraban `#F3EFF5` y recibían `#DDD3E4`: **su color declarado nunca se aplicó.** |
| 3 reglas sobre `[style*="background-color: #f8f9fa"]` | **Código muerto.** Apuntaban a un estilo inline que ya no existe en ninguna vista. |

`theme.css` pasa de 4 reglas por atributo a **0**. Ya nada depende de que el HTML conserve un estilo inline exacto.

---

## 3. Los fallos de contraste

Eran los últimos 2 del análisis… y apareció un tercero al revisar:

| Elemento | Antes | Ahora |
|---|---|---|
| Detalle mensual de la tarjeta | `#7b6a87` sobre `#F3EFF5` → **4.34:1** | `#A79FB2` sobre `#28212F` → **6.11:1** |
| Texto del banner de cookies | `#838391` sobre `#edeff5` → **3.25:1** | `#A79FB2` sobre `#28212F` → **6.11:1** |
| `.text-muted` en tarjeta clara | `#6c757d` sobre `#f8f9fa` → **4.45:1** | regla eliminada (era código muerto) |

**El banner de cookies no estaba en el análisis original.** El widget de cookieconsent se configuraba con un fondo claro `#edeff5` y texto `#838391`, que da **3.25:1** — un fallo real que llevaba ahí desde el principio, escondido porque mi medición original solo miraba el tema, no los widgets de terceros configurados por JS. Se oscureció a `#28212F` / `#A79FB2` (6.11:1), que además elimina la última superficie clara del producto.

---

## 4. Otros restos sueltos

| Elemento | Antes | Ahora | Motivo |
|---|---|---|---|
| Pista del donut (admin, SVG) | `stroke="#e9ecef"` | `stroke="#3A3142"` | El carril casi blanco competía con el arco de progreso. Literal porque `var()` no es fiable en atributos SVG. |
| Texto del popup de `/fleetmap` | `#e0e8ff` | `#EDEAF1` | Último celeste suelto, en una cadena JS. |

---

## 5. Verificación

### Resultado global de las siete fases

| Métrica | Al empezar | Ahora | Δ |
|---|---|---|---|
| **Colores hex únicos** | 116 | **40** | **−76** |
| **Declaraciones de color** | 527 | **121** | **−406** |
| **Fallos de contraste (texto)** | 23 | **0** | **−23** |
| Archivos que declaran tokens | 3 | **1** | −2 |
| Tokens con consumidor | — | **26 / 26** | sin muertos |
| Reglas `[style*=…]` | 4 | **0** | −4 |

### Los 40 colores que quedan

| Categoría | Nº | Qué son |
|---|---|---|
| Definiciones de token | 20 | La paleta. En un solo archivo. |
| Referencias en comentarios | 7 | Documentan el valor original sustituido, para poder auditar cada cambio. |
| Marcas de terceros | 4 | IVAO, VATSIM, Discord, POSCON. Repintarlos sería incorrecto. |
| Extremos oscuros del gradiente de peligro | 4 | El texto blanco necesita fondo oscuro. |
| Literales de JS | 3 | Leaflet y la escala categórica de rutas: `var()` no se resuelve ahí. |
| Texto oscuro de las etiquetas de mapa | 2 | `#0d0b18` sobre la plata institucional. Contraste 12:1. |

**Ninguno es deuda.**

### Comprobaciones automáticas

- 16 vistas Blade compiladas sin errores.
- Llaves CSS: `tokens.css` 1/1 · `theme.css` 210/210 · `vholar-admin.css` 67/67.
- **26 / 26 tokens con consumidor.** Cero declaraciones muertas.
- **0 referencias a tokens sin definir** en todo el tema y el admin.
- `home.blade.php`: **0 hex crudos**.
- **Permisos**: 0 archivos con grupo `frank`, 0 no escribibles por `www-data` (ver [`OPERACION-PERMISOS.md`](../OPERACION-PERMISOS.md)).
- Sitio: peticiones 200, 0 errores nuevos en el log de Laravel.

---

## 6. Reversión

```
/tmp/coloranalysis/backup6/home.blade.php        (home antes de la fase 7)
/tmp/coloranalysis/backup6/theme-pre7.css        (theme.css antes de la fase 7)
/tmp/coloranalysis/backup6/app.blade.php         (banner de cookies)
```

---

## 7. Cómo comprobarlo

1. **`/`** — la home completa en oscuro: la banda de estadísticas y las 4 tarjetas ahora son tarjetas oscuras de marca, y el cierre de página (`¿Estás listo para VHOLAR?`) es una banda oscura con el botón púrpura.
2. **`/`** — el detalle mensual bajo cada cifra (`12 flights this month`) ya se lee con holgura: antes era el peor contraste del tema.
3. **Primera visita o ventana privada** — el banner de cookies ahora es oscuro con texto legible; antes era claro con gris a 3.25:1.
4. **Admin → `/admin/dassignments`** — el gráfico de dona: el carril es sutil y el arco verde destaca, en vez de competir con un carril casi blanco.
5. **`/fleetmap`** — al pulsar una etiqueta de aeropuerto, el texto del popup ya es del color del tema.

---

## 8. Cierre

Con esta fase **el tema no tiene ningún fallo de contraste** y el color está resuelto de extremo a extremo:

- Una paleta institucional extraída de los assets reales.
- **Un solo archivo** donde se declara, que cargan las tres superficies.
- Tres familias semánticas con reglas explícitas y contraste medido.
- Cero colores de la paleta escritos a mano dentro de una vista.
- Cero tokens declarados sin consumidor.
- Cero reglas que dependan de un estilo inline exacto.

Lo que queda (marcas de terceros, literales de canvas, referencias en comentarios) es una decisión, no una deuda.
