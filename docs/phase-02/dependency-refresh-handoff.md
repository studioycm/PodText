# Bounded Dependency Refresh Handoff

Date: 2026-07-21

## Approved contract

- Audit ID: `LS-20260721-DEPENDENCY-REFRESH-01`
- Approved option: `DEP-UPGRADE-O1-BOUNDED-FULL-REFRESH`
- Scope: refresh the complete Composer and npm graphs only as far as the
  existing `composer.json` and `package.json` constraints allow.
- Initial disposition: leave the reviewed result uncommitted and do not push.
- Later operator instruction: `then commit`, authorizing the canonical local
  implementation commit and immediate docs-only hash stamp. No push is
  authorized.

## Outcome

Both dependency graphs are refreshed within their unchanged manifests. The
resolved stack now includes:

- Laravel 13.21.1;
- Filament 5.7.1, including the Spatie settings and tags plugins;
- Livewire 4.3.3, unchanged;
- Laravel Horizon 5.48.1;
- Awcodes Curator 5.1.2, unchanged;
- Pest 4.7.5;
- Laravel Boost 2.4.13;
- Tailwind CSS and its Vite integration 4.3.3;
- Vite 8.1.5; and
- Laravel Vite plugin 3.1.3.

The refresh required two bounded compatibility corrections: one Filament
action-unmount call now passes the boolean positionally so it does not depend
on a vendor parameter name, and the Card Template browser regression accounts
for Filament 5.7 dismissing a visible tooltip before Escape closes the modal.

No manifest constraint, major version, package family, migration, schema,
security boundary, application feature, production system, worker, or local
development database changed.

## Exact baseline and drift

- cwd and Git root: `/Users/studioycm/Herd/PodText`
- branch: `main`
- starting HEAD: `996a900c87583ee5c8b6d32329a39a25dd8f2f0b`
- `origin/main`: `7c55dca4012ce48779b32b2e3c4d2076d9198807`
- starting branch position: four commits ahead, zero behind
- starting worktree: clean
- runtime: PHP 8.4.23, Node.js 22.23.1, npm 10.9.8

No competing or user-owned work appeared during the refresh or the later
commit closeout. The observed HEAD, branch, and origin baseline did not drift.

## Compatibility and publication findings

### Filament actions and focus

Filament 5.7 renamed the boolean parameter accepted by `unmountAction()`.
`CardTemplateEditorPage` now calls `unmountAction(false)`, preserving the
existing one-action-at-a-time cleanup without coupling PodText to that
parameter name.

Filament 5.7 also dismisses a visible tooltip on the first Escape keypress.
The browser regression sends a second Escape only when the preview modal is
still open, then verifies that focus returns to the opener.

### Published assets

Composer's standard `filament:upgrade` hook republished every registered local
Filament/Curator output. Eighteen tracked core Filament outputs changed relative
to the starting commit; all 18 match the installed Filament 5.7.1 distribution
files byte-for-byte. The command registers 39 tracked outputs in this checkout,
so 18 is the changed-file count, not the total number copied.

No Filament config or Blade view was republished. PodText has no
`resources/views/vendor` tree and no `lang/vendor` package translation
overrides. The six customized package config files remain app-owned and
unchanged. Filament 5.7.1's new Hebrew translations therefore load directly
from the installed package whenever the application locale is `he`.

### Laravel, Livewire, Horizon, and frontend

The Laravel 13 guide contains no additional step for this 13.19.0 to 13.21.1
minor refresh. Livewire remained at 4.3.3, so no Livewire migration applies.
Horizon 5.48 adds optional CSP/dev-command behavior but no required PodText
config change. Vite, the Laravel Vite plugin, and Tailwind changes are in-range
fixes; PodText's Bunny font build completed successfully.

Production deployment must keep Composer's Filament upgrade hook, build the
frontend assets, reload the normal PHP runtime, and gracefully terminate
Horizon under its process monitor so workers load the new release. Those are
future deployment actions and were not run here.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Preserve Composer manifest constraints | Implemented | `composer.json` is byte-identical to the starting commit. |
| Preserve npm manifest constraints | Implemented | `package.json` is byte-identical to the starting commit. |
| Refresh all in-range Composer dependencies | Implemented | Resolver completed without a major or platform-boundary change. |
| Refresh all in-range npm dependencies | Implemented | Resolver completed without a manifest or major change. |
| Review security advisories | Implemented | Composer and npm audits reported no advisories after resolution. |
| Correct directly required compatibility failures | Implemented | Filament action parameter and tooltip-first Escape behavior corrected. |
| Validate tracked published assets | Implemented | All 18 changed Filament outputs match installed 5.7.1 dist files. |
| Republish configs or views | Not applicable | Upstream templates did not require it; no app-owned published view exists. |
| Update app Hebrew language files | Not applicable | New Filament Hebrew strings load from vendor; no override masks them. |
| Upgrade Livewire or Curator | Already current | Livewire remains 4.3.3; Curator remains 5.1.2. |
| Upgrade Spatie Laravel Permission to 8 | Deferred/excluded | Existing manifest pins 7.3.0 and Shield 4.2 permits 6 or 7. |
| Upgrade concurrently to 10 | Deferred/excluded | Existing `^9.0.1` constraint is preserved. |
| Schema, migration, production, or worker action | Not applicable/not authorized | None performed. |
| Commit and push | Partially authorized later | Canonical local commits authorized after review; push remains prohibited. |

## Files changed

### Dependency and compatibility files

- `composer.lock`
- `package-lock.json`
- `app/Filament/Pages/CardTemplateEditorPage.php`
- `tests/Browser/CardTemplatePreviewBrowserTest.php`

### Published Filament outputs

- `public/css/filament/filament/app.css`
- `public/js/filament/actions/actions.js`
- `public/js/filament/filament/app.js`
- `public/js/filament/filament/echo.js`
- `public/js/filament/forms/components/code-editor.js`
- `public/js/filament/forms/components/date-time-picker.js`
- `public/js/filament/forms/components/file-upload.js`
- `public/js/filament/forms/components/markdown-editor.js`
- `public/js/filament/forms/components/rich-editor.js`
- `public/js/filament/forms/components/select.js`
- `public/js/filament/forms/components/slider.js`
- `public/js/filament/forms/components/tags-input.js`
- `public/js/filament/notifications/notifications.js`
- `public/js/filament/schemas/schemas.js`
- `public/js/filament/support/support.js`
- `public/js/filament/tables/components/columns/select.js`
- `public/js/filament/tables/tables.js`
- `public/js/filament/widgets/components/stats-overview/stat/chart.js`

### Durable documentation

- `docs/research/dependency-refresh/00-dependency-refresh-research.md`
- `docs/research/dependency-refresh/01-dependency-refresh-implementation-plan.md`
- this handoff
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

No `composer.json`, `package.json`, migration, schema, application config,
application language, or published Blade view file changed.

## Tests updated

- `tests/Browser/CardTemplatePreviewBrowserTest.php` now preserves the modal
  focus-return contract across Filament's tooltip-first Escape handling.
- No new test file was needed; the existing focused and full regression suites
  exercise the changed framework/package paths.

## Commands and results

Read-only orientation included cwd/root/status/HEAD/origin/log checks, complete
lockfile and manifest diffs, installed package source/version inspection,
published asset byte comparisons, upstream config/language/view comparisons,
and official upgrade/release documentation review. No secret-bearing value was
printed.

| Command / check | Result |
|---|---|
| `composer validate --strict` | PASS with the existing exact-pin warnings for Shield 4.2.0 and Spatie Laravel Permission 7.3.0; both pins are intentional exclusions from this refresh. |
| `composer update --with-all-dependencies --dry-run --no-interaction` | PASS; resolution stayed inside the manifest. |
| `npm update --dry-run --ignore-scripts` | PASS; resolution stayed inside the manifest. |
| `composer update --with-all-dependencies --no-interaction` | PASS; lock graph and installed packages refreshed. |
| `composer audit` | PASS; no advisories. Composer could not write its user-level cache in the sandbox and continued without cache. |
| `npm update --ignore-scripts` | PASS; lock graph and installed packages refreshed. |
| `npm audit` | PASS; no advisories. |
| `npm prune` / `npm ls --depth=0` | Dependency tree valid; npm 10 continues to list optional Darwin WASM helpers as extraneous without invalid peers. |
| Focused Filament/Livewire/Card Template/Curator/import-export/public-form/settings feature matrix | Initial action-parameter failure diagnosed and corrected; final PASS: 28 tests / 387 assertions. |
| Focused authenticated Card Template browser file | Initial tooltip-first Escape expectation corrected; final PASS: 14 tests / 1,832 assertions. |
| Requirements classification sweep and `git diff --check` | PASS |
| `vendor/bin/pint --test` | PASS on final code state. |
| `vendor/bin/filacheck` | PASS on final code state with 0 issues. |
| `npm run build` | PASS on final code state. The Laravel font plugin warned that optional `fontaine` is absent, so optimized fallback generation is disabled; all configured Instrument Sans assets were generated. |
| First closeout `php artisan test` | FAIL at browser bootstrap: all 979 non-browser tests passed with 11,742 assertions, then Chromium hit the known macOS `MachPortRendezvousServer ... Permission denied` sandbox boundary and 22 browser tests failed or cascaded. |
| Browser infrastructure response | No application or test file changed. The identical full-suite command was rerun outside the macOS browser sandbox. |
| `php artisan test` outside the browser sandbox | PASS last, serial, and uninterrupted: 1,001 tests / 13,635 assertions. |

All tests used the test environment. The local development database and
storage were not probed. Browser and full-suite commands were not parallelized.

## Assumptions and deferred work

- Existing manifest constraints remain the operator-selected compatibility
  boundary; no constraint widening was inferred.
- Filament's upstream Hebrew catalog remains incomplete in a few newer
  surfaces. Missing keys fall back to English. Complete Hebrew parity would be
  a separately audited localization task using narrow overrides, not a full
  vendor language publication.
- Filament's opt-in CSV formula protection was not enabled because PodText
  already owns explicit spreadsheet-formula escaping; combining them requires
  separate double-escaping review.
- Production deployment, PHP runtime reload, Horizon termination, migrations,
  cache/process actions, and runtime smoke checks remain operator/deployment
  actions. No production mutation is authorized by this handoff.
- Future major upgrades, Spatie Laravel Permission 8, and concurrently 10
  remain outside this bounded refresh.

## Local Front Check Report

These are manual operator steps, not claims that a browser check ran:

1. Open the Hebrew admin panel and navigate through the sidebar; expect RTL
   layout and Filament's new Hebrew accessibility/notification labels without
   raw translation keys.
2. Open a Card Template, launch a nested Builder action, close it, and open the
   preview; expect only the intended action to remain mounted.
3. Hover the preview control until its tooltip is visible, press Escape once,
   then press Escape again if the modal remains open; expect the tooltip then
   modal to close and focus to return to the preview opener.
4. Edit Markdown while the editor is focused and save; expect the final focused
   state to persist without losing the latest text.
5. Upload more than one allowed image through an existing Filament upload
   surface; expect parallel completion not to remove an in-flight upload.
6. Run a test import and export through the existing admin actions; expect
   authorization, queued lifecycle behavior, and PodText's spreadsheet-formula
   escaping to remain intact.
7. Load a public and admin page using Instrument Sans; expect the Bunny-hosted
   font assets to resolve under the deployment's normal asset base.
8. After an authorized deployment, verify Horizon is consuming the configured
   queues from the new release and that the dashboard loads without console or
   CSP errors.

## No-environment-mutation statement

No production, local development database/storage, migration, worker, daemon,
deployment, branch, worktree, or push action occurred. Composer's normal local
`filament:upgrade` hook refreshed package-owned public assets and cleared local
framework configuration, route, and compiled view caches; that automatic local
tooling effect is disclosed rather than described as no cache mutation.

## Commit hash

`pending`
