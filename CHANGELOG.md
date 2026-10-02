# Changelog

## [Vholar] 2026-10-02 — Tipo por defecto en las posiciones ACARS (vholar-1.1.1)

- `Api\AcarsController@acars_store`: toda posicion almacenada lleva
  `type = FLIGHT_PATH (0)` si el cliente no envia el campo
  (`$position['type'] ?? AcarsType::FLIGHT_PATH`). La columna `acars.type` es
  `NOT NULL` y **sin default**, y `PositionRequest` no valida
  `positions.*.type`, asi que un cliente que omitiera el campo podia dejar la
  posicion fuera del mapa y del track del PIREP.
- Los logs (`type=2`) **no se ven afectados**: entran por `acars_logs` y
  `acars_events`, que fijan su propio tipo.
- Verificado con la suite completa: `OK (208 tests, 1166 assertions)`.

## [Vholar] 2026-10-01 — Actualizacion a phpVMS 7.0.10 (vholar-1.1.0)

Merge de la ultima release de la linea 7.x: **9 commits, 20 ficheros, 315
inserciones / 469 borrados**, sin migraciones ni cambios de dependencias.

- **Merge limpio**: `git merge 7.0.10` sin conflictos. Solo 2 ficheros se
  solapaban con los cambios propios y ambos se reconcilian solos:
  `app/Providers/RouteServiceProvider.php` (sobreviven las rutas propias de
  ACARS, desaparecen las del importador) y
  `app/Http/Controllers/Frontend/ProfileController.php` (entra la validacion de
  avatar de upstream y se mantiene el manejo propio del fichero).
- **Seguridad**: instalador endurecido, comprobacion de `.env` en vez de la
  tabla `users`, y validacion/sanitizacion de los campos de perfil.
- **Modulos**: dos arreglos del activador (`DatabaseActivator::setActive` con
  modulo nuevo, e invalidacion de la cache del manifiesto de nwidart), que es
  justo el mecanismo que usa esta instalacion (`modules.yml` + tabla `modules`).
- **Otros**: conteo de aeronaves por vuelo, parametro `post_date` en
  `FinanceService`, y eliminacion del importador antiguo (rutas + 8 vistas).
- `config/version.yml` se sube a **7.0.10** (upstream no lo actualiza en la
  rama), con lo que desaparece el aviso "New version 7.0.10 is available!" del
  panel de admin.
- Verificado por smoke test: dashboard de admin (sin aviso), flights, pireps,
  users, asignaciones, VmsOpenOps, VmsOpenFileManager, dashboard de piloto,
  listado y detalle de pirep (analisis ACARS), perfil y flights.

## [Vholar] 2026-10-01 — Auditoria de asignaciones de pilotos

### DisposableSpecial: registro en `activity_log`
- Generacion, edicion, alta y baja de asignaciones quedan registradas en
  `activity_log` con `log_name = 'assignments'`, el **usuario** que las hizo
  (`causer`) y propiedades legibles (mes, piloto, vuelo anterior/nuevo, contadores).
  Si lo dispara el **cron**, el `causer` queda `NULL` y `trigger = console`.
- `TriggerAssignment()` deja de reutilizar `$user` dentro del bucle: el objetivo
  del registro se captura aparte (era un shadowing latente).
- Guia de consulta y ejemplos SQL: `docs/auditoria-asignaciones.md`.

### Parche vendorizado regenerado
- `patches/DisposableSpecial-d1d776c.patch` **no incluia**
  `Resources/views/assignments/admin.blade.php` (710 lineas de la vista de admin
  de asignaciones). Regenerado contra `d1d776c` y verificado: aplica limpio sobre
  la base y reproduce el modulo actual byte a byte.
- `deploy/versions.yml`: el modulo declara ahora sus `local_changes`.

## [Vholar] 2026-09-30 — Versionado del repositorio (vholar-1.0.0)

Release que hace reproducible la instalacion: hasta ahora el tema, los modulos y
buena parte del codigo propio no estaban en git.

### Repositorio
- `.gitignore`: se versionan el tema `vholar` (`resources/views/layouts/vholar`),
  los modulos propios (`VmsOpenOps`, `VmsOpenFileManager`, `TestABC`) y los de
  terceros con parches (`DisposableSpecial`, `CHJumpSeat`); se ignora `.vscode/`.
- `deploy/versions.yml`: manifiesto que congela base de phpVMS, versiones de
  modulos, parches, settings y pasos de despliegue.
- `deploy/README.md`: esquema de versionado (tags `vholar-*`) y pendientes.
- Limpieza de ficheros basura en la raiz del repo.
- `public/assets/themes/vholar/css`: symlink al CSS del tema convertido a
  relativo (antes apuntaba a una ruta absoluta y no era portable).

### Modulos de terceros
- `DisposableSpecial` y `CHJumpSeat` se versionan con sus modificaciones locales
  aplicadas. Sus licencias prohiben redistribuir el codigo: es una **decision
  expresa del mantenedor** en un repositorio privado, asumiendo la
  responsabilidad. Los parches quedan ademas en `patches/` con su commit base.
- `DisposableBasic` sigue como submodulo (`f0b03db`, v3.7.4-2-g).

### Submodulos propios
- `VmsOpenOps` y `VmsOpenFileManager` se extraen a repos propios
  (`fhprietor/vmsOpenOps` 8a2f990 y `fhprietor/vmsOpenFileManager` f174900) y el
  repo central pasa a pinar el commit. `TestABC` sigue vendorizado (stub).
- Cada repo de modulo incluye su **README**: funcionalidad, rutas, modelo de
  datos, configuracion, instalacion y deuda tecnica verificada.

### Arreglos en VmsOpenFileManager
- **Migracion de liveries renumerada** de `000007` a `000004`: la tabla
  `migrations` ya registraba `000004`, asi que una instalacion limpia ejecutaba
  `000007` y fallaba con "table already exists".
- **Control de acceso en las descargas**: `folder()` exige `is_public` y solo
  lista hijos publicos; `download()` exige fichero `is_active` y carpeta
  publica; las liveries exigen `is_active` (tambien en `info`). Antes cualquier
  piloto autenticado podia descargar cualquier fichero o livery por su ID.
- Los contadores de descargas solo se incrementan cuando la descarga se sirve.

### Arreglos en VmsOpenOps
- **`GET /api/vmsopenops/operations/pending` devolvia 500**: llamaba a
  `OperationRequest::pendingForUser()`, que no existe. Ahora usa
  `pending()->forUser()`.
- **Eliminada la ruta `POST /vmsopenops/charter/aircraft`**: apuntaba a un metodo
  inexistente y ninguna vista la usaba.
- **El ferry por API nunca funcionaba**: comparaba `status` (`'A'`) con
  `AircraftState::PARKED` (`0`) y respondia 400 siempre. Ahora exige
  `state = PARKED` y `status = ACTIVE`, como el frontend.
- **Precios unificados** en `Support\OpsPricing`: el ferry por API multiplicaba
  por 100 y la vista cotizaba con la API mientras el cobro lo hacia el frontend.
  Ahora hay una sola formula (minimos de jumpseat y de ferry por MTOW incluidos).
- **Settings completos**: nueva migracion `000003` que siembra las 4 claves que
  faltaban (`jumpseat.min_cost` y los tres `ferry.min_cost_*`) y borra la fila
  huerfana con `id` vacio; `updateSettings` pasa a crear/actualizar **por `id`**
  (era el origen de esa fila).
- **Codigo muerto eliminado**: el `getData()` sin ruta del
  `Frontend\StatisticsController` (consultaba columnas inexistentes), los
  caracteres sueltos `要` de tres `<thead>` y un import sin usar.
- **Guardas redundantes**: la guarda `ability:admin,admin-access` estaba aplicada
  tres veces (grupo de rutas, `admin.php` y constructor del controlador); se
  conserva solo la del grupo.
- **`Config/config.php` pasa a ser la fuente de defaults**: el codigo lee
  `setting('vms_open_ops_x', config('vmsopenops.y'))` en controladores, modelo,
  notificaciones y vistas, y el fichero se completa con los minimos.
- **Previews duplicados eliminados**: la ruta de frontend `jumpseat/preview` y
  los metodos `preview()` de Jumpseat y Ferry; las vistas (incluidos los overrides
  del tema) cotizan con la API.

### Estado de modulos y datos
- `app/Database/seeds/modules.yml` refleja el estado real de activacion
  (activador `database`), incluyendo `CHJumpSeat` y `TestABC` como desactivados.
- Nueva migracion `2026_09_30_120000_backfill_pireps_source_name.php` que
  reproduce el backfill de `pireps.source_name` aplicado el 2026-09-29
  (`CrewSystem/import` para importaciones, `manual` para PIREPs de panel).

## [Vholar] 2026-05-17 — Discord notifications, ACARS parser fixes & pirep score

### Discord Notifications
- **Avatar fallback:** all 6 broadcast notifications (`PirepFiled`, `PirepStatusChanged`, `PirepDiverted`, `PirepPrefiled`, `UserRankChanged`, `AwardAwarded`) now use `url('/images/logo.png')` as fallback when the pilot has no avatar, replacing the phpVMS generic Gravatar image.
- **PirepFiled description:** second line added with `Score: N` and bonuses inline (`| +N pts: reason`) when log data is available.
- **PirepStatusChanged (LANDED):** second line added with `Landing rate: N fpm | G-Force: N.NNg` when available. Bonuses NOT included — LANDED fires before the full log is written.

### ACARS Log Parser (`FlightAnalysisHelper::parseLogData`)
- **Bonuses:** new `bonuses` array in result. Regex: `BONIFICACIÓN reason: +N pts` / `BONUS reason: +N pts` (bilingual). Displayed in the Advanced Flight Analysis card as green `+N pts` lines.
- **Takeoff context without header:** `DESPEGUE DETECTADO` now sets `$context = 'takeoff'` directly, so logs that omit the `DATOS DE DESPEGUE:` section header still parse `Velocidad de Rotación`, `Cabeceo|Alabeo`, `N1`, `Flaps` correctly.
- **Takeoff context clear:** added `Tren de aterrizaje: UP` (Spanish gear-up) alongside `Gear UP` to end the takeoff context.
- **QNH delta first-occurrence only:** `qnh_delta` is now set only once (first match), preventing the destination QNH line from overwriting the departure delta.
- **Penalty guard:** also excludes lines containing `PENALIZACIÓN:` (early Spanish warning lines), not just `PENALTY:`.

### Pirep Detail (`/pireps/{id}`) — Advanced Flight Analysis card
- **Card visibility:** guard changed from `@if($sc)` to `@if($hasAnalysis)` — card appears when there are penalties or bonuses even if there is no score line.
- **Score fallback:** when log has no score line, `$pirep->score` from the database is used as fallback (shown without rating label). Priority: log score → DB score → hidden.
- **Score column conditional:** `col-4` score column only renders when `$sc` is set; otherwise penalties/bonuses take full `col-12`.
- **Unidentified penalties:** when score comes from DB fallback and log penalties/bonuses don't reconcile mathematically, a `−N pts Penalizaciones no identificadas` line is appended in italics.
- **Bonuses displayed:** green `+N pts reason` lines shown in a "Bonificaciones" section after penalties.

### Dashboard (`/dashboard`)
- **Score column** added to "Your last report" recent pireps table: green ≥ 80, yellow ≥ 60, red < 60, `—` when null.

## [Vholar] 2026-05-16 — Pirep detail redesign & bilingual ACARS parser

### Pirep Detail (`/pireps/{id}`) — layout restructure
- **Auth-protected:** route moved from public group to authenticated group in `RouteServiceProvider.php`; unauthenticated visitors are redirected to login. Short URL `r/{id}` remains public.
- **Two-column layout:** left `col-8` now stacks (1) flight description card, (2) Advanced Flight Analysis + score/penalties, (3) Takeoff/Landing cards side by side (`col-6`/`col-6`), (4) approach capture bar — all within the same column alongside the right sidebar.
- **Right sidebar (`col-4`):** removed redundant Score and Landing Rate items (now shown with more detail in left-column analysis cards).
- **Full-width sections below:** Map → Flight Log → SimBrief OFP → Altitude Profile.

### ACARS log parser — bilingual EN/ES support (`FlightAnalysisHelper::parseLogData`)
- All regexes updated to match both English and Spanish ACARS client output via alternation.
- Added Spanish context markers: `DATOS DE DESPEGUE` (= `ACCURATE TAKEOFF DATA`), `DATOS DE ATERRIZAJE` (= `ACCURATE TOUCHDOWN DATA`).
- Added context end triggers: `Gear UP` closes takeoff context; `PIREP Status: TXI` closes landing context.
- Takeoff: `DESPEGUE DETECTADO - Vel:` ↔ `TAKEOFF DETECTED.*Speed:`, standalone `Velocidad en Tierra:` for GS, `Cabeceo:/Alabeo:` and `Pitch:/Bank:` for pitch/bank inside context block.
- Landing: `Aterrizaje registrado:.*Rumbo:.*Cabeceo:.*Alabeo:` ↔ `Landing recorded:.*Heading:.*Pitch:.*Bank:`, `Fuerza G:` ↔ `G-Force:`, `Reversas:` ↔ `Reversers:`, standalone `Velocidad: X kts (IAS) / Y kts (GS)` and `Flaps: X% | Spoilers: Y%`.
- Approach: `COMPUERTA DE APROXIMACIÓN.*ESTABILIZADA/INESTABLE` ↔ `APPROACH GATE.*STABILIZED/UNSTABILIZED`; `APPROACH CAPTURE: RWY X | AGL X ft | Dist X NM` ↔ `INICIO CAPTURA … PISTA …`.
- Score: `Score:` ↔ `Puntuación:` — **critical**: score absence suppresses the entire score+penalties card (`@if($sc)`).
- Network: `Connected on IVAO (VID …)` ↔ `Conectado en IVAO (VID …)`.
- OAT/Wind: `Viento:` ↔ `Wind:`.

## [Vholar] 2026-05-13 — UI polish & SimBrief Dispatch

### Vholar Theme — Weather widget
- `widgets/weather.blade.php` replaced with metar-taf.com embed (token `ons7HDjV`, account-specific)
- ICAO is dynamic: uses `$config['icao']` (the Weather widget class passes params as `$config`, not as individual variables)
- Scaled 80% via CSS transform inside a `240px × 348px; overflow:hidden` wrapper to fit the dashboard sidebar
- Embed constraints: `id="metartaf-{token}"` and `target={token}` must match exactly; only the ICAO path segment and `bg_color` param are safely changeable

### Vholar Theme — `/flights` & `/bids`
- Country flags added to both route-point airports in flight cards (`flights/table.blade.php`)
- Departure flag: `display:flex; align-items:center; gap:6px` wrapper (left-aligned, flag left of ICAO)
- Arrival flag: `display:inline-flex; align-items:center; gap:6px; text-align:left` — `inline-flex` floats right via parent `text-align:right` CSS; `text-align:left` on the wrapper neutralises inheritance so ICAO pill sits at the left edge of the text div (adjacent to the flag)

### Vholar Theme — `/dassignments` (frontend pilot view)
- SCHEDULED label changes to **BLOCK TIMES** for completed assignments where actual block data is available
- Airport flags: changed from `display:flex` + `vertical-align:middle` to `display:inline-flex; align-items:center; gap:6px` wrapper, same pattern as `/flights`

### SimBrief Direct Dispatch (new shared component)
- New partial: `resources/views/layouts/vholar/components/simbrief-dispatch-modal.blade.php`
- Included via `@include('vholar::components.simbrief-dispatch-modal')` in `flights/table.blade.php` and `modules/DisposableSpecial/assignments/index.blade.php`
- Button `sb-dispatch-btn` appears on `/flights`, `/bids`, `/dassignments` when a bid has a reserved aircraft (`$isBid && $hasReservedAircraft`)
- Modal fields: Vuelo, Tipo Aeronave, Matrícula, Comandante (all readonly), Salida UTC (now+40 min), Nivel de Vuelo ft (default by aircraft family), Cost Index, Tipo Vuelo, PAX (random by type), Cargo kgs, Pista Salida, Pista Llegada, Ruta
- "Generar OFP" builds SimBrief custom dispatch URL and opens in new tab, then closes modal
- Key URL params: `fl` = feet/100 (e.g. 36000 ft → `fl=360`), `origrwy`, `destrwy` (not `deprwy`/`arrrwy`), `extrarmk=OPR/{airline} CS/VHOLAR IVAOVA/{airline}`
- JS guard `window._sbDispatchInit` prevents double-registration when partial is included on the same page twice
- Close buttons use explicit `sbModal.hide()` (not `data-bs-dismiss`) to avoid Bootstrap conflict when modal was instantiated manually with `new bootstrap.Modal()`

## [7.0.0-beta.4](https://github.com/nabeelio/phpvms/tree/7.0.0-beta.4) (2020-05-09)

[Full Changelog](https://github.com/nabeelio/phpvms/compare/7.0.0-beta.3...7.0.0-beta.4)

**Implemented enhancements:**

- Move currency selection to admin settings [\#671](https://github.com/nabeelio/phpvms/issues/671)
- Center live map to custom location [\#661](https://github.com/nabeelio/phpvms/issues/661)
- Pilot Names -  Pilots ID [\#656](https://github.com/nabeelio/phpvms/issues/656)
- Simbrief sample data [\#651](https://github.com/nabeelio/phpvms/issues/651)
- Simbrief only available for bids [\#642](https://github.com/nabeelio/phpvms/issues/642)
- PIREP prefile download [\#634](https://github.com/nabeelio/phpvms/issues/634)
- Importer - group imported aircraft by type [\#628](https://github.com/nabeelio/phpvms/issues/628)
- API: When looking up flight, return the load factor [\#611](https://github.com/nabeelio/phpvms/issues/611)
- API: On prefile, return required fields [\#610](https://github.com/nabeelio/phpvms/issues/610)
- Center on active flights [\#605](https://github.com/nabeelio/phpvms/issues/605)
- Profiling/performance improvments [\#602](https://github.com/nabeelio/phpvms/issues/602)
- Aircraft stats resync [\#585](https://github.com/nabeelio/phpvms/issues/585)
- Cron Job [\#553](https://github.com/nabeelio/phpvms/issues/553)
- Importing a CSV with additional airports clears all previous airports [\#490](https://github.com/nabeelio/phpvms/issues/490)
- Use continuation token instead of page/offset [\#469](https://github.com/nabeelio/phpvms/issues/469)
- Combine the Util classes in Facade and Support [\#467](https://github.com/nabeelio/phpvms/issues/467)
- ACARS/Live flight events [\#436](https://github.com/nabeelio/phpvms/issues/436)
- Allow logo upload for airline [\#417](https://github.com/nabeelio/phpvms/issues/417)
- Google Analytics Settings [\#382](https://github.com/nabeelio/phpvms/issues/382)
- Let update only be run if logged in/admin [\#372](https://github.com/nabeelio/phpvms/issues/372)
- Airport Lookup - possible additions [\#364](https://github.com/nabeelio/phpvms/issues/364)
- Set flight type for expenses for flight [\#348](https://github.com/nabeelio/phpvms/issues/348)
- Extend search schedule options [\#297](https://github.com/nabeelio/phpvms/issues/297)
- Change currency from settings \#671 [\#672](https://github.com/nabeelio/phpvms/pull/672)
- Add privatized version of name \#656 [\#658](https://github.com/nabeelio/phpvms/pull/658)
- Add Simbrief entries as sample data for load \#651 [\#652](https://github.com/nabeelio/phpvms/pull/652)
- Option for SB only on bids; add the new file type \#642 [\#643](https://github.com/nabeelio/phpvms/pull/643)
- Account ICAO for subfleet, airline/location if columns exist \#628 [\#632](https://github.com/nabeelio/phpvms/pull/632)
- Performance improvements \#602 [\#607](https://github.com/nabeelio/phpvms/pull/607)
- Center on active flights \#605 [\#606](https://github.com/nabeelio/phpvms/pull/606)
- Installer fixes, remove bcmath, fix design [\#596](https://github.com/nabeelio/phpvms/pull/596)
- Remove airport length restrictions \#590 [\#592](https://github.com/nabeelio/phpvms/pull/592)
- Remove 4 char restriction from ICAO; use decimal type for lat/lon \#590 [\#591](https://github.com/nabeelio/phpvms/pull/591)
- Recalcuate aircraft stats in nightly cron and importer \#585 \#443 [\#587](https://github.com/nabeelio/phpvms/pull/587)
- \(Importer\) Set the pay rate for the ranks \#443 [\#586](https://github.com/nabeelio/phpvms/pull/586)
- Fix rowmapper generator, check for fields, map users \#443 [\#583](https://github.com/nabeelio/phpvms/pull/583)
- Add bcmath to the required extensions list \#558 [\#564](https://github.com/nabeelio/phpvms/pull/564)
- Add the argc\_argv flag for the cgi exec \#553 [\#556](https://github.com/nabeelio/phpvms/pull/556)
- Add logo to the flights page if its set \#417 [\#550](https://github.com/nabeelio/phpvms/pull/550)
- Add additional events for PIREP states \#436 [\#548](https://github.com/nabeelio/phpvms/pull/548)
- Cleanup Utils \#467 [\#547](https://github.com/nabeelio/phpvms/pull/547)
- Add checkbox to clear previous data when importing \#490 [\#546](https://github.com/nabeelio/phpvms/pull/546)
- Distro zip file from tar file \#449 [\#543](https://github.com/nabeelio/phpvms/pull/543)
- Spanish files translation [\#542](https://github.com/nabeelio/phpvms/pull/542)
- Add link to download ACARS config from profile [\#539](https://github.com/nabeelio/phpvms/pull/539)
- Format all blade templates to 2 spaces \#530 [\#531](https://github.com/nabeelio/phpvms/pull/531)
- Add menu bar for mobile [\#529](https://github.com/nabeelio/phpvms/pull/529)
- Fix live and route map errors \#527 [\#528](https://github.com/nabeelio/phpvms/pull/528)
- Set PIREPs page to public [\#526](https://github.com/nabeelio/phpvms/pull/526)
- Try to clear caches before updating [\#522](https://github.com/nabeelio/phpvms/pull/522)
- Reduce number of queries for update check [\#520](https://github.com/nabeelio/phpvms/pull/520)
- Add getRootDomain\(\) to Utils [\#514](https://github.com/nabeelio/phpvms/pull/514)
- Search flights by subfleet \#484 [\#506](https://github.com/nabeelio/phpvms/pull/506)
- Add base Dockerfile for Dockerhub upload [\#504](https://github.com/nabeelio/phpvms/pull/504)
- Add reference to docs on doc site [\#502](https://github.com/nabeelio/phpvms/pull/502)
- Add new command to export a specific PIREP for debugging [\#501](https://github.com/nabeelio/phpvms/pull/501)
- Flight search for departure/arrival airports not using column names [\#496](https://github.com/nabeelio/phpvms/pull/496)
- Split the importer module out from the installer module [\#468](https://github.com/nabeelio/phpvms/pull/468)

**Fixed bugs:**

- Aircraft hours not counting [\#683](https://github.com/nabeelio/phpvms/issues/683)
- Error creating first user/airline [\#677](https://github.com/nabeelio/phpvms/issues/677)
- submitting PIREP: Page Not Found [\#676](https://github.com/nabeelio/phpvms/issues/676)
- Filing manual report causes SimBrief Error [\#668](https://github.com/nabeelio/phpvms/issues/668)
- Error on finance page in admin [\#667](https://github.com/nabeelio/phpvms/issues/667)
- API: User bids aren't loading SimBrief data for flight [\#664](https://github.com/nabeelio/phpvms/issues/664)
- .: Links to pilot profiles :. [\#655](https://github.com/nabeelio/phpvms/issues/655)
- Some domains \(ex, .co.uk\) not properly parsed [\#647](https://github.com/nabeelio/phpvms/issues/647)
- API: Load bids through the bids.flight fields, not the flights field on the user [\#639](https://github.com/nabeelio/phpvms/issues/639)
- Login page not using theme [\#638](https://github.com/nabeelio/phpvms/issues/638)
- CSV import error says "csv not found" [\#636](https://github.com/nabeelio/phpvms/issues/636)
- Importer - schedules not importing properly [\#630](https://github.com/nabeelio/phpvms/issues/630)
- Disable using of php opcache [\#625](https://github.com/nabeelio/phpvms/issues/625)
- API: Flight fields are an array when empty [\#618](https://github.com/nabeelio/phpvms/issues/618)
- "Pending Pireps" in admin is blank [\#616](https://github.com/nabeelio/phpvms/issues/616)
- Flight fields show all fields, not custom added ones [\#614](https://github.com/nabeelio/phpvms/issues/614)
- Mixed content causes error [\#612](https://github.com/nabeelio/phpvms/issues/612)
- Fares not appearing in API response for user/subfleets [\#608](https://github.com/nabeelio/phpvms/issues/608)
- Not selecting active for aircraft shows nullable error [\#603](https://github.com/nabeelio/phpvms/issues/603)
- Airports page repeats airport name \(infinite loop\) [\#599](https://github.com/nabeelio/phpvms/issues/599)
- Calculate Distance button not working  [\#595](https://github.com/nabeelio/phpvms/issues/595)
- Support Seaports with 3 letter IATA/ID codes [\#590](https://github.com/nabeelio/phpvms/issues/590)
- Hubs only show for registration [\#580](https://github.com/nabeelio/phpvms/issues/580)
- Error when updating user in admin [\#576](https://github.com/nabeelio/phpvms/issues/576)
- Updater not working because Source repository \[github\] is not defined [\#575](https://github.com/nabeelio/phpvms/issues/575)
- Flights Page Search Subfleet Column [\#574](https://github.com/nabeelio/phpvms/issues/574)
- Duplicate key entry for ACARS data [\#572](https://github.com/nabeelio/phpvms/issues/572)
- No hint path defined for updater [\#570](https://github.com/nabeelio/phpvms/issues/570)
- My Bids Page Error [\#561](https://github.com/nabeelio/phpvms/issues/561)
- I can't change Current Theme [\#554](https://github.com/nabeelio/phpvms/issues/554)
- Pilots cannot use the dashboard or flights without admin rights [\#480](https://github.com/nabeelio/phpvms/issues/480)
- Database prefix not being applied [\#442](https://github.com/nabeelio/phpvms/issues/442)
- Install not working [\#411](https://github.com/nabeelio/phpvms/issues/411)
- typo in dependency on 'leaflet.geodesic' [\#687](https://github.com/nabeelio/phpvms/pull/687)
- Emails/notifications not sending \#675 [\#686](https://github.com/nabeelio/phpvms/pull/686)
- Aircraft hours not showing/incrementing \#683 [\#684](https://github.com/nabeelio/phpvms/pull/684)
- METAR: KM as unit in visibility \#680 [\#682](https://github.com/nabeelio/phpvms/pull/682)
- Blank/null IATA code isn't unique when added \#679 [\#681](https://github.com/nabeelio/phpvms/pull/681)
- Fix airline creation in installer \#677 [\#678](https://github.com/nabeelio/phpvms/pull/678)
- Journal not created when airline is created \#667 [\#670](https://github.com/nabeelio/phpvms/pull/670)
- Check for SB being avail before filing \#668 [\#669](https://github.com/nabeelio/phpvms/pull/669)
- Load Simbrief and flight data when getting bids \#664 [\#665](https://github.com/nabeelio/phpvms/pull/665)
- Fix pilots page not showing up \#655 [\#660](https://github.com/nabeelio/phpvms/pull/660)
- Fix PirepComment response; 201 to 200 [\#654](https://github.com/nabeelio/phpvms/pull/654)
- Domain detection failing for .co.uk, etc \#647 [\#648](https://github.com/nabeelio/phpvms/pull/648)
- Auth routes not using themes \#638 [\#645](https://github.com/nabeelio/phpvms/pull/645)
- Don't return bids by default, fix user flight column \#639 [\#640](https://github.com/nabeelio/phpvms/pull/640)
- Account for admin role, don't error out on null rows \#632 [\#637](https://github.com/nabeelio/phpvms/pull/637)
- Fix airline mapping for aircraft \#628 [\#633](https://github.com/nabeelio/phpvms/pull/633)
- Increase ID column size; seed ID generator with uniqid\(\) \#630 [\#631](https://github.com/nabeelio/phpvms/pull/631)
- Disable using apc/opcache by default if found \#625 [\#629](https://github.com/nabeelio/phpvms/pull/629)
- Fix saving of fare \#621 [\#624](https://github.com/nabeelio/phpvms/pull/624)
- API: Flight fields are an array when empty \#618 [\#619](https://github.com/nabeelio/phpvms/pull/619)
- Remove the flight fields link from admin \#614 [\#615](https://github.com/nabeelio/phpvms/pull/615)
- HTTP/HTTPS mixed content errors \#612 [\#613](https://github.com/nabeelio/phpvms/pull/613)
- Fare information not included in subfleet response \#608 [\#609](https://github.com/nabeelio/phpvms/pull/609)
- Aircraft status field blank causes error \#603 [\#604](https://github.com/nabeelio/phpvms/pull/604)
- METAR parsing infinite loop bugfix \#599 [\#600](https://github.com/nabeelio/phpvms/pull/600)
- Table prefixes not being added to indexes [\#597](https://github.com/nabeelio/phpvms/pull/597)
- Import expense and ledger entries \#443 [\#588](https://github.com/nabeelio/phpvms/pull/588)
- User country mapping; ignore unused groups \#443  [\#584](https://github.com/nabeelio/phpvms/pull/584)
- Change fuel used to optional \#512 [\#582](https://github.com/nabeelio/phpvms/pull/582)
- Respect home hubs setting for registration \#580 [\#581](https://github.com/nabeelio/phpvms/pull/581)
- Upstream null version; build version tags not being saved properly \#575 [\#578](https://github.com/nabeelio/phpvms/pull/578)
- Fix edit pilot error \(Facade\Utils missing\) \#576 [\#577](https://github.com/nabeelio/phpvms/pull/577)
- Upsert on ACARS positions \#572 [\#573](https://github.com/nabeelio/phpvms/pull/573)
- Fix the namespace path on updater templates \#570 [\#571](https://github.com/nabeelio/phpvms/pull/571)
- Route not found error for Pirep::resource\(\) call \#559 [\#565](https://github.com/nabeelio/phpvms/pull/565)
- Add extra check for $acars being set \#560 [\#563](https://github.com/nabeelio/phpvms/pull/563)
- Missing $subfleet error on bids page \#561 [\#562](https://github.com/nabeelio/phpvms/pull/562)
- Refresh theme cache on settings page load \#554 [\#557](https://github.com/nabeelio/phpvms/pull/557)
- Fix migrations when table prefix is involved \#442 [\#555](https://github.com/nabeelio/phpvms/pull/555)
- Use optional\(\) around the airport fields [\#537](https://github.com/nabeelio/phpvms/pull/537)
- PIREPS resource except for show [\#536](https://github.com/nabeelio/phpvms/pull/536)
- Fix import during flight cron \#532 [\#535](https://github.com/nabeelio/phpvms/pull/535)
- Fix PIREP edit endpoint [\#534](https://github.com/nabeelio/phpvms/pull/534)
- Add a public\_url\(\) helper \#513 [\#519](https://github.com/nabeelio/phpvms/pull/519)
- Correct text for no subfleets \#507 [\#518](https://github.com/nabeelio/phpvms/pull/518)
- Error page not showing when database isn't configured [\#517](https://github.com/nabeelio/phpvms/pull/517)
- Check user permissions on the routes \#508 [\#516](https://github.com/nabeelio/phpvms/pull/516)
- Show admin dropdown for admin-access ability [\#515](https://github.com/nabeelio/phpvms/pull/515)
- New subfleet not being attached to an airline on import \#479 [\#505](https://github.com/nabeelio/phpvms/pull/505)
- Importing not updating existing items \#486 [\#503](https://github.com/nabeelio/phpvms/pull/503)
- Set a default model value for airports on PIREP [\#500](https://github.com/nabeelio/phpvms/pull/500)
- Fix GeoService errors when viewing PIREP \#498 [\#499](https://github.com/nabeelio/phpvms/pull/499)
- Properly set the distance/planned\_distance [\#497](https://github.com/nabeelio/phpvms/pull/497)
- 491 Installation Error [\#495](https://github.com/nabeelio/phpvms/pull/495)
- Return the flight fares if there are no subfleet fares \#488 [\#489](https://github.com/nabeelio/phpvms/pull/489)
- Error if there are no roles when editing a user \#480 [\#483](https://github.com/nabeelio/phpvms/pull/483)
- Allow nullable field and calculate distance if nulled [\#482](https://github.com/nabeelio/phpvms/pull/482)
- Pilots cannot use the dashboard or flights without admin rights [\#481](https://github.com/nabeelio/phpvms/pull/481)
- Fix BindingResolutionError when debug toolbar isn't present [\#465](https://github.com/nabeelio/phpvms/pull/465)
- Fix CSV imports giving Storage class not found \#454 [\#462](https://github.com/nabeelio/phpvms/pull/462)
- Use PhpExecutableFinder\(\) closes \#457 \#458 [\#460](https://github.com/nabeelio/phpvms/pull/460)
- Avoid proc\_open use \#455 [\#456](https://github.com/nabeelio/phpvms/pull/456)
- Stricter checks on ACARS API data [\#451](https://github.com/nabeelio/phpvms/pull/451)
- Remove bootstrap cache [\#448](https://github.com/nabeelio/phpvms/pull/448)

**Closed issues:**

- Dashboard error when METAR visibility in KM [\#680](https://github.com/nabeelio/phpvms/issues/680)
- Error adding multiple airlines without IATA codes [\#679](https://github.com/nabeelio/phpvms/issues/679)
- Pages [\#641](https://github.com/nabeelio/phpvms/issues/641)
- Create a "fare type" for cargo [\#621](https://github.com/nabeelio/phpvms/issues/621)
- Split Sample module out into separate repo [\#593](https://github.com/nabeelio/phpvms/issues/593)
- No way to add files to fleet [\#567](https://github.com/nabeelio/phpvms/issues/567)
- Map Center Coords [\#566](https://github.com/nabeelio/phpvms/issues/566)
- Pilot Profile Page Error [\#560](https://github.com/nabeelio/phpvms/issues/560)
- File New Pirep [\#559](https://github.com/nabeelio/phpvms/issues/559)
- I can't add new flight [\#558](https://github.com/nabeelio/phpvms/issues/558)
- Pilot Pay route modifier [\#487](https://github.com/nabeelio/phpvms/issues/487)
- Auto-update [\#449](https://github.com/nabeelio/phpvms/issues/449)
- Setting to not automatically calculate distances [\#446](https://github.com/nabeelio/phpvms/issues/446)
- Classic importer [\#443](https://github.com/nabeelio/phpvms/issues/443)
- SimBrief integration - pirep prefile [\#405](https://github.com/nabeelio/phpvms/issues/405)
- PIREP rejection doesn't move pilot back to previous airport [\#392](https://github.com/nabeelio/phpvms/issues/392)
- Load Factor for flights [\#352](https://github.com/nabeelio/phpvms/issues/352)

**Merged pull requests:**

- Upgrade packages with security alerts [\#653](https://github.com/nabeelio/phpvms/pull/653)
- Add public/private pages \#641 [\#644](https://github.com/nabeelio/phpvms/pull/644)
- SimBrief integration \#405 [\#635](https://github.com/nabeelio/phpvms/pull/635)
- Add fare type for pax/cargo/mixed flights \#621 [\#623](https://github.com/nabeelio/phpvms/pull/623)
- Add fixed pilot pay for a flight \#487 [\#622](https://github.com/nabeelio/phpvms/pull/622)
- Add load factor and variance to flights \#352 [\#620](https://github.com/nabeelio/phpvms/pull/620)
- Module/plugin installation working \#593 [\#594](https://github.com/nabeelio/phpvms/pull/594)
- Add Google Analytics tracking ID to settings and to main template \#382 [\#551](https://github.com/nabeelio/phpvms/pull/551)
- Set expenses on specific flight types \#348 [\#549](https://github.com/nabeelio/phpvms/pull/549)
- Auto update \#449 [\#545](https://github.com/nabeelio/phpvms/pull/545)
- Auto-update functionality \#449 [\#544](https://github.com/nabeelio/phpvms/pull/544)
- ACARS XML config file download [\#540](https://github.com/nabeelio/phpvms/pull/540)
- Make the fuel used optional [\#512](https://github.com/nabeelio/phpvms/pull/512)
- Add PHP 7.4 support [\#464](https://github.com/nabeelio/phpvms/pull/464)
- Replace importer with AJAX powered; better error handling \#443 [\#447](https://github.com/nabeelio/phpvms/pull/447)
- Fix missing newsRepo variable [\#445](https://github.com/nabeelio/phpvms/pull/445)
- Refactor and add importer to Installer module \#443 [\#444](https://github.com/nabeelio/phpvms/pull/444)
- 391 Notification refactorings [\#441](https://github.com/nabeelio/phpvms/pull/441)

## [7.0.0-beta.3](https://github.com/nabeelio/phpvms/tree/7.0.0-beta.3) (2020-02-08)

[Full Changelog](https://github.com/nabeelio/phpvms/compare/7.0.0-beta.2...7.0.0-beta.3)

**Implemented enhancements:**

- Fix formatting in blade files [\#530](https://github.com/nabeelio/phpvms/issues/530)
- Make PIREP view page public [\#525](https://github.com/nabeelio/phpvms/issues/525)
- Fix map line colors [\#523](https://github.com/nabeelio/phpvms/issues/523)
- Null-able fuel values on PIREP [\#510](https://github.com/nabeelio/phpvms/issues/510)
- include subfleet in search options [\#484](https://github.com/nabeelio/phpvms/issues/484)
- PHP 7.4 in Travis [\#463](https://github.com/nabeelio/phpvms/issues/463)
- Move cron problem report to main page [\#458](https://github.com/nabeelio/phpvms/issues/458)

**Fixed bugs:**

- Method GET for /pireps/\<id\>/edit not supported [\#533](https://github.com/nabeelio/phpvms/issues/533)
- Cron error for removing expired flights [\#532](https://github.com/nabeelio/phpvms/issues/532)
- Live map marker click not working [\#527](https://github.com/nabeelio/phpvms/issues/527)
- Redirect to installer properly [\#513](https://github.com/nabeelio/phpvms/issues/513)
- Admin panel link not shown for users in role group [\#509](https://github.com/nabeelio/phpvms/issues/509)
- Users without "admin" role can modify roles [\#508](https://github.com/nabeelio/phpvms/issues/508)
- Edit flights page - text error [\#507](https://github.com/nabeelio/phpvms/issues/507)
- Installation error with external databases [\#494](https://github.com/nabeelio/phpvms/issues/494)
- Pirep not in KG [\#493](https://github.com/nabeelio/phpvms/issues/493)
- Installation Error step2 [\#491](https://github.com/nabeelio/phpvms/issues/491)
- Fares assigned to flights not working/overwritten by Subfleet [\#488](https://github.com/nabeelio/phpvms/issues/488)
- Status not changing when importing CSV for aircraft [\#486](https://github.com/nabeelio/phpvms/issues/486)
- Default Theme unusable on Mobile [\#485](https://github.com/nabeelio/phpvms/issues/485)
- Fatal Error in subfleet page when importing routes with new aircraft [\#479](https://github.com/nabeelio/phpvms/issues/479)
- Flights CSV import fails if no distance stipulated [\#478](https://github.com/nabeelio/phpvms/issues/478)
- Subfleet CSV import [\#476](https://github.com/nabeelio/phpvms/issues/476)
- Cron - DateTime::construct\(\) expects parameter 2 to be DateTimeZone, int given [\#461](https://github.com/nabeelio/phpvms/issues/461)
- if PHP\_CLI not found in cron maintenance, put generic `php` exec there [\#457](https://github.com/nabeelio/phpvms/issues/457)
- Importing schedule bug [\#454](https://github.com/nabeelio/phpvms/issues/454)
- The updater/installer should clear all caches [\#408](https://github.com/nabeelio/phpvms/issues/408)

**Closed issues:**

- Error 500 when trying to access Dashboard. [\#498](https://github.com/nabeelio/phpvms/issues/498)
- Import multiple aircraft of different subtypes in one sheet [\#477](https://github.com/nabeelio/phpvms/issues/477)
- Split out installer/importer from updater [\#453](https://github.com/nabeelio/phpvms/issues/453)
- ACARS update fields can be nulled [\#450](https://github.com/nabeelio/phpvms/issues/450)
- Retryable HTTP client [\#430](https://github.com/nabeelio/phpvms/issues/430)
- Pireps page: 'Pending' doesn't show pending PIREPs [\#427](https://github.com/nabeelio/phpvms/issues/427)
- Email when news sent out [\#391](https://github.com/nabeelio/phpvms/issues/391)
- Save cron last run time [\#386](https://github.com/nabeelio/phpvms/issues/386)

**Merged pull requests:**

- 7.0.0-beta3 Release [\#541](https://github.com/nabeelio/phpvms/pull/541)

## [7.0.0-beta.2](https://github.com/nabeelio/phpvms/tree/7.0.0-beta.2) (2019-11-19)

[Full Changelog](https://github.com/nabeelio/phpvms/compare/v7.0.0-beta...7.0.0-beta.2)

**Implemented enhancements:**

- CSS Love needed for the Flights Page  [\#418](https://github.com/nabeelio/phpvms/issues/418)
- Add Subfleet, Custom Fields and Fares during initial schedule entry [\#410](https://github.com/nabeelio/phpvms/issues/410)
- PIREP from flight/bid; orig: Bid not removed after flight accepted [\#406](https://github.com/nabeelio/phpvms/issues/406)
- Auto airport lookup [\#404](https://github.com/nabeelio/phpvms/issues/404)
- API Changes [\#389](https://github.com/nabeelio/phpvms/issues/389)
- "Maintenance" section in admin [\#376](https://github.com/nabeelio/phpvms/issues/376)
- Auto-calculate Distance on Add Flight page [\#355](https://github.com/nabeelio/phpvms/issues/355)
- Change PIREP fuel field type [\#340](https://github.com/nabeelio/phpvms/issues/340)
- Fuel in pireps [\#295](https://github.com/nabeelio/phpvms/issues/295)
- Add field to set aircraft location [\#277](https://github.com/nabeelio/phpvms/issues/277)
- Suggestion: edit pilot ID [\#265](https://github.com/nabeelio/phpvms/issues/265)
- Combine subfleet and aircraft export [\#233](https://github.com/nabeelio/phpvms/issues/233)
- Allow URL for downloads [\#229](https://github.com/nabeelio/phpvms/issues/229)
- Move settings into database seeder [\#224](https://github.com/nabeelio/phpvms/issues/224)
- Allow specifying transfer hours on registration [\#129](https://github.com/nabeelio/phpvms/issues/129)
- Backend changes separating id from pilot\_id [\#324](https://github.com/nabeelio/phpvms/pull/324)

**Fixed bugs:**

- Flight ID's and Airport ICAO's not clickable on airport page [\#419](https://github.com/nabeelio/phpvms/issues/419)
- Distance lookup needs bcmath extension [\#407](https://github.com/nabeelio/phpvms/issues/407)
- Missing flight level field on frontend [\#401](https://github.com/nabeelio/phpvms/issues/401)
- Set distance to 0 if no value [\#400](https://github.com/nabeelio/phpvms/issues/400)
- Upload livery via admin [\#398](https://github.com/nabeelio/phpvms/issues/398)
- Block deleting airline if there are assets associated with it [\#367](https://github.com/nabeelio/phpvms/issues/367)
- Cron not calculating number of flights [\#357](https://github.com/nabeelio/phpvms/issues/357)
- Error Seeing data of pilot profile [\#351](https://github.com/nabeelio/phpvms/issues/351)
- Fresh install doesn't create rank [\#346](https://github.com/nabeelio/phpvms/issues/346)
- Fuel cost only allows whole numbers for price [\#342](https://github.com/nabeelio/phpvms/issues/342)
- API calls in subfolder going to root domain [\#339](https://github.com/nabeelio/phpvms/issues/339)
- Errors versión 7.0.0 \(compilación 181025-6f7735\) [\#332](https://github.com/nabeelio/phpvms/issues/332)
- Error on Install [\#303](https://github.com/nabeelio/phpvms/issues/303)
- Having a "Error 505" when trying to install PHPVMS [\#302](https://github.com/nabeelio/phpvms/issues/302)
- Error when viewing profile or trying to add flights [\#291](https://github.com/nabeelio/phpvms/issues/291)
- "Hubs as home airport" doesn't work [\#288](https://github.com/nabeelio/phpvms/issues/288)
- Undocumented Extension Required - PHP 7.2 GMP [\#285](https://github.com/nabeelio/phpvms/issues/285)
- ErrorException  Trying to get property 'name' of non-object [\#281](https://github.com/nabeelio/phpvms/issues/281)

**Closed issues:**

- Add settings.yaml changes to update available check [\#437](https://github.com/nabeelio/phpvms/issues/437)
- Write current version to DB [\#428](https://github.com/nabeelio/phpvms/issues/428)
- Message for configuring cron [\#424](https://github.com/nabeelio/phpvms/issues/424)
- Opt in for telemetry on install [\#415](https://github.com/nabeelio/phpvms/issues/415)
- Error when saving a PIREP without filling some fields and attempting to edit it [\#414](https://github.com/nabeelio/phpvms/issues/414)
- Update htaccess file [\#412](https://github.com/nabeelio/phpvms/issues/412)
- Allow typing in drop down menus [\#409](https://github.com/nabeelio/phpvms/issues/409)
- Malformed email error on file [\#402](https://github.com/nabeelio/phpvms/issues/402)
- Residual design issues from Bootstrap update [\#397](https://github.com/nabeelio/phpvms/issues/397)
- Don't allow PIREP cancel if not in PENDING phase [\#395](https://github.com/nabeelio/phpvms/issues/395)
- Pageable Criteria [\#390](https://github.com/nabeelio/phpvms/issues/390)
- Update CSS Libraries [\#387](https://github.com/nabeelio/phpvms/issues/387)
- Laravel 6 [\#384](https://github.com/nabeelio/phpvms/issues/384)
- Profile menu dropdown [\#383](https://github.com/nabeelio/phpvms/issues/383)
- Update vaCentral Library [\#380](https://github.com/nabeelio/phpvms/issues/380)
- Implement Laravel 5.7 Notifications [\#378](https://github.com/nabeelio/phpvms/issues/378)
- Generate random cache prefix on install [\#374](https://github.com/nabeelio/phpvms/issues/374)
- Move transaction/journal code out of controller [\#370](https://github.com/nabeelio/phpvms/issues/370)
- Add Contract for Airport information lookup [\#363](https://github.com/nabeelio/phpvms/issues/363)
- RFC7807 - Standardize API error messages [\#361](https://github.com/nabeelio/phpvms/issues/361)
- Imposible delete bid [\#360](https://github.com/nabeelio/phpvms/issues/360)
- Change theme from settings [\#359](https://github.com/nabeelio/phpvms/issues/359)
- Integration with PACX \[Feature\] [\#338](https://github.com/nabeelio/phpvms/issues/338)
- Why don't you create a free ACARS for PHPVMS7 testing? [\#336](https://github.com/nabeelio/phpvms/issues/336)
- Error add Roles [\#334](https://github.com/nabeelio/phpvms/issues/334)
- Error during installation [\#333](https://github.com/nabeelio/phpvms/issues/333)
- spatie - laravel-backup [\#330](https://github.com/nabeelio/phpvms/issues/330)
- Refactor data seeding [\#329](https://github.com/nabeelio/phpvms/issues/329)
- Notify/download latest version file as part of cron [\#327](https://github.com/nabeelio/phpvms/issues/327)
- Notify if migration/updates are pending [\#326](https://github.com/nabeelio/phpvms/issues/326)
- PIREP ID change [\#318](https://github.com/nabeelio/phpvms/issues/318)
- Missing folders on install [\#316](https://github.com/nabeelio/phpvms/issues/316)
- Pull in VMACars plugin [\#315](https://github.com/nabeelio/phpvms/issues/315)
- Account for fuel in the finances [\#313](https://github.com/nabeelio/phpvms/issues/313)
- Show module in the external website [\#310](https://github.com/nabeelio/phpvms/issues/310)
- Profile picture distorted [\#309](https://github.com/nabeelio/phpvms/issues/309)
- Implement cachable in models [\#307](https://github.com/nabeelio/phpvms/issues/307)
- update [\#305](https://github.com/nabeelio/phpvms/issues/305)
- Error with installer - Permission  denied [\#298](https://github.com/nabeelio/phpvms/issues/298)
- No Staff Groups/ Authorised Permissions [\#296](https://github.com/nabeelio/phpvms/issues/296)
- .css and .js 404 [\#294](https://github.com/nabeelio/phpvms/issues/294)
- Error on Install [\#292](https://github.com/nabeelio/phpvms/issues/292)
- Ability to change interval of cron jobs [\#289](https://github.com/nabeelio/phpvms/issues/289)
- ACARS  api add response [\#287](https://github.com/nabeelio/phpvms/issues/287)
- No bids returned in json [\#286](https://github.com/nabeelio/phpvms/issues/286)
- Importing \*.csv with special characters [\#282](https://github.com/nabeelio/phpvms/issues/282)
- laravel-theme doesn't seem to be respecting the theme.json extends [\#279](https://github.com/nabeelio/phpvms/issues/279)
- Flight for current location not being restricted [\#263](https://github.com/nabeelio/phpvms/issues/263)
- Self diagnosis [\#253](https://github.com/nabeelio/phpvms/issues/253)
- Add /api/pireps/validate endpoint [\#247](https://github.com/nabeelio/phpvms/issues/247)
- Cosmetic issues [\#245](https://github.com/nabeelio/phpvms/issues/245)
- GDPR Compliance [\#244](https://github.com/nabeelio/phpvms/issues/244)
- Read all settings into the page session [\#243](https://github.com/nabeelio/phpvms/issues/243)
- Open Airspace [\#241](https://github.com/nabeelio/phpvms/issues/241)
- Option to cancel PIREP [\#238](https://github.com/nabeelio/phpvms/issues/238)
- Navdata sources [\#231](https://github.com/nabeelio/phpvms/issues/231)
- Refactor export, use streamed response [\#227](https://github.com/nabeelio/phpvms/issues/227)
- Add expense multiplier to subfleet [\#206](https://github.com/nabeelio/phpvms/issues/206)
- Expenses [\#136](https://github.com/nabeelio/phpvms/issues/136)
- Finances [\#130](https://github.com/nabeelio/phpvms/issues/130)
- phpVMS Classic Importer [\#114](https://github.com/nabeelio/phpvms/issues/114)
- Setup cron tasks [\#95](https://github.com/nabeelio/phpvms/issues/95)
- Warning in admin if Installer module is still present [\#77](https://github.com/nabeelio/phpvms/issues/77)
- flight planning [\#60](https://github.com/nabeelio/phpvms/issues/60)

**Merged pull requests:**

- Merge 7.0.0-beta.2 to master [\#440](https://github.com/nabeelio/phpvms/pull/440)
- Refactor the upgrade pending check to see if there are settings/permi… [\#438](https://github.com/nabeelio/phpvms/pull/438)
- Fixes [\#435](https://github.com/nabeelio/phpvms/pull/435)
- \#406 Refactor bids [\#432](https://github.com/nabeelio/phpvms/pull/432)
- fixes for issue \#419 [\#429](https://github.com/nabeelio/phpvms/pull/429)
- Wrap values in travis env [\#426](https://github.com/nabeelio/phpvms/pull/426)
- Show a message about setting up the cron and if there was an error ru… [\#425](https://github.com/nabeelio/phpvms/pull/425)
- 359 Select theme in settings [\#423](https://github.com/nabeelio/phpvms/pull/423)
- Fix blank aircraft field causing error closes \#414 [\#422](https://github.com/nabeelio/phpvms/pull/422)
- Bundle CSS changes [\#421](https://github.com/nabeelio/phpvms/pull/421)
- Some minor CSS fixes as discussed on Discord [\#420](https://github.com/nabeelio/phpvms/pull/420)
- Move telemetry option into settings; checkbox in install refs \#415 [\#416](https://github.com/nabeelio/phpvms/pull/416)
- Issue fixes [\#413](https://github.com/nabeelio/phpvms/pull/413)
- Catch error messages in notifying [\#403](https://github.com/nabeelio/phpvms/pull/403)
- Design and file upload issues [\#399](https://github.com/nabeelio/phpvms/pull/399)
- Don't allow cancels from certain states [\#396](https://github.com/nabeelio/phpvms/pull/396)
- Fix map checkout call [\#394](https://github.com/nabeelio/phpvms/pull/394)
- 389 API Changes [\#393](https://github.com/nabeelio/phpvms/pull/393)
- CSS Libraries and style updates [\#388](https://github.com/nabeelio/phpvms/pull/388)
- 384 Laravel 6 changes [\#385](https://github.com/nabeelio/phpvms/pull/385)
- 380 vacentral library [\#381](https://github.com/nabeelio/phpvms/pull/381)
- Use Notification/Notifiable [\#379](https://github.com/nabeelio/phpvms/pull/379)
- Add maintenance section to admin, clear caches \#376 [\#377](https://github.com/nabeelio/phpvms/pull/377)
- Refactor all JS API calls \#360 [\#375](https://github.com/nabeelio/phpvms/pull/375)
- Set the baseURL for ajax requests [\#373](https://github.com/nabeelio/phpvms/pull/373)
- Move airline transaction code into service layer [\#371](https://github.com/nabeelio/phpvms/pull/371)
- 229 url for downloads [\#369](https://github.com/nabeelio/phpvms/pull/369)
- \#355 Calculate distance button [\#366](https://github.com/nabeelio/phpvms/pull/366)
- Add Contract interface for airport lookup functionality [\#365](https://github.com/nabeelio/phpvms/pull/365)
- Refactor error handling internally to follow RFC7807 [\#362](https://github.com/nabeelio/phpvms/pull/362)
- Additional logging for the stats recalculation [\#358](https://github.com/nabeelio/phpvms/pull/358)
- Add missing expiry time for PIREP API [\#356](https://github.com/nabeelio/phpvms/pull/356)
- Fix/metar reading [\#354](https://github.com/nabeelio/phpvms/pull/354)
- Fix/metar reading [\#353](https://github.com/nabeelio/phpvms/pull/353)
- Add setting to include transfer hours in calculations [\#350](https://github.com/nabeelio/phpvms/pull/350)
- Remove ext-cal from requirements list [\#349](https://github.com/nabeelio/phpvms/pull/349)
- Multiple ticket crash fixes [\#347](https://github.com/nabeelio/phpvms/pull/347)
- Issue/327 versioning [\#345](https://github.com/nabeelio/phpvms/pull/345)
- Update issue templates [\#344](https://github.com/nabeelio/phpvms/pull/344)
- Add step fields to enable decimal points on fields \#342 [\#343](https://github.com/nabeelio/phpvms/pull/343)
- Issue fixes [\#341](https://github.com/nabeelio/phpvms/pull/341)
- Issue/329 refactor seeding [\#337](https://github.com/nabeelio/phpvms/pull/337)
- Fix issue where user stats aren't incremented on PIREP auto accept [\#335](https://github.com/nabeelio/phpvms/pull/335)
- Account for fuel in the finance calculations \#313 [\#331](https://github.com/nabeelio/phpvms/pull/331)
- Middleware to detect is update is pending; move Routes into HTTP [\#328](https://github.com/nabeelio/phpvms/pull/328)
- Change exporter to use utf-8 close \#282 [\#325](https://github.com/nabeelio/phpvms/pull/325)
- Rename Interfaces to Contracts to better match Laravel conventions [\#323](https://github.com/nabeelio/phpvms/pull/323)
- Cleanup SI Unit classes; METAR fixes [\#322](https://github.com/nabeelio/phpvms/pull/322)
- Bump lodash from 4.17.11 to 4.17.13 [\#321](https://github.com/nabeelio/phpvms/pull/321)
- Update composer library versions [\#320](https://github.com/nabeelio/phpvms/pull/320)
- Add missing folders under storage/public \#316 [\#319](https://github.com/nabeelio/phpvms/pull/319)
- Add interface to additional roles/permissions \#296 [\#314](https://github.com/nabeelio/phpvms/pull/314)
- Fix pax count fields; add fuel used field \#295 [\#312](https://github.com/nabeelio/phpvms/pull/312)
- Apply fixes from StyleCI [\#311](https://github.com/nabeelio/phpvms/pull/311)
- Laravel 5.8 Update [\#308](https://github.com/nabeelio/phpvms/pull/308)
- Add more search criteria/dev environment via docker-compose \#297 [\#306](https://github.com/nabeelio/phpvms/pull/306)
- Add gmp to requirements in install \#285 [\#301](https://github.com/nabeelio/phpvms/pull/301)
- Revert "Update issue templates" [\#300](https://github.com/nabeelio/phpvms/pull/300)
- Update issue templates [\#299](https://github.com/nabeelio/phpvms/pull/299)

## [v7.0.0-beta](https://github.com/nabeelio/phpvms/tree/v7.0.0-beta) (2018-10-25)

[Full Changelog](https://github.com/nabeelio/phpvms/compare/v7.0.0-alpha2...v7.0.0-beta)

**Implemented enhancements:**

- Follow flight checkbox on live map [\#246](https://github.com/nabeelio/phpvms/issues/246)
- PIREP restrictions based on settings [\#221](https://github.com/nabeelio/phpvms/issues/221)
- Save PIREP as draft [\#220](https://github.com/nabeelio/phpvms/issues/220)
- Shorten fields on flights table [\#205](https://github.com/nabeelio/phpvms/issues/205)
- Show bids in profile [\#203](https://github.com/nabeelio/phpvms/issues/203)
- Don't change rank if current rank is higher than the one found [\#196](https://github.com/nabeelio/phpvms/issues/196)
- pagination sizes [\#162](https://github.com/nabeelio/phpvms/issues/162)
- Allow overriding templates [\#161](https://github.com/nabeelio/phpvms/issues/161)
- Replace old pjax library with newer one [\#133](https://github.com/nabeelio/phpvms/issues/133)
- Awards [\#113](https://github.com/nabeelio/phpvms/issues/113)
- Setting to restrict airport selection to hubs [\#104](https://github.com/nabeelio/phpvms/issues/104)
- Pilot leave status [\#96](https://github.com/nabeelio/phpvms/issues/96)
- schedule import [\#15](https://github.com/nabeelio/phpvms/issues/15)

**Fixed bugs:**

- Error saving PIREP [\#252](https://github.com/nabeelio/phpvms/issues/252)
- Updater with modules not working [\#249](https://github.com/nabeelio/phpvms/issues/249)
- User registration country and timezone not saving [\#223](https://github.com/nabeelio/phpvms/issues/223)
- Importer inserts invalid data on empty columns/rows [\#222](https://github.com/nabeelio/phpvms/issues/222)
- Import/export - missing fields [\#219](https://github.com/nabeelio/phpvms/issues/219)
- Country flag not showing on user list [\#218](https://github.com/nabeelio/phpvms/issues/218)
- fares/subfleet unique field blocks saving [\#214](https://github.com/nabeelio/phpvms/issues/214)
- module navigation not working \(admin\) [\#201](https://github.com/nabeelio/phpvms/issues/201)
- Bid not removed when pirep if filled [\#200](https://github.com/nabeelio/phpvms/issues/200)
- Not saving Flight Time [\#199](https://github.com/nabeelio/phpvms/issues/199)

**Closed issues:**

- SettingsController/Repo are saving as "on" instead of 1/true [\#272](https://github.com/nabeelio/phpvms/issues/272)
- Auto Accept New Pilot isn't working [\#269](https://github.com/nabeelio/phpvms/issues/269)
- Suggestion: button to copy aircraft [\#267](https://github.com/nabeelio/phpvms/issues/267)
- Combine aircraft and subfleet import/export into one [\#256](https://github.com/nabeelio/phpvms/issues/256)
- cron - recalculate hours [\#254](https://github.com/nabeelio/phpvms/issues/254)
- Install Directory is missing from git repo  [\#251](https://github.com/nabeelio/phpvms/issues/251)
- Warning: require\(C:\xampp\Virtual Airline Website\bootstrap/../vendor/autoload.php\): failed to open stream: No such file or directory in C:\xampp\Virtual Airline Website\bootstrap\autoload.php on line 17 [\#248](https://github.com/nabeelio/phpvms/issues/248)
- Settings [\#239](https://github.com/nabeelio/phpvms/issues/239)
- HTTP caching [\#237](https://github.com/nabeelio/phpvms/issues/237)
- Module artisan command bug [\#234](https://github.com/nabeelio/phpvms/issues/234)
- Add /api/news [\#232](https://github.com/nabeelio/phpvms/issues/232)
- Start date/end date, along with day of week options [\#230](https://github.com/nabeelio/phpvms/issues/230)
- Replace weather lookup [\#228](https://github.com/nabeelio/phpvms/issues/228)
- File/avatar uploads [\#226](https://github.com/nabeelio/phpvms/issues/226)
- Change fleet page to load subfleets first [\#217](https://github.com/nabeelio/phpvms/issues/217)
- Add sort options [\#216](https://github.com/nabeelio/phpvms/issues/216)
- Auto loader on Line 17 [\#215](https://github.com/nabeelio/phpvms/issues/215)
- Add flight fields [\#213](https://github.com/nabeelio/phpvms/issues/213)
- Recaptcha in registration [\#212](https://github.com/nabeelio/phpvms/issues/212)
- Browser tests [\#211](https://github.com/nabeelio/phpvms/issues/211)
- Axios HTTP library [\#210](https://github.com/nabeelio/phpvms/issues/210)
- Replace skinning with laravel-theme [\#209](https://github.com/nabeelio/phpvms/issues/209)
- Refactor getting expenses in FinanceData [\#208](https://github.com/nabeelio/phpvms/issues/208)
- Change all money to hold in cents, not dollars [\#204](https://github.com/nabeelio/phpvms/issues/204)
- Pilot pay - rank base and subfleet multiplier [\#197](https://github.com/nabeelio/phpvms/issues/197)
- Flight schedule importer/exporter [\#194](https://github.com/nabeelio/phpvms/issues/194)
- use find/replace in bootstrapWith\(\) call [\#163](https://github.com/nabeelio/phpvms/issues/163)
- Awards [\#155](https://github.com/nabeelio/phpvms/issues/155)
- System Changes/Additions [\#135](https://github.com/nabeelio/phpvms/issues/135)
- Airport & Aircraft Changes [\#134](https://github.com/nabeelio/phpvms/issues/134)
- Handle flight specific fares & multipliers [\#125](https://github.com/nabeelio/phpvms/issues/125)
- Flight set day of week [\#122](https://github.com/nabeelio/phpvms/issues/122)
- Airport page [\#85](https://github.com/nabeelio/phpvms/issues/85)
- Bulk upload charts to airport [\#84](https://github.com/nabeelio/phpvms/issues/84)
- Pull some values from existing .env file [\#79](https://github.com/nabeelio/phpvms/issues/79)
- METAR Information [\#61](https://github.com/nabeelio/phpvms/issues/61)
- flights page [\#57](https://github.com/nabeelio/phpvms/issues/57)
- fuel costs to airports and fuel type on subfleet [\#41](https://github.com/nabeelio/phpvms/issues/41)
- generate aircraft hex code on create [\#33](https://github.com/nabeelio/phpvms/issues/33)

**Merged pull requests:**

- More acceptable translation to opt-in \(es\) [\#284](https://github.com/nabeelio/phpvms/pull/284)
- Fix 404s to requirements/setup in README.md [\#283](https://github.com/nabeelio/phpvms/pull/283)
- Hide expired live flight from live map and remove from DB [\#280](https://github.com/nabeelio/phpvms/pull/280)
- \[WIP\] Laravel 5.7 Update [\#278](https://github.com/nabeelio/phpvms/pull/278)
- Make admin input outline darker [\#276](https://github.com/nabeelio/phpvms/pull/276)
- capture & display pilot transfer hours [\#275](https://github.com/nabeelio/phpvms/pull/275)
- Optimize query with eager loading [\#274](https://github.com/nabeelio/phpvms/pull/274)
- Fix SettingsController saving boolean value as 'on' [\#273](https://github.com/nabeelio/phpvms/pull/273)
- Hide inactive pilot from pilot list page [\#271](https://github.com/nabeelio/phpvms/pull/271)
- Fix flight search restriction [\#270](https://github.com/nabeelio/phpvms/pull/270)
- Apply fixes from StyleCI [\#268](https://github.com/nabeelio/phpvms/pull/268)
- Fix METAR issue [\#264](https://github.com/nabeelio/phpvms/pull/264)
- Apply fixes from StyleCI [\#262](https://github.com/nabeelio/phpvms/pull/262)
- Apply fixes from StyleCI [\#261](https://github.com/nabeelio/phpvms/pull/261)
- Enable eager loading [\#259](https://github.com/nabeelio/phpvms/pull/259)
- Implement cron to remove expired bids [\#258](https://github.com/nabeelio/phpvms/pull/258)
- Fix timezone list HTML Escaped [\#257](https://github.com/nabeelio/phpvms/pull/257)
- Update 07/13/2018 es-translations [\#255](https://github.com/nabeelio/phpvms/pull/255)
- ES translation [\#250](https://github.com/nabeelio/phpvms/pull/250)
- flights filtered per va [\#242](https://github.com/nabeelio/phpvms/pull/242)
- Avatar Issue [\#236](https://github.com/nabeelio/phpvms/pull/236)
- Install bug [\#235](https://github.com/nabeelio/phpvms/pull/235)

## [v7.0.0-alpha2](https://github.com/nabeelio/phpvms/tree/v7.0.0-alpha2) (2018-02-23)

[Full Changelog](https://github.com/nabeelio/phpvms/compare/v7.0.0-alpha1...v7.0.0-alpha2)

**Implemented enhancements:**

- Get user PIREPs [\#192](https://github.com/nabeelio/phpvms/issues/192)
- Use time conversion class [\#190](https://github.com/nabeelio/phpvms/issues/190)
- Require inputs in Imperial units [\#189](https://github.com/nabeelio/phpvms/issues/189)
- Make planned\_distance in prefile optional [\#185](https://github.com/nabeelio/phpvms/issues/185)
- If there's no /pirep/route calls, when it's filed, write the route [\#184](https://github.com/nabeelio/phpvms/issues/184)
- Create a /api/flight/{id}/route call [\#183](https://github.com/nabeelio/phpvms/issues/183)
- Make all APIs behind auth [\#173](https://github.com/nabeelio/phpvms/issues/173)
- Add bid [\#172](https://github.com/nabeelio/phpvms/issues/172)
- Setting for only showing aircraft that are at the departure airport [\#171](https://github.com/nabeelio/phpvms/issues/171)
- Font for input [\#169](https://github.com/nabeelio/phpvms/issues/169)
- Read default value from settings table [\#106](https://github.com/nabeelio/phpvms/issues/106)

**Fixed bugs:**

- Volume units to lbs/kg, not gal/liter [\#193](https://github.com/nabeelio/phpvms/issues/193)
- Add country to airline missing [\#191](https://github.com/nabeelio/phpvms/issues/191)
- SKIN\_NAME missing on error pages [\#187](https://github.com/nabeelio/phpvms/issues/187)
- Airport timezone not being saved [\#182](https://github.com/nabeelio/phpvms/issues/182)
- Rank checkboxes not being saved [\#181](https://github.com/nabeelio/phpvms/issues/181)
- Make sure cancelled PIREPs don't show [\#180](https://github.com/nabeelio/phpvms/issues/180)
- Ignore cancelled PIREPs in duplicate check [\#179](https://github.com/nabeelio/phpvms/issues/179)
- Make sure all fields are returned in PIREP object [\#178](https://github.com/nabeelio/phpvms/issues/178)
- autopopulate SOURCE field in ACARS [\#177](https://github.com/nabeelio/phpvms/issues/177)
- Make sure pireps.restrict\_aircraft\_to\_rank is respected [\#170](https://github.com/nabeelio/phpvms/issues/170)
- Don't change rank if current rank is non-auto promoting [\#168](https://github.com/nabeelio/phpvms/issues/168)
- PIREP hours can't be changed [\#167](https://github.com/nabeelio/phpvms/issues/167)
- Changing a flight status does not save [\#150](https://github.com/nabeelio/phpvms/issues/150)
- Additional PIREP fields don't save correctly [\#146](https://github.com/nabeelio/phpvms/issues/146)

**Closed issues:**

- Cleanup icons [\#195](https://github.com/nabeelio/phpvms/issues/195)
- remove raw\_data field from pirep table [\#188](https://github.com/nabeelio/phpvms/issues/188)
- Remove Resource::withoutWrapping\(\) to follow jsonapi spec [\#186](https://github.com/nabeelio/phpvms/issues/186)
- Add an `artisan phpvms:dev-install` command [\#176](https://github.com/nabeelio/phpvms/issues/176)
- Pagination settings [\#175](https://github.com/nabeelio/phpvms/issues/175)
- Implement setting for flights at pilot's current airport [\#174](https://github.com/nabeelio/phpvms/issues/174)
- Minimum PHP supported version to 7.1 [\#166](https://github.com/nabeelio/phpvms/issues/166)
- Update to Laravel 5.6 [\#165](https://github.com/nabeelio/phpvms/issues/165)
- Updater [\#164](https://github.com/nabeelio/phpvms/issues/164)
- Allow adding rank in subfleet page [\#160](https://github.com/nabeelio/phpvms/issues/160)
- Docs [\#153](https://github.com/nabeelio/phpvms/issues/153)

## [v7.0.0-alpha1](https://github.com/nabeelio/phpvms/tree/v7.0.0-alpha1) (2018-02-04)

[Full Changelog](https://github.com/nabeelio/phpvms/compare/f158c11dea7c0a4af27c93382333b67fdc531e3a...v7.0.0-alpha1)

**Implemented enhancements:**

- Widgetized components [\#127](https://github.com/nabeelio/phpvms/issues/127)
- Set aircraft location on landing [\#112](https://github.com/nabeelio/phpvms/issues/112)
- save user IP on login [\#110](https://github.com/nabeelio/phpvms/issues/110)
- When rejecting PIREP, add a comment/reason [\#107](https://github.com/nabeelio/phpvms/issues/107)
- Check for duplicate PIREPs and add setting [\#105](https://github.com/nabeelio/phpvms/issues/105)
- create vendor.js with webpack [\#91](https://github.com/nabeelio/phpvms/issues/91)
- move from bower to webpack [\#90](https://github.com/nabeelio/phpvms/issues/90)
- Pilots list and profile [\#82](https://github.com/nabeelio/phpvms/issues/82)
- pirep auto-accept for certain rankings [\#24](https://github.com/nabeelio/phpvms/issues/24)
- Investigate removing the Repository pattern to simplify [\#6](https://github.com/nabeelio/phpvms/issues/6)

**Fixed bugs:**

- Error when sending /api/pireps/prefile [\#149](https://github.com/nabeelio/phpvms/issues/149)
- Extra numbers on flights page [\#148](https://github.com/nabeelio/phpvms/issues/148)
- Unable to select any aircraft from fleet on file PIREP page [\#147](https://github.com/nabeelio/phpvms/issues/147)
- Error thrown when editing a saved flight [\#145](https://github.com/nabeelio/phpvms/issues/145)
- When you delete flight from admin, filed pirep will throw error [\#144](https://github.com/nabeelio/phpvms/issues/144)
- Avoid calling MySQL executable for creating database [\#132](https://github.com/nabeelio/phpvms/issues/132)
- Aircraft can be added without specifying a subfleet [\#128](https://github.com/nabeelio/phpvms/issues/128)
- Non error flash messages not showing [\#126](https://github.com/nabeelio/phpvms/issues/126)
- Removing subfleet from flight [\#124](https://github.com/nabeelio/phpvms/issues/124)
- User still has API access if they're not ACTIVE [\#119](https://github.com/nabeelio/phpvms/issues/119)
- Delete bids when flight is deleted [\#103](https://github.com/nabeelio/phpvms/issues/103)
- JSON error messages [\#92](https://github.com/nabeelio/phpvms/issues/92)
- add subfleet to flight broken [\#88](https://github.com/nabeelio/phpvms/issues/88)
- logout functionality not working from frontpage [\#26](https://github.com/nabeelio/phpvms/issues/26)

**Closed issues:**

- `fuel\_used` in pirep/file [\#159](https://github.com/nabeelio/phpvms/issues/159)
- Add additional fields for PIREP and flights [\#158](https://github.com/nabeelio/phpvms/issues/158)
- Stub file [\#157](https://github.com/nabeelio/phpvms/issues/157)
- Add config.php in root on install [\#156](https://github.com/nabeelio/phpvms/issues/156)
- New registrations are auto-accepted and put as active [\#151](https://github.com/nabeelio/phpvms/issues/151)
- new version alert [\#143](https://github.com/nabeelio/phpvms/issues/143)
- Footer copyright text [\#142](https://github.com/nabeelio/phpvms/issues/142)
- Version build number to use string/assigned date [\#141](https://github.com/nabeelio/phpvms/issues/141)
- Handle duplicate flight number [\#139](https://github.com/nabeelio/phpvms/issues/139)
- Restrict shown aircraft to user's rank [\#138](https://github.com/nabeelio/phpvms/issues/138)
- Docs [\#137](https://github.com/nabeelio/phpvms/issues/137)
- Checksum for tarball [\#123](https://github.com/nabeelio/phpvms/issues/123)
- API Items need [\#120](https://github.com/nabeelio/phpvms/issues/120)
- ACARS API [\#118](https://github.com/nabeelio/phpvms/issues/118)
- List of cron jobs required [\#117](https://github.com/nabeelio/phpvms/issues/117)
- Add create/edit fields for columns [\#111](https://github.com/nabeelio/phpvms/issues/111)
- Add PIREP cancel endpoint and cleanup job [\#109](https://github.com/nabeelio/phpvms/issues/109)
- ACARS table should save the route [\#102](https://github.com/nabeelio/phpvms/issues/102)
- /api/fleet and /api/user/fleet [\#101](https://github.com/nabeelio/phpvms/issues/101)
- Assign subfleets to ranks [\#100](https://github.com/nabeelio/phpvms/issues/100)
- show dates/times in proper timezone [\#97](https://github.com/nabeelio/phpvms/issues/97)
- editable fields not working [\#94](https://github.com/nabeelio/phpvms/issues/94)
- Settings page needs to be completed [\#93](https://github.com/nabeelio/phpvms/issues/93)
- Airport page pagination & search [\#87](https://github.com/nabeelio/phpvms/issues/87)
- Mark airport as hub [\#86](https://github.com/nabeelio/phpvms/issues/86)
- Terms and Conditions & COPPA during registration [\#81](https://github.com/nabeelio/phpvms/issues/81)
- Look at alternative to Entrust [\#78](https://github.com/nabeelio/phpvms/issues/78)
- Check if .env file exists in installer [\#76](https://github.com/nabeelio/phpvms/issues/76)
- upload travis artifact to phpvms ftp as "latest.zip" [\#75](https://github.com/nabeelio/phpvms/issues/75)
- Track hours on airframes [\#73](https://github.com/nabeelio/phpvms/issues/73)
- PIREP accept/reject in admin should call PIREP service [\#72](https://github.com/nabeelio/phpvms/issues/72)
- Error with MySQL syntax when creating database. [\#71](https://github.com/nabeelio/phpvms/issues/71)
- pirep comments [\#70](https://github.com/nabeelio/phpvms/issues/70)
- findWithoutFail Method does not exist [\#69](https://github.com/nabeelio/phpvms/issues/69)
- Commit error 403 [\#67](https://github.com/nabeelio/phpvms/issues/67)
- Keep Showing 404.... [\#66](https://github.com/nabeelio/phpvms/issues/66)
- wrap migrate command [\#65](https://github.com/nabeelio/phpvms/issues/65)
- Create 404 page [\#63](https://github.com/nabeelio/phpvms/issues/63)
- link to admin in nav if you have permissions [\#62](https://github.com/nabeelio/phpvms/issues/62)
- Error while creating database [\#56](https://github.com/nabeelio/phpvms/issues/56)
- shared hosting stuff [\#55](https://github.com/nabeelio/phpvms/issues/55)
- gravatar url for profile [\#54](https://github.com/nabeelio/phpvms/issues/54)
- news and notams module [\#52](https://github.com/nabeelio/phpvms/issues/52)
- try atoum vs phpunit [\#51](https://github.com/nabeelio/phpvms/issues/51)
- Cache Airports calls [\#49](https://github.com/nabeelio/phpvms/issues/49)
- add custom fields table for flights [\#48](https://github.com/nabeelio/phpvms/issues/48)
- Events system [\#43](https://github.com/nabeelio/phpvms/issues/43)
- add fuel costs types to the airports table [\#40](https://github.com/nabeelio/phpvms/issues/40)
- adjust "created" date for airline [\#39](https://github.com/nabeelio/phpvms/issues/39)
- add gross\_weight to PIREPs [\#38](https://github.com/nabeelio/phpvms/issues/38)
- subfleet to flight pivot values [\#37](https://github.com/nabeelio/phpvms/issues/37)
- API authentication/middleware [\#36](https://github.com/nabeelio/phpvms/issues/36)
- REST API [\#35](https://github.com/nabeelio/phpvms/issues/35)
- use uuids on a few tables [\#34](https://github.com/nabeelio/phpvms/issues/34)
- subfleet \(ranks, expenses\) [\#32](https://github.com/nabeelio/phpvms/issues/32)
- How to install this? [\#31](https://github.com/nabeelio/phpvms/issues/31)
- experiment with changing PDO mode [\#30](https://github.com/nabeelio/phpvms/issues/30)
- forgot password [\#27](https://github.com/nabeelio/phpvms/issues/27)
- rankings [\#23](https://github.com/nabeelio/phpvms/issues/23)
- module system [\#22](https://github.com/nabeelio/phpvms/issues/22)
- pireps  [\#21](https://github.com/nabeelio/phpvms/issues/21)
- admin user management [\#20](https://github.com/nabeelio/phpvms/issues/20)
- modify DatabaseSeeder to look at env [\#19](https://github.com/nabeelio/phpvms/issues/19)
- look at flarum method of install [\#18](https://github.com/nabeelio/phpvms/issues/18)
- navdata ingestion [\#17](https://github.com/nabeelio/phpvms/issues/17)
- settings panel [\#16](https://github.com/nabeelio/phpvms/issues/16)
- routing and scheduling [\#14](https://github.com/nabeelio/phpvms/issues/14)
- airport details page [\#13](https://github.com/nabeelio/phpvms/issues/13)
- currency format config [\#12](https://github.com/nabeelio/phpvms/issues/12)
- aircraft details page [\#11](https://github.com/nabeelio/phpvms/issues/11)
- user dashboard and profile pages [\#10](https://github.com/nabeelio/phpvms/issues/10)
- admin panel work [\#9](https://github.com/nabeelio/phpvms/issues/9)
- theme/skins engine [\#8](https://github.com/nabeelio/phpvms/issues/8)
- Integrate roles and permissions [\#7](https://github.com/nabeelio/phpvms/issues/7)
- rebuild tables [\#5](https://github.com/nabeelio/phpvms/issues/5)
- front-end template [\#4](https://github.com/nabeelio/phpvms/issues/4)
- Laravel 5.4 [\#3](https://github.com/nabeelio/phpvms/issues/3)
- look up role-based permissions  [\#2](https://github.com/nabeelio/phpvms/issues/2)
- installer [\#1](https://github.com/nabeelio/phpvms/issues/1)

**Merged pull requests:**

- Fixed a few field entries [\#116](https://github.com/nabeelio/phpvms/pull/116)
- Remove unique email entry [\#115](https://github.com/nabeelio/phpvms/pull/115)



\* *This Changelog was automatically generated by [github_changelog_generator](https://github.com/github-changelog-generator/github-changelog-generator)*
