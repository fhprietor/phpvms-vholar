# Paralelo de numeración: Avianca (AVA) vs. VHOLAR (VHR)

**Fecha de captura de datos:** 2–3 de octubre de 2026 (ventana móvil de itinerarios de ~48 h) + muestreo en vivo de 30 min.
**Alcance:** 452 pares de ruta que operamos tocando Colombia (263 domésticos SK↔SK y 189 internacionales). Además se rastreó la red de Avianca: 471 pares domésticos SK↔SK con numeración AVA observada (85 de ellos con servicio directo confirmado) y 153 pares internacionales que tocan Colombia (95 confirmados).
**Respuesta corta a la pregunta:** sí, se puede acceder a la numeración nacional de AVA con fuentes públicas, y ya está hecha la comparación. Nuestro esquema coincide con el de Avianca en poco más del 12 % de los vuelos activos.

---

## 1. Resumen ejecutivo

| Métrica | Valor |
|---|---|
| Vuelos en la base | 989 (752 activos) |
| Vuelos activos domésticos / internacionales | 400 / 349 |
| Números distintos activos | 743 |
| Nuestros números que **sí** existen en el inventario de Avianca | **498 de 746 (66,8 %)** |
| Nuestros números **sin ningún registro** en Avianca | **248 de 746 (33,2 %)** |
| Vuelos activos con el número y la ruta correctos | **37** (4,9 %) |
| Vuelos activos con número de Avianca pero en el **sentido inverso** | **57** (7,6 %) |
| Vuelos activos con número vigente de Avianca en **otra ruta** | **408** (54,3 %) — de ellos 290 en rutas colombianas |
| Rutas nuestras con ≥1 número idéntico al de Avianca | 29 de 452 |
| Rutas nuestras con servicio directo de Avianca **confirmado** | 174 de 452 |
| Números AVA distintos observados | 961 (494 domésticos, 344 internacionales; 123 sin ruta legible) |
| Números AVA confirmados en vuelo (OpenSky, 30 min) | 35 callsigns |

**Conclusión operativa:** nuestra numeración no es arbitraria —dos tercios de nuestros números existen realmente en Avianca— pero está **desalineada de ruta**: usamos números reales de Avianca asignados a otros pares de ciudades, y un tercio del inventario (sobre todo el bloque 7xxx y parte del 1xxx) no existe en Avianca en absoluto.

---

## 2. Cómo se obtuvo la lista (y qué límites tiene)

### Fuentes usadas

| Fuente | Uso | Volumen |
|---|---|---|
| `flightaware.com/live/findflight?origin=X&destination=Y` | números AVA por par de rutas | 1.092 pares |
| `flightaware.com/live/flight/AVA<n>` | ¿existe el número? ¿qué ruta y equipo opera? | 1.073 números |
| OpenSky Network (`/api/states/all`, red abierta) | qué números AVA estaban **en vuelo** sobre Colombia | 30 muestras / 30 min |
| Base de datos phpVMS (`flights`, `airlines`, `airports`) | nuestra numeración real | 989 vuelos |

Se respetó `robots.txt` de FlightAware: se usan `/live/findflight` y `/live/flight/AVA<n>`, que están permitidas; **no** se consultó `/live/flight/id/` ni `/ajax/`, que están prohibidas para `User-agent: *`.

### Límites que hay que tener presentes

1. **Ventana corta.** FlightAware muestra ~48 h de itinerario. Un vuelo semanal o estacional puede no aparecer y leerse como «no existe». Por eso «no existe» significa literalmente *sin registro en la ventana consultada*.
2. **Itinerarios con escala.** Para un par sin vuelo directo, FlightAware devuelve itinerarios con conexión y el número observado pertenece al tramo de conexión. Se añadió una **verificación número por número** y cada tabla marca si el servicio directo está *confirmado*.
3. **La página por número muestra el último vuelo**, no toda la programación. Un número que rota entre dos rutas según el día puede aparecer «en otra ruta».
4. **Ruido de callsign.** Los números de 1–3 dígitos y algunos 2xxx aparecen asociados a operaciones que no son Avianca Colombia (p. ej. `AV2603` figura con rutas de Brasil y referencia a GOL; `AVA1` aparece en Filipinas). Esos casos se clasifican como *atribución dudosa*, no como colisión real.
5. **Grupo Avianca.** Avianca El Salvador, Costa Rica, Guatemala, Nicaragua, Honduras y Ecuador usan el mismo callsign `AVA` con numeración propia (bloques 3xx–6xx y 2xxx). Eso explica muchas de nuestras colisiones «internacionales».

---

## 3. Cómo numera Avianca hoy (con evidencia)

### 3.1 Bloques en uso

| Bloque | Números AVA observados | Domésticos | Internacionales | Equipos típicos | Lectura |
|---|---|---|---|---|---|
| 001–099 | 85 | 0 | 83 | B788, A320, A20N | Largo radio y América: **internacional** |
| 100–999 | 270 | 2 | 187 | A20N, A320, B788 | **Internacional** (el grueso del corto/medio radio) |
| 1xxx | 9 | 0 | 6 | MD80, B757 | **Bloque muerto**: últimos registros 1998–2009 |
| 2xxx | 23 | 0 | 0 | B738, B38M | Registros de **Avianca Brasil / Centroamérica**, no de la operación colombiana |
| 3xxx | 2 | 0 | 2 | B752, MD83 | Histórico (2007), sin uso actual |
| 4xxx | 116 | 95 | 10 | A320, **AT72**, A20N | Doméstico, incluye **Avianca Express (turboélice)** |
| 5xxx | 29 | 28 | 1 | A320 | Doméstico |
| 7xxx | 9 | 0 | 6 | A20N, A320 | **No es un bloque de Avianca** |
| 8xxx | 163 | 145 | 18 | A320, A319 | **Doméstico troncal** |
| 9xxx | 255 | 224 | 31 | A320, A319, AT72 | **Doméstico troncal** (el bloque más denso) |

### 3.2 Convención ida/vuelta: **la vuelta es el consecutivo**

En 208 pares ida/vuelta detectados en la programación de Avianca, **la vuelta es siempre `ida + 1`** (0 excepciones):

```
AV8522 SKCL→SKCC  /  AV8523 SKCC→SKCL
AV9458 SKBO→SKCC  /  AV9459 SKCC→SKBO
AV9372 SKRG→SKCG  /  AV9373 SKCG→SKRG
```

No existe una regla global de paridad (impar/pares): en las rutas que salen de Bogotá solo el **21 %** de los números son impares, y en las que llegan a Bogotá, el **80 %**. La regla fiable es **par consecutivo**.

### 3.3 Confirmación independiente en vivo (OpenSky, 35 callsigns)

Domésticos observados en vuelo: `AVA8472, AVA8529, AVA8539, AVA8577, AVA8580, AVA9203, AVA9225, AVA9308, AVA9322, AVA9325, AVA9346, AVA9471, AVA9492, AVA9525, AVA9566, AVA9567, AVA9790, AVA9796`.
Internacionales: `AVA006, AVA007, AVA011, AVA052, AVA057, AVA069, AVA073, AVA116, AVA186, AVA191, AVA211, AVA247, AVA251, AVA253, AVA259, AVA267, AVA4911`.

Esto confirma en operación real el reparto 8xxx/9xxx (doméstico) vs. 1–3 dígitos (internacional).

---

## 4. Diagnóstico: nuestra numeración frente a la de AVA

> **Nota de contexto:** este diagnóstico corresponde a la instantánea previa a la migración (base de la política A). Entre esa captura y la aplicación del plan, un proceso externo renumeró 262 vuelos internacionales; el plan definitivo se recalculó contra el estado real (ver §9.6). El efecto final de la migración está en §9.3.

### 4.1 Los 752 vuelos activos, uno por uno

| Diagnóstico | Vuelos | % | Significado |
|---|---|---|---|
| `COINCIDE` | 37 | 4,9 % | El número existe en AVA **y** opera exactamente nuestra ruta |
| `COINCIDE_INVERSO` | 57 | 7,6 % | El número existe, pero AVA lo usa en el sentido contrario (rompe la regla `vuelta = ida + 1`) |
| `OTRA_RUTA` | 408 | 54,3 % | Número vigente de Avianca asignado a **otro par de ciudades** |
| `NO_EXISTE_EN_AVA` | 247 | 32,8 % | Sin ningún registro en el inventario de Avianca |
| `SIN_DATO` | 3 | 0,4 % | No se pudo verificar |

De los 408 `OTRA_RUTA`:

| Ámbito de la ruta que AVA asigna a ese número | Vuelos |
|---|---|
| Toca Colombia (**colisión real**) | 290 |
| Aerolínea del grupo Avianca (Centroamérica) | 18 |
| Otra red / atribución dudosa (Brasil, EE. UU.) | 52 |
| Ruta no legible (coordenadas, ferry) | 48 |

### 4.2 Nuestros vuelos por bloque

| Bloque | Vuelos activos | Dom. | Intl. | Coincide | Otra ruta | No existe |
|---|---|---|---|---|---|---|
| 001–099 | 51 | 1 | 50 | 25 | 26 | 0 |
| 100–999 | 289 | 1 | 285 | 18 | 175 | 93 |
| 1xxx | 36 | 34 | 2 | 0 | 9 | 27 |
| 2xxx | 25 | 21 | 4 | 0 | 23 | 2 |
| 3xxx | 8 | 8 | 0 | 0 | 2 | 6 |
| 4xxx | 108 | 108 | 0 | 4 | 96 | 8 |
| 5xxx | 20 | 20 | 0 | 4 | 12 | 4 |
| 7xxx | 99 | 97 | 2 | 0 | 5 | 94 |
| 8xxx | 74 | 68 | 6 | 28 | 37 | 9 |
| 9xxx | 42 | 42 | 0 | 15 | 23 | 4 |

**Lecturas clave:**

- **8xxx y 9xxx son nuestro mejor alineado** (43 de 116 vuelos con coincidencia exacta) y son precisamente los bloques troncales domésticos de Avianca.
- **7xxx es el peor**: 99 vuelos, 94 números inexistentes en Avianca y 0 coincidencias. Es un bloque inventado que conviene abandonar.
- **1xxx doméstico es un anacronismo**: 34 vuelos domésticos en un bloque que Avianca dejó de usar hacia 2009 (los escasos registros son MD80/B757 en rutas a Miami).
- **2xxx/3xxx**: números que pertenecen al grupo Avianca de Centroamérica/Brasil, no a la operación colombiana.
- **100–999**: correcto para internacional (285 de 289 vuelos son internacionales), pero solo 18 tienen la ruta exacta.

### 4.3 Ejemplo completo: SKBO ↔ SKCL (Bogotá–Cali)

| Nuestro nº | Ruta | Diagnóstico | Lo que dice AVA |
|---|---|---|---|
| 1109, 1111, 1113 | SKBO→SKCL | No existe | (bloque 1xxx sin uso) |
| 1110, 1112, 1114 | SKCL→SKBO | No existe | (bloque 1xxx sin uso) |
| 8409 | SKBO→SKCL | **Coincide** | SKBO→SKCL, A320 |
| 8417 | SKBO→SKCL | **Coincide** | SKBO→SKCL, A320 |
| 8421 | SKBO→SKCL | **Coincide** | SKBO→SKCL, A320 |
| 8463 | SKBO→SKCL | **Coincide** | SKBO→SKCL, A320 |
| 8418, 8422 | SKCL→SKBO | **Coincide** | SKCL→SKBO, A319/A320 |
| 8410, 8464 | SKCL→SKBO | Otra ruta | SKBO→SKRH / SKBO→SKBQ |
| 2608 | SKCL→SKBO | Otra ruta | SBFZ→SBGL (Brasil) |

Avianca opera **21 números** SKBO→SKCL y **24** SKCL→SKBO en la ventana observada (8xxx, 9xxx y 4xxx). Nosotros cubrimos 7 + 8 vuelos, de los cuales solo 6 números son correctos.

---

## 5. Propuesta de renumeración

**Reglas aplicadas** (todas verificadas contra los datos):

1. Solo se propone un número que Avianca **opera hoy en esa misma ruta y sentido** (confirmado por número).
2. Se preserva la convención `vuelta = ida + 1` cuando el par está disponible.
3. No se reutiliza ningún número que ya use otro vuelo nuestro.
4. Si AVA no vuela ese par directamente, **no se inventa** un número AVA: se recomienda mantener el número propio y reubicarlo en un bloque coherente.

**Resultado:** de los 749 vuelos activos en rutas que tocan Colombia, 37 ya coinciden exactamente y **712 no**. De esos 712,

- **214** reciben un número AVA concreto (de ellos **154** respetan el par ida/vuelta),
- **498** quedan sin número directo confirmado (rutas que Avianca no opera sin escala o cuya programación no se pudo confirmar en la ventana).

### Ejemplo del resultado (SKBO ↔ SKCL)

| Vuelo | Nº actual | Nº propuesto |
|---|---|---|
| SKBO→SKCL | 1109 | **9203** |
| SKBO→SKCL | 1111 | **9205** |
| SKBO→SKCL | 1113 | **8411** |
| SKCL→SKBO | 1110 | **8522** |
| SKCL→SKBO | 1112 | **9204** |
| SKCL→SKBO | 1114 | **9220** |
| SKCL→SKBO | 2608 | **8416** |
| SKCL→SKBO | 8410 | **8454** |
| SKCL→SKBO | 8464 | **9218** |

> ⚠️ Los números propuestos provienen de una ventana de ~48 h: antes de aplicarlos conviene revalidarlos (y comprobar que el número no esté ya tomado por otro vuelo nuestro en la misma fecha). La propuesta es un insumo de decisión, no un script de migración.

### Dos políticas posibles

**A. Espejo (recomendada si el objetivo es realismo de callsigns):** adoptar los 214 números AVA confirmados, migrar el bloque 7xxx completo y el 1xxx doméstico a 8xxx/9xxx, y dejar el resto como numeración propia en bloques coherentes.

**B. Anticolisión mínima:** conservar nuestra numeración y corregir solo los **290** vuelos que hoy chocan con un número vigente de Avianca en otra ruta colombiana. Es el cambio más pequeño que elimina la ambigüedad operativa.

---

## 6. Hallazgos colaterales

- **Rutas de Avianca que no operamos (confirmadas):** SKBO–SKIP (4812/5200/9401 y 4811/5201/8598 de vuelta), SKBO–SKUC (4872/4871), SKBO–SKUI (4855/4854).
- **Candidatas por verificar:** 334 pares domésticos más en los que se observó numeración AVA pero sin confirmación de vuelo directo (pueden ser itinerarios con escala). Ver `ava_rutas_no_cubiertas.csv`.
- **Números duplicados en nuestra base:** 8 números activos repetidos (`269` en 3 vuelos; `61`, `113`, `137`, `220`, `246`, `270`, `2611` en 2). En Avianca el número identifica un servicio y un par de ciudades: conviene resolver estas colisiones internas primero.
- **Vuelos inactivos con `route_code`** (CH/FR/CA/PF: 234 registros) no participan del paralelo; son programas de charter y no siguen el esquema de Avianca.

---

## 7. Archivos entregados

| Archivo | Contenido |
|---|---|
| `ava_vs_vhr_vuelos.csv` | 989 vuelos con número, ruta propia, ruta real de AVA, equipo, última operación, vigencia, ámbito y diagnóstico |
| `ava_vs_vhr_rutas.csv` | 452 rutas: números propios, números AVA observados y confirmados, coincidencias, huecos y cobertura |
| `propuesta_renumeracion.csv` | filas accionables: `id_vuelo`, número actual, número propuesto, par ida/vuelta y motivo |
| `plan_politica_a.csv` | **Plan aplicado**: 749 vuelos activos con número actual, número nuevo, fase y motivo |
| `bloques_ava.csv` | Análisis por bloque numérico (AVA y nuestro) |
| `ava_rutas_no_cubiertas.csv` | 340 rutas de AVA que no operamos (6 confirmadas + 334 candidatas) |
| `anexo_rutas.md` / `anexo_bloques.md` | Las mismas tablas en Markdown |

### Reproducibilidad

Los scripts y los datos crudos quedaron en `storage/app/ava_parallel/`:

```bash
python3 scrape_fa.py        # (histórico) números AVA por par de rutas
python3 fetch_all.py all    # recolección unificada (rutas + números), reanudable
python3 discover_pairs.py   # descubre la red doméstica de AVA
python3 verify_pairs.py     # confirma servicio directo número por número
python3 sample_opensky.py   # muestreo en vivo de callsigns AVA
python3 build_report.py     # regenera los CSV, anexos y resumen.json
```

---

## 8. Siguientes pasos sugeridos

1. **Revalidar la ventana** en otra fecha (por ejemplo un fin de semana) para capturar vuelos que no operan a diario y poder adoptar más números reales de Avianca.
2. Si se quiere el listado completo (no solo la ventana de 48 h) de la programación nacional de AVA, la vía robusta es una API de horarios con licencia (AeroDataBox, Aviationstack, OAG) — las fuentes públicas usadas aquí no permiten garantizar exhaustividad.
3. Revisar el log del scheduler de phpVMS entre las 01:00 y las 01:15 (ver §9.6) antes de dar la numeración por estable.
4. Cuando Avianca estrene números, repetir `enrich_cierre.py` + `build_cierre.py` para detectar si alguno de nuestros números propios pasó a estar en uso.

---

## 9. Migración aplicada (política A – espejo)

### 9.1 Qué se aplicó

Ejecutada el **2026-10-03 a las 01:25 UTC** sobre la tabla `flights` (MySQL), en una única transacción:

| Fase | Criterio | Vuelos |
|---|---|---|
| 1 | Se adopta el número que Avianca opera **en esa misma ruta y sentido** (verificado número por número) | **249** |
| 2 | El vuelo estaba en un bloque incoherente (1xxx, 2xxx, 3xxx, 7xxx, o doméstico con número de 3 dígitos) → se le asigna un número libre del bloque correcto | **68** |
| 3 | Número duplicado (mismo par ida/vuelta o compartido entre dos rutas) → se resuelve respetando `vuelta = ida + 1` | **9** |
| | **Total de vuelos modificados** | **326** |

Además se limpió el `callsign` del vuelo `yVayjExggJZW5D0o` (tenía el valor suelto `VHR5`, que tras renumerar habría producido el ATC `VHRVHR5`). El resto de los 989 vuelos no se tocó.

### 9.2 Verificación posterior

| Comprobación | Resultado |
|---|---|
| Filas en `flights` | 989 (sin cambios) |
| Vuelos activos (SQL) / visibles para la aplicación | 752 / **727** (25 están soft-deleted, condición previa) |
| Filas del plan aplicadas exactamente | **749 / 749, 0 discrepancias** |
| Números duplicados entre vuelos activos | **0** (antes 7) |
| Vuelos domésticos con número de 3 dígitos | 0 |
| Vuelos domésticos en bloques AVA (4xxx/5xxx/8xxx/9xxx) | 400 / 400 |
| Vuelos internacionales con número de 3 dígitos | 347 + 5 en 8xxx que **también son números reales de Avianca** en esas rutas (SKBO–SAEZ, SKBO–SEGU, SKRG–SEGU) |
| Vuelos activos en los bloques 1xxx / 7xxx | **0** (antes 36 y 99) |

De los 326 vuelos modificados, **19 están soft-deleted** (no los ve la aplicación); el efecto visible es sobre 307 vuelos.

### 9.3 Efecto sobre el paralelo

Medido sobre los **724 vuelos activos, no borrados y que tocan Colombia** (la vista real de la aplicación):

| Diagnóstico | Antes | Después |
|---|---|---|
| `COINCIDE` (número y ruta idénticos a Avianca) | 13 | **250** |
| `COINCIDE_INVERSO` | 28 | 12 |
| `OTRA_RUTA` (número real de AVA en otra ruta) | 323 | 252 |
| `NO_EXISTE_EN_AVA` (numeración propia) | 360 | 210 |

En términos simples: **el 34,5 % de los vuelos usa ahora exactamente el número de Avianca para esa ruta**, frente al 1,8 % inicial (13 de 724), y desaparecieron por completo los bloques 1xxx y 7xxx.

### 9.4 Notas y límites de esta migración

- **18 de los 77 números** asignados en las fases 2 y 3 resultaron, al validarlos después, existir en el inventario de Avianca **en otra ruta**. Se eligieron a partir del inventario observado, que es un subconjunto del real. **Resuelto en §10.**
- **Colisión cruzada tolerada por diseño en aquel momento:** la política A solo garantizaba coherencia de bloque y adopción del número correcto donde Avianca vuela esa ruta; los 252 vuelos de `OTRA_RUTA` conservaban un número de Avianca de otra ruta. **Resuelto en §10.**
- **Números que Avianca no usa** (fases 2 y 3): son numeración propia dentro de los bloques de Avianca. Si en el futuro Avianca estrena ese número en otra ruta, reaparecería una colisión cruzada (conviene repetir la validación periódicamente).

### 9.5 Respaldos y reversión

| Archivo | Contenido |
|---|---|
| `storage/app/ava_parallel/backups/flights_pre_migracion_20261003_011931.sql` | Estado **anterior** a la migración (volcado completo de `flights`) |
| `storage/app/ava_parallel/backups/flights_post_migracion_20261003_012542.sql` | Estado **posterior** a la migración |
| `storage/app/ava_parallel/apply_politica_a.sql` | Los 326 `UPDATE` aplicados (solo modifica `flight_number` y un `callsign`) |

Reversión:

```bash
mysql -h127.0.0.1 -uphpvms -p phpvms < storage/app/ava_parallel/backups/flights_pre_migracion_20261003_011931.sql
```

### 9.6 Incidencias registradas durante la operación

1. **Renumeración externa previa.** Entre las 00:33 y las 01:15 UTC, 262 vuelos internacionales cambiaron de número (a pares consecutivos 775/776, 777/778, 779/780…). No fue un proceso mío. El plan definitivo se recalculó contra el estado real de la base, no contra la instantánea antigua, y las 245 numeraciones nuevas se validaron contra FlightAware antes de decidir.
2. **Error propio, subsanado sin pérdida.** Al comparar el respaldo con la base cargué un fragmento del volcado que incluía `DROP TABLE`/`CREATE TABLE` de `flights`, lo que dejó la tabla vacía durante unos minutos. Se restauró de inmediato desde el respaldo (989 filas, 752 activos) y se reaplicó la migración ya corregida. El estado final está verificado fila por fila (749/749).
3. Si algún proceso programado del servidor vuelve a renumerar vuelos (el scheduler de phpVMS ejecuta `Nightly` a la 01:00 y `BackupRun` a la 01:15), conviene revisar el log de esa franja antes de dar la numeración por estable.

---

## 10. Cierre del espejo (2026-10-03, 01:55 UTC)

Objetivo acordado: que **cada vuelo activo tenga o bien el número real de Avianca en su ruta y sentido, o bien un número propio verificado como no usado por Avianca** dentro del bloque correcto. Es decir, cero colisiones cruzadas.

### 10.1 Método

1. **Ampliar la referencia** (`enrich_cierre.py`): se validaron 537 números nuevos contra el inventario de Avianca — los números AVA de las rutas afectadas (para poder adoptarlos) y candidatos a numeración propia. Después, una segunda tanda de 60 candidatos internacionales. Total acumulado: **1.992 números auditados**.
2. **Filtrar por vigencia:** una primera pasada adoptaba números que Avianca operó en 1998–2009 (p. ej. `AV1005`, último vuelo en 2000). Se añadió un filtro que exige que el número esté en servicio hoy (visto en el itinerario actual o con último vuelo en 2026).
3. **Asignar** (`build_cierre.py`): se adopta un número real de Avianca si existe uno vigente y libre para esa ruta y sentido; si no, se asigna un número propio **ya verificado como no usado por Avianca**, respetando pares ida/vuelta consecutivos cuando es posible.

### 10.2 Qué se aplicó

| Concepto | Vuelos |
|---|---|
| Adoptan un número **real y vigente** de Avianca en su ruta | **30** |
| Reciben un número **propio verificado como libre** (Avianca no lo usa) | **234** |
| **Total modificado** | **264** |

Ejemplos de adopciones: `SKBO–KMIA` 5→**4**, `KMIA–SKBO` 6→**173**, `LEBL–SKBO` 920→**19**, `SKBO–LFPG` 927→**54**, `SKBO–SUMU` 107→**109**, `SKBO–SKEJ` 8042→**2984** (AT43 de Avianca Express).

### 10.3 Verificación

| Comprobación | Resultado |
|---|---|
| Filas del plan aplicadas exactamente | **724 / 724, 0 discrepancias** |
| Números duplicados entre vuelos activos | **0** |
| Adopciones que no coinciden con la ruta real del número | 0 |
| Adopciones que no están vigentes | 0 |
| Números propios que Avianca **sí** usa | **0** |
| Números propios sin verificar | 0 |

### 10.4 Estado final (724 vuelos activos visibles que tocan Colombia)

| Situación | Vuelos | % |
|---|---|---|
| **Número real de Avianca en esa ruta y sentido** | **280** | 38,7 % |
| **Numeración propia verificada como no usada por Avianca** | **444** | 61,3 % |
| Colisión cruzada (número de AVA en otra ruta) | **0** | — |
| Número en sentido inverso | **0** | — |

Distribución por bloque: 332 en 3 dígitos (internacional), 268 en 8xxx, 77 en 9xxx, 29 en 4xxx, 15 en 5xxx y 3 en 2xxx (estos tres últimos son ATR de Avianca Express en Barrancabermeja y Yopal, números reales de Avianca).

**Corrección a §3.1:** el bloque **2xxx no es solo herencia de Avianca Brasil/Centroamérica**. Avianca lo usa hoy para su operación regional (AT43): `AV2984` SKBO→SKEJ y `AV2975`/`AV2993` SKYP→SKBO, todos con actividad en octubre de 2026.

### 10.5 Respaldos y reversión

| Archivo | Contenido |
|---|---|
| `storage/app/ava_parallel/backups/flights_pre_cierre_20261003_015532.sql` | Estado anterior al cierre (post-política A) |
| `storage/app/ava_parallel/backups/flights_post_cierre_20261003_015545.sql` | Estado final |
| `storage/app/ava_parallel/apply_cierre.sql` | Los 264 `UPDATE` aplicados |
| `docs/ava-numeracion/plan_cierre.csv` | Plan del cierre: los 724 vuelos con número anterior, número nuevo, tipo y motivo |

```bash
# revertir solo el cierre
mysql -h127.0.0.1 -uphpvms -p phpvms < storage/app/ava_parallel/backups/flights_pre_cierre_20261003_015532.sql
```

### 10.6 Lectura de negocio

El espejo queda cerrado pero **asimétrico**, y eso es informativo: Avianca solo opera de forma directa y confirmada unas 174 de nuestras 452 rutas. En las demás, ningún número de Avianca es "el correcto", así que la única forma de que la numeración sea inequívoca es que el número sea nuestro. Si en el futuro interesa priorizar el realismo visual (números que existan en Avianca aunque sean de otra ruta) sobre la no-colisión, la operación inversa es exactamente la del §9.
