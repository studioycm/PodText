# Codex Prompt — IMG-B v2: Episode Images, Table Actions, Media Guards, Images Export

Work in the current local clone of `studioycm/PodText`.

ONE implementation run (planned IMG-1b + TB1 scope, plus Yoni's corrections:
library-retention cleanup semantics, referenced-media delete guard, and the
content-images export with egress naming). Standing runner rules: research
note + implementation plan docs BEFORE code, full sequential quality gate
incl. `git diff --check`, no push unless asked, no `filacheck --fix`,
fixture-owned tests, en+he translations, RTL-safe UI, NO Composer changes.
The handoff is a COMMITTED MARKDOWN FILE
(`docs/phase-02/images-arc-imgb-handoff.md`) ending with `## Commit hash` and
a numbered `## Local Front Check Report` written as MANUAL operator steps for
Yoni (not test-coverage mapping).

Test policy: iterate with targeted tests only. Run the FULL `php artisan test`
at the final gate after Pint — and "once" means once GREEN: if it fails, fix,
verify with targeted tests, then run the full suite AGAIN until it passes;
record every full run and its outcome. Each run takes ~8-10 quiet minutes;
never interrupt, never parallelize, never investigate the slowness.

## Preflight

```bash
git status --short --branch
git log --oneline -5
```

Clean tree; `e70d6f3` (EP1) and `988676e` (IMG-A) expected in history.

## Yoni decisions this run implements

- **D-IMG-A v2 — egress naming**: storage filenames remain whatever is
  technically best (Curator/library names untouched; no rename-on-attach —
  that idea is closed). The `admin_ux.media_naming_strategy` setting now
  governs EGRESS names: filenames of downloaded/exported images. Update the
  images track plan register accordingly.
- **D-IMG-E — library retention**: replacing or clearing an image, and
  deleting the owning record, KEEP library-registered files. Automatic file
  deletion applies ONLY to app-owned files with no Curator media row
  (legacy/fallback strays — the original orphan case). Explicit destruction
  happens only in the Curator library UI, guarded per Job 0.4.
- Referenced-media deletion: BLOCK with a translated warning (not
  warn-and-allow).

## Job 0 — process corrections and audit fixes

1. **Retrospective IMG-A handoff**: create
   `docs/phase-02/images-arc-imga-handoff.md` from the run record — commit
   `988676e`; dependency enumeration (awcodes/filament-curator 5.1.2 +
   transitives enshrined/svg-sanitize 0.22.0, intervention/gif 4.2.4,
   intervention/image 3.11.8, league/glide 3.2.0); honest gate record: the
   single full suite ran once and FAILED on 3 integration issues, fixes were
   verified with targeted tests only and the suite was not rerun in that
   session — full-suite green over the tree was first proven by EP1's run
   (411 tests, 3,740 assertions); recorded limitations (picker uploads not
   record-aware — now moot under D-IMG-A v2; adopted-media delete destructive
   — now guarded); EXIF stripping deferred; a numbered manual front-check
   list for Yoni.
2. **Backfill** EP1's commit hash `e70d6f3` wherever the EP1 handoff/ledger
   reference it by message only.
3. **Lessons** (`docs/phase-02/ai-development-lessons.md`), three durable
   entries: (a) a run is complete only when its handoff exists as a committed
   repo file — chat output is not a handoff; (b) "full suite exactly once"
   means once GREEN — a failed run does not satisfy the gate and a rerun
   after fixes is mandatory; (c) the Local Front Check Report is a numbered
   list of manual operator steps, separate from automated-coverage notes.
4. **Referenced-media deletion guard**: block deleting a Curator `Media` row
   whose path is referenced by any `content_groups.cover_path`,
   `content_items.image_path` (new this run), or a settings-stored asset path
   (menu logos, team/about, default images) — enforce at the model/policy
   boundary so BOTH the Curator UI and direct deletes are covered, with a
   translated warning naming the referencing surface. Tests: referenced media
   cannot be deleted; unused media can.
5. **Cleanup semantics flip to D-IMG-E**: rework `AppOwnedMediaFileCleaner` +
   observers so replace/clear/record-delete NEVER remove files that have a
   Curator media row; only app-owned no-row strays are deleted (keep the
   directory-scoping, traversal guards, and cross-reference checks). Adjust
   the IMG-A tests that asserted delete-on-replace; add tests: replaced
   library cover REMAINS on disk and in the library; a no-row stray is still
   cleaned.
6. **PathCuratorPicker cleanups**: regression test proving a legacy path with
   NO media row survives an untouched form save (preservedPath); remove the
   dead conditional in `mediaByPath()`.

## Job 1 — episode local image

- Migration: nullable `content_items.image_path` (string); `$fillable`;
  factory support.
- New `ImageFileNamer` family (`CONTENT_ITEM_IMAGE`, directory
  `content-items/images`) in `appOwnedDirectories()`.
- Workspace form media section: `MediaPickerField` for `image_path` with
  helper text stating the public preference order.
- `PublicDefaultImageResolver::contentItemImage()` preference: local
  `image_path` FIRST, then `external_thumbnail_url`, then group cover
  (mode-gated), then defaults — update tests and keep source labels truthful.
- ContentItem observer wired to the D-IMG-E cleaner semantics.
- Export/import of `image_path` stays OUT (deferred to packages/IE work) —
  state in the handoff.

## Job 2 — queued external-image download (admin-triggered enrichment)

Explicit admin action (workspace + episodes-table record action) "download
external image": queued job fetching `external_thumbnail_url` (HTTPS only,
ImageUploadRules size cap, raster MIME validation by content) into
`image_path` via the naming concern, registering a Curator media row,
notifying on completion/failure. Tests with `Http::fake()`: success fills
image_path + media row; oversized/wrong-MIME/HTTP URL fails cleanly with a
translated notification; action visible only when an external thumbnail
exists and no local image is set (or confirms overwrite).

## Job 3 — TB1 table image actions + effective-image column

- Record action on the podcasts AND episodes tables: choose/replace the image
  (cover_path / image_path) through `MediaPickerField`, presented in a modal
  or slideover per `admin_ux.tb1_picker_container` (both values must render),
  storing path strings, D-IMG-E cleanup semantics on replace.
- Episodes table: effective-image thumbnail column showing what the public
  card shows (via the resolver; reuse already-eager relations — no new N+1;
  state the query impact in the handoff).
- Translated labels/helper text; RTL-safe.

## Job 4 — content-images export with egress naming (Yoni feature)

- A "download content images" header action on the podcasts list (and a
  per-podcast record action variant scoping to one podcast if cheap): the
  confirmation modal shows the naming strategy PREFILLED from
  `admin_ux.media_naming_strategy` with a per-action override select (the
  bypass), then queues an export job on the `imports-exports` queue.
- The job builds a zip: `podcasts/{podcast-stem}/cover.{ext}` and
  `podcasts/{podcast-stem}/episodes/{episode-stem}.{ext}`, where stems come
  from `ImageFileNamer` using the CHOSEN strategy (slug / reference_key /
  slug_key; empty-slug falls back to reference_key). Include cover_path and
  image_path files that exist; missing/unreadable files are SKIPPED and
  listed in the completion report, never fatal. Zip entries with Hebrew names
  are supported (proven by the IMG-R probe).
- Store the zip on a private disk path, notify with a download action
  (mirror the Filament export notification pattern already in the app);
  clean up expired zips (bounded retention — simplest safe mechanism, e.g.
  delete-before-create per user or a scheduled prune; record the choice).
- Tests: zip structure + strategy naming + override; skip handling; guest
  blocked from the download route; queue assignment; notification received.

## Out of scope

Avatars (dead); zip IMPORT packages (still deferred); SF1/TL1 tools; IE-1;
exporter/importer column changes; Composer changes; rename-on-attach
(superseded by D-IMG-A v2).

## Docs and handoff

Research + plan docs before code (`docs/research/images-media/02-imgb-*.md`);
ledger row `IMG-B - Episode images, table image actions, media guards, images
export`; `current-project-state.md`; update the images track plan register
(D-IMG-A v2, D-IMG-E, IMG-1b/TB1 delivered, export shipped); the committed
handoff file per the header rules with a numbered MANUAL front-check list
(pick a local episode image in the workspace → public card prefers it over
the Spotify thumbnail; clear it → external thumbnail wins again and the file
REMAINS in the library; replace a podcast cover from the table action → old
cover REMAINS on disk and in the library; try deleting a referenced media row
in the Curator UI → blocked with a clear warning; delete an unreferenced one
→ allowed; run the download action on an episode with only an external image
→ local file appears and is library-registered; run "download content images"
→ zip arrives with podcasts/{name}/cover and nested episodes files named per
the selected strategy; run it again overriding the strategy in the modal →
names change; flip tb1_picker_container between modal and slideover; episodes
table shows the effective thumbnail; Hebrew RTL + light/dark).

Commit: `feat: add episode images, media guards, and content images export`

End with exactly:

```text
Images arc IMG-B is complete. Waiting for Yoni review before continuing.
```
