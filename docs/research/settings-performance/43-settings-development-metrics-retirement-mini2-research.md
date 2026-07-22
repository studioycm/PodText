# Settings Development Metrics Retirement Mini-task 2 Research

## Contract and baseline

This document records the implementation preflight for Mini-task 2 only of
Laravel Simplifier audit `LS-20260722-SETTINGS-DEV-METRICS-M2-01`, Option
`SETTINGS-METRICS-M2-O1-BOUNDED-FULL-RETIREMENT`.

The operator approved the option together with the six follow-up protection
controls. Implementation starts from clean `main` at
`ab37bd0838214bf55612390f7e501ac7e8eef4df`, immediately after Mini-task 1
implementation `3e5c411777994c431417dfd823576286d12d5c29` and its canonical
hash-stamp closeout. The checkout is eight commits ahead of `origin/main` and
zero behind. No concurrent or user-owned changes were present at preflight.

This run is Mini-task 2 only. It must preserve every functional Card Template,
security, deterministic query, browser interaction, Filament 5.7.1, Curator
G1, and Curator G1 LMTC behavior. It does not authorize a later mini-task,
dependency, migration, schema, local-development data, production, deployment,
worker, worktree, or push action.

## Installed-version evidence

Laravel Boost reported PHP 8.4, Laravel 13.21.1, Filament 5.7.1, Livewire
4.3.3, Pest 4.7.5, and Boost 2.4.13. Composer source retains Curator 5.1.2.
Versioned documentation confirms that Filament action modals close by Escape
by default and that Pest browser tests are the correct plane for hydrated DOM,
teleported modal, focus, JavaScript, and interaction behavior.

Server/component HTML counters are not interchangeable with authenticated
browser DOM, listener, heap, network, focus, or timing evidence. Mini-task 2
therefore removes historical report emitters and synthetic counters without
weakening deterministic query assertions or browser behavior checks.

## Executable artifact disposition

### Delete the isolated canary cluster

The following eight files have no production consumer and are deleted rather
than generalized, relocated, or copied into a tracked archive:

- `tests/Feature/SettingsSp3cCanaryTest.php`
- `tests/Support/SettingsSp3cCanaryPage.php`
- `tests/Support/SettingsSp3cCanaryLibraryPage.php`
- `tests/Support/SettingsSp3cCanaryMeasurement.php`
- `tests/Support/SettingsSp3cDeepestFixture.php`
- `tests/Fixtures/settings-sp3c-canary/page.blade.php`
- `tests/Fixtures/settings-sp3c-canary/library.blade.php`
- `tests/Fixtures/settings-sp3c-canary/part-summary.blade.php`

The dedicated test owns ten synthetic tests. Its page duplicates the
production Builder, its in-memory library is query-free by construction, and
its parsed-response counters depend on framework markup. Production-facing
tests already own the durable security, persistence, Builder, escaping,
query-count, focus, and interaction contracts.

### Narrow mixed functional tests

- Retain `tests/Feature/SettingsSp3cTest.php`. Delete only the
  `SettingsSp3cCanaryMeasurement` import/use and the two
  `SP3C_PRODUCTION_REPORT` blocks. Keep the exact selected-column reference
  query and one-query assertions at both 9 and 49 rows, retired-flag disposal,
  ordinary stored-template save, authorization, validation, and security
  checks.
- Retain `tests/Feature/CardTemplateEditorPreviewTest.php`. Delete only the
  helper import, synthetic response measurement, `wire_models` metric
  assertion, and `STEP5B_CANARY_REPORT` block. Keep the exact one-preview-root
  assertion and every real Builder, validation, escaping, restricted-state,
  and preview contract.
- Retain `tests/Browser/CardTemplatePreviewBrowserTest.php`. Delete the two
  `STEP5B_O2_BROWSER_REPORT` blocks, the `STEP5B_BROWSER_REPORT` block, and only
  their report-exclusive accumulators/fields. Keep every assertion and bounded
  synchronization deadline.

Exactly five report switches and eight conditional blocks are retired:

| Switch | Blocks |
|---|---:|
| `SP3C_CANARY_REPORT` | 1 |
| `SP3C_PRODUCTION_REPORT` | 2 |
| `STEP5B_CANARY_REPORT` | 2 |
| `STEP5B_O2_BROWSER_REPORT` | 2 |
| `STEP5B_BROWSER_REPORT` | 1 |

In retained tests, a metric can be removed only when every consumer belongs to
one of those report blocks. No browser `expect()` or assertion may be removed.
No `performance.now()` comparison that bounds a wait may be removed or
shortened. The post-wait elapsed-duration field used only for reporting is not
a wait deadline and may be removed.

### Translation boundary

Delete only `admin.settings_sp3c.canary` from `lang/en/admin.php` and
`lang/he/admin.php`. Retain all sibling functional `admin.settings_sp3c.*`
translations and all Curator translations. The language diff receives a
separate review before verification.

## Selector migration

Four unused attributes are removed while their surrounding UI remains:

- `data-sp3c-template-library`
- `data-sp3c-restricted-shell`
- `data-sp3c-part-item-separator`
- `data-sp3c-part-summary-separator`

Six consumed hooks are renamed atomically:

| Historical selector | Durable selector |
|---|---|
| `data-sp3c-template-editor-page` | `data-card-template-editor-page` |
| `data-sp3c-template-editor` | `data-card-template-editor` |
| `data-sp3c-template-parts` | `data-card-template-editor-parts` |
| `data-sp3c-part-heading` | `data-card-template-part-heading` |
| `data-sp3c-part-position-badge` | `data-card-template-part-position-badge` |
| `data-sp3c-part-summary` | `data-card-template-part-summary` |

`data-sp3c-template-editor` is production-functional because validation focus
fallback queries it. The migration therefore uses two internal phases: first
add new hooks beside old declarations and move every consumer to the new names;
then prove the new contract and remove the old declarations. No compatibility
JavaScript or permanent dual selector remains in final code.

The audit's proposed `data-card-template-parts` replacement was corrected at
implementation preflight to `data-card-template-editor-parts` because public
rendered cards already use `data-card-template-parts` for their part list. The
more specific name prevents document-level Builder selectors from matching a
preview card and does not change the approved surface or behavior.

## Documentation disposition

Historical SP1, SP2, SP3A, SP3B, SP3C, Step 5B, and Mini-task 1 measurements,
commands, results, hashes, research bodies, prompts, and handoff bodies remain
historical evidence. Git history is the archive for deleted executable code;
no tracked dead-code archive is created.

Active-looking SP3A/SP3B/SP3C handoff banners and reports 07/08 receive a short
supersession notice without rewriting their bodies. Active current-state,
ledger, queue, feature-map, prompt-index, master-plan, and Public Front routing
entries are updated so they cannot reactivate the retired harness. Historical
mentions remain searchable and are not treated as executable instructions.

## Protection controls and stop conditions

1. Whole-file deletion is restricted to the eight explicit canary paths. No
   wildcard or broad SP3-named test deletion is allowed.
2. Consumed selectors move in two phases and all declarations, PHP/Blade
   selector strings, feature assertions, and browser consumers change
   together.
3. Retained browser assertions and `performance.now()` wait deadlines are a
   no-delete boundary. Any proposed removal returns to an amended audit.
4. Historical bodies are immutable; only status/routing notices may change.
5. No Curator/media production file, migration, dependency manifest, or lockfile
   may change. Shared translation edits are exact-subtree only.
6. `SettingsSp3cCanaryMeasurement` has no replacement abstraction. Future
   observability requires a fresh audit naming a real consumer, measurement
   plane, sampling strategy, output sink, retention policy, actionable budget,
   and package-versus-application ownership.

Implementation stops for a new Stage 1 audit if it needs to weaken another
functional assertion, shorten a browser deadline, touch Curator production
code, add a dependency, rewrite historical results, or expand materially beyond
the approved file surface.

## Verification boundary

Focused verification covers SP3A/SP3B/SP3C functional tests, Card Template
previewer/editor/Builder tests, the complete Card Template browser file
serially, and these Curator preservation files serially:

- `tests/Feature/ImageMediaCuratorTest.php`
- `tests/Feature/LegacyMediaTransitionTest.php`
- `tests/Feature/LegacyOwnerMediaRepairTest.php`
- `tests/Feature/MediaBackfillAndIntegrityReportTest.php`
- `tests/Feature/MediaRelationshipPerformanceTest.php`

The canonical final order remains requirements sweep, `vendor/bin/pint --test`,
`vendor/bin/filacheck`, `npm run build`, and the full `php artisan test` suite
last, serial, and uninterrupted. Any file change after a gate restarts at Pint.
