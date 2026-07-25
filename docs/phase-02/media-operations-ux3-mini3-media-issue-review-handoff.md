# Media Operations UX3 Mini-task 3 Media Issue Review Handoff

## Scope and baseline

- Laravel Simplifier audit:
  `LS-20260725-PODTEXT-MEDIA-OPERATIONS-UX3-M3-01`
- Approved option:
  `MEDIA-OPS-UX3-M3-O1-ROUTE-FIRST-ISSUE-REVIEW-NO-RECHECK`
- Binding operator direction: dedicated Media Issue Review with the revised
  Media details hierarchy and truthful route-first semantics
- Approved slice: Media Operations UX3 Mini-task 3 only
- Repository: `/Users/studioycm/Herd/PodText`
- Starting branch/HEAD: `main` at
  `cc634cb02a922c9bf165bb5951ae32c5654fd564`, 33 commits ahead of
  `origin/main`
- Required predecessor commits:
  - Mini-task 1:
    `0e42ea47d2813141fa8583fc36532c3a85250c33`
  - Mini-task 2:
    `ce97b3d9350db966073b1fc224046d4a25cbfa68`
- Installed stack: Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3,
  Pest 4.7.5 and Tailwind CSS 4.3.3

Preflight found no tracked or staged drift. The only entries were the six
pre-existing operator-owned untracked `ux-design-thinking` paths and symlinks
under `.agents`, `.ai`, `.claude`, and `.junie`; this delivery did not touch
them.

No migration, dependency, model, policy, setting, queue, cache, local
development database/storage, production, branch/worktree, deployment or push
action occurred.

## Delivered outcome

Authorized Media operators can now:

1. open Media details and see stable identity, safe preview availability,
   original/stored filenames, reference key, concise file facts and an honest
   Ready or Needs Attention state;
2. see a concise primary issue plus additional-issue count and follow an
   explicit **Review issues** route;
3. inspect a dedicated read-only Issue Review page that explains every
   current diagnostic's cause, consequence, observed technical facts and
   evidence limit;
4. distinguish canonical direct owners from bounded legacy/settings evidence;
5. open only currently authorized canonical owner destinations for a missing
   file, with explicit copy that those destinations may repair owner
   presentation but do not repair the broken Media record;
6. use only currently authorized and available View/Download file routes,
   explicitly labelled as non-repair inspection/download destinations;
7. see an honest blocker because Mini-task 3 adds no current Media-record
   repair authority;
8. close to the current Media details page, move to the next issue in the same
   normalized task cohort without wrapping, or return to the exact originating
   Library task/filter/search/sort/page/card focus.

The descriptive form is now explicitly headed **Describe Media**. Its Save
path remains limited to title, alternative text, caption and description. It
does not change bytes, path, file facts, attachments, lifecycle state or the
technical `metadata` diagnostic.

## Recheck decision

The approved audit could not prove all required conditions for a safe
Recheck/Retry control: bounded non-mutating reads, actor authorization,
freshness, request-scoped result truth, and safe retry/result semantics.
Accordingly, the implemented option omits Recheck and Retry.

There is also no generic Fix control, reason-specific Media repair mutation,
Files Discovery, move, Trash, restore, purge, lifecycle placeholder or other
Package 5 control.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Stable identity on Media details | Implemented | Uses the current card fallback order and shows original/stored filenames plus reference key. |
| Concise truthful issue indication | Implemented | Ready or primary issue plus localized additional-reason count. |
| Explicit dedicated Review issues route | Implemented | New record-backed `/{record}/review-issues` Resource page. |
| Six current issue reasons | Implemented / existing authority reused | Projects the exact `MediaInventoryDiagnostics` result in its stable order. |
| Cause, consequence and evidence limits | Implemented | Bilingual reason-specific explanations plus observed technical facts. |
| Technical metadata vs descriptive Save | Implemented | Copy and persistence tests prove descriptive edits do not clear a technical diagnostic. |
| Bounded known impact | Implemented | Canonical attachments, bounded legacy/settings evidence and unresolved-row count are separate. |
| “No Known Direct Attachment” limitation | Implemented | Explicitly says absence is not unused or deletion-safe. |
| Truthful owner routes | Implemented | Only supported `content_group/cover` and `content_item/primary_image` pairs, existing owner Resource authorization and missing-file semantics. |
| Owner route is presentation-only | Implemented | Explicit warning on the Review surface; routes open in a new tab. |
| Truthful file routes | Implemented | Existing Gate abilities, configured disk, file existence and safe inline-SVG decision remain authoritative. |
| Honest no-authority blocker | Implemented | Visible whenever current issues exist. |
| Close to current details | Implemented | Encrypted normalized origin survives Review-to-Edit. |
| Next issue in same cohort | Implemented | Reuses exact task/MIME/reason/sort predicates, the same split-term/quoted-phrase title/name search predicate as the Library, and deterministic `(created_at, id)` order without wrapping. |
| Exact Library/task/card return | Implemented | The original server-derived focus and complete normalized context survive Next and Close. |
| Forged continuation handling | Implemented | Authenticated Laravel encryption plus exact-shape revalidation; invalid input falls back safely. |
| Hebrew RTL / English LTR | Implemented | Matching translation keys and real-browser proof at desktop and 390 pixels. |
| Keyboard/focus/accessibility | Implemented | Logical Close/Next/Return tab order and zero critical/serious violations within the owned Issue Review surface. |
| Query/probe economy | Implemented | Canonical owners are batch-loaded; 24 owners render within 11 database queries including all diagnostic/reference reads. |
| Existing details Save | Already existed / preserved | The four-field fill/save whitelist is unchanged. |
| Complete inventory and Needs Attention semantics | Already existed / preserved | No row, task or diagnostic authority changed. |
| Attachment/file-location authorities | Already existed / preserved | `media_attachments.media_id` remains owner authority and Curator `path` remains location evidence. |
| Gallery selection and admission permanence | Already existed / not touched | No picker or acquisition code changed. |
| Recheck/Retry | Deferred / omitted by approved proof gate | The required complete proof was not available. |
| First reason-specific repair mutation | Deferred | Requires a separately researched and audited later action slice. |
| Package 5 controls | Deferred / excluded | No discovery, move, Trash, restore, purge or lifecycle work. |
| Migration, dependency, production or push | Not applicable / excluded | None occurred. |

## Architecture and authority preservation

- `MediaResource::getEloquentQuery()` still resolves complete inventory.
- The custom Resource page resolves its record through that Resource and
  reauthorizes the existing Media view ability on mount and hydration.
- `MediaIssueReviewPresenter` returns view-ready scalars/arrays. Blade performs
  no query, authorization, storage or mutation work.
- Canonical owner evidence comes only from `media_attachments.media_id`.
- Unsupported or missing owner rows are counted as an evidence limit; they are
  never resolved through arbitrary morph aliases or paths.
- Public legacy owner paths and the bounded current settings families remain
  evidence only and never become attachment or route authority.
- View and Download reuse `AdminMediaFileController`, current policies,
  configured-disk checks, existence checks and the current SVG inline guard.
- The opaque continuation contains only the exact eight-key version-1 Media
  Library state. It accepts no raw URL, route, host, referrer, ability,
  action, next-record key or mutation input.
- Next issue performs one request-time read and creates no task snapshot,
  cache, queue item, persistent result or freshness claim.
- Existing attachment, acquisition, delivery, mutation coordinator and
  operation-journal code paths remain untouched.

## Files changed

### Application and localization

- `app/Filament/Resources/Media/MediaResource.php`
- `app/Filament/Resources/Media/Pages/EditMedia.php`
- `app/Filament/Resources/Media/Pages/ReviewMediaIssues.php`
- `app/Filament/Resources/Media/Schemas/MediaForm.php`
- `app/Filament/Resources/Media/Tables/MediaTable.php`
- `app/Support/Media/MediaIssueReviewPresenter.php`
- `app/Support/Media/MediaLibraryContext.php`
- `app/Support/Media/MediaLibraryTaskQuery.php`
- `app/Support/Media/MediaReferenceFinder.php`
- `resources/views/filament/resources/media/pages/review-media-issues.blade.php`
- `resources/views/filament/resources/media/schemas/media-details-summary.blade.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

### Tests

- `tests/Feature/MediaIssueReviewTest.php`
- `tests/Browser/MediaResourceGalleryBrowserTest.php`

### Documentation

- `docs/research/media-operations-ux3/05-mini-task-3-media-issue-review-research.md`
- `docs/research/media-operations-ux3/06-mini-task-3-media-issue-review-implementation-plan.md`
- `docs/phase-02/current-project-state.md`
- this handoff
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Tests added or updated

- Existing Media view authorization, moderator denial and missing-record 404.
- Stable identity, issue summary and explicit Review URL on details.
- Healthy details with no invented Review action.
- Descriptive Save changes only the existing four fields and leaves stored
  technical facts and a technical diagnostic unchanged.
- Cause, consequence, evidence limit and facts for all six reasons.
- Canonical group/item owner routes, unsupported/missing attachment evidence
  and separated legacy/settings evidence.
- Owner batch query budget with 24 canonical owners.
- Authorized/available safe View and Download behavior, including blocked
  unsafe inline SVG with an allowed download.
- Encrypted continuation success, forged-token fallback and exact original
  Library focus through Next, Close and Edit.
- Task/MIME/reason/search/sort composition, shared unquoted split-term and
  quoted-phrase search semantics, same-timestamp key tie, both ascending and
  descending null-timestamp boundaries, and no queue wrap.
- Read-only render with unchanged Media, attachment and mutation-operation
  state.
- Real-browser Hebrew RTL and English LTR flow from Library to details to
  Review to Next to exact return.
- Desktop and 390-pixel overflow, visible owner/blocker/navigation semantics,
  absence of Fix/Recheck/Retry/Package 5 controls, keyboard order, no console
  or JavaScript error and no critical/serious accessibility violation in the
  owned Issue Review surface.

## Visual evidence

The ignored Pest browser screenshot directory contains:

- `tests/Browser/Screenshots/media-operations-ux3-mini3-issue-review-he-desktop.png`
- `tests/Browser/Screenshots/media-operations-ux3-mini3-issue-review-he-narrow.png`
- `tests/Browser/Screenshots/media-operations-ux3-mini3-issue-review-en-desktop.png`

The images are review evidence only and are not tracked product assets.

## Installed-version and review record

- Laravel Boost returned installed-version application/package information and
  Filament/Livewire custom Resource page, schema View, authorization, locked
  state, testing and browser guidance.
- FilamentExamples was queried in decomposed first and second passes for
  custom record pages, prepared view data, sections and locked state. The
  configured server exposed search snippets only and no source/detail reader.
- Installed Filament 5.7.3 source confirmed `InteractsWithRecord`, explicit
  custom-page record authorization and Resource URL behavior.
- The repository Laravel Simplifier, UX design-thinking Stage 4 lens, Filament
  forms/performance, Laravel/PHP, Livewire, Pest, Tailwind and Spatie
  standards were applied.
- An independent final code review found no critical issue. It identified one
  important Library/Next search-cohort mismatch and two minor gaps: incomplete
  null-boundary coverage and string/direction-insensitive icons. Regression-
  first correction now shares one search predicate, mirrors Filament 5.7.3
  split/quoted term extraction, proves both null-order boundaries, uses
  `Heroicon` enum values, and points Next left in Hebrew RTL.
- PhpStorm MCP is configured as a local Streamable HTTP endpoint, but no
  callable inspection tool was exposed to this task. Source review, focused
  regressions, Pint and FilaCheck provide the recorded fallback verification.

## Commands and results

| Command / check | Result |
|---|---|
| Git root/status/branch/HEAD/ahead-behind and predecessor check | PASS; `main` at `cc634cb`, 33 ahead, Mini-task 1 and 2 commits present, only six pre-existing untracked skill paths. |
| Mandatory lessons/state/ledger/handoffs, R4 decision and corrected contract orientation | PASS; exact Mini-task 3 and Package 1–4 boundaries reconciled before edits. |
| Laravel Simplifier Stage 2 approval gate | PASS; current Audit ID and exact approved Option ID matched the operator message. |
| Laravel Boost, FilamentExamples and installed-source research | PASS with evidence levels recorded above. |
| Unchanged focused details/task baseline | PASS: 63 tests / 509 assertions. |
| Initial `MediaIssueReviewTest` | Expected RED: 9 tests, 7 errors plus 2 failures before the page/presenter/route existed. |
| First focused implementation rerun | PASS: 9 tests / 99 assertions. |
| Details/task/issue combined matrix | PASS: 72 tests / 609 assertions. |
| First focused browser command inside macOS sandbox | Infrastructure FAIL: Chromium `MachPortRendezvousServer ... Permission denied`. |
| First identical browser command outside sandbox | Application assertions passed; command failed only on the known ResizeObserver notification after viewport resize. |
| Corrected focused browser rerun outside sandbox | PASS: 2 tests / 72 assertions, including accessibility and visual evidence. |
| Complete Media Resource browser file outside sandbox | PASS: 6 tests / 214 assertions across the Mini-task 1–3 flows. |
| Initial owner query-budget expectation | Expected RED: observed 11 complete Review reads exceeded an initial provisional ceiling of 8. |
| Corrected owner query-budget and feature rerun | PASS: 10 tests / 102 assertions; 24 owners remain within a constant ceiling of 12 queries. |
| Affected Media authority/mutation/owner/performance matrix | PASS: 138 tests / 1,479 assertions. |
| `vendor/bin/pint` iteration | PASS; mechanically corrected three changed PHP files. |
| `vendor/bin/filacheck --dirty` | PASS with 0 issues. |
| HE/EN `media_issue_review` recursive key-parity check | PASS. |
| Changed-PHP syntax sweep | PASS for all 12 changed application, localization and test PHP files. |
| First requirements/scope/authority/lens sweep and `git diff --check` | PASS; no repair mutation, Recheck/Retry, Package 5, migration, dependency, data, production or push drift. |
| First ordered `vendor/bin/pint --test` | PASS. |
| First ordered `vendor/bin/filacheck` | PASS with 0 issues. |
| First ordered `npm run build` | PASS. |
| First ordered full serial `php artisan test` last | FAIL after 1,205 passing tests / 15,404 assertions: one run measured three pre-existing Filament sidebar group labels at 4.48:1 against a 4.5:1 whole-page Axe threshold. No Issue Review node was reported. |
| Owned-surface accessibility correction | The new assertion now scopes Axe to `[data-testid="media-issue-review"]`; no application UI or color token changed. |
| Corrected focused browser rerun outside sandbox | PASS: 2 tests / 72 assertions. |
| Corrected ordered requirements/scope/authority/lens sweep and `git diff --check` | PASS; only approved Mini-task 3 files plus the six untouched operator-owned untracked skill paths. |
| Corrected ordered `vendor/bin/pint --test` | PASS. |
| Corrected ordered `vendor/bin/filacheck` | PASS with 0 issues. |
| Corrected ordered `npm run build` | PASS. |
| Corrected ordered full serial `php artisan test` last | PASS outside the macOS browser sandbox: 1,206 tests / 15,408 assertions. |
| Required post-result requirements/scope/authority/lens sweep and `git diff --check` | PASS. |
| Required post-result `vendor/bin/pint --test` | PASS. |
| Required post-result `vendor/bin/filacheck` | PASS with 0 issues. |
| Required post-result `npm run build` | PASS. |
| Required post-result full serial `php artisan test` last | PASS outside the macOS browser sandbox: 1,206 tests / 15,408 assertions. |
| Independent final code review | No critical findings; one important search-cohort mismatch and two minor null-coverage/icon-convention findings, all corrected before commit. |
| Post-review search/null regression run before correction | Expected RED: 11 tests, 9 passing / 108 assertions, with the split-term queue mismatch plus a test-fixture null update that had not selected a row. |
| Systematic null-fixture diagnosis | Confirmed the Query Builder dynamic `whereKey()` call targeted a non-existent `key` expression under SQLite and updated zero rows; corrected the test to use the model key name. |
| Isolated post-fixture regression rerun before production correction | Expected RED: 11 tests, 10 passing / 113 assertions; only the Library/Next split-term mismatch remained. |
| Post-review feature correction | PASS: 11 tests / 114 assertions, including split terms, quoted phrases and both null-order boundaries. |
| Post-review issue/task context matrix | PASS: 39 tests / 213 assertions. |
| Post-review complete Media Resource browser file outside sandbox | PASS: 6 tests / 214 assertions; Hebrew visual evidence confirms direction-aware Next icon. |
| Post-review pre-documentation requirements/scope/authority/lens sweep and `git diff --check` | PASS; only approved Mini-task 3 files plus the six untouched operator-owned untracked skill paths. |
| Post-review pre-documentation `vendor/bin/pint --test` | PASS. |
| Post-review pre-documentation `vendor/bin/filacheck` | PASS with 0 issues. |
| Post-review pre-documentation `npm run build` | PASS. |
| Post-review pre-documentation full serial `php artisan test` last | PASS outside the macOS browser sandbox: 1,207 tests / 15,420 assertions. |
| Final documented-state requirements/scope/authority/lens sweep and `git diff --check` | PASS. |
| Final documented-state `vendor/bin/pint --test` | PASS. |
| Final documented-state `vendor/bin/filacheck` | PASS with 0 issues. |
| Final documented-state `npm run build` | PASS. |
| Final documented-state full serial `php artisan test` last | PASS outside the macOS browser sandbox: 1,207 tests / 15,420 assertions. |

Feature and browser tests use isolated databases, fake storage and
`Http::preventStrayRequests()`. No test touched the local development database,
live HTTP, live mail or production state.

## Assumptions

- The exact current diagnostic result is request-scoped evidence, not a
  durable task snapshot or guarantee that external storage cannot change
  immediately afterward.
- Existing owner Resources remain the only truthful owner-presentation
  destinations; they do not grant authority over the broken Media row.
- The original Library focus remains the correct return target even after
  moving to another issue. The next record is not allowed to overwrite that
  server-derived origin.
- Default Next ordering remains Added newest-first when the originating state
  has no explicit sort.
- Current database null placement is binding for deterministic continuation:
  null timestamps first ascending and last descending.

## Local Front Check Report

1. Open Admin > Media in Hebrew and choose Needs Attention with a diagnostic
   reason, MIME filter, search and Added order.
2. Open a Media card and expect the issue banner, stable identity, original
   and stored filenames, reference key and safe preview state above the form.
3. Expect the form heading to say Describe Media and expect its helper text to
   say that Save changes descriptive fields only and does not repair stored
   technical diagnostics.
4. Activate Review issues and expect a dedicated page for the same Media
   identity.
5. Read every visible issue card and expect separate cause, consequence,
   observed technical facts and evidence-limit text.
6. Inspect Bounded known impact and expect canonical direct owners to be
   separated from legacy/settings evidence and unresolved attachment counts.
7. For a missing-file record with an authorized podcast or episode owner,
   open the owner route in a new tab and expect the existing owner-image
   workflow; expect the Review page to state that this may repair presentation
   only.
8. Expect View file only for a safely renderable available file and Download
   only for an authorized available file; do not expect either action to be
   labelled as repair.
9. Expect the explicit no-current-repair blocker and verify there is no Fix,
   Recheck, Retry, Files Discovery, move, Trash, restore or purge control.
10. Activate Close review and expect the current Media details page with the
    original Library context retained.
11. Reopen Review issues, activate Next issue and expect the next diagnostic
    record in the same task/filter/search/sort cohort without wrapping.
12. Activate Return to Media Library and expect the original task, filters,
    search, sort and page with the originally opened card anchored and focused.
13. Repeat the flow in English and expect the same semantics in LTR.
14. Resize to 390 by 844 and use Tab through Close, Next and Return; expect one
    readable column, visible focus, logical action order and no horizontal
    overflow.

## Deferred and excluded

- Recheck and Retry, because the complete approved proof gate was not met.
- Any generic Fix control.
- The first reason-specific Media repair mutation and its ability, validation,
  commit/rollback, idempotency, freshness and result semantics.
- Complete usage proof, deletion-safety proof or arbitrary polymorphic owner
  resolution.
- Package 5 Files Discovery, move, Trash, restore, purge and lifecycle.
- Mini-task 4.
- Schema, migration, index, dependency, setting, cache, queue or new policy
  authority.
- Local-development data/storage work, production, deployment and push.

## Commit hash

`fa3b626ccb45f7ff22962cf4cdb8a0755216329f`
