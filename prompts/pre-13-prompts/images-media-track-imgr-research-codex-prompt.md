# Codex Prompt — IMG-R: Images & Media Track Research + Plan (docs only) — v2

Work in the current local clone of `studioycm/PodText`.

ONE research-and-planning run. NO app code, NO migrations, NO `composer require`,
NO plugin installation — the output is two documents and a decision list. Run this
ONLY after the `Step 10R-HF3` hotfix run is committed and pushed (clean tree
preflight; stop on dirt). Standing rules: fresh chat, Boost `search-docs` +
FilamentExamples in short batches, commit with a `docs:` prefix, no push unless
asked. Installed stack for all compatibility claims: filament/filament v5.6.7,
laravel/framework v13.19.0, livewire/livewire v4.3.3, PHP 8.4.

The Research/Plan contract applies in full: the research doc ends with numbered
R-decisions, each citing evidence (vendor docs/source/packagist, or a local probe
output) plus at least one rejected alternative; the plan references R-numbers;
framework-native capabilities surfaced by research win by default over hand-rolled
patterns; anything asserted about plugin compatibility must cite the source.

## Grounding facts (verified by Fable at file:line — build on these)

- ONE content image column exists: `content_groups.cover_path`. Its form field
  (ContentGroupForm:80-86) is `FileUpload` with disk public, directory
  `content-groups/covers`, `->image()->maxSize(2048)` — NO explicit
  `acceptedFileTypes` (bare `->image()` admits `image/svg+xml`, an XSS surface
  when an SVG is opened by direct URL) and NO helperText (cross-cutting rule
  violation). Uploads keep Livewire's hashed filenames — no naming rule anywhere.
- The groups admin table already shows `ImageColumn::make('cover_path')` with
  disk/visibility/square configured — table display is NOT a gap for groups.
- The settings page is the GOOD prior art (PublicContentSettings:585-605,
  1431-1439): menu logos (light/dark, directory `header/`) and about-team images
  (directory `team/`) with explicit acceptedFileTypes (SVG deliberately allowed
  for logos), maxSize, helperText, and a companion `alt_text` TextInput. The
  settings lifecycle already has an `asset_path` semantic and the package
  analyzer already emits missing-file warnings — a working portability precedent.
- `PublicDefaultImageResolver` is the single public consumption point:
  items prefer remote `external_thumbnail_url`, then group cover (mode-gated),
  then settings-configured `default_images` (per-family inherit/custom/none +
  global fallback); groups use cover_path then defaults; CONTRIBUTORS have NO
  per-record image source at all — only the settings default. Items also already
  carry `external_id` (the Spotify episode id) alongside the thumbnail URL.
- Portability hole: `cover_path` IS exported (disabled-by-default column) but NO
  importer has a cover_path column — the export column is decorative, and the
  path string it emits is meaningless on another site. Image columns currently
  violate the portable-identifier doctrine in both directions.
- Orphan files: Filament FileUpload does not delete the replaced file — every
  cover replacement strands the old file on the public disk; nothing cleans up on
  record delete either.
- Filesystems: default disk `local` (private), public disk = local
  `storage/app/public` with APP_URL-derived `/storage` URLs (requires
  storage:link on Forge); no S3.
- `config/livewire.php` is NOT published → Livewire's default temporary-upload
  validation (12MB) binds before PHP limits in practice.
- The Importer Workbench plan already contains WB7: "Drive images-folder media
  fetch (match via resolver, queued download to public disk)" — bulk image
  ingestion is planned there, NOT in the CSV importers.
- Slugs are Hebrew-first since UX3 (HebrewSlugger); `reference_key` is a char(26)
  ULID on all content models; ContentItem slugs are unique PER GROUP only — a
  flat filename=slug scheme collides across groups. Import/export doctrine:
  portable identifiers only; no remote media fetch during imports;
  `transcript_file` package support stays deferred until a documented package
  structure is approved — an images zip is the same class.
- Known IE-1 feed (from the HF3 handoff): the item exporter writes ALL
  `contentTags` including disabled ones while the importer resolves only enabled
  tags — round-trips silently drop disabled tags.

## Job 1 — inventory, schema gaps, and UX candidates

Map every admin resource against: has image column today / could sensibly have one
/ stays URL-only by rule (item embed thumbnails). Use the resolver evidence:
contributors are the strongest candidate (author avatar column — contributor
cards and pages exist with no per-record image), items are the weakest (remote
thumbnail already wins), category image is optional. For each candidate:
migration effort, resolver integration, public render surfaces, and whether
public specs reference it. Present options with effort so Yoni decides
(feeds D-IMG-C). Score these UX candidates for the plan: quick-upload record
action on tables with an image column; a replace-image flow with an explicit
old-file cleanup policy (fix the orphan problem: delete-on-replace +
delete-on-record-delete via observer, with tests); alt-text companion field for
covers (generalize the menu-logo pattern — accessibility + SEO); a form hint
linking to the default-images settings ("empty cover falls back to X").

## Job 2 — media plugin compatibility matrix

Candidates (packagist + vendor docs/READMEs; NO installs):
`awcodes/filament-curator`, `filament/spatie-laravel-media-library-plugin` (+
`spatie/laravel-medialibrary`), `tomatophp/filament-media-manager`, and any other
maintained Filament media manager FilamentExamples surfaces. For each: Filament
v5.6 + Livewire 4 + PHP 8.4 compatibility (cite the exact composer constraint —
v5 support is the GATING question), storage model (central media table vs
per-record polymorphic collections vs plain path columns), picker field offering
select-from-library OR upload, custom file-naming hooks (Spatie FileNamer /
Curator naming config), image conversions and responsive/thumbnail generation
(the public front currently serves original up-to-2MB covers on cards — check GD
or Imagick availability locally with `extension_loaded`), per-image alt/caption
storage, replaced/deleted file cleanup behavior, RTL/translation readiness,
license/cost, maintenance activity. End with a ranked recommendation INCLUDING
the "no plugin — plain FileUpload + our naming concern + observer cleanup"
baseline as a scored option (feeds D-IMG-B). Integration constraint for all
candidates: whatever is chosen must slot UNDER `PublicDefaultImageResolver` and
the `default_images` settings (extend the resolver's sources, never bypass it).

## Job 3 — central file-naming rule design

Design (do not build) `App\Support\Media\...` naming support: final name = slug,
fallback `reference_key`; collision policy MUST address group-scoped item slugs
(directory scoping like `content-items/{group}/` or composite names — present
both); extension normalization; lowercase. Answer the Hebrew-filename question
WITH EVIDENCE: probe (tinker/Storage in a scratch path, then clean up) how a
Hebrew filename behaves through Storage::disk('public') → public URL encoding →
browser fetch → ZipArchive entry round-trip. Present slug-Hebrew vs
reference_key-ASCII on-disk naming with a recommendation; note the matcher design
(import matching accepts slug OR reference_key OR external_id/spotify id — the
`external_id` column exists on items TODAY) so UX does not depend on the on-disk
choice (feeds D-IMG-A). The rule applies to NEW uploads only — existing stored
paths (hashed cover names, `header/`, `team/`) are never rewritten. Document how
the rule plugs into: plain FileUpload (`getUploadedFileNameForStorageUsing()`),
each Job-2 plugin's naming hook, WB7's queued downloads, the quick-upload record
action, and a future export-images naming option (slug default, external_id
alternative). Server-side validation for the quick-upload action should use
Laravel 13 `File::image()->max()` rule objects with explicit photo-safe accepted
types (jpeg/png/webp — NO svg for covers/avatars; svg stays logo-only in
settings), plus a dimensions cap and an EXIF-stripping recommendation if
conversions land.

## Job 4 — upload limits documentation

Measure and record the real local chain: `upload_max_filesize`, `post_max_size`,
`max_file_uploads` (php -r / Herd), nginx client_max_body_size where visible,
Livewire temp-upload default, current Filament field maxSize values (2048 on all
three image surfaces today). Document the Forge-side knobs (PHP tab max upload
setting; site nginx config) as a deploy note — do not guess production values,
list where to read them; note storage:link must exist on Forge and that public
URLs derive from APP_URL (domain cutover safe: stored paths are relative).
Recommend per-surface maxSize values for covers/avatars.

## Job 5 — content packages: images in import AND export (research only)

Read the installed Filament ImportAction/Importer source: can the import options
form carry a FileUpload (zip) and how would that file reach the queued import
jobs? Document zip safety requirements if ever built: zip-slip entry validation,
per-file and total size caps, image MIME validation, count cap, temp-dir
cleanup. Design the portability semantics BOTH directions: what should
`cover_path` export mean (nothing? a filename under the naming rule? a bundled
file in a package)? — today it exports a dead path string and imports not at all.
Reuse the settings-lifecycle precedent (asset_path semantic + missing-file
warnings) rather than inventing a new mechanism. Then compare honestly against
WB7's Drive-folder fetch (no HTTP upload limits, queued, resumable, matches the
real editorial workflow) and recommend build vs defer for the zip path with
cited reasons (feeds D-IMG-D; the transcript_file deferral precedent applies).

## Job 6 — native import/export relation-handling audit (scopes IE-1)

Audit how the importers treat multi-value relations TODAY on update-mode rows
(categories, tags, and the transcriber keys from M-era work): sync/replace vs
merge — cite the exact lines. Include the verified disabled-tags round-trip loss
(export writes all contentTags; import resolves only enabled → silent drop) and
propose explicit outcomes (row warning vs documented drop vs import-disabled
behind spec change). Define the IE-1 mini-step: explicit relation modes
(replace / merge / add-only) as import options aligned with the D25
settings-merge vocabulary, plus export-side counterparts worth having (e.g.
enabled-only vs all tags toggle). Respect the existing per-action `chunkSize`
tuning. List tests IE-1 must add.

## Output documents

- `docs/research/images-media/00-images-media-research.md` — Jobs 1-6 evidence +
  numbered R-decisions with rejected alternatives.
- `docs/phase-02/images-media-track-plan.md` — mini-steps IMG-1 (naming concern +
  cover-field hardening: photo-safe acceptedFileTypes + helperText + orphan-file
  cleanup observer + quick-upload table record action), IE-1, IMG-2 (plugin,
  gated on D-IMG-B approval), IMG-3 (content packages / zip, likely deferred),
  each with scope, tests, and which R-decisions it implements; a `D-IMG-A..D`
  decision list for Yoni with recommendations; WB7 touchpoints; explicit
  out-of-scope (no schema changes, no dependency changes until approved).
- Ledger: one pending-track note line for the IMG/IE track (do not renumber or
  touch existing rows); `current-project-state.md` gets a one-line pointer.

Commit: `docs: add images and media track research and plan`

End with exactly:

```text
Images & media track research IMG-R is complete. Waiting for Yoni decisions on D-IMG-A..D before any implementation.
```
