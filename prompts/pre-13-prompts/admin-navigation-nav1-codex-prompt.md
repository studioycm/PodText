# Codex Prompt — NAV1: Admin Navigation Restructure + Deferred Badges

Work in the current local clone of `studioycm/PodText`.

ONE small implementation run, immediately after IMG-B. Standing runner rules:
research note + implementation plan docs BEFORE code, full sequential quality
gate incl. `git diff --check`, no push unless asked, no `filacheck --fix`,
fixture-owned tests, en+he translations, RTL-safe UI, NO Composer changes.
The handoff is a COMMITTED MARKDOWN FILE
(`docs/phase-02/admin-navigation-nav1-handoff.md`) ending with `## Commit
hash` and a numbered `## Local Front Check Report` written as MANUAL operator
steps. Per the standing backfill rule, backfill the IMG-B commit hash from
git log into the IMG-B handoff/ledger row.

Test policy: targeted tests while iterating. FINAL GATE ORDER (new, from the
IMG-B lesson): (1) pre-gate requirements sweep — re-read this prompt's job and
test lists and confirm each item is implemented/covered BEFORE starting the
gate; (2) `vendor/bin/pint --test` (fix + recheck if needed); (3)
`vendor/bin/filacheck`; (4) `npm run build`; (5) the FULL suite LAST, as
`php artisan test --profile` (the gate run doubles as the profiling run) —
cheap checks run first so any change they force happens before the one
expensive run. "Once" means once GREEN on the FINAL code state: any app/test
change after a green suite invalidates it — fix, verify targeted, re-enter
the gate from Pint, and run the suite again; record every full run. ~8-10
quiet minutes per run; never interrupt or parallelize; investigate slowness
ONLY through the diagnosis job below.

## Preflight

```bash
git status --short --branch
git log --oneline -5
```

Clean tree; the IMG-B `feat:` commit expected in history. Stop on dirt.

## Research first (Boost + FilamentExamples, short batches)

- Filament 5.6 panel-level navigation configuration: `->navigationGroups()`
  with `NavigationGroup::make()` (labels, icons, collapsible, ORDER), and how
  ungrouped items sort relative to groups.
- The exact Filament 5.6 API for DEFERRED navigation badges and whether a
  panel/central place can defer ALL badges by default (Boost `search_docs`:
  deferred navigation badges / lazy badge). Cite the API found; if no global
  toggle exists, apply the per-item deferred API consistently through the
  central map.
- How the Curator plugin's navigation registration accepts group/sort
  overrides (it currently sits in the content group).
- The existing `app/Filament/Support/AdminNavigationOrder.php` central map and
  its nav-completeness test — the restructure must flow THROUGH this central
  map plus the panel provider, not via scattered per-resource literals.

## The target structure (Yoni spec — exact order)

1. UNGROUPED, FIRST: the episode workspace create page — label "New episode",
   Hebrew SHORT: "פרק חדש".
2. UNGROUPED, just before the groups: Form submissions (Hebrew
   "רשומות טפסים") with a DEFERRED count badge; then Media (Curator).
3. Group "Content management" / "ניהול תוכן": Podcasts, Episodes,
   Transcriptions.
4. Group "Taxonomy management" / Hebrew simpler than taxonomy — use
   "ניהול סיווג" (Yoni may adjust the lang value later): Transcribers
   (authors), Categories, Tags.
5. Group "Site management" / "ניהול אתר": Homepage sections, Public content
   settings, Admin UX settings, Settings backups, Importer settings (moves
   OUT of the ייבוא group; record the move in the WB-related docs line —
   future importer tools will get their own placement in TOOLS1).

Notes:

- Form submissions are the submissions of the PUBLIC form page (Yoni
  confirmed). Locate the existing storage first (the model/table the public
  form page writes to) and its admin resource. If an admin resource already
  exists, restructure ITS nav placement and add the deferred count badge. If
  only the model/table exists with NO admin resource, CREATE a minimal
  read-only admin resource for it in this run (list + view, searchable
  columns, day-first date columns, translated labels, no create/edit) so the
  nav item is real. Badge value: use an unread/unhandled flag if the schema
  already has one, otherwise total count — deferred either way.
- Naming convention (record as the durable rule): the WORKSPACE is the
  unmarked default everywhere — nav item, list header actions, and row
  actions say "New episode"/"פרק חדש" (or "Edit"/"עריכה"); the CLASSIC
  create/edit surfaces get a "(system)" / "(מערכת)" suffix wherever both are
  visible. Apply to the episodes list header action pair and row action pair
  from EP1.
- Badges: defer by default from ONE central place (panel provider or the
  central map helper per the researched API). The form-submissions count
  badge must be deferred; any existing badges join the same mechanism.
- All labels/group names via lang keys (en+he). Group icons via the Heroicon
  enum. RTL-safe.

## Job — test-suite timing diagnosis (measure and report; no behavior changes)

Yoni wants to know what the suite slowness is actually made of. Known context:
an earlier profiling pass found the settings-page save tests dominating
(~60% of total runtime, one test alone ~110s); a known order-dependent flake
(`PublicFrontIconRegistryTest`) is recorded in the lessons doc; `--parallel`
adoption was evaluated as safe (per-process SQLite `:memory:`) and parked.

1. The final gate's full run uses `--profile`: record total wall time, test
   count, and the slowest-tests list verbatim in the handoff.
2. For the top offenders, inspect the test + exercised code and name the
   actual cost drivers with evidence: unfaked services (HTTP, snapshots,
   queues, notifications), password hashing per user creation, repeated
   heavy form fills against the full settings validator, per-test setup that
   could be shared, sleeps/retries/backoffs running for real.
3. Verify `phpunit.xml` test-env optimizations: hashing rounds
   (BCRYPT_ROUNDS or the current hashing driver equivalent), mail/queue
   drivers, and similar zero-risk toggles. You MAY apply a config-only
   test-env fix (e.g. low hashing rounds) if it is missing and you prove the
   gain with a before/after on ONE targeted slow test file — no extra full
   suite runs for measurement, and no app-code or test-behavior changes.
3b. Add three durable entries to `docs/phase-02/ai-development-lessons.md`
   (from the IMG-B gate history of four full runs): (a) the final gate runs
   cheap checks FIRST — pint, filacheck, build — and the full suite LAST, so
   changes forced by cheap checks happen before the expensive run; (b) a
   pre-gate requirements sweep against the active prompt's job/test lists is
   mandatory before starting the gate; (c) the final green suite must
   reflect the FINAL code state — any change after a green run invalidates
   the gate and re-enters it from Pint.
4. Everything else becomes a WRITTEN `TS1` proposal in the handoff: ranked
   fixes with expected savings (service fakes in the hot tests, shared
   fixtures, splitting the 110s-class tests, and the parked `--parallel`
   adoption with its safety rationale restated), plus whether the icon
   registry flake's suspected static-state cause showed up in evidence.

## Tests

Update the nav-completeness/central-map test to assert the NEW structure:
group membership, group order, ungrouped-first placement, translated labels.
Add: classic-vs-workspace action labels carry the (system) suffix convention;
badge configuration is deferred (assert as far as the API allows without
brittleness). Existing admin tests keep passing. Full sequential gate.

## Docs and handoff

Ledger row `NAV1 - Admin navigation restructure and suite timing diagnosis`;
`current-project-state.md` (one line on the timing findings too); research +
plan docs (`docs/research/admin-navigation/00-nav1-research.md` or similar);
handoff file per header rules with a `## Suite Timing Report` section (the
profile numbers + cost drivers + the ranked TS1 proposal) and manual operator
checks (open the
admin: order reads פרק חדש first, then רשומות טפסים with a badge that loads
deferred, then מדיה, then the three groups in order with correct members;
episodes list shows the workspace as default action and classic with
(מערכת); Hebrew RTL + light/dark; nothing lost — every previously reachable
page still reachable).

Commit: `feat: restructure admin navigation groups and defer badges`

End with exactly:

```text
Admin navigation NAV1 is complete. Waiting for Yoni review before continuing.
```
