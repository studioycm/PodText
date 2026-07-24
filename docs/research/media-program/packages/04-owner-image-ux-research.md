# Package 4 Research — Post-P3 Owner Image UX

## Authority and baseline

- Audit: `LS-20260724-PODTEXT-MEDIA-P4-POSTP3-OWNER-UX-01`
- Approved option:
  `MEDIA-P4-POSTP3-O1-INTEGRATED-IMAGE-WORKSPACE`
- Status: complete locally as `52875222916558542cfde19f8a1987b78e72c121`
- Approved on 2026-07-24 from clean `main` at
  `abd5e11b1e8db6cedd8e673a246711698fde3c5f`, 23 commits ahead of
  `origin/main`.
- Package 3, its post-acquisition correction, and the dependency/Resend/
  Fontaine run are complete and hash-stamped.
- The earlier Package 4 audit and both of its options are superseded and
  authorize nothing.
- No prompt under `prompts/pre-13-prompts/` is active.

The approved run is Package 4 plus the separately estimated Resource-table
record-action rider. Composer/npm refreshes, dependency changes, Package 5,
schema changes, live data, production actions and push are excluded.

## Current behavior

- Podcast, episode and podcast-episode relation tables already render compact
  effective-image columns.
- Podcast edit, classic episode edit and episode workspace already expose the
  same `ContentImageActions` Add/Replace Image factory.
- That action already embeds the completed four-source `MediaPickerField` /
  `MediaPickerPanel`; Gallery is mutation-free, while completed Upload, URL
  and Storage acquisitions are permanent before owner attachment.
- The picker has one outer Filament action-modal owner, no form wrapper and
  one schema-owned Livewire child. Its upload lifecycle, direct single choice,
  explicit multi-choice, busy/offline guard, stable opener, focus restoration,
  RTL/LTR and nested child-action behavior are browser-proven.
- Owner list queries already eager-load the direct attachment, Media row,
  legacy diagnostic rows and inherited podcast cover needed by the effective
  image resolver.
- `AdminMediaFileController` already provides authenticated, policy-checked,
  freshly resolved preview and download responses. It rechecks disk/file
  existence, prevents unsafe inline SVG delivery and never exposes a raw
  storage URL.
- `MediaAttachmentManager` owns transactional attachment writes.
  `LegacyOwnerMediaRepairer` already provides fingerprint-guarded repair for
  unsafe owner state.

## Confirmed gaps

1. The current action modal shows only a small direct-Media preview and title.
   It returns no content for unsafe or unresolved owner identity.
2. It does not identify whether the effective image is direct Media, an
   external URL, an inherited podcast cover, a configured fallback or a
   fallback displayed because the direct association cannot be delivered.
3. It does not expose original filename versus stored basename, MIME/
   extension, dimensions, size, directory/disk, useful portable identity,
   safe download/review links or copy feedback.
4. Table thumbnails do not open image detail, and no bounded hover preview is
   configured.
5. Normal Add/Replace captures no mount-time expected owner identity.
   A concurrent attachment change can therefore be overwritten. Normal direct
   removal has no expected-identity API.
6. Broken direct associations do not show the effective fallback separately
   from their warning and bounded replace/detach/review choices.
7. The 43 ungrouped Resource/RelationManager record actions remain mixed
   visible-label buttons. They are spread across 12 surfaces. The Settings
   Backups table is a thirteenth surface with one ActionGroup containing six
   visibly labelled actions and must remain grouped.

## Selected architecture

Use one integrated owner-image action factory and one lazy, read-only
presenter:

- The root action displays effective preview, source truth, canonical metadata,
  warning/diagnostic state and safe links.
- Its existing `MediaPickerField` is the only Change Image path.
- The root action remains the only owner action form. The completed picker is
  the only child modal and keeps `formWrapper(false)`.
- Copy is local Alpine clipboard behavior with visible live feedback.
- Preview, download and Media review are safe links.
- Remove Direct Image / Use Automatic Image and external-image import are
  parent modal-submit operations with arguments. They do not open another
  confirmation modal.
- Normal replace/remove carries the expected direct Media ID and compatibility
  path captured on mount and rechecks them under the existing database locks.
  Unsafe repair continues to use the existing diagnostic fingerprint.

The presenter is deliberately separate from `MediaRecordProjector`.
Expanding the picker projector would serialize detail-only metadata for every
gallery tile and increase Livewire state.

## Source and metadata rules

- `media_attachments.media_id` remains direct owner authority.
- Curator `path` remains current file-location authority.
- The effective preview follows the existing D01 delivery order and fallback
  rules exactly.
- Direct association truth and effective preview truth are presented
  separately. A direct association can therefore remain visible as broken
  while the operator sees the external, inherited or configured fallback.
- A canonical Media row is freshly loaded only when the detail action opens.
  Original filename is read from `exif.original_filename`; stored filename is
  `basename(path)` and is labelled separately.
- Displayed metadata is a point-in-time snapshot. Safe preview/download routes
  resolve the current row and file again. Package 4 does not hash bytes or
  invent a file fingerprint.
- External URLs are labelled as external and never presented as Media rows.

## Modal and Livewire topology

```text
Owner page/table Livewire component
└── integrated owner-image root action (modal or configured slide-over)
    ├── lazy detail Blade view (no queries)
    ├── local copy + safe URL actions (no modal)
    ├── root submit: save/change, remove, or import external
    └── existing MediaPickerField action
        └── schema-owned MediaPickerPanel child
            └── existing picker-owned child actions
```

No second picker, new owner-image Livewire component, duplicate action-modal
partial or nested HTML form is required.

## Performance evidence

- Keep current table eager loads; do not add provider bindings, filesystem
  diagnostics or detail metadata to list projections.
- Existing compact thumbnail state remains part of the current table render.
- Bounded tooltip markup uses the same thumbnail URL and lazy image loading;
  maximum dimensions are 300 by 300 pixels.
- Detail-only Media lookup, file-existence/SVG check and metadata projection
  happen after action mount, not once per row.
- The action form serializes only picker identity, unsafe fingerprint and a
  small expected-owner snapshot. It does not serialize a Media model or
  metadata payload.

## Resource-table action inventory

Verified ungrouped record actions:

| Surface | Count |
|---|---:|
| Authors | 1 |
| Categories | 1 |
| Content Groups | 4 |
| Content Items | 8 |
| Podcast Content Items relation manager | 10 |
| Episode Transcriptions relation manager | 4 |
| Content Tags | 1 |
| Homepage Sections | 1 |
| Media | 6 |
| Public Form Submissions | 4 |
| Transcriptions | 2 |
| Users | 1 |
| **Total** | **43** |

Use a bounded `ResourceTableActions` configurator only from those 12 Resource
or RelationManager table builders. It calls the installed Filament 5.7.3
`modifyUngroupedRecordActionsUsing()` API and retains the evaluated translated
label as both hidden accessible name and tooltip. A non-null semantic
`Heroicon` is required before converting an action to `iconButton()`.

Do not install this through `Table::configureUsing()`: a global callback could
change custom Page tables outside the rider. Keep the Settings Backups
ActionGroup grouped with visible labels inside its menu. Header, toolbar,
bulk, empty-state, page-header, form suffix/hint and modal-footer actions are
excluded.

## Research record

- Laravel Boost `application-info`: installed-version proof for Laravel
  13.21.1, Filament 5.7.3, Livewire 4.3.3, Boost 2.4.13 and Pest 4.7.5.
- Laravel Boost `search-docs`: installed-version guidance for column actions,
  record actions, modal/slide-over APIs, child action modals and Livewire child
  ownership.
- Installed Filament 5.7.3 source: full source proof for
  `iconButton()`, hidden labels, tooltip evaluation,
  `modifyUngroupedRecordActionsUsing()`, column click
  `wire:click.prevent.stop`, modal submit arguments and parent/child action
  behavior.
- FilamentExamples: two multi-query/refinement passes. Results exposed names,
  paths and code snippets for modal footer actions, image preview actions,
  clipboard feedback and record actions. No separate source/detail reader was
  available, so this is honestly classified as search/snippet evidence.
- Repository Filament form UX and performance skills: checklists applied to
  modal ownership, localization, narrow screens, serialized state, query
  shape and lazy detail work.
- Official release/changelog research from Stage 1 found no Package 4
  dependency prerequisite. The completed dependency/Boost/Fontaine work is
  baseline, not Package 4 effort.

## Boundaries

No relational/settings migration, Composer/npm dependency, provider, cache,
queue, storage scan or filesystem journal is needed. Package 4 does not move,
rename, replace, normalize, optimize, crop, edit, trash, restore or purge media
files. Existing authorization and admin/public delivery boundaries are reused;
this is not a dedicated security audit.
