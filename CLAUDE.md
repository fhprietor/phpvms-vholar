# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

> **Run artisan and phpunit with `umask 000`, and clear the compiled views first.**
>
> The app runs as `www-data`; the developer shell runs as `frank`. `frank` is in the
> `www-data` group but `storage/framework/views` has no setgid bit, so ownership of the
> compiled Blade cache flips depending on who compiled last, and **neither user can
> overwrite the other's files**:
>
> | Compiled by | Result | The other user can... |
> |---|---|---|
> | `frank` (plain) | `664 frank:frank` | www-data can read, **not overwrite** |
> | `www-data` (web request) | `644 www-data:www-data` | frank can read, **not overwrite** |
> | `frank` with `umask 000` | `666 frank:frank` | www-data **can** overwrite |
>
> Whichever way it breaks, the symptom is the same on the next recompile:
> `file_put_contents(.../storage/framework/views/xxxx.php): Failed to open stream:
> Permission denied`.
>
> ```bash
> php artisan view:clear            # FIRST: frank can always delete in the 777 dir
> (umask 000; php artisan ...)      # then create 666 files, which www-data can overwrite
> (umask 000; vendor/bin/phpunit)   # same
> php artisan view:clear            # LAST: let www-data regenerate them as its own
> ```
>
> Deletion works from `frank` because the directory itself is 777 (deleting needs write on
> the directory, not on the file). `storage/logs` already has setgid, which is why logs
> never showed this problem. `chown`/`chmod g+s` are not available without root.

```bash
# Install/setup
make install                          # composer install + migrate + seed
make update                           # composer update + migrate

# Development
make build                            # composer install only
make build-assets                     # npm run production (webpack/laravel-mix)
npm run dev                           # watch assets

# Testing
vendor/bin/phpunit                    # run all tests (uses in-memory SQLite)
vendor/bin/phpunit --filter TestName  # run single test

# Linting
vendor/bin/php-cs-fixer fix           # fix code style
vendor/bin/php-cs-fixer fix --dry-run # check without fixing

# Cache management
make clean                            # clear all caches (routes, config, views, logs)
make clear                            # clear without removing log files
php artisan route:clear               # clear routes only

# Database
php artisan migrate                   # run pending migrations
php artisan migrate:refresh --seed    # reset + reseed (destroys data)
```

## Architecture

**phpVMS v7** is a Laravel 10 virtual airline management system. PHP 8.1+, MySQL 5.7+.

### Request Flow

Routes are registered in `app/Providers/RouteServiceProvider.php` across three groups:
- **Web** (`/`) — frontend (auth via session), admin panel (`/admin`, role-gated via `ability:admin,admin-access`)
- **API** (`/api`) — JSON endpoints, token-based auth; ACARS tracking goes through `AcarsController`
- **Install/Update** (`/install`, `/update`) — system setup routes

### Service Layer

Business logic lives in `app/Services/`. Key services:
- `PirepService` — flight report lifecycle: creation, status transitions, event firing, awards
- `FlightService` — flight CRUD, availability, bid handling
- `FinanceService` — journal entries, expenses, fuel costs
- `BidService` — flight bidding
- `SimBriefService` — SimBrief XML API integration
- `GeoService` — distance/coordinate calculations
- `ImportService` — CSV and legacy system imports

Services extend `app/Contracts/Service` and are bound via `app/Providers/BindServiceProviders.php`.

### Data Access

Repository pattern via `prettus/l5-repository`. Repositories in `app/Repositories/` accept Criteria objects for complex filtering. Models are in `app/Models/` and use UUIDs as primary keys (`Model::ID_MAX_LENGTH` constant).

Key relationships use `staudenmeir/eloquent-has-many-deep` and `staudenmeir/belongs-to-through` for multi-level associations (e.g., User → Subfleet → Aircraft).

### Events & State Transitions

Pirep state changes fire events (`PirepFiled`, `PirepAccepted`, `PirepRejected`) defined in `app/Events/`. Listeners are in `app/Listeners/` and registered in `app/Providers/EventServiceProvider.php`. Awards are processed via `ProcessAward` event after pirep acceptance.

### Theme System

Active theme is set via `config/phpvms.php` → `theme` key (currently `vholar`). Theme views live in `resources/views/layouts/vholar/`. The Vholar theme is built on **Disposable Theme v3** (Bootstrap 5).

**Dark mode: use the `--vh-*` tokens, never Bootstrap's semantic colour classes.** The layout sets `<html data-bs-theme="dark">`, and Bootstrap 5.3 redefines its alert/table variables for that mode. `.alert-warning` is **not** the light cream of light mode: in dark mode it is `background: #332701` (dark mustard) with `color: #ffda6a`. Adding `text-dark` on top forces `#212529` and collapses the contrast to **1.05:1** — the text becomes invisible. That exact combination shipped on the AI feedback card and had to be fixed.

Rules for this theme:
- Colours come from `public/assets/themes/vholar/css/tokens.css` (`--vh-text`, `--vh-warning`, `--vh-surface-2`...). It is declared the single source of truth and no sheet may hardcode a palette colour.
- The documented usage is `<color>` for text/icons/borders, `<color>-soft` (alpha 0.10) as a pill or panel background with `<color>` as the text on it, and `<color>-strong` only as a solid background for WHITE text. So: soft background + accent border + `--vh-text` body text.
- Measured with WCAG 2.1, that gives accent-on-soft 4.86–6.77:1 and `--vh-text` on soft 12.2–12.7:1.
- `bg-warning text-dark` **is** fine on a plain badge (9.46:1), because dark mode does not change `--bs-warning-rgb`, only the `-bg-subtle` / `-text-emphasis` / `-border-subtle` trio. The trap is specific to `alert-*`.
- `alert-important`, `alert-error` and `alert-icon` are **not** Bootstrap 5 classes and have no colour anywhere in the project — those alerts render unstyled (10 existing uses, in modules and views outside the theme).

**Vholar view structure** (`resources/views/layouts/vholar/`):
- `app.blade.php`, `admin.blade.php` — master layouts
- `nav.blade.php` — main navigation; fallback avatar uses `vholar_logoweb.png`
- `dashboard/` — `index.blade.php` (last 5 own pireps + recent airline pireps with avatar column), `pirep_card.blade.php`
- `flights/`, `fleet/`, `profile/`, `airports/` — section views; `flights/show.blade.php` uses `logo.png` as airline logo fallback
- `pireps/` — `show.blade.php` (full pirep detail: map, flight log, advanced ACARS analysis, altitude profile), `map.blade.php`, `table.blade.php` (has avatar column)
- `profile/index.blade.php` — uses `logo.png` as avatar fallback (123px circular)
- `users/table.blade.php` — uses `logo.png` as avatar fallback (40px circular)
- `widgets/` — `latest_pireps.blade.php` (airline-wide, with avatar + links), `latest_news.blade.php`, `latest_pilots.blade.php` (with avatar), `live_map.blade.php`, `airspace_map.blade.php`, `latest_awards.blade.php` (with avatar), `weather.blade.php` (metar-taf.com embed, see below)
- `modules/` — views overriding module defaults
- `components/` — shared partials; includes `simbrief-dispatch-modal.blade.php` (SimBrief direct dispatch modal, used on `/flights`, `/bids`, `/dassignments`)
- `theme.json` — theme metadata (`{"name": "vholar", "extends": null}`)

**Default avatar / logo fallback pattern:**
`public/images/logo.png` is used as the default avatar wherever no pilot avatar or airline logo is set. Applied consistently across: profile, users table, dashboard pireps, latest_pireps widget, latest_pilots widget, latest_awards widget, pireps/table, DisposableBasic pireps table, VmsOpenOps admin table, CHJumpSeat admin and frontend tables, **Discord broadcast notification thumbnails**. Style: `border-radius:50%; object-fit:contain; background:#1f1c27; padding:3px;` at sizes 30–40px (tables/widgets) or 123px (profile).

**Discord broadcast notifications** (`app/Notifications/Messages/Broadcast/`):
- All 6 files use `url('/images/logo.png')` as avatar fallback (thumbnail) when `$user->avatar` is empty — replaces the old `gravatar(256)` which returned the phpVMS generic image.
- Footer: `'Vholar Virtual Airlines'` with `url('/images/vholar_logoweb.png')` icon on all broadcasts.
- `PirepFiled` description: line 1 = Discord mention; line 2 = `Score: N | +N pts: reason` (if log data available).
- `PirepStatusChanged` (LANDED only): description appends `Landing rate: N fpm | G-Force: N.NNg`; bonuses NOT included — LANDED fires before the full log is written.
- `PirepPrefiled` uses `icon_url` in the author field (not thumbnail); same `logo.png` fallback applies.

**Custom Vholar logic:**
- `app/Themes/Vholar/ThemeServiceProvider.php` — registers `vholar::` view namespace, loads `app/Themes/Vholar/Routes/web.php`
- `app/Http/Controllers/Vholar/` — controllers for theme-specific routes (e.g., `FleetGridController`)
- `public/disposable/` — compiled CSS/JS for the Disposable theme stylesheet
- `resources/lang/{locale}/disposable.php` — Disposable theme translation strings

**Overriding module views in Vholar:**

`App\Support\ThemeViewFinder` (registered in `AppServiceProvider`) remaps namespaced view lookups so that `ModuleNamespace::some.view` is resolved first in the active theme's `modules/` subfolder before falling back to the module's own views. The path mapping is:

```
resources/views/modules/{ModuleAlias}/  →  resources/views/layouts/vholar/modules/{ModuleAlias}/
```

To override a module view for the Vholar theme, create the file at:
```
resources/views/layouts/vholar/modules/{ModuleAlias}/{path/to/view}.blade.php
```

Example: `DBasic::pireps.index` → `resources/views/layouts/vholar/modules/DisposableBasic/pireps/index.blade.php`

Existing Vholar module overrides:
- `resources/views/layouts/vholar/modules/DisposableBasic/pireps/` — custom `index.blade.php` and `table.blade.php` for `/dpireps` (Bootstrap 5, expandable rows: primary = avatar/flight/route/time/pilot/status/score/lrate; secondary = aircraft/fuel)
- `resources/views/layouts/vholar/modules/DisposableSpecial/assignments/index.blade.php` — frontend pilot assignments view (`/dassignments`). Card-per-assignment layout with Leaflet map, country flags on airports, linked CALLSIGN/EQUIPMENT/BLOCK TIMES/DURATION/DISTANCE for completed assignments (dynamic `$linkedPirep` query). Includes SimBrief dispatch modal.

**Non-theme module overrides** (applied regardless of active theme, checked before module source):
- `resources/views/modules/DisposableSpecial/assignments/admin.blade.php` — extends `admin.app`, used for `/admin/dassignments`. Filter form (year/month/pilot) uses Bootstrap 3 classes (`form-inline`, `form-control input-sm`) and lives inside the "Acciones de Asignación" card.

**Country flag pattern in flight cards:**
- Frontend uses `fi fi-{cc}` (flag-icons CDN, loaded in `app.blade.php`); admin uses `flag-icon flag-icon-{cc}` (from `global/css/vendor.css`)
- Departure airport (left-aligned): `<div style="display:flex; align-items:center; gap:6px;">` wrapping flag + text div
- Arrival airport (right-aligned, inside `.route-point:last-child` which has CSS `text-align:right`): `<div style="display:inline-flex; align-items:center; gap:6px; text-align:left;">` — `inline-flex` causes the block to float right via parent `text-align:right`; `text-align:left` on the wrapper overrides inheritance so the ICAO pill stays left-aligned within the text div (adjacent to the flag gap), not pushed to the right edge

**SimBrief Direct Dispatch:**
- Shared modal partial: `vholar::components.simbrief-dispatch-modal` — include with `@include('vholar::components.simbrief-dispatch-modal')`
- Button class `sb-dispatch-btn` with `data-*` attributes: `fltnum`, `airline`, `orig`, `dest`, `orig-name`, `dest-name`, `actype`, `acreg`, `route`
- Appears when `$isBid && $hasReservedAircraft` — requires `$reservedAircraftIcao = $aircraft->icao ?? ''` in the PHP block
- SimBrief URL params: `fl` = feet/100 (e.g. 36000 ft → `fl=360`), runway params are `origrwy` / `destrwy`
- `window._sbDispatchInit` guard prevents double JS registration; close buttons call `sbModal.hide()` explicitly (not `data-bs-dismiss`) to avoid conflict with manually-instantiated `new bootstrap.Modal()`

### Module System

Uses `nwidart/laravel-modules`. Modules live in `modules/`, each with its own Providers, Routes, Controllers, Models, Migrations, and Resources/views.

**Active (`"active": 1`):**

- **VmsOpenOps** — jumpseat and aircraft ferry with distance-based pricing. Model: `OperationRequest`. Controllers: `Admin/`, `Api/`, `Frontend/`. Has `Notifications/`.
  - `/vmsopenops/stats` — statistics page. Frontend controller serves the blade shell; data is loaded via AJAX from the API route (`GET /api/vmsopenops/stats` → `Api\StatisticsController@getData`). All queries live in `computeStats()` and results are cached 5 min. The JS in the view builds cards dynamically via helper functions (`pilotCard`, `scoreCard`, `routesCard`, etc.).
- **VmsOpenFileManager** — file management and liveries. Models: `FileItem`, `FileFolder`, `FilePermission`, `Livery`, `Manufacturer`, `Simulator`. Has its own `Services/`, `Traits/`, `tests/`, `Listeners/`. Views: `admin/`, `pilot/`, `layouts/`.
- **CHJumpSeat** — jumpseat requests. Model: `CHJumpseatRequest`. Controllers: `Admin/`, `Api/`, `Frontend/`. Has `Console/`, `Notifications/`, `tests/`.
- **Awards** — award class definitions only (`FlightRouteAwards`, `PilotFlightAwards`, `PilotHoursAwards`). No routes or models; award logic is in core `AwardService`.
- **SmartCARS3phpVMS7Api** (v1.0.2, TFDi Design) — smartCARS 3 ACARS client API. Models: `ActiveFlight`, `PirepLog`. Has `Actions/`, `Jobs/`, `Listeners/`.
- **Vacentral** — vaCentral network integration. Minimal: only `Providers/` and `Config/`, no models or controllers.

**Inactive (`"active": 0`, loaded but disabled):**

- **DisposableBasic** (v3.7.4) — VA widgets, awards, VATSIM/IVAO whazzup, stable approach, Discord. Global helpers in `db_helpers.php` and `db_helpers_tech.php` (e.g., `DB_GetUnits()`). Has `Widgets/`, `Services/`, `Listeners/`. Used by Vholar's `FleetGridController`.
- **DisposableSpecial** (v3.7.8) — tours, assignments, maintenance, market items, NOTAMs, missions. Models: `DS_Tour`, `DS_Assignment`, `DS_Maintenance`, `DS_Mission`, `DS_Notam`, `DS_Marketitem`. Global helper: `ds_helpers.php`. Has `Widgets/`, `Services/`.

### Finance

Finance uses the Akaunting Money library with 160+ currencies defined in `config/money.php`. `JournalTransaction` model tracks all debits/credits. `FinanceService` handles all monetary operations.

### Enums

Model state enums in `app/Models/Enums/`: `PirepStatus`, `PirepState`, `PirepSource`, `FlightType`, `AircraftState`, `AcarsType`, `Days`.

### RBAC

Roles and permissions via Laratrust, configured in `config/laratrust.php`. Three core roles: `admin`, `staff`, `user`. Admin routes guard with `ability:admin,admin-access` middleware.

### ACARS

Real-time flight tracking via `Acars` model. The custom migration `2026_04_05_175726_add_advanced_acars_fields_to_acars_table.php` adds extended fields. API endpoint: `app/Http/Controllers/Api/AcarsController.php`.

**ACARS log parsing:** `app/Helpers/FlightAnalysisHelper::parseLogData(Pirep $pirep)` reads `type=2` (AcarsType::LOG) entries and parses the structured text written by the ACARS client (status=SCH). Returns score, penalties, **bonuses**, takeoff block, landing block, approach, aircraft, and network data. **Important:** use `Acars::where('pirep_id', $pirep->id)->where('type', AcarsType::LOG)` — do NOT use `$pirep->acars()` which already scopes to `type=FLIGHT_PATH (0)`.

**Bilingual log parsing (EN/ES):** The parser handles both English and Spanish ACARS log formats via alternation in every regex. Key equivalences:
- Context headers: `ACCURATE TAKEOFF DATA` = `DATOS DE DESPEGUE`, `ACCURATE TOUCHDOWN DATA` = `DATOS DE ATERRIZAJE`
- Takeoff detection: `TAKEOFF DETECTED.*Speed:` = `DESPEGUE DETECTADO.*Vel:` — **also sets `$context = 'takeoff'`** so logs that omit the separate header line still parse the data block correctly.
- Takeoff context end: `Gear UP` or `Tren de aterrizaje: UP` (Spanish gear-up) or `Phase changed:`.
- Landing summary: `Landing recorded:.*Heading:.*Pitch:.*Bank:` = `Aterrizaje registrado:.*Rumbo:.*Cabeceo:.*Alabeo:`
- G-Force: `G-Force:` = `Fuerza G:`, Reversers: `Reversers:` = `Reversas:`, Wind: `Wind:` = `Viento:`
- Score: `Score:` = `Puntuación:`, Network: `Connected on` = `Conectado en`
- Approach gate: `APPROACH GATE.*STABILIZED/UNSTABILIZED` = `COMPUERTA DE APROXIMACIÓN.*ESTABILIZADA/INESTABLE`
- Approach capture: `APPROACH CAPTURE: RWY X | AGL X ft | Dist X NM` = `INICIO CAPTURA … PISTA …`
- Inside takeoff block: `Rotation Speed:` = `Velocidad de Rotación:`, `Ground Speed:` = `Velocidad en Tierra:`, `Pitch: | Bank:` = `Cabeceo: | Alabeo:`
- **Bonuses:** `BONUS reason: +N pts` = `BONIFICACIÓN reason: +N pts` — parsed into `result['bonuses']` array.
- **QNH delta:** only first occurrence is captured (`!isset($result['takeoff']['qnh_delta'])`) — prevents destination QNH line from overwriting departure delta.
- **Penalty guard:** excludes lines containing `PENALTY:` OR `PENALIZACIÓN:` (early warning lines) to avoid false positives.
- The `Score:` / `Puntuación:` line is no longer the sole gate for the analysis card — see Pirep Detail section below.

Key `Pirep` ACARS relationships:
- `acars()` → `type=0` (FLIGHT_PATH), ordered by `created_at/sim_time` — position track points
- `acars_logs()` → `type=2` (LOG), ordered by `created_at` — text log entries
- `acars_route()` → `type=1` (ROUTE) — planned route waypoints

**NavData credentials for ACARS clients (`GET /api/navdata`):** phpVMS is *not* a NavData proxy. The staff keeps the service URL + API key in Admin > Settings (`general.navdata_api_url`, `general.navdata_api_key`); the endpoint hands them to the authenticated pilot once, sealed, and the client then talks to NavData directly. The key never leaves the server in clear text: it is encrypted with a key derived (HKDF-SHA256, `salt = "vmsopenacars/navdata/v1"`) from the caller's own `api_key`, so phpVMS needs no extra shared secret and a pilot cannot open another pilot's envelope. Two envelopes exist because the client (`vmsopenacarsexe.config`) is **.NET Framework 4.8.1**, where `AesGcm` and `HKDF` do not exist: `aes-256-gcm` (default) and `aes-256-cbc-hmac-sha256` (BCL-only, encrypt-then-MAC), selected with the optional `X-NavData-Cipher` header. Code: `app/Services/NavDataService.php`, `app/Http/Controllers/Api/NavDataController.php`, exceptions `NavDataNotConfigured` (503) / `NavDataUnsupportedCipher` (400), route registered inside the `api.auth` group with `EnableActivityLogging` + `throttle:30,1` (activity log `log_name = navdata`). The contract is frozen by `tests/NavDataKeyTest.php`, which decrypts **by hand** (no service reuse) for both ciphers, and documented in `api_vms.md` §1.10 + `docs/vmsopenacars/ENTREGA-CLAVE-NAVDATA.md` (with .NET Framework 4.8.1 samples: manual HKDF + `AesCryptoServiceProvider`, or BouncyCastle for GCM). The published `.config` in the file manager must keep `navdata_api_key` empty.

**Weather widget (`widgets/weather.blade.php`):**
Replaced the default CheckWX text widget with a metar-taf.com embed. The embed uses an account-specific token (`ons7HDjV`) that cannot change. ICAO is dynamic via `$config['icao']` (NOT `$icao` — the Weather widget class passes the array as `$config`). The embed HTML structure is validated by metar-taf.com: `id="metartaf-{token}"` on the anchor and `target={token}` on the script src must match exactly. Only the ICAO path segment and `bg_color` param are safely changeable. The widget is scaled 80% via `transform:scale(0.8); transform-origin:top left` inside a `240px × 348px; overflow:hidden` wrapper to fit the sidebar column. Called from the dashboard as `Widget::Weather(['icao' => $current_airport])`.

### Dashboard

`app/Http/Controllers/Frontend/DashboardController.php` passes to `dashboard.index`:
- `$last_pirep` — the user's single most recent pirep (via `$user->last_pirep_id`)
- `$recent_pireps` — last 5 non-draft/non-cancelled pireps for the user (direct Pirep query)
- `$current_airport`, `$user`

The Vholar dashboard shows `$recent_pireps` as a compact table (not `$last_pirep`). Columns: avatar, flight number (linked), route, flight time, **score** (color-coded: green ≥80, yellow ≥60, red <60, `—` if null), state badge, date. The sidebar "Recent Reports" uses `Widget::latestPireps(['count' => 5])` rendered by `resources/views/layouts/vholar/widgets/latest_pireps.blade.php` — includes link to each pirep's show page.

### Pirep Detail (`/pireps/{id}`)

**Route is auth-protected** — moved from the public route group into the authenticated group in `RouteServiceProvider.php`. The short URL `r/{id}` remains public. `/pireps/{id}` redirects to login if unauthenticated.

`app/Http/Controllers/Frontend/PirepController::view()` passes:
- `$altitudeProfile` — FLIGHT_PATH ACARS points for altitude chart (Chart.js)
- `$logData` — parsed ACARS log data from `FlightAnalysisHelper::parseLogData()`
- Standard pirep + map features from `GeoService`

`resources/views/layouts/vholar/pireps/show.blade.php` — two-column layout then full-width sections:

**Two-column row (`col-8` / `col-4`):**
- **Left `col-8`:** (1) flight description card (departure/arrival city, block times, progress bar); (2) Advanced Flight Analysis card — header shows aircraft type badge and network VID; (3) Takeoff / Landing cards side by side (`col-6`/`col-6` within the col-8); (4) approach capture bar. Sections 2–4 only render `@if(!empty($logData))`.
- **Right `col-4`:** Pilot (linked to profile), Aircraft (linked to `/daircraft/{reg}`), State badge, Status badge, Source, Flight type, Route, Notes, Fields, Fares. Score and Landing Rate are **not** shown here — they appear in the left column analysis cards.

**Advanced Flight Analysis card logic (`$hasAnalysis`):**
- Card renders when `$sc` OR `count(penalties) > 0` OR `count(bonuses) > 0` — no longer gated solely on score presence.
- `$sc` priority: log `Puntuación:/Score:` line → `$pirep->score` DB field (shown without rating label) → null.
- Score column (`col-4`): only rendered when `$sc !== null`; otherwise penalties/bonuses take `col-12`.
- **Bonuses:** green `+N pts reason` lines in a "Bonificaciones" section after penalties.
- **Unidentified penalties:** when score comes from DB fallback and log penalties/bonuses exist but math doesn't reconcile (`100 − Σpenalties + Σbonuses ≠ pirep->score`), a `−N pts Penalizaciones no identificadas` line is appended in italic/muted style.

**Full-width below the two-column row (in order):**
1. Map (`@include('pireps.map')`)
2. Flight log table (`$pirep->acars_logs`) — format `Y-m-d H:i:s`
3. SimBrief OFP (if present)
4. Altitude Profile chart (`@if($altitudeProfile ...)`) — independent of logData, always shown if data exists

### Automatic PIREP Feedback (AI)

Gives every filed PIREP a short flight-instructor review, generated by the DeepSeek API from the ACARS telemetry. Advisory only — **nothing in the PIREP lifecycle reads it**, so a failed analysis can never affect a flight.

**Flow:** `PirepFiled` → `PirepFeedbackListener` (only checks the setting and enqueues) → `GeneratePirepFeedback` job → `PirepFeedbackService::analyseAndStore()` → Discord broadcast + pilot email.

**Why a queue and not a listener that analyses:** `PirepService::submit()` runs inside the ACARS client's HTTP request (`Api\PirepController.php`), and the analysis takes 12–25 s. Analysing inline would add that wait to every PIREP submission. `QUEUE_DRIVER=database` plus the `cron:queue` already scheduled in `App\Console\Kernel` processes it in **~50–60 s**. The listener must stay synchronous and do nothing but enqueue.

**Files:**
- `app/Services/PirepFeedbackService.php` — payload build, API call, response validation, cost estimate
- `app/Jobs/GeneratePirepFeedback.php` — `$afterCommit`, `$tries=1`, `$timeout=300` (must exceed the 180 s HTTP timeout)
- `app/Listeners/PirepFeedbackListener.php` — registered on `PirepFiled` in `EventServiceProvider`
- `app/Models/PirepAiFeedback.php` + `app/Database/migrations/2026_10_03_120000_create_pirep_ai_feedback_table.php`
- `app/Console/Commands/PirepAiFeedback.php` — `phpvms:pireps-ai-feedback` (`--limit`, `--pirep`, `--force`, `--dry-run`, `--recent=N`, `--stats`)
- `app/Notifications/Messages/Broadcast/PirepFeedback.php` (Discord) and `app/Notifications/Messages/PirepFeedback.php` (email) — **separate messages** from the PIREP filing notice
- `resources/views/components/pirep-ai-feedback.blade.php` — the card itself, included from `layouts/vholar/pireps/show.blade.php` via `@include('components.pirep-ai-feedback')`. It lives in its own partial so it can be render-tested: rendering `pireps.show` whole is not possible in the suite (the themed view calls `route('DBasic.aircraft')`, a route from an inactive module, and the test DB seeds `general.theme` as `seven`).

**Testing a themed view:** the test database seeds `general.theme = 'seven'` from `settings.yml` even though the live site runs `vholar`. `SetActiveTheme` reads that setting on every request, so a test that needs the themed view must update the **setting**, not call `Theme::set()` — the middleware overwrites the facade.

**Settings (Admin > Settings):** `general.deepseek_api_key`, `general.deepseek_model`, `general.deepseek_feedback_enabled` (master switch, **off by default**), `notifications.discord_pirep_feedback`, `notifications.mail_pirep_feedback`.

**Two constraints that must not be relaxed:**
1. Only already-parsed numeric telemetry is sent — never the raw ACARS log. The log is written by the pilot's simulator, so it is untrusted input and would be a prompt-injection vector. Rejecting unsupported/nested keys in `cleanTelemetry()` is what enforces this.
2. No pilot identity goes into the payload (no name, id or email), only the pilot's own aggregate landing statistics as a baseline.

**`max_tokens` must stay generous (currently 16000).** The model's reasoning pass is billed as output and already consumes 1.8k–4.1k tokens. Measured on the same 4 PIREPs: at 3000 the reasoning ate the whole allowance and returned empty content in 3 of 4 calls; at 6000 and 16000 all succeeded, and the reasoning used the same amount at both — **raising the ceiling does not raise the bill**, it only removes the empty-answer failure mode. `completion_tokens` includes the reasoning tokens.

**Cost (deepseek-flash, published rates):** ~770 input + ~2,705 output tokens per PIREP ≈ **US$0.0035 peak / US$0.0017 off-peak**. At this VA's ~93 PIREPs/month that is ~US$0.20/month. Peak is only 01:00–04:00 and 06:00–10:00 UTC Monday–Friday; everything else, weekends included, is half price.

### Admin Theme (Bootstrap 3)

The admin panel uses Bootstrap 3 (not Bootstrap 5). When writing or editing admin views:
- Use `form-control input-sm` (not `form-select form-select-sm`)
- Use `form-inline` (not `row g-2 + col-auto`) for inline form rows
- Use `btn-default` (not `btn-secondary`)
- `col-12` is not a real Bootstrap 3 class — use `col-xs-12` or avoid nested rows inside `admin.app` yield (which already wraps in `.container-fluid > .row > .col-12`)

**Admin CSS override:** `public/assets/admin/css/vholar-admin.css` — loaded last with `?v=20260507` cache-bust. Uses `body .table ...` selectors (specificity 0,2,x) to override vendor.css `!important` table background rules. Load order: `global/vendor.css` → `admin/vendor.css` → `admin/admin.css` → `vholar-admin.css`.

**Inline avatar pattern for admin tables** (Bootstrap 3, no BS5 classes): use `style="display:flex;align-items:center;gap:8px;"` wrapper inside the `<td>`, not a separate column, to avoid colspan issues.

### Frontend Assets

Laravel Mix v6 (`webpack.mix.js`). jQuery 3.5.1 + Bootstrap for admin; Leaflet for maps; Select2 for dropdowns; CKEditor for rich text. Run `npm run dev` during development, `npm run production` for deployment.
