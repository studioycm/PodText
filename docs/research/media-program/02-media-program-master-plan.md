# Inventory-First Media Program Master Plan

## Controlling route

Package 1 closed under audit `LS-20260723-MEDIA-INVENTORY-FIRST-RESET-01`.
Package 2 is implemented and hash-stamped under audit
`LS-20260723-PODTEXT-MEDIA-P2-INVENTORY-PICKER-REPLACE-01`.
Package 3 is implemented and hash-stamped under audit
`LS-20260723-PODTEXT-MEDIA-P3-ACQUISITION-PICKER-01`, approved option
`MEDIA-P3-O1-IMMEDIATE-SHARED-ADMISSION`. Its post-acquisition picker
correction is complete locally under audit
`LS-20260723-PODTEXT-MEDIA-P3-POST-ACQUISITION-UX-01`, approved option
`MEDIA-P3-POST-O3-IMMEDIATE-SOURCE-WORKSPACE`; its handoff is
`../../phase-02/media-program-p3-post-acquisition-picker-ux-handoff.md`.
Package 4 is complete locally under audit
`LS-20260724-PODTEXT-MEDIA-P4-POSTP3-OWNER-UX-01`, approved option
`MEDIA-P4-POSTP3-O1-INTEGRATED-IMAGE-WORKSPACE`; its handoff is
`../../phase-02/media-program-p4-owner-image-ux-handoff.md` and its
implementation is `52875222916558542cfde19f8a1987b78e72c121`. Its owner-picker
topology correction is complete locally under audit
`LS-20260724-PODTEXT-MEDIA-OWNER-PICKER-CORRECTIONS-01`, approved option
`MEDIA-OWNER-CORR-O3-INLINE-PICKER-TABS`; its handoff is
`../../phase-02/media-program-p4-inline-picker-correction-handoff.md`.
Package 5 remains a route forecast requiring fresh Simplifier approval.

## Package 1 — minimal kernel and database conversion

Outcome: every Curator row has one portable MediaAsset and Curator binding;
numeric owner relationships remain authoritative and no file is mutated.

Tasks:

1. Rebase canonical documents and record the unfinished handoff invalid.
2. Remove only the attributable overbuilt draft, preserving rebased docs.
3. Test-first minimal schema/models, attachment bridge and one idempotent
   conversion command.
4. Close with production-shaped fixtures, ordered gates, local implementation
   commit and immediate docs hash stamp.

Schema:

- `media_assets`: ID, immutable unique ULID reference key, timestamps.
- `media_provider_bindings`: asset FK, provider, provider record key,
  timestamps, unique provider-record and asset-provider pairs.
- nullable `media_attachments.media_asset_id`, retaining `media_id`.

The converter preserves IDs, paths and metadata; reuses or generates keys;
creates assets/bindings; bridges authoritative attachments; resolves unique
legacy paths/settings; reports missing files, duplicate paths and unresolved
owners; and commits database changes transactionally. It has no byte,
normalization, hashing, relocation, quarantine, manifest or journal behavior.

## Package 2 — inventory, repair diagnostics and selection

Outcome: every Media row is visible in All Media; Needs Repair is a filter;
attachment authority and D01 are correct; picker All Media clears its initial
logical filter.

This package removes strict existing-row eligibility from Resource/picker and
public resolution while retaining delivery/access/SVG-inline boundaries.
It also reuses the Gallery/Upload picker for always-visible, same-page
podcast/episode Add/Replace Image actions. Package 2 is complete locally;
its implementation is hash-stamped as
`2a6de67816b9a7c8e53bcd29795a5b306a36dbaf`.

## Package 3 — four-source acquisition

Outcome: Gallery, Upload, URL and Storage share one new-input admission path;
Spotify supplies URLs. MIME/extension/limits apply to new input, SVG sanitation
is reusable, and raster normalization/checksum is optional rather than
mandatory.

Package 3 preserves immediate library writes: Upload, URL and Storage are
permanent when acquisition succeeds, while Gallery remains mutation-free
selection. One common boundary validates and admits source bytes, then creates
the Curator row, MediaAsset and provider binding atomically. Owner attachment
continues through `MediaAttachmentManager`.

Tasks:

1. Reconcile the five controlling Package 3 documents before PHP.
2. Test-first correct byte-preserving validation, add bounded settings/naming,
   and build atomic shared admission.
3. Test-first extend the current picker with URL and opaque configured Storage,
   then route queued external/Spotify paths through the same boundary.
4. Close with the Package 3 requirements sweep, ordered gates, local
   implementation commit and immediate hash stamp.

Package 3 may add one Spatie settings migration. It adds no relational schema,
provider, dependency, filesystem scanning or physical lifecycle feature.

Package 3 is complete locally. Its implementation is hash-stamped as
`656a7c2ed1b64b3f6fd8392bff88f7cca36d2695`.

## Post-Package-3 acquisition picker correction

Outcome: preserve immediate permanent acquisition and pending owner attachment
while correcting single-mode batch admission, partial-result truth, Storage
error/concurrency behavior, URL deadline/error copy and the picker interaction.
Gallery remains primary; Upload, URL and Storage become an on-demand source
workspace with accessible, truthful busy/offline/selection behavior.

Tasks:

1. Write durable post-P3 research/plan and reconcile active route documents.
2. Test-first correct mode guards, batch results, Storage dispositions/locking
   and Media Resource compatibility.
3. Test-first add one URL deadline and safe localized failure categories.
4. Test-first add direct single acquisition, source workspace, lazy Storage,
   truthful feedback and guarded busy/offline/dismissal behavior.
5. Complete accessibility/browser regressions, requirements sweep, ordered
   gates, handoff, implementation commit and immediate hash stamp.

The correction adds no migration, relational schema, cached inventory, queue
or new persistence lifecycle. Its only dependency change is the operator's
later explicit bounded Filament 5.7.1 to 5.7.3 patch amendment; manifests, npm
and unrelated packages remain unchanged. Its detailed authority is
`packages/03-post-acquisition-picker-ux-research.md` and
`packages/03-post-acquisition-picker-ux-plan.md`; its completed result is
recorded in
`../../phase-02/media-program-p3-post-acquisition-picker-ux-handoff.md`.

## Package 4 — owner image UX

Outcome: podcast/episode list and detail surfaces provide bounded preview,
metadata, safe download, copy filename, change image and broken-association
repair/default actions.

Tasks:

1. Reconcile active Package 4 route documents.
2. Test-first add a lazy effective-source/metadata presenter and stale-safe
   normal replace/remove attachment mutations.
3. Integrate one owner-image workspace across all six table/edit/workspace
   surfaces while reusing the completed picker and safe delivery routes.
4. Convert the verified 43 ungrouped Resource/RelationManager record actions
   to accessible icon-only triggers through an explicitly applied local
   configurator; retain the Settings Backups ActionGroup.
5. Complete focused, performance, localization and browser regressions, then
   the ordered gates, handoff, implementation commit and immediate hash stamp.

Package 4 adds no schema, dependency, provider, new Livewire picker, storage
scan, filesystem mutation or byte fingerprint. Its detailed authority is
`packages/04-owner-image-ux-research.md` and
`packages/04-owner-image-ux-plan.md`.

Package 4 is complete locally. It preserves the post-Package-3 picker and
attachment contracts, adds the integrated six-surface owner-image workspace,
and completes the bounded 43-action Resource-table rider.

## Package 4 owner-picker correction

Outcome: the complete four-source picker is visible directly in the
first/default Replace Image tab of the existing owner action, with Details and
Effective Image second and no additional picker modal.

Tasks:

1. Activate only the already-committed Package 3 settings and Media Asset
   migrations under separate backup-first local approval, then settle the
   database-only Curator conversion.
2. Test-first move the existing schema-owned `MediaPickerPanel` inline while
   preserving the trusted field bridge, stale owner evidence, one outer action
   owner and all six Package 4 surfaces.
3. Test-first allow bounded acquisition-only owner multi-upload: admit all
   successes permanently, choose no arbitrary owner image and require one
   explicit gallery choice.
4. Make the existing standalone Media Resource batch admission explicit
   without adding another uploader.
5. Close with localization, Storage-state, busy/offline, focus, RTL/LTR,
   narrow-screen and cancellation regressions, ordered gates, handoff,
   implementation commit and immediate hash stamp.

The correction adds no migration or dependency of its own. Storage continues
to browse only configured bounded roots and broader discovery remains Package
5. The original Package 4 source detail, diagnostics, safe delivery,
stale-write protection and Resource-table rider remain unchanged.

## Package 5 — files and physical lifecycle

Outcome: managed rowless files appear in Files Discovery; explicit import,
move, rename, replace, trash, restore and purge use containment, reference
protection and durable mutation journals.

## Verification and commits

Every package is serial and test-first. Final order is requirements/drift
sweep, Pint, FilaCheck, Vite build and full test suite last. Any subsequent
change restarts at Pint. Package closeout uses an implementation commit followed
immediately by a docs-only handoff/ledger hash stamp. No push occurs.

## Program exclusions

No Package 4 dependency/toolchain change, one-shot five-package
implementation, dedicated security-audit phase, unapproved local/production
live action, compatibility-field removal or parallel repository writers.
