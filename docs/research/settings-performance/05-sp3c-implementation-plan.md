# Settings SP3C provisional implementation plan

> **Historical implementation notice — 2026-07-16:** This plan describes the
> completed SP3C implementation and remains evidence for that delivery. It must
> not be reused as the forward Template architecture. The operator-approved
> ARCH1 decisions in `07-sp3d-pre-research.md` now place Card Templates and
> Public Forms in versioned model/Resource aggregates before SP3D.

Date: 2026-07-15

Status: canary-amended and approved for production adoption. This plan is self-contained and
implements `settings-sp3c-codex-prompt.md` v3. The historical v1 review is
evidence only and is not an execution dependency.

## Outcome and non-goals

Replace the temporary whole-list Card Templates settings form with:

1. a read-only Template Library at the existing Card Templates URL; and
2. hidden Create and Edit custom pages that mount exactly one template draft.

The library never persists. Create, edit, allowed rename, clone-save, and
guarded delete use one focused writer, one fresh canonical
`PublicContentSettings` snapshot, and exactly one Spatie `save()` on success.

Do not add migrations, settings properties/groups, template enable flags,
template reorder, dependencies, persistent cache, coordination locks,
concurrency/transaction serialization, lifecycle groups, import/restore/
normalize rewrites, backup queues, or broad generic services. Do not change the
SP3A fixture or frozen lifecycle SHA. Do not claim simultaneous-request
serialization or literal database-payload byte preservation.

## Domain contracts

- Identity is the validated composite `family:key`.
- Families come only from `PublicFrontCardTemplateRegistry::families()`.
- Keys are ASCII semantic keys, maximum 80 bytes/characters under the specified
  ASCII grammar. Labels are maximum 120 Unicode characters.
- Defaults live in the code registry. A configured row at a default identity is
  an override. A missing default produces one virtual row and is not stored
  until Create override succeeds.
- A protected template is any template containing a protected part at any
  supported nesting depth under the existing role/mode policy.
- A capable actor currently passes the super-admin gate and the Admin UX mode
  is currently `multi`. Recompute this on page access, mount, every action, and
  every save/delete boundary.
- Locked means client mutation protection only. Locked values are browser-
  visible and are revalidated/re-authorized server-side.

## Job 0: durable documentation

1. Add to `AGENTS.md` a durable commit-message section with allowed prefixes,
   imperative mood, and the canonical `docs: backfill <step> hash` pattern. Add
   no hook.
2. Add ledger history rows only for `9d8296f` logo/favicon and `d128cfd`
   collapsible navigation. Do not alter the already-recorded Curator or OTP
   rows.
3. Add the final SP3C state, ledger, handoff, and hash stamps only after the
   implementation and gates have passed.

## Job 1: isolated canary before production adoption

### Test-owned fixture

Add a separate SP3C deepest fixture under tests. It will contain:

- every applicable card-part block type;
- repeated same-type parts with distinct ordinary sentinel values;
- top-level groups and nested children at the validator's maximum depth;
- distinct protected sentinels at top-level and nested depth;
- hostile strings in every property rendered by summaries;
- enough configured templates and homepage-section rows for scale/query tests.

It will not modify or masquerade as the frozen SP3A runtime fixture.

### Canary surface and behavior

Build only a test/canary surface first. Render the current full Builder against
the same fixture as the control. Candidate A uses Builder block previews with
escaped app-owned summary Blade. If Candidate A fails, Candidate B is an
app-owned summary plus one selected-part editor that mutates the parent draft.

Exercise all top-level summaries; selected top-level and nested editors;
edit/confirm; modal-local cancel; validation failure; clone/delete/reorder at
both supported levels; close/reopen; repeated-type state isolation; parent draft
survival across editor and page-level validation/stale/collision errors; browser
Back with the panel unsaved-change warning; and the absence of durable remount
recovery.

Ordinary unselected sentinels may stay in the parent Livewire snapshot but must
have no selected marker, field wrapper, control ID, or `wire:model` editor path.
A selected ordinary sentinel appears in exactly one editor. For a non-capable
actor, protected sentinels/tokens/labels/values are absent from initial HTML,
summaries, modal HTML/state, and serialized Livewire state; only generic
translated Restricted copy is visible.

### Metrics and verdict

Create one reusable test helper using a DOM parser. Freeze the installed
Filament field-wrapper selector after source/markup inspection. Count total
elements, wrappers, controls/IDs/`wire:model` only inside wrappers, summary/
action chrome separately, serialized state bytes under one documented JSON
encoding, stable initial/selected response bytes, settings reads, total queries,
reference-scan queries/rows/time, lifecycle derivations, and duplicate lifecycle
loads.

Take at least three deterministic samples. A candidate passes only if wrappers
and controls each fall at least 70% from the same-surface full-Builder control,
the UX matrix passes, and query/settings/reference/lifecycle counts remain
bounded as fixture size grows. Freeze production budgets at the measured maxima
plus 20% for the library, unselected editor, and selected editor. Claim only
rendered wrapper/control reduction, not complete PHP schema deferral.

If previews and fallback both fail, retain only isolated green canary/evidence,
remove production prototypes, document the failed verdict, stop without
canonical commits, and do not print the success ending.

### Canary verdict amendment — passing

The isolated canary selected Filament Builder previews. Unselected blocks render
escaped preview summaries and action chrome; the installed edit action mounts
only the selected part schema. A selected group uses the same rule for its child
Builder, so only the group plus one chosen nested child mount editor controls.
Filament may still construct Block schemas in PHP; the accepted claim is DOM
wrapper/control reduction only.

The final canary run passed 9 tests and 79 assertions. Three samples were
identical. The same fixture/control measured:

| Surface | Wrappers | Controls | Wrapper reduction | Control reduction | Serialized state bytes |
|---|---:|---:|---:|---:|---:|
| Full Builder control | 396 | 146 | — | — | 18,118 |
| Preview unselected | 8 | 2 | 97.98% | 98.63% | 18,118 |
| One selected top part | 16 | 6 | 95.96% | 95.89% | 18,118 |
| Selected group + one child | 24 | 10 | 93.94% | 93.15% | 18,118 |

The identical ordinary serialized-state size is intentional and honest:
unselected ordinary parts remain in the one parent draft for reorder/save but
have no part-specific wrapper, control ID, or `wire:model` path. The test-owned
non-capable surface removes `parts` entirely and exposes only translated generic
Restricted copy.

Frozen production caps are each passing maximum plus 20%, rounded up:

| Surface | Elements | Wrappers | Controls | IDs | `wire:model` | Summary/action chrome | HTML bytes | State bytes |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Library, 30 rows | 1,262 | 2 | 2 | 2 | 2 | 0 / 0 | 575,913 | 13,973 |
| Editor unselected | 4,212 | 10 | 3 | 3 | 3 | 35 / 35 | 1,508,900 | 21,742 |
| Editor one top selected | 4,341 | 20 | 8 | 8 | 8 | 35 / 35 | 1,562,081 | 21,742 |
| Editor group + nested selected | 4,469 | 29 | 12 | 12 | 12 | 35 / 35 | 1,616,670 | 21,742 |

The library cap is normalized to 30 rows, matching the frozen fixture surface.
The measured library is the same mechanism required in production: a Filament
custom-data table with seven columns, boolean icon state, header/record URL
action chrome, custom-data search/default-override filtering, and pagination
disabled, not a plain Blade list. Search/filter were admitted only after the
required FilaCheck gate demonstrated the need; their filtering is implemented
inside `records()` as required for custom data. Scale tests separately require
constant query counts at 10 and 100 rows. The isolated surface issued zero
queries/settings reads/reference scans/lifecycle loads.
Production must prove its own fixed contract: one settings snapshot, exactly
one projected homepage-section scan query, and no duplicate lifecycle
derivation. These fixed query boundaries do not scale with template or section
count.

The static Livewire test response does not merge Filament's teleported action-
modal DOM. Native modal action state/mutations were tested directly; selected
DOM counts used the same selected field set in an isolated parent-path-bound
render. Browser Back, heap, listener, and actual teleported-modal sampling are
pending objective operator acceptance and are not represented as completed.

Production adoption may now begin. If production cannot remain within these
caps or the selected-part/protected-state boundaries, stop and revisit the
candidate rather than weakening the budgets.

## Job 2: safe projection and reference scanner

### Focused services

Create injected single-purpose collaborators under `App\Support\Settings` (or
a narrower Card Templates namespace):

- identity/canonical raw-row fingerprint helper;
- current role/mode capability and protected-part policy helper;
- library projector;
- reference scanner;
- focused writer;
- summary formatter used by both projection and escaped preview Blade.

Do not create a generic `SettingsService` and do not query/read settings in
views or row/part loops.

### Library projection

Per request, refresh/resolve one settings snapshot, project once, scan references
once, and pass safe arrays to a custom Filament table. Configured rows use key
`configured:{family}:{key}`. Virtual defaults use
`virtual:{family}:{key}`. Corrupt rows use a deterministic non-route key such as
`corrupt:{raw-index}:{hash}`.

Each valid row contains only composite identity, escaped/display-safe label with
key fallback, translated family/layout, safe part status/count, explicit
where-used status/count, separate implicit/default-override indication, and
permitted route-only action URLs. Raw `parts` never enter table/action state.
Non-capable viewers of a protected template receive generic Restricted instead
of an exact part count. Unparseable parts are protected. Corrupt rows expose no
edit route and are never normalized by viewing.

Keep valid configured rows in raw stored order and include raw index in
diagnostics. Add one virtual row per registry default missing from the configured
identity bucket, in registry order after configured rows. A default override is
one configured row, never an extra virtual row.

### Reference scanner

The scanner accepts the same fresh settings snapshot and performs exactly one
projected Eloquent query for every `HomepageSection`, including invisible rows:
select `id`, `name`, `type`, `source_config`, `display_config`, ordered by `id`.
It scans:

- podcast/group settings template and item-template keys;
- homepage-section source/display explicit template family/key paths;
- legacy section type/source fallback family derivation;
- implicit registry default use, recorded separately from explicit refs.

For explicit section references, a validator/registry-valid explicit family
wins; otherwise derive family from source type, then legacy section type. A key
without a safe unique family goes to an ambiguous-key bucket and blocks rename
or delete in every possible family. Return display-safe reference labels and
stable IDs, not models. Record one query, rows scanned, and elapsed time.

Run a new scan during rename/delete save. This is a sequential stale/reference
guard with an acknowledged scan-to-save TOCTOU window, not concurrency
serialization.

## Job 3: pages, routes, table, and access

### Read-only library

Refactor `App\Filament\Pages\CardTemplateSettings` to a custom `Page` with
`HasTable`/`InteractsWithTable`. Preserve its existing slug, navigation label,
group/order/icon, admin page gate, and import-lock display. The table uses
`records()`, `paginated(false)`, no search/sort/filter, read-only columns, one
Create header URL, and valid-row Edit/Clone/Create override URL actions. It has
no delete action and no writable settings schema.

Every action is URL-only and rechecks current authorization before exposing a
URL. No action persists, resolves a writer, or carries raw templates.

### Hidden create/edit pages

Add hidden Filament custom Pages at exact slugs:

- `settings/card-templates/create`;
- `settings/card-templates/edit/{family}/{key}`.

Use Page `getUrl()` APIs everywhere and preserve the legacy advanced-tab
redirect to the library. Do not manually URL-decode. Validate route family and
ASCII key grammar/length in `mount()` and again in each action. Guests redirect
to admin login; ordinary users get 403; missing/corrupt/duplicate or malformed
targets get 404/refusal according to v3. Test encoded separators, Unicode,
overlength, and double encoding.

Create accepts only mode `blank`, `clone`, or `override`, plus validated source
family/key. On mount, refresh once, resolve/re-authorize source exactly once,
and create an authorized draft. Keep mode, source identity, source fingerprint,
measurement flags, original identity, and target fingerprint in `#[Locked]`
server-owned state. Never transport arrays in query/session/browser storage.

Clone is unsaved. Generate `<source>_copy`, then `_copy_2`, etc., deterministically
under the 80-byte ASCII limit. Append a translated clone suffix to the label
with Unicode-safe 120-character truncation. Override keeps the exact virtual
default identity. Recheck clone source/fingerprint at save.

## Job 4: exactly-one-draft editor

Both editor pages expose one `data.template` (or equivalently scoped) draft,
never a `card_templates` list. Top-level shell fields have writer-mirrored
required/finite/length rules. Parts use the passing canary preview/fallback
mechanism. The old whole-list Repeater is removed from production.

The part editor mutates only the parent unsaved draft. It never reads or saves
settings, creates backups, invalidates cache, or derives lifecycle state.
Cancel, local validation failure, page validation failure, stale conflict,
collision, refusal, and delete refusal preserve the page draft and cause zero
settings saves/events.

Non-capable protected templates are projected to shell-only state before any
HTML/state leaves the server. Clone/delete are hidden and hard-refused. Rename
may be allowed only under the original-identity policy. If capability is lost
between requests, sanitize every future response to the safe shell; do not claim
revocation of data already delivered. A shell-only allowed save restores fresh
protected parts server-side.

Use Filament's unsaved-data trait and call `rememberData()` after save. Browser
Back must show the panel warning for dirty state. There is no durable remount
recovery.

## Job 5: focused writer algorithms

### Shared preservation and lifecycle boundary

All operations accept canonical top-level template keys only and reject forged
foreign settings roots. Resolve the canonical settings instance, call
`refresh()` once, take one full decoded snapshot, modify only `card_templates`,
overlay that root into the same snapshot, call `fill()` once, and call Spatie
`save()` exactly once. Preserve existing transaction/configuration hooks,
before/after save hooks, profiler phases, notification, `rememberData()`,
redirect, and exception behavior.

Use one deterministic raw-row canonical JSON/fingerprint helper. Compare
siblings before/after by strict decoded equality and per-row canonical JSON,
with index/order preserved for edit/rename and relative order preserved for
delete compaction. Preserve a sequentially changed foreign settings root. Make
no literal database JSON bytes/whitespace/key-order claim.

### Edit and allowed rename

1. Reauthorize page/edit using current user and mode; refuse locked local
   measurement mode before dehydration/hooks.
2. Validate/dehydrate exactly one draft; accept no sibling/foreign root.
3. Refresh settings once and take the fresh full snapshot/raw list.
4. Locate the immutable original identity exactly once; zero is missing/stale,
   more than one is corrupt/duplicate.
5. Compare the fresh raw target fingerprint with the locked mount fingerprint.
6. Recompute capability and protected policy using original identity.
7. For a proposed identity change, block defaults, explicit/ambiguous refs, and
   collisions using the fresh scan. Do not repoint references.
8. Set requested new identity aside; bind candidate to original identity;
   restore fresh protected parts for an allowed shell-only edit; reject forged
   protected additions/changes.
9. Restore requested identity; validate a one-element card-template list with
   the installed validator; require one normalized result and zero invalid
   entries; map every issue to bilingual form errors; independently enforce key
   80 and label 120 at form and writer boundaries.
10. Apply final target-specific authorization against the same fresh target,
    snapshot, and explicit original identity.
11. Replace only the raw target numeric index. Never validate, normalize, sort,
    or rebuild siblings.
12. Run the shared one-fill/one-save lifecycle.

An unchanged edit may perform the one save. Rename redirects to the new edit
URL; the old URL then 404s. Same identity stays at/redirects to canonical edit.

### Create, clone-save, override

1. Reauthorize and refuse measurement mode before dehydration/hooks.
2. Validate/dehydrate one draft only.
3. Refresh once and take the fresh snapshot/raw list.
4. For clone, locate the locked source exactly once and compare its fresh raw
   fingerprint. Missing/duplicate/changed is stale with zero save. Recompute
   capability and reject protected source/forgery.
5. Reject desired identity collisions. Override must equal the locked virtual
   source identity and must still be missing from configured rows.
6. Validate/normalize one candidate with zero invalid entries and the same
   boundary rules.
7. Append exactly one normalized candidate after raw siblings, preserving all
   sibling decoded/canonical values and order.
8. Run the shared one-fill/one-save lifecycle and redirect to canonical edit.

### Delete

Delete exists only as a confirmed editor-header action.

1. Reauthorize delete and refuse measurement mode.
2. Refresh once and take fresh snapshot/raw list.
3. Locate original exactly once and match the locked fingerprint.
4. Recompute capability; non-capable protected delete is refused.
5. Block every registry default identity.
6. Run fresh scanner and block explicit/ambiguous references.
7. Remove only the target, compact list keys, preserve sibling strict/canonical
   values and relative order.
8. Run the shared one-fill/one-save lifecycle.
9. Redirect to library.

## Job 6: import lock, measurement, and profiler

- Keep the existing family import-lock surface visible on the library, import-
  only, with shared lifecycle/load semantics.
- Measurement remains local-only. Keep measurement/profile/fixture identity in
  locked properties and re-establish profiling config from locked server state
  on every Livewire request.
- Runtime library/editor measurement uses the unchanged SP3A fixture, with one
  explicit valid edit fixture identity. The deepest SP3C fixture remains test-
  only canary data.
- Every mutation refuses measurement mode before form dehydration or hooks.
  Forge-setting measurement false must still refuse. Ordinary requests expose
  no fixture state or measurement headers.
- Extend `SettingsPageProfiler` with exception-safe nested `withSubject()`
  restoration. Timers capture subject at start; listener phases inherit it; no
  subject leaks across pages/requests.
- Attribute middleware headers only to initial GET. Record component-side
  selected-state metrics separately.

## Validation, localization, and presentation

- Add English and Hebrew keys for every label, family/layout display, action,
  hint, status, empty state, restriction, diagnostic, validation issue,
  collision, stale conflict, reference/default block, and notification.
- Hebrew remains primary/RTL. Escape all stored and hostile summary values in
  Blade. Views perform no settings reads, queries, rendering side effects, or
  raw HTML output.
- Use Heroicon enum values, no string icons.
- Keep technical family/key/source/mode information accompanied by translated
  hints.

## Mandatory tests

### Library and references

- Configured, virtual default, and configured default override exactly once;
  stable kind-prefixed keys; escaped labels; bilingual copy; protected generic
  status; no raw parts in table/action state.
- Unpaginated custom data; no persistence from every library action; no template
  reorder.
- Corrupt/malformed/duplicate diagnostics without normalization and no edit URL.
- One projected homepage query over visible/invisible rows; every settings and
  section path; family derivation precedence; ambiguous blocking; explicit vs
  implicit use; display-safe references; constant query count/no lazy loading at
  scale.

### Routes and editors

- Exact generated routes, hidden pages, legacy redirect, direct URLs, guest/
  ordinary/admin/super-admin tiers, forged direct actions, malformed/encoded/
  overlength input, missing/corrupt/duplicate target, renamed-old URL 404, SPA/
  Back unsaved warning.
- Blank create, virtual override, unsaved clone zero lifecycle effects,
  deterministic key/label suffix truncation and collision sequence, locked
  source identity/fingerprint, clone source stale/duplicate/protected refusal.
- One-template draft only; current/fallback preview matrix; no whole-list state;
  no unselected ordinary wrappers/controls; no protected leakage.

### Focused writer and lifecycle

- Validation and unknown-key refusal; collision/missing/duplicate/stale failures;
  original-identity authorization through rename; default/reference/ambiguous
  rename and delete blocks; no repoint.
- Sequential independent sibling edits survive. Edit/rename preserve target
  index and every sibling strict/canonical value. Create appends. Delete compacts
  while preserving relative order/canonical siblings. Sequential foreign-root
  changes survive.
- Success dispatches exactly one `SettingsSaved`, one backup-manager attempt,
  and one invalidation/reset sequence. Do not require a new deduplicated backup
  row each time.
- Cancel/validation/auth/collision/missing/duplicate/stale/reference/default/
  measurement/failed delete cause zero settings saves/events.
- Do not add simultaneous overlap tests or claims.

### Authorization and protected data

- Capability matrix for role and live Admin UX mode at mount/action/save.
- Protected shell-only HTML/state/snapshot absence, generic copy only, server-
  side fresh protected restoration, forged additions/change refusal.
- Protected clone/delete hidden and hard-refused.
- Role demotion and multi-to-single after mount sanitize future responses and
  cause zero protected persistence.

### Canary, measurement, profiler, regressions

- Full nested canary interaction matrix and honest sentinel assertions.
- Same-surface control; three deterministic samples; at least 70% wrapper and
  control reduction; frozen +20% caps; scale/bounded query/settings/reference/
  lifecycle counts; zero duplicate lifecycle loads.
- Locked measurement/profile/fixture state across Livewire; forged false refusal;
  unchanged SP3A runtime projection; no ordinary leakage.
- Nested/exception-safe profiler subject restore; timer start subject capture;
  listener inheritance; no cross-page leak.
- Ownership registry still classifies all roots exactly once; editor schema only
  one draft; library no writable settings schema; frozen SHA/SP3A fixture
  untouched.
- Retarget SP3B and older tests that assumed `CardTemplateSettings` was a
  writable all-list form; keep public rendering, resolver, icon, roles, import,
  backups, normalization, and Curator regressions green.

Every HTTP-touching test calls `Http::preventStrayRequests()`; mail tests use
`Mail::fake()`; tests own fixtures; no local development database probe.

## Verification and failure policy

After these docs, run the prompt's exact 16-file targeted baseline before any
application code. Stop if an application assertion is red. A runner-only retry
requires identifying and recording the infrastructure cause.

After canary adoption and implementation, perform a requirement sweep, then the
final gate in this exact order on the final file state:

1. `vendor/bin/pint --test`
2. `vendor/bin/filacheck`
3. `npm run build`
4. full `php artisan test` last, never parallelized or interrupted

After any file change, re-enter at Pint. Record every run, including failures,
in the handoff. Do not run `filacheck --fix`. Do not change Composer/npm or push.

If a required preflight, baseline, canary, or final gate fails, create neither
canonical commit and report the exact blocker plus retained state.

## Documentation and canonical completion

On success, amend this plan/research with canary verdict/budgets; update current
state; add ledger row `SP3C - Template library and one-template editor`; and add
`docs/phase-02/settings-sp3c-handoff.md` with requirement classification,
files/tests/commands, gate results, deviations, three evidence tables, and
numbered imperative Local Front Check steps.

Create exactly:

1. `feat: add template library and one-template editor`
2. immediately, docs-only: `docs: backfill settings sp3c hash`

Stamp the implementation hash into handoff/ledger in the second commit, verify
the tree is clean, and do not push.
