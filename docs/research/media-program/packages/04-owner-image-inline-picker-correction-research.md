# Package 4 Correction Research — Inline Owner Picker Tabs

## Authority and baseline

- Audit: `LS-20260724-PODTEXT-MEDIA-OWNER-PICKER-CORRECTIONS-01`
- Approved option: `MEDIA-OWNER-CORR-O3-INLINE-PICKER-TABS`
- Status: complete locally; implementation hash is recorded in the correction
  handoff
- Stage 2 approval date: 2026-07-24
- Starting checkout: clean `main` at
  `75249da2c6de7dcdc82cd938d2a722449d87aa47`, 25 commits ahead of
  `origin/main`
- Package 4 implementation and hash stamp:
  `52875222916558542cfde19f8a1987b78e72c121` and
  `75249da2c6de7dcdc82cd938d2a722449d87aa47`
- No prompt under `prompts/pre-13-prompts/` is active.

This correction is limited to the approved owner-image picker topology,
acquisition-only batch upload, visible standalone Media Library batch upload,
tests, documentation and local closeout. Package 5, broader Storage discovery,
dependencies, production and push remain excluded.

## Local database activation

The Package 3 acquisition mechanism and its settings migration were already
built and committed. Package 3 deliberately excluded migrations and local
data actions, so only environment activation was pending.

Under separate backup-first local-database approval, this run:

1. verified the local MySQL database was `podtext`;
2. created and verified a recoverable mode-0600 backup;
3. found exactly the three approved migrations pending;
4. applied the Package 3 settings migration and two Media Asset relational
   migrations in migration order;
5. ran the Curator converter in report mode, apply mode and report mode again.

The settled report contains 15 bound Media rows, zero unbound rows, and zero
missing-file, duplicate-path, unresolved-owner or unresolved-settings
diagnostics. This was activation of committed work, not a new Package 4 schema
change.

## Current behavior and confirmed correction gaps

- `ContentImageActions` owns one reusable podcast/episode image action across
  the six Package 4 surfaces.
- Its detail Blade view is currently passed through `modalContent()`, so the
  effective image and metadata appear before the replacement field.
- `MediaPickerField` renders a selected-item summary and a button. The button
  opens another Filament action modal containing the completed
  `MediaPickerPanel`.
- `MediaPickerPanel` already provides Gallery, Upload, URL and Storage,
  mutation-free gallery choice, immediate permanent acquisition, busy/offline
  guards, safe selection authorization and stable child-action ownership.
- The owner field is deliberately single-select. That also configures its
  `FileUpload` as single, so the owner workflow exposes no acquisition-only
  multi-upload path.
- The standalone Media Resource already admits a bounded batch through
  `CreateMedia`, but its generic Create trigger does not make that capability
  obvious.
- The Storage source already lists bounded direct children of configured
  roots and distinguishes unconfigured, empty, search-empty and load-failure
  states. A general directory browser is Package 5 and remains out of scope.

## Selected topology

```text
owner Resource/Page Livewire component
└── one ContentImageActions modal or configured slide-over
    └── Filament schema Tabs
        ├── Replace Image (first/default)
        │   ├── trusted single owner-identity field
        │   └── schema-owned MediaPickerPanel
        │       ├── Gallery
        │       ├── Upload, including acquisition-only batch
        │       ├── URL
        │       ├── Storage
        │       └── existing picker-owned item actions
        └── Details and Effective Image
            └── existing presentation-only Blade view
```

The picker-launch action is omitted only in this inline owner context. Other
forms continue to use the established picker modal. The outer owner action
remains the only owner modal and the picker remains a schema-owned Livewire
child. No duplicate action-modal partial, new provider, new picker or nested
HTML form is introduced.

## Selection and acquisition rules

- Owner attachment cardinality remains exactly one image.
- Existing gallery selection remains mutation-free.
- Single Upload, URL and Storage acquisition can select the acquired Media
  row for the pending owner action.
- In the inline owner context, Upload accepts one or more files up to the
  existing configured batch limit.
- A multi-file upload permanently admits every successful file, reloads the
  gallery and selects no arbitrary acquired item. The operator explicitly
  chooses one owner image.
- Cancelling the owner action leaves the attachment unchanged and never
  deletes a completed acquisition.
- Outer submit continues to use the trusted field, expected owner identity
  and diagnostic fingerprint. Stale replacement and repair behavior do not
  move into the Livewire child.

## Modal and action ownership

- Replace, details, copy, safe preview/download, Media review, remove direct
  image, automatic fallback and external import need no additional owner
  modal.
- Copy remains local Alpine behavior.
- Safe preview/download and Media review remain links/actions to existing
  authorized routes.
- Remove, repair and external import remain root modal submissions.
- Picker-owned edit/swap/delete confirmation actions keep the already-tested
  child Livewire action owner and teleported modal behavior. They do not add a
  form wrapper to the outer action.

## Performance and accessibility

- No new table query, eager load, storage scan or per-row serialized metadata
  is required.
- The picker continues to load its bounded first gallery page only when the
  owner action opens. Storage candidates remain lazy until the Storage source
  is activated.
- Inline mode removes one action-modal mount/unmount round trip.
- The selected owner identity remains a small scalar in outer action state.
  Acquired gallery projections remain bounded by existing browse/search
  limits.
- The first tab is keyboard and touch reachable and is the default.
  The second tab provides the existing bounded effective preview and
  metadata. Hebrew RTL, English LTR, focus, busy/offline and narrow-screen
  contracts remain mandatory.
- Inline mode suppresses the picker-only Close button because the outer modal
  owns closing and focus restoration.

## Standalone Media Library

Keep the existing `CreateMedia` batch admission and make it explicit through:

- a translated Upload Images header action with an upload icon;
- translated batch-capable field/help copy;
- existing maximum-file and maximum-parallel-upload limits;
- existing bulk-upload authorization and partial-success behavior.

No second standalone uploader is required.

## Research record

- Laravel Boost application information: installed-version proof for Laravel
  13.21.1, Filament 5.7.3, Livewire 4.3.3, Boost 2.4.13 and Pest 4.7.5.
- Laravel Boost documentation search: installed-version guidance for schema
  Tabs, schema Views, schema-owned Livewire components, action schemas,
  FileUpload multiple state and Livewire child ownership.
- Installed Filament 5.7.3 source: full source evidence that Tabs default to
  the first tab, schema Livewire data accepts closures, exposed schema methods
  can bridge trusted field state and FileUpload supports bounded multiple
  uploads.
- FilamentExamples: multiple short searches followed by refined searches.
  The server exposed paths and snippets only; no full-source/details reader
  was available.
- Repository form UX and performance skills: applied to tab order, hidden
  identity state, helper copy, modal ownership, serialized state, lazy
  Storage loading and narrow-screen behavior.

## Boundaries

No new relational/settings migration, Composer/npm dependency, provider,
filesystem journal, cache protocol or queue protocol is needed. The correction
does not move, rename, normalize, replace, edit or delete media bytes. Existing
authorization and public-delivery boundaries are preserved; this is not a
dedicated security audit.
