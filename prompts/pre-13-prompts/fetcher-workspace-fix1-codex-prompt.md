# Codex Prompt — FIX1: Fetcher Direct Import + Workspace Publishing Follow-ups

Work in the current local clone of `studioycm/PodText`.

ONE run: act on Yoni's field-test findings from FETCH1/EP1/MP2 — make the
fetcher's CSVs actually importable, add direct import from the fetcher
(create podcasts + episodes, or link to existing), fix the workspace fetch
UX (options modal, podcast matching, slug fill, prefix clear), add the
publish-date auto-fill rule everywhere publication status exists, and
upgrade raw-HTML editing to a real LTR code editor. Standing runner rules:
research note + implementation plan docs BEFORE code, no push unless asked,
no `filacheck --fix`, fixture-owned tests, `Http::preventStrayRequests()`
plus committed fixtures in every HTTP-touching test, en+he translations for
every new label, NO Composer/npm dependency changes.

PREFLIGHT MUST INCLUDE reading `docs/phase-02/ai-development-lessons.md`
IN FULL. This is now a standing rule for every run.

CANONICAL RUN ENDING (new standing rule, replaces both earlier
interpretations): implementation commit (code + docs + handoff, with
`## Commit hash` pending) → IMMEDIATELY a second docs-only commit that
stamps the implementation hash into the handoff and ledger
(`docs: backfill fetcher workspace fix1 hash`). No pending-hash debt is
left for the next run.

The handoff is `docs/phase-02/fetcher-workspace-fix1-handoff.md` with gate
outcomes written in before committing. Its Local Front Check Report is a
NUMBERED LIST OF MANUAL OPERATOR STEPS Yoni performs in a browser —
imperative voice ("open X, click Y, expect Z") — never a self-report of
what tests covered.

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test`
→ `vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change;
record every run).

## Preflight

```bash
git status --short --branch
git log --oneline -5
```

Expect FETCH1 `524a292` at or near HEAD (prompt/docs commits may sit
above). Stop on unexpected app-code dirt.

## Job 0 — carried fixes and lessons (do first)

1. Stamp FETCH1 hash `524a292` into
   `docs/phase-02/spotify-fetcher-fetch1-handoff.md` `## Commit hash` and
   the ledger's FETCH1 row (it was left "pending" — the canonical ending
   above prevents this from now on).
2. Append this lessons batch to `docs/phase-02/ai-development-lessons.md`
   (merge with existing entries, do not duplicate), and strengthen the
   file's usage note to: "Every implementation run MUST read this file in
   full during preflight."
   - The local development database is off-limits for command probes; use
     tests/sqlite first, and if a live probe is unavoidable, create a
     backup before touching anything.
   - Canonical run ending: implementation commit, then an immediate
     docs-only hash-backfill commit. No pending-hash debt.
   - The Local Front Check Report is numbered manual operator steps for
     Yoni, never a self-report of test coverage.
   - Every HTTP-touching test uses `Http::preventStrayRequests()` plus
     committed fixtures; live network in tests is never acceptable.
   - Allowlisted fetch clients resolve redirects manually (host-pinned,
     re-canonicalized per hop), cache negative results, and always carry
     throttle, descriptive UA, and timeouts.
   - When adding a sibling client/tool, retrofit existing siblings to the
     same discipline in the same run.
   - Behavior changes invalidate adjacent UI copy; sweep nearby labels,
     hints, and helper texts whenever capability changes.
   - Performance work attributes cost with probes before restructuring;
     decision gates are honored; a gate-stopped run is a legitimate
     evidence report. Slow admin UI and slow tests often share one root
     cause — check before scheduling separate fixes.
   - Memoize per page instance (property, not static) in schema-traversal
     hot paths; keep shared predicates in their owning class to avoid
     drift.
   - Prompts and docs reference actual committed filenames; reconcile and
     disclose drift in the handoff's Assumptions instead of stalling.
3. Settings cache rider: verify Spatie settings cache config
   (`SETTINGS_CACHE_ENABLED`) and its invalidation on save (Spatie's own
   clearing plus the existing `SettingsSaved` listener). If safe, add the
   flag to `.env.example` (default false locally), add a deploy note that
   production should enable it, and add/extend a test proving a save
   invalidates the cached settings read. If verification finds a real
   blocker, document it in the handoff instead of forcing it. Context:
   Yoni reports the settings page takes 2–5s on production vs 1–2s
   locally; form build is ~80 ms since SP2, so remaining cost is
   settings read + full page pipeline.
4. Patch the media-embeds guideline (`.ai/guidelines/media-embeds.md`) and
   its spec where durable rules changed: admin raw-HTML fields
   (`embed_html`, maintenance raw HTML override) are trusted-admin inputs —
   edited in an LTR code editor, saved and rendered verbatim, never
   sanitized, escaped, or length-restricted (extends D-EMB1).

## Job 1 — fetcher CSV ↔ importer compatibility (root cause known)

Yoni's finding: the fetcher's CSVs cannot be used as-is by the generic
Filament importers. Root cause (verified in code): `ContentItemImporter`
requires `reference_key` per row (`requiredMapping` +
`rules(['required','ulid','max:26'])`) and a ULID
`content_group_reference_key`; the fetcher emits blanks for new records,
so every row fails validation by design (missing references = row
failures is the standing security rule — do NOT weaken the importers).

Fix in the fetcher instead — make its CSV pair self-consistent:

- For each fetched show/episode, resolve an EXISTING record first
  (groups by Spotify show id — `resolveContentGroup()` already exists;
  episodes by `media_metadata->episode_id` / external id). Existing
  records contribute their REAL `reference_key` (imports become updates).
- New records get a PRE-ASSIGNED fresh ULID as `reference_key`, generated
  once per fetch batch; episode rows reference their podcast's key (real
  or pre-assigned) in `content_group_reference_key`, so the podcasts CSV
  and episodes CSV agree with each other.
- Verify `ContentGroupImporter` creates a group with a provided
  `reference_key` (reference keys are the portable identifiers by
  guideline); extend it only if it ignores provided keys on create.
- Document the import order (podcasts CSV first, then episodes) in the
  fetcher UI helper text (he+en) and the handoff.
- Verify episode CSV field formats against importer expectations
  (day-first dates per locale rules, duration seconds, booleans).

Tests: full round trip — fetch (faked) → export both CSVs → run both
importers → groups and items created with the pre-assigned keys, episodes
linked to their groups; second import of the same CSVs updates instead of
duplicating; an existing episode/group resolves to its real key.

## Job 2 — direct import from the fetcher (no CSV)

New action on the fetch results: ייבוא ישיר / Direct import.

- Uses THE SAME resolver as Job 1 (one resolver, two consumers).
- Confirmation modal first: summary counts (new podcasts, new episodes,
  episodes linked to existing podcasts, already-existing episodes to be
  skipped) and a confirm button. No per-row cherry-picking in v1.
- Semantics: create ContentGroups that don't exist (matched by show id),
  link episodes to their group; SKIP episodes that already exist (report,
  don't update) — create-or-link only, no updates in v1.
- Created records: DRAFT status (never published by the import), slug
  auto-generated from title with the existing generator, description
  Markdown, embed/external/duration/thumbnail-URL fields as fetched,
  `media_metadata` provenance as already emitted, reference keys
  auto-assigned by the normal model path. URL-only media — never download
  anything.
- After import: per-row outcome in the results table (created / linked /
  skipped / failed with reason) plus a summary notification; link created
  records to their edit pages where the table pattern allows.
- Runs in a transaction per row or per batch (pick one, justify in the
  plan doc); a failed row never aborts the whole batch.
- Authorization: same admin gate as the page; test it.

Tests: creates group+episodes; links to an existing group by show id;
skips an existing episode with reported status; draft status and slug
verified; reduced-mode rows import with their sparser data; failure of one
row leaves others imported.

## Job 3 — workspace Spotify fetch UX

On `EpisodeWorkspaceForm`'s Spotify fetch:

1. Replace the immediate fetch with a small OPTIONS MODAL: checkboxes for
   "fill slug when empty" (default on), "fill title prefix when empty"
   (default on), "link matched podcast" (default on, shows the matched
   podcast name when resolved), "overwrite non-empty fields" (default
   OFF — current blank-fill-only behavior stays the default). he+en
   labels with hints.
2. Resolve the podcast by Spotify show id (Job 1 resolver) and SET the
   podcast select when a match exists and the option is on.
3. Fill the slug from the fetched title via the existing slug generator —
   only when empty unless overwrite is checked (auto-generate but allow
   manual override stays the rule).
4. Add a quick-clear suffix action (X) on the title-prefix field —
   always available, independent of the fetcher.

Tests: modal defaults; each option on/off path; podcast select set on
match and untouched on no-match; slug filled only when empty; prefix
clear action empties the field.

## Job 4 — publish-date auto-fill (shared behavior, everywhere)

Yoni's rule, independent of the fetcher: choosing a published status must
auto-fill the publication date ONLY when it is empty — never overwrite an
existing date.

- Build it ONCE as reusable logic (a Field/Select macro or a small shared
  helper class — Yoni's standing directive: traits and macros for repeated
  form logic), then apply it to every form that pairs a publication-status
  field with `published_at`: the episode workspace form, the system item
  form, the podcast (ContentGroup) form, and the transcription forms
  (relation manager + standalone resource).
- The status field becomes live for this purpose; `published_at` is set to
  now (stored normally, displayed in Asia/Jerusalem per locale rules) only
  when blank.
- Tests: both branches (blank → filled; existing date → untouched) on at
  least the item workspace and one transcription form; the macro/helper
  itself unit-covered.

## Job 5 — real HTML editor for raw-HTML fields (LTR, no escaping)

Yoni: raw HTML editing needs a proper editor; HTML is code — LTR by
default; these fields are admin-trusted with NO restrictions or escaping.

- Enumerate every admin field that edits raw HTML (known: `embed_html` in
  the workspace/system item forms; `maintenance.raw_html_override` in the
  settings monolith; list any others found in research).
- Preferred component: Filament 5's native code editor field if the
  installed 5.6.7 ships one — verify via Boost `search-docs` and check
  FilamentExamples (Yoni points there explicitly: editor-field patterns
  with Tailwind helpers). If no native field exists in this version, build
  ONE shared macro (e.g. `Textarea::macro('htmlCode')`) producing an LTR,
  monospace, tall, resizable textarea with the Tailwind helper classes
  from the examples — no new dependencies either way.
- Whatever the component: `dir="ltr"` for field content, monospace,
  sensible height, no `maxLength`, and the save/render paths stay
  VERBATIM — verify no validation, sanitization, or escaping is added or
  already interferes on these fields (trusted-admin doctrine, Job 0.4).
- he+en labels/hints preserved; hint text explains the field is trusted
  raw HTML.

Tests: a full HTML document with scripts/iframes round-trips byte-exact
through the maintenance override and `embed_html` save paths; the editor
component renders with LTR direction.

## Job 6 — prove the description Markdown (small)

Yoni: "I'm not sure it's Markdown." Make it visible and proven:

- Fixture test (extend if one exists): a rich API `html_description`
  (bold, links, paragraphs, line breaks) converts to real Markdown syntax
  (`**bold**`, `[text](url)`, blank-line paragraphs) in the results row,
  the CSV cell, and the workspace fill.
- In the results table, make the full description viewable as RAW Markdown
  text (expandable/tooltip per the existing table pattern) so the syntax
  itself is visible, not just plain prose.
- Front-check step: fetch an episode whose Spotify description contains
  links/formatting and see the Markdown syntax in the expanded cell.

## Tests

Per job above; plus existing fetcher/importer/workspace/maintenance suites
stay green. Full gate per header order.

## Docs and handoff

Ledger row `FIX1 - Fetcher direct import and workspace publishing fixes`;
`current-project-state.md`; research/plan docs BEFORE code
(`docs/research/fetcher-workspace/00-fix1-research.md`,
`00-fix1-implementation-plan.md` — research includes the raw-HTML field
inventory and the importer reference-key verification); handoff per header
rules. Local Front Check Report (operator steps, imperative): fetch
without credentials and export both CSVs → import podcasts CSV then
episodes CSV through the admin importers → rows import cleanly; rerun the
same import → updates, no duplicates; use Direct import on a fresh fetch →
podcasts/episodes appear as drafts, linked; workspace fetch → options
modal, podcast matched, slug filled, prefix clear works; set an item to
published with empty date → date fills; with a date → untouched; edit
maintenance raw HTML and embed HTML in the new editor → LTR, saves
verbatim; expand a fetched description → Markdown syntax visible.

Commit: `feat: add fetcher direct import and workspace publishing fixes`
Then the canonical docs-only backfill commit (see header).

End with exactly:

```text
Fetcher and workspace FIX1 is complete. Waiting for Yoni review before continuing.
```
