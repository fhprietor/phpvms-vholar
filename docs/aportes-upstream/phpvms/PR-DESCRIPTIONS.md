# Pull requests for phpVMS

Four independent PRs. Base: tag **7.0.10** (`46a0e88e`, branch `releases/7.0`).
Each patch is one commit; apply with `git am <file>.patch`.

Prepared in a clean clone of `7.0.10`, so they carry no fork-specific changes and
no file-mode changes. With all four applied the suite passes:
`OK (211 tests, 1184 assertions)` (upstream baseline plus the new regression test).

**Target branch**: 7.x fixes go to `releases/7.0`. Items 1, 2 and 4 also apply to
`main` (8.x) with small adjustments — in 8.x `app/Repositories` is gone, so the
`source_name` filter belongs in the new Query class.

---

## PR 1 — `GET pireps/{id}/acars/logs`

**Patch:** `0001-api-add-endpoint-to-read-the-ACARS-log-entries-of-a-.patch`
**Title:** `api: add endpoint to read the ACARS log entries of a PIREP`
**Files:** `app/Http/Controllers/Api/AcarsController.php`, `app/Providers/RouteServiceProvider.php`

### Why
The API can **write** ACARS log entries (`POST pireps/{id}/acars/logs`) but there
is no way to **read** them back: `GET pireps/{id}/acars` only returns the flight
path (`type=0`) and `/acars/geojson` only the positions. Anything that needs to
review what was recorded (analytics, a client showing its own log, support) has to
go to the database.

### What it does
Adds `GET pireps/{pirep_id}/acars/logs`, mirroring the existing `acars_get`: it
returns the `AcarsType::LOG` rows ordered by `created_at`, using the existing
`AcarsRoute` resource.

### How to reproduce the gap
`GET /api/pireps/{id}/acars` returns only `type=0` rows; any `GET
/api/pireps/{id}/acars/logs` is a 404.

### How it was tested
`php -l` plus the full suite in a clone of 7.0.10 with the four patches applied.

---

## PR 2 — filter the PIREP list by `source_name`

**Patch:** `0002-api-allow-filtering-the-PIREP-list-by-source_name.patch`
**Title:** `api: allow filtering the PIREP list by source_name`
**Files:** `app/Http/Controllers/Api/UserController.php`, `app/Repositories/PirepRepository.php`

### Why
`pireps.source_name` records which client produced a PIREP (for example
`vmsOpenAcars/0.9.16`), but it cannot be filtered: `GET user/pireps` only accepts
`state`, and `searchFields=source_name` fails because the column is not whitelisted
in the repository ("Columns source_name are not accepted in the research").

### What it does
- `?source_name=` on `GET user/pireps` as a **prefix match**, so the client name
  alone returns every version of it.
- Whitelists `source_name` in `PirepRepository::$fieldSearchable`, so
  `searchFields=source_name` works as well.

### How it was tested
Same as PR 1. Manually with real data: `?source_name=vmsOpenAcars` returns the rows
produced by any version of that client.

---

## PR 3 — expand unit fields in the ACARS resource

**Patch:** `0003-api-expand-unit-fields-in-the-ACARS-route-resource.patch`
**Title:** `api: expand unit fields in the ACARS route resource`
**Files:** `app/Http/Resources/AcarsRoute.php`

### Why
`distance` and `fuel` in the ACARS endpoints are cast to `Unit` objects
(`App\Contracts\Unit`). The numeric value lives in a protected property, so
serialising them sends only the unit names and **no value at all**:

```json
"distance": {"localUnit":"nmi","internalUnit":"nmi","responseUnits":["m","km","mi","nmi"]}
```

A client cannot know how far the aircraft went. Confirmed against the live API of
a 7.x installation.

### What it does
`AcarsRoute` expands those fields into unit => value pairs through
`getResponseUnits()`, both for a single row and for a collection (the resource is
used by `acars_get`, which returns a collection).

### Notes
`AcarsRoute` is an empty class upstream. Fixing `Unit` itself (for example making
it implement `JsonSerializable`) would change every unit field in the API, so this
patch is deliberately scoped to the ACARS routes.

### How it was tested
Same as PR 1, plus comparing the JSON of `GET pireps/{id}/acars` before and after.

---

## PR 4 — isolate the test suite (KVP store and logs)

**Patch:** `0004-tests-isolate-the-KVP-store-and-the-log-channels.patch`
**Title:** `tests: isolate the KVP store and the log channels`
**Files:** `tests/TestCase.php`, `tests/TestCaseIsolationTest.php` (new)

### Why
The suite shares two resources with the running application:

1. **KVP store**: `App\Repositories\KvpRepository` is a JSON file in
   `storage/app/kvp.json`. `VersionTest` leaves `new_version_available = true`
   with a fake `latest_version_tag`, so **after running the tests the admin panel
   announces a version which does not exist**; `UtilsTest` leaves stray keys
   behind as well.
2. **Logs**: `App\Contracts\CronCommand::redirectLoggingToFile('cron')` splices
   the handlers of the `cron` channel into the root logger, which is a singleton.
   As soon as a test builds a cron command, the rest of the run writes to
   `storage/logs/cron-*.log` — thousands of `testing.` lines in a production log.

### What it does
`TestCase` points the KVP store and the log channels at
`storage/framework/testing/`, resets the channels already resolved during boot and
cleans both up in `tearDown`. A regression test asserts that neither the
production KVP file nor the production log files receive any write.

### How it was tested
Run the suite and check that the number of `testing.` lines in
`storage/logs/cron-*.log` and the contents of `storage/app/kvp.json` do not change.
