# VHOLAR Express — Baron 58 en la red regional

**Fecha:** 2026-10-03 (recalibrado tras tu corrección) · Flota: 5 × Beechcraft Baron 58 (subflota 20) · 537 vuelos regionales analizados.

## 0. Corrección aplicada

Mi primer modelo era demasiado estricto con los turbohélices y demasiado amplio con el AVGAS. Con tus dos datos reales:

| Caso | Dato tuyo | Lo que implica |
|---|---|---|
| **SKMZ Manizales** (1.474 m a 6.871 ft) | **Sí tiene AVGAS** y **operan ATR 42 y Q400** | No hay tankeo ahí, y el mínimo de pista del ATR 42 / Q400 es menor de lo que asumí |
| **SKPV Providencia** (1.168 m a 10 ft) | **No tiene AVGAS**, y **operan ATR 42 y Q400** | Tankeo obligatorio, y 1.168 m es suficiente para el ATR 42 **y** el Q400 |

Con esos dos puntos se recalibró el modelo: **ATR 42 y Q400 con mínimo de 1.150 m** (+4 % por cada 1.000 ft), **ATR 72 con 1.450 m** — que es lo que explica que el 72 no aparezca ni en Manizales ni en Providencia. Se restauraron los enlaces que el modelo viejo había quitado por error y se recalculó todo.

## 1. El avión y su alcance

| Dato | Valor | Uso |
|---|---|---|
| Alcance de folleto (crucero largo, 4 ocupantes, reservas NBAA IFR) | 1.480 nm | referencia |
| Alcance operativo con pasaje, reservas y viento | ~1.000 nm | techo realista |
| Radio con **tankeo** (sin AVGAS en un extremo) | ~450 nm por tramo | restricción |
| Despegue a MTOW (SL, ISA) | 640 m | + margen → mínimo 800 m |
| Sensibilidad a la altitud | motor atmosférico | +7 % de pista por 1.000 ft |

## 2. Resultado: el Baron puede volar **toda** la red regional

**537 de 537 vuelos** regionales admiten el Baron. Todos los tramos están dentro de alcance: el más largo es de **419 nm** (San Andrés–Villavicencio) y el radio con tankeo es de 450 nm, así que ni siquiera los tramos a campos sin AVGAS quedan fuera.

- **331 vuelos** ganan el Baron como equipo alternativo (75 de ellos con tankeo obligatorio).
- **Rutas más largas habilitadas:** SKSM–SKVV 419 nm, SKBQ–SKVV 410 nm, SKBO–SKPC 408 nm, SKPC–SKVV 385 nm.

## 3. Dónde el Baron es la única opción

Con el modelo corregido quedan **dos aeródromos** donde **ningún ATR ni Q400** puede operar:

| OACI | Pista | Elevación | Por qué | Flota resultante |
|---|---|---|---|---|
| **SKML Montelíbano** | 1.085 m | 160 ft | Pista por debajo del mínimo del ATR 42/Q400 | **Baron + DHC-6** |
| **SKOC Ocaña** | 1.200 m | 3.850 ft | Pista corta **y** 3.850 ft de altitud: el ATR 42 necesitaría ~1.327 m | **solo Baron** |

**7 vuelos** quedan con el Baron como único equipo viable:

| Vuelo | Ruta | Distancia |
|---|---|---|
| 8849 | SKCC → SKOC (Cúcuta–Ocaña) | 55 nm |
| 8846 | SKMD → SKOC (Medellín–Ocaña) | 183 nm |
| 8911, 8912, 8926 | SKMD ↔ SKML (Medellín–Montelíbano) | 106 nm |
| 8254, 8913 | SKMD ↔ SKML | 106 nm |

## 4. Dónde no cambia nada (y estuvo bien dejarlo)

Manizales y Providencia **mantienen ATR 42 y Q400**, que es lo correcto según tu experiencia:

| Aeropuerto | Flota enlazada | Nota |
|---|---|---|
| SKMZ Manizales | ATR 42, Q400, Baron, DHC-6 | **sin ATR 72** (pista corta + 6.871 ft) |
| SKPV Providencia | ATR 42, Q400, Baron, DHC-6 | **sin ATR 72**; Baron con tankeo |
| SKML Montelíbano | Baron, DHC-6 | sin ATR ni Q400 |
| SKOC Ocaña | **solo Baron** | sin ATR ni Q400 |

El **ATR 72** es el que queda fuera de todos los campos cortos (1.085–1.350 m) y de los de altitud elevada: Montelíbano, Providencia, Nuquí, Bahía Solano, Guapi, Leguízamo, Saravena, Tolú, Villagarzón, Ocaña, Manizales, Pitalito, Paz de Ariporo, San Gil.

## 5. Cambios aplicados en la base

| Acción | Cantidad |
|---|---|
| Enlaces retirados por pista insuficiente | **98** (ATR 72 91, ATR 42 5, Q400 2) |
| Vuelos que ganan el Baron | **331** |
| Vuelos con el Baron enlazado (total) | **537** |
| Vuelos activos sin ninguna flota | **0** |

**Arreglo colateral:** el vuelo **9508 (MDE–Cúcuta)** llevaba sin ninguna subflota y con `flight_time = 0` desde abril; se le enlazó la flota narrowbody de esa ruta y se le puso el bloque de 70 min que tienen sus gemelos 9507 y 9510.

Respaldos: `backups/baron_pre_*.sql` (estado previo al primer intento) y `backups/baron2_pre_*.sql` (previo a este recalculo). SQL aplicado: `apply_baron2.sql`.

## 6. Lo único que sigue asumido

La disponibilidad de AVGAS. Confirmado: **SKMZ sí, SKPV no**. Para el resto se asume sin AVGAS en los aeródromos remotos (Araracuara, La Macarena, Leguízamo, San Gil, Montelíbano, Ocaña, Bahía Solano, Nuquí, Guapi, Buenaventura, Tolú, Villagarzón). Hoy esa asunción **no excluye ningún vuelo** (el radio con tankeo, 450 nm, cubre el tramo más largo de 419 nm), pero conviene cargar el dato real en `airports.fuel_100ll_cost` para que el cálculo se haga solo en el futuro.

---

## 7. Pistas: la más corta y las que no tienen pavimento (2026-10-03)

Cruce de la red activa (119 aeropuertos) con el dataset de pistas.

**La pista más corta donde volamos: SKML Montelíbano, 1.085 m (3.560 ft), asfalto.** Es justo el caso donde el Baron (y el DHC-6) son la única opción, porque ningún ATR ni Q400 baja de ahí con carga.

Las seis más cortas, todas asfaltadas:

| OACI | Pista | Superficie | Flota viable |
|---|---|---|---|
| SKML Montelíbano | 1.085 m | Asfalto | Baron, DHC-6 |
| SKPV Providencia | 1.168 m | Asfalto | ATR 42, Q400, Baron, DHC-6 |
| SKLG Puerto Leguízamo | 1.200 m | Asfalto | ATR 42, Q400, Baron, DHC-6 |
| SKOC Ocaña | 1.200 m | Asfalto | **solo Baron** (3.850 ft de altitud) |
| SKNQ Nuquí | 1.200 m | Asfalto | ATR 42, Q400, Baron, DHC-6 |
| SKSA Saravena | 1.200 m | Asfalto | ATR 42, Q400, Baron, DHC-6 |

**Sin pavimento solo hay uno: SKAC Araracuara, pista 11/29 de 1.280 m en GRAVA** (elevación 1.240 ft). Es una de las rutas que abrimos para cubrir a Satena, y estaba enlazada al ATR 42: se le retiró (un ATR sobre grava no es realista), quedando **Baron 58 + DHC-6**.

Los otros tres aeródromos sin dato en el dataset se verificaron por otra vía:

| OACI | Pista | Superficie | Fuente |
|---|---|---|---|
| SKNA La Macarena | 1.580 m | Asfalto | Wikipedia (El Refugio) |
| SKSG San Gil | 1.400 m | Asfalto | Wikipedia (Los Pozos), a 5.741 ft |
| SKAC Araracuara | 1.280 m | **Grava** | Wikipedia |

De paso, el ajuste por San Gil: a 5.741 ft de altitud, el **ATR 72 queda fuera** (necesitaría ~1.784 m); el ATR 42 y el Q400 siguen como marginales con restricción de carga.

Por completitud: en la red hay dos pistas sin pavimento que **no usamos** — una franja de hierba de 440 m en París CDG (08H/26H, para helicópteros y aviación ligera) y una pista de grava de 2.050 m en El Alto (La Paz, 10L/28R). Nuestras operaciones en ambos aeropuertos van por las pistas pavimentadas.
