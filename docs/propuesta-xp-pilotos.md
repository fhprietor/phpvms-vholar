# Propuesta: puntuación de piloto (XP) para ascensos y homologación de tipos

Fecha: 2026-11-16. Estado: **propuesta** (nada implementado).

## Lo que hay hoy

- **Rangos**: 7 (Cadet 0 h → TRI/TRE 3.000 h), todos con `auto_promote = true`. El ascenso
  depende **solo de las horas**; el `score_gate` de la tabla `ranks` está a `false` en todos.
- **Homologaciones**: 7 typeratings (A320 Series, A330, A350, B767, B737s, B777, Cargo) con
  pivotes `typerating_user` (11 asignaciones) y `typerating_subfleet` (21).
- **Datos disponibles**: 14.554 h y 6.174 vuelos; 229 PIREPs con `score` (media 80,5);
  análisis de aterrizaje en ~43-50 vuelos pesados; bonificaciones y penalizaciones ya parseadas
  por `FlightAnalysisHelper`.
- **El XP del CrewSystem NO se importó**: los 5.718 PIREPs importados solo traen la nota
  "ACARS flight." y sin score. El XP antiguo está perdido; habría que **reconstruirlo**.

## La idea

Un **XP acumulado** como segunda moneda de progreso, en paralelo a las horas:

- **Ascensos**: la hora sigue siendo el suelo; el XP pasa a ser **puerta** (horas **y** XP).
- **Homologación de tipos**: el XP abre el typerating, con un requisito adicional de **horas en
  la familia** para que no se compre con vuelos cortos.
- **Trazabilidad**: cada punto viene de un PIREP concreto y se puede auditar.

## Fórmula propuesta (por vuelo)

```
base      = block_time_min x 1                     (60 XP/hora de bloque)
calidad   = (score ?? 80) / 80                     (media actual 80,5 -> factor ~1)
acars     = bonificaciones x 5 - penalizaciones x 5
aterrizaje= -60..-200 fpm: +10 | -200..-400: +5 | >-600: 0 | mas duro: -5
eje       = <5 ft: +5 | <15 ft: +2
extras    = tour/asignacion completada +50 | primer vuelo en un tipo +25
XP vuelo  = min(300, base x calidad + acars + aterrizaje + eje + extras)
```

Un vuelo de 2 h sale a ~120 XP. Un piloto de 1.000 h ronda los 60.000 XP.

Escala de referencia (a ajustar):

| Rango | Horas | XP | Typerating | XP | Horas en la familia |
|---|---:|---:|---|---:|---:|
| Cadet | 0 | 0 | A320 Series | 2.000 | 25 |
| Junior FO | 30 | 1.500 | B737s | 2.000 | 25 |
| Senior FO | 200 | 12.000 | B767 | 8.000 | 40 |
| Captain | 500 | 30.000 | A330 | 12.000 | 50 |
| Senior Captain | 1.000 | 60.000 | B777 | 20.000 | 60 |
| CFI | 2.000 | 120.000 | A350 | 20.000 | 60 |
| TRI/TRE | 3.000 | 180.000 | Cargo | 25.000 | 40 |

## Modelo de datos

- `users.xp` (int, cacheado) y `users.xp_updated_at`.
- `ranks.xp` (int, requisito).
- **`xp_events`** (el libro): `id`, `user_id`, `pirep_id`, `kind` (flight/landing/type/tour/
  adjust), `points`, `note`, timestamps, **`unique(pirep_id, kind)`** para que recalcular sea
  idempotente.
- `typerating_rules`: `typerating_id`, `min_xp`, `min_family_hours`, `note`.

## Dónde se engancha

1. **Al aceptar el PIREP** (`PirepAccepted`): calcular el XP del vuelo, escribir `xp_events` y
   actualizar `users.xp`. Igual que el libro de la economía: un solo punto de escritura.
2. **Ascenso**: `UserService::calculatePilotRank()` gana la condición de XP (edición pequeña del
   core, documentada). `auto_promote` sigue mandando, pero no asciende sin XP.
3. **Homologación**: listener sobre `PirepAccepted` que, si el piloto alcanza `min_xp` **y**
   `min_family_hours`, crea la fila en `typerating_user` y avisa (Discord + perfil). Es
   idempotente: si ya la tiene, no hace nada.
4. **Restricción**: hoy `pireps.restrict_aircraft_to_typerating` está apagado. Si se enciende,
   hay que **abuelo** (grandfather) las 11 asignaciones actuales o el piloto se queda en tierra.

## Dónde se ve

- **Perfil**: XP total, barra al siguiente rango, homologaciones (con las bloqueadas y lo que
  falta), y el desglose de los últimos movimientos.
- **Tablero**: una línea compacta (XP + progreso), como «Mi economía».
- **Admin**: un top por XP y un ajuste manual (`kind = adjust`) con nota.

## Migración del XP antiguo

No hay dato que importar. Dos pasos:

1. **Reconstrucción**: comando `phpvms:xp-rebuild` que recorre los PIREPs aceptados, escribe
   `xp_events` y recalcula `users.xp` (idempotente, `--dry-run`).
2. **Abuelo**: los rangos y typeratings actuales **se respetan** aunque el XP reconstruido no
   llegue: el XP no quita nada, solo añade puertas de aquí en adelante.

## Anti-abuso

- Tope de 300 XP por vuelo.
- Sin XP en PIREPs cancelados, rechazados o sin vuelo real.
- El mismo tipo repetido no vuelve a dar el bonus de «tipo nuevo».
- Las rutas repetidas puntúan igual: el XP premia calidad y horas, no farmeo de la misma ruta.
- `adjust` solo desde admin y siempre con nota.

## Plan de implementación

1. Migración `xp_events` + `users.xp`/`ranks.xp` + `typerating_rules` (+ semilla de la tabla).
2. `PilotXpService` (la fórmula) + listener `PirepAccepted` + tests de la fórmula.
3. `phpvms:xp-rebuild` + la reconstrucción inicial y el abuelo.
4. Puerta de XP en `calculatePilotRank` + la homologación automática + notificación.
5. Perfil, tablero y top de admin.

## Decisiones que necesito

1. **Escala**: ¿1 XP por minuto de bloque (60/h) te parece, o prefieres otra?
2. **Umbrales de rango**: ¿los de la tabla o los ajustamos a la plantilla actual (47 pilotos,
   top en 3.000 h)?
3. **Homologación**: ¿XP + horas de familia (mi recomendación) o solo XP?
4. **Restricción de tipos**: ¿se enciende `restrict_aircraft_to_typerating` con el XP, o sigue
   apagado y el typerating es solo prestigio?
