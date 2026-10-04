# Análisis de alcance de flota — nuevas rutas de largo radio

**Fecha:** 2026-10-03 · **Base:** datos de `airports` (coordenadas) y la flota real de la VA (`subfleets` + `aircraft`).
**Método:** distancia ortonómica (haversine, R = 6371,0088 km) entre las coordenadas de la base. Validado contra las distancias ya guardadas en `flights` — que están en **millas náuticas**, no en km: BOG–MAD figura como 4.341 en la base y el cálculo da 4.337 nm (8031 km). Desviación < 0,2 %.

---

## 1. Distancias de las rutas solicitadas

| Ruta | km | nm |
|---|---|---|
| SKBO (Bogotá) – LEMD (Madrid) | 8.031 | 4.337 |
| SKBO – LFPG (París CDG) | 8.645 | 4.668 |
| SKBO – EGLL (Londres LHR) | 8.473 | 4.575 |
| SKBO – EDDF (Fráncfort) | 9.085 | 4.906 |
| SKBO – LIRF (Roma FCO) | 9.361 | 5.055 |
| SKCG (Cartagena) – LEMD | 7.733 | 4.176 |
| SKCG – LFPG | 8.264 | 4.462 |
| SKCG – EGLL | 8.068 | 4.356 |
| SKCG – EDDF | 8.697 | 4.696 |
| SKCG – LIRF | 9.052 | 4.888 |
| **SKCG – LTFM (Estambul)** | **10.406** | **5.619** |

Referencia: la ruta más larga que opera hoy la VA es BOG–Fráncfort con 4.906 nm. **Estambul desde Cartagena añade 713 nm (un 14,5 %) sobre ese máximo.**

---

## 2. Flota de doble pasillo disponible

| Tipo | Subflota | Aviones | Alcance de folleto | Margen en SKCG–LTFM (5.619 nm) |
|---|---|---|---|---|
| A339 | Airbus A330-900neo | 3 | 7.200 nm | **+28 %** ✅ |
| A359 | Airbus A350-900 | 1 | 8.100 nm | **+44 %** ✅ |
| B77L | Boeing 777-200LR | 1 | 8.555 nm | **+52 %** ✅ |
| B77W | Boeing 777-300ER | 2 | 7.370 nm | **+31 %** ✅ |
| B777 | Boeing 777-200ER | 2 | 7.065 nm | **+26 %** ✅ |
| A333 | Airbus A330-300 | 2 | 6.350 nm | +13 % ⚠️ |
| B763 | Boeing 767-300ER | 1 | 5.980 nm | +6 % ⚠️ |
| B764 | Boeing 767-400ER | 2 | 5.625 nm | **+0 %** ❌ |
| B789 / B772 / A332 / A330 | (sin aviones) | 0 | 7.635 / 7.065 / 7.250 / 6.350 nm | ✅ / ✅ / ✅ / ⚠️ |
| A306, B77F, A30F | carga | 4 | 4.050 / 4.970 / — nm | ❌ (no aplica) |

> **Conclusión sobre Estambul:** solo **A339, A359, B77L, B77W y B777** tienen margen suficiente (≥ 26 %). El **B767-400ER no puede** cubrir la ruta (su alcance de folleto es exactamente la distancia: cero margen para viento, reservas o desvío), y el **A330-300 y el B767-300ER** quedan en +13 % y +6 %, es decir, viables solo con restricción de carga.

---

## 3. Por qué Estambul necesita un filtro y las demás no

Los márgenes de folleto son en aire en calma. Aplicando un factor de planificación del **90 %** (viento en contra, reservas y desvío al alterno):

| Ruta | Margen mínimo de la flota completa | Veredicto |
|---|---|---|
| BOG/CTG ↔ Madrid, París, Londres, Fráncfort, Roma | +20 % a +35 % (el peor, el B764) | Toda la flota de doble pasillo sirve |
| **CTG ↔ Estambul** | **−10 % (B764), +2 % (B763), +2 % (A333)** | **Solo A339, A359, B777, B77L y B77W** |

Dos agravantes específicos de Cartagena en la ruta a Estambul:

1. **Pista de 2.600 m** (Rafael Núñez, elevación 1 m). Un bimotor de fuselaje ancho con combustible para 5.619 nm y temperatura caribeña (>30 °C) queda limitado en peso de despegue; el A350-900 y el A330-900 tienen mejor comportamiento en pista que el 777-300ER, que a MTOW necesitaría bastante más longitud. En la práctica implicaría **penalización de pasaje**.
2. **Sentido vuelta (LTFM → SKCG)** es el crítico: se vuela contra el chorro polar, con vientos en contra de hasta 100 kt. La planificación se hace por el sentido oeste, no por el este.

**Recomendación operativa:** abrir CTG–Estambul con **A339 / A359** como tipos principales y **B77L / B77W / B777** como alternativos, dejando fuera A333, B763, B764 y los cargueros. Si se quiere un margen cómodo para el sentido vuelta, el **A359** es la mejor opción de la flota (1 avión) y el **A339** la más disponible (3 aviones).

---

## 4. Cómo se implementa la restricción

phpVMS une vuelos y subflotas por la tabla pivote `flight_subfleet` (6.458 enlaces ya existentes). La convención de la VA:

- Vuelos de largo radio de pasaje → enlazados a las 12 subflotas de doble pasillo (A330, A332, A333, A339, A359, B763, B764, B772, B777, B77L, B77W, B789).
- Vuelos de carga → enlazados a las 3 subflotas de carga (A306, A30F, B77F). Ejemplo real: BOG–MAD nº 46.

Para las nuevas rutas se aplica:

| Nuevas rutas | Subflotas enlazadas |
|---|---|
| SKBO↔LIRF, SKCG↔EDDF, SKCG↔LFPG, SKCG↔EGLL, SKCG↔LEMD, SKCG↔LIRF | Las 12 de doble pasillo (todas cumplen con margen) |
| **SKCG↔LTFM** | **Solo A339, A359, B77L, B77W, B777** (y B789/B772/A332 si algún día entran en flota, pues también cumplen) — se excluyen A333, A330, B763, B764 y las de carga |

---

## 5. Tiempos de bloque estimados

Con el promedio real de la flota en largo radio (≈ 7,4 nm por minuto, medido en los vuelos actuales BOG–MAD/LHR/FRA):

| Ruta | nm | Bloque estimado |
|---|---|---|
| SKCG–LEMD | 4.176 | 9 h 25 min |
| SKCG–EGLL | 4.356 | 9 h 50 min |
| SKCG–LFPG | 4.462 | 10 h 00 min |
| SKCG–EDDF | 4.696 | 10 h 35 min |
| SKCG–LIRF | 4.888 | 11 h 00 min |
| SKBO–LIRF | 5.055 | 11 h 20 min |
| **SKCG–LTFM** | **5.619** | **12 h 40 min** |

---

## 6. Vuelos creados (2026-10-03)

14 vuelos nuevos (7 pares ida/vuelta) bajo VHOLAR, activos y visibles. Numeración propia verificada como **no usada por Avianca**, en pares consecutivos (`ida = n`, `vuelta = n+1`). Distancias en millas náuticas (convención de la base) y tiempos de bloque según el promedio real de la flota (≈ 7,5 nm/min).

| Nº | Ruta | Salida | Llegada | nm | Bloque | Subflotas |
|---|---|---|---|---|---|---|
| 665 | SKBO → LIRF (Bogotá–Roma) | 21:30 | 08:45 | 5.055 | 11 h 15 | 12 dobles pasillos |
| 666 | LIRF → SKBO | 10:30 | 21:45 | 5.055 | 11 h 15 | 12 dobles pasillos |
| 667 | SKCG → EDDF (Cartagena–Fráncfort) | 20:00 | 06:25 | 4.696 | 10 h 25 | 12 dobles pasillos |
| 668 | EDDF → SKCG | 10:00 | 20:25 | 4.696 | 10 h 25 | 12 dobles pasillos |
| 672 | SKCG → LFPG (Cartagena–París) | 20:20 | 06:15 | 4.462 | 9 h 55 | 12 dobles pasillos |
| 673 | LFPG → SKCG | 11:15 | 21:10 | 4.462 | 9 h 55 | 12 dobles pasillos |
| 674 | SKCG → EGLL (Cartagena–Londres) | 20:30 | 06:10 | 4.356 | 9 h 40 | 12 dobles pasillos |
| 675 | EGLL → SKCG | 11:30 | 21:10 | 4.356 | 9 h 40 | 12 dobles pasillos |
| 676 | SKCG → LEMD (Cartagena–Madrid) | 21:00 | 06:15 | 4.176 | 9 h 15 | 12 dobles pasillos |
| 677 | LEMD → SKCG | 12:00 | 21:15 | 4.176 | 9 h 15 | 12 dobles pasillos |
| 678 | SKCG → LIRF (Cartagena–Roma) | 20:15 | 07:05 | 4.888 | 10 h 50 | 12 dobles pasillos |
| 679 | LIRF → SKCG | 11:30 | 22:20 | 4.888 | 10 h 50 | 12 dobles pasillos |
| 681 | **SKCG → LTFM (Cartagena–Estambul)** | 19:45 | 08:15 | 5.619 | 12 h 30 | **8 (solo con margen)** |
| 682 | **LTFM → SKCG** | 09:30 | 23:00 | 5.619 | **13 h 30** | **8 (solo con margen)** |

Notas de la ejecución:

- **Bogotá–Fráncfort, –París, –Londres y –Madrid ya existían** (números 305, 54, 921 y 10/26 respectivamente) y ya estaban enlazados a los 12 dobles pasillos: no se duplicaron. El único tramo nuevo desde Bogotá es **Roma**.
- El regreso de Estambul (682) lleva **13 h 30** en vez de 12 h 30 porque es el sentido crítico contra el chorro polar; es la única ruta donde se rompe la simetría de tiempos de la VA.
- Los vuelos nuevos heredan las tarifas por subflota (`subfleet_fare`, 80 tarifas en 26 subflotas); no hace falta crear tarifas por vuelo.
- Respaldo previo: `storage/app/ava_parallel/backups/longhaul_pre_20261003_193119.sql`; SQL aplicado: `storage/app/ava_parallel/create_longhaul.sql`.
