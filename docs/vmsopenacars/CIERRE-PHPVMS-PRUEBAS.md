# Cierre — phpVMS → vmsOpenAcars

> **De:** equipo de phpVMS · **Para:** equipo de vmsOpenAcars
> **Fecha:** 2026-09-29 · **Responde a:** `RESPUESTA-PHPVMS-PRUEBAS-2026-09-29.md`
> **Estado:** los tres puntos que dejasteis abiertos ya están aplicados en la instalación.

Gracias por la verificación cruzada. Damos por cerrado el asunto con esto.

---

## 1. Hecho en nuestro lado

### 1.1 Campos personalizados creados

Ya existen en `pirep_fields`, con `pirep_source = ACARS` y `required = false`:

| Campo (`name`) | `slug` | Contenido |
|---|---|---|
| `Departure Runway` | `departure-runway` | Pista de despegue |
| `Arrival Runway` | `arrival-runway` | Pista de llegada |
| `Taxi Route` | `taxi-route` | La autorización de rodaje **tal cual la tecleó el piloto** |

Dos detalles de implementación que os afectan:

1. **En `fields` mandad el nombre, no el slug.** La API guarda la **clave** que enviáis como *nombre*
   del campo y deriva el `slug` con `str_slug()`. Enviad
   `"Departure Runway": "14R"` (no `"departure-runway"`), para que el nombre mostrado sea el correcto.
   Si mandáis el slug también casa por `slug`, pero el nombre que se pinta será el slug.
2. **Desde ya, todo PIREP ACARS devuelve las tres claves vacías** en `fields` hasta que enviéis
   valores. Es el comportamiento normal de los campos declarados (se rellenan con `""` si no hay
   valor), así que **no lo interpretéis como error**:

```json
"fields": {
  "Network Online": "IVAO",
  "Departure Runway": "",
  "Arrival Runway": "",
  "Taxi Route": ""
}
```

Podíais haber empezado a enviarlos sin esto (la ruta ACARS no consulta las definiciones), pero
declararlos los hace visibles en el detalle del PIREP.

### 1.2 `fuel` por posición: activado

Añadido a `$fillable` de `App\Models\Acars`. Desde ahora **se persiste** el combustible de cada
posición si lo enviáis. Hoy la columna sigue a 0 filas porque no lo mandáis; cuando empecéis, se
guardará. El cast a unidades (`FuelCast`) ya estaba y funciona.

### 1.3 Backfill de `source_name` aplicado

Discriminador usado, verificado antes de tocar nada: los PIREPs con `source_name` vacío van del
2022-10-03 al 2026-09-15, y ninguno tiene telemetría ACARS (los 40 que la tienen ya traían su
`source_name`). Reparto por `source`:

| `source` | Valor asignado | PIREPs |
|---|---|---|
| `1` (ACARS) → importación | `CrewSystem/import` | 5 718 |
| `0` (MANUAL) → panel | `manual` | 227 |

**Vacíos restantes: 0.** El reparto final queda:
`CrewSystem/import` 5 718 · `manual` 227 · `vmsOpenACars/0.9.2` 19 · `vmsOpenACars/0.9.1` 6 · resto de
versiones de vmsOpenACars.

Ya no hay que inferir: podéis excluir con `?source_name=vmsOpenACars` (o usar `manual` y
`CrewSystem/import` para lo contrario). No se tocó `updated_at` de ningún PIREP.

---

## 2. Acordado: nada pendiente por nuestra parte

- **2.1 Endpoint global:** no se hace. Con `?id=` + `?source_name=` + `?limit=` os vale para 39.
- **2.6 `activity_log`:** no se expone. Con `state` y `comments` en el detalle, cerrado.
- **`TXI` mezclado:** no se cambia el esquema. Vuestra heurística (`PBT`/`TXI` antes de `TOF` =
  salida; después de `LDG` = llegada) es correcta.

---

## 3. Pendiente de vosotros

1. Empezar a enviar `Departure Runway`, `Arrival Runway` y `Taxi Route` en `prefile`/`update`.
2. Enviar `fuel` en las posiciones cuando queráis (ya se guarda).
3. Los avisos del RAAS por `acars/logs`, con el prefijo propio que comentasteis para distinguirlos de
   las `CHK`. Avisadnos cuando empiecen a llegar y los verificamos.

---

## 4. Recordatorio operativo

- **Auth:** `X-API-KEY: <api_key>`. `Authorization: Bearer <key>` devuelve 401.
- **Filtros:** `?source_name=` (prefijo), `?limit=` (sin tope), `?page=`, `?id=<uuid>`.
- La documentación de [api_vms.md](../api_vms.md) ya refleja los parámetros nuevos, la auth correcta
  y el estado de `phase`/`fuel`.

---

*vmsOpenAcars — quedamos a la espera de los primeros campos y de los avisos del RAAS.*
