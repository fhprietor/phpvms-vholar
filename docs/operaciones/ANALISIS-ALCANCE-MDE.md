# Rutas desde Rionegro (MDE / SKRG) a Calgary, Los Ángeles, San Diego y Seattle

**Fecha:** 2026-10-03 · Base: coordenadas de `airports`, flota real en `subfleets`/`aircraft`.

## 1. ¿Qué teníamos?

| Destino | ¿Ruta existente? | Detalle |
|---|---|---|
| CYYC Calgary | **No** | Ninguna operación |
| KLAX Los Ángeles | **Solo desde Bogotá** | Nº 347 y 544 (SKBO→KLAX, 3.027 nm) y 348/381 de vuelta |
| KSAN San Diego | **No** | Ninguna operación |
| KSEA Seattle | **No** | Ninguna operación |

Ninguna de las cuatro existe desde MDE. Las internacionales actuales de MDE son Miami (1.211 nm), Nueva York (2.072 nm), Atlanta y Houston.

## 2. Distancias y el factor determinante: la altitud de MDE

| Ruta | nm | km | Comparación |
|---|---|---|---|
| SKRG–KSAN | 2.823 | 5.227 | +613 nm sobre MDE–Nueva York |
| SKRG–KLAX | 2.909 | 5.387 | |
| SKRG–CYYC | 3.311 | 6.133 | |
| SKRG–KSEA | 3.447 | 6.384 | **la más larga que operaría la VA desde MDE** |

**José María Córdova está a 6.955 ft (2.120 m).** A esa altitud la potencia de despegue cae en torno al 20 % y la carrera de despegue se alarga, así que el alcance útil es bastante menor que el de folleto. Por eso el análisis no basta con “¿llega?”: hay que exigir margen.

## 3. Qué equipo las cubre de forma eficiente

Margen = (alcance de folleto − distancia) / distancia. Se exige **≥ 12 %** para considerarlo eficiente (el resto queda como marginal o no viable).

| Tipo | Aviones | CYYC (3.311) | KLAX (2.909) | KSAN (2.823) | KSEA (3.447) |
|---|---|---|---|---|---|
| **A21N** A321neo | 4 | **+21 % ✅** | **+38 % ✅** | **+42 % ✅** | **+16 % ✅** |
| B38M 737 MAX 8 | 4 | +7 % ⚠️ | +22 % ✅ | +26 % ✅ | +3 % ❌ |
| A20N A320neo | 4 | +3 % ❌ | +17 % ✅ | +20 % ✅ | −1 % ❌ |
| A319 | 5 | +12 % ⚠️ | +27 % ✅ | +31 % ✅ | +7 % ⚠️ |
| A320 | 6 | 0 % ❌ | +13 % ✅ | +17 % ✅ | −4 % ❌ |
| A321 | 2 | −3 % ❌ | +10 % ⚠️ | +13 % ✅ | −7 % ❌ |
| B738 | 5 | −11 % ❌ | +1 % ❌ | +4 % ❌ | −15 % ❌ |
| B737 | 3 | −9 % ❌ | +3 % ❌ | +7 % ⚠️ | −13 % ❌ |
| A339 (doble pasillo) | 3 | +117 % ✅ | +148 % ✅ | +155 % ✅ | +109 % ✅ |

**Respuesta:** el **Airbus A321neo (A21N) es el único avión de la flota con margen cómodo en las cuatro rutas**, y es la elección eficiente también para Calgary y Seattle — los dos casos donde el resto de la flota narrowbody se queda corto. Las cuatro rutas son de **pasillo único**: un A330-900 llega de sobra, pero sería ineficiente por capacidad (y en MDE penalizaría aún más el despegue).

- **Calgary y Seattle:** solo A21N. Son las dos rutas que justifican el avión y, con 4 unidades, conviene revisar la rotación.
- **Los Ángeles y San Diego:** A21N como principal, con B38M, A20N, A319 y A320 como alternativas de respaldo (todas con margen ≥ 13 %).
- **B737-800 / 737-700 / A320 / A321 clásicos:** no deben asignarse; en Calgary y Seattle no llegan y en las otras van sin margen.

## 4. Vuelos creados (2026-10-03)

8 vuelos nuevos (4 pares ida/vuelta) bajo VHOLAR, desde y hacia MDE:

| Nº | Ruta | Salida | Llegada | nm | Bloque | Subflotas enlazadas |
|---|---|---|---|---|---|---|
| 683 | SKRG → KLAX | 08:30 | 15:10 | 2.909 | 6 h 40 | A21N, B38M, A20N, A319, A320 |
| 684 | KLAX → SKRG | 17:00 | 23:40 | 2.909 | 6 h 40 | idem |
| 685 | SKRG → KSAN | 09:00 | 15:25 | 2.823 | 6 h 25 | A21N, B38M, A20N, A319, A320 |
| 686 | KSAN → SKRG | 17:30 | 23:55 | 2.823 | 6 h 25 | idem |
| 687 | SKRG → CYYC | 07:45 | 15:20 | 3.311 | 7 h 35 | **solo A21N** |
| 688 | CYYC → SKRG | 17:00 | 00:35 | 3.311 | 7 h 35 | **solo A21N** |
| 694 | SKRG → KSEA | 08:00 | 15:50 | 3.447 | 7 h 50 | **solo A21N** |
| 695 | KSEA → SKRG | 17:30 | 01:20 | 3.447 | 7 h 50 | **solo A21N** |

Numeración propia verificada como no usada por Avianca, en pares consecutivos. Respaldo previo: `storage/app/ava_parallel/backups/mde_pre_*.sql`; SQL aplicado: `create_mde.sql`.

> Si más adelante se quiere reforzar Calgary o Seattle, la vía natural no es meter un doble pasillo sino **un A321neo más** (o revisar el peso máximo de despegue desde MDE).
