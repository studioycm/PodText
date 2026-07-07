# Public Front v2 Step 10R / 9F Mini-Step Ledger

This ledger controls the post-Step-10 mini-step sequence. Each run implements exactly the first pending mini-step unless Yoni explicitly selects a different one.

## Current Run

- Selected mini-step: Step 10R-A2
- Status: complete
- Next mini-step after completion: Step 10R-B1

## Checklist

| Mini-step | Status | Commit | Files changed | Tests run | Notes / deviations |
|---|---|---|---|---|---|
| Step 10R-A1 - PublicFrontRenderContext foundation | complete | `a230410` | `app/Providers/AppServiceProvider.php`; `app/Support/PublicFront/PublicFrontRenderContext.php`; `app/Support/PublicFront/PublicFrontRenderContextFactory.php`; `tests/Feature/PublicFrontRenderContextTest.php`; `tests/Feature/AdminResourcesTest.php`; `docs/phase-02/public-front-v2-step10r-a1-implementation-plan.md`; `docs/phase-02/public-front-v2-step10r-a1-handoff.md`; `docs/research/public-front-v2/16-step10r-a1-render-context-foundation-mcp-research.md`; this ledger; `docs/phase-02/current-project-state.md` | `php artisan test tests/Feature/PublicFrontRenderContextTest.php`; `php artisan test tests/Feature/AdminResourcesTest.php --filter="creates and edits content groups"`; `vendor/bin/pint --dirty --format agent`; `php artisan test`; `vendor/bin/pint --test`; `vendor/bin/filacheck`; `npm run build`; `git diff --check` | Added request-scoped context/factory and focused tests. Full gate exposed one stale translated default-label test expectation; fixed the test only, no app behavior change. |
| Step 10R-A2 - Adopt render context in public consumers | complete | `d6d0bec` | `app/Support/PublicFront/PublicFrontRenderContext.php`; `app/Support/PublicFront/PublicFrontRenderContextFactory.php`; `app/Providers/AppServiceProvider.php`; public Livewire components; public Filament page classes; public menu/about/card-template support services; contributor public views; public card Blade defaults; public-front tests; A2 plan/research/handoff docs; this ledger; `docs/phase-02/current-project-state.md` | `php artisan test tests/Feature/PublicFrontRenderContextTest.php`; focused public-front regression file set; `vendor/bin/pint --dirty --format agent`; `php artisan test`; `vendor/bin/pint --test`; `vendor/bin/filacheck`; `npm run build`; `git diff --check` | Public runtime settings/config reads now route through scoped `PublicFrontRenderContext`. Public output behavior is intended to remain unchanged. Test direct-DB settings helpers now also clear the scoped context. |
| Step 10R-B1 - Card template select/options and settings UX fixes | pending |  |  |  | Ensure saved custom templates appear in relevant settings selects. |
| Step 10R-B2 - Real content-item card part renderer | pending |  |  |  | Make content-item template parts visibly affect cards. |
| Step 10R-B3 - Content group and contributor card template renderers | pending |  |  |  | Add equivalent controlled renderers for group/contributor cards. |
| Step 10R-B4 - Legacy card-options convergence | pending |  |  |  | Converge `PublicContentCardOptions` with card presentation services. |
| Step 10R-C1 - Transcriber attribution correction | pending |  |  |  | Use transcription authors for public transcriber contexts without schema changes. |
| Step 10R-C2 - Card layout consistency and semantic layout tokens | pending |  |  |  | Normalize public card layout markers and semantic token maps. |
| Step 9F-A - Rich homepage columns foundation | pending |  |  |  | Run only after Step 10R-A/B/C are complete. |
| Step 9F-B - Footer config and footer renderer | pending |  |  |  | Run only after Step 9F-A is complete. |
| Step 9F-C - Footer/rich section admin UX and integration polish | pending |  |  |  | Run only after Step 9F-B is complete. |
| Step 11 - Seeders/Demo Data/Assets/Cleanup | pending |  |  |  | Do not run until all approved prior mini-steps are complete and Yoni approves Step 11. |

## Required Guardrail State

- Step 2 transcription publication policy remains deferred/reserved.
- Prompt 13 has not started.
- Step 9F / 10F implementation must wait until required Step 10R mini-steps are complete.
- Step 11 must not start from this ledger until Yoni explicitly approves it.
