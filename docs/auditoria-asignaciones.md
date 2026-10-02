# Auditoria de asignaciones (DisposableSpecial)

Las asignaciones mensuales se guardan en `disposable_assignments`
(`month_assignments` es solo una vista de lectura) y **cada cambio queda
registrado** en `activity_log` con `log_name = 'assignments'`.

## Eventos registrados

| `event` | Cuando | `properties` |
|---|---|---|
| `assignments_generated` | generacion mensual (boton del admin o cron) | `year`, `month`, `scope` (`single_pilot`/`all_active_pilots`), `target_user_id`, `target_ident`, `reset`, `trigger`, `assignments_before`, `assignments_after`, `assignments_deleted`, `delta` |
| `assignment_updated` | se cambia el vuelo de una asignacion | `assignment_id`, `pilot_id`, `year`, `month`, `order`, `old_flight_id`, `old_flight`, `new_flight_id`, `new_flight` |
| `assignment_added` | se anade una asignacion | `assignment_id`, `pilot_id`, `year`, `month`, `order`, `flight_id`, `flight` |
| `assignment_deleted` | se elimina una asignacion | `assignment_id`, `pilot_id`, `year`, `month`, `order`, `flight_id`, `flight` |

- **`causer`**: el usuario que hizo el cambio. Si lo disparo el **cron** queda
  `NULL` (automatico).
- **`subject`**: la asignacion afectada. En `assignments_generated` no hay
  subject (es una generacion en lote).
- **`trigger`**: `web` si llego por HTTP, `console` si fue por consola/cron.
- **`description`**: texto legible en espanol (p. ej. "Asignaciones mensuales
  generadas para todos los pilotos activos").

> Nota tecnica: la instalacion desactiva el activity log por defecto
> (`app/Providers/AppServiceProvider.php`) y solo lo reactiva en el grupo
> `/admin` del core (middleware `EnableActivityLogging`). Las rutas de
> asignaciones viven en su propio grupo, asi que
> `DS_AssignmentController::logAssignmentActivity()` activa el log de forma
> puntual y **restaura el estado previo** al terminar. Asi la auditoria cubre
> tambien el cron.

## Consultas utiles

```sql
-- Quien ha tocado las asignaciones (ultimos 50 cambios)
SELECT a.created_at, u.pilot_id, u.name, a.event, a.description, a.properties
FROM activity_log a
LEFT JOIN users u ON u.id = a.causer_id
WHERE a.log_name = 'assignments'
ORDER BY a.id DESC
LIMIT 50;

-- Quien genero las asignaciones de un mes concreto
SELECT created_at, causer_id, description, properties
FROM activity_log
WHERE log_name = 'assignments' AND event = 'assignments_generated'
ORDER BY id DESC;

-- Historial de una asignacion concreta
SELECT created_at, event, causer_id, description
FROM activity_log
WHERE log_name = 'assignments' AND subject_id = 4665
ORDER BY id;

-- Solo lo automatico (cron)
SELECT created_at, description, properties
FROM activity_log
WHERE log_name = 'assignments' AND causer_id IS NULL
ORDER BY id DESC;
```

## Historico y entradas de backfill

La generacion de **octubre de 2026** (85 filas, `2026-10-01 09:07:46-47`) fue
anterior a esta instrumentacion, asi que se anoto **a posteriori** con una
entrada marcada como inferida:

| Campo | Valor |
|---|---|
| `event` | `assignments_generated_backfill` |
| `causer_id` | `1` (VHR001) |
| `created_at` | cuando se anoto (2026-10-02 01:12) |
| `properties.generated_at` | `2026-10-01 09:07:47` (cuando ocurrio de verdad) |
| `properties.inferred` | `true` |
| `properties.evidence` | access log de nginx (09:07:47, Edge 154) + `users.lastlogin_at` (VHR001, 09:06:40) |

**Convencion**: los hechos anteriores a la instrumentacion se registran con
`event = '*_backfill'` y `properties.inferred = true`; la fecha real del hecho va
en `properties.generated_at`, porque el `created_at` de la fila es cuando se
anoto. Para separarlas de las reales:
`WHERE event NOT LIKE '%\_backfill'`.
