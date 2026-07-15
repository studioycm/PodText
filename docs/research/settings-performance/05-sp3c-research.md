# Settings SP3C research

Date: 2026-07-15

Prompt contract: `prompts/pre-13-prompts/settings-sp3c-codex-prompt.md`, prompt
version `v3 — 2026-07-15`.

This note records the pre-implementation evidence for SP3C. The v3 prompt is
the sole execution contract. `06-sp3c-prompt-review.md` is historical audit
evidence only; v3 governs every difference.

## Preflight result

- `git status --short --branch` reported a clean `main` branch, one commit
  ahead of `origin/main`.
- `git log --oneline -12` showed the required SP3B chain and the named
  follow-ups: `9d8296f`, `23a6ce9`, `2ea189f`, `d128cfd`, `0394ab5`,
  `41cf3c5`, the v1 prompt, v1 review, and v3 prompt commits.
- Each named prerequisite is an ancestor of `HEAD`.
- SP3C application code, its research/plan, its handoff, and its ledger row did
  not exist before this session.
- No kickoff correction was supplied.

## Sources read

- `AGENTS.md`, in full.
- `docs/phase-02/ai-development-lessons.md`, in full.
- `docs/phase-02/current-project-state.md`, the current ledger head, and the
  two newest distinct handoffs selected through git history.
- SP3A research, implementation plan, and handoff.
- SP3B research, implementation plan, and handoff.
- The v3 prompt, in full, and `06-sp3c-prompt-review.md`, in full.
- Relevant evergreen settings, public-panel, tooling, Laravel, Pest, Livewire,
  Tailwind, and Spatie/PHP guidelines.
- Installed Filament Blueprint planning guidance for custom pages, forms,
  tables, actions, authorization, testing, and its completion checklist.
- Current settings pages, settings ownership, validator, registry, card
  resolver, lifecycle schema, import locks, measurement fixture/middleware,
  profiler, homepage-section model/configuration, and affected tests.
- Installed Laravel, Filament, Livewire, Spatie Settings, and Pest source where
  page routes, custom tables, Builder previews, locked state, unsaved-change
  alerts, settings persistence, and test behavior matter.

## Installed-version facts

Laravel Boost reported the installed runtime as PHP 8.4, Laravel 13.19.0,
Filament 5.6.7, Livewire 4.3.3, Spatie Laravel Settings 3.9, Pest 4.7.4,
Pint 1.29.3, and Tailwind CSS 4.3.2. MySQL is the application database; tests
use the repository's isolated test database configuration.

The schema inspection confirms that settings are stored in the existing
Spatie `settings` table and that `homepage_sections` already provides the
`id`, `name`, `type`, `source_config`, and `display_config` data needed by the
scanner. SP3C needs no migration.

## Existing architecture and invariants

### SP3A

- The frozen SP3A measurement fixture remains about 37 KB and must not be
  changed or repurposed as the deepest SP3C fixture.
- The lifecycle output SHA remains
  `61e551a422280b06ea6a2a66f235da10d1e349c787780f1709369e53c888addc`.
- `SettingsLifecycleSchema` memoizes derivation per request, keyed by group and
  canonical payload. SP3C must consume that shared lifecycle and must not add a
  second derivation path.
- `SettingsSaved` has one synchronous listener chain. SP3C must keep one
  Spatie `save()` for each successful mutation and leave the listener/cache/
  backup flow intact.
- The five middleware response headers describe the initial GET only.

### SP3B

- The ownership registry classifies all 37 `PublicContentSettings` roots
  exactly once and assigns the entire `card_templates` root to the current
  Card Templates page. SP3C must not change that ownership.
- `PublicContentSettingsSubjectPage` establishes the fresh-snapshot focused
  owner-root save pattern and transaction/save/profiler hooks. The temporary
  Card Templates page is the one exception that SP3C replaces with a read-only
  library and one-template editors.
- The proven guarantee is sequential preservation of disjoint stale owner
  changes. It is not simultaneous-request serialization.
- Existing import, restore, normalization, lock, backup, Admin UX, and
  lifecycle routes remain shared and unchanged.

### Current card-template form and validator

- The temporary `cardTemplatesTab()` mounts a Repeater containing every
  template and a full nested Builder for every part. Collapsing the Repeater or
  Builder keeps all field wrappers and controls in the DOM, so it does not meet
  SP3C.
- The canonical stored template keys are `key`, `family`, `label`, `layout`,
  `density`, `image_size`, `title_size`, and `parts`.
- The validator accepts legacy aliases while normalizing, sorts parts by
  `order`, permits one nested `part_group` level, rejects unknown keys, and
  returns invalid-config entries rather than throwing for ordinary bad rows.
- The focused writer must run the installed validator against one candidate
  row only, require one normalized row and zero invalid entries, and never
  normalize or rebuild siblings.
- Existing public resolution overlays configured templates on code defaults.
  A configured row matching a default identity is therefore an override; a
  missing default is virtual and must not be written until Create override.

## Installed Filament and Livewire guidance

Laravel Boost's version-aware documentation established:

- `Table::records()` supports keyed array records. A table action receives the
  record as an array, so route-only actions can use projection-only data.
- `Table::paginated(false)` is the supported unpaginated custom-data form.
- A custom action with `->url(...)` redirects without a mutation modal.
- Builder `blockPreviews()` replaces the block's ordinary schema rendering
  with its preview. By default the preview click opens the block's Edit modal.
  Interactive previews are optional and are not needed here.
- A Blade view supplied to a Builder Block preview is the supported mechanism.
- Livewire public properties are browser-visible request input. `#[Locked]`
  prevents client updates, but does not make the value secret and never
  replaces current-state authorization or target re-resolution.
- Filament's unsaved-data trait stores a checksum and relies on
  `rememberData()` after a successful save. Custom pages must opt into and test
  this behavior; there is no durable remount recovery.
- Custom page slugs are Laravel route paths. Route parameters must be validated
  in `mount()` and again at every action/mutation boundary.

## FilamentExamples research

The configured server exposed `search_examples` only; no source/read/fetch/
details tool was available. Research therefore used multiple short batches and
a refined pass, with limits of 10 and 3.

| Example or returned pattern | Useful pattern | Pattern not copied | PodText adaptation |
|---|---|---|---|
| Doctor Availability / `ManageDoctorSchedule` | Custom Page + table; `paginated(false)`; hidden page; header and record actions | Database mutations inside table actions; hard-coded labels; string icons | Library actions are URL-only, translated, enum-icon actions over safe array projections. |
| Hotel Booking / `FindHotel` | `records()` with keyed arrays and array-record callbacks | Querying relationships inside projection loops | Project once from one settings snapshot and one homepage-section query; no row callbacks read settings or query. |
| Editable Box Score / `ManagePlayerStats` | Custom-data array records are accepted by Filament tables | Inline editable columns | SP3C library contains no writable column or modal. |
| Hidden custom resource/page examples | Explicit route parameters and hidden navigation | Relying on model binding for arbitrary string identities | Generate URLs through Filament APIs; validate exact family/key grammar on mount and every action. |
| Builder preview searches | No sufficiently relevant source example was returned | No claim of deep source research | Installed Filament source/docs, not snippets, governs the preview canary. |

## Candidate editor mechanism

The first candidate is Filament Builder block previews on an isolated,
test-owned canary surface:

1. The parent draft contains one template only.
2. Ordinary unselected part state may remain serialized for reorder/save.
3. Preview Blade renders escaped summaries and action chrome but no form field
   wrappers or editor controls.
4. Clicking one preview opens the installed Builder Edit action modal, which
   mounts only the selected part's schema.
5. A nested `part_group` uses the same preview mechanism for its child Builder,
   so selecting the group and one child should expose one group editor and one
   selected child editor rather than all descendants.

The fallback candidate, only if previews fail the canary, is an app-owned
summary list plus a selected-part component that mutates the parent draft. It
must not own a separate lifecycle or save settings.

No candidate may claim complete backend schema deferral: Filament can still
construct Block schemas in PHP. The acceptable claim is measured reduction of
rendered field wrappers and controls.

## Canary measurement definitions

- One DOM parser will count all HTML elements; regex will not count elements.
- The exact installed wrapper selector is `[data-field-wrapper]`. Filament
  5.6.7 renders that attribute on `forms::components.field-wrapper`; the older
  `.fi-fo-field-wrp` class is not the installed wrapper contract.
- Editor controls are counted only inside those wrappers: native `input`,
  `select`, and `textarea`; elements with `contenteditable`; and installed
  custom-field controls with IDs or `wire:model` bindings.
- Summary/action chrome is counted separately.
- Serialized state bytes use UTF-8 JSON with
  `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
- Initial/selected response bytes are recorded only where the test surface is
  stable enough to compare.
- At least three identical samples must establish the maximum stable count.
- The same fixture must render the current full Builder as the control and the
  preview/fallback candidate as the comparison.
- Passing requires at least 70% fewer wrappers and controls than the control.
  Query and settings reads must remain bounded as template and
  `HomepageSection` counts grow.
- Production budgets will be frozen as measured passing maxima plus 20% for the
  library, unselected editor, and selected editor surfaces. They are pending the
  isolated canary and will be appended to the implementation plan before any
  production adoption.

## Security and preservation conclusions

- Capability is current super-admin authorization plus current Admin UX
  `transcription_mode=multi`. It is recalculated on mount, actions, and save.
- Protected-template handling is a server-side projection/write policy, not a
  CSS hiding policy. Non-capable state contains shell fields only; protected
  parts and their sentinels must be absent from HTML, summaries, modals, and
  serialized state.
- If capability is lost, future responses are sanitized to the read-safe shell.
  Already delivered browser data cannot be revoked.
- The writer binds an edit to the immutable original identity and fresh raw-row
  fingerprint. A proposed rename never becomes the authorization identity.
- Fresh protected parts are restored server-side for a permitted shell-only
  edit. Forged protected additions or changes are refused.
- Reference checks are a fresh save-time scan. They prevent sequentially stale
  renames/deletes but do not serialize simultaneous requests and do not remove
  the normal scan-to-save TOCTOU window.
- Sibling preservation is strict decoded equality and deterministic canonical
  per-row JSON equality. SP3C does not promise literal settings-table payload
  bytes, whitespace, or JSON key order.
- No lock, persistent cache, queue, transaction-serialization claim, migration,
  dependency, lifecycle group, import path, or backup flow belongs in SP3C.

## Documentation reconciliation

The ledger already records CURATOR-HF1 and OTP-POLICY1, so those rows must
remain untouched. The two missing in-between history rows are:

- `9d8296f feat: normalize logo svg coordinates and add theme-adaptive favicon`;
- `d128cfd feat: make admin navigation groups collapsible`.

SP3C will add only those historical rows plus its own row. `AGENTS.md` lacks the
operator-approved durable commit-message rule and will receive allowed
prefixes, imperative mood, and the canonical hash-backfill format, with no
hook.

## Isolated canary verdict

The preview candidate passed and is selected for production adoption. The
separate test-owned fixture contains all 14 registered part types twice,
top-level and maximum-depth nested groups, protected sentinels at both depths,
hostile summary values, 100-template scale data, and 100 projected section
fixtures. It does not alter or reuse the SP3A fixture.

The final isolated suite passed 9 tests and 79 assertions. It exercised escaped
summaries; one top editor; group plus one nested child editor; native Builder
edit/confirm, cancel, validation, clone, delete, reorder, close/reopen; repeated-
type isolation; parent-draft survival through simulated page validation/stale/
collision errors; non-capable protected state absence; and zero-query constant
test projection at 10 versus 100 rows.

The Livewire test transport returns Filament action modals as partial/teleported
updates rather than merging their modal DOM into `Testable::html()`. Native
Builder edit action state and mutations were therefore tested directly, while
the selected DOM count used an isolated inline rendering of the same selected
field set bound to the exact parent-draft state path. Real modal DOM/heap/
listener and Back-button behavior remains an explicit browser acceptance step;
no browser result is fabricated.

Three repeated samples were byte/count identical:

| Surface | Elements | Wrappers | Controls | IDs | `wire:model` | Summary/action chrome | HTML bytes | State bytes |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Full Builder control | 8,756 | 396 | 146 | 146 | 146 | 0 / 0 | 3,690,183 | 18,118 |
| Preview, unselected | 3,510 | 8 | 2 | 2 | 2 | 29 / 29 | 1,257,416 | 18,118 |
| Preview, one top selected | 3,617 | 16 | 6 | 6 | 6 | 29 / 29 | 1,301,734 | 18,118 |
| Preview, group + one nested selected | 3,724 | 24 | 10 | 10 | 10 | 29 / 29 | 1,347,225 | 18,118 |
| Production-shaped Filament custom-data table library, 30 safe rows | 1,051 | 1 | 1 | 1 | 1 | 0 / 0 | 479,927 | 11,644 |

Wrapper/control reductions were 97.98%/98.63% unselected,
95.96%/95.89% with one selected top-level part, and 93.94%/93.15% with a
selected group plus one nested child. The required 70% threshold passed in
every candidate state. Ordinary parent state bytes remain 18,118 by design so
reorder/save can operate; no backend schema-deferral claim is made.

The library measurement uses the representative production mechanism: a
Filament custom-data table with seven columns, boolean icon state, header/record
URL action chrome, custom-data search/default-override filtering, and pagination
disabled. Search/filter were enabled only after the required FilaCheck gate
proved the need, and the representative canary was rerun before final adoption.
Its frozen 30-row cap is 1,262 elements, 2 wrappers/controls/IDs/`wire:model`
paths, 575,913 HTML bytes, and 13,973 serialized state bytes (each measured
maximum plus 20%). The editor caps are 21,742 state bytes; all other frozen
editor caps are recorded in the implementation plan.

The test-only surfaces issued zero settings reads, reference queries, lifecycle
derivations, or database queries at both tested scales. Production is still
bound to one fresh settings snapshot, one projected reference query, and one
shared request lifecycle derivation; production tests must prove those fixed
counts rather than borrowing the isolated zeros.
