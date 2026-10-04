# Rentabilidad de los vuelos reportados por vmsOpenAcars

Fecha: 2026-10-04. Datos: **60 PIREPs** con `source_name LIKE 'vmsOpenAcars%'`
(2026-09-18 → 2026-10-04), de los que **57** tienen libro contable completo. Moneda: **USD**.
Fuente de los costes: `PirepEconomicsService::costs()` (todos los apuntes al debe del PIREP);
ingresos: los apuntes de tarifas del libro (`memo` con "fare"), en bruto.

## Resultado global

| Concepto | Importe (USD) | % de ingresos |
|---|---:|---:|
| **Ingresos por tarifas** | **980.919** | 100 % |
| Fuel (0,9/lb, 302.308 lb) | 326.208 | 33 % |
| **Coste de bloque de la subflota** | **657.234** | **67 %** |
| Pilot payment | 26.209 | 2,7 % |
| Ground handling | 644 | 0,07 % |
| Coste de las tarifas (catering y similares) | 165.505 | 17 % |
| **Coste total** | **1.175.799** | 120 % |
| **RESULTADO** | **−194.880** | **−19,9 %** |

En términos netos (ingresos ya descontado el coste de las tarifas): **815.414 de ingreso
neto frente a 1.010.294 de costes operativos → −194.880 (−23,9 %)**.

Por vuelo: ingreso medio **13.590**, coste medio **16.838** → **pérdida media 3.248**.
Por hora: ingreso **7.659/h** frente a coste **9.180/h**.

## Distribución

- **14 vuelos rentables, 43 en pérdidas** (75 % pierde dinero).
- Horas totales: **128,1 h**.

**Peores 5**

| Vuelo | Ruta | Equipo | Horas | Ingreso | Coste | Resultado |
|---|---|---|---:|---:|---:|---:|
| VHR4 | SKBO–KMIA | B77L | 3,67 | 26.840 | 69.860 | **−43.020** |
| VHR114 | SKRG–SCEL | A320 | 6,33 | 25.200 | 55.269 | **−30.069** |
| VHR100 | SKBO–CYUL | A320 | 5,95 | 24.640 | 49.325 | **−24.685** |
| VHR161 | KBOS–SKBO | A320 | 5,72 | 24.100 | 47.533 | **−23.433** |
| VHR699 | CYUL–SKCG | A320 | 5,27 | 23.920 | 44.942 | **−21.022** |

**Mejores 3**

| Vuelo | Ruta | Equipo | Horas | Ingreso | Coste | Resultado |
|---|---|---|---:|---:|---:|---:|
| VHR34CH | SKSM–SKCG | A20N | 0,58 | 42.660 | 5.180 | **+37.480** |
| VHR025CH | KRSW–KMIA | B738 | 0,75 | 42.300 | 5.701 | **+36.599** |
| VHR55CH | LMML–DAAG | B77L | 2,08 | 70.640 | 34.154 | **+36.486** |

## Diagnóstico

1. **El modelo de ingresos no escala como los costes.** El coste crece con las **horas**
   (bloque + fuel), pero el ingreso depende de **pasajeros × tarifa**, que no crece con la
   duración: un A320 de 6 h factura unas 25.000 y cuesta 55.000, mientras un A20N de 35
   minutos factura 42.660 y cuesta 5.180. **Todo vuelo largo con tarifas de corto radio
   pierde dinero.**
2. **El coste de bloque configurado es el 67 % de los ingresos** y ya por sí solo supera
   el ingreso medio por hora: A320 = **4.829/h**, A20N = **5.200/h**, B38M = **5.000/h**,
   B738 = **4.900/h**, más fuel (~2.500/h). Suman ~7.500–7.900/h contra 7.659/h de
   ingreso: **el punto de equilibrio está en el mejor de los casos**, y las 43 pérdidas
   salen de ahí.
3. **El fuel (0,9/lb) es el 33 %** del coste: razonable, no es el problema.
4. **El pilot payment es irrelevante** (2,7 %).
5. **Anomalía a revisar**: los tres mejores resultados son vuelos con sufijo **CH**
   (¿chárter?) de muy poca duración con ingresos de 42.000–70.000 (hasta 73.000/h, diez
   veces la media). O el chárter se cobra muy por encima, o esas tarifas se están
   aplicando mal. Merece una comprobación antes de sacar conclusiones de ellos.

## Recomendaciones

1. **Recalibrar `cost_block_hour`** de las subflotas a un valor realista (la mitad, por
   ejemplo: A320 ~2.400/h): con los costes actuales ningún vuelo largo puede ser rentable.
2. **Tarifas por radio**: subir el precio de las clases en rutas largas (o aplicar una
   clase alta por defecto en vuelos de más de ~3 h) para que el ingreso por hora acompañe
   al coste por hora. Objetivo mínimo: **9.180/h** de ingreso para empatar, ~11.000/h para
   un margen sano del 15–20 %.
3. **Vigilar la ocupación**: con 60 vuelos el ingreso medio por vuelo (13.590) está muy
   por debajo del coste; parte del arreglo es tarifa y parte es llenar el avión.
4. **Revisar el chárter** (sufijo CH) antes de usar sus números como referencia.
5. **Medir después del cambio**: la referencia es este mismo conjunto — ingreso/hora,
   coste/hora y % de vuelos rentables (hoy 25 %).

## Cómo reproducirlo

```bash
# Ingresos y costes de los PIREPs de vmsOpenAcars (el libro está en céntimos)
php artisan tinker
>>> $ids = DB::table('pireps')->where('source_name','like','vmsOpenAcars%')->pluck('id');
>>> DB::table('journal_transactions')->where('ref_model','App\Models\Pirep')
      ->whereIn('ref_model_id',$ids)->where('memo','like','%fare%')
      ->selectRaw('sum(credit-debit)/100 as ingreso_neto')->first();
```
