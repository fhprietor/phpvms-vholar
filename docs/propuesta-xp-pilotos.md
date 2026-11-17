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
base      = block_time_min x 1,5                   (90 XP/hora de bloque: la asintota del OM-A)
calidad   = (score ?? 80) / 80                     (media actual 80,5 -> factor ~1)
acars     = bonificaciones x 5 - penalizaciones x 5
aterrizaje= -60..-200 fpm: +10 | -200..-400: +5 | >-600: 0 | mas duro: -5
eje       = <5 ft: +5 | <15 ft: +2
extras    = tour/asignacion completada +50 | primer vuelo en un tipo +25
XP vuelo  = min(300, base x calidad + acars + aterrizaje + eje + extras)
```

Un vuelo de 2 h sale a ~120 XP. Un piloto de 1.000 h ronda los 60.000 XP.

### La escala NO se inventa: está en el OM-A

El OM-A (Rev. 6, 2025-07-25, §4) fija **horas y XP** juntos. Esta es la escala real:

| Rango | Horas | XP (OM-A) | Habilitaciones |
|---|---:|---:|---|
| Cadet | 0 | **0** | Bimotores categoría L (MTOW 5.700 kg) |
| Junior First Officer | 30 | **6.000** | Según filial (ATR42/72, DHC8, A319/320, B737) |
| Senior First Officer | 200 | **28.000** | Según filial + **solo una** de tipo pesado (H) |
| Captain | 500 | **50.000** | Las de SFO + **dos** de tipo pesado |
| Senior Captain | 1.000 | **90.000** | **Todas** las de la flota según filial |
| CFI | 2.000 | **180.000** | Todas. Requiere SPP IVAO + exámenes |
| TRI/TRE | 3.000 | *(no figura)* | Todas. Requiere CP IVAO + examen y entrevista |

**Lo que esto cambia**: los typeratings **no** llevan un XP propio; van **atados al rango**
("solo una de tipo pesado" en SFO, "dos" en Captain, "todas" en Senior Captain). Es una
puerta por rango, no una tabla paralela.

**Y el XP no es una puntuación de calidad**: es un **contador de carrera** que se gana sobre
todo con horas, y los escalones que no cuadran con las horas corresponden a la **formación**
del OM-A (retroalimentación escrita en JFO, exámenes y sesión práctica en Captain, SPP/CP de
IVAO en CFI/TRE). Por eso 30 h piden 6.000 XP (200/h) y 2.000 h piden 180.000 (90/h): las
primeras horas valen más porque la formación suma.

Base de cálculo que replica la escala: **90 XP por hora de vuelo** (la asíntota de CFI:
180.000/2.000) **más los bonus de formación** hasta cuadrar cada escalón.

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

## Decisiones que necesito (ya con el OM-A como base)

1. **Formación**: el OM-A dice que el XP sube con los exámenes/sesiones, pero no cuánto. Mi
   calibración para cuadrar los escalones: 3.300 XP al superar JFO, 6.700 al SFO, 5.000 al
   Captain. ¿Te parece, o prefieres otro reparto?
2. **TRE/TRI**: el OM-A no le pone XP (solo 3.000 h + examen + entrevista). ¿Lo dejamos
   **solo por horas y exámenes** (recomendado) o le ponemos un XP sobre 180.000?
3. **Habilitaciones**: el OM-A las ata al **rango** ("una de tipo pesado" en SFO...). ¿Lo
   implementamos así (el rango abre tipos) o mantenemos los typeratings como dato aparte que
   el admin asigna a mano?
4. **Homologación de horas**: el OM-A permite homologar horas por experiencia real, rango PP
   de IVAO o FS3 con +1.000 h. ¿Eso suma **horas** (y por tanto XP) o solo horas?
5. **Restricción de tipos**: ¿encendemos `restrict_aircraft_to_typerating` cuando el rango no
   habilita el tipo, o sigue apagado?
