# Codex Prompt — EP1: Episode Workspace Implementation

Work in the current local clone of `studioycm/PodText`.

ONE implementation run executing the committed, audited plan
`docs/phase-02/episode-workspace-plan.md` (sections 1-9 + its test plan),
backed by `docs/research/episode-workspace/00-ep1-research.md` (R1-R13). Read
BOTH before code — the plan is the contract; this prompt only adds the
corrections below. Standing runner rules: full sequential quality gate incl.
`git diff --check`, no push unless asked, no `filacheck --fix`, fixture-owned
tests, en+he translations, RTL-safe UI, NO Composer changes. Handoff ends with
`## Commit hash` and a numbered `## Local Front Check Report`; per the
standing backfill rule, backfill the previous run's (IMG-A) commit hash from
git log into its handoff/ledger row.

Test policy: iterate with targeted tests only; run the FULL `php artisan test`
exactly ONCE at the final gate after Pint (expect ~8-10 quiet minutes — known
issue, out of scope; do not interrupt or parallelize).

## Preflight

```bash
git status --short --branch
git log --oneline -6
```

Clean tree required. The IMG-A commit
(`feat: add image naming foundation and curator media library`) is EXPECTED in
history — see correction 1 for both cases. Stop on unexpected app-code dirt.

## Corrections and additions to the plan (Fable audit — these override)

1. **AdminUxSettings ownership**: if IMG-A is in history, the class and its
   settings migration ALREADY exist with `media_naming_strategy`. EXTEND them:
   a second settings migration adding `transcription_presentation_mode`,
   `transcription_mode`, `show_episode_workspace_hint_line`,
   `show_episode_workspace_language_code`, `tb1_picker_container` (reserved),
   with the plan's defaults — do not recreate or rename anything IMG-A made.
   If IMG-A is absent, create the class with ALL six keys and say so in the
   handoff. EP1 owns the admin-UX SettingsPage either way (translated labels,
   helper text per cross-cutting rules).
2. **workspaceTranscription() HasOne guard**: the targeting logic
   (featured -> effective/latest published -> newest draft) is
   instance-conditional. This relation is a WORKSPACE-ONLY boundary: never
   eager-load it in tables, queries, or collections; add a PHPDoc warning on
   the relation. Tests must cover each targeting branch separately: featured
   set; no featured with published rows; no featured with only drafts; zero
   transcriptions (create path). Also test the interplay with the EXISTING
   `Transcription::booted()` first-transcription auto-pin: creating the first
   transcription through the workspace ends featured-pinned exactly once, and
   the afterCreate/afterSave adopt hooks are idempotent with it.
3. **Replace-transcription action is dual-mode**: the confirmed modal offers
   (a) select another EXISTING same-item transcription (visible when others
   exist, per the plan) and (b) "start a fresh transcription" — creates a new
   empty transcription, pins it as featured, the previous one stays attached
   and unpinned. Both paths confirm before applying and reload workspace
   state. Test both.
4. **EpisodeSpotifyLookup**: expanding `SpotifyHttpClient` for description /
   html_description fields is allowed WITHOUT composer changes (research Job 4
   finding). Fetch-fill rules follow the plan's fill-targets list exactly —
   notably: never overwrite non-blank `title`/`title_prefix` without explicit
   confirm, and never touch `embed_html`.
5. **D-EMB1 scope discipline**: the media component raw mode + precedence +
   nowhere-else guarantee are already specified in the amended
   `docs/phase-02/media-embed-spec.md` and `.ai/guidelines/media-embeds.md` —
   implement to THOSE documents; do not re-litigate them. The
   nowhere-else tests from the spec are mandatory.

## Scope reminders (from the plan — not repeated here)

Schema (title_prefix, embed_html), workspace HasOne + adopt hooks, shared
workspace form schema with all sections, CreateEpisodeWorkspace /
EditEpisodeWorkspace resource pages + routing (fixed route before wildcard),
navigation item + list header action, recordUrl + default action overrides on
BOTH episode list surfaces with classic edit demoted to secondary,
replace action, embed helpers (EpisodeEmbedInputNormalizer), Spotify lookup +
fetch action, public title-prefix touch through a central display-title
boundary, paste-cleanup boundary ONLY if it stays small (else defer per plan
section 8), the full plan test list, and the plan's out-of-scope list.

## Docs and handoff

Ledger row `Step EP1 - Episode workspace` after the latest completed row;
`current-project-state.md`; mark the D-EP register statuses in the plan doc as
implemented where done; handoff with root decisions, `## Commit hash`, IMG-A
hash backfill, and a numbered `## Local Front Check Report`: create a new
episode WITH transcript in one submit (both records exist, featured pinned);
open an existing episode from the table row (lands on the workspace; classic
edit still reachable as secondary); edit and save both sides in one submit;
replace via modal — both options; flip the presentation setting through
collapsible/modal/slideover and see the transcript UI follow; toggle the hint
line setting; paste a Spotify iframe -> keep as embed_html -> public item page
renders it verbatim and it wins over embed_url; extract-src fills embed_url;
Spotify fetch fills the form; combined-title live preview + public card shows
prefix-based combined title with group-title fallback; slug clear action +
URL preview; visibility checklist matches reality for a draft group; audio
player appears for a valid MP3 URL; Hebrew RTL + light/dark; classic
create/edit pages regression-free.

Commit: `feat: add episode workspace with single transcription lens`

End with exactly:

```text
Episode workspace EP1 is complete. Waiting for Yoni review before continuing.
```
