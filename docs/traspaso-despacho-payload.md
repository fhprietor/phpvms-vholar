# Traspaso: sugerido de PAX y carga en el despacho (en curso)

Fecha: 2026-10-04. Estado: **modelo validado en seco; nada implementado en la web**.

## Objetivo

Cuando el piloto ya ha reservado un vuelo regular y va al despacho (SimBrief), ofrecerle un
**sugerido de PAX y carga que haga rentable el trayecto**, teniendo en cuenta clima y
elevacion del aeropuerto de salida. Excluye charter (`CH`) y ferry (`FR`) **por sufijo**.

## Decisiones cerradas (mantenedor)

1. **Plazas por tipo**: las asume el agente (A320 180, A20N 180, A21N 220, A319 140,
   A321 220, B738 189, B38M 178, B737 140, B77L 300, B77W 350, B789 290, A333/A339 290,
   A359 315, B763 270, B764 245, AT76 70, DH8D 78; cargueros 0). No hay tabla propia.
2. **Equipo: NO se sugiere**. El piloto ya reservo: se usa **el tipo reservado**.
3. **CH y FR se excluyen por sufijo** del numero de vuelo.
4. **Objetivo**: minimo rentable con **+20 % de margen**.
5. **Rendimiento**: aproximacion **−1 % de plazas por cada 1.000 ft de elevacion** y **−1 %
   adicional por cada 5 ºC por encima de la ISA**, avisando en el modal cuando recorta.
6. Orden acordado antes de construir: **simulacion en seco** (hecha, abajo) → servicio →
   endpoint con METAR cacheado → rellenar el modal.

## Formula validada

```
horas      = pirep.flight_time / 60
coste      = horas * (subfleet.cost_block_hour + 2.124) + 15      # 2.124/h = fuel medido del libro
objetivo   = coste * 1,20
plazas_max = tabla(tipo) * (1 - 0,01*(elevacion_ft/1000) - 0,01*max(0, temp - ISA)/5)
precio_pax = media de las tarifas de pasajero del vuelo (flight_fare -> fares, type=0)
pax        = min(ceil(objetivo / precio_pax), plazas_max)
si falta ingreso y el vuelo tiene tarifas de carga: cubrir con carga (type=1)
```

## Resultado de la simulacion (57 vuelos regulares de vmsOpenAcars, CH/FR excluidos)

- **57 de 57 rentables** con el sugerido; **0** imposibles ni lleno; **5** con recorte por
  elevacion.
- Los pilotos vuelan **muy por encima** de lo necesario: 135 pax donde bastan 75; 164 donde
  bastan 92; 180 donde bastan 121.
- **Hoy 16 de 57 vuelos ingresaron menos que su coste (28 %).**
- Caso a revisar siempre: MDSD–SKCL (A320, 4,8 h) con recorte por elevacion → margen 7 %
  en vez del 20 % (avisar de que el objetivo no es alcanzable).

## Donde se toca (para implementar)

- **Modal**: `resources/views/layouts/vholar/components/simbrief-dispatch-modal.blade.php`
  — ya tiene `#sb-pax` y `#sb-cargo`; hoy los **inventa** en JS
  (`sbDefaultPax(d.actype)` y `pax*25 + random()`). Ahi va el sugerido y la nota de contexto.
- **Botones de despacho**: `resources/views/layouts/vholar/flights/table.blade.php` (clase
  `sb-dispatch-btn` con `data-*`) y `modules/DisposableSpecial/.../assignments/index.blade.php`.
- **Endpoints/controladores del tema**: `app/Themes/Vholar/Routes/web.php` +
  `app/Http/Controllers/Vholar/` (patron de `FleetGridController`/`NetworkMapController`).
- **Datos**: `subfleets.cost_block_hour`, `aircraft.dow/mtow/mlw/zfw`, `airports.elevation`,
  `flight_fare` + `fares`, `app/Services/Metar*` para temperatura/QNH/viento.
- **Servicio nuevo propuesto**: `app/Services/DispatchSuggestionService.php`.

## Restricciones del entorno (importantes)

- **Vistas compiladas**: `php artisan view:clear` antes y despues; ejecutar artisan/phpunit
  con `umask 000` (si no, www-data no puede sobrescribir y salen 500).
- **Cache**: versionar la clave al cambiar el **significado** del dato (`...v2`), porque
  `cache:clear` puede fallar en silencio por permisos de los shards.
- **No commitear** los ficheros en curso del mantenedor (`PirepRevenue*`,
  `PirepEconomicsService`, `weather.blade.php`, `CLAUDE.md`...): `git add` solo de lo propio.
- Comprobar siempre **por HTTP**, no con tinker (opcache sirvio una version vieja del
  controlador y me dio un falso OK en otra tarea de esta misma sesion).

## Pendientes de otros temas (no mezclar)

- Tarifas/rentabilidad: lo lleva **otro hilo**; este hilo solo analizo y no toco precios.
- 16 vuelos con `route_code = VHR26` que no son tramos de ningun tour (limpiar cuando se
  decida; el tour usa la tabla `disposable_tour_flights`).
