# Eight verified defects in the `VmsOpenOps` phpVMS module

**Component:** `Modules\VmsOpenOps` — phpVMS 7 module ("Open Source Operations Module for phpVMS - Jumpseats and Aircraft Ferries with distance-based pricing").
**Environment where these were verified:** phpVMS 7.0.5 → 7.0.10 (Laravel 10, PHP 8.3, MySQL/MariaDB). Module imported at commit `4154ace`.
**Line references** are taken from commit **`587bda0`** (the pre-fix state of this fork). All eight defects were fixed locally in commit **`249abce`**, so they are no longer reproducible on `fhprietor/vmsOpenOps@main`; the references below describe the code as reported.

**Repo/authorship note:** `composer.json` still carries scaffold metadata (`your-vendor/vmsopenops-module`, author `Your Name`), `module.json` has no homepage, and the only remote is `fhprietor/vmsOpenOps`. I checked `FatihKoz/{VmsOpenOps,vmsopenops,phpvms-vmsopenops,VMSOpenOps}` (none exist) and searched the web: **no upstream repository was identified for this module**, so this report has no upstream tracker.

## Summary

| # | Defect | Severity | Where (at `587bda0`) |
|---|--------|----------|----------------------|
| 1 | `GET /api/vmsopenops/operations/pending` returns 500 | high | `Http/Controllers/Api/OperationsController.php:70` |
| 2 | Ferry over the API never works (always HTTP 400) | high | `Http/Controllers/Api/OperationsController.php:379`, `:424` |
| 3 | Ferry price diverges up to ×100 between API and frontend | high | `Http/Controllers/Api/OperationsController.php:186-187`, `:405`, `:579-580` |
| 4 | `updateSettings()` writes settings without `id` → rows with empty primary key | high | `Http/Controllers/Admin/OperationsController.php:183,194,205,217,228,239,250,261,272,284,295,307` |
| 5 | Four settings are read by the code but never seeded | medium | `Database/Migrations/2024_01_01_000002_add_operations_settings.php:14,26,39,51,63,76,88` |
| 6 | `POST /vmsopenops/charter/aircraft` targets a non-existent method | medium | `Http/Routes/web.php:28` |
| 7 | Dead `Frontend\StatisticsController@getData()` queries non-existent columns | low | `Http/Controllers/Frontend/StatisticsController.php:23`, `:210`, `:212` |
| 8 | Stray `要` character as first child of `<thead>` (3 views) | low | `Resources/views/admin/index.blade.php:72`, `Resources/views/frontend/jumpseat/index.blade.php:45`, `Resources/views/frontend/ferry/index.blade.php:46` |

---

## 1. `GET /api/vmsopenops/operations/pending` always returns 500

**Where:** `Http/Controllers/Api/OperationsController.php:70`

```php
'pending_requests' => OperationRequest::pendingForUser($userId, $type)
```

**Why it fails:** `OperationRequest` defines `scopePending()`, `scopeForUser()`, `hasPendingRequest()`, `getPendingCount()` and `getPendingRequest()` (`Models/OperationRequest.php:121`, `:146`, `:154`, `:173`, `:188`) — there is no `pendingForUser()` method, so the call is a `BadMethodCallException`.

**Repro:** sign in (the module routes use the `web` + `auth` guards) and request:
`GET /api/vmsopenops/operations/pending`

**Expected:** `200` with `{"success":true,"data":{"has_pending":…,"pending_count":…,"pending_requests":[…]}}`.

**Actual:** `500` (undefined method `pendingForUser`).

**Suggested fix:** use the existing scopes:

```php
'pending_requests' => OperationRequest::pending()
    ->forUser($userId)
    ->when($type, fn ($query) => $query->where('operation_type', $type))
    ->with(['fromAirport', 'toAirport', 'aircraft'])
    ->orderByDesc('created_at')
    ->get()
```

## 2. Ferry over the API never works (always HTTP 400)

**Where:** `Http/Controllers/Api/OperationsController.php:379` (initial check) and `:424` (re-check inside the transaction)

```php
if ($aircraft->status != AircraftState::PARKED) {
    return response()->json(['success' => false, 'message' => 'Aircraft not available for ferry'], 400);
}
```

**Why it fails:** `aircraft.status` is the *aircraft status* (`'A'` = active, see `AircraftStatus`), while the operational state lives in `aircraft.state`. `AircraftState::PARKED` is `0`, so `'A' != 0` is always true and the endpoint rejects every aircraft. The frontend does it correctly: `Frontend/FerryController.php:280` checks `$freshAircraft->state != AircraftState::PARKED` and `:284` checks `$freshAircraft->status != AircraftStatus::ACTIVE`. The same file already uses `state` correctly for the "available aircraft" query (`:565`), which shows the inconsistency.

**Repro:** `POST /api/vmsopenops/ferry` with `aircraft_id` of any parked, active aircraft → always `400 {"message":"Aircraft not available for ferry"}`.

**Expected:** `200`, accepting an aircraft whose `state = PARKED` and `status = ACTIVE` (same rule as the frontend).

**Actual:** `400` for every aircraft, even perfectly valid ones.

**Suggested fix:** compare the right columns, in both places:

```php
if ($aircraft->state != AircraftState::PARKED || $aircraft->status != AircraftStatus::ACTIVE) { … }
```

## 3. Ferry price diverges up to ×100 between API and frontend

**Where:** `Http/Controllers/Api/OperationsController.php`

- `:186-187` (`previewFerry`): `if ($costPerNm < 100) { $costPerNm = $costPerNm * 100; }`
- `:405` (`storeFerry`): `$cost = new Money($distance * $costPerNm * 100);`
- `:579-580` (`getAvailableAircraft`): same heuristic as `:186-187`

**Why it matters:** the ferry *create* view quotes with the **API** (`/api/vmsopenops/ferry/available` and `api.vmsopenops.api.ferry.preview`) but the charge is made by the **frontend** route (`POST /vmsopenops/ferry`), which computes `max(round($distance * $costPerNm), $this->getMinFerryCostCents($aircraft))` (`Frontend/FerryController.php:133-134`). Settings are documented as **cents per NM** (`vms_open_ops.ferry.cost_per_nm` seeded as `500`), so multiplying by 100 changes the meaning of the configured value, and the unconditional `* 100` in `storeFerry` makes the API charge 100× the quote of the frontend.

**Repro (measured in our install):** aircraft 7,442.79 NM away with `ferry.cost_per_nm = 600` (cents/NM):

- correct charge (frontend): `7442.79 × 600 = 4,465,674` cents = **$44,656.74**
- API response before the fix: **$4,465,674** (100× more)

**Expected:** one single pricing rule shared by the frontend, the preview endpoints and the "available aircraft" endpoint, including the MTOW-based minimum.

**Actual:** three different computations, with the heuristic triggering or not depending on the stored value.

**Suggested fix (what we did):** a single pricing helper — `Modules\VmsOpenOps\Support\OpsPricing` with `jumpseatCostCents()`, `ferryCostCents()` and `ferryMinCostCents()` — used by every call site. After the change, API preview, frontend preview and frontend charge return the same number, and the jumpseat minimum floor is applied consistently too (the API previously ignored `jumpseat.min_cost`).

## 4. `updateSettings()` writes settings without `id` → rows with an empty primary key

**Where:** `Http/Controllers/Admin/OperationsController.php` — 12 calls at lines `183, 194, 205, 217, 228, 239, 250, 261, 272, 284, 295, 307`

```php
\App\Models\Setting::updateOrCreate(
    ['key' => 'vms_open_ops.jumpseat.enabled'],
    [ 'value' => …, 'name' => …, 'group' => 'VmsOpenOps', 'type' => 'bool', … ]
);
```

**Why it fails:** the `settings` table primary key is the string column `id` (`vms_open_ops_jumpseat_enabled`, …), as the seed migration itself shows (`Database/Migrations/2024_01_01_000002_add_operations_settings.php:13`). Looking rows up by `key` and inserting without `id` means that, on a database where the row does not exist yet, the insert has no primary key value: MySQL stores `id = ''`, producing a **duplicate, unreachable row** — `setting()` looks settings up by id, so the orphan is never read, and Admin → Settings renders it with an empty field name.

**Evidence:** in our install we found exactly such a row (`id = ''` with key `vms_open_ops.jumpseat.enabled`) next to the real one.

**Repro:** start from a database where the VmsOpenOps settings do not exist (fresh install, or after deleting the rows) and save the admin settings form once. Then:

```sql
SELECT id, `key` FROM settings WHERE `key` LIKE 'vms_open_ops%';
```

**Expected:** one row per setting, each with its proper `id`.

**Actual:** rows with `id = ''` appear.

**Suggested fix:** include the id in the lookup (or reuse a helper like the migration's `createOrUpdateSetting()`):

```php
Setting::updateOrCreate(
    ['id' => 'vms_open_ops_jumpseat_enabled'],
    ['key' => 'vms_open_ops.jumpseat.enabled', 'value' => …]
);
```

We applied that to all 12 calls and removed the orphan row with a data migration.

## 5. Four settings are read by the code but never seeded

**Where:** seed migration `Database/Migrations/2024_01_01_000002_add_operations_settings.php` seeds 7 keys (lines `14, 26, 39, 51, 63, 76, 88`): `jumpseat.enabled`, `jumpseat.cost_per_nm`, `ferry.enabled`, `ferry.cost_per_nm`, `ferry.require_certification`, `require_reason`, `max_reason_length`.

**Missing but used with hard-coded defaults:**

- `vms_open_ops_jumpseat_min_cost` → `Frontend/JumpseatController.php:137` and `:293` (`setting(..., 5000)`)
- `vms_open_ops_ferry_min_cost_light` / `_medium` / `_heavy` → `Frontend/FerryController.php:379`, `:383`, `:387`, `:390` (`20000` / `50000` / `100000`)

**Impact:** the values exist only as PHP defaults, so they cannot be tuned from the panel until an admin saves the form — and the admin form does validate them (`Admin/OperationsController.php:166`, `:169`, `:170`, `:171`), which combined with defect 4 creates malformed rows on that first save.

**Repro:** `SELECT id FROM settings WHERE id LIKE 'vms_open_ops%';` on a clean install → only 7 rows; Admin → Settings shows no min-cost fields.

**Expected:** the 11 settings of the module exist after installing/migrating and can be edited.

**Actual:** 4 of them are absent.

**Suggested fix:** seed them in a new migration (id, key, name, value, default, group `VmsOpenOps`, type `int`, orders 9102 and 9203-9205). We did exactly that in `2024_01_01_000003_add_missing_operations_settings.php`, which also deletes the orphan row of defect 4.

## 6. `POST /vmsopenops/charter/aircraft` targets a non-existent method

**Where:** `Http/Routes/web.php:28`

```php
Route::post('/aircraft', 'CharterController@getAvailableAircraft')->name('charter.aircraft');
```

`Frontend/CharterController.php` only defines `__construct`, `create`, `preview` and `store` (lines `27`, `36`, `88`, `146`). The equivalent method exists only in the API controller (`Api\OperationsController@getAvailableAircraft`). No view references the route (`grep -rn "charter.aircraft" Resources/` returns nothing), and the aircraft list is passed by `create()`.

**Repro:** authenticated `POST /vmsopenops/charter/aircraft` → `500` (action not defined).

**Expected:** either a working endpoint or no route.

**Suggested fix:** delete the route (what we did), or implement the method.

## 7. Dead `Frontend\StatisticsController@getData()` queries non-existent columns

**Where:** `Http/Controllers/Frontend/StatisticsController.php:23` (`getData`), with `:210` `DB::raw('SUM(passengers) as total_passengers')` and `:212` `DB::raw('SUM(cargo) as total_freight')`.

**Why it is wrong:** the web routes only map the view (`Http/Routes/web.php:33` → `StatisticsController@index`); the data endpoint used by the page is the API one (`Http/Routes/api.php:15` → `Api\StatisticsController@getData`, which uses `pirep_fares`/`FareType`). The frontend method is unreachable dead code, and its queries reference `pireps.passengers` and `pireps.cargo`, columns that **do not exist** in the `pireps` table (verified against the schema; fare data lives in `pirep_fares`), so wiring that route would throw `SQLSTATE[42S22] Unknown column`.

**Repro:** `php artisan route:list | grep -i stats` → only `api/vmsopenops/stats` and the frontend *index* route; `getData` has no route. Calling it directly throws the SQL error.

**Expected:** only one statistics implementation.

**Suggested fix:** delete `getData()`, `computeStats()` and `formatMinutes()` from the frontend controller (we did; the file now only has `index()`).

## 8. Stray `要` character as first child of `<thead>`

**Where:** `Resources/views/admin/index.blade.php:72`, `Resources/views/frontend/jumpseat/index.blade.php:45`, `Resources/views/frontend/ferry/index.blade.php:46`.

A literal CJK character sits as the first node inside the table header row, so it renders as a stray glyph above the first column.

**Repro:** open the admin operations list, the jumpseat list and the ferry list.

**Expected:** no stray character.

**Actual:** `要` is rendered (and it also ends up in copy/pasted table text).

**Suggested fix:** remove the line in the three views (what we did).

---

## How these were verified

- Every reference above was checked against the actual source at commit `587bda0` with `git grep -n` (no guesses): the line numbers are exact for that revision.
- For 2 and 3 the behaviour was reproduced with real data: an aircraft with `status = 'A'` / `state = 0` is rejected by the old condition and accepted by the corrected one, and the API quote was compared against the frontend charge for a 7,442.79 NM ferry.
- Defects 1, 6, 7 and 8 were exercised end to end (HTTP requests, `route:list`, and rendering the three list pages); defect 5 was checked against the seed migration and the `settings` table.
- Defect 4 was observed in the `settings` table (`id = ''`), which is how the orphan row was found.

## Unrelated to this report

Two separate pull requests accompany this document for the **Bootstrap 5** migration of the `data-*` attributes in `DisposableBasic` and `DisposableSpecial` (`data-toggle`/`data-target`/`data-dismiss`/`data-backdrop`/`data-keyboard` → `data-bs-*`), which is a different codebase (FatihKoz's modules) and is not part of this issue.
