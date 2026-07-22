# PodText Media Asset Program Requirements, Decisions, and Working Method

## Authority and status

- Audit: `LS-20260722-PODTEXT-MEDIA-ASSET-PROGRAM-06`
- Approved option:
  `MEDIA-CUTOVER-O1-DIRECT-ASSET-HYBRID-ROOT-MAINTENANCE`
- Approval date: 2026-07-22
- Execution: documentation Gate 0 followed by five sequential packages in the
  existing checkout.
- Current controlling context: `docs/phase-02/media-program-context.md`.
- No file under `prompts/pre-13-prompts/` is active. This is an approved ad-hoc
  program.

This registry is the durable record of the operator's final requirements and
the decisions that survived the conversation. It is not a transcript. Earlier
ideas remain useful only where a row below explicitly retains them.

## Context-restoration protocol

After an automatic context compaction, a resumed task, or any uncertainty about
the current package, do not rely on the compressed conversation alone. Read in
this order:

1. `AGENTS.md` and the mandatory orientation documents.
2. `docs/phase-02/media-program-context.md`.
3. This requirements and decisions registry.
4. `docs/research/media-program/01-media-program-research.md`.
5. `docs/research/media-program/02-media-program-master-plan.md`.
6. The active package research, plan, and latest handoff linked by the context
   helper.
7. The current Git status, source, migrations, installed vendor source, and
   relevant tests.

The compressed conversation is a hint for what to verify, not authority for a
fact that can drift.

## Working method

Use this loop for each package and important micro-subject:

1. State one exact question or invariant.
2. Inspect current application source and tests that own it.
3. Inspect installed vendor source and installed-version documentation when a
   framework or plugin contract matters.
4. Inspect schema and real-data facts read-only only when the micro-subject
   actually depends on them. Never replace evidence with an assumption.
5. Separate observed fact, inference, operator decision, and unresolved risk.
6. Compare the smallest coherent choices against this registry.
7. Write a failing production-shaped test before implementation code.
8. Implement the smallest complete state transition, including failure and
   retry behavior.
9. Recheck the focused tests, diff, authorization, translations, query/state
   cost, and mutation boundaries.
10. Update the context helper, master plan, active package docs, and handoff so
    the next micro-subject does not depend on chat memory.

Tool use must be concurrent with the small subject being decided, not a single
up-front ceremony. Repeated tool use does not authorize repeated live database,
storage, cache, or production mutation.

## Final requirements and decisions

| ID | Final requirement or decision | Owner / proof |
|---|---|---|
| `MP-R001` | PodText owns one minimal canonical `MediaAsset` kernel. Curator is the first and only installed provider, not PodText's portable identity or policy owner. | Package 1 schema, models, provider adapter, architecture tests. |
| `MP-R002` | Do not install or adopt Spatie Media Library. Its Filament plugin does not supply a benefit that justifies a second media identity/schema for this program. | Dependency sweep and Composer diff guard in every package. |
| `MP-R003` | Do not install a file-manager dependency in this program. A future `mwguerra/filemanager` review may use it only as a physical Files UI; it may never mutate canonical media behind PodText services. | Package 5 app-owned Files Discovery; dependency remains deferred. |
| `MP-R004` | Every Curator row converts to exactly one `MediaAsset` and exactly one Curator provider binding. Duplicate bytes remain separate assets. | Conversion manifest, unique binding constraints, 403-row fixture. |
| `MP-R005` | Preserve the exact Curator numeric ID as provider-local identity. Do not use it as portable identity and do not renumber it. | `media_provider_bindings.provider_record_key`; conversion tests. |
| `MP-R006` | Each asset has one immutable ULID `reference_key`. It is the only portable media identity. During compatibility, `curator.reference_key` may mirror that same key; it must never become a second identity. | Model guards, bridge tests, import/export/settings tests. |
| `MP-R007` | MediaAsset validation and lifecycle status become the trust boundary. Curator metadata plus a non-null key is not enough. | Asset-first scope/resolver and unsafe-key regression. |
| `MP-R008` | A trusted active asset is the only kind ordinary gallery, search, picker, preview, view, download, rename, move, attach, swap, or delete flows may use. | Policy/controllers/resources/security tests. |
| `MP-R009` | A Curator row that cannot become trusted still gets a bound `Needs Repair` asset with exact provenance. It must not remain silently legacy-only. | Conversion closure report and Needs Repair resource. |
| `MP-R010` | Unsafe active associations remain as diagnostic evidence. Read paths refuse the bytes and resolve the configured family default or system fallback; they do not erase the association just to avoid an error. | Asset resolver, owner diagnostics, public/default tests. |
| `MP-R011` | Podcast and episode list/edit/image actions must mount without HTTP 500 when their current media is unsafe. They must offer authorized repair, replacement, or detach-to-default without exposing unsafe bytes. | Packages 2 and 4 Filament/Livewire tests. |
| `MP-R012` | Selecting an existing trusted asset attaches that asset. It never clones, copies, normalizes again, or moves it because of the new owner. | Picker/attachment tests. |
| `MP-R013` | All compatible trusted images must be findable from every image field unless an explicit special compatibility rule is approved. The initial slot folder is only a default filter; one click shows All Media. | MediaAssetPicker query/UX tests. |
| `MP-R014` | A reusable `MediaAssetPicker` owns every image-selection surface. Its four source experiences are Gallery, Upload, URL, and Storage. | Package 3 component and surface-integration inventory. |
| `MP-R015` | Spotify is a contextual URL producer, not a fifth picker source. Podcast and episode create/edit Spotify fetchers may offer image acquisition through the same URL pipeline. | Package 3 Spotify tests on both owner types. |
| `MP-R016` | Native import/export continues to use portable asset keys. It must not fetch remote media inside importer transactions. Network/filesystem acquisition completes before the short relationship transaction. | Importer/exporter and transaction-boundary tests. |
| `MP-R017` | Preserve all podcast, episode, menu/header, default-image, About/team, shared-attachment, settings, legacy-path, and portable import/export relationships during conversion. | Reference inventory, conversion digest, closure fixture. |
| `MP-R018` | Keep `cover_path`, `image_path`, Curator IDs, Curator keys, and settings path/key pairs as compatibility mirrors through all five packages. Removing them requires a later amended audit after closure proof. | Bridge schema and compatibility tests. |
| `MP-R019` | Physical roots and logical folders are different concepts. Physical roots are server-controlled storage placement; logical folders are admin organization and picker filters. Neither is a trust or authorization boundary. | Schema, policy, picker, Files Discovery tests. |
| `MP-R020` | Initial physical placement is hybrid: a single-purpose active row goes to its purpose root; a mixed-purpose or unassigned row goes to `media-library`. Reuse later does not cause a move. | Conversion planner classifications. |
| `MP-R021` | The current purpose roots remain valid initial roots and `media-library` is added for mixed/unassigned conversion. Arbitrary admin text must not become a physical path. A future root change is a code/config policy change with validation. | Physical-path policy tests. |
| `MP-R022` | Logical folders start flat. Six protected system keys are Podcast covers, Episode images, Headers/logos, Defaults, About/team, and Legacy library. Kernel migration A inserts those six rows in deterministic enum order with an idempotent unique-key upsert after creating the folder table and before creating assets. The migration runs once under the migration lock/full-maintenance cutover; down drops bindings, assets, then the folder rows/table. | `MediaFolderSystemKey`, migration/upsert parity, retry, ordering, and reversal tests. |
| `MP-R023` | System folder keys cannot be renamed or deleted in the UI. Labels are translated in Hebrew and English. Admins may reorder them, choose relevant defaults, and control normal-gallery visibility without hiding them from integrity/security tools. | Folder policy/resource/settings tests. |
| `MP-R024` | Admins may add, rename, reorder, show/hide, and delete custom logical folders. Deleting a custom folder atomically moves its assets to Legacy library. | Package 2 folder lifecycle tests. |
| `MP-R025` | An asset has one current logical folder. Logical folder membership does not restrict compatible selection and does not rewrite its physical path. | Model/picker tests. |
| `MP-R026` | The trusted Media gallery is the primary library. Invalid records are absent from it and visible in a separate Needs Repair view with explicit reason, provenance, and permitted repair/revalidation actions. | Package 2 Resource tests. |
| `MP-R027` | Unsafe raw bytes are not ordinarily previewable or downloadable, even from Needs Repair. Any diagnostic display uses metadata only. | Controller/policy tests. |
| `MP-R028` | Valid noncanonical rasters use one reusable journaled normalizer/fixer. A key becomes trusted only after staged output, independent validation, size/dimension/MIME proof, and SHA-256 proof. | Packages 1-2 transition tests. |
| `MP-R029` | SVG sanitation is reusable and staged. A successfully sanitized and validated SVG is a normal trusted image usable in every image slot. Unsanitized or failed SVG stays Needs Repair. | Package 2 safe/malicious SVG tests. |
| `MP-R030` | This implementation may build and test SVG sanitation but must not sanitize real local or production SVG rows, including Curator IDs 6 and 7. | No-real-data command log and handoffs. |
| `MP-R031` | Current 2 MB and 3000x3000 values become admin-adjustable working targets for optimize/downsize UX, not removable security limits. Server-owned absolute hard caps remain finite. | Media settings, validator, hostile-file tests. |
| `MP-R032` | Admin UX defaults are browse 25 per page, search maximum 50, and upload maximum 10 at once. Admin settings may lower/change working defaults within hard server ceilings. | Package 2/3 settings and pagination tests. |
| `MP-R033` | Preserve a cleaned original filename by default. Provide an admin default and per-upload/batch checkbox to use cleaned original names; deterministic collision handling adds a suffix, including Curator ID during conversion. | Naming policy and collision tests. |
| `MP-R034` | The app controls stored filenames and physical roots through policy. “Preserve original” means a sanitized filename stem, never trusting client path, separators, extension, or MIME. | Filename/path security tests. |
| `MP-R035` | Acquisition from Upload, URL, and Storage passes through one image-only pipeline: source validation, positive MIME/extension checks, raster decode/normalization or staged SVG sanitation, checksum proof, MediaAsset creation, Curator provider creation/binding, then optional attachment. Spotify-provided URLs use the URL mechanism and record Spotify only as provenance/origin metadata; they do not create a fifth source path. | Package 3 service and equivalence tests. |
| `MP-R036` | URL/Spotify acquisition retains SSRF protection, redirect and DNS-rebinding controls, time/size/content limits, HTTPS policy, and committed HTTP fixtures with stray requests prevented. | Package 3 security tests. |
| `MP-R037` | Storage source imports one exact discovered file through the same validator/journal. The picker never trusts an arbitrary client path and never bulk-imports an unreviewed directory. | Package 3 signed/opaque candidate tests. |
| `MP-R038` | Files Discovery is a separate physical-files area for filesystem-only/orphan discovery. It excludes cache, staging, quarantine, curations, and non-owned roots. | Package 5 discovery tests. |
| `MP-R039` | Files Discovery actions are explicit Import or Import-and-Use, move, rename, trash, restore, and purge. Every canonical mutation goes through PodText validation, authorization, lease/fence, and durable journal. | Package 5 state-machine tests. |
| `MP-R040` | Logical folder changes are database organization. Physical move/rename is an explicit journaled operation and is never inferred from a folder or attachment change. | Package 2/5 separation tests. |
| `MP-R041` | Trashed assets and displaced originals use private quarantine with a default 90-day retention. Purge requires an elapsed retention date, completed journal, zero owner/settings references, authorization, and an exact fresh plan. | Package 5 trash/purge tests. |
| `MP-R042` | Curations/derived variants may be added later for optimization and oversized files. They must not silently replace the canonical identity. Replacing the canonical file requires the journal and may quarantine the prior original. | Deferred capability boundary; normalizer tests. |
| `MP-R043` | Podcast and episode image columns show a bounded hover preview up to 300px. Click opens a modal or slide-over with image, metadata, safe download, copy filename, and the same reusable change-image action/picker. | Package 4 table/action/browser tests. |
| `MP-R044` | Reuse the same image-detail/change action across ContentGroup Resource, ContentItem Resource, relation/workspace surfaces, and later image-bearing settings where applicable. | Package 4 surface inventory and tests. |
| `MP-R045` | Admin-or-higher remains the authorization boundary. Do not introduce Shield, `HasRoles`, new grants, or a role redesign. Every record ID, asset key, provider ID, Livewire value, path, URL, filename, and candidate token is untrusted input. | Policies, forged-input tests, architecture sweep. |
| `MP-R046` | Curator remains image-only. Positive MIME/extension allowlists, raster normalization, staged SVG sanitation, app-owned public disk for trusted output, and server-owned visibility remain mandatory. | Validation and storage-policy tests. |
| `MP-R047` | Public visibility of a trusted canonical file does not mean a user may discover or mutate it. Authorization is enforced on Resource, action, controller, picker, acquisition, repair, and lifecycle entry points. | Security audit matrix. |
| `MP-R048` | Conversion and lifecycle use copy -> verify -> short database commit -> cleanup. Process failure is resumable; destination conflicts, changed source, stale references, stale manifest, foreign lease, and incomplete cleanup fail closed. | Journal/fence/compensation tests. |
| `MP-R049` | Conversion preflight is schema-aware and deterministic. It binds schema profile, every Curator row/file/reference, source checksum, proposed disposition/root/folder/key, open journal, and excluded filesystem-only count into a canonical digest. Apply requires that exact digest and replans under lock. | Package 1 manifest tests. |
| `MP-R050` | Local and production use separate entry commands/profiles if useful, but one shared planner/executor/state machine. Do not create two divergent repair scripts. | Package 1 command architecture tests. |
| `MP-R051` | Production conversion is full maintenance only. It must preserve every Curator row and relationship, use an exact migration allowlist, and prove closure before normal writes return. No broad `migrate --force`, broad rollback, or guessed disposition. | Future cutover runbook; real action separately approved. |
| `MP-R052` | The production-shaped fixture preserves 403 Curator identities, 108 group covers, 1 image-empty item, 2 header/logo references, 110 active paths, 293 unreferenced rows, 5 excluded filesystem-only files, 3 SVGs, one oversized raster, and two duplicate-byte pairs. | Package 1 closure fixture. |
| `MP-R053` | The local-shaped fixture preserves 15 Curator rows with null keys, five group covers, no item images, header SVG IDs 6-7, default settings on ID 9, and empty attachment/journal tables. | Package 1 local profile tests. |
| `MP-R054` | Filesystem-only files are not imported during Curator conversion. They are counted and left for Files Discovery. | Conversion closure assertion. |
| `MP-R055` | Root-level legacy rows are not abandoned or permanently blocked merely because the old six-root gate rejects them. Valid rows are placed through the approved hybrid-root conversion; invalid rows become Needs Repair. | Package 1 production-profile tests. |
| `MP-R056` | Integrity reporting distinguishes trusted, active Needs Repair, unreferenced Needs Repair, missing/ambiguous source, filesystem-only discovery candidate, trash, purge-ready, and incomplete operation. Actively referenced broken media must not look like an ordinary quarantine candidate. | Packages 1, 2, and 5 reports. |
| `MP-R057` | The migration-closure gate must test the combined real state, not only isolated happy paths. Every preflight state needs an executable transition or explicit safe block; otherwise the gate fails. | Transition Closure Gate tests. |
| `MP-R058` | Current G1 and LMTC algorithms/tests are reusable evidence, but their Curator-canonical, fixed-root selection, and old production runbook decisions no longer control forward architecture. | Supersession map and source reconciliation. |
| `MP-R059` | The dormant empty permission schema is unrelated to media correction. Leave it in place; no broad rollback. Any future targeted cleanup needs its own audit. | Docs and migration allowlist. |
| `MP-R060` | No real local-development or production database/storage/cache/migration/conversion/backfill/repair/sanitation/deployment/process action, no push, and no production outage are authorized by this implementation run. | Every handoff and final Git/action audit. |
| `MP-R061` | All user-facing labels, hints, warnings, actions, validation messages, and folder names have Hebrew and English translations; Hebrew-first RTL behavior is preserved. | Translation parity and browser/feature tests. |
| `MP-R062` | Gallery/picker/list work has bounded queries and serialized Livewire state. No filesystem hashing/image decode/settings lookup occurs per table row. Measure 1/10/50 and production-shaped cases in the plane claimed. | Performance package tests and audit. |
| `MP-R063` | Every package uses TDD, focused serial verification, and the complete repository gate in exact order: requirements sweep, Pint, FilaCheck, build, then the full test suite last and uninterrupted. Research/planning/audit/review subagents use GPT-5.6 Sol where routing is available; implementation subagents use GPT-5.6 Terra; subagents use Fast while the main task remains non-Fast. Writers remain sequential. Each package closes with an implementation commit followed immediately by a docs-only hash stamp, and nothing is pushed. | Package handoffs and Git history. |

## Correct baselines

### Local development incident

The local incident already ran the four G1 media migrations and the dormant
permission migration in batch 8. Fresh 2026-07-22 Boost schema inspection
confirms the G1 tables/columns/FKs. The separate already-verified 2026-07-21
incident data/filesystem snapshot recorded 15 Curator rows/files, all with null
`reference_key`; five ContentGroup covers matching IDs 1-5; no populated
ContentItem image path; header SVG IDs 6-7; default settings backed by ID 9;
and empty attachment/journal and permission/role tables. Those row/file counts
are dated evidence, not a fresh Gate 0 data probe. This program does not apply
its new schema or conversion to that database.

### Production snapshot

The dated read-only snapshot used for design is production release `7c55dca`:
the G1 media migrations are not applied; the dormant permission migration is
applied with empty tables; 403 Curator rows/files include 399 JPEG, 1 PNG, and
3 SVG; 108 ContentGroup covers all match rows; 106 of those paths are root
level; one ContentItem has no image; two header/logo references exist; no
default is configured; 110 distinct paths are active; 293 rows are unreferenced;
five filesystem-only files are excluded; one unreferenced raster is over the
current dimension target; and two duplicate-byte pairs must remain distinct.

Production facts can drift. Before any later real action, regenerate the
complete manifest and digest read-only. Nothing in this document authorizes
that action.

## Package route

| Package | Stable ID | Research | Plan | Handoff |
|---|---|---|---|---|
| Kernel and full Curator conversion | `MEDIA-P1-KERNEL-CONVERSION` | `packages/01-kernel-conversion-research.md` | `packages/01-kernel-conversion-plan.md` | `docs/phase-02/media-program-p1-kernel-conversion-handoff.md` |
| Trusted gallery, folders, repair, normalization and SVG | `MEDIA-P2-GALLERY-REPAIR` | `packages/02-gallery-repair-research.md` | `packages/02-gallery-repair-plan.md` | `docs/phase-02/media-program-p2-gallery-repair-handoff.md` |
| Unified acquisition and picker | `MEDIA-P3-ACQUISITION-PICKER` | `packages/03-acquisition-picker-research.md` | `packages/03-acquisition-picker-plan.md` | `docs/phase-02/media-program-p3-acquisition-picker-handoff.md` |
| Owner image presentation and change UX | `MEDIA-P4-OWNER-IMAGE-UX` | `packages/04-owner-image-ux-research.md` | `packages/04-owner-image-ux-plan.md` | `docs/phase-02/media-program-p4-owner-image-ux-handoff.md` |
| Files Discovery and lifecycle | `MEDIA-P5-FILES-LIFECYCLE` | `packages/05-files-lifecycle-research.md` | `packages/05-files-lifecycle-plan.md` | `docs/phase-02/media-program-p5-files-lifecycle-handoff.md` |

## Deferred or separately approval-gated

- Applying any migration or converter to local development or production.
- Production backup, maintenance, deploy, cache/process work, or downtime.
- Sanitizing real SVG rows, including Curator IDs 6 and 7.
- Importing the five known production filesystem-only files.
- Installing `mwguerra/filemanager`, Spatie Media Library, or any dependency.
- Removing Curator, compatibility keys/IDs/paths, attachment `media_id`, or
  journal Curator snapshots.
- Nested logical folders, public media contribution, video/audio/document
  assets, and cross-disk/cloud providers.
- Automatic curation generation beyond the approved image normalization path.
- Empty permission-schema cleanup.

## Rejected or superseded choices

- “Generate a key whenever it is null” is rejected. Null plus complete byte,
  metadata, normalization, and checksum proof may become trusted; null alone
  cannot.
- Curator as the permanent canonical media controller is superseded by the
  app-owned MediaAsset kernel.
- Spatie Media Library as a parallel or replacement identity layer is rejected
  for this program.
- Fixed physical roots as picker/security boundaries are superseded.
- Cloning/copying an existing asset into a slot-specific root on selection is
  rejected.
- Hiding all bad rows without a Needs Repair path is rejected.
- Treating a broken owner image as an exception or silently clearing it is
  rejected; retain diagnostic intent and show fallback.
- A plugin directly renaming, moving, deleting, or importing canonical files is
  rejected.
- Broad production `php artisan migrate --force`, broad batch rollback, cache
  clearing, and middleware removal are not media transition strategies.

## Lessons that control the program

1. A strict steady-state rule is incomplete until every real legacy state has
   a tested transition, repair, or explicit safe block.
2. Separate green tests for row registration, canonical backfill, and owner
   attachment did not prove their production-shaped intersection. Combined
   fixtures and closure digests are required.
3. Start from exact schema/data/file relationships. Architecture discussion
   without those counts produced wrong assumptions about local migration state
   and production root placement.
4. Physical organization is not product organization. Separating logical
   folders from physical roots removes the pressure to copy assets for UX.
5. Preserve evidence when trust fails. A broken association can remain useful
   diagnostic state while resolvers safely return no image/default fallback.
6. A provider adapter keeps future replacement possible only if application
   identity, validation, relationships, authorization, and lifecycle do not
   leak back into the provider model.
7. “Configurable” cannot mean “unbounded.” Admin working targets remain inside
   hard server security ceilings.
8. Every real-data operation needs a fresh deterministic plan. Historical
   counts guide fixtures but never authorize or substitute for a current
   production manifest.

## Change log

- 2026-07-22: created for approved Gate 0 from the settled conversation,
  current source, installed packages, Boost schema, G1/LMTC evidence, and the
  corrected local/production snapshots.
