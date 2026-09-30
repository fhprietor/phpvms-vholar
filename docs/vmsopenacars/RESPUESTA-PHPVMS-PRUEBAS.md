# Respuesta al pedido de vmsOpenAcars — datos para las pruebas con PIREPs reales

> **De:** equipo de phpVMS · **Para:** equipo de vmsOpenAcars
> **Fecha:** 2026-09-29 · **Ref:** `PEDIDO-PHPVMS-PRUEBAS.md` (cliente v0.9.16)
> **Estado:** parche aplicado en el código. Sin migración de base de datos.

---

## 0. Resumen

Hemos verificado el pedido punto por punto contra el código y contra la base de datos de
producción (solo lectura). El resultado:

1. **Concedido:** filtro por productor (`?source_name=`) en los listados de PIREPs. Es el único
   cambio de código necesario y ya está aplicado.
2. **Ya existía y no estaba documentado:** la paginación (`?limit=`, `?page=`) y la posibilidad de
   consultar los PIREPs de **otro piloto** (`?id=<uuid>`). Con esto el corpus es accesible hoy.
3. **Corregido en la documentación:** la autenticación. `Authorization: Bearer <key>` **no
   funciona**; hay que usar `X-API-KEY: <key>`.
4. **Dos premisas del pedido no se sostienen** con los datos: `phase` nunca se ha usado (la fase va
   en `status`) y los PIREPs de 0.8.10–0.9.10 **sí** traen posiciones de rodaje. El banco de rodaje
   no es de un vuelo: hay **39 PIREPs con rodaje en 18 aeropuertos**.
5. **Pendiente de decisión vuestra/nuestra:** pista de despegue/llegada (2.4), nombre exacto del
   campo de ruta de rodaje (2.5) y el bug de `fuel` por posición.

---

## 1. Correcciones a las premisas del pedido

### 1.1 `phase` no es el campo de las fases

La columna `acars.phase` está **vacía en el 100 % de las filas del servidor** (0 de 21 494). La fase
de cada posición llega en **`acars.status`**, con los códigos de `PirepStatus`:

| Código | `PirepStatus` | Código | `PirepStatus` |
|---|---|---|---|
| `INI` | Initiated | `ENR` | Enroute |
| `BST` | Boarding | `APR` | Approach (ICAO) |
| `PBT` | Pushback & Tow | `LDG` | Landing |
| `TXI` | Taxi | `ARR` | Arrived |
| `TOF` | Takeoff | `FIN` | On final |
| `ICL` | Initial Climb | `CHK` | Check / score (no está en el enum; lo define el cliente) |

> **Aviso:** `TXI` **agrupa rodaje de salida y de llegada**; no existen `TXI_OUT`/`TXI_IN`. Si
> necesitáis distinguirlos, decidnos y lo valoramos (sería cambio de cliente y/o de esquema).

### 1.2 Los PIREPs de 0.8.10–0.9.10 sí tienen rodaje

Verificado vuelo a vuelo (piloto de la tabla de vuestro §1). No son cero: son decenas de posiciones.

| PIREP | Versión | Posiciones | `PBT` | `TXI` |
|---|---|---|---|---|
| `Aj3N4gqn9omdoJE2` | 0.8.10 | 157 | 1 | 17 |
| `XAW2A5Gybw6BJ1NO` | 0.9.1 | 138 | 3 | 27 |
| `6EdeP0yJlWLKa1MW` | 0.9.1 | 125 | 2 | 19 |
| `E7DK47e88XdabzoL` | 0.9.1 | 204 | 4 | 20 |
| `xBV3ErB1n2wAvp2R` | 0.9.2 | 202 | 2 | 20 |
| `gB6ZDPj1QQRxEaWj` | 0.9.9 | 195 | 3 | 16 |
| `Z12naE7Ox6pxVyde` | 0.9.10 | 199 | 2 | 16 |

Y el vuelo de referencia `MNjR664PBAr25RbD` (0.9.9, 967 posiciones) tiene `PBT=3`, `TXI=45`,
`APR=48`, `FIN=87`, `CHK=239`. Las versiones ≥0.9.14 también traen `PBT`/`TXI` (comprobado en 0.9.15
y 0.9.16).

### 1.3 `state=2` no es "Arrived"

`state` es el estado de **moderación**: `0 = IN_PROGRESS`, `1 = PENDING`, `2 = ACCEPTED`,
`3 = CANCELLED`, `4 = DELETED`, `5 = DRAFT`, `6 = REJECTED`, `7 = PAUSED`. "Arrived" es el `status`
del PIREP (`status=ARR`, `status_text="Arrived"`). Es decir: **`state=2` ya era la respuesta a
vuestro 2.6**, y significa "aceptado", no "llegado".

### 1.4 El vuelo de referencia sí está en la API

`MNjR664PBAr25RbD` pertenece al **piloto 76** y ahí es el 3.º más reciente (página 1 de 5). Queda
fuera de vuestra lista porque consultáis otra cuenta. Con `?id=76` aparece a la primera.

### 1.5 `source_name` vacío: causa

De 5 982 PIREPs, 5 945 tienen `source_name` vacío. No los produce ningún cliente ACARS: son
importaciones (CrewSystem, `vholar:migrate-pireps`) y PIREPs manuales, que fijan `source` pero no
`source_name`. El marcador fiable de telemetría hoy es `acars.source`, que vale `vmsOp` en
**las 21 494 filas** del servidor.

---

## 2. Lo concedido: filtro por productor

Nuevo parámetro **`source_name`** en los listados de PIREPs, por **prefijo**:

```
GET /api/user/pireps?source_name=vmsOpenAcars
```

Devuelve todas las versiones (`vmsOpenAcars/0.9.16`, `vmsOpenACars/0.8.9`, …) y excluye el resto.
Funciona igual en:

- `GET /api/user/pireps`
- `GET /api/pireps`
- `GET /api/users/{id}/pireps`

Combinable con los filtros existentes:

```
GET /api/user/pireps?source_name=vmsOpenAcars&state=2&limit=1000&page=1
```

**Cambios aplicados:**

| Fichero | Cambio |
|---|---|
| `app/Http/Controllers/Api/UserController.php` | Nuevo filtro `source_name` (prefijo) en `pireps()` |
| `app/Repositories/PirepRepository.php` | `source_name` añadido a `$fieldSearchable` |
| `api_vms.md` | Documentados `source_name`, `limit`, `page`, `id` y la autenticación correcta |
| `tests/PIREPTest.php` | Test `test_get_user_pireps_filter_by_source_name` |

Sin migración, sin cambios de esquema y sin limpieza de caché de rutas.

---

## 3. Lo que ya existía y no estaba documentado

| Parámetro | Comportamiento |
|---|---|
| `limit` | Tamaño de página. Por defecto **20**, **sin tope máximo**. `?limit=1000` devuelve todo |
| `page` | Número de página. La respuesta trae `meta.pagination` con `total` y `last_page` |
| `id` | Piloto a consultar. **`?id=<uuid>` devuelve los PIREPs de otro piloto** (sin comprobación de propiedad) |
| `state` | Filtro por estado de moderación |

> El `filter` de la librería de repositorios **no** es un `WHERE`: selecciona columnas. Para filtrar
> por productor usad `?source_name=`.
>
> `?searchFields=source_name` ya no rompe (antes devolvía 503), pero es innecesario: usad
> `source_name`.

### 3.1 Autenticación (importante)

Verificado en producción:

| Cabecera | Resultado |
|---|---|
| `X-API-KEY: <api_key>` | **200** |
| `Authorization: <api_key>` (sin `Bearer`) | **200** |
| `Authorization: Bearer <api_key>` | **401** |

La documentación decía `Bearer`; era incorrecto y ya está corregido. **Usad `X-API-KEY`.**

---

## 4. Corpus disponible hoy

| Métrica | Valor |
|---|---|
| PIREPs vivos con traza de vuelo (`type=0`) | **40**, todos producidos por vmsOpenAcars |
| De ellos, **con posiciones de rodaje** (`PBT`/`TXI`) | **39** |
| Pilotos | **8** (`1, 10, 17, 25, 27, 34, 55, 76`) |
| Aeropuertos distintos | **18** |
| Versiones presentes | 0.8.9 ×2 · 0.8.10 ×1 · 0.9.0 ×1 · 0.9.1 ×6 · 0.9.2 ×19 · 0.9.9 ×3 · 0.9.10 ×2 · 0.9.15 ×3 · 0.9.16 ×3 |

> **Cuidado con los huérfanos:** hay **6 conjuntos de filas `acars` cuyo PIREP ya no existe** (5 de
> ellos con traza de vuelo; las filas de `acars` no se borran en cascada). Aparecen al consultar la
> tabla `acars` pero no al pedir el PIREP por la API. Si extraéis el corpus PIREP a PIREP, no os
> afectan.

### 4.1 Receta para reconstruir el corpus

```bash
KEY="<api_key>"
BASE="https://<vuestra-va>"

# 1) Listar los PIREPs de un piloto, todos los de vmsOpenAcars, sin tope
curl -s -H "X-API-KEY: $KEY" \
  "$BASE/api/user/pireps?id=<uuid-piloto>&source_name=vmsOpenAcars&limit=1000" \
  | jq '.data[] | {id, source_name, score, state, created_at}'

# 2) Repetir para cada piloto con PIREPs
#    1, 10, 17, 25, 27, 34, 55, 76  (el listado es por piloto; no hay endpoint global)

# 3) Traza completa de cada PIREP (todas las posiciones, sin paginar)
curl -s -H "X-API-KEY: $KEY" "$BASE/api/pireps/<pirep_id>/acars/position" \
  | jq '.data[] | {status, lat, lon, altitude_agl, gs, ias, heading, vs, log, sim_time}'

# 4) Detalle: campos personalizados, comentarios y estado de moderación (ruta pública)
curl -s "$BASE/api/pireps/<pirep_id>" \
  | jq '.data | {state, status, status_text, fields, comments}'
```

Filtrad el rodaje en cliente por `status == "PBT"` o `status == "TXI"`.

---

## 5. Respuesta punto por punto

### 2.1 Poder pedir el corpus por productor, sin el tope de 20

**Concedido.** `?source_name=` (nuevo) + `?limit=` y `?page=` (ya existían). No hay endpoint global
de PIREPs: el listado es por piloto, así que hay que iterar los 8 pilotos con `?id=<uuid>`. Si
preferís un endpoint global con filtro por productor y rango de fechas, decidlo y lo estudiamos.

### 2.2 `source_name` en todos los PIREPs

**Aceptado como tarea de datos, no de cliente.** Los vacíos son importaciones y PIREPs manuales.
Podemos hacer un *backfill* a partir de `source`/`acars.source`, pero necesitamos que defináis qué
valor queréis para los históricos (p. ej. `CrewSystem/import`). Mientras tanto, para excluir con
criterio usad `acars.source` o la presencia de telemetría, no `source_name`.

### 2.3 Las trazas de las versiones nuevas, completas

**Ya se guardan enteras y se recuperan sin tope.** `GET /api/pireps/{id}/acars/position` devuelve
todas las posiciones de golpe (967 en `MNjR`, verificado). No hace falta nada vuestro para tener
rodaje: ya está en `status` desde 0.8.10. Lo único que pedimos es que **no uséis `phase`** y que
tengáis en cuenta que `TXI` mezcla salida y llegada.

### 2.4 La pista usada (despegue y llegada)

**Requiere cambio, y hay que decidir cuál.** La tabla `simbrief` está **vacía (0 filas)**: nunca se
ha adjuntado un OFP a un PIREP, porque el cliente no usa la integración SimBrief de phpVMS. Por eso
`simbrief` sale vacío, y no hay columnas `dpt_runway`/`arr_runway`.

Opciones, de menos a más invasiva:

1. **Campos personalizados (recomendado, cero código):** creamos en admin un `PirepField` y vosotros
   lo enviáis en `fields` en `prefile`/`update`. Ya existen campos así en producción (los
   `Network *`). La convención que ya usa DisposableBasic es `departure-runway` / `arrival-runway`.
2. **Columnas nuevas** `dpt_runway` / `arr_runway` en `pireps` (requiere migración).
3. **Poblar `simbrief`**: solo viable si enviáis el OFP a phpVMS, que hoy no ocurre.

Decidnos nombres exactos y vamos por la opción 1 salvo que queráis otra cosa.

### 2.5 La ruta de rodaje que declara el piloto

**Concedido y ya soportado.** No hace falta cambio de servidor: creamos el campo (p. ej.
`Taxi Route`) y vosotros lo enviáis en `fields` al filear. Confirmadnos el **nombre exacto** del
campo (el `slug` se deriva de él) y lo dejamos creado.

### 2.6 El estado de moderación del PIREP

**Parcialmente disponible:**

| Dato | Dónde | ¿En la API? |
|---|---|---|
| Estado de moderación | `pireps.state` | Sí, en el detalle y el listado |
| Motivo del rechazo | `pirep_comments` | Sí, en el detalle (`comments`) — **no** en el listado |
| Quién y cuándo cambió el estado | `activity_log` | **No**, sin endpoint |
| Correcciones de `arr_airport_id` | `activity_log` | **No** (y hoy no hay ninguna registrada) |

Hoy no hay PIREPs rechazados en la base; hay 2 casos de `REJECTED → ACCEPTED` (p. ej.
`2am1prA2vwr2L6vn`, con el motivo "Flights must be operated online!…" en un comentario). Si
necesitáis el historial de moderación en la API, lo exponemos en el detalle; decidnos qué campos.

---

## 6. Bug conocido: `fuel` por posición no se guarda

`fuel` **no está** en `$fillable` de `App\Models\Acars` (sí en `$casts` y en la doc antigua), así que
se descarta en silencio: **0 filas con `fuel`** en todo el servidor. Las demás columnas de posición
(`ias`, `gforce`, `pitch`, `bank`, `status`, `source`, `log`) sí se guardan.

Es un arreglo de una línea, pero cambia datos en producción, así que **no lo hemos aplicado**:
decidnos si lo activamos.

---

## 7. Lo que sigue siendo cosa vuestra (vuestro §3)

Confirmado: `POST /api/pireps/{id}/acars/logs` acepta texto en `log` y los avisos del RAAS se
guardarán ahí, con lo que quedarán auditables desde el PIREP. Cuando empecéis a enviarlos, avisadnos
y los verificamos. Aprovechamos para recordar que el desglose del score que ya enviáis en las filas
`CHK` (`log="SC:ov=0,lt=0,…"`) también queda almacenado, no solo en el log local del piloto.

---

*vmsOpenAcars — cualquier duda sobre estos datos, responded a este documento.*
