# Single-Lens LENS1 Handoff

Date: 2026-07-14

## Scope

Executed only
`prompts/pre-13-prompts/single-lens-lens1-codex-prompt.md`, prompt version
v1 (2026-07-13).

LENS1 applies an episode ontology when `transcription_mode = single` while
leaving multi-mode strings, stored settings, record-count semantics, and
multi-mode branches unchanged. No Composer or npm dependency changes were
made.

During implementation the operator clarified that the episode Transcriptions
relation manager and the item/group admin tables intentionally expose their
featured/count/history operational context. Those surfaces remain unchanged.
The clarification supersedes any broader leak-audit interpretation that would
hide or relabel those columns.

## Commit hash

`2299c71 feat: apply single transcription ontology across public and admin`

## ROLES1 Verification

- ROLES1's implementation commit is present:
  `9cd7349 feat: add user roles and multi-transcription visibility gates`.
- `docs/phase-02/roles-gates-roles1-handoff.md` stamps `9cd7349` under
  `## Commit hash`.
- The mini-step ledger also stamps `9cd7349` in the ROLES1 row.

## Requirement Classification

- Implemented: the D-LENS1 contract in the transcription model spec; a
  mode-aware English/Hebrew label resolver with base-key fallback; single-mode
  effective-only public policy clamping; distinct-effective-episode contributor
  and podcast counts; hard episode-card transcription-count suppression;
  effective-only viewer behavior even for forged historical references;
  episode-language card, page, workspace, resource, form, action, notification,
  and template-editor labels; localized shared second-row creation refusal;
  resource/direct/relation-manager first-row auto-feature coverage; importer
  guard coverage; a transient sanctioned workspace-replacement exemption; one
  current standalone-resource row per episode in single mode; a
  mode-independent, super-admin-only history filter; hidden standalone
  set-featured action in single mode; state, ledger, research, plan, handoff,
  and regression tests.
- Already existed: first-row auto-feature logic in `Transcription::created()`;
  the public effective-transcription selector; public correlated aggregate
  subqueries; the workspace replace-and-adopt flow; ROLES1 mode/role gates;
  the standalone Transcriptions resource's leading related-item column; the
  standalone table's absence of a featured column; the relation manager's
  operational history; and the item/group table featured/count context.
- Deferred: no LENS1 requirement is deferred. Wider redesign of the intentional
  relation-manager and item/group operational surfaces is outside LENS1 and
  would require a new operator decision.
- Not applicable: Composer/npm dependency changes, schema migrations, public
  analytics, dashboard metrics, a new import format, changes to row-level CSV
  headers, production probes, and live mail/network tests.
- Blocked: none.

## Behavior Delivered

- Single-mode public display and count policy is always effective-only even if
  stored multi settings still say `all_published`.
- Single contributor and podcast transcription counts are counts of distinct
  public episodes reached through each episode's effective transcription.
  Extra historical rows cannot increase those numbers.
- Episode cards return no transcription-count part in single mode even when a
  stored template still contains it.
- Cards use `episodes` / `פרקים`; fuller surfaces use
  `transcribed episodes` / `פרקים מתומללים`; contributor episode lists use
  `Transcribed episodes` / `פרקים שתומללו`; podcast latest dates use
  `latest episode :date` / `פרק אחרון :date`.
- Multi mode selects the original translation keys and retains the existing
  queries and presentation branches.
- The shared model `creating` hook refuses an unsanctioned second row in single
  mode with `This episode already has its transcript` /
  `לפרק כבר יש תמלול`. Direct model, Filament resource, relation-manager, and
  importer persistence all pass through that guard.
- `startFreshWorkspaceTranscription()` marks only its new unsaved model as a
  sanctioned replacement, saves it through normal events, and adopts it. No
  global or static bypass exists.
- The standalone Transcriptions resource shows the current editable row in
  single mode: a same-episode featured row first, otherwise latest published,
  otherwise latest row. Super-admins can remove that named scope with the
  history filter in either mode; admins never see the filter.
- The standalone set-featured row action is hidden in single mode. The episode
  relation manager and item/group admin table columns remain untouched by the
  operator's direction.

## Files Changed

- Mode/creation foundation:
  `app/Support/Transcriptions/TranscriptionModeLabel.php`,
  `app/Support/Transcriptions/SingleTranscriptionLens.php`,
  `app/Models/Transcription.php`, and `app/Models/ContentItem.php`.
- Public policy, queries, cards, pages, and views:
  `app/Support/PublicContent/PublicTranscriptionPolicy.php`,
  `app/Support/PublicContent/PublicTranscriptionAggregates.php`,
  the three public card presenters,
  `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`,
  `app/Filament/Public/Pages/ShowContentItem.php`, and the affected public
  contributor/group/section Blade views.
- Admin resource/workspace copy:
  `app/Filament/Resources/Transcriptions/*`,
  `app/Filament/Resources/ContentItems/Schemas/EpisodeWorkspaceForm.php`,
  `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`,
  `app/Filament/Resources/Support/RelationshipOptionForms.php`,
  `app/Filament/Actions/EditEffectiveTranscriptionAction.php`, and
  `app/Filament/Pages/AdminUxSettings.php`.
- Locales: `lang/en/admin.php`, `lang/he/admin.php`, `lang/en/public.php`, and
  `lang/he/public.php`. Existing base values were not edited; new single-mode
  variants were added.
- Tests: `tests/Feature/SingleTranscriptionLensTest.php`, `tests/Pest.php`, and
  the affected model, public-front, workspace, roles-gates, admin-resource,
  item-page, visibility, contributor, and import/export regression files.
- Docs: `docs/research/single-lens/*`,
  `docs/phase-02/transcriptions-model-spec.md`, this handoff,
  `docs/phase-02/current-project-state.md`, and the mini-step ledger.

## Tests Added Or Updated

- Added `tests/Feature/SingleTranscriptionLensTest.php` for first-create
  auto-feature paths, localized second-create refusal, resource-visible error,
  workspace replacement, distinct single counts versus multi row counts,
  English/Hebrew variants, stored-template count suppression, forged viewer
  selection normalization, current-row table scoping, history filter roles,
  and single/multi set-featured action visibility.
- Added a single-mode importer regression proving a second row reaches the
  shared localized model guard.
- Added `setTestTranscriptionMode()` to the Pest bootstrap and explicitly opted
  legacy multi-row fixtures into multi mode. Tests that exercise single-mode
  leakage switch back explicitly.
- Updated the workspace section-label expectation for the single episode
  variant. No HTTP-touching test permits stray requests; mail remains faked.

## Label Inventory And Leak Audit

- The required exhaustive bilingual before/after table is committed in
  `docs/research/single-lens/00-lens1-implementation-plan.md`.
- The research and plan include every checked leak-audit surface and its
  verdict: public policy/aggregates/cards/pages/viewer, standalone resource,
  workspace, relation manager, item/group tables, dashboard/navigation,
  import/export headers, and template editor/runtime.
- Translation duplicate-key scanning passed for both locale pairs.
- Review note: the admin standalone “current row” intentionally means the
  editable/workspace row, not only a publicly effective row, so draft-only and
  newly replaced episodes remain visible to administrators.
- Review note: the standalone resource did not have a featured column before
  LENS1. Multi mode restores its existing set-featured row action; the featured
  columns the operator asked to retain are on the item/group operational
  tables.

## Local Front Check Report

1. Run `php artisan users:assign-role <your-email> super-admin` for the review
   account if it is not already a super-admin.
2. Open Admin UX settings as super-admin, select `single`, and save.
3. Open the public contributor directory in Hebrew and expect each contributor
   count to use `פרקים`.
4. Open a podcast card that has a published episode and expect its latest-date
   label to start with `פרק אחרון`.
5. Open contributor and podcast fuller surfaces and expect episode-based
   counts; add a replaced historical row and expect the displayed number not
   to increase.
6. Open an episode card backed by a stored template containing its
   transcription-count part and expect no transcription count to render.
7. Open an episode with replaced transcript history and expect no public
   transcript switcher or historical transcript title.
8. Log in as admin and open the standalone Transcriptions resource.
9. Expect one current row per episode, expect the episode column first, expect
   no history filter, and expect no standalone set-featured action.
10. Open the episode relation manager and the item/group admin tables and
    expect their intentional featured/count/history operational context to
    remain available under the existing ROLES1 permissions.
11. Start creating another standalone transcription for an episode that
    already has one, submit the form, and expect
    `לפרק כבר יש תמלול` without a new row.
12. Log in as super-admin, replace the workspace transcript with a fresh row,
    and return to the standalone Transcriptions resource.
13. Enable `היסטוריית תמלולים` and expect both the fresh current row and the
    replaced row; disable it and expect only the fresh row.
14. Change the locale to English, repeat the blocked create, and expect
    `This episode already has its transcript`.
15. Set mode to `multi` as super-admin and save.
16. Expect public counts to return to transcription-record semantics, base
    multi strings to return, and the standalone set-featured action to return.
17. Expect the relation manager and item/group operational columns to remain
    unchanged across both modes.
18. Set mode back to `single` and save before ending the review.

## Commands Run

- Preflight/read-only inspection:
  `git status --short --branch`; `git log --oneline -5`; full reads of
  `AGENTS.md`, `docs/phase-02/ai-development-lessons.md`, current state, ledger
  head, newest handoffs, the active prompt, named spec, relevant guidelines,
  implementation code, tests, and installed vendor source.
- Research tools: Laravel Boost application/version, schema, and installed-doc
  searches; FilamentExamples short query batches plus refined searches for
  table query modification, filters, conditional visibility, and table tests.
  FilamentExamples exposed search snippets only, not full example source.
- Initial test iterations:
  the first sandboxed affected-suite attempt could not open Pest's local socket
  and also exposed legacy fixtures that assumed multi mode; it was rerun outside
  the sandbox after those fixtures opted into multi explicitly.
  The first escalated affected suite passed 116 of 117 tests and failed the
  workspace settings default because the whole file had been forced to multi;
  the fixture was narrowed and `EpisodeWorkspaceTest` then passed 13 tests /
  113 assertions.
  The initial LENS1 file passed 5 tests / 58 assertions before the explicit
  resource refusal assertion was added.
  That resource assertion first failed because the model error key did not map
  to Filament's nested form path; the shared error was mapped to
  `data.content_item_id`, and the focused test passed 1 test / 9 assertions.
  The first combined importer check passed 5 of 6 tests but expected English
  while the active locale was Hebrew; the test now selects English explicitly
  and its focused rerun passed 1 test / 2 assertions.
- Formatting and static checks:
  `vendor/bin/pint --dirty` fixed four changed files;
  token-level duplicate literal-key scan passed all four edited locale files;
  `php -l` passed all changed/new PHP files; `git diff --check` passed.
- Final affected regression set:
  `php artisan test` with the LENS1, model, public-policy, public rendering,
  contributor, item-page, visibility, admin-resource, import/export, workspace,
  and roles-gates files passed 123 tests / 1,303 assertions.
- First final-gate attempt: requirements sweep, Pint, FilaCheck (0 issues), and
  the build passed. The full suite then failed 2 of 496 tests after 494 tests /
  4,378 assertions passed in 362.473s. Both failures were legacy card-renderer
  assertions that requested the unchanged base multi translation while running
  under the new default single mode. A focused rerun of the two files confirmed
  the same 2 failures after 36 of 38 tests passed. Only those two legacy
  rendering tests now select multi explicitly; their focused rerun passed.
  The restarted Pint gate then failed on import/style normalization in those
  two test files; Pint formatted only those files, and the gate restarted again
  from the requirements sweep.
- Requirements sweep: passed; the prompt/spec matrix, 269-row label inventory,
  leak-audit list, localized variants, mode branches, model guard, admin scope,
  tests, and operator clarification were present; no dependency-file diff,
  base-translation deletion, or relation-manager/group-table diff was found.
- Final ordered gate on the final implementation state:
  `vendor/bin/pint --test` passed; `vendor/bin/filacheck` passed with 0
  issues; `npm run build` passed; full `php artisan test` passed last.

## Gate Outcomes

- Requirements sweep: passed.
- Pint: `vendor/bin/pint --test` passed.
- FilaCheck: `vendor/bin/filacheck` passed with 0 issues.
- Build: `npm run build` passed.
- Full suite, run last and without interruption or parallelization:
  `php artisan test` passed.

## Tooling Notes

- Laravel Boost was available and returned installed-version guidance. It
  confirmed PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest
  4.7.4, and Tailwind CSS 4.3.2.
- FilamentExamples provided search/snippet results only; no source/read/detail
  tool was exposed, so this is not described as deep-source research.
- Pest's browser support requires a local socket that the filesystem sandbox
  denied. Relevant and final Laravel test commands were therefore rerun with
  approved escalation. No wrapper was disabled.
- The Laravel, Livewire, Pest, and Spatie/PHP skills guided lifecycle placement,
  mode-safe component behavior, behavioral tests, and formatting conventions.

## Assumptions And Review Points

- “Effective” remains public published-featured/latest-published selection for
  public rendering and counts. The admin default row is the current editable
  featured/latest row so draft-only episodes do not disappear.
- The workspace's fresh replacement is the only operation allowed to create a
  second row in single mode; its exemption is transient to that model instance.
- Base translations are the multi-mode contract. New helpers may return a
  single variant or fall back, but never mutate stored settings or strings.
- The operator's relation-manager and item/group table clarification is treated
  as the final scope decision for those surfaces.

## Deferred Issues

- None inside LENS1.
- Any future attempt to hide or redesign relation-manager or item/group
  operational history/count context needs a new explicit operator request.

## Current Git Status Before Final Gate

`main...origin/main [ahead 1]` with only the intended LENS1 code, tests,
translations, research, state, ledger, and handoff changes. No Composer/npm
dependency files are modified.
