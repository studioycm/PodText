# Step 5B Card Template sample-ranking parity implementation plan

Date: 2026-07-19
Audit: `LS-20260719-STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-01`
Approved option: `STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-PARITY`
Research basis: `docs/research/settings-performance/37-step5b-card-template-sample-ranking-parity-research.md`

## Objective

Make automatic sample choice, exactly ten preloaded options, capped fifty-result
search, selected-label lookup, and rendered preview image use one current
validated effective-image context without changing public eligibility,
contributor ordering, restricted behavior, settings lifecycle, or O1/O2
renderer/editor contracts.

## Implementation sequence

1. **Adopt one validated request context**
   - Constructor-inject the scoped `PublicFrontRenderContext` into
     `CardTemplatePreviewer`.
   - Build preview renderer, single-mode public transcription services,
     aggregates, and `PublicDefaultImageResolver` from that context.
   - Continue rendering the normalized unsaved template passed explicitly to
     the presenter; do not resolve a configured Card Template.

2. **Expose the minimum effective-image policy seam**
   - Add a small public resolver method for whether content-item group-cover
     inheritance is enabled.
   - Add a small public resolver method for whether a family has an effective
     validated configured default.
   - Implement both from the same normalized resolver configuration used by
     `contentItemImage()`, `contentGroupImage()`, and `contributorImage()`.
   - Do not inspect storage, make HTTP requests, invent a field, or create a
     generalized ranking service.

3. **Unify ranked queries**
   - Remove the `imageFirst` argument and `sampleOptionsQuery()` divergence.
   - Pass the same resolver/context facts into every family query used by
     automatic, preload, search, and selected-label lookup.
   - Rank item own local/external at 0, permitted inherited group cover at 1,
     resolver-effective family/global default at 2, and none at 3.
   - Rank group own cover at 0, configured family/global default at 2, and none
     at 3.
   - Preserve item publication/ID ties, group title/ID ties, contributor order,
     public scopes, search filters, explicit selected IDs, and the 10/50 limits.
   - Use model-derived table names and bound non-user configuration flags; keep
     ranking inside SQL before limiting.

4. **Render the ranked effective image**
   - Remove the preview-only `inheritGroupCover: false` override.
   - Reuse the same current-context resolver so the selected sample's rendered
     `data-card-image-source` matches its rank.
   - Preserve explicit preview mode, inert links/actions, draft rendering,
     O1 shell/focus/root behavior, and O2 ordered-flow diagnostics/geometry.

5. **Focused automated coverage**
   - Replace old tests that intentionally freeze registry-only context and
     preview-only no-inheritance behavior.
   - Add a deliberately reverse-ordered item matrix for own local, own external,
     inherited group, configured family/global default, and none.
   - Assert automatic, ten-option preload, and searched options return the same
     tier order, while explicit public-safe selection remains honored.
   - Add group own/default/none coverage and keep contributor order unchanged.
   - Add public draft/future/no-effective-transcription exclusions and forged
     identity/label checks where not already pinned.
   - Preserve restricted zero-render/zero-query tests, selected-current-label
     zero-query behavior, exact 10/50 caps, no lazy loading, query/state budgets,
     HTTP prevention, mail fake, and settings lifecycle non-regressions.

6. **Authenticated browser coverage**
   - Extend the existing owned browser fixture rather than changing local
     development data.
   - Prove fresh automatic choice, exactly ten visible preloaded options, and a
     fifty-result capped search using labels whose timestamps/titles cannot
     accidentally satisfy the expected rank.
   - Select and render own-local, own-external, inherited, configured-default,
     and none cases; assert the corresponding source/fallback markers.
   - Record locale/direction, viewport, visible order/labels, selected value,
     focus/keyboard behavior, restricted selector absence, observed Livewire
     requests, and console/smoke results. Keep unsupported planes explicit.
   - Run one browser owner at a time. Retry an identical Chromium suite with the
     permitted runner if macOS bootstrap/rendezvous permissions fail.

7. **Independent review and simplification**
   - Run the smallest focused tests first and sequentially.
   - Obtain independent read-only architecture and test/performance/security
     reviews after implementation.
   - Resolve or classify every finding, then perform a touched-code
     simplification pass without adjacent cleanup.

8. **Documentation and canonical closeout**
   - Create the option handoff and classify every requirement.
   - Update current project state, the mini-step ledger, and the cumulative Card
     Template preview handoff without selecting a later option.
   - Correct the stale O2 handoff status that still says its completed hash stamp
     is pending.
   - Record every command, failure, browser infrastructure retry, review, gate,
     assumption, measurement boundary, and full deferred inventory.
   - Run requirements sweep, Pint, FilaCheck, Vite build, then the full serial
     suite last. After any later edit, restart at Pint.
   - Commit implementation plus docs/handoff with its hash pending, immediately
     stamp that implementation hash into the handoff and ledger, and make the
     docs-only commit `docs: backfill Step5B FU02 sample-ranking hash`.
   - End clean on `main`; do not push.

## Expected change surface

- Application PHP:
  `app/Support/Settings/CardTemplates/CardTemplatePreviewer.php` and, only for
  the minimal shared policy seam,
  `app/Support/PublicFront/PublicDefaultImageResolver.php`.
- Focused tests:
  `tests/Feature/CardTemplatePreviewerTest.php`,
  `tests/Feature/CardTemplateEditorPreviewTest.php`, and
  `tests/Browser/CardTemplatePreviewBrowserTest.php` as evidence requires.
- New docs: research 37, plan 38, and
  `docs/phase-02/settings-step5b-card-template-sample-ranking-parity-handoff.md`.
- Required synchronization: current state, mini-step ledger, cumulative preview
  handoff, and the stale O2 handoff status sentence.
- No expected Blade, JavaScript, CSS, translation, navigation, model, migration,
  dependency, permission, config, production, or settings-writer change.

## Stop and drift conditions

Stop for an amended Stage 1 audit before improvising if the baseline becomes
dirty or changes, a public scope must change, a new serialized state surface or
general service is needed, query ranking cannot remain bounded in SQL, the
renderer/editor contract must change outside image inheritance, translations
or navigation need broader work, or any migration, dependency, permission,
settings lifecycle, production, later option, second task, or material forecast
increase is required.
