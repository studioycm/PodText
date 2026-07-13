# SP2 Settings Performance Research

Date: 2026-07-13

## Prompt Scope

Run `prompts/pre-13-prompts/settings-performance-sp2-codex-prompt.md` as this
session's single step.

SP2 is evidence-gated. Job 1 must attribute the missing approximately 1,000 ms
inside `PublicContentSettings::form()` before the page split proceeds. If the
dominant cost does not scale with component count, the run stops after Job 2 with
an evidence report instead of shipping a large refactor.

## Preflight

- `git status --short --branch` reported `## main...origin/main [ahead 1]`.
- Recent commits include `b2deb95 docs: add backlog triage, settings performance sp2 and fetcher prompts for phase 2` and SP1 commit `c6c9587 perf: instrument settings page and fix maintenance marker copy`.
- Working tree was clean before implementation.
- `docs/phase-02/back-log-triage-2026-07-13.md` is already tracked. The prompt
  references `backlog-triage-2026-07-13.md` without the second hyphen, but the
  attached and tracked file is `back-log-triage-2026-07-13.md`.

## Installed Versions

Laravel Boost `application_info` was available and reported:

- PHP 8.4
- Laravel 13.19.0
- Filament 5.6.7
- Livewire 4.3.3
- Horizon 5.47.2
- Pest 4.7.4
- Tailwind CSS 4.3.2

## Boost Research

Boost `search_docs` was used before code changes for:

- Filament 5 custom pages and SettingsPage save/mutate hooks.
- Filament 5 repeater cloning.
- Laravel 13 Artisan command options and command testing.
- Pest 4 command/test execution behavior.

Useful results:

- Filament `SettingsPage` save flow remains `form->getState()` ->
  `mutateFormDataBeforeSave()` -> settings `fill()`/`save()` with transaction
  hooks.
- Filament Repeater supports native `cloneable()`, but the existing
  `SettingsItemCloner` remains useful where cloned keys/names need deterministic
  suffixes and uniqueness.

## FilamentExamples Research

FilamentExamples MCP was available with `search_examples` only. No separate
source/read/detail tool was exposed, so this research has search/snippet access
only.

Initial topic batch:

- `Filament settings page tabs form`
- `Filament settings page Spatie settings`
- `Filament page form save mounted action`
- `Filament clone repeater item action`

Refined topic batch:

- `Filament v4 settings page form save statePath`
- `Filament v4 custom page settings save notification`
- `Filament v4 repeater clone item action`
- `Filament v4 schema component actions copy clone`
- `Filament v4 page redirect route old slug`

Relevant examples and adaptations:

| Example | Path/class | Useful pattern | Avoided pattern | PodText adaptation |
|---|---|---|---|---|
| Custom Edit Profile Page With Two Forms | `v4/forms/edit-profile-custom-forms/app/Filament/Pages/EditProfile.php` | Page-owned schemas, explicit `statePath`, explicit form submit actions. | User-profile model-specific update flow. | Domain pages can keep page-owned form/schema/save boundaries and use shared settings persistence. |
| Account Settings Cluster Pages | `v4/full-projects/clusters-with-profile-settings/app/Filament/Clusters/AccountSettings/Pages/ProfileSettings.php` | Multiple small settings-like pages under a common navigation family. | Cluster structure is unnecessary for this app's existing nav. | Split pages should stay under the existing site-management navigation group through `AdminNavigationOrder`. |
| ManageSettings custom page | `v4/forms/wizard-invoice-form/app/Filament/Pages/ManageSettings.php` | Simple settings page with form footer and per-key persistence. | Custom ad hoc setting storage. | PodText keeps Spatie `PublicContentSettings` as the single storage class. |
| MyHotel custom page | `v4/full-projects/hotel-management-bookings/app/Filament/Hotel/Pages/MyHotel.php` | Page-level form save with notification and `getState()`. | Eloquent `updateOrCreate()` persistence. | Supports using focused page classes while preserving existing settings events. |

## Source Findings

- `App\Filament\Pages\PublicContentSettings` is the monolith. Its `form()`
  builds nine top-level tabs: homepage, display, item page, menu/header,
  podcasts, contributors, about, maintenance, and advanced.
- Existing dedicated pages already own two groups:
  - `public_forms` -> `ManagePublicForms`
  - `import_locks` -> `ManageSettingsImportLocks`
- The validator owns fourteen top-level groups:
  `card_templates`, `menu_config`, `about_page`, `public_forms`,
  `route_labels`, `display_defaults`, `default_images`,
  `transcription_policy`, `item_page`, `podcasts_page`,
  `contributors_page`, `settings_backups`, `import_locks`, and `maintenance`.
- SP1 already times per-tab schema callbacks and per-section wrappers. Those
  sums are far smaller than `form.total_build`, so the missing cost is likely
  after arrays are produced, especially root component tree assembly,
  `$schema->components($components)`, and recursive inline import-lock hint
  attachment.
- `cardTemplateOptions()` currently normalizes `card_templates` through the full
  validator every time option closures are evaluated. That is a likely source of
  repeated validator work during form hydration and Livewire updates.
- `applyInlineImportLockHints()` recursively traverses the whole tree and calls
  `SettingsLifecycleSchema::unitPathsForSemanticPath()` per field. Without a
  page split, that means all monolith fields are traversed on every form build.

## Job 1 Attribution Table

Temporary probes bracketed root component construction, root `Tabs`
configuration, `$schema->components($components)`, and inline import-lock hint
attachment. The scaffolding probes were removed after attribution; the
permanent low-cost probe kept in code is `form.inline_import_lock_hints`.

### Before Import-Lock Hint Memoization

Run id: `sp2-attribution-20260713022520`

Payload: 37,292 bytes from Yoni's local legacy/custom stored settings.

| Phase | Cold | Warm 1 | Warm 2 | Finding |
|---|---:|---:|---:|---|
| `form.total_build` | 1286.839 ms | 1319.253 ms | 1321.055 ms | Reproduced the SP1-sized missing cost on the large local payload. |
| `form.inline_import_lock_hints` | 1248.850 ms | 1263.535 ms | 1261.816 ms | Dominant cost; almost all of `form.total_build`. |
| `form.components_array` / `form.tabs_component` | ~37.8 ms | ~55.5 ms | ~59.0 ms | Normal component construction was small compared with hint traversal. |
| `form.schema_components_assign` | ~0.001 ms | ~0.001 ms | ~0.001 ms | Assigning components to the schema was not the missing cost. |

Attribution: `applyInlineImportLockHints()` traversed all fields and called
`SettingsLifecycleSchema::unitPathsForSemanticPath()` repeatedly for the same
semantic paths. The cost scaled with the monolith field tree, but the immediate
bug was repeated lifecycle-schema lookup work inside the traversal, not Filament
component construction itself.

### After Memoizing Per Semantic Path

First optimized run id: `sp2-attribution-deduped-20260713022634`.

Final optimized run id after removing temporary probes:
`sp2-final-20260713023719`.

Payload: 37,293 bytes.

| Phase | Cold | Warm 1 | Warm 2 | Finding |
|---|---:|---:|---:|---|
| `form.total_build` | 71.169 ms | 81.550 ms | 82.792 ms | The optimized monolith is already below the prompt's canary target. |
| `form.inline_import_lock_hints` | 33.338 ms | 22.938 ms | 23.152 ms | The former 1.25s traversal is now tens of milliseconds. |
| `settings.read_hydrate` | 17.543 ms | 4.214 ms | 4.325 ms | Settings read is not a split driver. |
| `payload.load` | 0 ms | 0 ms | 0 ms | Payload bytes logged as 37,293 for each mount. |

Top final phases by total across the three-mount run:

| Rank | Phase | Total | Max | Count |
|---:|---|---:|---:|---:|
| 1 | `form.total_build` | 235.511 ms | 82.792 ms | 3 |
| 2 | `form.inline_import_lock_hints` | 79.428 ms | 33.338 ms | 3 |
| 3 | `schema.tab.advanced` | 62.678 ms | 51.069 ms | 3 |
| 4 | `schema.tab.maintenance` | 50.343 ms | 45.066 ms | 3 |
| 5 | `settings.read_hydrate` | 26.082 ms | 17.543 ms | 3 |

The temporary component-tree probes were intentionally removed. The permanent
change is the memoized inline import-lock unit-path lookup plus the
`form.inline_import_lock_hints` profiler phase.

## Decision Gate

The split does not proceed in this run.

The original 1.25s cost was real, but the attribution gate showed a targeted
memoization fix removes it without a domain-page split. The final optimized
monolith measures roughly 71-83 ms `form.total_build` on the 37 KB local
payload, far below the prompt's expected canary target. Shipping a large page
split after that result would add risk without performance evidence.

Per the prompt's stop rule, SP2 stops after Job 2 and records this as an
evidence report plus reusable foundations.
