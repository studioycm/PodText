# Codex Prompt — IMG-R: Images & Media Track Research + Plan (docs only)

Work in the current local clone of `studioycm/PodText`.

ONE research-and-planning run. NO app code, NO migrations, NO `composer require`,
NO plugin installation — the output is two documents and a decision list. Run this
ONLY after the `Step 10R-HF3` hotfix run is committed and pushed (clean tree
preflight; stop on dirt). Standing rules: fresh chat, Boost `search-docs` +
FilamentExamples in short batches, commit with a `docs:` prefix, no push unless
asked.

The Research/Plan contract applies in full: the research doc ends with numbered
R-decisions, each citing evidence (vendor docs/source/packagist, or a local probe
output) plus at least one rejected alternative; the plan references R-numbers;
framework-native capabilities surfaced by research win by default over hand-rolled
patterns; anything asserted about plugin compatibility must cite the source.

## Grounding facts (verified by Fable — build on these)

- The app has ONE image column: `content_groups.cover_path`
  (`FileUpload` in ContentGroupForm: disk public, directory `content-groups/covers`,
  `->image()->maxSize(2048)`, default Livewire hashed filenames — no naming rule).
- Item thumbnails are URL-only by design (media-embeds rules); authors and
  categories have no image columns.
- `config/livewire.php` is NOT published → Livewire's default temporary-upload
  validation (12MB) binds before PHP limits in practice.
- The Importer Workbench plan already contains WB7: "Drive images-folder media
  fetch (match via resolver, queued download to public disk)" — bulk image
  ingestion is planned there, NOT in the CSV importers.
- Slugs are Hebrew-first since UX3 (HebrewSlugger); `reference_key` is a char(26)
  ULID on all content models. Import/export doctrine: portable identifiers only
  (reference keys, slugs/paths); no remote media fetch during imports;
  `transcript_file` package support is deferred until a documented package
  structure is approved — an images zip is the same class.

## Job 1 — inventory and schema gap analysis

Map every admin resource against: has image column today / could sensibly have one
(author avatar, item cover, category icon-image) / stays URL-only by rule (item
embed thumbnails). For each candidate column: migration effort, public-front render
surface, and whether public specs reference it. No recommendation to add columns —
present the options with effort so Yoni decides (feeds D-IMG-C).

## Job 2 — media plugin compatibility matrix

Candidates to research (packagist + vendor docs/READMEs; NO installs):
`awcodes/filament-curator`, `filament/spatie-laravel-media-library-plugin` (+
`spatie/laravel-medialibrary`), `tomatophp/filament-media-manager`, and any other
maintained Filament media manager FilamentExamples surfaces. For each: Filament v5
+ Livewire 4 + PHP 8.4 compatibility (cite the exact constraint from composer.json
or docs — v5 support is the GATING question), storage model (central media table
vs per-record polymorphic collections vs plain path columns), picker field that
offers select-from-library OR upload, custom file-naming hooks (e.g. Spatie
FileNamer, Curator naming config), RTL/translation readiness, license/cost,
maintenance activity. End with a ranked recommendation INCLUDING the "no plugin —
plain FileUpload + our naming concern" baseline as a scored option (feeds D-IMG-B).

## Job 3 — central file-naming rule design

Design (do not build) `App\Support\Media\...` naming support: final name = slug,
fallback `reference_key`; collision suffix policy; extension normalization;
lowercase. Answer the Hebrew-filename question WITH EVIDENCE: probe (tinker/
Storage in a scratch path, then clean up) how a Hebrew filename behaves through
Storage::disk('public') → public URL encoding → browser fetch → ZipArchive entry
round-trip. Present slug-Hebrew vs reference_key-ASCII on-disk naming with a
recommendation; note the matcher design (import matching accepts slug OR
reference_key OR spotify_id regardless of stored name) so UX does not depend on
the on-disk choice (feeds D-IMG-A). Document how the rule plugs into: plain
FileUpload (`getUploadedFileNameForStorageUsing()`), each Job-2 plugin's naming
hook, WB7's queued downloads, and a future export-images naming option
(slug default, spotify_id alternative).

## Job 4 — upload limits documentation

Measure and record the real local chain: `upload_max_filesize`, `post_max_size`,
`max_file_uploads` (php -r / Herd), nginx client_max_body_size where visible,
Livewire temp-upload default, current Filament field maxSize values. Document the
Forge-side knobs (PHP tab max upload setting; site nginx config) as a deploy note
— do not guess production values, list where to read them. Recommend per-surface
maxSize values for covers/avatars.

## Job 5 — importer-images feasibility (research only)

Read the installed Filament ImportAction/Importer source: can the import options
form carry a FileUpload (zip) and how would that file reach the queued import
jobs? Document zip safety requirements if ever built: zip-slip entry validation,
per-file and total size caps, image MIME validation, count cap, temp-dir cleanup.
Then compare honestly against WB7's Drive-folder fetch (no HTTP upload limits,
queued, resumable, matches the real editorial workflow) and recommend build vs
defer for the zip path with cited reasons (feeds D-IMG-D; the transcript_file
deferral precedent applies).

## Job 6 — native import/export relation-handling audit (scopes IE-1)

Audit how the importers treat multi-value relations TODAY on update-mode rows
(categories, tags, and the transcriber keys from M-era work): sync/replace vs
merge — cite the exact lines. Define the IE-1 mini-step: explicit relation modes
(replace / merge / add-only) as import options aligned with the D25 settings-merge
vocabulary, plus export-side counterparts worth having. List tests IE-1 must add.

## Output documents

- `docs/research/images-media/00-images-media-research.md` — Jobs 1-6 evidence +
  numbered R-decisions with rejected alternatives.
- `docs/phase-02/images-media-track-plan.md` — mini-steps IMG-1 (naming concern +
  apply to cover upload + quick-upload table record action), IE-1, IMG-2 (plugin,
  gated on D-IMG-B approval), IMG-3 (zip, likely deferred), each with scope, tests,
  and which R-decisions it implements; a `D-IMG-A..D` decision list for Yoni with
  recommendations; WB7 touchpoints; explicit out-of-scope (no schema changes, no
  dependency changes until approved).
- Ledger: one pending-track note line for the IMG/IE track (do not renumber or
  touch existing rows); `current-project-state.md` gets a one-line pointer.

Commit: `docs: add images and media track research and plan`

End with exactly:

```text
Images & media track research IMG-R is complete. Waiting for Yoni decisions on D-IMG-A..D before any implementation.
```
