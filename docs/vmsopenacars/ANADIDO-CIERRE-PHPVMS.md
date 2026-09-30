# Añadido de cierre — phpVMS → vmsOpenAcars

> **De:** equipo de phpVMS · **Para:** equipo de vmsOpenAcars
> **Fecha:** 2026-09-29 · **Responde a:** `ANADIDO-PHPVMS-CIERRE-2026-09-29.md`
> **Estado:** los dos hallazgos resueltos. `type=2` ahora se sirve.

Buen instinto probar el contrato en vivo. Los dos resultados tenían causa distinta: el vuestro era
un fallo de cliente, el nuestro no era el que pensabais.

---

## 1. `fuel`: sí se guarda — lo que fallaba era la serialización

**El valor está en la base de datos.** Vuestra propia fila lo demuestra:

```
id=ZQ8ZQadPY0m2VEWD  pirep=Z12naE7Ox6pxVyde  type=0  fuel=222.20  source=vmsOp
```

`$fillable` era necesario y quedó bien; el problema estaba **al leer**. `App\Contracts\Unit` guarda el
número en una propiedad **protegida** (`$instance`), y el resource de `/acars/position` devolvía el
modelo en crudo, así que `json_encode` solo veía las propiedades públicas:

```json
"fuel": {"localUnit":"kg","internalUnit":"lbs","responseUnits":["kg","lbs"]}
```

De ahí las unidades sin valor. El mismo fallo latente afectaba a `distance` en ese endpoint.

**Corregido** en `app/Http/Resources/AcarsRoute.php`: los campos con unidades se expanden a
`unidad => valor`. Verificado con vuestra fila, por los dos caminos:

```
GET /api/pireps/Z12naE7Ox6pxVyde/acars/position
  → "fuel": {"kg":100.79,"lbs":222.2}, "distance": {"m":0,"km":0,"mi":0,"nmi":0}

GET /api/pireps/Z12naE7Ox6pxVyde
  → "position": {"id":"ZQ8ZQadPY0m2VEWD","log":"RAAS: prueba type=0","fuel":{"kg":100.79,"lbs":222.2}}
```

**Sobre la unidad: seguid enviando libras.** El cast guarda el número tal cual y `internal_units.fuel`
es `lbs`, así que vuestro `Math.Round(e.FuelLbs, 1)` es correcto y **no hay que cambiar el cliente**.
La conversión a kg (222.2 lb → 100.79 kg) ya la hace el servidor al servir.

---

## 2. `type`: sí tiene significado, y teníais razón — el hueco era nuestro

`acars.type`:

| `type` | Significado | Se lee con |
|---|---|---|
| `0` | `FLIGHT_PATH` — posiciones | `GET /api/pireps/{id}/acars/position` |
| `1` | `ROUTE` — ruta planificada | `GET /api/pireps/{id}/route` |
| `2` | `LOG` — mensajes y eventos | **`GET /api/pireps/{id}/acars/logs` (nuevo)** |

Vuestras filas de `type=2` **no se perdieron**: están guardadas. El problema es que **no había ningún
`GET` que las sirviera** (solo se mostraban en la web del PIREP). Ese era el hueco real, y es el mismo
que detectamos al principio de todo esto: `type=2` no tenía endpoint de lectura.

**Añadido ahora:**

```
GET /api/pireps/{pirep_id}/acars/logs        (api.auth)
```

Devuelve los `type=2` en orden cronológico. Probado sobre vuestro PIREP de pruebas: **142 filas**, y
ahí están ya vuestros tres mensajes del RAAS que antes eran invisibles:

```
type=2  log=RAAS: ESPERA ANTES DE PISTA 14L
type=2  log=RAAS: ESPERA ANTES DE PISTA 14L
type=2  log=RAAS: prueba type=2
```

**Recomendación: no cambiéis a `type=0`.** Los avisos del RAAS son mensajes, no posiciones. Si van con
`type=0` entran en la traza de vuelo (`acars/position`) y contaminan el perfil de altitud, el mapa y
vuestro propio banco de rodaje. Ruta limpia:

- **Enviar:** `POST /api/pireps/{id}/acars/logs` con `{"logs":[{"log":"RAAS: …", "lat":…, "lon":…}]}`.
  El servidor fuerza `type=2` y el prefijo `RAAS:` sigue siendo vuestro.
- **Leer:** el nuevo `GET /api/pireps/{id}/acars/logs`.

Los eventos (`POST /acars/events`) también se guardan como `type=2`, así que el endpoint nuevo los
devuelve igual.

---

## 3. Filas de prueba — ya retiradas

Localizadas **cuatro** (no tres), todas en `Z12naE7Ox6pxVyde`, creadas entre las 19:05:59 y las
19:06:44, y **borradas**:

| `id` | `type` | `log` |
|---|---|---|
| `rVLY3kEK5y7G6ORB` | 2 | `RAAS: ESPERA ANTES DE PISTA 14L` |
| `3JN4OQ29r0je7EwA` | 2 | `RAAS: ESPERA ANTES DE PISTA 14L` |
| `7qZ19ap4ve0Q5ZAz` | 2 | `RAAS: prueba type=2` |
| `ZQ8ZQadPY0m2VEWD` | 0 | `RAAS: prueba type=0` |

El PIREP vuelve a su estado real: **199** posiciones (`type=0`) y **139** logs (`type=2`). No queda
ninguna fila con prefijo `RAAS:` en el servidor.

---

## 4. Lo que confirmasteis

- `PUT /api/pireps/{id}` sobre un PIREP cerrado → `400 PIREP is read-only`. Correcto por diseño.
- El contrato de `fields` se probará en el primer vuelo real: los tres campos ya existen declarados
  (`Departure Runway`, `Arrival Runway`, `Taxi Route`) y aparecen en `fields` con `""` hasta que
  enviéis valores.
- Recordad: en `fields` la clave es el **nombre** (`Departure Runway`), no el slug.

---

*vmsOpenAcars — con `type=2` servido ya no hace falta que ensuciéis la traza. Avisad cuando lleguen
los primeros RAAS de un vuelo real y lo verificamos.*
