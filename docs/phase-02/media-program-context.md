# Media Program Context Helper

Read this file first after any context compaction or resumed media task. Then
read the linked registry, master plan, active package plan, latest package
handoff, and current source. Automatic conversation summaries are not the
program's source of truth.

## Approved route

- Audit: `LS-20260722-PODTEXT-MEDIA-ASSET-PROGRAM-06`
- Option: `MEDIA-CUTOVER-O1-DIRECT-ASSET-HYBRID-ROOT-MAINTENANCE`
- Current stage: Stage 2, documentation Gate 0 complete as
  `4881fe588a28d985194bf432020a0d8f4fef1c4f`.
- Active package: `MEDIA-P1-KERNEL-CONVERSION`, beginning post-Gate-0 source/
  plan reconciliation before its first RED test.
- Checkout at approval: clean `main`,
  `5ba687eff92878f18e9e19e807944a2d39b63372`, 11 commits ahead of
  `origin/main`.
- No `prompts/pre-13-prompts/` file is active.

## Canonical documents

- Requirements, decisions, method:
  `docs/research/media-program/00-media-program-requirements-decisions-and-method.md`
- Master research:
  `docs/research/media-program/01-media-program-research.md`
- Master plan:
  `docs/research/media-program/02-media-program-master-plan.md`
- Correct baselines:
  `docs/research/media-program/03-local-production-baselines.md`
- Active-document map:
  `docs/research/media-program/04-active-document-supersession-map.md`
- Package routes:
  `docs/research/media-program/packages/`

## Settled architecture

- PodText owns `MediaAsset`; Curator is the only initial provider.
- One MediaAsset per Curator row, one immutable asset key, Curator numeric ID
  retained in its binding, and no checksum deduplication.
- MediaAsset trust/lifecycle is authoritative. Curator metadata is provider
  evidence, not sufficient trust.
- Physical roots use hybrid initial placement. Logical folders are independent
  flat admin organization and picker filters.
- Existing asset selection attaches without copy, clone, normalization, or
  move.
- Trusted gallery and separate Needs Repair; unsafe bytes have no ordinary
  preview/download.
- Broken active associations remain diagnostic and resolve configured/system
  fallback without HTTP 500.
- One four-source picker: Gallery, Upload, URL, Storage. Spotify feeds URL.
- Files Discovery is separate and every canonical mutation uses PodText's
  validator and journal.
- Private quarantine defaults to 90 days; purge needs zero-reference proof.

## Baselines that must not drift back

- Local: fresh 2026-07-22 schema inspection confirms the four G1 media
  migrations plus dormant permission migration ran in batch 8. The separate
  2026-07-21 incident snapshot recorded 15 unconverted Curator rows with null
  keys; Gate 0 did not freshly probe those row/file counts.
- Production snapshot: G1 media migrations not applied; 403 Curator rows,
  108 matched group covers, 3 SVG, 5 filesystem-only files excluded, and two
  duplicate-byte pairs retained as separate identities.
- Both snapshots require fresh read-only preflight before any later real-data
  action. This task does not apply migrations or conversion to either.

## Package sequence

1. `MEDIA-P1-KERNEL-CONVERSION`: schema, asset/provider authority, bridge,
   deterministic all-Curator conversion, closure tooling.
2. `MEDIA-P2-GALLERY-REPAIR`: trusted gallery, logical folders, Needs Repair,
   raster normalizer, reusable SVG sanitation.
3. `MEDIA-P3-ACQUISITION-PICKER`: unified upload/URL/Spotify/Storage
   acquisition and reusable MediaAssetPicker.
4. `MEDIA-P4-OWNER-IMAGE-UX`: podcast/episode hover, detail, safe download,
   copy filename, and change-image UX.
5. `MEDIA-P5-FILES-LIFECYCLE`: Files Discovery, import, physical move/rename,
   trash/restore/purge and retention.

Each package is TDD-first, independently reviewed, and closed with an
implementation commit followed immediately by a docs-only hash-stamp commit.
Every package runs requirements sweep, Pint, FilaCheck, build, then the full
test suite last and uninterrupted; any later change restarts at Pint. Sol owns
research/review, Terra owns implementation where routing exists, subagents use
Fast, the main task remains non-Fast, writers stay sequential, and there is no
push.

## Hard exclusions for this run

- No local-development or production database/storage/cache mutation.
- No live migration, conversion, backfill, repair, sanitation, deploy, or
  process action.
- No real SVG sanitation, including Curator IDs 6 and 7.
- No filesystem-only import during Curator conversion.
- No Spatie Media Library or file-manager dependency.
- No production outage and no push.

Any material dependency, schema, security, package/task count, data shape, or
production-action change returns to amended Stage 1.

## Resume checklist

1. Recheck `git status --short --branch`, HEAD, and latest commits.
2. Read this helper and the registry.
3. Read the master plan and active package research/plan/handoff.
4. Recheck current source, migrations, installed vendor source, and affected
   tests for the exact micro-subject.
5. Confirm no overlapping writer changes and no prohibited real-data action.
6. Continue from the first incomplete task; do not replay completed packages.
