# Codex Prompt — SP3C v3: Template Library + One-Template Editor (execution)

Prompt version: v3 — 2026-07-15. Supersedes v1 and v2, which were review
rounds only and were never executed. Standing rule: stop and ask if the kickoff
names any other version. Stage 3 of SP3A→SP3B→SP3C→SP3D.

This v3 is the complete and sole execution contract. Read
`docs/research/settings-performance/06-sp3c-prompt-review.md` as audit history,
but v3 incorporates and, where necessary, corrects it. If the review and v3
conflict, v3 governs. In particular, do not manually URL-decode Laravel route
parameters, do not claim literal database-payload byte preservation, and do not
force deletion through the edit candidate algorithm.

Work in the current local clone of `studioycm/PodText`. Execute only SP3C. The
kickoff must enumerate corrections or say `none`; do not accept open-ended scope.

## Outcome

Replace the temporary all-template Repeater with:

1. a read-only TEMPLATE LIBRARY at the existing Card Templates URL; and
2. hidden CREATE and EDIT pages mounting exactly one template draft.

The library never persists. Every create, edit, allowed rename, clone-save, and
guarded delete goes through one focused writer, one fresh canonical
`PublicContentSettings` snapshot, and exactly one existing Spatie settings save.
Unselected templates never enter editor state. Within the selected template,
unselected ordinary parts may remain in the parent draft but render zero editor
form controls and zero Filament field-wrapper DOM. A collapsed full Builder is
never compliant.

## Fixed definitions and product decisions

- Template identity is `family:key`.
- Allowed families are the three installed registry families.
- Semantic keys are ASCII and must match `^[a-z][a-z0-9_-]*$`, maximum 80
  characters. Labels remain administrator-authored content, maximum 120
  characters.
- The writer accepts only canonical template keys `key`, `family`, `label`,
  `layout`, `density`, `image_size`, `title_size`, and `parts`. Installed legacy
  aliases may be read by the existing validator, but editor persistence emits
  only the canonical keys. Reject arbitrary top-level draft keys and every
  foreign settings root.
- A **capable** actor is a currently authorized super-admin while the current
  `AdminUxSettings` transcription mode is `multi`. Re-evaluate both facts from
  current server state for every action and save; mount-time capability is not
  authoritative.
- A **protected template** is any raw template containing a protected part at
  any nesting depth under the installed `MultiTranscriptionSurfaces` policy.
- For a non-capable actor, a protected template exposes editable shell fields
  only. Its complete parts state is absent from Livewire/HTML, replaced by a
  generic translated restricted status, and restored from the fresh stored
  target before save. Clone and delete are hidden and hard-refused for that
  actor. Allowed shell-only rename still uses the original identity and fresh
  protected-state restoration.
- Registry defaults are virtual only while no configured row has the same
  identity. Exactly one library row represents each default identity: either a
  virtual default with **Create override**, or a configured **default override**.
  Default identities cannot be renamed or deleted in SP3C.
- Clone is unsaved. It opens create state and causes no event, backup attempt,
  cache invalidation, or settings write until the page-level Save action.
- Rename and delete are blocked while explicit references exist. The operator
  repoints consumers first; SP3C never rewrites consumers.
- Template-level reordering from the temporary Repeater is intentionally
  deferred. Part and nested-part ordering remain supported. Do not add a hidden
  template-order writer or claim full parity with the old Repeater.
- Stale detection is optimistic and sequential. No claim or test may assert
  simultaneous-request serialization, atomic compare-and-swap, or concurrent
  referential integrity.

## Scope guard

Do not add or change:

- migrations, tables, settings groups, or stored settings properties;
- template `enabled`, generic `type`, timestamps, aliases, tombstones, or
  per-template revision metadata;
- import/export formats or the existing import, restore, normalize, import-lock,
  backup, cache, Admin UX, Curator, OTP, logo/favicon, or public-form writers;
- lifecycle unit definitions or serialization; frozen SHA-256
  `61e551a60016b1ac0c9aa8051463818adf31677bea465ac0e9b269fe3d2386b8`
  must pass untouched;
- `SettingsSp3aMeasurementFixture`; it remains byte-identical;
- persistent where-used caches, cache tags, `Cache::add()` coordination,
  database/cache/advisory/application locks, or `lockForUpdate()`;
- panel-wide `databaseTransactions()` or any new atomicity claim. Preserve the
  existing Filament transaction hooks and their currently configured behavior;
- live/public preview, islands, a second settings-save boundary, monolith
  deletion, Composer packages, or npm packages.

The existing synchronous `SettingsSaved` listener remains unchanged. One
successful writer save means one `SettingsSaved` event, one
`SettingsBackupManager::createSystem()` attempt, and one existing invalidation/
render-context-reset sequence. It does **not** guarantee a new backup row because
system backups deliberately deduplicate payload hashes.

## Standing workflow

- Read `docs/phase-02/ai-development-lessons.md` in full during preflight.
- Write research and a self-contained PROVISIONAL implementation plan before
  application code. Do not make the plan depend on readers reconstructing v1 or
  v2 from the review.
- Amend the plan with the measured canary verdict and frozen budgets before
  adopting a production editor mechanism.
- Tests own fixtures. Every HTTP-touching test uses
  `Http::preventStrayRequests()` and committed fixtures; mail uses `Mail::fake()`.
- Add both `he` and `en` translations for every new UI label, action, hint,
  status, empty state, corruption message, validation message, and conflict.
  Stored administrator labels remain escaped content, not translation keys.
  Virtual registry rows use translated display copy; creating an override may
  prefill editable content from the registry but must not persist until Save.
- Do not use `vendor/bin/filacheck --fix`. Do not push.
- Commit messages use `feat|fix|perf|docs|refactor|test|chore` and imperative
  mood. The successful canonical ending is the implementation commit followed
  immediately by the docs-only hash backfill commit.

Final gate order on the final file state:

1. requirements sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` LAST.

After any file change following a gate, re-enter at Pint. Record every run,
including failures and reruns, in the handoff. Never interrupt or parallelize
the full suite.

## Preflight and green baseline

Run first:

```bash
git status --short --branch
git log --oneline -12
```

Require a completely clean tree. Confirm SP3C has not started and the history
contains the SP3B chain plus `9d8296f`, `23a6ce9`, `2ea189f`, `d128cfd`,
`0394ab5`, `41cf3c5`, the v1 prompt commit, the v1 review commit, and the final v3
prompt commit. Stop on any dirt or history mismatch.

Read the full session-start documents from `AGENTS.md`, the SP3A/SP3B research,
plans and handoffs, this prompt, the v1 review, current settings/lifecycle code,
the affected tests, installed Filament/Livewire/Laravel/Spatie source, relevant
evergreen guidelines, Laravel Boost installed-version guidance, Filament
Blueprint planning guidance, and the FilamentExamples research protocol.

Write the research note and provisional plan first, then run this exact targeted
baseline before any application code:

```bash
php artisan test --compact \
  tests/Feature/SettingsSp3aTest.php \
  tests/Feature/SettingsSp3bTest.php \
  tests/Feature/SettingsPageProfilerTest.php \
  tests/Feature/SettingsBackupsTest.php \
  tests/Feature/SettingsBackupSnapshotsTest.php \
  tests/Feature/SettingsImportExportTest.php \
  tests/Feature/PublicContentSettingsNormalizeCommandTest.php \
  tests/Feature/PublicFrontJsonSettingsArchitectureTest.php \
  tests/Feature/PublicDefaultImagesSettingsTest.php \
  tests/Feature/TaxonomyTagsPinningSettingsTest.php \
  tests/Feature/ImageMediaCuratorTest.php \
  tests/Feature/AdminPhase02ResourcesTest.php \
  tests/Feature/RolesGatesTest.php \
  tests/Feature/SingleTranscriptionLensTest.php \
  tests/Feature/PublicFrontCardTemplateBuilderTest.php \
  tests/Feature/PublicFrontIconRegistryTest.php
```

Stop before application code if the baseline is red. A sandbox/runner failure
may be rerun only after identifying and recording that it is infrastructure, not
an application assertion failure.

## Job 0 — carried documentation rule

Inspect state and the ledger before editing them. Curator and OTP are already
recorded; reconcile only genuinely missing compact logo/favicon or collapsible-
navigation status and never duplicate rows.

Add the operator-approved durable commit-message section to `AGENTS.md`: allowed
prefixes, imperative mood, and canonical `docs: backfill … hash` format. Rule
only; no hook.

## Job 1 — research, provisional plan, and isolated canary

Create before application code:

- `docs/research/settings-performance/05-sp3c-research.md`
- `docs/research/settings-performance/05-sp3c-implementation-plan.md`

The provisional plan must copy the operative writer, authorization, reference,
route, measurement, failure, test, and gate contracts from this v3. It may cite
the v1 review as evidence but must not delegate requirements to it.

### Canary isolation and fixture

Add a new test-owned SP3C deepest fixture. Do not edit or reuse the frozen SP3A
fixture as the deepest canary. The fixture must include:

- every applicable block type;
- repeated instances with distinct ordinary sentinel values;
- top-level and nested `part_group` structures at the maximum supported depth;
- protected parts at both depths with distinct protected sentinels;
- hostile strings in every summary-bearing property;
- enough templates and sections for scale/query tests.

Build only an isolated test/canary surface before production adoption. Any
preview Blade must use escaped output and app-owned translations/formatters—no
raw HTML, renderer side effects, settings reads, or queries from views.

Exercise:

1. every top-level block summary;
2. one selected top-level part with all other ordinary parts rendering summary
   chrome but zero editor controls/wrappers;
3. a selected `part_group`, nested summaries, and exactly one selected nested
   child editor;
4. edit/confirm, modal-local cancel, validation failure, clone part, delete part,
   reorder, nested reorder, close/reopen, and repeated-type state isolation;
5. parent-draft survival across part-editor transitions and page-level
   validation/stale/collision failures;
6. hostile-string escaping;
7. authorization non-exposure and forged top-level/nested refusal;
8. capability loss between mount and the next action/update.

Do not require impossible durable draft recovery after a full browser remount.
Browser back must return to the correct route and trigger the existing or newly
integrated unsaved-changes warning before abandoning a dirty draft. Modal cancel
discards only modal-local edits; confirmed modal edits mutate only the parent's
unsaved draft.

### Honest sentinel assertions

- An ordinary unselected sentinel may remain in the parent Livewire snapshot so
  reorder/save works. Assert that it has no selected editor marker, field
  wrapper, control ID, or `wire:model` editor path.
- A selected ordinary sentinel appears in exactly one editor surface.
- For a non-capable actor, every protected sentinel/token/label/value is absent
  from initial HTML, summaries, modal HTML/state, and serialized Livewire state.
  The only visible replacement is generic translated restricted copy.

### Canary metrics and verdict

Render the current full Builder against the same SP3C fixture as the same-surface
control. For previews and, if needed, the summary-plus-selected-part fallback,
measure:

- total elements;
- Filament field wrappers;
- rendered form controls;
- control IDs and `wire:model` paths;
- serialized component-state bytes;
- initial and selected response bytes where deterministically available;
- total queries, settings reads, reference-scan queries, lifecycle derivations,
  duplicate lifecycle loads, and scanner rows/time.

Define one test measurement helper after inspecting installed Filament markup and
reuse it for control/candidates/final pages. Count HTML elements with one DOM
parser, not regex; document the exact Filament field-wrapper selector; count
editor controls/IDs/`wire:model` only inside those wrappers, including native
inputs/selects/textareas and contenteditable/custom field controls identified by
installed markup. Count summary/action chrome separately so it cannot masquerade
as an editor-control reduction. Define serialized-state byte encoding once.
Record inclusion/exclusion rules and empty/corrupt/error-state behavior in the
plan. Keep measurement ordering deterministic and do not parallelize samples.

Run deterministic counts repeatedly. For dynamic byte metrics, record at least
three samples and use the maximum stable sample, not a single lucky response.
The chosen mechanism must reduce editor field wrappers and rendered controls by
at least 70% versus the control. Freeze separate library, unselected-editor, and
selected-part caps in the amended plan using the measured stable maximum plus
20% headroom. Provisional `< 2,000` and `< 4,500` figures are stop/go context,
not inherited final budgets. Prove query counts remain constant as template and
HomepageSection counts grow.

Filament may still construct schemas in PHP for preview items. Claim only the
measured control/wrapper reduction, never complete backend schema deferral.

Automated component canary results decide previews versus fallback. Browser
navigation and real DOM/heap/listener sampling remain operator acceptance. If a
usable browser runner is unavailable, record that honestly and leave those
operator steps pending; do not fabricate results or block an otherwise proven
component mechanism solely because the previously broken in-app browser cannot
start.

Amend the plan with the verdict before production adoption. If neither mechanism
passes, remove only SP3C production prototypes, retain clearly isolated green
canary/evidence work for review, record the failed verdict, and STOP. Do not make
the implementation/backfill commits and do not print the success ending.

## Job 2 — centralized safe projections and reference scanner

Create focused, injected, single-purpose readers—never a generic settings
service.

### Authorized library projection

Take one fresh settings snapshot per request. Project summary-only array records;
never place raw template `parts` into a custom Table record or row action
arguments.

For valid configured rows show:

- stable record key `configured:{family}:{key}`;
- composite identity;
- escaped stored label with key fallback;
- translated family and layout;
- parts count, except a non-capable actor viewing a protected template receives
  generic translated **Restricted** instead of an exact count;
- explicit where-used count/status;
- separate implicit/default-override indicator;
- permitted route-only actions.

For each missing registry default show one virtual row with stable key
`virtual:{family}:{key}`, translated virtual-default display copy, implicit-use
status, and only **Create override**. Never show both virtual and configured rows
for one identity.

Keep record ordering deterministic: valid configured rows follow their raw
stored relative order; diagnostics follow the affected raw index; missing
virtual defaults follow registry family order after configured/diagnostic rows.
Do not reinterpret this display order as a new template-reordering feature.

Do not silently normalize corrupt storage for display. A malformed row or
duplicate identity must produce a safe localized non-editable diagnostic row
without raw protected content, using a stable unique key derived from raw index
plus a one-way canonical hash (for example `corrupt:{index}:{hash}`), or block
the library with a localized corruption message and recovery direction. Treat
unparseable parts as protected/restricted for display and action policy. Editing
a corrupt/duplicate target remains 404/refused. Tests must prove corrupt siblings
are never rewritten by an unrelated valid edit.

### Reference scanner

One request-scoped scanner accepts the same fresh settings snapshot and one
parameterized Eloquent projected query over **all** HomepageSections, including
invisible rows. Select only the columns needed for correct derivation and useful
blocker copy: at least `id`, `name`, `type`, `source_config`, and
`display_config`; order by `id` for deterministic blocker output. Escape names
at output and preserve Unicode/Hebrew content. Do not lazy-load relationships.

Return a map keyed by `family:key` containing:

- `podcasts_page.template_key` as `content_group`;
- `podcasts_page.item_template_key` as `content_item`;
- HomepageSection IDs/names/counts;
- implicit/default-use separately from explicit references.

Derive HomepageSection family with the installed
`PublicDisplaySectionConfigValidator`/`PublicDisplaySectionRegistry` behavior:
valid explicit `display_config.template_family`, otherwise the normalized
`source_config.source_type`, otherwise the legacy `type` fallback. Scan invisible
rows. Put an explicit template key whose effective family is malformed or
ambiguous into a separate ambiguous-key bucket; rename/delete of that key in any
family is blocked rather than silently ignored.

Compute the map once outside Table/Blade closures. No query in a column, action
visibility closure, Blade view, preview, or per-template loop. No persistent
cache. Test explicit family, source-config inference, legacy fallback, invalid
family fallback, invisible rows, no-family/content-block rows, malformed
ambiguity, and constant query count at scale.

The library scan is informational. Rename/delete must run a new save-time scan
against the fresh save snapshot and a current projected HomepageSection query.
Because locks are prohibited, document the remaining scan-to-save race and make
no concurrent referential-integrity claim.

## Job 3 — library page and routes

Keep `App\Filament\Pages\CardTemplateSettings` as the read-only library with
the existing panel slug:

- `/settings/card-templates`

These route strings are Filament page slugs. The installed admin panel path
prefix makes the generated full paths `/admin/settings/card-templates` and the
corresponding prefixed create/edit paths. Always assert URLs through Filament
Page APIs rather than hard-coding either form.

Preserve its existing Settings navigation group, label seam, icon, and sort.
Keep the legacy Advanced-tab redirect through `CardTemplateSettings::getUrl()`.
The panel's existing authentication middleware and admin-or-super-admin page
gate apply to library and editor routes. Reauthorize every row/header action and
every direct Livewire invocation; hiding an action is never the authorization
boundary.

Use Filament 5 custom array `records()` with `->paginated(false)`. Keep the list
unsearched, unsorted, and unfiltered unless measurement proves a need; if any
behavior is enabled, implement it inside `records()` using the installed custom-
data arguments. Merely marking columns searchable/sortable is invalid. Type row
records as arrays. There are no state-changing library actions and therefore no
table reset or persistence path.

Library actions only generate URLs through Filament Page APIs:

- header Create;
- Edit for a valid configured row;
- Clone for a valid configured row when current authorization permits;
- Create override for a virtual row.

Do not add enable/disable, delete, reorder, bulk, or inline-save actions.

Use separate hidden Filament custom Page classes for stable create/edit panel
slugs, sharing editor schema/support code without duplicating writers:

- `/settings/card-templates/create`
- `/settings/card-templates/edit/{family}/{key}`

Do not manually call `urldecode()` or `rawurldecode()`. Laravel/Symfony already
provide decoded route values. Apply route constraints where Filament supports
them, then repeat validation at mount and every action/save. Validate family,
ASCII key regex, and 80-character maximum. Return 404 for missing, malformed,
corrupt, or duplicate edit identity. Test `%2F`, `%252F`, encoded percent/braces,
invalid UTF-8, overlength keys, direct URLs, unauthorized roles, old renamed URL,
SPA navigation, route generation, and direct invocation of hidden actions.

### Clone and override transport

Create URLs may carry only a mode (`blank`, `clone`, `override`) plus validated
source `family` and `key`. Never put template arrays in query strings, session,
or browser storage.

On mount, the create page refreshes settings, resolves the source exactly once,
re-authorizes it, and builds an authorized draft. Store mode/source identity and
the clone source's canonical raw-row fingerprint as locked server-owned state. A
tampered or unauthorized source is refused. Clone save rechecks that source in
the final fresh snapshot; missing, duplicate, or changed source is a stale clone
conflict with zero settings save. Default override uses the code registry source
and does not invent a stored source fingerprint.

For clone keys, try `_copy`, `_copy_2`, and so on. For every candidate suffix,
truncate the ASCII base to `80 - strlen(suffix)` before appending it, then check
the fresh identity set. Apply the same deterministic maximum-length treatment to
the translated label copy suffix within 120 characters. Recheck collision at
final save. A virtual override keeps the fixed default identity and is created
only on final save.

## Job 4 — one-template editor and authorization-safe state

The editor contains one template draft and no sibling templates or foreign
settings roots. Keep the ownership registry unchanged: the `card-templates`
subject still owns the complete `card_templates` root. Page schema isolation and
registry completeness/uniqueness are separate tests.

Locked server-owned properties include, as applicable:

- operation mode;
- original family and key;
- raw-target canonical fingerprint;
- clone/override source identity;
- clone source fingerprint;
- local measurement mode, profiling mode, and measurement fixture identity.

`#[Locked]` protects against client mutation but is not secrecy. Never place
protected raw parts or a fingerprint input containing them in public state; only
the one-way target fingerprint may be serialized.

Once per Livewire request before rendering, and again at every action/save
boundary that can mutate state, evaluate current user role and freshly resolve
current mode state. Do not query once per field, block, summary, or render
closure. If capability was lost after mount, sanitize the outgoing draft to the
read-safe shell projection before any modal or HTML is returned. Already
delivered browser data cannot be retroactively revoked; make no such claim. No
further protected value may be returned or persisted. On shell-only save,
restore fresh protected parts server-side from the original target.

The canary-selected preview or selected-part component mutates only the parent
unsaved draft. It never resolves or saves `PublicContentSettings`, creates a
backup, invalidates cache, or owns an independent settings lifecycle. Cancel,
validation failure, stale conflict, collision, and refusal leave the page draft
intact and cause zero settings saves/events.

## Job 5 — focused writer

Implement one focused writer with injected validator, authorization guard,
reference scanner, and settings dependency. Keep UI composition in Filament
pages and persistence rules in the focused writer. Do not create a broad
`SettingsService`.

### Edit and allowed rename algorithm

Execute in this order:

1. Reauthorize page and edit ability from current server state. Refuse local
   measurement mode before dehydration or lifecycle hooks.
2. Validate/dehydrate exactly one editor draft. No sibling template or foreign
   settings root may be accepted from Livewire state.
3. Resolve the canonical `PublicContentSettings`, call `refresh()` once, and take
   one fresh full decoded settings snapshot.
4. Read the fresh raw `card_templates` list. Locate the immutable original
   identity exactly once: zero is missing/stale, more than one is corrupt/
   duplicate.
5. Canonicalize the raw target row only with one deterministic helper and compare
   its hash with the locked mount fingerprint. Mismatch is stale conflict.
6. Recompute capability against current role and freshly resolved mode. Apply the
   protected-template action/shell policy. The original identity—not the proposed
   rename—is the authorization identity.
7. If identity changes, reject default identities and run the fresh reference
   scan; any explicit reference blocks rename. Check the desired identity against
   all fresh siblings and reject collision.
8. Preserve the requested new identity aside. Associate the dehydrated draft
   with the original target and restore fresh protected parts before validation.
   A forged protected addition/change is a validation/authorization refusal.
9. Restore the requested identity onto the guarded candidate. Validate and
   normalize a **one-element** `card_templates` list with the installed validator.
   Require exactly one normalized result and zero `invalidConfig` entries; map
   every issue to bilingual form errors. Enforce key 80 and label 120 limits at
   the form and writer boundary.
10. Apply the final target-specific authorization guard to the normalized result
    against the same fresh target/snapshot using the original identity
    explicitly. Recheck that identity/family and protected state satisfy policy.
11. Replace only the target at its original numeric index. Do not validate,
    normalize, sort, or rebuild siblings. Overlay only the resulting
    `card_templates` root into the same fresh full settings snapshot.
12. Preserve the existing transaction hooks and configured behavior, save hooks,
    profiler, notification, `rememberData()`, redirect, and exception behavior.
    Call `fill()` once and Spatie `save()` exactly once.

An unchanged edit may still perform the single existing save; backup-row
deduplication remains valid. After rename, redirect to the new edit URL and make
the old URL 404. Same-identity edits remain on or redirect back to the canonical
edit URL.

### Create, clone-save, and default-override algorithm

Create has no original target fingerprint:

1. Reauthorize and refuse measurement mode.
2. Validate/dehydrate one draft only.
3. Refresh canonical settings once and take the fresh full snapshot/raw list.
4. For clone, locate the locked source identity exactly once and compare its raw
   fingerprint. Missing, duplicate, or changed source is a stale clone conflict.
   Recompute capability and reject forged protected parts. A non-capable clone
   of a protected source is refused before draft creation and again here.
5. Reject desired identity collision. Default override must equal its locked
   virtual source identity.
6. Validate/normalize one candidate, require one result and zero invalid config,
   then apply the final authorization guard against the same snapshot.
7. Append the candidate at the end in a defined order without touching siblings.
8. Overlay the root, `fill()` once, and `save()` once through the existing hooks/
   profiler/notification behavior.

Redirect successful create/clone/default override to its edit URL. Cancel or any
failure causes zero settings saves/events.

### Guarded delete algorithm

Delete is a distinct writer operation invoked only by an editor header action:

1. Reauthorize delete and refuse measurement mode.
2. Refresh canonical settings once and take the fresh full snapshot/raw list.
3. Locate the original identity exactly once and compare the same fingerprint.
4. Recompute capability. A non-capable actor cannot delete a protected template.
5. Block every default identity.
6. Run the fresh reference scan and block any explicit reference.
7. Remove exactly the target. Compact numeric list keys as required for valid
   JSON-list storage; later siblings may shift indices, but their strict decoded
   values, canonical per-row JSON, and relative order must remain unchanged.
8. Overlay the root, `fill()` once, and `save()` once through the same hooks,
   profiler, notification, and exception behavior.
9. Redirect success to the library.

Cancel, missing/duplicate target, stale fingerprint, reference/default block,
authorization failure, or capability loss causes zero settings saves/events.

### Preservation and concurrency contract

For edit/rename, untouched siblings preserve strict decoded PHP-array equality,
canonical per-row JSON equality, original numeric index, and order. Create
preserves every existing sibling/index and appends. Delete preserves sibling
strict/canonical equality and relative order while allowing necessary list-index
compaction. Do not claim preservation of arbitrary historical database JSON
whitespace, escaping, or key order; Spatie rewrites the complete settings
property.

Every operation uses one fresh settings snapshot for target lookup, authorization
restoration, candidate merge, foreign-root preservation, and final fill. Test
that a sequentially changed foreign root survives. Without locks there remains a
refresh/compare/save TOCTOU window; this contract detects sequential stale pages
only.

## Job 6 — lifecycle locks, measurement, profiler, and evidence

Display the existing family-level lifecycle lock state on library/editor. Do not
create template/part lock units. Import locks remain import-only and never gate
ordinary editor saves.

### Local measurement mode

Both pages deliberately support local-only `sp3a_measure=1`:

- initialize measurement/profile flags only during mount when environment is
  local;
- keep flags and fixture identity `#[Locked]`;
- on every Livewire request, re-establish server profiling config from locked
  local-only state rather than trusting absent update-request query parameters;
- use the unchanged SP3A fixture as the runtime library/editor measurement
  projection and choose an explicit fixture identity for edit measurement;
- use the separate deepest SP3C fixture only for canary/tests;
- refuse every create/edit/rename/delete/clone mutation before dehydration;
- test a forged attempt to set measurement mode false;
- ordinary requests expose no measurement headers or fixture state.

The five middleware headers describe initial GET only. Never attribute them to a
selected Livewire state. Measure selected HTML through `Livewire::test()->html()`.
Record Livewire delta bytes only if a documented stable harness can extract them;
do not make an internal/unstable payload detail a gate.

### Profiler subject context

Add scoped `withSubject('card-template-library', ...)` and
`withSubject('card-template-editor', ...)` support without renaming existing
phases or request kinds. Subject scope restores the previous value in `finally`,
works when nested, and cannot leak after exceptions or into unrelated pages.
Timer tokens capture subject as well as request kind so `start()`/`stop()` retain
the initiating context. The synchronous `SettingsSaved` listener and backup/
snapshot profiling phases inherit the editor subject during save. Test all of
these behaviors.

### Evidence classes

Record separately:

1. **Initial GET:** uncompressed bytes, total/settings/reference queries,
   lifecycle derivations, duplicate loads, server-HTML element count, wrappers,
   and controls for library and unselected editor.
2. **Selected state:** post-selection `Livewire::test()->html()` element,
   field-wrapper, `wire:model`, control-ID, sentinel, and serialized-state counts;
   delta size only if the stable harness above exists.
3. **Browser/operator:** authenticated fixed-viewport TTFB, DOM, listeners, heap,
   navigation, back-warning, and nested editing. The historical 29,404 Advanced
   DOM value remains contrast only. Never compute percentages across browser and
   server/Livewire surfaces.

Use stable `data-sp3c-*` markers. Record browser samples only when actually
collected; otherwise mark the numbered operator checks pending.

## Mandatory tests

Add or retarget behavioral Pest coverage for all of the following:

### Library and references

- configured, virtual-default, and default-override rows, exactly one row per
  identity, stable kind-prefixed keys, escaped labels, bilingual UI, restricted
  parts status, and absence of raw parts from table/action state;
- unpaginated custom data and no persistence from every library action;
- corrupt/malformed/duplicate diagnostic behavior without normalization;
- one projected HomepageSection query, correct settings paths, all family
  derivation/fallback cases, invisible sections, ambiguous blockers, implicit
  defaults, deterministic ID ordering, escaped Hebrew/Unicode reference names,
  no lazy-loading/N+1 behavior, and constant query count at scale;
- template-level reorder is absent and explicitly deferred.

### Routes, create, clone, and edit

- exact route generation, separate hidden pages, legacy redirect, direct URLs,
  guest redirect, ordinary-user refusal, authorized admin/super-admin access,
  unauthorized direct actions, malformed/encoded/overlength input, missing/
  corrupt/duplicate targets, SPA/back navigation, and renamed-old-URL 404;
- blank create, virtual override, clone-unsaved with zero lifecycle effects,
  deterministic 80-character suffix truncation, `_copy` collision sequence,
  120-character copied-label handling, source disappearing/changing/duplicating,
  tampered source, and save-time collision recheck;
- ordinary edit, unused non-default rename, referenced rename refusal, default
  rename refusal, validation failures, and conflict draft retention.

### Writer preservation and lifecycle

- independent sequential same-template stale-page conflict;
- independent sequential sibling-template edits both surviving;
- target edit/rename strict and canonical sibling equality plus unchanged index/
  order; create append preservation; delete relative-order/canonical preservation
  with valid list compaction;
- a sequentially changed foreign settings root surviving;
- exactly one `SettingsSaved`, one backup-manager attempt, and one invalidation/
  reset sequence for a successful changed save; do not require a new deduplicated
  backup row in every case;
- zero settings saves/events for cancel, validation, authorization, collision,
  missing, duplicate, stale, reference/default block, measurement mode, and
  failed delete;
- preserve existing transaction hooks/configuration without claiming an active
  DB transaction or atomic concurrent serialization.

Create factories/fixtures before `Event::fake()` so setup events are not
suppressed. Test event count separately from real listener effects and backup-row
deduplication.

### Authorization and Livewire state

Use admin, super-admin single mode, and super-admin multi mode across:

- initial HTML, serialized state, library record/action state, preview summary,
  selected top-level editor, selected nested editor, modal state, and direct
  action invocation;
- forged create/edit/nested/rename and forged protected additions;
- non-capable protected shell edit with fresh whole-parts restoration;
- protected clone/delete hidden and hard-refused;
- role demotion and multi→single capability loss after mount, with safe outgoing
  re-projection and zero protected save;
- original identity remaining the authorization identity through allowed rename.

### Canary, measurement, profiler, and regressions

- complete nested canary matrix and honest ordinary/protected sentinel rules;
- same-surface control, repeated deterministic samples, ≥70% wrapper/control
  reduction, frozen +20% caps, scale fixture, bounded settings/reference queries,
  and zero duplicate lifecycle loads;
- locked measurement/profile/fixture state, Livewire survival, forged-mode
  refusal, runtime SP3A projection, and no ordinary-request leakage;
- nested/exception-safe profiler subject restoration, timer subject capture,
  listener inheritance, and no cross-page leakage;
- ownership registry still classifies every public root exactly once, independent
  editor schema contains only its one-template draft, and library contains no
  writable settings schema;
- frozen lifecycle SHA and SP3A fixture regression pass untouched;
- existing SP3B behavior remains green after deliberately retargeting old tests
  that treated `CardTemplateSettings` as a writable whole-list form, including
  `SettingsSp3bTest`, `RolesGatesTest`, `PublicFrontCardTemplateBuilderTest`, and
  `PublicFrontIconRegistryTest`; preserve and extend
  `AdminPhase02ResourcesTest` coverage for the library's existing navigation
  position and the editor pages' navigation exclusion.

Do not add or run a simultaneous-request serialization test.

## Docs, handoff, and completion

Update:

- the amended SP3C research/plan with canary verdict and frozen budgets;
- `docs/phase-02/current-project-state.md`;
- ledger row `SP3C - Template library and one-template editor`;
- `docs/phase-02/settings-sp3c-handoff.md`.

The committed handoff must classify every requirement, list files/tests, record
every command and gate, disclose tooling deviations, and include the three
evidence tables. Its Local Front Check Report must be numbered imperative manual
operator steps with objective expectations, including:

1. each default identity appears exactly once as virtual or configured override;
2. opening one template mounts only its draft;
3. selecting top-level and nested parts mounts controls only for that selection;
4. editing and saving changes the public card while edit-time siblings remain
   strict/canonical equal at the same indices;
5. referenced rename is blocked with the reference list;
6. clone opens unsaved with a deterministic `_copy` key;
7. two sequential tabs on the same template make the second save stale;
8. a non-capable protected template shows only generic restricted state, blocks
   clone/delete, and contains no protected token in page source/state;
9. dirty back navigation warns before abandoning the draft; it does not promise
   recovery after a full remount;
10. operator browser metrics are recorded or explicitly pending—never invented.

On successful completion only:

1. commit implementation, tests, docs, and handoff with `## Commit hash` pending:
   `feat: add template library and one-template editor`;
2. immediately make the docs-only handoff/ledger hash stamp commit:
   `docs: backfill settings sp3c hash`;
3. verify the tree is clean and do not push.

If preflight, baseline, canary, or a required final gate blocks completion, do
not create the canonical success commits and do not print the success sentence.
Report the exact blocker, retained evidence, and working-tree state instead.

After both successful commits, end with exactly:

```text
Settings SP3C is complete. Waiting for Yoni review before continuing.
```
