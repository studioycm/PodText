# Package 4 Plan — Integrated Owner Image Workspace

## Approval

- Audit: `LS-20260724-PODTEXT-MEDIA-P4-POSTP3-OWNER-UX-01`
- Selected option:
  `MEDIA-P4-POSTP3-O1-INTEGRATED-IMAGE-WORKSPACE`
- Status: complete locally as `52875222916558542cfde19f8a1987b78e72c121`
- Stage 2 approval date: 2026-07-24
- Starting baseline: clean `main` at
  `abd5e11b1e8db6cedd8e673a246711698fde3c5f`, 23 commits ahead of
  `origin/main`
- Scope: Package 4 and the independently estimated Resource-table
  record-action rider only
- Excluded: Composer/npm/toolchain updates, Package 5 and every deferred item

## Commands

No generator, migration or dependency command is required. Work is test-first
against isolated test databases, `Storage::fake()` and committed HTTP
fixtures. Final gates run sequentially in repository order.

## Models and schema

No model, relationship, column, index, migration or settings shape changes.

Authority remains:

- owner relationship: `media_attachments.media_id`;
- current file location: Curator `path`;
- portable identity: MediaAsset/reference key;
- owner/settings paths: compatibility mirrors.

## Mini-task 1 — reconcile active package documents

Update the context helper, requirements registry, master plan, Package 4
research/plan, supersession map, current state and ledger before PHP changes.
Record the new audit/option and preserve the post-P3 contracts.

## Mini-task 2 — lazy presenter and stale-safe mutations

### Presenter

Create `App\Support\Media\OwnerImagePresenter`.

- Location:
  `app/Support/Media/OwnerImagePresenter.php`
- Behavior:
  - freshly load the owner and only the relationships needed for its direct
    identity, diagnostic and inherited podcast cover;
  - resolve the effective preview through
    `App\Support\PublicFront\PublicDefaultImageResolver`;
  - separately report direct association state and effective source;
  - identify direct Media, compatibility Media, external URL, inherited
    podcast cover, configured family/global fallback, empty fallback and a
    broken direct association displaying one of those fallbacks;
  - project original filename, stored basename, MIME/extension, dimensions,
    human-readable size, directory, disk, optional reference key and
    day-first Jerusalem update time;
  - expose only authenticated admin preview/download routes and Resource URLs;
  - expose translated diagnostic reason keys without raw paths, exceptions or
    storage URLs;
  - memoize only within the current request/action render.

The Blade view performs no queries.

### Mutation APIs

Modify `App\Support\Media\MediaAttachmentManager`.

- Add a direct detach path that accepts expected Media ID and expected
  compatibility path.
- Lock the owner, role attachment and current Media row in the existing
  transaction before comparison.
- Fail closed when either value changed.
- Preserve existing policy authorization and mutation fence checks.
- Delete only the attachment and clear only the compatibility mirror.

Modify `App\Support\Media\MediaAttachmentFormState`.

- Allow the action path to pass and enforce the expected direct identity for
  normal replacement/removal.
- Resolve the target through the current inventory/selection boundary.
- Convert stale-operation failure into localized action validation feedback.
- Keep unsafe repair on `LegacyOwnerMediaRepairer` and its diagnostic
  fingerprint.
- Do not change unrelated owner-form save semantics.

## Mini-task 3 — integrated owner-image action and six surfaces

### Action

**Action**: `Filament\Actions\Action` produced by
`App\Filament\Actions\ContentImageActions`

- Docs:
  <https://github.com/filamentphp/filament/blob/5.x/packages/actions/docs/02-modals.md>
- Locations:
  - podcast Resource table;
  - episode Resource table;
  - podcast ContentItems relation manager;
  - podcast edit header;
  - classic episode edit header;
  - episode workspace header.
- Visibility/authorization: preserve current action visibility; mutation paths
  reauthorize through existing Media policies/services.
- Behavior:
  1. On mount, lazily present effective source, metadata and diagnostics.
  2. Capture picker identity, unsafe fingerprint, expected direct Media ID and
     expected compatibility path as small form state.
  3. Render `MediaPickerField` as the only Change Image mechanism.
  4. Default submit persists the selected replacement stale-safely.
  5. A modal submit argument removes a normal direct association or invokes
     fingerprint-guarded unsafe detach, then uses automatic fallback.
  6. For an unattached episode external URL, a modal submit argument reuses the
     existing queued import action path.
  7. Cancel/close performs no attachment mutation.

The configured `AdminUxSettings` modal/slide-over choice remains intact. The
existing picker is the only child modal, keeps its schema-owned Livewire
component and keeps `formWrapper(false)`.

### Detail view

Create/replace
`resources/views/filament/actions/current-content-image.blade.php`.

- Maximum preview size: 300 by 300 pixels.
- Show effective source independently from any direct-association warning.
- Clearly label original filename and stored filename.
- Copy buttons use local Alpine clipboard behavior and a visible `aria-live`
  success message.
- Preview/download/review links use only presenter-provided safe URLs.
- Keep Hebrew-first RTL and narrow-screen stacking; metadata values that are
  naturally LTR use `dir="ltr"`.

### Table columns

**Column**: `Filament\Tables\Columns\ImageColumn`

- Docs:
  <https://github.com/filamentphp/filament/blob/5.x/packages/tables/docs/02-columns/03-image.md>
- Config:
  - retain compact 48-pixel square thumbnails;
  - attach a distinct integrated detail action to each image column so
    `wire:click.prevent.stop` prevents record navigation;
  - use one translated bounded-preview tooltip view with a lazily loaded image
    and 300 by 300 maximum;
  - click, Enter/Space, touch and modal content remain the authoritative
    accessible path; hover preview is supplemental.

Affected tables:

- `ContentGroupsTable`;
- `ContentItemsTable`;
- `ContentItemsRelationManager`.

## Mini-task 4 — Resource-table record-action rider

Create `App\Filament\Resources\Support\ResourceTableActions`.

**Action configuration**:
`Filament\Actions\Action`

- Docs:
  <https://github.com/filamentphp/filament/blob/5.x/packages/tables/docs/04-actions.md>
- Location: called explicitly from the 12 audited Resource/RelationManager
  table builders, never globally.
- Visibility/authorization/behavior: unchanged.
- Config:
  - `iconButton()`;
  - `hiddenLabel()`;
  - tooltip equal to the evaluated translated label;
  - reject a null icon before icon-only conversion.

Add a semantic icon only where an existing action lacks one. Keep adjacent
actions distinguishable. Preserve all 43 action names, URLs, colors,
authorization, visibility, confirmation/modal and destructive semantics.

The Settings Backups ActionGroup remains grouped, and its six internal actions
retain visible labels. Custom Page tables and all excluded action locations
remain untouched.

## Authorization

No policy or ability changes.

- Owner pages remain admin-authenticated.
- Preview/download use `AdminMediaFileController`.
- Media review uses existing Resource authorization.
- Attach/detach/select reuse `CuratorMediaPolicy` through the attachment and
  repair services.
- External acquisition reuses the current queued job and its source/owner
  stale checks.

## Tests

### Focused Pest coverage

1. Effective-source matrix: direct, external, inherited podcast cover,
   content-family default, global default and empty fallback.
2. Broken direct matrix: missing Media row, missing file, audience denial and
   unsanitized SVG; fallback preview remains separate from warning.
3. Metadata: original versus stored filename, MIME/extension, dimensions,
   size, directory/disk, reference key, day-first timestamp and safe URLs.
4. Fresh-on-open behavior after Media row/path/metadata changes.
5. Stale normal replace and stale direct remove reject after a concurrent
   attachment/path change and preserve the newer state.
6. Unsafe replace/detach continues to require the diagnostic fingerprint.
7. Unrelated owner saves preserve broken evidence.
8. All six surfaces expose the shared action; all three table thumbnails mount
   it without record navigation.
9. No new per-row detail/storage work and stable list query shape for bounded
   record counts.
10. Rider inventory remains exactly 43 ungrouped actions over 12 surfaces;
    every action has a semantic icon, hidden authoritative label and matching
    tooltip; the Settings Backups group stays grouped and custom Page tables
    stay excluded.
11. Hebrew and English keys stay structurally aligned.

### Browser verification

- Hebrew RTL and English LTR.
- Wide and narrow viewports without horizontal overflow.
- Hover preview is bounded; click/touch and keyboard open details.
- Thumbnail click does not navigate to the record; other row clicks retain
  current navigation.
- Copy success is visible and announced.
- Safe download/review controls work without raw paths.
- Change Image opens the one existing picker child; cancel returns to the
  owner workspace without mutation and focus returns to the stable opener.
- Remove switches to the accurate automatic effective fallback.
- Broken direct state mounts without error and offers replace, detach and
  review where applicable.
- No duplicate modal partial, nested HTML form or JavaScript error.

## Final verification and closeout

Run in this exact order:

1. requirements/drift/source sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last.

After any later file change, restart from Pint and finish the full suite again.
Then create the canonical implementation commit followed immediately by the
docs-only hash-stamp commit. Do not push.

## Explicit exclusions

- Package 5 Files Discovery or physical lifecycle.
- Move, rename, swap, replace bytes, trash, restore, purge or cleanup.
- Image editing, crop, optimization, resizing or normalization.
- New schema, migration, settings property, provider or dependency.
- Composer/npm/toolchain refresh or Boost discovery.
- Public-front redesign.
- Unrelated Package 3 corrections.
- Live/local-development data or storage actions, production actions,
  deployment, worker/process actions and push.
