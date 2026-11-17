# Revisión de la ruta antes del vuelo (prefile) — contrato v1

Fecha: 2026-11-16. Estado: **implementado en phpVMS**; vmsOpenAcars manda `route_navlog`
(versión 1) desde la 0.9.73.

## El problema, en una línea

La ruta la genera SimBrief y el piloto la acepta sin mirarla. El vuelo **863 (SKBO–KATL)**
entró en **SKR11 / BARRANQUILLA** (restringida, GND–FL999, permanente) porque **el plan**
pasaba por dentro; el piloto no desvió y el ACARS lo detectó **en vuelo** (17:30:55), con el
aviso «AHEAD» solo 2 minutos antes. Al presentar el PIREP ya no hay nada que corregir.

## Momento elegido: el PREFILE, no el envío

El cliente **prefila antes de despegar**. Ahí es donde el aviso llega a tiempo. En el envío
del PIREP el vuelo ya está volado: solo sirve para marcar y revisar.

## Qué manda el cliente (v1)

En el **prefile** (`POST /api/pireps/prefile`):

```json
{
  "flight_id": "…",
  "route_navlog": "DE14L,4.692706,-74.124725,9900;BO907,4.784872,-74.065556,9900;…",
  "route_navlog_version": 1
}
```

- `route_navlog`: puntos separados por `;`. Cada punto es
  **`IDENT,lat,lon,alt`** (altitud en pies).
- `route_navlog_version`: **1**. Es la única versión que el servidor interpreta; si llega
  otra, se ignora el navlog y se cae al respaldo de SimBrief.

El servidor tolera puntos vacíos o mal formados (los descarta) y **nunca** falla el prefile
por este campo.

### La altitud es parte del dato

Sin altitud, un SID que pasa **por encima** de un área se marcaba como incursión: el SID
**LLIX2A de SKRG** «invadía» **SVR2508** (GND–3000 ft MSL) volando a **11.500 ft**. Ahora
solo se avisa si el punto de cruce está **dentro** de los límites del área
(`lower_limit`..`upper_limit` de NavData, en pies).

Aproximación conocida: los límites referidos a **GND/AGL** se comparan como si fueran MSL
(el proyecto no tiene modelo de terreno). Para las áreas colombianas y venezolanas, que son
casi todas MSL o GND–FLxxx, no cambia el resultado.

## Cadena de respaldo

1. `route_navlog` (v1) → se comprueba con su altitud.
2. Si no viene: `simbrief_id` → se lee el `navlog` del OFP (`altitude_feet` por fix).
   *Hoy no resuelve en la práctica*: el id llega, pero el OFP no está siempre en el servidor.
3. Si tampoco: `route_warning: null`.

## Qué recibe el cliente

En el recurso del PIREP:

```json
"route_warning": {
  "area":   "SKR11 / BARRANQUILLA",
  "limits": "GND–FL999",
  "leg":    "GIKPU–PADUD",
  "action": "revisar_ruta"
}
```

`route_warning` es **`null`** cuando no hay nada que avisar (ruta limpia, sin navlog o área
de tipo **Danger**, que es advertencia y no prohibición).

## Qué pedimos que haga el cliente

1. **Mostrar el aviso antes de conectar**, con área, límites y tramo. No un log: un aviso
   visible, como el de la compuerta.
2. Dos salidas: **corregir el plan** en SimBrief, o **«he revisado la ruta»**.
3. Si el piloto acepta, mandar en el PIREP `fields.route_ack = 1`. El servidor lo guarda
   **tal cual**: es un hecho (el piloto pulsó), no un juicio. No cambia el estado del PIREP;
   deja constancia de que **fue advertido** para la revisión posterior.
4. No bloquear la conexión desde el servidor: el prefile **nunca** falla por esto. El bloqueo,
   si lo queréis, es decisión de la UI del cliente.

## Lo que hace phpVMS

- `AirspaceService`: pide a **NavData** las áreas alrededor de la ruta y comprueba
  **intersección de segmentos** (muestreo cada ~1 nm), no 15 puntos sueltos. Usa la
  **altitud interpolada** de cada tramo.
- **Caché de cobertura**: el endpoint de NavData garantiza 200 NM por consulta (índice local)
  o ~108 NM (API REST), así que se guarda el centro y el radio de cada consulta
  (`storage/app/auditoria/airspaces.json`, la misma caché que `phpvms:airspace-audit`) y solo
  se pide a NavData cuando la ruta sale de toda la cobertura conocida. Las consultas van en
  paralelo (`Http::pool`).
- Enganche en `PirepService::prefile()`: si hay cruce, guarda los campos `route_airspace`,
  `route_limits` y `route_leg` y devuelve `route_warning`. Cualquier fallo (sin ruta, sin
  red, NavData caído) se ignora con un `Log::warning`.
- Solo cuentan **Restricted** y **Prohibited**; **Danger** se guarda en caché pero no avisa.
- `level` acepta `34000` o `FL340` (se normaliza a pies).

## Cómo comprobarlo

1. Prefilar con un `route_navlog` que cruce SKR11 a FL340 → `route_warning` con esa área.
2. Prefilar con un navlog que pase sobre SVR2508 a 11.500 ft → `route_warning: null`.
3. El mismo navlog a 2.000 ft → avisa de SVR2508.
4. Prefilar sin `route_navlog` ni `simbrief_id` → `route_warning: null` y el prefile funciona igual.
5. `fields.route_ack = "1"` → se guarda en el PIREP sin cambios.

Pruebas automáticas: `tests/RouteAirspaceCheckTest.php` (cruce, altitud, Danger, navlog v1) y
`tests/RouteNavlogPrefileTest.php` (prefile con aviso, sin aviso, versión futura,
`route_ack`, `level` en formato FL).
