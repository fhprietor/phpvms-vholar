# Despacho de SimBrief desde vmsOpenAcars

Estado: **servidor listo** (phpVMS). Falta el cambio en el cliente.

## 1. La idea

Hasta ahora vmsOpenAcars montaba la URL de SimBrief por su cuenta a partir de
`GET /api/flights/{id}`. Eso dejaba fuera todo lo que hace la web: el **sugerido
de PAX/carga** (el mínimo que hace rentable el vuelo) y los parámetros canónicos,
y obligaba a mantener dos copias (de ahí salieron bugs como `fl` en centenas).

Ahora **el servidor monta la URL y el cliente la abre**. El cliente no construye
nada: solo pide el despacho y hace `window.open(url)`.

## 2. La llamada

```
GET /api/flights/{id}/dispatch?aircraft_id={id}
x-api-key: <api key del piloto>
Accept: application/json
```

| Query param | Obligatorio | Descripción |
|---|---|---|
| `aircraft_id` | **Sí** | El avión que el piloto ha elegido |
| `dep_time` | No | Salida UTC en `HHMM` o `HH:MM`. Por defecto, ahora + 40 min |

- Autenticación: la misma `x-api-key` que ya usa el resto del cliente.
- **No hace falta que exista reserva (bid)** antes de llamar.
- No consume cuota de SimBrief ni necesita la API key de SimBrief en phpVMS: la
  URL que devuelve es la pública `dispatch.simbrief.com/options/custom`.

## 3. La respuesta

Ejemplo real (recortado):

```json
{
  "ok": true,
  "flight_id": "yz0o45ER36N4kaOA",
  "aircraft_id": 15,
  "simbrief": {
    "url": "https://dispatch.simbrief.com/options/custom?airline=VHR&fltnum=378&orig=MDSD&dest=SKCL&type=A320&reg=HK6251&cpt=…&civalue=30&units=kgs&maps=detail&deph=04&depm=10&flighttype=s&fl=35000&pax=175&cargo=3882&extrarmk=CS%2FVHOLAR+IVAOVA%2FVHR+OPR%2FVHR",
    "params": {
      "airline": "VHR", "fltnum": "378", "orig": "MDSD", "dest": "SKCL",
      "type": "A320", "reg": "HK6251", "cpt": "NOMBRE DEL PILOTO",
      "civalue": "30", "units": "kgs", "maps": "detail",
      "deph": "04", "depm": "10", "flighttype": "s",
      "fl": "35000", "pax": "175", "cargo": "3882",
      "extrarmk": "CS/VHOLAR IVAOVA/VHR OPR/VHR"
    }
  },
  "applicable": true,
  "reason": "ok",
  "route_code": null,
  "aircraft_type": "A320",
  "registration": "HK6251",
  "route": { "dpt": "MDSD", "arr": "SKCL" },
  "block": { "minutes": 145, "hours": 2.42, "cost_hour": 9451 },
  "suggestion": {
    "pax": 175, "cargo": 3882,
    "revenue": 33661.2, "target": 33660.02,
    "margin_pct": 20.0, "target_reached": true
  },
  "notes": [
    { "level": "warn", "text": "Rendimiento: plazas recortadas de 180 a 175 por …" },
    { "level": "ok",   "text": "Margen estimado: +20,0 %." }
  ]
}
```

Campos que importan:

| Campo | Para qué |
|---|---|
| `simbrief.url` | **Lo único imprescindible**: abrirla en el navegador |
| `simbrief.params` | Informativo (log, depuración); es lo que lleva dentro la URL |
| `suggestion.pax` / `.cargo` | Cifra sugerida, si quieres mostrarla antes de abrir |
| `suggestion.margin_pct` / `.target_reached` | `false` = ni con la capacidad del avión se llega al +20 % |
| `notes[]` | Avisos ya redactados (`level`: `ok`/`warn`/`danger`/`info`) para pintar tal cual |
| `applicable` | `false` en operaciones no regulares: **hay URL, pero sin `pax`/`cargo`** |

⚠️ **Ojo con dos nombres parecidos:** `route` (nivel superior) es
`{"dpt": "...", "arr": "..."}`; la **ruta de navegación** va en
`simbrief.params.route` (y solo aparece si el vuelo la tiene).

## 4. Qué tiene que hacer el cliente

```text
al pulsar "PLAN IN SIMBRIEF":
    despacho = GET /api/flights/{id}/dispatch?aircraft_id={avion}
    si ok:
        abrir despacho.simbrief.url en el navegador   // el piloto ajusta y genera el OFP
        (opcional) pintar despacho.notes y despacho.suggestion
    si falla:
        usar el builder local de siempre              // ver §6
```

Nada más. No hay que copiar parámetros ni reconstruir la URL: si el cliente
empieza a añadir o quitar parámetros por su cuenta, vuelve a haber dos versiones.

## 5. Errores

| HTTP | `error` | Qué mostrar |
|---|---|---|
| 401 | — | API key inválida (como en el resto de la API) |
| 404 | `flight_not_found` | El vuelo ya no existe: refrescar la lista |
| 422 | `aircraft_not_found` | Falta el avión o ya no existe: que elija otro |
| 403 | `aircraft_not_allowed` | Ese avión no es de una subflota que el piloto pueda volar en ese vuelo |

## 6. Qué debe dejar de hacer el cliente

1. **Su constructor de URL**: queda como **respaldo** solo si la llamada falla
   (servidores viejos, 500, timeout). Si el endpoint responde, se use la suya o
   no, mándense los parámetros del servidor.
2. **Su `extrarmk`**: el Item 18 (Extra FPL Info) ya es un ajuste de phpVMS
   (`simbrief.extrarmk`, valor `CS/VHOLAR IVAOVA/VHR OPR/VHR`, editable en
   Admin > Settings). Si el cliente sigue mandando el suyo **además**, el plan
   puede acabar con dos. Lo suyo es **vaciar ese ajuste en el cliente** y dejar
   que lo ponga el servidor.
3. **Revisar su `fl`**: si en algún sitio hace `pies / 100`, tenía el mismo bug
   que la web (`fl=330` para 33.000 ft). Con la URL del servidor da igual, pero
   conviene saber si el respaldo lo arrastra. SimBrief lo documenta en **pies**
   (`34000`, o `FL340`).
4. `maps` es `detail` (no `detailed`) y **no existe** `static_url`: si los tienen
   en su builder, sobran.

## 7. Cuándo llamarlo

**Al pulsar el botón**, no al seleccionar el avión. La salida por defecto es
«ahora + 40 min»; si se llama al seleccionar y el piloto tarda media hora, la
hora que abre SimBrief es vieja. (O se le pasa `dep_time` con la hora prevista.)

## 8. Compatibilidad

- El endpoint es **aditivo**: no rompe nada de lo que ya usa el cliente.
- **Desplegar primero el servidor**, después el cliente.
- Detección sencilla: si el `GET` devuelve 404 con un vuelo que existe, el
  servidor es viejo → usar el builder local (respaldo).

## 9. Comprobación antes de dar por bueno el cambio

- [ ] La URL abre SimBrief con el **PAX y la carga sugeridos** precargados.
- [ ] El nivel va en pies (`fl=35000`, no `fl=350`).
- [ ] El Item 18 aparece en el plan (`CS/VHOLAR IVAOVA/VHR OPR/VHR`).
- [ ] Los mapas son `detail`.
- [ ] Un avión de otra subflota devuelve 403 y el cliente muestra el aviso.
- [ ] Un vuelo charter/ferry abre SimBrief **sin** PAX/carga y sin errores.
- [ ] Con el endpoint caído, el cliente sigue despachando con su builder.

## 10. Futuro (no entra en este cambio)

El cliente ya tiene el **usuario de SimBrief** del piloto en sus ajustes. Si en la
llamada lo enviara, phpVMS podría generar un `static_id`, y al volver a por el OFP
poblar la tabla `simbrief` (hoy vacía: nunca se ha adjuntado un OFP a un PIREP).
Eso habilitaría runways del plan, `dpt_runway`/`arr_runway` y demás. Se decide
aparte.
