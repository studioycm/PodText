# Step 5B Card Template path-corrected validation research

Date: 2026-07-19
Audit: `LS-20260719-STEP5B-CARD-UX2-FU03-PATH-CORRECTED-01`
Approved option: `STEP5B-CARD-UX2-FU03-PATH-CORRECTED-CLOSURE`
Verified baseline: `206963998b9513ea345ef657df7697b2901a7af3` on clean `main`, seven commits ahead of `origin/main`

## Scope and approval boundary

FU03 corrects one internal Card Template editor bug: structured validator paths
must reach the actual current Filament Builder field after hydration and
reordering. It covers the save failure path and the preview's existing “focus
invalid field” interaction, top-level and one-level nested parts, inline and
slide-over Builder modes, collapsed rows, the native Builder edit-action stack,
field focus, and deterministic safe fallback.

The approval does not authorize FU04 order compatibility closure, FU05
interaction/duplicate-refresh closure, FU06 copy cleanup, legacy inline-header
editing, a global explicit-order cutover, nested media redesign, contributor
image-field invention, preview preference persistence, migrations,
dependencies, permission or settings-lifecycle changes, generalized validator
or renderer work, production actions, another roadmap step, a branch/worktree,
push, or PR.

## Verified provenance and preserved contracts

- FU02 implementation, hash stamp, and correction are
  `a8be0aa4e7d89d8f70276ff497ee7b54a63d20df`,
  `23c3ac9e9c780e3f2b8882d5f9c4770f3cbb7f1e`, and
  `206963998b9513ea345ef657df7697b2901a7af3`.
- O1's responsive shell, focus restoration, exact `lg` boundary, dirty-state
  behavior, and single preview root remain binding.
- O2's position-canonical Builder flow, renderer geometry, diagnostics, and
  theme sources remain binding.
- FU02's automatic/preload/search/rendered effective-image rank, public
  eligibility, contributor order, restricted guards, and query bounds remain
  binding.
- The `d8f42da` navigation order, groups, labels, translations, and tests remain
  unchanged. FU03 requires no navigation or translation change.
- Restricted templates must continue to omit the parts Builder and must not
  mount actions, traverse protected state, or issue sample/domain queries.

## Installed-version and example research

Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2. Version-aware Builder guidance
confirms that runtime Builder items use UUID ownership, extra item actions
receive the item ID, and raw state is available through the owning Builder.
Livewire guidance confirms server-dispatched browser events and the existing
Pest browser harness for focus/modal verification.

The required FilamentExamples protocol used direct queries for Builder
validation focus, slide-over edit actions, nested UUID state, and collapsed
validation, followed by refined queries for `extraItemActions`, raw item state,
native edit-action mounting, and nested modal validation. The integration
exposes only `search_examples`; no read/fetch/detail/source tool is available.
Results were broad neighbouring v4 snippets and supplied no exact solution, so
this was search/snippet research rather than deep source retrieval.

Installed Filament 5.6.7 source is therefore authoritative:

- Builder preview editing mounts action `edit` with `item=<UUID>` and the
  owning Builder's absolute `schemaComponent` key.
- A mounted action schema uses `mountedActions.<index>.data` as its state path.
- `mountAction()` resets the error bag while opening the modal, so every
  required action must mount before any `addError()` call.
- `form-validation-error` expands ancestors and scrolls the first visible
  `[data-validation-error]` wrapper, but does not focus its input.
- Text, Select, and other relevant fields expose the installed `focus-input`
  DOM event for actual control focus.

## Current defect and structured provenance

`PublicFrontConfigValidator` already produces `PublicFrontInvalidConfig`
objects with immutable `path`, `reason`, and `valuePreview` fields.
`PublicFrontConfigResult::invalidConfig()` preserves those objects. The loss
occurs in `CardTemplateDraftNormalizer::normalizeCandidate()`, which converts
every issue into the display string `path: reason`. `CardTemplateWriteException`
can consequently carry only string details.

`CardTemplateEditorPage::reportWriteFailure()` concatenates those strings and
always calls `addError('data.key', ...)`. Preview refresh catches the same
exception, discards its issue detail, and the preview button focuses the first
generic editor control. Both paths therefore mis-target key/slug instead of the
invalid Builder field.

The fix must retain structured objects separately from compatibility string
details. It may format a notification from `path` and `reason`, but must never
parse that display text and must never use or expose `valuePreview` for field
identity.

## Positional path to current UUID contract

The normalizer converts Builder transport into the singleton validator group
`card_templates.0`. `candidateParts()` filters non-array entries, applies
`values()`, recursively repeats that operation for group children, strips
transient controls and legacy order, and synthesizes contiguous position order.
Validator indices consequently describe that exact current candidate order.

Only these shapes are accepted:

| Validator path | Current owner and inline target |
|---|---|
| `card_templates.0.<field>` | Allowlisted real root field `data.<field>` |
| `card_templates.0.parts` | Top Builder `data.parts` |
| `card_templates.0.parts.<p>.data.<field>` | Select the `p`th array-valued current top item; UUID `u` targets `data.parts.<u>.data.<field>` |
| `card_templates.0.parts.<p>.data.children` | Verified parent UUID `u` targets `data.parts.<u>.data.children` |
| `card_templates.0.parts.<p>.data.children.<c>.data.<field>` | Resolve top UUID `u`, then the `c`th array-valued child UUID `v`; target `data.parts.<u>.data.children.<v>.data.<field>` |

Mapping uses current state from the same request and preserves associative
iteration order. It validates the singleton prefix, bounds, array shape, part
type, Builder ownership, component existence, and allowlisted leaf field. It
never uses key, slug, label, part contents, translated messages, reason text,
or `valuePreview` as identity.

For slide-over mode, the resolver first identifies the same UUID owners, then
mounts native actions in owner order. A top-level leaf is re-targeted to
`mountedActions.<top-index>.data.<field>`. A nested leaf mounts the parent
action, resolves the nested Builder from that mounted schema, mounts the child
action, and targets `mountedActions.<child-index>.data.<field>`. All required
actions mount before errors are added.

## Visibility, focus, and fallback

- Inline errors dispatch one `form-validation-error` event after all error
  placement so collapsed Builder ancestors expand and the first error scrolls
  into view.
- Slide-over errors mount the exact owning action stack first, then add the
  error inside mounted action data.
- `label`, `label_position`, and `label_alignment` may reveal only transient
  `_show_label`; `icon` and `icon_position` may reveal only transient
  `_show_icon`. Persistent position/value state must not be changed merely to
  reveal a target, and modal cancel must preserve the original draft.
- After render, narrow page-scoped glue locates only the server-verified target
  wrapper and dispatches Filament's installed `focus-input` event. Native
  modal Escape/cancel and opener-focus restoration remain authoritative.
- An unmappable part issue attaches only to its nearest verified owning Builder
  (`data.parts` or verified children Builder). An unmappable non-part issue
  shows the generic notification without inventing a field target. No FU03
  path falls back to `data.key`.

The preview focus action must accept no path or UUID from the browser. It
recomputes validation from the current server-side draft, observes current
capability/restricted guards, and routes the first actionable structured issue.

## Authenticated Stage 1 browser evidence and limitation

The signed-in editor was inspected at
`/admin/settings/card-templates/edit/content_item/default_content_item` in
Hebrew RTL at 1470 x 745 CSS pixels. Slide-over mode was selected, inline mode
was available, the adjacent `lg+` preview was visible, and nine top-level items
exposed actual UUID ownership such as
`b7101fa0-730a-4183-9933-b4e125f7cce0` through state keys shaped as
`form.parts.<UUID>.data.item`.

Opening the native Edit action did not return within 458 seconds and the
browser operation was interrupted. No field changed and Save was not invoked.
Invalid submission, nested action mounting, focus, Escape/cancel, fallback,
restricted behavior, and request counts therefore remain mandatory Stage 2
fixture-backed browser evidence rather than Stage 1 claims.

## Smallest safe implementation

1. Extend the existing write exception with a separately typed collection of
   `PublicFrontInvalidConfig` objects while retaining string `details` for
   non-validation compatibility errors.
2. Pass validator objects directly from the draft normalizer without flattening.
3. Keep the resolver card-template-specific and private to the existing editor
   flow unless one tiny focused value object is required for readability; do
   not build a generalized validator-navigation platform.
4. Recompute the issue server-side for preview focus; do not serialize the
   issue array or duplicate the draft in a public Livewire property.
5. Mount native owning actions before errors, reveal only transient controls,
   dispatch installed expansion/focus events, and preserve deterministic
   owning-Builder fallback.

## Security and performance boundaries

- Keep `canAccess()` and current capability enforcement ahead of all routing.
- Restricted/forged interaction mounts no action, exposes no protected parts,
  and triggers no sample/domain query or settings mutation.
- Mapping and focus add zero database queries, settings writes, lifecycle
  events, backups, cache invalidations, reference scans, storage probes, HTTP,
  mail, or persistence.
- Prefer request-local variables. If render-delayed focus needs state, retain
  only bounded scalar target data for one response, never the full issue list
  or draft.
- One operator activation should produce at most one Livewire request. If
  installed nested action mechanics require two separate browser round trips,
  that is material performance/effort drift and requires an amended audit.
- Preserve the existing single preview root and frozen component/state canary
  ceilings. Browser DOM/network/modal evidence remains separate from
  server/query/component-state evidence.

## Required verification

Focused Pest coverage must prove structured issue transport without
`valuePreview`, exact root/top/nested/reordered UUID mapping, current owner
validation, collapsed inline expansion, mounted top/nested action state paths,
transient subordinate reveal, malformed/out-of-range/unsupported fallback,
no key/slug mis-target, repeated cancel/retry cleanup, and restricted/forged
zero-query/zero-mutation behavior.

Authenticated Chromium coverage must prove real top-level and nested errors in
inline and slide-over modes, reordered/collapsed rows, visible exact error,
actual focused element, native modal stack, Escape/cancel restoration,
deterministic fallback, restricted absence, Hebrew RTL and English LTR context,
viewport and UUID/path evidence, request observations, no persistence, single
preview root, and strict console/smoke behavior.

Final verification follows repository order on the final tree: requirements
sweep, `vendor/bin/pint --test`, `vendor/bin/filacheck`, `npm run build`, then
full serial `php artisan test` last. Any later file change restarts at Pint.

## Stop conditions

Return to an amended Stage 1 audit if implementation needs more than one
bounded task, a generalized JavaScript or validator subsystem, more than one
Livewire request for one navigation activation, persistent issue/draft state,
a dependency, migration, permission or lifecycle change, broader translation
or navigation work, production action, or any excluded FU04–FU06/renderer/
media/persistence/roadmap scope.
