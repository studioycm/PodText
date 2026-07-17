# Step 5B Card Template Preview Implementation Plan

## Control

- Approved Laravel Simplifier audit: `LS-20260717-STEP5B-01`.
- Approved option: `STEP5B-O1-FOCUSED-PREVIEW`.
- Baseline: `a137123cba22ba1325ef491959fecbc1b53d5706` on `main`,
  with a clean worktree at Stage 2 entry.
- Contract: `21-step5b-card-template-preview-ux-specification.md` v1.
- No migrations, dependency changes, persistent preview state, new permission,
  settings ownership change, or generalized preview framework.

## Installed-version research

Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2.

Installed-version documentation confirms:

1. A growing Filament `Select` should use `searchable()`,
   `getSearchResultsUsing()`, and `getOptionLabelUsing()` for constrained
   server search and selected-label resolution. The Step 5B selector will
   return at most 50 public-safe results and will not preload a collection.
2. Filament actions support read-only slide-overs with no submit action. This
   is the narrow preview shell; it does not own another editor form or draft.
3. Livewire `#[Locked]` properties are appropriate for the server-owned sample
   identity, preview status, timestamp, and rendered result. The editable
   `data` form remains the only authoritative draft.
4. `wire:dirty` can expose draft freshness without per-keystroke presentation.
   It does not authorize autosave, polling, or a duplicate server-side draft.
5. Pest browser tests support explicit viewport resizing and browser-side
   script assertions. Component HTML remains a separate evidence plane from
   teleported modal DOM, focus, network, listener, heap, and timing evidence.

## FilamentExamples research

The configured server exposed search/snippet access only; no separate
source/detail endpoint was available. Two query passes covered custom settings
pages, action slide-overs, searchable selection, responsive page layout,
dirty-state indicators, preview actions, and read-only modal composition.

Relevant results:

- **Live Content Preview For Editors** — a form hint action uses a Filament
  slide-over and a dedicated preview view. Reuse the native action/modal
  lifecycle. Avoid its direct unvalidated HTML rendering and adapt it to the
  existing PodText validator, presenters, and escaped public Blade cards.
- **Custom Table Field With Product Picker Modal** — a read-only slide-over
  removes its submit action and isolates selection. Reuse the read-only action
  behavior. Avoid adding another Livewire table or model collection to the
  editor state.
- **Doctor Availability and Blocked-Time Scheduling** — a custom page owns
  normal Filament page actions and schemas. Reuse ordinary page-action
  composition. Avoid its public Eloquent-model properties and uncapped option
  queries.
- **Two-column custom page examples** — responsive page composition is viable
  in the custom page Blade. PodText will use one editor region plus one
  bounded logical-end preview region, not a second schema.

## Verified implementation seams

### Shared draft normalization

`CardTemplateFocusedWriter` currently owns the exact Builder transport cleanup.
Extract that logic into a stateless `CardTemplateDraftNormalizer` and inject it
back into the writer. The writer retains candidate-count checks, stored-state
guards, reference scans, persistence, and lifecycle ownership.

The previewer passes exactly one normalized candidate through the existing
validator and constructs `PublicFrontCardTemplate` only after successful
validation. Invalid, zero, or multiple results never fall back to configured
templates.

### Preview-local public context

Ordinary public query helpers resolve settings-backed selector/aggregate
services from the container. Add narrow optional dependencies to those helper
methods so preview can supply an in-memory policy, selector, and aggregates.
Ordinary callers keep their existing default behavior.

The preview composer constructs a registry-default `PublicFrontRenderContext`
directly. It does not resolve the configured settings reader, configured Card
Template list, settings-backed card options, or focused writer.

### Compact Livewire state

Presenter output contains Eloquent models and therefore cannot be stored in
public Livewire state. Each accepted refresh will render the trusted existing
Blade card on the server and retain only:

- locked scalar family and sample ID;
- locked bounded sample label;
- locked preview status and refreshed timestamp; and
- locked server-rendered card HTML.

The HTML is produced only by application-owned escaped Blade components. It is
never accepted from the browser and remains subject to the Step 5B serialized
state delta measurement.

### Selector and performance interpretation

The approved audit resolves the specification's selector/control wording as
follows: the selector is an isolated preview-shell Filament action control, not
part of the editor draft subtree. Preservation assertions continue to require
zero new editor draft controls, wrappers, or `wire:model` paths.

The zero-settings-read condition applies to preview establishment and refresh,
not the editor's existing initial settings snapshot needed to populate the
single draft.

### Inert public rendering

Existing public card and part components gain an explicit `previewMode`
property. In preview mode they retain visible public-card structure while
omitting `href`, `wire:click`, submission behavior, and tab stops. Props are
forwarded explicitly across nested components; no blanket click capture is the
only safeguard.

## Bounded implementation tasks

### Task 1 — preview pipeline and public-card parity

1. Extract and regression-test `CardTemplateDraftNormalizer`.
2. Add the focused `CardTemplatePreviewer` with explicit dependencies.
3. Add preview-local query seams and deterministic one-record/sample-search
   queries for all three families.
4. Add explicit inert preview mode to the existing public card components.
5. Test normalization parity, invalid draft behavior, public visibility,
   deterministic ordering, bounded search, eager loading, rendering parity,
   and zero query work during Blade rendering.

### Task 2 — editor interaction and evidence

1. Add locked compact preview state and explicit refresh actions to
   `CardTemplateEditorPage` without adding another schema or draft.
2. Add the `xl` adjacent shell and below-`xl` read-only slide-over with one
   active preview root.
3. Add family-change refresh, transient sample selection, freshness/loading/
   error/restricted states, focus behavior, and bilingual labels.
4. Extend SP3C canaries without changing frozen ceilings.
5. Add browser acceptance and record supported DOM, focusable, network,
   listener, heap, and timing observations; state unsupported metrics plainly.

## Planned verification

Focused iteration will run the smallest affected Pest files sequentially.
HTTP-touching tests will use `Http::preventStrayRequests()` and owned fixtures;
no live network, live mail, or local development database probe is permitted.

The final gate is run only after the final code and documentation state:

1. requirements sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last.

After any later file change, restart the final gate from Pint. The final handoff
will record every run, including failures, and provide numbered imperative
Local Front Check operator steps.

## Stop conditions

Return to Laravel Simplifier Stage 1 if implementation requires a migration,
dependency, persistent model, new settings path, new permission/security
boundary, generalized preview subsystem, more than two bounded tasks, or a
material effort expansion beyond the approved forecast.
