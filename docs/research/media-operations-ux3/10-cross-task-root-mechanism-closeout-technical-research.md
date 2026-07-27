# Media Operations UX3 Mini-task 3A Cross-task Root-mechanism Closeout Research

## Authority

- Laravel Simplifier Audit ID:
  `LS-20260726-PODTEXT-MEDIA-OPERATIONS-UX3-M3A-CROSS-TASK-INTEGRITY-03`
- Approved option:
  `MEDIA-OPS-UX3-M3A-RC-O2-CROSS-TASK-MECHANISM-CLOSEOUT`
- Approval date: 2026-07-26
- Active task contract:
  `prompts/pre-13-prompts/media-operations-ux3-program-reconciliation-finding-coverage-codex-prompt.md`
- Confirmed prompt version: `v1 — 2026-07-25`

This note is the required pre-code technical research for the approved O2
closeout. It does not authorize O3.

## Checkout and preserved baseline

Research used the saved checkout directly:

- working directory and Git root:
  `/Users/studioycm/Herd/PodText`;
- branch: `main`, 35 commits ahead of `origin/main`;
- HEAD:
  `576f5ada925d035baef75615f24d0fc9f8c7aa06`.

Tasks 1–6 are staged and frozen. Their 41 staged paths are expected inputs.
They must not be reset, restaged selectively, or rewritten outside this
approved closeout.

The following operator-owned forward-document changes remain outside this
implementation unless the final contract explicitly requires a bounded
closeout update:

- modified:
  - `docs/phase-02/current-project-state.md`;
  - `docs/phase-02/media-program-context.md`;
  - `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
  - `docs/research/media-program/02-media-program-master-plan.md`;
  - `docs/research/media-program/04-active-document-supersession-map.md`;
  - `prompts/README.md`;
- untracked:
  - the four `ux-design-thinking` skill entries;
  - `docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`;
  - the Mini-task 3A research and plan files `08` and `09`;
  - the active prompt.

No worktree, dependency, migration, live-database probe, production mutation,
push, Recheck/Retry control, generic repair control, 3B, 3C, Mini-task 4, or
Package 5 work is part of O2.

## Root-cause finding map

| Finding | Classification | Root cause | O2 treatment |
|---|---|---|---|
| Task 2 field-local memoization proof disappeared from the final test shape | test-proof gap, not a proven production regression | the field implementation caches the evaluated presentation, but no focused assertion proves one evaluation per field state | restore a focused behavioral proof without changing the already-correct production cache unless the test fails |
| Subject Settings pages copied Filament's complete mount/fill/save lifecycle | product mechanism flaw | custom lifecycle code drifted from the installed Filament `SettingsPage` contract and became responsible for transaction, validation, hooks, persistence, notification, and redirects | return to native hooks; retain only a thin authorization and write-lock wrapper |
| Disjoint concurrent Settings edits can overwrite each other | product data-loss flaw | Spatie Settings persists the whole reflected settings group; several writers read before any shared lock, then save a stale full group | one cross-process group write lock, acquired before every fresh read, with persistence inside the lock |
| Existing row locks do not fully serialize Settings writes | product concurrency flaw | settings properties may be absent, and different writers lock only selected existing rows | use Laravel atomic lock as the group mutex; keep transactions and row locks where they protect other domain rows |
| Settings pages compare one aggregate owner-image fingerprint | product consistency flaw | image-only stale detection cannot preserve unrelated unit edits or identify an exact collision | use `SettingsLifecycleSchema` units and a three-way opened/local/fresh comparison |
| Invalid changed nested units can be normalized away and saved | product data-loss flaw | the validator returns defaults plus findings, while current callers consume the normalized config without rejecting changed invalid units | reject any invalid unit changed by the operator; preserve untouched invalid stored units byte-for-value at the unit level |
| Valid Settings image replacement/clear can fail when the stored identity is broken | product bug | `normalizeMediaPair()` resolves the stored reference before examining the incoming choice | classify the incoming action first; a valid replacement or explicit clear must not depend on the old identity resolving |
| Broken Settings image state can appear absent | product truthfulness bug | the settings presenter omits `direct_state` from its fingerprint and collapses resolver failure to `null` | preserve configured evidence, project `broken`, and include direct state in the snapshot fingerprint |
| Settings pages do not use Filament's upload RPC schema restriction | framework-safety gap | custom Settings page inheritance omitted the installed parent concern | add the official Filament concern at the shared parent page |
| Proven cloned nested identities can collide | product form-integrity flaw | cloneable route-label/public-form structures copy stable keys while validation does not consistently enforce sibling uniqueness | clear or regenerate only identities proven to be stable keys and add native distinct/sibling-option protection |
| Settings query cost is asserted only on lower-level projections | measurement gap | no real Livewire Settings page proof includes hydration/render behavior | add a real page query measurement first; optimize production only if the test is RED |
| Original Tasks 7–8 are unfinished | approved-plan remainder | responsive CSS/localization proof and browser acceptance were never completed | resume only after the mechanism closeout is green |

The menu-item and item-info identity fields are not changed by O2. The audit
did not prove that those values require uniqueness. Adding speculative
constraints would be a material scope expansion.

## Package-first evidence

### Installed stack

- PHP 8.4
- Laravel 13.21.1
- Filament 5.7.3
- Livewire 4.3.3
- Pest 4.7.5
- Curator 5.1.2

Installed source is the final package-behavior authority.

### Filament 5 SettingsPage

The installed Spatie Settings plugin page uses the native lifecycle:

1. `beforeFill()`;
2. load Settings;
3. `mutateFormDataBeforeFill()`;
4. form fill;
5. `afterFill()`;
6. on Save, start the configured database transaction;
7. `beforeValidate()`;
8. schema `getState()`;
9. `afterValidate()`;
10. `mutateFormDataBeforeSave()`;
11. `beforeSave()`;
12. Settings fill and save;
13. `afterSave()`;
14. commit;
15. remember form data;
16. notify and redirect.

O2 must compose with this lifecycle instead of copying it.

Filament also ships
`RestrictsFileUploadsToSchemaComponents`. It rejects upload RPC state paths
that do not resolve to a visible upload-capable schema component. The shared
subject Settings page should use that official concern.

Native Repeater support supplies:

- `distinct()`;
- `disableOptionsWhenSelectedInSiblingRepeaterItems()`;
- clone-action customization.

These APIs should be used before inventing custom nested-form enforcement.

### Spatie Laravel Settings

Installed source shows:

- `SettingsMapper::save()` forwards all unlocked reflected properties;
- the database repository upserts the complete supplied property payload;
- no revision, compare-and-swap, group mutex, or conflict merge exists.

Therefore a focused subject page that fills only owned fields can still write
stale values for unowned properties held by the Settings object.

Laravel Boost did not return useful versioned Spatie Settings documentation
for this mechanism. Installed package source was used instead. This is a
tooling limitation, not evidence that the behavior is absent.

### Laravel atomic locks

Laravel provides `Cache::lock(...)->block(...)`. The application default cache
store is `database`; PHPUnit forces `array`. A cache lock therefore supplies
the required no-migration cross-process mutex in the deployed default and a
deterministic in-process seam in tests.

The group lock must be acquired before any fresh Settings read. A database
transaction begins inside the lock where the caller does not already own a
framework transaction.

### Curator

Curator owns Media selection/upload field behavior and physical Media records.
It does not own:

- PodText `media_attachments`;
- Settings units;
- Settings conflict comparison;
- owner commit boundaries.

The Settings fix must remain in PodText's page, lifecycle, resolver, and
writer seams. No custom Curator replacement or new Media authority is needed.

### FilamentExamples

Research began with broad searches:

- `curator`;
- `settings page`;
- `settings forms`;
- `file upload schema`;
- `repeater clone`.

Refined searches followed returned names and snippets. The available server
exposed search/snippet results, predominantly for Filament 4, but no
authoritative Filament 5 SettingsPage source/detail pattern. Those results
were treated as neighboring examples only; installed Filament 5 source is the
authority for O2.

## Production writer inventory

Every production path below can interleave with a subject Settings page and
must use the same group mutex.

| Writer | Current risk | O2 integration |
|---|---|---|
| `PublicContentSettingsSubjectPage` descendants | refresh and full save occur without a group mutex | thin native `save()` wrapper acquires the mutex before `parent::save()` |
| `CardTemplateFocusedWriter` | `freshSnapshot()` happens before persistence and can become stale | execute fresh read, target guard, merge, and save inside one coordinated transaction |
| `SettingsImportLocks::save()` | writes one property through a full Settings save | fresh read and save inside coordinator |
| `SettingsBackupManager::restore()` and `import()` | backup/current read and full apply can race with page writers | hold the group mutex around the existing transaction and all fresh reads |
| `NormalizePublicContentSettings --apply` | computes normalized output before write lock | recompute the apply candidate inside the coordinator after backup/confirmation preconditions |
| `BackfillSettingsMediaReferenceKeys --apply` | locks only selected existing settings rows | acquire the group mutex before the transaction and fresh manifest/settings read |
| `CuratorMediaAssetConverter::convert()` | reconciles selected settings rows inside a larger media transaction | acquire the group mutex before entering the conversion transaction |
| legacy Media registration/transition | settings snapshot is planned outside the commit transaction and selected rows alone do not serialize other writers | hold the same group mutex around plan revalidation and the existing commit transaction when settings references are involved |

Read-only package export, comparison, render, and diagnostic paths do not need
the write mutex.

## O2 architecture

### One coordinated writer

Add a focused `PublicContentSettingsWriteCoordinator`.

Responsibilities:

- expose one stable lock key for the `public_content` Settings group;
- acquire the Laravel atomic lock with bounded lease and wait times;
- run a closure only after the lock is owned;
- provide a transaction helper for writers that do not already use the native
  Filament transaction;
- never cache a Settings instance across the lock boundary;
- clear the container/app Settings instance before the fresh read where
  needed;
- release the lock for success, validation halt, or exception.

It does not:

- add a database revision;
- implement history or undo;
- decide subject ownership;
- normalize arbitrary values;
- change authorization;
- alter Media/file authority.

Nested coordinator acquisition is forbidden. Callers that already own the
group lock use an internal/private non-locking method rather than reacquiring
it.

### Native subject-page lifecycle

`PublicContentSettingsSubjectPage` should:

- use Filament's upload restriction concern;
- let the installed parent own mount, fill, validation, transactions,
  persistence, notifications, and redirects;
- keep `save()` only to enforce `canEdit()` and execute `parent::save()` while
  the group lock is held;
- capture the opened raw Settings unit hashes before native fill;
- capture the opened editable unit hashes after native form fill;
- merge in `mutateFormDataBeforeSave()` while the lock is held;
- refresh owner-image and unit baselines in `afterSave()`.

Locked Livewire properties should store hashes/existence metadata, not a
second full Settings payload.

### Unit-level three-way merge

Use the union of unit paths derived from opened raw, opened editable, local
candidate, and fresh raw payloads.

For each unit:

1. compare local editable value with the opened editable hash;
2. compare fresh raw value with the opened raw hash;
3. if local did not change, preserve fresh;
4. if local changed and fresh did not, apply the validated local unit;
5. if both changed to the same canonical value, accept it;
6. if both changed differently, reject the exact unit and write nothing.

Whole nested lists remain whole units under the existing
`SettingsLifecycleSchema`. O2 does not invent row-level revision semantics.

### Invalid changed-unit rule

Validation findings must be mapped back to lifecycle units.

- invalid + locally changed unit: show a field/form error and halt the full
  write;
- invalid + untouched stored unit: preserve the fresh raw value;
- valid + locally changed unit: take the validator-normalized local unit;
- locally unchanged unit: take the fresh raw value regardless of a legacy
  warning.

This prevents silent deletion/defaulting while allowing an operator to edit an
unrelated valid unit.

### Settings Media rule

For each path/reference pair:

1. if the reference field is omitted, preserve stored identity;
2. if the submitted reference is a valid Media identity, resolve it for the
   required purpose and store its canonical reference key/path;
3. if the submitted reference is explicitly empty, clear both values;
4. only inspect legacy stored identity when preserving an unchanged legacy
   value;
5. never require a broken old identity to resolve before replacement/clear.

The presenter must distinguish:

- present;
- absent;
- broken configured identity.

The direct state participates in the stale fingerprint.

## Proof strategy

Tests must distinguish product, test, fixture, and environment failures before
production changes.

Required focused proof:

- field-local owner presentation evaluates once per unchanged field state and
  invalidates after state change;
- coordinator lock is held before a writer's fresh read;
- a held group lock prevents another writer from entering;
- disjoint subject/card/import-lock writes preserve both changes;
- same-unit divergent edits halt with zero write;
- same-unit equal edits succeed;
- untouched invalid legacy unit survives an unrelated valid edit;
- changed invalid unit halts instead of being dropped/defaulted;
- valid replacement and clear succeed when stored Settings Media identity is
  broken;
- broken Settings image is visibly/fingerprint-wise distinct from absent;
- forged/hidden upload paths are rejected by the official parent concern;
- cloned public-form/route-label identities cannot silently collide;
- real Livewire Settings page query growth is bounded; production
  optimization is allowed only after a RED measurement;
- existing backup/import/normalization/backfill/conversion/legacy-transition
  behavior remains green.

After O2 mechanism proof is green, complete the original Task 7 localization
and scoped responsive CSS, then Task 8 browser/visual acceptance. Browser work
runs serially. Implementation screenshots and the current-versus-implemented
review PDF stay outside the repository.

## O3 boundary and catalog handoff

The separate visible task has been renamed:

`Root-Cause Mechanisms — Revisioning Catalog`

It now holds, under `root cause mechanisms - revisioned`:

- Audit:
  `LS-20260726-PODTEXT-MEDIA-OPERATIONS-UX3-M3A-CROSS-TASK-INTEGRITY-03`
- Option:
  `MEDIA-OPS-UX3-M3A-RC-O3-REVISIONED-SETTINGS-REBUILD`
- catalog title:
  `Revisioned settings persistence/editor architecture`

That task is read-only research and cataloging. O3 is not implementation
authority here. Any required revision column, migration, compare-and-swap
contract, durable edit history, or focused-editor rebuild returns to a new
explicit Stage 2 approval.
