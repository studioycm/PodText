# NAV1 Admin Navigation Implementation Plan

Date: 2026-07-12

This plan follows `prompts/pre-13-prompts/admin-navigation-nav1-codex-prompt.md` and
the research in `docs/research/admin-navigation/00-nav1-research.md`.

## Guardrails

- Execute only NAV1 in this session.
- No Composer changes.
- Keep the existing public form submissions resource; do not create a duplicate
  resource/model.
- Route navigation structure through `AdminNavigationOrder` plus the admin panel
  provider, not scattered per-resource literals.
- Use translated English and Hebrew labels.
- Keep final gate order: pre-gate requirements sweep, Pint, FilaCheck, build, then
  full suite last as `php artisan test --profile`.

## Implementation Steps

1. Expand `AdminNavigationOrder` from sort-only to a central item map:
   sort, group key, optional navigation label key, and badge-deferred flag.
2. Update `UsesAdminNavigationOrder` so resources/pages receive navigation sort and
   group from the map. Keep per-class custom label methods unless the map provides an
   override.
3. Add translated group labels:
   `Content management` / `ניהול תוכן`,
   `Taxonomy management` / `ניהול סיווג`,
   `Site management` / `ניהול אתר`.
4. Configure admin panel `navigationGroups()` in the required order with
   `Heroicon` enum icons, and update Curator plugin navigation to be ungrouped at the
   central sort position.
5. Update `ContentItemResource::getNavigationItems()` so the workspace create item is
   ungrouped first and labeled `New episode` / `פרק חדש`.
6. Add a form-submission navigation badge on `PublicFormSubmissionResource` that counts
   `PublicFormSubmissionStatus::New`, returns warning color, and exposes the central
   badge-deferred intent for tests/documentation. Do not claim native async navigation
   badge loading because Filament 5.6 does not expose that API.
7. Apply workspace/default and classic/system naming:
   list header workspace action says `New episode`; classic create says
   `New episode (system)`;
   row workspace action says `Edit`; classic edit says `Edit (system)`.
   Mirror the same convention in the content-group relation manager.
8. Update English and Hebrew translations.
9. Update tests:
   navigation central-map/completeness test covers ungrouped-first placement, group
   order, group membership, translated labels, and badge-deferred configuration;
   action-label test covers workspace/default and classic/system labels.
10. Backfill IMG-B commit hash into `docs/phase-02/images-arc-imgb-handoff.md` and the
    current-state ledger row.
11. Add the three durable gate-order lessons from IMG-B to
    `docs/phase-02/ai-development-lessons.md`.
12. Update `docs/phase-02/current-project-state.md` for NAV1 and timing findings after
    implementation and profiling.
13. Create `docs/phase-02/admin-navigation-nav1-handoff.md` with Suite Timing Report,
    manual Local Front Check Report, current git status, and final commit hash section.

## Targeted Tests

- `php artisan test --compact tests/Feature/AdminPhase02ResourcesTest.php --filter="orders every registered admin navigation resource and page through the central map"`
- `php artisan test --compact tests/Feature/AdminPhase02ResourcesTest.php --filter="labels episode workspace actions as the default and classic actions as system"`
- If navigation construction needs direct verification, run the full
  `tests/Feature/AdminPhase02ResourcesTest.php` file before the final gate.

## Timing Diagnosis Plan

- Use the final `php artisan test --profile` run as the required profiling run.
- Record total wall time, test count, assertion count, and slowest-tests list verbatim
  in the NAV1 handoff.
- Inspect only the top offenders from the profile output and name likely cost drivers
  with evidence from the test and exercised code.
- Keep all non-trivial performance fixes as the written `TS1` proposal. Do not change
  app behavior or test behavior for performance in this run.

