# Settings Development Metrics Retirement Research

## Contract

This document records the implementation preflight for Mini-task 1 only of
Laravel Simplifier audit `LS-20260721-SETTINGS-DEV-METRICS-03`, Option
`SETTINGS-METRICS-O1-FULL-RETIREMENT`.

The operator approved implementation only after the Laravel stack checkout was
stabilized and its ownership boundary was committed. The stabilized baseline is
`0d7c931`, following the Filament 5.7.1 dependency refresh implementation at
`4d2a063`. The checkout was clean on `main` before this work began. Curator G1,
its LMTC correction, and all dependency-refresh changes are preserved.

Mini-task 2 is explicitly out of scope. This run must stop after Mini-task 1
verification and closeout.

## Current purpose of the development metrics

The settings measurement surface was built in three layers:

- SP1 added an opt-in PHP phase profiler, a dedicated log channel, and timing
  wrappers around settings reads, validation, persistence, backups, and schema
  construction.
- SP3A added a local-only query-flagged runtime fixture, response/query headers,
  lifecycle counters, and a browser-console measurement script.
- SP3B added subject-specific stress fixtures and forwarding of measurement
  query parameters to the focused settings pages.
- SP3C reused the SP3A runtime fixture and profiler around the template library,
  one-template editor, reference scanning, and save/delete paths. SP3C also
  introduced separate test-owned canaries and historical report switches.

These facilities were useful for deciding the settings split and the template
editor architecture. They are no longer an application feature: they add
middleware, public Livewire state, alternate data paths, write refusal branches,
runtime fixture classes, logging configuration, and mixed test obligations.

## Reuse and abstraction decision

No new abstraction is justified for the runtime profiler or SP3A/SP3B fixtures.
Their phase names, fixture identities, response headers, and query flags are
tightly coupled to completed experiments. Generalizing them would preserve an
alternate production code path without a current operational consumer.

Reusable functional work must remain in place:

- request-scoped lifecycle schema memoization;
- focused subject-page ownership and fresh-snapshot saves;
- settings import locks and authorization overlays;
- card-template library projection, reference blocking, and focused writers;
- Curator reference-key/path identity projection and validation;
- test-owned SP3C canaries and historical report artifacts deferred to
  Mini-task 2;
- browser interaction deadlines and DOM assertions that verify production UI
  behavior rather than the retired settings harness.

The correct Mini-task 1 boundary is therefore full removal of the runtime
development-metrics path, with functional regressions relocated or rewritten.
Historical handoffs and research remain the archive; deleted runtime files do
not need replacement archive copies because Git preserves them.

## Mini-task 1 inventory

### Delete

- `app/Http/Middleware/MeasureSettingsSp3aResponse.php`
- `app/Support/Settings/SettingsPageProfiler.php`
- `app/Support/Settings/SettingsSp3aMeasurementFixture.php`
- `app/Support/Settings/SettingsSp3bSubjectFixture.php`
- `scripts/settings-sp3a-browser-metrics.js`
- `tests/Feature/SettingsPageProfilerTest.php`, after its maintenance-marker
  regression is moved to functional maintenance coverage

### Unwire and unwrap

- profiling environment, config, and log-channel entries;
- middleware registration and profiler container binding;
- profiler wrappers in settings validation, backup scheduling, save listeners,
  subject pages, schema builders, and card-template pages;
- SP3A/SP3B query flags, fixture overlays, locked public state, and measurement
  write-refusal branches;
- lifecycle derivation counters and the public metrics accessor;
- reference-scanner row/timing values that are not used by functional blocking;
- the bilingual measurement-only write-error translation.

### Preserve while rewriting mixed tests

- lifecycle output identity and application-scope isolation;
- import-lock, restore/import authorization, stale-save, ownership, cache,
  settings-event, and select-loading behavior;
- card-template library/editor authorization, validation, collision,
  transaction, notification, backup, and cache behavior;
- maintenance raw-marker rendering and exact clipboard payload;
- malformed Curator reference-key normalization and reason reporting;
- atomic import application of each Curator reference-key/path pair.

## Explicit Mini-task 2 deferral

This mini-task does not remove the isolated SP3C canary suite, its support
classes and fixtures, `SP3C_*_REPORT` or `STEP5B_*_REPORT` switches,
card-preview report blocks, the production `data-sp3c-*` selectors, or the
functional `admin.settings_sp3c.*` translation namespace. Mixed SP3C test
blocks may be narrowed only where needed to stop consuming the retired runtime
headers, fixtures, and timing DTO fields; the report switches and test-owned
measurement helper remain for the separately audited Mini-task 2. This mini-task
also does not edit `tests/Browser/CardTemplatePreviewBrowserTest.php`; that test
is verification coverage for the preserved Filament 5.7.1 behavior.

## Installed-version research

Laravel Boost reported Laravel 13.21.1, Filament 5.7.1, Livewire 4.3.3, Pest
4.7.5, and PHP 8.4. Installed-version documentation confirmed Laravel 13
middleware registration, Livewire's untrusted public-property boundary, and
Filament custom-page/action testing patterns.

FilamentExamples was searched in two passes for custom settings pages, custom
page saves, Builder schemas, and Livewire form validation. The server exposed
search results and snippets but no source/detail reader. Relevant examples
included the custom edit-profile page and account-settings cluster pages. No
example is copied: the existing focused settings pages are the authoritative
implementation, and this mini-task only removes instrumentation from them.

## Preservation invariants

- Keep the `SettingsSaved` listener order: public-front cache forget, system
  backup creation, render-context forget, transcription-policy forget.
- Keep subject-page authorization, hooks, transaction/Halt handling, fresh
  stored snapshot, owned-root overlay, validation, save, notification, and
  redirect order.
- Keep all Curator media validation, transition, repair, attachment, settings
  identity, and integrity-report behavior.
- Keep `SettingsMediaIdentityProjector::expandSelectedPaths()` so a selected
  reference key or legacy path imports as one identity pair.
- Keep card-template protection, fingerprints, collision checks, import locks,
  normal stored-template validation, and reference blockers.
- Keep `unmountAction(false)` in the card-template editor and do not alter the
  Filament 5.7.1 Escape/focus browser synchronization.
- Keep final verification serial and in repository order.

## Conclusion

The committed stack change is the only baseline drift since the approved audit
and was anticipated by that audit. The scoped findings remain valid. Mini-task
1 can proceed without touching dependencies, migrations, local development
data, Curator implementation, or Mini-task 2 artifacts.
