# Codex Prompt — IMG-B: Episode Images, Table Image Actions, Media Guards

Work in the current local clone of `studioycm/PodText`.

ONE implementation run (the planned IMG-1b + TB1 scope plus audit corrections).
Standing runner rules: research note + implementation plan docs BEFORE code,
full sequential quality gate incl. `git diff --check`, no push unless asked,
no `filacheck --fix`, fixture-owned tests, en+he translations, RTL-safe UI,
NO Composer changes. The handoff is a COMMITTED MARKDOWN FILE
(`docs/phase-02/images-arc-imgb-handoff.md`) ending with `## Commit hash` and
a numbered `## Local Front Check Report` written as MANUAL operator steps for
Yoni (not test-coverage mapping).

Test policy: iterate with targeted tests only. Run the FULL `php artisan test`
ONCE at the final gate after Pint — and "once" means once GREEN: if it fails,
fix, verify with targeted tests, then run the full suite AGAIN until it
passes; record every full run and its outcome. Expect ~8-10 quiet minutes per
run; never interrupt, never parallelize, never investigate the slowness.

## Preflight

```bash
git status --short --branch
git log --oneline -5
```

Clean tree; `e70d6f3` (EP1) and `988676e` (IMG-A) expected in history.

## Job 0 — process corrections and audit fixes (Fable audit of IMG-A/EP1)

1. **Retrospective IMG-A handoff**: create
   `docs/phase-02/images-arc-imga-handoff.md` from the run record — commit
   `988676e`; dependency enumeration (awcodes/filament-curator 5.1.2 +
   transitives enshrined/svg-sanitize 0.22.0, intervention/gif 4.2.4,
   intervention/image 3.11.8, league/glide 3.2.0); honest gate record: the
   single full suite ran once and FAILED on 3 integration issues, the fixes
   were verified with targeted tests only and the suite was not rerun in that
   session — full-suite green over this tree was first proven by EP1's run
   (411 tests, 3,740 assertions); recorded limitations (Curator picker
   uploads are not record-aware so the naming strategy fully applies only in
   the plain-upload fallback; deleting a Curator media row deletes the
   physical file — destructive for adopted files); EXIF stripping deferred;
   a numbered manual front-check list for Yoni (Curator library upload;
   pick-existing on a podcast cover; replace deletes the old app-owned file;
   public alt text; settings logo via picker stores a plain path; register
   command idempotence; RTL/light/dark).
2. **Backfill** EP1's commit hash `e70d6f3` wherever the EP1 handoff/ledger
   reference it by message only.
3. **Lessons** (`docs/phase-02/ai-development-lessons.md`), three durable
   entries: (a) a run is complete only when its handoff exists as a committed
   repo file — chat output is not a handoff; (b) "full suite exactly once"
   means once GREEN — a failed run does not satisfy the gate and a rerun
   after fixes is mandatory; (c) the Local Front Check Report is a numbered
   list of manual operator steps, separate from automated-coverage notes.
4. **Referenced-media deletion guard** (closes the destructive-delete gap):
   block deleting a Curator `Media` row whose path is referenced by any
   `content_groups.cover_path`, `content_items.image_path` (new this run),
   or a settings-stored asset path (menu logos, team/about, default images) —
   implement at the model/policy boundary so BOTH the Curator UI and direct
   deletes are covered, with a translated error/notification naming the
   referencing surface. Tests: referenced media cannot be deleted; unused
   media still can.
5. **PathCuratorPicker cleanups**: add the missing regression test proving a
   legacy path with NO media row survives an untouched form save
   (preservedPath); remove the dead conditional in `mediaByPath()` (both
   branches return the same value).

## Job 1 — episode local image

- Migration: nullable `content_items.image_path` (string) after
  `external_thumbnail_url`; add to `$fillable`; factory support.
- New `ImageFileNamer` family (e.g. `CONTENT_ITEM_IMAGE`, directory
  `content-items/images`) wired into `appOwnedDirectories()`.
- Workspace form (EpisodeWorkspaceForm media section): `MediaPickerField` for
  `image_path` with helper text stating the public preference order.
- `PublicDefaultImageResolver::contentItemImage()` preference becomes: local
  `image_path` FIRST, then `external_thumbnail_url`, then group cover
  (mode-gated), then defaults — update tests for the new order and keep the
  source labels truthful.
- Extend `AppOwnedMediaFileCleaner` + a ContentItem observer for
  delete-on-replace / delete-on-record-delete of app-owned item images (same
  directory-scoped + cross-reference safety as covers).
- Export/import of `image_path` stays OUT (portability belongs to the
  deferred packages/IE work) — state that in the handoff.

## Job 2 — queued external-image download (admin-triggered enrichment)

An explicit admin action (workspace header/action + episodes-table record
action) "download external image": queues a job that fetches
`external_thumbnail_url` (HTTPS only, size cap per ImageUploadRules, MIME
must be a permitted raster type) into `image_path` via the naming concern,
registers a Curator media row for it, and notifies on completion/failure.
Queue: `default` is fine (small jobs). Tests with `Http::fake()`: success
fills image_path + creates the media row; oversized/wrong-MIME/HTTP URL
fails cleanly with a translated failure notification; action visible only
when an external thumbnail exists and no local image is set (or confirm
overwrite).

## Job 3 — TB1 table image actions + effective-image column

- Record action on the podcasts AND episodes tables: choose/replace the image
  (cover_path / image_path) through `MediaPickerField`, presented in a modal
  or slideover per `admin_ux.tb1_picker_container` (both values must actually
  render), storing path strings, old-file cleanup firing on replace.
- Episodes table gains an effective-image thumbnail column showing what the
  public card shows (via the resolver; reuse already-eager relations — no new
  N+1: verify with the existing eager loads and state the query impact in
  the handoff).
- Translated labels/helper text; RTL-safe.

## Out of scope

Rename-on-attach for Curator uploads (separate Yoni decision, pending);
avatars (dead); zip packages (deferred); SF1/TL1 tools; IE-1; exporter
changes; Composer changes.

## Docs and handoff

Research + plan docs before code (`docs/research/images-media/02-imgb-*.md`);
ledger row `IMG-B - Episode images, table image actions, media guards`;
`current-project-state.md`; update the images track plan register (IMG-1b/TB1
delivered, what remains); the committed handoff file per the header rules
with a numbered MANUAL front-check list (pick a local episode image in the
workspace → public card prefers it over the Spotify thumbnail; remove it →
external thumbnail wins again; run the download action on an episode with
only an external image → local file appears under content-items/images and
in the Curator library; try deleting that media row in the Curator UI →
blocked with a clear message; replace a cover from the podcasts TABLE via
the action → old file cleaned; flip tb1_picker_container between modal and
slideover; episodes table shows the effective thumbnail; Hebrew RTL +
light/dark).

Commit: `feat: add episode images, table image actions, and media guards`

End with exactly:

```text
Images arc IMG-B is complete. Waiting for Yoni review before continuing.
```
