# Pull request descriptions

Two independent PRs, one per repository. Both are attribute renames in Blade views only: no PHP logic, no dependencies, no migrations, no CSS changes, no file mode changes.

---

## PR 1 — `FatihKoz/DisposableBasic`

**Patch:** `0001-dbasic-bootstrap5-data-attributes.patch`
**Base commit:** `f0b03db5bef7a5b7ea08e93ef07e3e86d9f3cbfd`
**Title:** `Bootstrap 5: migrate modal and collapse data attributes`

### Why

Disposable Theme v3 based themes ship Bootstrap 5. These three views still use the Bootstrap 4 attribute names, so on a Bootstrap 5 site the modals never open and the collapse never toggles:

- `Resources/views/ranks/index.blade.php` — rank collapse (`data-toggle="collapse"`, `data-target`), modal close (`data-dismiss`)
- `Resources/views/widgets/fuel_calculator.blade.php` — modal (`data-backdrop`, `data-keyboard`) and close button
- `Resources/views/widgets/map.blade.php` — map-source modal (same attributes)

Bootstrap 5 removed `data-toggle` / `data-target` / `data-dismiss` / `data-backdrop` / `data-keyboard` in favour of the `data-bs-*` names, so this markup is dead in a modern theme.

### What changed

8 lines across 3 files:

`data-toggle` → `data-bs-toggle`, `data-target` → `data-bs-target`, `data-dismiss` → `data-bs-dismiss`, `data-backdrop` → `data-bs-backdrop`, `data-keyboard` → `data-bs-keyboard`.

`git show --stat` lists only those three views; there are no mode changes and nothing else is touched.

### How it was tested

On a phpVMS 7 installation (7.0.5, later updated to 7.0.10) with `DisposableBasic` enabled inside a Bootstrap 5 theme:

- the per-rank panel expands/collapses,
- the fuel calculator modal opens and closes (button, backdrop and keyboard),
- the map-source modal opens and closes.

All three were dead before the patch and work afterwards.

### Compatibility note

These are hard renames, so a site still rendering these views with **Bootstrap 4** would lose that behaviour. If both need to be supported, tell me and I will send a variant that emits both attribute sets (or guards them behind a theme check).

---

## PR 2 — `FatihKoz/DisposableSpecial`

**Patch:** `0002-dspecial-bootstrap5-data-attributes.patch`
**Base commit:** `d1d776cea9d4e6453dad99e59b18f3f3a9ddd153`
**Title:** `Bootstrap 5: migrate modal, pill and collapse data attributes`

### Why

Same root cause. The tour views and the NOTAM widget use Bootstrap 4 attribute names, so with a Bootstrap 5 theme the tour tabs and the NOTAM collapse do not respond:

- `Resources/views/tours/index.blade.php` — 6 tab pills (`data-toggle="pill"`)
- `Resources/views/tours/show.blade.php` — 2 tab pills
- `Resources/views/widgets/notams.blade.php` — `data-toggle="collapse"` + `data-target` on the "Show More" icon

### What changed

9 lines across 3 files: `data-toggle` → `data-bs-toggle` (8 occurrences) and `data-target` → `data-bs-target` (1 occurrence). Nothing else; `git show --stat` lists only those three views and there are no mode changes.

### How it was tested

Same installation, `DisposableSpecial` enabled in a Bootstrap 5 theme:

- the tour index tabs (active / future / closed / rules / awards / report) switch correctly,
- the tour detail tabs (legs / rules / awards / report) switch correctly,
- the NOTAM "Show more" icon expands and collapses the list.

Before the patch, clicking the pills did nothing and the NOTAM block stayed collapsed.

### Compatibility note

Identical to PR 1: these are hard renames, so Bootstrap 4 renderings of these views would need the legacy names. Happy to send a dual-attribute variant if you want both.

---

## Applying the patches

```bash
git clone https://github.com/FatihKoz/DisposableBasic /tmp/pr-dbasic
git -C /tmp/pr-dbasic checkout f0b03db5bef7a5b7ea08e93ef07e3e86d9f3cbfd
git -C /tmp/pr-dbasic apply 0001-dbasic-bootstrap5-data-attributes.patch

git clone https://github.com/FatihKoz/DisposableSpecial /tmp/pr-dspecial
git -C /tmp/pr-dspecial checkout d1d776cea9d4e6453dad99e59b18f3f3a9ddd153
git -C /tmp/pr-dspecial apply 0002-dspecial-bootstrap5-data-attributes.patch
```

Both files were produced with `git format-patch` from a single commit on top of exactly those base commits, so `git am` also works if you prefer to keep the commit message and authorship.

## Suggested submission notes

- One PR per repository; they are independent and can be merged in any order.
- Keep the commit message (it explains the BS5/BS4 trade-off); the PR body can be the "Why / What changed / How it was tested" sections above.
- If the maintainer prefers a different approach (dual attributes, or a per-theme conditional), the change is small enough to rework on request.
