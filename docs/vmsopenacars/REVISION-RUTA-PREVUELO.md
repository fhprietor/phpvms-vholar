# Revisión de la ruta antes del vuelo (prefile) — peticiones al cliente

Fecha: 2026-11-16. Estado: **implementado en phpVMS**. Aquí va lo que necesitamos del lado
de vmsOpenAcars y lo que ya está hecho, para que no haya dudas de contrato.

## El problema, en una línea

La ruta la genera SimBrief y el piloto la acepta sin mirarla. El vuelo **863 (SKBO–KATL)**
entró en **SKR11 / BARRANQUILLA** (restringida, GND–FL999, permanente) porque **el plan**
pasaba por dentro; el piloto no desvió y el ACARS lo detectó **en vuelo** (17:30:55), con el
aviso «AHEAD» solo 2 minutos antes. Al presentar el PIREP ya no hay nada que corregir.

## Momento elegido: el PREFILE, no el envío

El cliente **prefila antes de despegar**. Ahí es donde el aviso llega a tiempo. En el envío
del PIREP el vuelo ya está volado: solo sirve para marcar y revisar.

## Qué tiene que enviar el cliente

En el **prefile** (`POST /api/pireps/prefile`), añadir **`simbrief_id`** (el id del OFP de
SimBrief). Es el único dato que falta: phpVMS ya recibe `route`, y con el id descarga el OFP
y lee su `navlog` (coordenadas de cada fix).

```json
{ "flight_id": "...", "aircraft_id": "...", "route": "OSUS4R GIKPU UQ120 PADUD ...",
  "simbrief_id": "123456789" }
```

Si no mandáis `simbrief_id`, no hay aviso: el servidor no adivina el plan.

## Qué recibe el cliente en la respuesta

En el recurso del PIREP, un campo nuevo:

```json
"route_warning": {
  "area":   "SKR11 / BARRANQUILLA",
  "limits": "GND–FL999",
  "leg":    "GIKPU–PADUD",
  "action": "revisar_ruta"
}
```

`route_warning` es **`null`** cuando no hay nada que avisar (ruta limpia, sin OFP, o área de
tipo **Danger**, que es advertencia y no prohibición).

## Qué pedimos que haga el cliente

1. **Mostrar el aviso antes de conectar**, con área, límites y tramo. No un log: un aviso
   visible, como el de la compuerta.
2. Dos salidas: **corregir el plan** en SimBrief, o **«he revisado la ruta»**.
3. Si el piloto acepta, mandar en el PIREP `fields.route_ack = 1`. Eso deja constancia de
   que **fue advertido**, y cambia por completo la revisión posterior: no es lo mismo volar
   a sabiendas que sin aviso.
4. No bloquear la conexión desde el servidor: el prefile **nunca** falla por esto. El bloqueo,
   si lo queréis, es decisión de la UI del cliente.

## Lo que ya está hecho en phpVMS

- `AirspaceService`: lee el `navlog` del OFP, pide las áreas a **NavData** por celda de 0,25°
  (con caché en `storage/app/auditoria/airspaces.json`) y comprueba **por segmentos**, no por
  muestras sueltas.
- Enganche en `PirepService::prefile()`: si hay cruce, guarda los campos `route_airspace`,
  `route_limits` y `route_leg` y devuelve `route_warning`. **Nunca impide prefilar** y
  cualquier fallo (sin OFP, sin red, NavData caído) se ignora con un `Log::warning`.
- Solo cuentan **Restricted** y **Prohibited**; **Danger** se registra pero no avisa.
- Tests: `tests/RouteAirspaceCheckTest.php` (cruce avisa, ruta limpia no, Danger no, sin
  navlog no).

## Lo que sigue pendiente de vuestro lado (recordatorio)

- **Vuestra comprobación de ruta usa 15 muestras**: en un SKBO–KATL son ~130 nm entre puntos
  y SKR11 mide ~40 nm, así que se lo salta. Cambiadla a **intersección de segmentos**.
- **No mandéis `Danger` como violación**: es advertencia. En la auditoría de los 10 PIREPs
  marcados, 2 eran `Danger`.
- El aviso en vuelo está bien; si añadís la aceptación, que quede en el log y en el PIREP.

## Cómo comprobarlo

1. Prefilar con un `simbrief_id` cuyo OFP cruce SKR11 → `route_warning` con esa área.
2. Prefilar con un OFP limpio → `route_warning: null`.
3. Prefilar sin `simbrief_id` → `route_warning: null` y el prefile funciona igual.
