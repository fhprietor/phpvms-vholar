# Wingo (RPB), Click (EFY) y Satena (NSE) — completado

> **Actualización posterior:** los `route_code` RPB/EFY/NSE se han eliminado (ya no hay idents
> `VHR<n>/C.RPB`) y se cerraron los huecos de cobertura de destinos. Ver
> `COBERTURA-COMPETENCIA.md`.

**Fecha:** 2026-10-03 · Decisión: añadir sus vuelos **bajo VHOLAR**, **todas sus rutas confirmadas**, con barrido completo de red.

## 1. Resultado: 477 vuelos creados

| Operador | Vuelos | Pares de ruta confirmados | Equipos observados |
|---|---|---|---|
| **Wingo (RPB)** | 156 | 50 | B737-800 (156) |
| **Click / Clic Air (EFY)** | 197 | 69 | ATR 42 (119) y ATR 72 (76) |
| **Satena (NSE)** | 124 | 74 | ATR 42 (63), ATR 72 (28), ERJ-145 (18), Embraer 190 (7), DHC-6 (3) |
| **Total** | **477** | 193 | |

- Idents: `VHR7002/C.RPB`, `VHR4880/C.EFY`, `VHR8769/C.NSE` (el `route_code` identifica al operador y evita chocar con nuestros números; el ATC sigue siendo VHR<número>).
- Horas: se usa la **hora de salida real observada** (UTC) de la última operación del número; la llegada se calcula con el tiempo de bloque.
- Distancias en millas náuticas; bloque estimado según el equipo (ATR ≈ 4,3–4,5 nm/min; 737 ≈ 7,5; regional jet ≈ 6,5).
- **0 números duplicados** con el resto de la flota.

## 2. Método (el mismo que con Avianca)

1. Barrido de **4.722 pares de ruta** (3.192 entre los 57 aeropuertos colombianos + 1.504 internacionales desde las bases de Wingo).
2. **Verificación número por número** (`/live/flight/<operador><número>`): 510 números, de los que **509 devolvieron ruta real** (origen, destino y equipo).
3. Solo se crean vuelos donde la ruta real del número **coincide exactamente** con el par: eso descarta los itinerarios con escala, que son la mayoría de lo que aparece en un par sin vuelo directo.

Ese filtro explica la diferencia entre pares observados y pares confirmados:

| Operador | Pares observados | Pares **confirmados** (vuelo directo) | Descartados por ser conexión |
|---|---|---|---|
| Wingo | 211 | 50 | 161 |
| Click | 387 | 69 | 318 |
| Satena | 101 | 74 | 27 |

Satena es la que mejor ratio tiene (74 de 101): su red es casi toda punto a punto.

## 3. Incidencia: límite de peticiones de FlightAware

El barrido masivo agotó el límite de FlightAware y todas las respuestas pasaron a **HTTP 429** con `<title>Error</title>`; el parser antiguo las contaba como «existe», dejando los 510 números sin ruta. Se detectó al ver que el plan sólo daba 9 vuelos.

Solución: `reprobe_carriers.py` (un hilo, 4 s entre consultas, backoff exponencial 90 s → 900 s ante cada 429). Cuando el bloqueo levantó, revalidó los 510 números: **159/159 RPB, 211/212 EFY, 139/139 NSE**, todos con actividad en 2026.

## 4. Equipos: dónde no nos equiparamos

| Equipo | Vuelos | En nuestra flota | Qué se hizo |
|---|---|---|---|
| ATR 42 (AT43) | 182 | Sí (AT46) | Enlazado a ATR 42/72 |
| B737-800 | 156 | Sí (B738/B38M) | Enlazado a B737 MAX 8 y 737-800 |
| ATR 72 | 104 | Sí (AT76) | Enlazado a ATR 72/42 |
| **ERJ-145 (E145)** | 18 | **No** | Enlazado al regional más cercano (ATR) — provisional |
| **Embraer 190 (B190)** | 7 | **No** | Enlazado al regional más cercano (ATR) — provisional |
| DHC-6 | 3 | Sí (DHC6) | Enlazado a DHC-6 |
| Sin equipo en el dato | 7 | — | Enlazado al regional de su operador |

**Brecha de flota regional:** Satena vuela **ERJ-145 y Embraer 190** y nuestra flota no los tiene; 25 vuelos quedan cubiertos con ATR. Si se quiere equiparación real, hay que dar de alta esas dos subflotas (con sus tarifas por subflota) — es el único punto donde Click/Satena nos superan en equipos.

## 5. Tarifas

Los vuelos internacionales de Wingo entraron en el barrido de tarifas con factor **low-cost (0,8 ×)**. Ejemplo: `RPB7002` SKBO–TNCA (Aruba, 529 nm) → Y 175 / W 265 / C 540.

## 6. Respaldos

| Archivo | Contenido |
|---|---|
| `storage/app/ava_parallel/backups/carriers_pre_*.sql` | Estado anterior a la creación de los 477 vuelos |
| `storage/app/ava_parallel/create_carriers.sql` | Los 477 `INSERT` + 958 enlaces de subflota |
| `storage/app/ava_parallel/plan_carriers.csv` | Plan completo (número, ruta, equipo, distancia, bloque, horas, subflotas) |
| `red_carriers.csv` | Red observada par a par, con columna `lo_operamos` |
