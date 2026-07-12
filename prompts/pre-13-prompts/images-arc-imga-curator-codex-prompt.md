# Codex Prompt — IMG-A: Image Naming Foundation + Curator Media Library

Work in the current local clone of `studioycm/PodText`.

ONE merged implementation run (the approved IMG-1 + IMG-2 scope from
`docs/phase-02/images-media-track-plan.md`, updated by Yoni's decisions below).
Standing runner rules: research note + implementation plan docs BEFORE code,
full sequential quality gate incl. `git diff --check`, no push unless asked,
no `filacheck --fix`, fixture-owned tests, en+he translations, RTL-safe UI,
handoff ends with `## Commit hash` and `## Local Front Check Report`.
Preflight: clean tree; the EP1-R docs commit is expected in recent history —
that is fine; stop on any APP-code dirt.

Test policy: iterate with targeted tests only; run the FULL `php artisan test`
exactly ONCE at the final gate after Pint (expect ~8-10 quiet minutes — known,
out of scope; do not interrupt or parallelize).

## Yoni decisions this run implements (with the IMG-R research R-numbers)

- D-IMG-B final: **Curator** (`awcodes/filament-curator`, the Filament 5 line)
  is the approved NEW dependency — the only composer addition allowed here.
- D-IMG-A final: on-disk naming is a **site setting** `media_naming_strategy`:
  `slug` (DEFAULT — Hebrew allowed, evidence: IMG-R probe) | `reference_key` |
  `slug_key`. Empty slug always falls back to reference_key. Strategy changes
  are FORWARD-ONLY: existing stored files/paths are never renamed (R3 adjusted
  by Yoni: slug is the default, not reference_key).
- C3 approved: `content_groups.cover_alt_text` ships now. C2 (author avatars)
  is DEAD — do not build avatar anything.
- 1f: settings-page image assets also go through Curator, BUT behind an
  app-owned field factory that can switch back to plain FileUpload by config,
  and settings/model storage ALWAYS remains plain path strings.
- 1g: existing cover + settings asset files get registered into the Curator
  library (pickable), never moved or renamed.

## Job 1 — naming + validation foundation

- `App\Support\Media\ImageFileNamer` per the IMG-R Job-3 design: semantic stem
  (slug else reference_key), storage stem by the `media_naming_strategy`
  setting, export stem (`slug--reference_key`), lowercase normalized extension
  from validated MIME, family directories (`content-groups/covers/...`),
  collision policy per research. Pure + unit-tested (Hebrew slug, empty-slug
  fallback, collision suffix, each strategy).
- The setting lives in a NEW `AdminUxSettings` Spatie settings class (settings
  migration; this run creates the class with ONLY this key — EP1 will extend
  it later; note that in the class docblock and handoff).
- Server-side upload validation helper using Laravel 13 `File::image()` rule
  objects: jpeg/png/webp only for content photos (NO svg — R5), max size, and
  a dimensions cap per the IMG-R Job-4 recommendations. Record (do not build)
  the EXIF-stripping note: metadata stripping lands when image re-encoding
  lands; add it to the plan doc as a deferred item.

## Job 2 — cover field hardening + alt text

- ContentGroupForm cover: explicit accepted types via the Job-1 helper,
  helperText (en+he) stating size/type/fallback behavior, and a hint linking
  editorially to the default-images settings.
- Migration: nullable `content_groups.cover_alt_text` (bounded string) +
  form field with helper text + the public render paths use it: the group
  badge Blade and every public `<img>` fed by `PublicDefaultImageResolver`
  group images use cover_alt_text, falling back to the group title. Tests.

## Job 3 — file cleanup (the orphan fix, R4)

Delete-on-replace and delete-on-record-delete for app-owned cover files:
observer/service comparing old/new `cover_path`, deleting only files inside
the app-owned cover directory (never touching external/legacy paths outside
it). Same behavior wired for Curator-managed replacement where applicable
(see Job 4 findings). Tests: replace deletes only the old file; record delete
removes its file; a path outside the owned directory is never deleted.

## Job 4 — Curator install + integration (research first)

- Research batch BEFORE code: Curator v5 official install for Filament 5.6
  (plugin registration, migrations, config, theme CSS), its path-generation /
  naming hooks (integrate `ImageFileNamer`), its alt/caption metadata
  workflow, and its delete/detach semantics — these were the plan's explicit
  pre-code source-review items; record findings with file/doc citations in
  the research doc.
- `composer require awcodes/filament-curator` (current v5 line), publish and
  run its migrations (MySQL locally), register the plugin on the ADMIN panel
  only, wire the naming concern into its path generation, disk `public`.
- Handoff must enumerate every composer.json and lock change (the WB1
  dependency-enumeration rule).

## Job 5 — app-owned picker field factory (the switchability guarantee)

One factory (e.g. `App\Filament\Forms\MediaPickerField::make(...)`) that
renders CuratorPicker or plain FileUpload based on a config flag, and ALWAYS
dehydrates a plain path string into the bound state (extract the path from
the selected Curator media). Apply it to:

- `cover_path` on ContentGroupForm (model column stays a string; exporters,
  importers, ImageColumn, and the resolver are UNCHANGED consumers).
- The settings-page image uploads: menu logos (light/dark), team/about
  images, default-images paths — settings JSON keeps storing path strings.
  Logos keep their deliberate SVG allowance in plain-upload mode; decide and
  record how SVG logos behave under Curator mode (Curator ships an SVG
  sanitizer — cite it).

Tests: both factory modes render; picker selection persists the path string;
settings save round-trips byte-identical path values; cover export column
output unchanged.

## Job 6 — register existing files into the library (1g)

Idempotent artisan command that registers existing cover files and settings
asset files (header/, team/, about/, default-images/) as Curator media
WITHOUT moving or renaming them — verify from Curator source/docs that
adopting files in place is supported; if Curator cannot reference existing
files in place, STOP that sub-scope, record the evidence in the handoff, and
leave old files outside the library (do NOT copy/duplicate storage without a
recorded Yoni decision). Report counts. Test with fixture files on a faked
disk.

## Docs and handoff

Research + plan docs BEFORE code (`docs/research/images-media/01-imga-*.md`
or similar under the same folder); ledger row `IMG-A - Image naming
foundation and Curator media library` after the latest completed row;
`current-project-state.md`; update the D-IMG register in
`docs/phase-02/images-media-track-plan.md` (A=slug-default setting, B=Curator
final, C2 dead, C3 shipped, 1f/1g outcomes); `ai-development-lessons` only if
a durable lesson emerged. Handoff: `## Commit hash`, dependency enumeration,
and a numbered `## Local Front Check Report` (upload a cover through the
picker → pick an EXISTING library image on another podcast → replace a cover
and verify the old app-owned file is gone → alt text renders on the public
badge/card img → settings logo picked via Curator stores a plain path and
renders on the public header → switch `media_naming_strategy` and verify only
NEW uploads change shape → registered legacy covers appear in the library →
Hebrew RTL + light/dark).

## Out of scope

Episode image column + downloads (IMG-B); table image actions (TB1); avatars
(dead); zip packages (deferred); EP1 workspace; SF1/TL1 tools; any exporter
changes.

Commit: `feat: add image naming foundation and curator media library`

End with exactly:

```text
Images arc IMG-A is complete. Waiting for Yoni review before continuing.
```
