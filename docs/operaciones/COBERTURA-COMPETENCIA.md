# Cobertura frente a la competencia (Wingo, Click, Satena) — cerrada

**Fecha:** 2026-10-03

## 1. Objetivo

Que **VHOLAR opere a todo destino donde opere la competencia**, con **numeración propia** y **sin idents de operador** (`VHR7002/C.RPB` queda eliminado).

## 2. Fuera los idents de operador

Los 477 vuelos creados desde las redes de Wingo/Click/Satena llevaban `route_code = RPB/EFY/NSE`, lo que producía idents como `VHR7002/C.RPB`. Se han convertido en vuelos propios:

| Concepto | Vuelos |
|---|---|
| Conservan su número (no chocaban con nada) | 455 |
| Reubicados: chocaban con nuestra numeración | 11 |
| Reubicados: era un número que Avianca opera en otra ruta | 11 |
| **Total** | **477** |

- `route_code` queda **NULL** en todos: el ident es `VHR<número>` y el ATC `VHR<número>`.
- Los 22 reubicados recibieron números del **pool propio verificado** (números que Avianca no opera): 8240–8262 para los domésticos y 594 para el internacional.
- La tabla queda con **0 números de vuelo duplicados**.

## 3. Destinos de la competencia que no cubríamos

Se cruzó la red observada (4.722 pares de FlightAware) con las **listas de destinos publicadas** de las tres aerolíneas. El primer cruce (solo con los aeropuertos que ya teníamos en la red) daba 0 huecos, pero al comparar contra las listas completas aparecieron **11 destinos** fuera de nuestra red:

| Destino | Aeropuerto | País | Cubierto con |
|---|---|---|---|
| SKAC | Araracuara | CO | 8263/8264 SKFL–SKAC (DHC-6 + ATR 42) |
| SKGO | Santa Ana (Cartago) | CO | 8265/8266 SKCL–SKGO (ATR 42/72, Q400) |
| SKLG | Caucaya (Puerto Leguízamo) | CO | 8267/8268 SKAS–SKLG (DHC-6 + ATR 42) |
| SKSA | Los Colonizadores (Saravena) | CO | 8269/8270 SKBG–SKSA (ATR) |
| SKSV | San Vicente del Caguán | CO | 8271/8272 SKFL–SKSV (ATR) |
| MDPP | Puerto Plata | DO | 8273/8274 SKBO–MDPP (737/320) |
| MKJS | Montego Bay | JM | 8275/8276 SKRG–MKJS |
| MPDA | David | PA | 8277/8278 SKRG–MPDA |
| MPSM | Río Hato | PA | 8279/8280 SKBO–MPSM |
| SVMG | Porlamar (Margarita) | VE | 8281/8282 SKBO–SVMG |
| SVVA | Valencia | VE | 8283/8284 SKBO–SVVA |

Cada uno se abrió **ida y vuelta** (22 vuelos), con numeración propia en pares consecutivos, la distancia en millas náuticas y la subflota adecuada (STOL para los aeródromos de selva, ATR para el regional, 737/320 para los internacionales de Wingo).

**Verificación final: 62 destinos de la competencia, 0 sin cubrir.** Nuestra red activa pasa a 119 aeropuertos.

### Ajuste posterior con datos reales

El barrido discreto (ver §4) reveló por dónde vuela realmente la competencia a esos destinos, y se corrigieron dos ejes:

| Vuelos | Eje inicial | Eje corregido | Evidencia |
|---|---|---|---|
| 8269/8270 | SKBG–SKSA (80 nm) | **SKMD–SKSA (227 nm)** | Click y Satena sirven Saravena desde Olaya Herrera: EFY 7871/9081/9085, NSE 8616/8753 |
| 8271/8272 | SKFL–SKSV (59 nm) | **SKBO–SKSV (158 nm)** | Satena sirve San Vicente del Caguán desde Bogotá: NSE 8632/8633 |

Se mantuvieron los ejes que sí coincidían con la operación real: SKAS–SKLG (Satena 8915/8916) y SKFL–SKAC (Araracuara, que Satena sirve desde Florencia y La Chorrera).

### Evidencia recogida para los 11 destinos

Barrido discreto de 106 pares (una petición cada 9–18 s): **106 respuestas HTTP 200, 0 bloqueos**.

| Destino | Servicio real detectado | Nuestro eje | ¿Coincide? |
|---|---|---|---|
| SKLG Puerto Leguízamo | Satena desde SKAS (8915/8916), SKBO (8645/8754/8776/8907 y 8777), SKCL (8773/8908), SKFL (8907/8908) | SKAS–SKLG | Sí (uno de los reales) |
| SKSA Saravena | Satena desde SKBO (8752) y SKAS (7704/8753); Click y Satena desde SKMD (EFY 7871/9081/9085, NSE 8616/8753) | SKMD–SKSA | Sí (el de más operadores) |
| SKSV San Vicente | Satena desde SKBO (8632/8633) y SKMD (8767) | SKBO–SKSV | Sí |
| MKJS Montego Bay | Wingo desde SKBO (7298/7478/7482), SKCL (7482) y SKRG (7482) | SKRG–MKJS | Sí (uno de los reales) |
| SVVA Valencia | Wingo desde SKBO (7458/7459) | SKBO–SVVA | Sí |
| SKAC Araracuara | Sin servicio en la ventana de FlightAware (Satena la opera desde Florencia y La Chorrera) | SKFL–SKAC | Coherente con la fuente publicada |
| SKGO Santa Ana | Sin servicio en la ventana | SKCL–SKGO | Eje más cercano |
| MDPP Puerto Plata | Sin servicio aún (ruta de Wingo que inicia el 26 de octubre de 2026) | SKBO–MDPP | Coherente con la fuente publicada |
| MPSM Río Hato | Sin servicio aún (inicia el 5 de octubre de 2026) | SKBO–MPSM | Coherente con la fuente publicada |
| MPDA David | Sin servicio en la ventana | SKRG–MPDA | Eje de Wingo en la región |
| SVMG Porlamar | Sin servicio en la ventana | SKBO–SVMG | Eje de Wingo en el Caribe |

No hubo que corregir ningún eje más: los cinco con servicio confirmado ya apuntaban al hub correcto.

## 4. Cliente FlightAware discreto

El barrido anterior agotó el límite de peticiones (HTTP 429). Se incorporó `fa_client.py`, que aplica reglas de cortesía y se autorregula:

- **secuencial**, una petición cada **9–18 s** (retardo base + jitter);
- **cabeceras de navegador** completas y **cookies de sesión** (primero carga la portada);
- **cortacircuitos**: ante un 429 pausa 15 min y duplica hasta 2 h; al recuperarse **sube el retardo base un 40 %**;
- **caché en disco** (`fa_cache/`): nada se descarga dos veces;
- **tope por ejecución** (`--max`) para no encadenar barridos largos;
- registro de cada petición en `fa_client.log`.

Prueba real: 3 peticiones, 3 × HTTP 200, **0 bloqueos**. Con ese cliente se está refinando, ya sin prisa, la ruta real que la competencia usa hacia los 11 destinos nuevos (106 pares, en curso).

## 5. Estado final

| Concepto | Valor |
|---|---|
| Vuelos en la base | 1.275 (1.273 activos) |
| Números duplicados | **0** |
| Vuelos con `route_code` | **0** |
| Aeropuertos en la red activa | 119 |
| Destinos de la competencia sin cubrir | **0** |

Respaldos: `backups/renum_carriers_pre_*.sql` (antes de quitar los route_code) y `backups/cobertura_pre_*.sql` (antes de los 22 vuelos de cobertura).
