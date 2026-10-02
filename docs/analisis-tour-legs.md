# Analisis: desacoplar los tramos de tour de `route_code`

Fecha: 2026-10-02. Estado: **propuesta — nada implementado todavia**.

## El problema

En DisposableSpecial un "tramo" de tour **es un vuelo normal** marcado con
`route_code = <codigo del tour>` y `route_leg = N`; la relacion es
`DS_Tour::legs()` = `hasMany(Flight::class, 'route_code', 'tour_code')`. Consecuencias:

- Hay que tocar `route_code`/`route_leg` (y `owner_type`/`owner_id`) de vuelos de la
  programacion.
- En phpVMS esos vuelos pasan a ser "la ruta VHR26, tramo N" (agrupacion nativa de rutas
  multietapa): su `ident` se convierte en `VHR1265/C.VHR26/L.1` y salen agrupados en los
  listados.
- El PIREP tiene que heredar `route_code`/`route_leg` del vuelo para que el tour lo cuente.

## Inventario del acoplamiento (72 coincidencias; ~55 son de tours)

| Zona | Fichero | Que hace |
|---|---|---|
| Relacion | `Models/DS_Tour.php:58` | `legs()` = vuelos con `route_code` = codigo del tour |
| Matching de PIREP | `ds_helpers.php:285` `DS_IsTourLegFlown()` | busca el PIREP del piloto por `route_code` + `route_leg` + aeropuertos + estado |
| Premios | `Awards/DSpecial_Tour.php`, `Awards/DSpecial_AirlineTour.php` | `pirep_where` con `route_code` y comparacion de secuencias de `route_leg` |
| Widget | `Widgets/TourProgress.php` | cuenta tramos volados agrupando PIREPs por `route_code` |
| Admin | `Http/Controllers/DS_TourController.php` | informe, alta (`store`), `leg_actions`, `DeleteLegs` (pone `route_code` a null), `LegOwnership`, `ActivateLegs`, `ManageTourSubfleets`, `remove_from_pirep` |
| Cron | `Services/DS_CronServices.php:123,143,157,172` | "posee"/oculta/activa/desactiva tramos por `route_code` |
| Vistas | `Resources/views/admin/tours.blade.php:130-134`, `Resources/views/tours/legs_table.blade.php:14,17,45,52` y los overrides del tema vholar | listan y numeran tramos con `route_leg` |
| API | `Http/Controllers/DS_ApiController.php:104-105` | cuenta pilotos por `route_code` y devuelve `route_code`/`route_leg` por tramo |
| Auxiliares | `ds_helpers.php:185,196` (`DS_GetTourName`, `DS_GetTourFPLRemark`) | dado el `route_code` de un vuelo, devuelven nombre/remark del tour |
| Asignaciones | `Http/Controllers/DS_AssignmentController.php:532` | selecciona vuelos de tours por `route_code` |
| **Ajeno (no tocar)** | `DS_FreeFlightController:166,173,245`, `Expense_Airport:77`, `DS_CronServices:61`, migracion 2023 | usan `route_code` para "PF" (vuelos personales) y "AJ" (low cost) |

## Propuesta

Tabla propia **`disposable_tour_flights`** (`tour_id`, `flight_id`, `leg`, timestamps;
unicos por `tour_id + flight_id` y `tour_id + leg`), y:

1. `DS_Tour::legs()` -> `belongsToMany(Flight::class, 'disposable_tour_flights')->withPivot('leg')`.
   Las vistas siguen usando `$tour->legs`; el numero de tramo pasa a `$leg->pivot->leg`.
2. **Matching del PIREP por `flight_id`** (que el PIREP ya guarda) en lugar de por
   `route_code`/`route_leg`, **con fallback** a la comparacion antigua para no romper los
   PIREPs manuales en los que el piloto escribe codigo y tramo a mano.
3. Las acciones de admin y el cron pasan a **attach/detach en la tabla**; `normalize`
   = detach. No se toca `route_code`, `route_leg` ni `owner_*` de ningun vuelo.
4. Los vuelos vuelven a su estado normal: se limpia `route_code`/`route_leg`/`owner_*` de
   los tramos actuales (el 1265 deja de mostrar `VHR1265/C.VHR26/L.1`) y su programacion
   deja de estar agrupada bajo el tour.
5. Compatibilidad de la API (`dsapi/tours`): se mantienen los campos `route_code` y
   `route_leg` en la respuesta, alimentados desde el pivote.

**Bonus importante**: con el pivote **varios vuelos pueden compartir el mismo numero de
tramo**, que es justo el modelo de la web de referencia ("Eligible routes: elige una de
estas"). Hoy el modulo no puede expresarlo.

## Migracion y verificacion

1. Migracion de esquema + comando de datos que importe los tramos actuales al pivote
   (ANDES: 2, VHR26: 18) y despues limpie `route_code`/`route_leg`/`owner_*` de esos vuelos.
2. A verificar (no hay tests en el modulo): pagina publica del tour (18 tramos), progreso
   por piloto, informe de admin (`dtour_admin`), acciones de tramos, widget
   `TourProgress`, premios de tour, `dsapi/tours`, y que un PIREP siga contando el tramo
   (ACARS y manual).
3. Es un parche local mas del modulo: habria que regenerar
   `patches/DisposableSpecial-d1d776c.patch` y **es aportable a upstream**.

## Riesgos

- El modulo no tiene tests: la verificacion es manual (puntos de arriba).
- Toca el corazon del tour (el matching de los premios). Un fallo haria que los tramos no
  cuenten. Se mitiga con el fallback antiguo y probando con los 2 tours existentes.
- Es divergencia adicional frente al autor (aunque candidata a PR).

## Plan B (sin cirugia del modulo)

Dejar el mecanismo actual y limitarse a lo cosmetico: mostrar el `ident` limpio en la
ficha del vuelo (`VHR1265` en vez de `VHR1265/C.VHR26/L.1`) y aceptar el agrupamiento bajo
VHR26 mientras dure el tour. Coste: minutos; riesgo: cero.
