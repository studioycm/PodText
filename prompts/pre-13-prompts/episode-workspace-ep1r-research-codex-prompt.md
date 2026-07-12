# Codex Prompt — EP1-R: Episode Workspace Research + Plan (docs only)

Work in the current local clone of `studioycm/PodText`.

ONE research-and-planning run. NO app code, NO migrations, NO composer changes —
the output is a research doc, a plan doc, and a decision register. Clean-tree
preflight; stop on dirt. Docs-only validation: `git status --short` +
`git diff --check` (no test suite, no full gate — recorded docs-only lesson).
Read-only probes are allowed and REQUIRED where named below; any probe scratch
files are cleaned up and never committed. Fresh chat. Boost `search-docs` +
FilamentExamples in short batches. Commit with a `docs:` prefix.

The Research/Plan contract applies in full: numbered R-decisions with cited
evidence (vendor source/docs/probe output) plus at least one rejected
alternative each; the plan references R-numbers; framework-native capabilities
win by default over hand-rolled patterns.

## What EP1 is (Yoni-decided — the research serves these, it does not reopen them)

A CUSTOM episode workspace page — create and edit an episode PLUS its single
"main" transcription on one page. It does NOT replace the standard
ContentItemResource pages, but it BECOMES the default edit action on the
episodes table (classic full edit demoted to a secondary action), and create
gets a header action + a main-navigation item. Decisions already recorded:

- D-EP1 single-transcription lens: the page targets the FEATURED transcription;
  no featured -> the same effective resolution the public uses (latest
  published, else newest draft); zero transcriptions -> the transcript section
  is the create form. A transcription created here is pinned as featured.
  Saving an unpinned target adopts it as featured. A "replace transcription"
  action (modal + confirmation) is the only way the target changes. When other
  transcriptions exist, at most a one-line hint appears (hideable by setting).
- D-EP2 architecture race (Job 1 below): native relationship-bound section
  first, create-Wizard second, save-draft-first last.
- D-EP3 title prefix: new `title_prefix` column; auto-fills from the selected
  podcast name, editable; combined "[prefix] + [episode]" is a LIVE PREVIEW
  only, never stored in `title`; card/page templates use prefix ?? podcast name
  where combined display is enabled.
- D-EP4 no generic JSON extras column: queryable/exported fields get columns;
  the MP3 URL uses the EXISTING `direct_media_url` column; media_metadata-style
  registries only for non-queryable metadata, per-field decisions.
- D-EP5 defaults: transcription presentation modes collapsible|modal|slideover
  with collapsible as default; `transcription_mode` setting single|multi with
  single for this site; language_code field hidden behind a setting, default
  `he`; slug keeps UX3 smart behavior plus a CLEAR hint action and a full-URL
  preview; embed input accepts URL or pasted iframe code and stores URL-ONLY
  (extract src, validate provider allowlist — raw HTML is never persisted);
  categories+tags included as a collapsed section; advanced collapsed area for
  durations + language + featured selector; public-visibility checklist panel;
  inline audio player when the MP3 URL validates; a Spotify-link field with a
  "fetch into form" action.

## Job 1 — the architecture race (the core research)

Probe Filament 5.6.7's real capabilities (vendor source + Boost docs +
FilamentExamples), in this order, and pick the FIRST that survives:

1. **Relationship-bound section**: a layout component (`Section`/`Group`)
   with `->relationship('featuredTranscription')` editing the related
   Transcription's fields inside the episode form, saving atomically on submit.
   MUST answer with evidence: does it CREATE the related record when empty on
   both create and edit pages; how does `content_items.featured_transcription_id`
   (a belongsTo on the ITEM side) get set, and how does the new Transcription
   get its `content_item_id` (order-of-save problem — cite Filament's
   relationship save hooks); does it respect `mutateRelationshipDataBefore*`;
   does it validate independently; can the section render inside
   modal/slideover containers per the display setting. If the belongsTo
   direction fights record creation, evaluate flipping the bound relation to a
   dedicated `mainTranscription()` HasOne on ContentItem (scoped
   `->ofMany()`... or FK-based) that stays synchronized with
   featured_transcription_id — cite exactly what Filament supports for HasOne
   vs BelongsTo binding.
2. **Create-Wizard fallback**: episode created at step-1 completion,
   transcription at step 2 — cite the Filament wizard + create-record hooks
   that allow committing the record between steps.
3. **Save-draft-first fallback**: auto-save episode as draft when "add
   transcription" is used mid-create, reload workspace with errors preserved.

Record the winner as R-decision with the losing options' concrete blockers.

## Job 2 — page type, routing, actions

Custom Filament page vs custom Resource page (ViewRecord/EditRecord subclass)
vs standalone Livewire page for the workspace: which gives form-with-relations,
header actions, per-record routing, and panel navigation registration with the
least custom glue. Table integration: make the workspace the DEFAULT edit
record action on the episodes table and relation managers where episodes list;
classic edit becomes secondary (label from lang files); create-workspace nav
item + header action. Cite the Filament APIs for overriding a resource's
default record URL / row action.

## Job 3 — settings and per-user preference

Design the `AdminUxSettings` Spatie settings class (approved package):
transcription presentation mode (collapsible|modal|slideover, default
collapsible), transcription_mode (single|multi, default single), show hint line
(bool), show language_code (bool), TB1 picker container (modal|slideover —
separate key, reserved now), media naming strategy (slug|reference_key|
slug_key, default slug — reserved for the IMG arc). Per-user override for the
presentation mode: evaluate a `users.admin_preferences` json column + a
mode switcher ON the workspace persisting per user (site setting = default);
if that costs more than a small bounded slice, plan global-only for EP1 and
record the per-user upgrade as a follow-up. Cite how the existing
PublicContentSettings page registers a settings class, and note the settings
migration path.

## Job 4 — form surfaces inventory (map, don't invent)

For each workspace section, name the exact existing fields/rules/components to
reuse: podcast select + inline create/edit option forms
(RelationshipOptionForms precedent); title + title_prefix (NEW column —
migration note: nullable string, MySQL-verified) + combined live preview
(Filament live()/placeholder or a small Livewire computed — cite the cheapest);
SlugInput (UX3) + clear action + URL preview; media fields (media_url,
direct_media_url, embed_url + embed_provider, external_thumbnail_url) with
ContentItemMediaRules; the iframe-src extraction helper (transient input,
dehydrated(false)-class helper field — never persisted); Spotify-link field +
fetch action calling a NEW app service wrapping the WB1 SpotifyConnector
(design the service boundary here; the same service later powers the SF1 bulk
tool — episode lookup by URL/ID, show lookup, html_description handling);
audio preview player (owned Blade/Alpine, renders when URL passes validation);
taxonomy section (categories select + spatie tags, collapsed); transcript
section = Transcription form fields (MarkdownEditor for transcript_markdown —
NO Repeater, NO rich-HTML storage; title, status, language behind setting;
transcriber authors select); publication section (status, published_at,
original_published_at, day-first + Asia/Jerusalem per standing rules);
visibility checklist panel (group published? episode published? effective
transcription published? — read from existing model state, no new queries per
keystroke); sticky footer actions (save draft / publish / save-and-add-another).

## Job 5 — presenter/template touch for title_prefix

Find where card/page templates render the combined episode+podcast title today
(M5 card template parts / presenters) and specify the exact change: combined
display uses `title_prefix ?? group title`. List the template/presenter files
and the tests that must change. This is EP1 scope, not a separate step.

## Job 6 — paste-cleanup and [] conventions (bounded)

Evidence-only pass: what Filament 5's MarkdownEditor does with rich-HTML paste
today (vendor behavior); options for converting Google-Docs/Colab paste to
Markdown client-side; note that transcript `[]` styling conventions exist in
Yoni's source documents and their real shapes arrive with the pending WB format
probe — DESIGN the hook point (a paste-transform boundary), defer conversion
rules to the probe's findings. No implementation.

## Output documents

- `docs/research/episode-workspace/00-ep1-research.md` — Jobs 1-6 evidence +
  numbered R-decisions with rejected alternatives (the Job 1 race result is
  R1).
- `docs/phase-02/episode-workspace-plan.md` — EP1 implementation plan:
  migration list (title_prefix, settings class, optional user preferences),
  page + actions + settings + presenter changes, test list (atomic
  create-with-transcription; edit adopts featured; replace action; mode
  setting variants render; checklist truthfulness; embed extraction never
  stores HTML; title_prefix preview + template fallback; slug clear action;
  Spotify fetch fills form fields with a faked service; RTL), explicit
  out-of-scope (SF1 bulk tool, images track, IE-1), and the D-EP register
  restated.
- Ledger: one pending-track note line for EP1 (do not renumber existing rows);
  `current-project-state.md` one-line pointer.

Commit: `docs: add episode workspace research and plan`

End with exactly:

```text
Episode workspace research EP1-R is complete. Waiting for Yoni review of the plan before EP1 implementation.
```
