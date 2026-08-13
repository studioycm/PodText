# Phase 02 Current Project State

This is the single source of truth for rolling Phase 02 prompt progress. Other active docs may describe stable dependencies and ownership, but should link here for current completion/progress status.

Recorded after the Markdown-only post-Prompt-10 prompt-progress centralization cleanup. This document intentionally avoids local secrets and should be updated when later prompts change the active baseline.

## Naming: "Storage Truth" versus "Package 5"

Two unrelated pieces of work both reached the number five and were briefly
called "Package 5". Disambiguated on 2026-07-30:

- **Storage Truth** (`UX3-STORAGE-TRUTH`, phases P1–P5) is the work that
  **shipped** on 2026-07-29/30: the managed relocation engine and surfaces,
  the production root-file relocation, legacy owner-column retirement, the
  column-drop migration, and the in-use sanitize lift. It was the fifth
  outcome in the Media Operations UX3 queue, so it inherited "5" by position,
  not by scope. Its external dossier ID
  `LS-20260729-PODTEXT-MEDIA-OPS-UX3-P5-01` and its commit subjects
  (`(UX3 P5 P1)`, `(UX3 P5 P3-P5)`) are immutable identifiers and keep their
  original wording.
- **Package 5 — Files and Physical Lifecycle** is the media-program package
  that has **not started**: Files Discovery for rowless managed files, general
  physical move, Trash with retention, Restore, Purge, and operator-visible
  lifecycle recovery (`MUX3-F046`–`F051`). It stays forecast-only and needs its
  own research, audit and approval. `docs/research/media-program/02-media-program-master-plan.md`
  is the authoritative definition.

Historical "no Package 5 action occurred" exclusions in the completed-outcome
rows below refer to the lifecycle package and remain accurate as written.

A third, unrelated use of "P5" survives: individual mini-tasks number their own
phases, so `3B P1–P5` and `3C P5–P8` mean phase five of those mini-tasks. Bare
"P5" is no longer used for a package.

## Active Media Operations UX3 Forward Route

- The documentation-only **Program Reconciliation and Finding Coverage**
  contract at
  `prompts/pre-13-prompts/media-operations-ux3-program-reconciliation-finding-coverage-codex-prompt.md`
  (`Prompt version: v1 — 2026-07-25`) has been executed and accepted by the
  operator on 2026-07-26. The canonical result is at
  `docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`.
  That document is the detailed owner of the literal goal, 51-finding matrix,
  outcome boundaries and gates. This state file and the mini-step ledger own
  rolling status only.
- Mini-task 3A — Owner Image Choice and Commit is implemented locally and is
  awaiting operator outcome review. The remaining outcome order after that
  review is:
  **Mini-task 3B — Media Intake and Acquisition Results** →
  **Mini-task 3C — Safe Existing-File Operations and Outcomes** →
  **replanned Mini-task 4 — provisional boundary: Reason-Specific Media Issue
  Resolution and Verified Results**. Each outcome receives its own
  research-first Operations UX cycle, operator design decision, bounded
  technical implementation plan, fresh audit/approval and separately gated
  implementation.
- **Binding sequencing rule:** Media Operations UX3 Mini-task 4 must not begin
  UX research, technical research, planning, Laravel Simplifier Stage 1 or
  implementation until Mini-task 3C has been reviewed and closed. After 3C
  closes, Mini-task 4 restarts at UX research/design and is replanned from the
  reconciled 3A–3C evidence. No earlier Mini-task 4 title, scope, selected
  diagnostic reason, plan, audit, option or implementation proposal is
  authoritative. The completed choice, acquisition-result and existing-file
  operation contracts determine which first reason-specific repair is
  truthful, valuable and safely bounded.
- The separate **PodText Documentation Architecture and Consolidation Audit**
  prompt is intentionally not written yet. Operator acceptance on 2026-07-26
  satisfies its prompt-authoring trigger; its later read-only inventory may be
  coordinated alongside a future UX research phase, while document moves or
  consolidation remain separately reviewed and must not overlap an
  implementation write phase.
- **Current gate:** Mini-task 3A implementation is complete locally under its
  accepted audits/options. Its operator outcome review produced the approved
  OR1 through OR6 corrections, all implemented, committed and pushed
  (`07b7eab..cc5c294`). The operator closed the 3A outcome review on
  2026-07-28 («looks great» plus push approval). The selected continuation
  sequence is 3B → 3C → replanned Mini-task 4 → Storage Truth, followed by a
  revision of the paused non-media queues, with bounded TDD (stash-baseline
  environment attribution, time-boxed failure chases, known-environmental
  ledger). **Current gate:** Mini-task 3B is closed by the operator on
  2026-07-28 after full outcome review (P1–P5 plus the OR corrections,
  pushed at `a638fd4`). **Current gate:** Mini-task 3C — Safe Existing-File
  Operations — completed its research cycle (dossier
  `LS-20260728-PODTEXT-MEDIA-OPS-UX3-3C-01`, published artifact) and the
  operator approved «3C all» with decisions D1–D8 (D4 flipped to role
  priority podcast → episode → settings; the rest as recommended). All
  eight phases are implemented locally (see the Git State entry below,
  implementation hash `bc0ce8f488135afd5a407113e94dfbafe2926da4`). The mid-research operator
  seeds (bulk name-by-owner, title-as-export-filename with preselected
  default, search scopes, selection-time retitle checkbox, zero-cost
  public alt chain) were absorbed into the dossier as P5–P8 before
  approval, and the operator's «rename is for a title» ruling is recorded
  as the model: filenames stay anonymous engine mints; «יצירת שם קובץ
  חדש» is maintenance-only. The operator closed the 3C outcome review on
  2026-07-28 («3c is good») after two approved corrections: OR1
  `e529f48` (card references stacked — label above the humanized names
  or the no-usages text, readiness badge on its own row) and OR2
  `88c6880` (the primed reference cache and linked references now build
  one canonical owner-title label and dedupe an attachment against its
  converter-bridged legacy path column; repair-facing legacy list
  untouched). Replanned Mini-task 4 research was opened in parallel by
  operator direction as a read-only background research cycle
  (`unsanitized_svg` operator-preferred first reason). The M4 dossier
  (`LS-20260728-PODTEXT-MEDIA-OPS-UX3-M4-01`, published artifact) was
  delivered and the operator approved «M4 all» = P1+P2+P3 with decisions
  D1=b («ניקוי בטיחותי»), D2=A (managed rows only — P4 dropped; media 2
  waits for Storage Truth relocation, accepted explicitly: zero live
  production targets until then), D3=a, D4=a, D5=Issue-Review-only plus
  card/slide-over Issue Review links, D6=a (fate chips, no Recheck),
  D7=a, D8=a. M4 P1–P3 are implemented locally (implementation hash
  `24b13fb5ba3313d4ffe53ada418e62f7963a6678`). **Current gate:** the
  operator closed M4 on 2026-07-28 («lets say M4 closed») after the
  outcome-review corrections OR1 (accountable admin trust mark,
  `33a659c`) and OR2 (fate-chip badge wording, `8bbd5e9`); the
  standalone picker-modal scroll fix (`1713cbb`) was reclassified by
  the operator as a general picker regression outside M4 scope. The M4
  series is pushed and deployed (production release `74315264`, health
  verified). Storage Truth (root-file relocation and
  legacy owner-column retirement) — is open in UX research under the
  mandatory cycle, seeded per the Storage Truth seeds memory: the ~394-row
  root-level production cohort including media 2, the census, the
  operator's D3 lift (post-retirement in-use sanitize), and
  relocation's expected side-effects on the intrinsic-issue rows. Storage Truth gained operator seeds: legacy
  owner-column retirement (production census 2026-07-28: 294 covers all
  attachment-bridged, 0 legacy-only, `image_path` unused — code-only
  migration) alongside root-file relocation. A staged Eloquent
  strict-mode chore runs in a separate operator-started session.

## Git State

- **Episodes-lens R1 (native-first) is implemented locally, 2026-08-05.**
  The mini-project's design phase closed with every decision answered
  (EQ-1…EQ-12, principles P-EL1–P-EL8, plus three operator-authored rules);
  the authoritative record is the decision annex in
  `docs/phase-02/episodes-lens-design-spec.md` §12, the exact-code plan is
  `docs/phase-02/episodes-lens-r1-implementation-plan.md`, the research is
  `docs/research/episodes-lens-design-research.md`, and the reviewed option
  boards are archived at `docs/research/episodes-lens/episodes-lens-boards.html`.
  R2 (custom-forward: chip strip, grouping/sort toggle rows, three-segment
  visibility cell, saved views) is a **later phase and has not started**;
  its plan must start from the R2 seeds in the spec annex — currently S1,
  the operator's rename of the «ניהול תוכן» navigation group to
  «ניהול ממוקד» now that episodes have left it.
  Shipped in R1:
  - **Publication-date home** (`App\Models\Concerns\InteractsWithPublicationDate`
    on `ContentItem`, `ContentGroup`, `Transcription`): saving a record as
    published with a null date stamps `now()`; an explicit date is never
    overwritten and unpublishing keeps it. `effective_published_at` resolves
    published-but-dateless rows to `created_at` (read-side only — **no
    backfill**; production currently has zero such rows), and
    `orderByEffectivePublishedAt()` folds the same fallback into SQL.
  - **`ContentItemPolicy`**: uniform admin CRUD (which is what arms the
    inline column's `Gate`-based `disabled()`), delete/deleteAny reserved for
    super-admins — the episodes bulk delete and workspace delete follow.
  - **Six quick-scope tabs** (`EpisodeListScope` + `EpisodeListScopeQuery`):
    הכל / טיוטות / גלויים / מתוזמנים / חסומים / מוצמדים, an exact partition
    (drafts+visible+scheduled+blocked = all) with `visible` riding the model's
    own `published()` scope, count badges resolved in one round trip of
    counting subqueries built from the same predicates, forged tab values
    narrowed at the door, and a scope-naming subheading.
  - **The table**: `EpisodePublicState` badge (batch-primed from a
    `withExists` flag) answering each row before it offers actions; status as
    an inline `SelectColumn` with a truthful visibility notification; the
    publish-date cell itself opening a Jerusalem-timezone reschedule modal;
    contextual remedy doors (publish-the-podcast, server re-checked; open-the-
    transcript); occasional actions in an `ActionGroup` with a new
    edit-podcast door; every column but the title toggleable with
    `reorderableColumns()`; filters open above the table with a grouped
    `ToggleButtons` pinned filter and a Jerusalem-walled published-date range;
    podcast + status grouping; `updated_at desc` default sort. The podcast
    page's episodes relation manager shares the same builders.
  - **Navigation**: episodes moved to the ungrouped front door (sort 15,
    under «פרק חדש»); the labelled groups reorder to taxonomy → content, with
    the content group (podcasts + transcripts) collapsed by default and both
    groups given icons (a collapsible desktop sidebar drops the label and
    spills items from an icon-less group). The transcripts item hides in
    single mode for non-super-admins — decluttering only; the URL and
    `canAccess()` are untouched.
  **A three-dimension adversarial review of the R1 diff** (correctness,
  house patterns, Filament API fidelity — 23 claims, each put through a
  refutation pass; 14 confirmed, 9 refuted) ran before the work was called
  done, and every confirmed finding is fixed and mutation-checked:
  - **raw-state, reproduced 500**: table filter state is raw browser input,
    so a forged `?tableFilters[published_between][published_from]=` reached
    `Carbon::parse` and crashed the list. Dates narrow at the door now, the
    range compares against the same effective-date expression the column
    beside it displays (a legacy dateless row was previously invisible to
    its own displayed date), and the indicator shares the query's timezone.
  - **Truthful scheduling**: a future-dated episode whose podcast or
    transcript would still be unpublished on the day reported «מתוזמן» and
    sat outside the blocked tier — the one window in which it was still
    fixable. Prerequisites are now judged at the later of now and the air
    time, so such a row reads «חסום» immediately, a legitimately
    co-scheduled podcast/transcript raises no false alarm, and an
    already-live episode whose podcast published later stays visible.
    `ContentGroup` and `Transcription` gained a `releasedBy()` scope that
    `published()` delegates to — one contract asked about two moments.
  - **A no-op remedy**: publish-the-podcast set a status that was already
    published when the podcast blocked by a *future date*; it pulls the date
    back now.
  - **`resetState`**: clearing the pinned filter left its toggle blank
    instead of returning to «הכל»; the tri-state also gained a type home
    (`EpisodePinScope`) and its unpinned arm a test.
  - **The narrowing was at the wrong door**: `updatedActiveTab()` never
    fires for `#[Url]` hydration, so a query-string tab bypassed it. `mount()`
    narrows now, proven by a query-string forgery test.
  - **unpinned-promise**: `EpisodePublicState`'s docblock claimed a parity
    guard that did not exist. It exists now — every fixture's badge must
    match the scope query that owns it, which also ties the three
    derivations of the visibility contract together.
  - **one-home**: the seven `d/m/Y` literals this diff added route through
    `UiFormats`; the file is back to zero (the parked app-wide sweep is
    untouched).
  Two trade-offs were reviewed and **kept deliberately**, with the reasoning
  and a revisit trigger recorded in the code: the badge aggregate is
  uncached and library-wide (it answers "how many drafts exist", not "how
  many match this filter"). One vendor limitation is worth knowing:
  `NavigationGroup::collapsed()` seeds `collapsedGroups` in localStorage
  only when the key is absent, so an admin whose browser already has that
  key keeps the group expanded until they collapse it once themselves.

  Two defects were found and fixed during implementation: the outcome
  notification read a stale `withExists` attribute through `refresh()` (now a
  clean re-read), and the transcriber fallback relation caused a 10×
  `authors` N+1 (now eager-loaded, pinned by a fixed-query-budget test).
  A vendor gotcha is recorded in code: `NavigationGroup::collapsed()` also
  *sets* collapsibility, so passing `false` strips an expanded group's toggle.
  Three existing guards were deliberately re-pinned (cluster navigation order,
  the 28-action icon-only fleet with the episode surfaces' ActionGroup, and
  the multi-mode timezone helper check, which had been driving the workspace
  as a guest). Tests: `PublicationDateRuleTest`, `ContentItemPolicyTest`,
  `EpisodeListScopeTest`, `EpisodesTableR1Test`, `EpisodesLensNavigationTest`
  (mutation-checked red on the stamping rule, the accessor, the delete tier,
  the blocked predicate, the nav visibility rule and the collapsed flag).
  Gate: full suite **1712 tests / 20,182 assertions green** (including all 56
  browser tests — one ResizeObserver flake under full-run load passed 3/3 in
  isolation and 56/56 in the browser suite), pint clean, full FilaCheck 0
  issues, `npm run build` clean. **Commits are local and unpushed**, awaiting
  the operator's push word (auto-deploy is on).

- Form-target observability (2026-08-02) adds admin-side visibility for
  public-form CTAs whose form key has no enabled definition, without touching
  the public skip behavior (`PublicMenuConfigReader::resolveItem()`, the
  about-page block filter, and the homepage content-block button branch still
  skip silently). `App\Support\PublicFront\PublicFormTargetStatus` is the
  shared truth: status-suffixed select options (`(disabled)` for configured
  but disabled definitions, `(not yet defined)` for registry-default keys
  with no definition), per-key warnings, and misconfigured-CTA counts
  (visible public-form menu items, visible about `form_cta` blocks, visible
  content-block homepage sections with a broken `button_form_key`). The three
  admin `form_key` selects (menu item and about block in
  `BuildsPublicContentSettingsSubjectSchemas`, and
  `display_config.button_form_key` in `HomepageSectionForm`) now share that
  options source — the two duplicated `publicFormOptions()` merge helpers
  delegate to it — are `live()`, and show a warning-colored hint with an icon
  when the selected target will not render publicly; their helper texts
  stopped claiming "only enabled forms are offered" (en+he).
  `PublicFormTargetWarningsWidget` (Overview/Intake lens, directly after the
  context widget, no polling) lists the three counts with links to
  Menu/Header settings, About settings, and Homepage Sections, and hides
  itself for guests and whenever every target resolves. The maintenance
  `form_key` select keeps its stricter enabled-only options and was left
  unchanged. Tests: `tests/Feature/PublicFormTargetObservabilityTest.php`
  (12 tests — option suffixes, warnings, counts, page hints on
  MenuHeaderSettings / AboutSettings / EditHomepageSection with
  healthy-config mutation checks, widget render/links/visibility);
  `DashboardOverviewLensTest` board-order expectation now includes the
  widget. Gate: full suite green (1556 tests / 19,357 assertions), pint
  clean, full FilaCheck 0 issues, `npm run build` clean.
  **Follow-up (same day, operator decision: computed inactive badge, saves
  stay permissive):** menu-item repeater headers and about `form_cta` block
  headers now append a computed "inactive: public target unavailable" marker
  whenever the public side would skip the item, via
  `PublicMenuItemTargetHealth` — an admin-side mirror of
  `PublicMenuConfigReader::resolveItem()` skip rules (route resolvable,
  HTTPS external URL, enabled form definition; theme selector always
  usable) computed from raw form state, sharing the page's memoized
  form-target status. Nothing is persisted — re-enabling a form instantly
  revives its CTAs. Note discovered en route: the config validator *drops*
  saved route items with unknown keys and non-HTTPS external items before
  the admin form hydrates, so the only saved broken state that reaches the
  form is a dangling/disabled form key; the health class still covers
  route/external for unsaved rows being edited. Hard-require-at-save was
  rejected because registry defaults ship two form menu items with an empty
  `public_forms.definitions` list (fresh installs are born dangling) and
  because disabling a form definition is the intended one-click kill-switch
  for its CTAs. Follow-up gate: full suite green (1560 tests / 19,375
  assertions), pint clean, full FilaCheck 0 issues, `npm run build` clean.

- RECON2 R6 (P3 + P2, 2026-07-31) closes the reconciliation plan. **P2 scope
  settled first, per the operator's tripwire instruction:** the ledger
  (`docs/phase-02/back-log-triage-2026-07-13.md`) now pins P2 to the PHP-lazy
  reading — Livewire-lazy/deferred option loading is out of P2's scope, the
  drawer stays server-rendered inside `x-show`, and `MAINT-LW-UX1` (which has
  never run) must still run before the SL2/SL4 slider arc. **P3 (executed
  first — smaller blast radius, no tripwire):** `Transcription` now persists
  `parsed_segments` on save through the same explicit-value-wins hook that
  derives `word_count` (factories/imports that set the column keep it; body
  edits re-derive), the public viewer renders persisted segments and only
  falls back to `TranscriptSegmentParser` for never-derived rows — proven by
  a tamper test that could not pass if the viewer re-parsed per view — and
  `php artisan transcriptions:backfill-parsed-segments` repairs existing null
  rows without touching timestamps, as the word-count sibling does. The
  word-count half of P3 was already delivered by RECON2 R1. **P2:** the five
  public search filter option lists (categories, tags, groups, transcribers,
  providers — the last two being the expensive aggregate/ranking queries) are
  served from a one-minute bounded cache keyed by kind and locale, so every
  debounced keystroke stops paying for them; no Livewire lazy/deferred
  mechanics were introduced. Deeper "bound listing fetch windows / opt-in
  aggregates" sub-items of the original P2 row remain open under that row —
  not silently claimed. Tests: `tests/Feature/TranscriptDerivedStateTest.php`
  (persistence, explicit-wins, no-reparse tamper proof, backfill, cache).
  Gate: full suite 1458 tests / 18,914 assertions fully green including all
  browser tests, pint clean, FilaCheck 0 issues, no asset changes.

- RECON2 R5 (reason-specific resolutions, 2026-07-31) implements the five M4
  Group A rulings the operator settled on 2026-07-31, extending Issue Review
  from one repairable reason (`unsanitized_svg`) to five. **Structured
  verdict:** `MediaRecordScope::backfillVerdict()` returns the exact failing
  facts (`MediaRecordScopeVerdict` codes) instead of one folded boolean;
  `allows`/`allowsForBackfill` semantics are unchanged and parity-tested.
  **Missing file (`MUX3-F032`):** the reason card offers both rulings —
  restore-by-upload rides the existing swap action, now that
  `CuratorMediaPolicy::swap` lifts attachment references (settings paths and
  duplicate identities still block; rename/delete unchanged), and
  detach-and-delete (danger styling, confirmation) detaches every owner
  through `MediaAttachmentManager` (dangling owners removed directly) then
  deletes through the coordinator. The coordinator tolerates missing sources
  for exactly swap and delete: the journal records `context.source_missing`
  instead of a quarantine copy, `assertOperationShape` waives
  `quarantine_path`/`source_sha256` only for that pair with that context, and
  the pre-R5 "delete throws on missing source" contract was deliberately
  replaced (the row is the lie when the file is gone). **Reference key
  (`MUX3-F035`):** `mintReferenceKey` issues a key to key-less rows only,
  through the fence (whitelisted for inventory locking), the lease issuance
  window, `StoredMediaValidator::validateForReferenceKeyBackfill`, and a
  journaled `ReferenceKeyBackfill` operation whose committed identity is the
  issued key; the missing MediaAsset/provider-binding kernel is created in
  the same transaction; filled-but-malformed keys stay immutably blocked.
  **Audience (`MUX3-F037`):** super-admin-only make-public with trust-mark
  weight — consequence dialog, `audience_made_public_at/by` columns
  (migration on `curator`), a revocable strip mirroring the trusted strip,
  and lease-wrapped visibility writes. **Disk (`MUX3-F036`):** super-admin
  disk correction, offered only when the named disk does not exist, Select
  limited to configured disks (helper text per cross-cutting rules), lease-
  wrapped. The vendor Curator `url`/`full_path` accessors are overridden to
  guard unknown disks so a storage_disk-defective row stays reviewable
  instead of crashing any surface that arrays the model. The reason-card
  action zone is now generic (presenter-driven multi-action lists). Tests:
  `tests/Feature/MediaReasonResolutionsTest.php` (19 tests) plus deliberate
  contract updates in the coordinator/authorization/issue-review suites and
  the Issue Review browser contract (the missing-file record now proves the
  resolution zone instead of the honest blocker). Gate: full suite 1453 tests
  — feature slice fully green; the only two failures were the pre-update
  browser blocker assertions, updated and re-proven (media gallery browser
  suite 6/6, 230 assertions). Pint clean, FilaCheck 0 issues, build green.

- RECON2 R4 (record truth, 2026-07-31) makes the documentation layer match
  the code again, as dated amendments — operator-accepted artifacts were
  amended, never rewritten. In the findings matrix
  (`docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`):
  the twelve false-pending rows are corrected (`MUX3-F017`–`F021` closed by
  3B, `F024`–`F030` closed by 3C, `F045` proven by `def171b`), `F022` is
  recorded as the genuine partial (Spotify artwork auto-admission),
  `MUX3-F038` is recorded as **deferred-not-retired** with its unmet proof
  gate intact, the `F048` amendment gains the R2 retention contract and the
  quarantine-as-asset record, the 2026-07-31 operator rulings for
  `F032`/`F035`/`F036`/`F037` are recorded, and the gate-record correction
  states plainly that `3694919` opened **Storage Truth's** research gate —
  the Files-and-Physical-Lifecycle package remains ungated and needs its own
  research, audit and approval. The **missing 3B and 3C handoffs now exist as
  labelled reconstructions** following the Mini-task 4 precedent:
  `docs/phase-02/media-operations-ux3-mini3b-intake-acquisition-results-handoff.md`
  and
  `docs/phase-02/media-operations-ux3-mini3c-safe-existing-file-operations-handoff.md`
  (root cause recorded: no handoff → no matrix update). The M4 handoff gains
  a dated **D2=A lapse** section: `3900645` removed the managed-scope guard
  from `repair` (verified against `CuratorMediaPolicy` — `delete`/`swap`
  still carry it) and the relocation gave sanitize live targets everywhere;
  the operator accepts the resulting boundary. The Step 5B deferred
  inventories in research 33 and the lg-column handoff are amended (items
  3–7, 11–12 resolved by O2/FU02/FU03 and friends; item 13 resolved by R3;
  only FU05's idempotence guard and FU04's order-compat closure remain). The
  backlog triage doc: the EXIF line is closed by R1 with the misleading
  precondition note corrected, and the `podtext.data4.work` line is corrected
  per the operator (original domain folder of this same app; nothing to
  remove). The ROLES1 handoff gains the first-super-admin closure (proof by
  Users-resource visibility). Dead code: the always-empty
  `rowless_transition_candidates` block in
  `app/Console/Commands/ReportMediaIntegrity.php` is deleted (the reporter
  key was retired in `d5be68f`; command smoke-verified locally, which also
  reconfirmed the two known local `cleanup_pending` sanitize rows that
  `media:repair-mutations --apply` can complete).

- RECON2 R3 (FU06 copy cleanup, 2026-07-31) closes
  `STEP5B-CARD-UX2-FU06-COPY-CLEANUP`: the twelve stale helper strings that
  described live Card Template renderer behavior as "future renderers" (or as
  still riding "the compatibility renderer") now state the present truth in
  both locales — `card_template_layout`, `card_template_density`,
  `card_template_image_size`, `card_template_title_size`,
  `card_template_part_layout` and `card_template_part_visible` in
  `lang/en/admin.php` and `lang/he/admin.php` — and the four provably dead
  `card_template_part_order` keys (field label + helper, both locales, zero
  code references) are deleted. `tests/Feature/CardTemplateCopyTruthTest.php`
  pins the contract: those six helpers resolve in both locales without
  future-renderer copy, and the dead keys stay retired. Gate: full suite 1421
  tests / 18,796 assertions fully green including browser, pint clean,
  FilaCheck 0 issues, no asset build needed (helper strings are server-side).

- RECON2 R2 (quarantine retention, 2026-07-31) adds
  `php artisan media:prune-quarantine` — dry-run by default with `--apply` and
  `--days=`, matching the `media:repair-mutations` shape. It prunes **only**
  journal rows at `status = completed` whose `completed_at` exceeds
  `media.quarantine.retention_days` (env `MEDIA_QUARANTINE_RETENTION_DAYS`,
  default 90; 0 = keep forever). Staged, copied, committed, cleanup-pending
  and failed rows are never touched at any age — their quarantine copy is a
  hard repair precondition, and the regression test proves an aged
  `cleanup_pending` row survives the prune and `repair()` still returns
  `completed_cleanup` afterwards. The prune re-derives the directory from
  `operation_key` only after validating it against the ULID pattern and
  checking the stored `quarantine_path` carries that exact prefix; rows that
  fail either check are skipped for manual review (exit failure) and nothing
  is deleted for them. Journal rows keep `quarantine_path`/`quarantine_sha256`
  (`assertOperationShape` requires them) and gain a
  `context.quarantine_pruned_at` marker so a future Trash surface cannot
  believe pruned bytes still exist. The coordinator machinery is untouched
  except the four-line delete-context widening: `delete()` now snapshots
  `alt`/`title`/`caption`/`description` into the journal context, closing the
  only substrate gap the RECON2 audit found between quarantine and a future
  Trash restore. A `Schedule::command('media:prune-quarantine --apply')`
  entry was added at `routes/console.php` (daily 03:30) — **it only runs if
  the operator enables the hosting panel's scheduler to invoke
  `schedule:run`; no scheduler is provisioned by the app itself** (the inline
  fallback precedent is `SettingsBackupManager::prune()`). The
  `MEDIA_QUARANTINE_RETENTION_DAYS` documentation line was appended to
  `.env.example` in the working tree but is **not committed** with R2,
  because the file carries an unrelated concurrent documentation overhaul
  that belongs to its own author. Tests:
  `tests/Feature/MediaQuarantinePruneTest.php`. Gate: full suite 1417 tests /
  18,738 assertions fully green including all browser tests, pint clean,
  FilaCheck 0 issues, no asset changes (no build needed).

- RECON2 R1 (post-Storage-Truth reconciliation, first phase, 2026-07-31) fixes
  the two real bugs the RECON2 audit surfaced plus the Spotify reduced-mode
  indication. **B1 `word_count`:** `Transcription` now derives `word_count` on
  save through the shared `App\Support\Transcriptions\TranscriptWordCounter`
  (the public viewer's proven definition, which the viewer now reuses as its
  fallback); an explicitly provided value still wins, so factories, seeds and
  deliberate imports keep their numbers, and editing the transcript body
  recomputes a stale stored count. `php artisan
  transcriptions:backfill-word-counts` repairs existing null rows without
  touching timestamps. Public cards and podcast headers therefore stop
  advertising zero words for admin- and import-created transcripts.
  **B2 EXIF:** new-file media admission strips embedded metadata at byte level
  with no pixel re-encode via `App\Support\Media\MediaImageMetadataStripper`
  inside `MediaAcquisitionManager::admitNewFile` — JPEG APP1 (EXIF/GPS/XMP),
  APP13 (IPTC) and COM segments; PNG tEXt/zTXt/iTXt/eXIf/tIME chunks; WebP
  EXIF/XMP chunks with VP8X flags cleared. Upload, batch upload, picker bytes,
  storage-candidate copy and external fetch all converge on that choke point;
  display orientation survives through a minimal orientation-only EXIF payload;
  the recorded hash/size are taken from the stripped bytes so journal
  invariants stay consistent; the register-in-place Storage path intentionally
  keeps existing disk bytes verbatim. MI-R044 was amended with a dated note in
  `docs/research/media-program/00-media-program-requirements-decisions-and-method.md`,
  and the acquisition tests' byte-verbatim assertions were deliberately amended
  to pixel-verbatim-plus-metadata-strip. **Spotify reduced mode:** the Links
  Fetcher now renders an unmissable warning banner
  (`data-spotify-reduced-banner`) whenever any row used reduced mode — both
  the no-connection case and silent per-row API fallback — replacing the
  single line inside the warnings list; verifying the actual production
  connection state remains operator-only. Tests:
  `tests/Feature/TranscriptionWordCountTest.php`,
  `tests/Feature/MediaAdmissionMetadataStripTest.php`, and the banner test in
  `tests/Feature/SpotifyFetcherFetch1Test.php`. Gate: feature tests fully
  green across the 1410-test full run (its 21 failures were browser-only and
  attributable to a concurrent `npm run build` swapping hashed assets under
  the live browser-test server mid-run — do not rebuild assets while browser
  tests run); the browser suite re-run on stable assets passes 47/47,
  including the picker test that flaked on the pre-edit baseline. Pint clean,
  FilaCheck 0 issues, `npm run build` green. An unrelated concurrent
  `.env.example` documentation overhaul appeared in the working tree
  mid-phase (mtime 05:07); it is not part of RECON2 R1 and was left
  uncommitted for its author.

- Storage Truth P1 (managed relocation engine and surfaces) is complete
  locally under dossier `LS-20260729-PODTEXT-MEDIA-OPS-UX3-P5-01`,
  operator approval «approve all p5» with decisions D1=b+a (admin batch
  action plus a thin CLI wrapper over one engine core), D2=a, D3=a,
  D4=b, D5=a, D6=b, D7=a (sanitize-only lift), D8=a, and the external
  hotlinks question answered «none» (access-log verification was
  honestly inconclusive: Forge sets `access_log off` for the site).
  Implementation hash `5a4c2f545f7a8af4d45e77f517292bae25f964bb`. The engine:
  `MediaFilesystemMutationCoordinator::relocate` moves an unmanaged/root
  file into the managed covers root through the full
  journal/fence/quarantine machinery — same row id, same immutable
  reference key, `cover_path`/`image_path` references rewritten and
  attachments ensured inside the same commit transaction (locked and
  verified against the journaled census), original bytes preserved when
  they validate (D4b — the validator re-encodes rasters, so originals
  are written explicitly; only SVG takes the sanitizer output),
  admin-trusted rows relocated verbatim keeping the mark (D5a),
  refusal-class SVGs left untouched with nothing journaled. New
  `MediaMutationOperationType::Relocation` joins the cleanup/shape
  families with the same root-source relaxations as Sanitize; the new
  `CuratorMediaPolicy::relocate` ability denies managed rows
  («כבר בתיקיות המנוהלות») and duplicate identities.
  `MediaRelocationBatch` provides the census (fates: oversized per D3a,
  settings-referenced, policy-denied) and chunked execution that
  excludes already-failed rows (no failure spinning) with census-based
  resume (relocated rows drop out of the candidate query). Surfaces:
  the gallery header action «העברה מנוהלת» (visible only while root
  files exist) with a census modal, self-chaining chunked execution,
  progress toasts and a durable three-count receipt; and
  `php artisan media:relocate-root` printing the census report by
  default with `--apply --chunk --user` for the rehearsed run. Gate:
  full suite 1462 tests with only the 4 known-environmental browser
  failures, FilaCheck 0, pint clean, build green. **P2 executed on production 2026-07-29** under the
  operator's Option-B protocol (check → roll-over stock → run →
  integrity): pre-run backups at `/home/forge/backups/p5-relocation-*`
  (gzipped DB dump verified to contain all 40 tables; tar of all root
  files, 34M); the first apply moved 390/394 and truthfully failed the
  four MIME/extension-mismatch rows, exposing that the engine validated
  with the claimed filename — fixed the same hour (content-first
  validation via `validateExternalBytes` with an extensionless name, so
  mismatches NORMALIZE during relocation; non-svg admission failures
  got their own batch label `relocation_fail_invalid`); the resumed
  apply moved the final 4. Integrity sweep: 0 root rows, 419 total, 0
  files missing on disk, 0 covers pointing nowhere, 394 relocation
  journal operations all completed, mismatch rows normalized (webp ×3,
  png), 305 attachments intact, the storage root holds directories
  only, a sample relocated cover serves 200 publicly, and the census
  reads 0 relocatable (the admin action auto-hides). The homepage 503
  is the operator's deliberate maintenance mode. The same round shipped
  the operator's limits decisions: ceilings raised to 4096KB/6000px,
  the informational heavy-upload threshold setting
  (`media_heavy_upload_warning_kilobytes`), pinned-bound tests moved
  above the new ceilings, and resize-and-use with a marked kept-aside
  original seeded for a future cycle. P3 (retirement code-off), P4
  (column drop) and P5 (sanitize lift) are now unblocked.

- Storage Truth P3+P4+P5 (legacy owner-column retirement, drop and sanitize
  lift) is complete locally, commits held for operator push/deploy
  approval per the 2026-07-29 gate. Implementation hash `d5be68f0662b5037a135089b126ee19dbb724808`.
  Media ownership truth is now the `media_attachments` pivot alone:
  `content_groups.cover_path` and `content_items.image_path` are dropped
  by migration `2026_07_29_172340_drop_legacy_owner_media_path_columns`
  (production census had proven 0 legacy-only rows). All dual-writes
  stopped (`MediaAttachmentManager`, `MediaAttachmentFormState`, the
  coordinator's relocate owner-rewrite/census, converter owner repair,
  form-lifecycle pins); every reader is attachment-first with no column
  fallback (identity resolver, reference finder, task query,
  CardTemplatePreviewer ranking, export manager, integrity reporter —
  whose `missing_legacy_path`/`legacy_path_mismatch` codes and
  transition fields retired); the optimistic-concurrency token is
  media-id-only (baseline payload v2), and the external-image download
  actions key off `primaryImageMediaAttachment()` existence. The whole
  legacy plane is deleted: transition/registration planners, switcher,
  executor, manifest, unsafe-owner diagnostics/projector/repairer, the
  converter, seven one-time artisan commands
  (backfill-attachments/reference-keys/settings-reference-keys,
  register-existing, transition, preflight, convert-curator) and the
  unsafe badge/action surfaces. Kept as shared settings-plane kernel:
  `MediaIdentityResolver`, `UnsafeLegacyOwnerMediaException`,
  `LegacyOwnerMediaDiagnostic(+Code)`; `legacyReferencesForMedia` and
  `referencesForPath` now report settings path references only. One real
  regression was caught and fixed in-flight: detaching an attachment
  whose Media row was hard-deleted now tolerates the missing row
  (previously the unsafe repairer absorbed that case). The P5 sanitize
  lift (D7=a):
  `CuratorMediaPolicy::repair` allows «ניקוי בטיחותי» for
  attachment-referenced rows (attachments follow the row id through the
  byte/address change) and still denies with the settings carve-out
  (path-based settings references would break); pinned by policy and
  issue-review presenter tests including a settings-referenced deny.
  Six legacy-machinery test files were deleted with the machinery, and
  the surviving suites were converted attachment-first (fixtures,
  signatures, token payloads, broken-state evidence now sourced from
  the attachment media path). Gate: full suite 1385 tests / 18540
  assertions with only the 4 known-environmental macOS browser
  failures, FilaCheck full 0 issues, pint clean, build green.

- Post-Storage-Truth admin shell rounds (operator-directed, 2026-07-30) are
  complete locally alongside the Storage Truth P3-P5 commits: the admin brand logo
  renders 48px under the 64px header via `brandLogoHeight()`
  (`a4be528`); the settings and system navigation retired their
  sidebar groups for two first-level clusters «הגדרות» and «ניהול
  מערכת» with top-tab sub-navigation and de-prefixed page slugs under
  `/admin/settings/*` and `/admin/system/*` (`9259cb7`); and the
  sidebar order was recomposed with Filament's NavigationBuilder
  driven from `AdminNavigationOrder::ITEMS` so the content and
  taxonomy groups follow the media item while tools, Spotify import,
  both clusters and the public-site link trail in a header-less block
  — automatic building always renders every ungrouped item above the
  labeled groups, and the builder pins the admin panel context because
  another panel resolving the admin redirect URL otherwise evaluates
  page URLs against itself (`138d889`).

- The four long-standing "known-environmental" macOS browser failures
  were diagnosed to a verdict and the environmental ledger is now
  EMPTY (`625becf`): all four `MediaPickerBrowserTest` cases were
  stale tests pinning contracts retired by the media UX program (the
  `wire:loading` close-guard wrapper superseded by Alpine
  `returningSelection` bindings; the `media-picker-selected-item`
  handoff testid superseded by owner-choice choice-state cards; the
  nested card dropdown superseded by the owner details chip; plus
  never-reached sections pinning the retired footer and non-owner grid
  layout). The diagnosis surfaced one real defect — the owner save
  request rendered the pre-persist pending choice because the picker
  field's cached presentation and the owner's loaded attachment
  relation predate the persist — fixed by forgetting both after a
  successful persist, pinned by a revert-proven
  `OwnerImageWorkspaceTest` save-request render test. Browser suite
  47/47 for the first time on this machine; full gate 1344 non-browser
  tests, FilaCheck full 0 issues, pint clean.

- Post-Storage-Truth sweep housekeeping (2026-07-30) closed four recorded loose ends
  without behavior change: the panel no longer calls `discoverWidgets()` at a
  directory that does not exist, `.env.example` documents the app-specific keys
  it had been missing (Hebrew locale trio, media picker driver, settings-backup
  retention and snapshot timeouts, Google importer plus throttle and log level,
  Horizon path and supervisor timeout), the `model:show` baseline issue was
  retested and closed, and `MUX3-F045` (are Media actions discoverable in every
  state and viewport) is now proven rather than assumed — the gallery browser
  test asserts the details action, the edit action, the action group and
  in-viewport bounds at 390px in both locales, where it previously checked
  layout only. Two sweep suspicions were disproven in code and left alone:
  `playwright` is a production runtime dependency because settings-backup
  snapshots shell out to `scripts/settings-snapshots.mjs`, and the
  `MediaReferenceFinder` settings-payload invalidation is live, called from the
  coordinator's registration cache reset.

- Naming disambiguation and M4 truth restoration (2026-07-30, docs and copy
  only). Two unrelated bodies of work were both called "Package 5"; the shipped
  one is renamed **Storage Truth** (`UX3-STORAGE-TRUTH`) and the name "Package
  5" is returned to the unstarted files-and-physical-lifecycle package
  (`MUX3-F046`-`F051`). A naming section at the top of this document states the
  rule, including the third sense where `3B P1-P5` means a mini-task's own
  phases; historical "no Package 5 action occurred" exclusions refer to the
  lifecycle package and were left as written; the dossier ID and commit subjects
  are immutable and keep their wording. The media-program master plan and
  supersession map now record that a bounded relocation shipped without opening
  the package, and the mini-step ledger's stale rows were corrected: 3B, 3C and
  M4 no longer read "not started", Storage Truth gained the two rows it never
  had, and the blanket migration/production exclusion was narrowed to
  per-approval. The findings matrix received a dated post-acceptance amendment
  rather than in-place edits to operator-accepted rows, recording that `F033` is
  complete, `F031`/`F040` are satisfied for one reason of six, `F047` is
  partially superseded by the production relocation, and `F048` has a live
  consequence: committed mutations quarantine the original bytes and nothing
  ever prunes them. Mini-task 4 finally has a handoff, reconstructed from the
  repository and labelled as such. In code, the Issue Review copy that told
  operators file moves were "handled in a separate phase" now points at the
  Managed relocation action that exists, and the matching resolution kind was
  renamed `separate_phase` to `relocation_available` across presenter, view and
  test.

- Browser-suite storage URL hermeticity (bounded test chore, investigation
  of the two `CardTemplatePreviewBrowserTest` geometry failures flagged
  below): root cause found — card image `src` values come from
  `Storage::disk('public')->url(...)`, whose `filesystems.disks.public.url`
  bakes the `.env` `APP_URL` (`https://PodText.test`) because the pest
  browser plugin's in-process server rewrites `app.url` and the URL
  generator origins but not the filesystem disk URL. Playwright therefore
  fetched card images from the Herd vhost, which always serves the primary
  checkout's `storage/app/public`; runs from the primary checkout passed
  (fixtures land in the storage Herd serves) while runs from any worktree
  failed deterministically with `leading_image_loaded=false` (fixtures
  land in the worktree's storage; Herd 404s). Fix: a Browser-suite
  `beforeEach` in `tests/Pest.php` sets
  `filesystems.disks.public.url` to relative `/storage` (plus
  `Storage::forgetDisk('public')`), so browser subresources resolve
  against the pest server origin, which serves `public_path()` through the
  `public/storage` symlink of the checkout under test. `storage:link`
  remains a required worktree provisioning step. The two flagged failures
  are resolved; the known-environmental browser ledger stays at four
  (MediaPicker acquisition-workspace x2, guards-close,
  nested-item-action).
- Media Operations UX3 replanned Mini-task 4 (Repairing Unsafe Files) is
  complete locally for the approved P1–P3 under dossier
  `LS-20260728-PODTEXT-MEDIA-OPS-UX3-M4-01` (M4 research ran as a
  parallel read-only agent by operator direction), operator approval
  «approve M4 all» with D1–D8 (D2 flipped to A: managed rows only, P4
  dropped — the production root-level cohort, including media 2, waits
  for Storage Truth relocation), and light audit
  `LS-20260728-PODTEXT-M4-IMPL-01` (no change). Implementation hash
  `24b13fb5ba3313d4ffe53ada418e62f7963a6678`. P1: the Issue Review page replaces the
  hard-coded no-authority blocker with truthful per-reason resolution
  states (`MediaIssueReviewPresenter::resolutionForReason`): the
  `unsanitized_svg` reason renders its action zone or a
  disabled-with-reason block from the new `repair` policy Response;
  root-path `metadata` reviews carry the D7 sentence
  («הקובץ שמור מחוץ לתיקיות המנוהלות…»); the generic blocker shows only
  when no reason has resolution content. D5 additions: gallery cards
  gained a «בדיקת הבעיות» group action (visible when the row needs
  attention) and the details slide-over keeps its Issue Review link. P2:
  «ניקוי בטיחותי» (D1=b) is a new journaled coordinator operation
  (`MediaMutationOperationType::Sanitize` through the same
  staging/quarantine/lease machinery as rename/swap; destination bytes =
  sanitizer output; refusal classes throw from validation before
  anything is journaled) behind the narrow `CuratorMediaPolicy::repair`
  ability (admin + managed scope + reason present + zero references +
  unique identity, each deny carrying its truthful message). The page
  action shows the 3C-grammar consequence dialog (address-change
  disclosure + not-a-swap hint), and success sends a durable receipt
  (toast + bell) plus same-request re-evaluated fate chips
  («נסגר»/«נותר») with no Recheck control (D6=a). P3: an unsanitizable
  file is a named refusal (nothing journaled, bytes untouched) with two
  prefilled continuation routes on the page — the 3C swap and
  permanent-delete dialogs (delete returns to the library).
  Engine-contract adjustment discovered and required by approved D4
  routes: `mutateExisting` validated SOURCE bytes unconditionally, which
  made swapping a refusal-class SVG impossible; source validation is now
  skipped only when a replacement exists (raw source bytes still
  quarantined; derive-from-source operations keep the strict throw) —
  covered by the refusal-route test. Gate: full suite 1449 tests /
  19282 assertions with only the 4 known-environmental browser
  failures, FilaCheck 0 issues, pint clean, `npm run build` green; the
  `media_mutation_operations.operation` column is `string(32)` so the
  new enum case needs no migration. M4 outcome review (operator,
  2026-07-28) added OR1 — the admin trust mark: «סימון כקובץ מהימן»
  settles validator-based display blocks for files whose source the
  admin fully trusts (the project's existing trusted-admin doctrine,
  extended from `embed_html`/maintenance raw HTML to SVG media). One
  migration adds nullable `trusted_at`/`trusted_by_user_id` to the
  `curator` table; trust flows through the single
  `PublicMediaDelivery::canRenderInline` choke point so the diagnostic
  reason, selection blocking, previews, and public rendering settle
  together; the `CuratorMediaPolicy::trust` ability requires admin plus
  an applicable safety block (or an existing mark, for revocation); the
  refusal region offers the trust route with a heavy accountability
  dialog; a trusted-state strip on the review page shows who/when
  (day-first Asia/Jerusalem) with a revoke action; grant and revoke send
  durable receipts; bytes are never touched and nothing is journaled.
  The operator's D3 follow-through (lift the zero-reference sanitize
  restriction once legacy path columns retire — id-authority
  attachments plus the engine's settings-reference rewrite make it
  safe, and sanitizer output is app-derived, trusted bytes) is seeded
  to Storage Truth. Deferred: intrinsic metadata-row repairs (natural
  second cycle per D8), root-level reach (Storage Truth).
- Staged Eloquent strict mode (bounded chore, implemented on worktree
  branch `claude/keen-gould-16e3a4`, rebased onto the 3C closure
  `9393fef`; implementation hash `72c29c5`, merged fast-forward into
  `main` and the worktree retired): lazy-loading
  prevention outside production has been active since `af9f399`
  (2026-07-08); this chore adds the production degradation path.
  `AppServiceProvider::boot()` now registers
  `Model::handleLazyLoadingViolationUsing(...)` that logs a warning
  (`Lazy loading violation detected.` with model/relation context) and
  lets the relation load normally in production, rethrows
  `LazyLoadingViolationException` elsewhere, and preserves stock silence
  for non-existing/recently-created models (the framework default skips
  those; a naive callback would newly throw on `replicate()`d or deleted
  models — `MediaInventoryDiagnostics` replicates media records).
  `MediaRecordScope::hasUniqueStorageIdentity()` now reads
  `getAttributes()['storage_identity_count'] ?? null` so both optional
  producers of that alias — `MediaResource::getEloquentQuery()` and the
  3C picker projection (`MediaPickerPanel` adds it only when the panel is
  not in owner-choice mode) — stay safe for a future
  `Model::preventAccessingMissingAttributes()` enablement; that guard and
  `preventSilentlyDiscardingAttributes` stay intentionally off (vendor
  Curator `Media` mass-assignment risk). Optional-attribute audit: the
  one remaining must-fix before enabling the missing-attribute guard is
  `EditEffectiveTranscriptionAction::recordHasTranscriptions()`
  (`getAttribute('transcriptions_count')`); `?? 0`/`isset()` readers of
  public aggregate aliases are guard-safe because `Model::offsetExists`
  suspends the guard; `CardTemplatePreviewer` subselect aliases are
  SQL-only; dynamic legacy-column `getAttribute()` reads across
  `app/Support/Media/*` target real columns and only need a
  partial-select hydration spot-check at enablement time. New
  `tests/Feature/EloquentStrictModeTest.php` (5 tests) proves
  collection-hydrated lazy loads throw outside production (single-model
  hydration is exempt by framework design — `Builder::hydrate` stamps the
  per-instance flag only when hydrating more than one row), production
  logs and still loads, deleted models stay silent, a selected
  `storage_identity_count` is trusted, and the peer-query fallback passes
  under an enabled missing-attribute guard. Lazy-loading triage across
  the full suite: zero violations, none deferred. Gate on the post-3C
  tree: Pint clean, FilaCheck 0 issues, Vite build clean, full suite
  1444 tests / 1438 passed / 18,883 assertions in 10.8 minutes; the only
  6 failures are the four known-environmental browser tests plus two
  `CardTemplatePreviewBrowserTest` geometry failures proven pre-existing
  on this machine (identical with the chore stashed and at a pre-3B
  commit; `leading_image_loaded` false on seeded default images; the
  fresh-worktree `public/storage` symlink gap was found and fixed but is
  not the full cause; flagged for a separate investigation session).
- Media Operations UX3 Mini-task 3C (Safe Existing-File Operations) is
  complete locally under research dossier
  `LS-20260728-PODTEXT-MEDIA-OPS-UX3-3C-01`, operator approval
  «approved 3C all» with D1–D8 (D4: role priority podcast → episode →
  settings), and post-implementation light audit
  `LS-20260728-PODTEXT-3C-IMPL-01` (verdict: no change). Implementation
  hash `bc0ce8f488135afd5a407113e94dfbafe2926da4`. P1 buttons tell the truth: rename/swap/
  delete policies now return `Response` reasons (in-use with surfaces,
  unmanaged legacy, duplicate identity) rendered by Filament
  `authorizationTooltip()` as disabled-with-reason on gallery cards; the
  picker panel projects per-tile `ops` availability (primed references +
  `storage_identity_count` subselect, zero extra queries) and its tile
  buttons disable with the same reasons — the production «media 2» class
  of confirm-then-404 crashes is closed (`trustedRecord` now resolves
  inventory + authorize, 403 with reason instead of 404). View/Download
  disable with «הקובץ חסר באחסון» when diagnostics report a missing file.
  The panel delete label unified to «מחיקה לצמיתות». P2 consequence
  dialogs: one shared blade shows identity (thumb, name, dims, size),
  the truthful zero-usages line, and one per-operation consequence
  sentence (rename address-change + external-links warning; swap
  library-file semantics; delete permanent + journal safety copy per D1).
  Implementation-discovered contract recorded: policy restricts rename/
  swap to unreferenced files (`canMutateFile`), so the dossier's
  «בכל :count המקומות» swap state is unreachable and PM3 dies at the
  policy layer. P3 receipts: every operation sends before→after success
  notifications (toast + database bell) via `MediaOperationReceipts`;
  failures show the engine reason or a safe generic. P4 bulk census:
  `MediaBulkDeleteCensus` classifies selections (eligible / blocked with
  reasons), the confirm modal shows the census, execution skips blocked
  rows per D2 (one legacy row no longer aborts the whole bulk), and a
  three-count receipt lands in toast + bell on both surfaces. P5 export
  truth: both export dialogs state population + count + destination, and
  `MediaNamingStrategy::Title` makes the media title the export filename
  source (settings default preselected; NULL titles fall back to
  slug-key per D6; duplicate entry names suffix -2/-3). P6 title-by-
  owner: `MediaReferenceFinder::ownerTitleForMedia` derives «{owner} —
  {role}» per D4 priority/D5 shape; gallery bulk action with census +
  optional prefix/suffix, single-card action with preview, and the D8
  smart-default selection-time checkbox on the owner-image modal
  (checked when the media has no title; applies on save via
  `MediaOwnerTitleApplier`). P7 search scopes: gallery header selector
  and panel select scope search to הכל/כותרת/בעלים/שם קובץ, with the
  new owner-title search (attachments via both morph aliases + legacy
  path columns). P8 public alt chain: `media.title ?: owner title` on
  already-loaded rows only (groups keep `cover_alt_text` priority) —
  zero added queries by contract. Latent bug fixed in passing: raw
  `attachable_type` comparisons accepted only one morph alias while the
  database holds both (`content_group`/FQCN); the finder, export count,
  and owner search now accept both. Gate: full suite 1438 tests /
  19203 assertions with only the 4 known-environmental browser failures,
  FilaCheck 0 issues, pint clean, `npm run build` green. Deferred/
  registered: retitle checkbox on the two other selection paths
  (relation manager, owner edit-page lifecycle — same applier, small
  wiring each), quarantine-journal UI, root-file relocation (Storage Truth),
  lifting the rename/swap zero-reference restriction (drift, operator
  decision).
- Media Operations UX3 Mini-task 3B (Sources, Acquisition and Results) is
  complete locally under research dossier
  `LS-20260728-PODTEXT-MEDIA-OPS-UX3-3B-01` and operator approval
  `approve 3B all` (phases P1–P4 implemented, P5 delivered as a read-only
  spike memo, then P5 built on explicit operator approval). Its
  implementation is `bc95e21d692754b8404b2bd30dbe370326c22698` with the P5
  chunked-queue follow-up at `7e4eaf2931b2800ec35e4827131afc028409c147`. P1: batch uploads now report named per-file fates
  (נקלט/נכשל/לא נוסה) with three separated counts in an in-panel result list
  and in the partial toasts, and un-admitted files stay queued for one-click
  retry («קליטת :count הקבצים שנותרו») — achieved without changing the
  validate-all-first admission safety contract (invalid batches now return a
  named zero-admission result instead of a blind exception; the deliberate
  safety test proves the same guarantee against the new shape).
  `MediaUploadBatchResult` carries failed/notAttempted/admitted indexes, and
  the standalone Media create page consumes the new shape. P2: the URL gate
  gains a browser-side blur preview in a wire:ignore Alpine island (no
  server fetch before the explicit Add; admin CSP verified absent), honest
  browser-fact dimensions, an onerror message, and a reworded url_help. P3:
  the Spotify/external thread speaks one verb — «ייבוא» — across nav, action
  labels, modals and notifications, and the queued toast now says the result
  arrives in the notifications bell. P4: Storage candidate rows carry
  extension and file size and show busy/invalid errors on the attempted row
  (`storageErrorToken`). P5 (built): a new `media_upload_queue_limit`
  Admin UX setting (default 40, clamped queue ≥ batch and ≤ 100, settings
  migration applied) lets the upload queue exceed the per-request batch
  limit; the action admits the first batch-limit chunk per run, the result
  list accumulates admitted history plus current-run and queued rows, the
  success toast reports the remaining queue, and the retry button invites
  the next chunk.
  Findings F017–F022 and both operator seeds are addressed or explicitly
  gated. Delta gate: Pint, FilaCheck (0), Vite, 219 feature tests across the
  affected files and both browser files with only the four
  known-environmental failures; the cumulative full suite runs before the
  commit is finalized.
- Media Operations UX3 Mini-task 3A outcome-review corrections OR5 and OR6
  are complete locally under the shared design-review artifact (OR5 section
  plus in-chat steers) and operator approval `approve OR5 - all details
  actions` with follow-up steers. Their implementation is
  `6af53a1562aff67b02c05ac12faf23d51af2ae63`. OR5: every details action on the picker surface
  (owner gallery tiles, blocked-tile links, strip card ⓘ, plus an additive
  entry on standalone tiles) opens a panel-owned read-only details slide-over
  (thumbnail, name with copy, MIME/dimensions/size, stored filename, folder,
  reference key, known usages, warning or selectable state) instead of a new
  tab; the full Media page stays one click away inside it; Issue Review stays
  a full page; the strip reaches the panel action through a window event
  bridge. OR6: status-card thumbnails grew to 56px and card actions to 28px
  with unified button rendering; restore is danger-red; remove-direct-image
  became a danger trash Filament field action with a confirmation modal
  (restore stays confirmation-free as the undo); the upload/url source
  sections lost their duplicated headers; the upload and URL acquire actions
  sit in a top bar sticky inside the source panel with the permanence note
  inline; and both permanence texts were rewritten functional with an inline
  new-tab link to Media management («ניהול הגלריה») via a `:link`
  placeholder kept in HE/EN placeholder parity. Browser predicates and the
  canonical details contract moved to button/slide-over assertions. The
  staged-removal state now tells its whole story: pending-state texts were
  rewritten to the happened-then-result formula («התמונה הישירה הוסרה
  מהבחירה. בשמירה תוצג במקומה תמונה אוטומטית או ברירת מחדל.» /
  «נבחרה תמונה חדשה. בשמירה היא תחליף את התמונה המוצגת.»), the direct card
  carries a danger «תוסר בשמירה» marker while removal is staged, the pending
  card previews the resolved future image via a new read-only
  `ignoreOwnImage` skip on `PublicDefaultImageResolver`, the shown-now card
  gains an amber «בשמירה: :source» suffix naming the resolved future source,
  and the details slide-over leads with a large (~85% width) preview. No
  schema, dependency, settings or authority change.
- Media Operations UX3 Mini-task 3A outcome-review correction OR4 is complete
  locally under the shared design-review artifact (OR4 section) and operator
  approval `approve OR4`. Its implementation is
  `5a27e5b3ab2c001d3b39941e47c89261c510715a`. The two acquisition permanence texts are rewritten
  short and simple in HE+EN (inline: «כל תמונה שנקלטה נשמרת בספרייה לצמיתות,
  גם אחרי ביטול. העלאה מרובה אינה בוחרת תמונת בעלים אוטומטית. יש לבחור תמונה
  אחת וללחוץ שמירה.»; standalone keeps the gallery-choice sentence) with the
  translation-contract expected values updated; queued upload previews render
  as a grid (`panelLayout('grid')`) on the picker upload field and the
  standalone Media create form while single-file replacements keep rows; and
  the upload action moved into a bar sticky at the top of the scrolling
  source panel with its guards and testid intact. Delta gate: Pint, FilaCheck
  (0), Vite, 167 picker/owner feature tests and the picker browser file with
  only the four known-environmental failures; the cumulative full suite runs
  at the OR5 gate.
- Media Operations UX3 Mini-task 3A outcome-review correction OR3 is complete
  locally under the shared design-review artifact
  (`LS-20260728-PODTEXT-MEDIA-OPS-UX3-M3A-OR1`, OR3 v3 section) and operator
  approval `OR3 as drawn` after three mockup iterations. Its implementation
  is `0ddb9d1fb79694686c2555ec24ccaed2284b3777`. The owner choice strip is
  now one line: bare owner title above three compact cards ordered shown-now,
  direct, pending — each card a 36px thumbnail, small fact label and visible
  short state description with the trailing period dropped; media names and
  reference keys left the strip (hover tooltip carries the name, clicking the
  card copies it with the existing «הועתק» feedback), and per-card icon
  actions replace the standalone button row: details ⓘ on the direct and
  pending cards (details before restore), restore ↺ on the pending card only,
  and the preserved choose-automatic action as a direct-card icon. The four
  owner heading values were reworded to the «kind · slot — action» format
  («פרק · תמונה ראשית — החלפה»…), `sources.direct_media` became
  «התמונה הישירה»/'The direct image', and a dedicated
  `owner_image.actions.restore_saved` key («חזרה לתמונה השמורה») replaced the
  shared generic Restore label on this surface. Commit and cancel sentences
  stay verbatim on one compact line; the broken-state block is unchanged;
  spacing tightened (strip p-2, tabs mt-2). All 3A testids and
  mixed-direction escaping guarantees are preserved with updated assertions.
  No schema, dependency, settings or authority change.
- Media Operations UX3 Mini-task 3A outcome-review correction OR2 is complete
  locally under the same design-review artifact as OR1
  (`LS-20260728-PODTEXT-MEDIA-OPS-UX3-M3A-OR1`, OR2 section) and operator
  selection `OR2 first, then 3B`. It started from clean `main` at
  `841f8a8` (OR1 docs backfill), 39 commits ahead of `origin/main`. Its
  implementation is `c9152b386bd0c1a2a17586df64ebc02ccbbc3b4a`. The gallery
  toolbar gains one native folder select whose resting value «כל המדיה»
  states the owner gallery's true whole-library scope and, when changed,
  filters by directory: options are the distinct inventory directories with a
  root sentinel, tampered values reset to all, changing the filter resets to
  page one and recounts pagination, search composes with the filter, the
  non-owner picker shows the select only in All-Media mode and clears it on
  return to context mode, and a filtered-empty state offers a one-click
  return to all media. Three new HE+EN keys (`directory_filter_label`,
  `root_directory`, `empty_directory`) joined the translation-parity list.
  No schema, dependency, settings or authority change. The 10-per-batch
  upload limit stays a settings decision (raisable to 20); lifting the 20
  ceiling is routed to Mini-task 3B per F019/F020.
- Media Operations UX3 Mini-task 3A outcome-review correction OR1 is complete
  locally under design review `LS-20260728-PODTEXT-MEDIA-OPS-UX3-M3A-OR1`
  (ux-design-thinking lens-lint review) and operator approval `approve OR1`
  covering phases P1 (gallery-scoped chrome and duplicate removal) and P2
  (compact owner choice strip). It started from clean `main` at
  `07b7eab8be1d9207705fedec02e19c7754a3d70c`, 37 commits ahead of
  `origin/main`. Its implementation is
  `b641f60fe55930d2b894ac907dfe549c9a9e35a0`. The owner Change-Image surface now scopes gallery search, the
  selected-count live region and pagination inside the Gallery tab, removes
  the panel header row in the inline owner workspace, renders the owner
  heading and helper once each, keeps the acquisition permanence sentence only
  in the acquisition aside, and compresses the three choice-state cards into
  one compact strip with an inline shown-now thumbnail and a single details
  link. Direct/shown/pending truth, broken-state evidence, commit/cancel
  boundary wording, restore and automatic actions, every choice-state and
  picker testid, and mixed-direction isolation remain unchanged; the
  `commitBoundary` admission label became optional and unused, and one dead
  owner-modal header CSS rule was removed with its stylesheet-contract
  assertion. Pint, FilaCheck (0 issues), Vite and the full 1,415-test /
  17,101-assertion suite passed except four browser tests that fail
  identically on clean `main` in this macOS environment (stash-baseline
  attribution run: no new and no fixed browser failures versus baseline),
  with a trailing light Laravel-simplifier pass over the touched files.
  3B, 3C, Mini-task 4, Package 5, dependency/schema/production work and
  push were not started.
- Media Operations UX3 Mini-task 3A is complete locally under primary audit
  `LS-20260726-PODTEXT-MEDIA-OPERATIONS-UX3-M3A-01` and option
  `MEDIA-OPS-UX3-M3A-O1-CANONICAL-MODAL-SHARED-OWNER-LIFECYCLE`, with the
  approved cross-task mechanism closeout and Task 8/9 amendments recorded in
  `docs/phase-02/media-operations-ux3-mini3a-owner-image-choice-and-commit-handoff.md`.
  It started from `main` at
  `576f5ada925d035baef75615f24d0fc9f8c7aa06`, 35 commits ahead of
  `origin/main`. Its implementation is
  `6da7fda62e59c515b2fccc7a9108d814300d313b`. Owner actions, full forms,
  workspaces, relation-manager Create/Edit and public Settings image slots now
  share direct/shown/pending truth and the correct prepare/persist/rollback
  lifecycle. All Media remains complete,
  Gallery choice remains mutation-free, admitted Media remains permanent,
  Cancel remains an owner no-op, and attachment/file-location authorities are
  unchanged. Root settings lifecycle, nested identity, coordinated-writer and
  event-bridge gaps discovered during implementation were fixed under the
  approved O2 closeout. Independent final Task 9 review found no Critical or
  Important issue. Recheck/Retry, generic Fix, reason-specific repair, 3B, 3C,
  Mini-task 4, Package 5, dependency/schema/production work and push were not
  started.
- Media Operations UX3 Mini-task 3 is complete locally under audit
  `LS-20260725-PODTEXT-MEDIA-OPERATIONS-UX3-M3-01` and approved option
  `MEDIA-OPS-UX3-M3-O1-ROUTE-FIRST-ISSUE-REVIEW-NO-RECHECK`. It started from
  `main` at `cc634cb02a922c9bf165bb5951ae32c5654fd564`, 33 commits ahead of
  `origin/main`; its implementation is
  `fa3b626ccb45f7ff22962cf4cdb8a0755216329f`. Media details now leads
  with stable identity and a concise issue indication, labels descriptive
  editing as non-repair, and links to a dedicated read-only Issue Review.
  The Review surface explains all six
  current diagnostics, bounded known impact and evidence limits; exposes only
  current authorized owner-presentation and file routes; shows an honest
  no-repair-authority blocker; and preserves the original Library task,
  filters, search, sort, page and card focus through Close, Next and Return.
  Recheck/Retry, generic Fix, the first reason-specific repair mutation,
  Package 5, Mini-task 4, migrations, dependencies, local-development data,
  production and push were not started. Independent review found no critical
  issue and corrected one Library/Next split-search parity issue plus bounded
  null-order coverage and direction-aware icon gaps. The post-review
  pre-documentation and final documented-state gates passed Pint, FilaCheck,
  Vite and the full serial 1,207-test / 15,420-assertion suite outside the
  macOS browser sandbox. Handoff:
  `docs/phase-02/media-operations-ux3-mini3-media-issue-review-handoff.md`.
- Media Operations UX3 Mini-task 2 is complete locally under parent option
  `MEDIA-OPS-UX3-O2-PDF-CONTRACT-TARGETED-WORKSPACES`, audit
  `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX3-M2-01` and approved option
  `MEDIA-OPS-UX3-M2-O2-CANONICAL-TASK-CONTEXT`, with
  `PODTEXT-MEDIA-UX-CONTRACT-20260724-CORRECTED` binding. It started from
  clean `main` at `69aa0ce3f4983be54a3d25124cf43ef3ee21b5d6`, 31 commits
  ahead of `origin/main`. Its implementation is
  `ce97b3d9350db966073b1fc224046d4a25cbfa68`. The Media Library
  now has five canonical task views, exact diagnostic-reason composition,
  the inclusive rolling previous 30-day Recent definition, two bounded
  request-memoized badges, deterministic Added sorting and a validated,
  versioned List-to-Edit return context that restores filters, page and actual
  card focus. All Package 1–4 persistence, inventory, attachment, acquisition,
  delivery, authorization and mutation authorities remain unchanged.
  Mini-task 3 builds on this context under the separate completed entry above.
  Package 5, migrations, dependencies, local-development data, production and
  push were not started.
- Media Operations UX3 Mini-task 1 is complete locally under audit
  `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX-03`, approved option
  `MEDIA-OPS-UX3-O2-PDF-CONTRACT-TARGETED-WORKSPACES`, with
  `PODTEXT-MEDIA-UX-CONTRACT-20260724-CORRECTED` binding. It started from clean
  `main` at `6d8f7f73742448a7671fb5b8f238bf01ebf6b5ad`, 29 commits ahead of
  `origin/main`. Its implementation is
  `0e42ea47d2813141fa8583fc36532c3a85250c33`. The Media card is image-first
  with stable identity, bounded truthful known-reference copy, persistent
  primary issue, quiet file facts, one visible details entry and one accessible
  action menu. All Package 1–4 inventory, acquisition, attachment, policy and
  mutation authorities remain unchanged. Mini-task 2, Package 5, migrations,
  dependencies, data, production and push were not started.
- The Package 4 owner-picker correction is complete locally under audit
  `LS-20260724-PODTEXT-MEDIA-OWNER-PICKER-CORRECTIONS-01`, approved option
  `MEDIA-OWNER-CORR-O3-INLINE-PICKER-TABS`. Its implementation is
  `f905de83e996d34c65b767deb7ce121283f0786a`. It started from clean `main` at
  `75249da2c6de7dcdc82cd938d2a722449d87aa47`, 25 commits ahead of
  `origin/main`. The complete Gallery/Upload/URL/Storage picker now appears in
  the first/default Replace Image tab, Details and Effective Image is second,
  owner batch upload admits every success without choosing an arbitrary owner
  image, and standalone Media batch upload is explicit. No dependency,
  Package 5, broader Storage discovery, production or push action occurred.
- Under separate exact backup-first local-database approval, this run applied
  the already-committed Package 3 settings migration and two Media Asset
  relational migrations to local MySQL database `podtext`, then ran Curator
  conversion report/apply/report. The settled state is 15 bound rows, zero
  unbound rows and zero diagnostics. No media file or production state was
  touched.
- Media Program Package 4 is complete locally under audit
  `LS-20260724-PODTEXT-MEDIA-P4-POSTP3-OWNER-UX-01`, approved option
  `MEDIA-P4-POSTP3-O1-INTEGRATED-IMAGE-WORKSPACE`. Its implementation is
  `52875222916558542cfde19f8a1987b78e72c121`.
- Package 4 started from clean `main` at
  `abd5e11b1e8db6cedd8e673a246711698fde3c5f`, 23 commits ahead of
  `origin/main`. Its scope is the integrated owner-image workspace plus the
  separately estimated Resource-table record-action rider. Composer/npm
  toolchain changes, Package 5, live data, production actions and push are
  excluded.
- Media Program Package 3 is complete locally under audit
  `LS-20260723-PODTEXT-MEDIA-P3-ACQUISITION-PICKER-01`, approved option
  `MEDIA-P3-O1-IMMEDIATE-SHARED-ADMISSION`.
- Package 3 hash-stamped baseline: `main` at
  `786f7c5f699a3d9d2c02f4c93baff02b0ddcbc1f`, 19 commits ahead of
  `origin/main`.
- Package 3 implementation is committed as
  `656a7c2ed1b64b3f6fd8392bff88f7cca36d2695`.
- The approved post-Package-3 correction is complete locally under audit
  `LS-20260723-PODTEXT-MEDIA-P3-POST-ACQUISITION-UX-01`, option
  `MEDIA-P3-POST-O3-IMMEDIATE-SOURCE-WORKSPACE`. It preserves immediate
  permanent acquisition and pending owner attachment while correcting
  result/error truth, single-mode safety, Storage convergence, URL deadline,
  direct single choice, busy/offline/accessibility behavior and source
  organization. The first complete ordered gate passed Pint, FilaCheck, Vite
  and 1,080 tests / 14,165 assertions outside the known macOS browser sandbox.
  Its handoff is
  `docs/phase-02/media-program-p3-post-acquisition-picker-ux-handoff.md`.
  Package 5, migrations, live data, production actions and push remain
  excluded. The operator later amended only the dependency boundary for a
  bounded Filament 5.7.1 to 5.7.3 patch refresh; manifests, npm and unrelated
  packages remain unchanged.
- The approved dependency/Resend webhook follow-up is complete locally under
  audit `LS-20260724-PODTEXT-DEPENDENCY-RESEND-WEBHOOK-02`, option
  `DEP-REFRESH-WEBHOOK-O1-BUILTIN-SAFE-LOGGING`. It replaces the direct Resend
  SDK requirement with `resend/resend-laravel` 1.4.0 while retaining the SDK
  transitively, installs Fontaine 0.8.0, completes the bounded Composer/npm
  refresh and Laravel Boost discovery, and keeps the package's single built-in
  webhook controller/events behind a PodText missing-secret guard and the
  package signature verifier. One synchronous subscriber logs exactly four
  bounded operational fields for seven delivery events. Migrations,
  persistence, an admin event page, engagement/inbound/contact/domain
  processing, raw payloads, live calls, production actions, Packages 4-5 and
  push remain excluded. Handoff:
  `docs/phase-02/dependency-resend-webhook-fontaine-handoff.md`.
- Stage 1 found an unfinished overbuilt draft: 79 modified tracked files and 54
  default-status untracked entries. Exact enumeration found 55 files because
  `tests/Support/` was collapsed. Its trusted-status, normalization/checksum,
  manifest/digest, quarantine/relocation and conversion-journal behavior is
  rejected. Its untracked Package 1 handoff is invalid/incomplete.
- Package 1 implementation and local hash-stamped closeout are complete.
  Package 2 inventory-first gallery, Needs Repair, authoritative resolution,
  D01, picker All Media and same-page Add/Replace Image implementation is
  complete locally; its widened pre-gate matrix passes 195 tests / 2,278
  assertions after independent review corrections. Its first complete ordered
  gate passes Pint, FilaCheck, Vite and 1,016 tests / 13,488 assertions outside
  the known macOS browser sandbox; its post-result documentation gate repeats
  the same green 1,016 / 13,488 full-suite result. Package 2 is hash-stamped as
  `2a6de67816b9a7c8e53bcd29795a5b306a36dbaf`. Package 3 adds the shared
  immediate Upload/URL/Storage admission boundary, atomic asset/binding
  creation, four-source picker, bounded settings and Spotify URL convergence.
  Package 4 and its inline owner-picker correction are complete locally under
  their exact approvals; Package 5 remains separately gated. The only local
  environment action was the separately approved backup-first database
  activation recorded above; no further local or production action is
  authorized.
- Fresh local conversion evidence now records all 15 Curator rows bound to
  MediaAssets with zero diagnostics. Production was not probed or changed.
- Latest docs cleanup commit recorded here: `1cb158a docs: centralize prompt progress and ai development lessons`.
- Latest local `HEAD` before Prompt 10 implementation: `014c6b0 docs: update phase two prompt state, completion details for Prompts 08 and 09, and readiness notes for Prompt 10`.
- Admin management UX repair commit is present in history: `16ab33a fix: repair admin management ux after phase two resources`.
- Prompt 09 implementation commit is present in history: `22e11d0 feat: add phase two admin content management`.
- Prompt 08 implementation commit is present in history: `b15f5c1 feat: add taxonomy tags pinning settings and media foundation`.
- Prompt 07 implementation commit remains in history: `7edb82d feat: add transcription model revision`.
- Prompt 10 implementation commit is present in history: `fad6721 feat: extend phase two import export`.
- Prompt 11 public homepage/search is implemented and committed as `7ef2fa7 feat: add public content item homepage search`.
- Pre-Prompt-12 documentation pack is present in history; latest pushed pre-Prompt-12 docs state is `c1237eb docs: add pre-prompt 12 documentation and guidelines for public admin contributors`.
- Prompt 11R public frontend custom Livewire/Blade refactor is implemented and committed as `bb4b97c refactor: customize public content item discovery`.
- Prompt 11A admin relationship UX is implemented and committed as `1d81ec0 feat: improve admin relationship management ux`.
- Prompt 11B public contributors/transcribers discovery is implemented and committed as `8998f7e feat: add public contributor discovery`.
- Prompt 12 readiness sync is implemented and committed locally as `23242a1 docs: prepare prompt twelve after public discovery work`.
- Prompt 12 public item page/media/parser is implemented and committed as `ffba2b3 feat: add public item page media and transcript parser`.
- Public Front v2 research and blueprint planning commits are present: `f9a1f80 docs: research public front v2 json settings blueprint plan`, `adbed99 docs: add blueprint results for public front v2 plans`, and `40aeafc docs: add execution plan for Public Front v2 implementation`.
- PodText brand-logo customization is implemented and committed as `6962c82 feat: add customizable brand logo and height for admin and public panels`; the logo exists at `public/images/podtext-logo.jpg`.
- Public Front v2 correction/Step 1 prompt pack is present as `716ee5a docs: add corrections to Public Front v2 execution plan and initial Step 1 prompt files`.
- Public Front v2 docs correction before Step 1 is implemented and committed as `5586ec8 docs: correct public front v2 execution plan`.
- Public Front v2 Step 1 JSON Settings Architecture is implemented and committed as `fb759b5 feat: add public front json settings architecture`.
- Public Front v2 Step 3 Card Template Builder is implemented and committed as `a0146ce feat: add public front card template builder foundation`.
- Public Front v2 Step 4 Public Display Sections and Loopers is implemented and committed as `c0ce7d7 feat: add public display section loopers`.
- Public Front v2 Step 5 Latest and Search UX is implemented and committed as `eea9164 feat: refine public latest and search ux`.
- Public Front v2 Step 6 Public Forms and Submissions is implemented and committed as `49f6ab0 feat: add public forms and submissions`.
- Public Front v2 Step 7 About Page Content and Team Builder is implemented and committed as `b4fe4d5 feat: add public about page content and team builder`.
- Public Front v2 Step 8 Podcasts and Groups UX is implemented and committed as `f3d137e feat: add public podcasts and groups ux`.
- Public Front v2 Step 9 Public Menu/Header and UX Fixes is implemented and committed as `5cf3363 feat: add public menu header and ux fixes`.
- Public Front v2 Step 9R Menu/Header UX Fixes is implemented and committed as `bfcda46 fix: refine public menu header and homepage ux`.
- Public Front v2 Step 9R Podcast Episode Grid Settings follow-up is implemented and committed as `af23555 feat: add podcast episode grid settings`.
- Public Front v2 Step 10 Contributors and Top Transcribers UX is implemented and committed as `37ce738 feat: refine contributors and top transcribers ux`.
- Post-Step-10 public label/header polish is committed through `cea4f60 fix: refine theme selector and search UX in public header`.
- Post-Step-10 follow-up sequence after the Step 10 handoff commit: `e8077ea` simplified public-facing Hebrew labels, `20970a3` aligned Hebrew content/podcast terminology, `802cf4a` temporarily enabled public panel SPA mode, `2b1c6b3` removed SPA mode and externalized content-group type label defaults to translation keys, and `cea4f60` refined public header search/theme selector layout.
- Public Front v2 Step 10R-A1 PublicFrontRenderContext foundation is implemented and committed as `a230410 feat: add public front render context foundation`.
- Public Front v2 Step 10R-A2 render context adoption is implemented and committed as `d6d0bec refactor: route public front settings through render context`.
- Public Front v2 Step 10R-B1 card template select/options UX is implemented and committed as `34c6032 fix: expose custom public card templates in settings`.
- Public Front v2 Step 10R-B2 content item card part rendering is implemented and committed as `e3c81de feat: render content item card template parts`.
- Public Front v2 Step 10R-B3 content group and contributor card part rendering is implemented and committed as `f712791 feat: render group and contributor card templates`.
- Public Front v2 post-B3 contributor item card overflow follow-up is committed as `549b331 refactor: remove unused contributor transcription list component from grid layout`.
- Public Front v2 post-B3 multi-transcriber/card-template continuation is active. Step 10R-M1 is complete as `800218a feat: add multi-transcriber relationship foundation`; Step 10R-M2 is complete as `e813513 feat: replace episode authors with transcription transcribers`; Step 10R-M3 is complete as `825004c feat: add public transcription policy and aggregates`; Step 10R-M4 is complete as `af9f399 feat: render public transcribers and transcription aggregates`; urgent hotfix Step 10R-HF1 is complete as `2a5ff96 fix: preserve transcript markdown formatting`; Step 10R-M5 is complete as `aa7568c feat: add card template grouped parts labels and icons`; Step 10R-IP1 is complete as `9d565d7 feat: add episode page settings and publication dates`; Step 10R-IP2 is complete through `280b7ef feat: refine episode podcast identity settings`; Step 10R-IP3 is complete as `d83edf8 feat: add transcript reading controls and actions menu`; Step 10R-M6 is complete as `ebfa68e docs: summarize public front multi-transcriber card template arc` with the stabilization audit, C1 superseded status, and `transcription_display` default alignment to `effective_only`. Step 10R-UX1 is complete as `a88115f feat: standardize admin navigation tables and modals`. Step 10R-UX2 is complete as `e99f22a feat: add effective transcription edit action to episode lists` with the shared effective transcription edit action on both episode list surfaces and the v4 ledger/sequence amendment that schedules AX1-AX3. Step 10R-V1a is complete as `4c545eb feat: add default image fallback settings`. Step 10R-V1b is complete as `ba43145 feat: expand icon settings with searchable heroicon picker`. Step 10R-V1c is complete as `a846341 feat: add custom colors and theme safe podcast palette`. Step 10R-P1 is complete as `e17cefd perf: cache validated public front config`. Step 10R-S2 is complete as `f694c49 feat: add settings backup versions and restore`. Step 10R-S2V is complete as `86d21cb feat: add backup visual snapshots`. Step 10R-S1a is complete as `30e413c feat: add settings export and import wizard`. Step 10R-S1b is complete as `ada29fb feat: add settings import locks and add-only mode`. Step 10R-HF2 is complete as `f719d30 fix: bound snapshot index column lengths for mysql`. Step 10R-UX3 is complete as `0f3aed6 feat: add hebrew smart slugs and key contract alignment`. Step 10R-S1c is complete as `389cb0f feat: add inline import locks on settings page`. Step 10R-MP1 is complete as `8458a5d feat: add maintenance mode page and settings` and only covered maintenance mode. Step 10R-S1d is complete as `5b3593c feat: add import result report and maintenance hardening` for import result reporting plus MP1 maintenance/panel hardening. Importer Workbench WB1 is complete as `1148867 feat: add importer connections foundation`. Step 10R-HF3 is adopted and complete: first half `7d80c99 feat: add import/export logging and lifecycle tracing with horizon queue integration` fixed Horizon queue consumption, tracer/channel setup, and ContentGroup export row loading; completion commit `8d24ce8 fix: complete imports-exports hotfix across exporters with shared lifecycle tracing` completed all exporter row-loading/lifecycle/tracer gaps. HF3 coda localized import/export completion notifications. Next WB step is WB2, and the main queue position remains P2 before P3, AX1, SL1-SL4, AX2, AX3, B4, C2, and 9F-A through 9F-C.
- Episode workspace EP1-R docs-only research and planning are recorded in `docs/research/episode-workspace/00-ep1-research.md` and `docs/phase-02/episode-workspace-plan.md`; EP1 implementation is complete and committed as `e70d6f3 feat: add episode workspace with single transcription lens`.
- Images arc IMG-B is implemented and committed as `8c590ab58f1b4b4b89ec85b7c0541d95a41cde90 feat: add episode images, media guards, and content images export`.
- NAV1 admin navigation restructure is implemented and committed as `e59705b feat: restructure admin navigation groups and defer badges`: ungrouped quick-entry navigation first, grouped admin sections, deferred public-form submission badge evaluation, and suite timing diagnosis recorded in `docs/phase-02/admin-navigation-nav1-handoff.md`.
- MP2 forms page and maintenance form embedding is implemented and committed as `465967f feat: add forms management page and maintenance form embedding`: dedicated settings-backed `ManagePublicForms`, form clone helper, public maintenance plain POST form route, raw-HTML marker embedding, stale-CSRF retry rendering, and settings lifecycle coverage. Research and planning are recorded in `docs/research/maintenance-form/00-mp2-research.md` and `docs/phase-02/maintenance-form-mp2-implementation-plan.md`; the handoff is `docs/phase-02/maintenance-form-mp2-handoff.md`.
- TS1 test-suite performance is implemented and committed as `0d17c2a perf: cut settings test suite cost and restore dashboard navigation`: Dashboard is restored as the first admin sidebar item, non-backup settings-page tests fake only the backup snapshot job side effect, MP2/NAV1 handoff gaps are corrected, and the final profiled suite result is recorded in `docs/phase-02/test-suite-performance-ts1-handoff.md`.
- TOOLS1 admin tools page and Spotify links fetcher is implemented and committed as `a6d6408 feat: add admin tools page and spotify links fetcher`: adds the browser-local Markdown multi-editor Tools page, the transient Spotify links fetcher, importer-shaped CSV downloads, show lookup/oEmbed fallback support, IMG-B download authorization coverage, and `docs/phase-02/admin-tools-tools1-handoff.md`. No Composer changes were made. SF1 pulls the WB7 Spotify links-list source forward as a standalone admin tool only; WB2+ studio/recipe machinery remains unchanged.
- SP2 settings performance is implemented and committed as `fb3f515 perf: optimize public settings lock hints`: Job 1 attributed the missing settings-page cost to repeated inline import-lock hint unit-path lookups, memoized that lookup, stopped the page split by evidence, and still completed Job 2's scoped validator plus stored-settings normalize command. Handoff: `docs/phase-02/settings-performance-sp2-handoff.md`.
- FETCH1 Spotify fetcher reduced-mode upgrade is complete as `524a292 feat: enrich spotify fetcher reduced mode with opengraph and previews`: reduced mode now merges public oEmbed with a plain-HTTP OpenGraph/LD-JSON tier, restores admin-side image previews including the oEmbed-only thumbnail regression, normalizes Spotify descriptions to Markdown for table/CSV/workspace surfaces, adds source labels, and polishes the MP2 raw-maintenance missing-marker fallback container. Handoff: `docs/phase-02/spotify-fetcher-fetch1-handoff.md`.
- FIX1 fetcher direct import and workspace publishing fixes are complete as `700de7f feat: add fetcher direct import and workspace publishing fixes`: fetcher CSV rows now carry stable reference keys for strict native imports, direct import creates/links/skips draft podcast/episode rows from fetched data, workspace Spotify fill has an options modal with podcast matching and slug/prefix controls, publication status selects autofill empty publish dates, trusted raw HTML fields use LTR code editors and save/render verbatim, HTTP tests use committed fixtures with stray requests prevented, and settings cache flags plus invalidation coverage are in place. Handoff: `docs/phase-02/fetcher-workspace-fix1-handoff.md`.
- MAIL1 mail foundation and email OTP form verification is implemented and committed as `330350d feat: add mail foundation and email otp form verification`: Resend-first transactional mail wiring, queued localized OTP mail, verification-code storage/manager, Livewire and maintenance plain-POST form enforcement, public-form hardening, import failure cause summaries, public-site admin nav link, and tiered workspace Spotify podcast recognition are complete. Handoff: `docs/phase-02/forms-mail-mail1-handoff.md`; research and plan are under `docs/research/forms-mail/`.
- ROLES1 user roles and multi-transcription visibility gates are implemented and committed as `9cd7349 feat: add user roles and multi-transcription visibility gates`: fixed enum roles, admin-panel role access, super-admin-only Users resource, the mode/role multi-transcription gate, and server-side settings save guards against forged hidden state are complete. Handoff: `docs/phase-02/roles-gates-roles1-handoff.md`; research and plan are under `docs/research/roles-gates/`.
- LENS1 single-transcription ontology and vocabulary is implemented as `2299c71 feat: apply single transcription ontology across public and admin`: single mode now counts distinct effective episodes, selects English/Hebrew episode-language variants, suppresses per-episode count parts and public history, blocks unsanctioned second-row creation, and scopes the standalone Transcriptions resource to one current row per episode with super-admin history access. Multi behavior and base strings remain unchanged. Per the operator's clarification, the episode relation manager and item/group admin table featured/count columns remain intentional and unchanged. Handoff: `docs/phase-02/single-lens-lens1-handoff.md`; research and plan are under `docs/research/single-lens/`.
- SP3A settings foundations are complete as `88fdda2 perf: add settings measurement protocol, lock surface, and import overlay`: its historical deterministic measurement established the recorded baseline; request-scoped, group-and-payload-keyed lifecycle memoization with byte-identical units, section/important-field lock surfaces, acting-user overlays, anonymous normalization protection, and the admin Select loading sweep remain functional. The temporary runtime measurement plane was retired by settings metrics Mini-task 1 and the remaining test-only plane by Mini-task 2. `AdminUxSettings` remains outside lifecycle import/locks. Handoff: `docs/phase-02/settings-sp3a-handoff.md`; research and plan are under `docs/research/settings-performance/03-sp3a-*`.
- MAIL2 inline email verification UX is complete in the current run: verification now belongs to the configured email field in the Livewire public form and maintenance fallback, with logical suffix actions, server-backed validity/reset state, resend countdown feedback, inline verification errors, success collapse/badge behavior, and changed-address re-verification enforcement. MAIL1 code delivery, hashing, challenge binding, signed maintenance POST, consume-on-submit, and rate-limit mechanics remain unchanged. Handoff: `docs/phase-02/forms-otp-ux-mail2-handoff.md`; research and plan: `docs/research/forms-mail/01-mail2-notes.md`.
- OTP-POLICY1 is complete in `0394ab5`: the email OTP expiry, maximum attempts, and resend cooldown now come from env-backed `forms.otp` config; the default expiry is five minutes; email expiry copy is pluralized correctly; both Livewire and maintenance code inputs show the configured bilingual expiry hint; and adjacent OTP actions render at logical inline-end (left in Hebrew RTL, right in English LTR). OTP hashing, challenge binding, refusal, cooldown enforcement, and consume-on-submit control flow are unchanged. Handoff: `docs/phase-02/forms-otp-policy-config-handoff.md`; research and plan: `docs/research/forms-mail/02-otp-policy-config-notes.md`.
- SP3B settings subject pages and fresh owned-path saves are complete in the current run: eight ordinary focused pages, temporary Card Templates, and Manage Public Forms now preserve disjoint stale-page changes through a fresh canonical settings snapshot and registry-owned root overlay; legacy monolith URLs redirect; lifecycle/import/restore/normalize/lock/backup/Admin UX paths and the SP3A SHA remain unchanged. Its unavailable browser measurement remains historical provenance, not pending work; settings metrics Mini-task 2 retired the bespoke browser-evidence route. Handoff: `docs/phase-02/settings-sp3b-handoff.md`; research and plan are under `docs/research/settings-performance/04-sp3b-*`.
- SP3C template library and one-template editor are complete in the current run: the temporary writable whole-list Card Templates page is now an unpaginated read-only Filament library backed by one fresh settings snapshot and one projected HomepageSection reference scan; separate create/edit pages mount one authorized draft; the focused writer provides sequential stale detection, reference/default/collision guards, fresh protected-part restoration, strict sibling/foreign-root preservation, and the existing one-save lifecycle. Its recorded canary threshold and frozen budgets remain historical provenance. Settings metrics Mini-task 2 retired the canary/report plane and pending browser-measurement route while preserving functional editor, security, query, and browser coverage. Handoff: `docs/phase-02/settings-sp3c-handoff.md`; research and plan are under `docs/research/settings-performance/05-sp3c-*`.
- AUTHZ1's package/schema/catalog foundation is complete but dormant. Shield
  4.2.0 is deliberately unregistered, `User` lacks `HasRoles`, no package
  assignments or compatibility grants are active, and no role-management UI
  exists. Legacy `users.role`, ranks, Gates/macros, panel/Horizon/maintenance
  admission, Users Resource restrictions, and callers remain authoritative.
  The three dormant legacy-role migration commands are now withheld from
  Artisan discovery. The migration services, frozen catalog, and reports remain
  isolated historical foundation evidence, not an active cutover queue.
- The maintenance Livewire enforcement effects audit v1 is complete in `docs/research/settings-performance/14-maintenance-livewire-enforcement-effects-audit.md`. Its server correction is accepted. The medium production stale-tab notification/body/retry UX and focused missing regression coverage are named `MAINT-LW-UX1`, deferred independently, and due before the first later public Livewire navigation/polling/lazy/deferred/stream/upload expansion.
- AUTHZ1-C and remediation/audit reports 15–18 are complete historical
  evidence. H-01, M-01, and L-01 remain real findings on the unused migration
  utility, but the feature-first reset narrows them to a non-operational threat
  boundary. The operator-approved v1 command closure is complete: the three
  auto-discovered command classes are deleted, their names are absent from
  Artisan, and an architecture test prevents application callers from reaching
  the retained migration namespace. AUTHZ1-D–I, package cutover,
  production migration, and MySQL rehearsal are stopped as current work.
- The controlling reset is
  `docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`.
  BQ1/BQ2 multiple roles, direct grants, catalog governance, and role UI are
  future options. ARCH1 and SP3D are deferred research directions, not
  prerequisites for Card Template preview/side-panel UX or other bounded
  feature work. Existing Card Template/Public Form storage and writers remain
  authoritative until a separately approved present requirement changes them.
- Step 5B Card Template Preview UX is complete under Laravel Simplifier audit
  `LS-20260717-STEP5B-01`, approved option
  `STEP5B-O1-FOCUSED-PREVIEW`, implementation commit
  `c75d0f2b2d476c58d12c16610ea97ba4088c5e79`. Create/Edit now preview the current unsaved single draft
  through an in-memory default render context, deterministic/transient
  public-safe samples, an `xl` adjacent region, and a native sub-`xl`
  slide-over. The later approved restricted-selector closure audit
  `LS-20260717-STEP5B-CLOSURE-01` / `STEP5B-CLOSURE-O1` suppresses selector
  rendering and all sample lookup paths for restricted shells without adding a
  permission or changing public-sample semantics; its implementation hash is
  `69813dbd4002ed8e7c3e42e640f7d48085e275da`. Handoff:
  `docs/phase-02/settings-step5b-card-template-preview-handoff.md`.
- Step 5B's expanded Card Template editor UX closure is complete under
  `LS-20260718-STEP5B-CARD-TEMPLATE-UX-01` /
  `STEP5B-CARD-UX-O1`; implementation commit
  `d889d4f6fca521616e148890502b038a113dff9c`. It keeps the preview in
  its logical-end column, moves compact import-lock metadata to the header,
  adds transient bounded zoom and an inline 10-preload/50-search public-safe
  sample Select with own-image-first ordering, adds browser-remembered
  inline/native-slide-over Builder modes, expands automatic refresh to finite
  rendered presentation Selects, localizes Builder summaries, and uses the
  existing missing-image fallback for image-less episode previews. Restricted
  no-render/no-query/forged boundaries and all no-persistence/lifecycle
  invariants remain green. No next implementation is selected.
- Step 5B Card Template editor UX2 is complete under
  `LS-20260719-STEP5B-CARD-TEMPLATE-UX2-01` /
  `STEP5B-CARD-UX2-O1-COMPAT-MODAL`; implementation commit
  `82e639d0fd22c06c52a70acec7c26ee9e2d8c72a`. The
  focused editor now hydrates legacy explicit order into a position-canonical
  Builder, removes editable order fields, writes contiguous x10 order on
  focused preview/save, and offers a native owning-Builder move-to-position
  modal. Compact item headings/summary separators, native inline collapse,
  collapsible Template settings/parts sections, always-reachable transient
  label/icon switches, and centered 100/90/80/70/60 card-width preview controls
  are covered in HE/EN feature, canary, and browser tests. The global validator,
  value object, import, restore, backup, and lifecycle compatibility paths are
  unchanged. O2, O3, O4, production normalization, and another roadmap
  selection remain excluded. Handoff:
  `docs/phase-02/settings-step5b-card-template-editor-ux2-handoff.md`.
- Step 5B's strict image-order follow-up is complete under
  `LS-20260719-STEP5B-CARD-UX2-FOLLOWUP-01` /
  `STEP5B-CARD-UX2-FU01-STRICT-IMAGE-ORDER`; its implementation commit is
  recorded in
  `docs/phase-02/settings-step5b-card-template-image-order-followup-handoff.md`.
  Content-item and content-group cards retain existing row/card geometry when
  the image leads, but render one position-authoritative stacked part stream
  when the image is interleaved. Preview and public output share the repair;
  native scoped movement, global order compatibility, contributor cards,
  sample queries, validation targeting, lifecycle, and persistence remain
  unchanged. Research 31 remains the provenance inventory; FU02 sample ranking
  and FU03 path-corrected validation are now complete. FU04–FU06 remain
  sequentially later and unapproved. FU03/O4 is an internal Step 5B bug, not a
  GitHub issue.
- Step 5B's `lg` preview-shell O1 is complete under
  `LS-20260719-STEP5B-CARD-RENDER-O1-LG-PREVIEW-SHELL-01` /
  `STEP5B-CARD-RENDER-OVERHAUL-O1-LG-PREVIEW-SHELL`; its implementation hash
  is recorded in
  `docs/phase-02/settings-step5b-card-template-preview-lg-column-handoff.md`.
  Below 1024px the preview is a native slide-over; from 1024px it is one
  adjacent logical-end column. Alpine, Tailwind, action visibility, unmount
  sequencing, rapid resize-back, and bidirectional focus restoration now share
  that boundary. The measured finite `lg` track keeps 376px for the editor at
  1024px and restores the established preview track at `xl`. Renderer flow,
  sample ranking, validation targeting, order compatibility, modal refresh,
  copy, navigation, lifecycle, permissions, persistence, and public output are
  unchanged. Research/plan 33–34 retain the seven-option program and all
  deferred findings; no later option is selected automatically.
- Step 5B's ordered-flow O2 foundation is complete in the current run under
  `LS-20260719-STEP5B-CARD-RENDER-O2-ORDERED-FLOW-01` /
  `STEP5B-CARD-RENDER-OVERHAUL-O2-ORDERED-FLOW-FOUNDATION`; its implementation
  commit is `f56ef36983a8813a1ebe18b1087864c626a4c8f5` and its docs-only
  hash-stamp commit is `27f38aeaebc8ab2ff4279abd2a905efdce82b495` in
  `docs/phase-02/settings-step5b-card-template-ordered-flow-foundation-handoff.md`.
  Item and group presenters now select geometry from each record's actual
  filtered part sequence. A real leading image plus body may retain row
  geometry; body-only, media-only, interleaved, and repeated-image output use
  content-aware card/ordered flow with exact diagnostics and no phantom media
  track. Repeated top-level images render once each in order, group geometry
  lives on the article without a whole-card anchor, and both Filament themes
  scan the renderer's finite Tailwind classes. O1's 1024px shell/focus/root
  contract and `d8f42da` navigation remain unchanged. FU03 path-corrected
  validation is now complete; FU04–FU06 remain sequentially later and
  unapproved. Global
  order cutover, nested images, contributor redesign, production normalization,
  persistence, permissions, lifecycle, migrations, dependencies, and another
  roadmap selection remain deferred.
- Step 5B FU02 sample-ranking parity is complete in the current run under
  `LS-20260719-STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-01` /
  `STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-PARITY`; its implementation commit is
  `a8be0aa4e7d89d8f70276ff497ee7b54a63d20df` and its docs-only hash-stamp
  commit is `23c3ac9e9c780e3f2b8882d5f9c4770f3cbb7f1e`, recorded in
  `docs/phase-02/settings-step5b-card-template-sample-ranking-parity-handoff.md`.
  Automatic selection, exactly ten preloaded options, capped fifty-result
  search, selected labels, and preview rendering now share the current
  validated request context and effective-image tiers: own local/external,
  permitted inherited group cover, configured family/global default, then no
  effective image. Public eligibility, contributor ordering, restricted
  zero-render/zero-query/forged guards, request-scoped bounded queries, O1/O2
  UI/rendering contracts, and `d8f42da` navigation remain unchanged. FU03 is
  now complete; FU04–FU06 are sequentially later and unapproved. Every wider
  renderer, persistence, lifecycle, permission, migration, dependency, or
  production change remains deferred.
- Step 5B FU03 path-corrected validation is complete in the current run under
  `LS-20260719-STEP5B-CARD-UX2-FU03-PATH-CORRECTED-01` /
  `STEP5B-CARD-UX2-FU03-PATH-CORRECTED-CLOSURE`; implementation
  `659762f931a01626237a91de27a873e423c0713c` is closed by this immediate
  documentation-only hash-stamp follow-up in
  `docs/phase-02/settings-step5b-card-template-path-corrected-validation-handoff.md`.
  Structured validator issues now retain typed provenance through the draft
  normalizer and write exception. Strict root, top-level, and one-level nested
  paths resolve against current UUID-owned Builder state after hydration and
  reordering. Inline mode expands and focuses the exact real field; slide-over
  mode mounts the verified native parent/child action stack before placing the
  error. Transient label/icon controls may reveal only when necessary. For a
  recognized root or parts path, malformed positions/depths, stale or
  unsupported descendants, and action/focus failures use deterministic
  verified-Builder or visible-control fallback. An invalid singleton prefix is
  rejected without an owner and terminates at the danger notification; no
  rejected path becomes a key, slug, label, or value-based target.
  Authenticated Hebrew RTL and English LTR browser coverage proves reordered,
  collapsed, inline, wide and 1023px slide-over, Escape/restoration,
  restricted, one-request, and no-persistence behavior. O1/O2/FU02 and
  `d8f42da` navigation remain unchanged. The complete serial gate passes 809
  tests / 11,807 assertions. FU04 order-compatibility closure is next and
  unapproved; FU05–FU06 remain later and unapproved.
- CURATOR-HF1 is an in-between Curator picker hydration repair: registered
  header-logo and custom default-image selections now hydrate from raw path
  state exactly once and retain Curator's UUID-keyed item map for rendering and
  picker actions. Settings continue to store plain paths; preservation-only
  legacy paths with no Curator row remain safe. It does not change SP3B's
  focused settings schemas or fresh owned-path save lifecycle. Handoff:
  `docs/phase-02/curator-picker-hydration-hf1-handoff.md`.
- CURATOR-G1 Stage 2 is complete locally under audit
  `LS-20260720-CURATOR-G1-IMAGE-LIBRARY-01`, option
  `CURATOR-G1-O2-FULL-APP-OWNED-CURATOR-SURFACE`. It replaces the vendor
  gallery/picker boundaries with one Admin-or-higher app surface; adds the
  finite image validator, private SVG/raster normalization, immutable
  checksum-journaled Media identities, typed shared attachments, portable
  import/export keys, journaled registration/mutations/repair, and bounded
  query/state behavior. This supersedes CURATOR-HF1's path-only settings
  contract and IMG-A's in-place registration behavior while retaining legacy
  paths as reversible mirrors. Production deployment, migrations, backfills,
  one-path registration applies, repair/cache actions, disallowed-media
  disposition, SVG IDs 6/7, legacy-path retirement/non-null proof, and measured
  curation dimensions remain separately approval-gated and were not executed.
  Handoff: `docs/phase-02/curator-g1-image-library-o2-handoff.md`.
- CURATOR-G1 LMTC closes the existing-row transition gap locally under audit
  `LS-20260721-CURATOR-G1-LEGACY-MEDIA-TRANSITION-CORRECTION-03`, option
  `CURATOR-G1-LMTC-O1-IN-PLACE-JOURNALED-TRANSITION-DEFAULT-FALLBACK`.
  It adds a deterministic schema-aware manifest/digest, exact one-ID/path
  same-ID raster and reusable SVG transition, exact-path rowless import,
  ordered backfill/integrity classifications, typed owner diagnostics, safe
  ContentGroup/ContentItem replacement or detach-to-default, and strict
  production cutover instructions. It adds no schema or dependency and does
  not weaken the app-owned gallery. LMTC itself did not run any environment
  action; the local incident had already applied the four G1 media migrations
  and dormant permission migration in batch 8, while production remained
  pre-G1. No local/production transition, backfill, repair, sanitation,
  cache/process action, or real IDs 6/7 action ran. Handoff:
  `docs/phase-02/curator-g1-legacy-media-transition-correction-handoff.md`.
- The completed forward media route is the post-Package-3 correction under audit
  `LS-20260723-PODTEXT-MEDIA-P3-POST-ACQUISITION-UX-01`, option
  `MEDIA-P3-POST-O3-IMMEDIATE-SOURCE-WORKSPACE`. Package 1 added portable assets,
  Curator bindings, the attachment bridge and database-only conversion;
  Package 2 added visible inventory/diagnostics, D01 and picker/replacement UX;
  Package 3 added one immediate Upload/URL/Storage admission boundary, atomic
  Curator/asset/binding creation, four-source picker and Spotify URL reuse.
  The completed correction hardens that boundary and organizes its
  acquisition rail without changing permanence or attachment timing.
  `media_attachments.media_id` remains local owner authority and `curator.path`
  remains file-location authority. Existing-media visibility must not depend on
  key, root, filename, metadata, size, dimensions, normalization, checksum or
  provenance. Existing-media selection changes only the attachment and mirror
  path; it never copies, moves, renames or normalizes media. Cancelling a staged
  Gallery selection changes no owner, row, file or journal. Successful
  Upload/URL/Storage becomes a permanent library item immediately; cancelling
  the picker or owner form cancels only the pending attachment. New raster
  admission preserves bytes, SVG uses the existing sanitizer, and legacy
  normalization/journals remain isolated from acquisition. Package 4 now adds
  only the approved owner-image workspace and bounded Resource-table action
  rider. Package 5 remains unapproved. Dependency/toolchain changes, real
  local/production schema/data/file/cache actions, deploy, process actions and
  push remain outside this implementation run.
- Settings development metrics retirement Mini-task 1 is complete as
  `3e5c411777994c431417dfd823576286d12d5c29` under audit
  `LS-20260721-SETTINGS-DEV-METRICS-03`, option
  `SETTINGS-METRICS-O1-FULL-RETIREMENT`. It removed the SP1/SP3A/SP3B/SP3C
  runtime profiler, middleware, fixtures, alternate query-driven state,
  response headers, lifecycle/reference counters, config/log channel, and
  browser harness. Handoff:
  `docs/phase-02/settings-development-metrics-retirement-mini1-handoff.md`.
- Settings development metrics retirement Mini-task 2 is complete as
  `c37bcf7f5cd7ea0408623218edeee4842d8e6592` under audit
  `LS-20260722-SETTINGS-DEV-METRICS-M2-01`, option
  `SETTINGS-METRICS-M2-O1-BOUNDED-FULL-RETIREMENT`. It deletes only the isolated
  eight-file canary graph, removes report-only branches and canary translations,
  migrates consumed production hooks to durable Card Template selectors, and
  supersedes active measurement routing without rewriting historical results.
  Functional settings saves, lifecycle memoization, Card Template protection,
  deterministic query checks, browser interactions/deadlines, Filament 5.7.1,
  and Curator G1/LMTC remain covered. Handoff:
  `docs/phase-02/settings-development-metrics-retirement-mini2-handoff.md`.

## Prompt Progress

| Prompt | Status | Commit / evidence | Notes |
|---|---|---|---|
| Prompt 07 transcriptions model revision | Complete | `7edb82d feat: add transcription model revision` | Prompt 07 migrations are applied locally. |
| Prompt 08 taxonomy/settings/media foundation | Complete | `b15f5c1 feat: add taxonomy tags pinning settings and media foundation` | Spatie tags/settings foundation and media metadata fields exist. |
| Prompt 09 admin content management | Complete | `22e11d0 feat: add phase two admin content management` | Admin Resources and relation-manager baseline exist. |
| Admin UX repair | Complete | `16ab33a fix: repair admin management ux after phase two resources` | Repaired ContentItem edit tab behavior and related admin workflows. |
| Prompt 10 import/export | Complete | `fad6721 feat: extend phase two import export` | Native Filament import/export baseline exists and should be preserved by later prompts. |
| Post-Prompt-10 guidance sync | Complete | `773f1c0 docs: sync prompt workflow lessons after prompt ten` | Markdown-only guidance sync; did not start Prompt 11. |
| Post-Prompt-10 prompt-progress centralization cleanup | Complete | `1cb158a docs: centralize prompt progress and ai development lessons` | Markdown-only cleanup; centralized rolling progress in this file; did not start Prompt 11. |
| Prompt 11 public homepage/search | Complete | `7ef2fa7 feat: add public content item homepage search` | Public homepage/search lists `ContentItem` cards using public visibility rules, settings, filters, routes, and homepage section foundations. |
| Pre-Prompt-12 documentation pack | Complete | `c1237eb docs: add pre-prompt 12 documentation and guidelines for public admin contributors` | Adds Prompt 11R/11A/11B sequencing before Prompt 12 and ignores local Herd remote-site config. |
| Prompt 11R public frontend custom Livewire/Blade refactor | Complete | `bb4b97c refactor: customize public content item discovery` | Public homepage/search/category/tag listing no longer uses Filament Table as the public UI; custom Livewire state and Blade components render cards, filters, pagination, and homepage sections. |
| Prompt 11A admin relationship UX | Complete | `1d81ec0 feat: improve admin relationship management ux` | Adds safe admin create/edit option modals and `ContentGroupResource` -> `ContentItemsRelationManager`; Prompt 12 not started. |
| Prompt 11B public contributors/transcribers discovery | Complete | `8998f7e feat: add public contributor discovery` | Adds `top_transcribers`, public contributor directory, previews, full contributor page, and demo seeder state; Prompt 12 not started. |
| Prompt 12 readiness sync | Complete | `23242a1 docs: prepare prompt twelve after public discovery work` | Prepared Prompt 12 activation without starting implementation. |
| Prompt 12 media embed/item page/parser | Complete | `ffba2b3 feat: add public item page media and transcript parser` | Adds the public item page, safe media component behavior, and parse-only transcript viewer. |
| Public Front v2 planning/research | Complete | `40aeafc docs: add execution plan for Public Front v2 implementation` plus prior research/blueprint commits | Public Front v2 should run before Prompt 13 unless the user explicitly chooses dashboard metrics first. |
| Public Front v2 docs correction before Step 1 | Complete | `5586ec8 docs: correct public front v2 execution plan` | Corrects execution order, reserves transcription publication policy, and requires Step 1 handoff. |
| Public Front v2 Step 1 JSON Settings Architecture | Complete | `fb759b5 feat: add public front json settings architecture` | Adds the JSON settings architecture foundation and creates `docs/phase-02/public-front-v2-step1-json-settings-handoff.md` for ChatGPT/Yoni review. |
| Public Front v2 Step 3 Card Template Builder | Complete | `a0146ce feat: add public front card template builder foundation` | Adds JSON-first card template registry/validator support, support classes, admin settings UI, compatibility rendering attributes, tests, and Step 3 handoff. |
| Public Front v2 Step 4 Public Display Sections and Loopers | Complete | `c0ce7d7 feat: add public display section loopers` | Adds homepage section JSON config columns, section/looper validation and query support, admin config fields, Step 3 template integration, tests, and Step 4 handoff. |
| Public Front v2 Step 5 Latest and Search UX | Complete | `eea9164 feat: refine public latest and search ux` | Adds looper-driven Latest UX, search filter drawer, multi-select category/tag filters, card layout repair, controlled content-item renderer, tests, and Step 5 handoff. |
| Public Front v2 Step 6 Public Forms and Submissions | Complete | `49f6ab0 feat: add public forms and submissions` | Adds JSON-first public form definitions, `PublicFormSubmission` schema/model/resource, Livewire public form modal/slide-over, honeypot/rate limiting, admin settings UI, tests, and Step 6 handoff. |
| Public Front v2 Step 7 About Page Content and Team Builder | Complete | `b4fe4d5 feat: add public about page content and team builder` | Adds JSON-first About page content, public `/about`, safe Markdown/RichEditor rendering, team profiles in JSON settings, team/about image upload constraints, optional Step 6 form CTA integration, tests, and Step 7 handoff. |
| Public Front v2 Step 8 Podcasts and Groups UX | Complete | `f3d137e feat: add public podcasts and groups ux` | Adds canonical `/podcasts`, public group detail pages at `/podcasts/{contentGroupSlug}`, JSON-first podcast settings, public group query support, group cards, category/search UX, tests, and Step 8 handoff. |
| Public Front v2 Step 9 Public Menu/Header and UX Fixes | Complete | `5cf3363 feat: add public menu header and ux fixes` | Adds tabbed public settings organization, About/team card fixes, contributor list/preview repairs, homepage chrome/header fixes, JSON-powered public menu/header, theme selector, content-block sections, tests, and Step 9 handoff. |
| Public Front v2 Step 9R Menu/Header UX Fixes | Complete | `bfcda46 fix: refine public menu header and homepage ux` | Verifies Step 8/9 plans, improves FilamentExamples MCP discipline, repairs root-query homepage chrome, extends header logo/search/alignment/theme behavior, adds image styling settings, repairs contributor preview grid, and documents future footer/section-builder scope. |
| Public Front v2 Step 9R Podcast Episode Grid Settings follow-up | Complete | `af23555 feat: add podcast episode grid settings` | Adds JSON-first podcast detail episode grid/settings controls under `podcasts_page.group_page`, keeps `ContentItemBrowser` as Livewire owner, and was followed by the now-complete Step 10 implementation. |
| Public Front v2 Step 10 Contributors and Top Transcribers UX | Complete | `37ce738 feat: refine contributors and top transcribers ux` | Adds `contributors_page` settings, settings UI, horizontal top-transcriber selector/preview, contributor directory/page controls, grouped contributor transcription titles, tests, and Step 10 handoff. |
| Post-Step-10 public label/header polish | Complete | `e8077ea`, `20970a3`, `802cf4a`, `2b1c6b3`, `cea4f60` | Simplifies Hebrew public/admin labels, aligns podcast/episode terminology, records that temporary public panel SPA mode was removed, externalizes content-group type-label defaults to translation keys, and refines public header search/theme selector layout. |
| Public Front v2 Step 10R-A1 render context foundation | Complete | `a230410 feat: add public front render context foundation` | Adds request-scoped `PublicFrontRenderContext`, `PublicFrontRenderContextFactory`, scoped app binding, group accessors including future-safe `footer()`, and focused tests. |
| Public Front v2 Step 10R-A2 render context adoption | Complete | `d6d0bec refactor: route public front settings through render context` | Routes public Livewire components, public page classes, menu/about/card-template support services, and Blade compatibility defaults through `PublicFrontRenderContext`; public output behavior is intended to remain unchanged. |
| Public Front v2 Step 10R-B1 card template select/options UX | Complete | `34c6032 fix: expose custom public card templates in settings` | Adds family-scoped resolver option helpers, makes podcast settings template selects read safely normalized same-session `card_templates` state, routes homepage section template options through the resolver, and documents contributor template setting selection as deferred because no contributor template key setting exists yet. |
| Public Front v2 Step 10R-B2 content item card part renderer | Complete | `e3c81de feat: render content item card template parts` | Adds a controlled content item card presenter, makes supported content item template parts visibly render on homepage/search/category/tag and podcast detail item cards, and keeps group/contributor renderers deferred to Step 10R-B3. |
| Public Front v2 Step 10R-B3 content group and contributor card renderers | Complete | `f712791 feat: render group and contributor card templates`; follow-up `549b331 refactor: remove unused contributor transcription list component from grid layout` | Adds controlled presenters for `content_group` and `contributor` cards so `/podcasts`, homepage group/contributor sections, contributor directory cards, and top-transcriber selector cards visibly honor safe card template parts. The follow-up removed the old contributor transcription list from contributor item grid cards to avoid overflow. |
| Public Front v2 Step 10R-M1 multi-transcriber schema and model foundation | Complete | `800218a feat: add multi-transcriber relationship foundation` | Adds the `author_transcription` pivot and backfills it from `transcriptions.author_id`, adds ordered multi-transcriber relationships/helpers, preserves `transcriptions.author_id` compatibility/primary storage, and keeps first-transcription-auto-featured behavior intact. `author_content_item` remained for M2 and is removed by Step 10R-M2. |
| Public Front v2 Step 10R-M2 episode-author removal and transcription transcriber conversion | Complete | `e813513 feat: replace episode authors with transcription transcribers` | Drops `author_content_item`, removes `ContentItem::authors()` and `Author::contentItems()`, converts admin transcription forms/actions plus import/export/public search/display paths to `Transcription::authors()`, and keeps `transcriptions.author_id` synchronized as compatibility primary storage. |
| Public Front v2 Step 10R-M3 public transcription policy and aggregates | Complete | `825004c feat: add public transcription policy and aggregates` | Adds normalized `transcription_policy`, scoped policy/selector/aggregate services, pivot-backed contributor counts, featured-only/all-published count behavior, public item/group aggregate subselects, and policy-aware public transcriber filters. |
| Public Front v2 Step 10R-M4 public rendering and aggregate attributes | Complete | `af9f399 feat: render public transcribers and transcription aggregates` | Renders transcription-backed transcribers, optional all-published count badges, per-transcription viewer tab transcribers, contributor-context transcription data, and group aggregate attributes. Adds `transcription_display` settings, a settings migration, non-production lazy-loading prevention, memoized viewer/top-transcriber lists, and a bounded query-count harness. Next mini-step is Step 10R-M5 grouped parts, labels, and icons. |
| Public Front v2 Step 10R-HF1 transcript viewer Markdown rendering hotfix | Complete | `2a5ff96 fix: preserve transcript markdown formatting` | Removes the capped Symfony sanitizer pass from the Markdown path, adds transcript-specific soft-break rendering and fixed transcript typography classes, guards the executable-block prefilter against PCRE null-wipe, and keeps generated Markdown images HTTPS-only. P3 transcript render economy remains scheduled; Step 10R-M5 remains the next mini-step. |
| Public Front v2 Step 10R-M5 card-template labels/icons/groups | Complete | `aa7568c feat: add card template grouped parts labels and icons` | Adds escaped label rendering, finite Heroicon-backed icon rendering, label alignment tokens, one-level `part_group` rendering, nested admin Builder support, validator normalization/rejection coverage, and rendered-output tests across content item, content group, and contributor card families. No schema or new settings keys were added. The next mini-step is Step 10R-IP1 for episode-page settings/date foundations. |
| Public Front v2 Step 10R-IP1 episode page settings/date foundation | Complete | `9d565d7 feat: add episode page settings and publication dates` | Adds the `item_page` settings group, Spatie settings migration, Episode page settings tab, site/original/transcription date settings, finite info-badge tokens, `PublicFrontRenderContext::itemPage()`, and content-item card attributes for `site_published_date` and `original_published_date`. R1 data/attributes, R2-R6, R13 token foundation, and R23 are landed. IP2 owns public episode page placement/rendering. |
| Public Front v2 Step 10R-IP2 episode page header/info layout | Complete | `280b7ef feat: refine episode podcast identity settings` | Extends `item_page` with `show_breadcrumbs`, `podcast_identity`, and ordered `info_fields`; adds settings migrations and admin controls; rebuilds the public episode header with item/podcast image fallback, linked podcast identity, configured date labels/icons, linked category/tag/transcriber info fields, and site-wide link-audit tests. Review-fix coverage adds podcast identity style (`badge`, `text`, `title`, `hidden`), size, semantic/image-sampled color tokens, and placement above/below/before/after the title. R1 page part and R11-R18 are landed. |
| Public Front v2 Step 10R-IP3 transcript actions and reading UX | Complete | `d83edf8 feat: add transcript reading controls and actions menu` | Adds `item_page.show_transcript_actions_menu` default false, settings migration/admin toggle, share block above the player, transcript details row, settings-gated Blade/Alpine actions menu, font-size controls, fullscreen reading mode, and player/media column toggle. R7-R10 and R19-R22 are landed. |
| Public Front v2 Step 10R-M6 stabilization closeout | Complete | `ebfa68e docs: summarize public front multi-transcriber card template arc` | Verifies M1-M5, HF1, and IP1-IP3 regressions; records R1-R23 as landed; confirms `author_content_item`, `ContentItem::authors()`, and `Author::contentItems()` remain absent; marks C1 superseded; aligns existing `transcription_display` defaults/fallbacks/settings rows to `effective_only`; leaves F1-F3, F7, F11-F13, and F15 for P1-P3/B4/C2. |
| Public Front v2 post-M6 admin/settings enhancement planning | Planned | `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`; `docs/research/public-front-v2/19-admin-settings-enhancement-mcp-research.md` | Adds the v4 queue for admin navigation/table/modal standards, effective transcription edit action, split default-image/icon/color settings, caching, backups/imports, AX1-AX3 motion work, slider/modal display templates, and the remaining performance/card steps. |
| Public Front v2 Step 10R-UX1 admin navigation/table/modal standards | Complete | `a88115f feat: standardize admin navigation tables and modals` | Adds the central admin navigation order map, admin-scoped global table/action/Section defaults, combined relation-manager tabs with content first on item/group edit pages, scoped tab-label CSS, tests, and the earlier ledger/sequence amendment. |
| Public Front v2 Step 10R-UX2 effective transcription edit action | Complete | `e99f22a feat: add effective transcription edit action to episode lists` | Adds one shared action class mounted on the Episodes resource table and podcast Episodes relation manager, two-tier admin fallback after public-effective resolution, transcriber pivot/`author_id` synchronization through `Transcription::syncTranscribers()`, a zero-query context column, focused tests, and the v4 ledger/sequence alignment with AX1-AX3 scheduled. |
| Public Front v2 Step 10R-V1a default/no-image fallback settings | Complete | `4c545eb feat: add default image fallback settings` | Adds `default_images` settings for global/content item/content group/contributor families, finite inherit/custom/none modes, validator/migration/render-context support, constrained admin FileUploads, and shared fallback rendering on public cards and detail pages. |
| Public Front v2 Step 10R-V1b Heroicon registry and shared icon picker | Complete | `ba43145 feat: expand icon settings with searchable heroicon picker` | Adds `PublicFrontIconRegistry`, a shared lazy searchable `IconSelect`, enum-name icon token normalization, permanent legacy alias compatibility, settings migration for stored aliases, and focused V1b tests. Step 10R-V1c is complete. |
| Public Front v2 Step 10R-V1c custom colors and theme-safe podcast palette | Complete | `a846341 feat: add custom colors and theme safe podcast palette` | Adds strict custom hex settings beside finite color tokens, conditional admin ColorPickers, CSS-variable-only public rendering, D9 decision note, cached light/dark podcast cover palette variants, and focused V1c tests. Step 10R-P1 is complete. |
| Public Front v2 Step 10R-P1 validated public-front config cache | Complete | `e17cefd perf: cache validated public front config` | Adds `PublicFrontConfigCache`, a versioned `public_front.config.v1` key with settings-migration watermark, save invalidation through `SettingsSaved`, corrupted-cache fallback, and shared V1c palette cache key naming. Step 10R-S2 is complete. |
| Public Front v2 Step 10R-S2 settings backup versions and restore | Complete | `f694c49 feat: add settings backup versions and restore` | Adds the `settings_backup_versions` table, `settings_backups` JSON group, verbatim `PublicSettingsPackage`, automatic/manual backups, hash dedupe, retention pruning, JSON download, compare, before-restore backup creation, transactional restore through `PublicContentSettings::save()`, and focused tests. S2V and S1a are now complete; S1b is next. |
| Public Front v2 Step 10R-S2V backup visual snapshots | Complete | `86d21cb feat: add backup visual snapshots` | Adds the `settings_backup_snapshots` table, finite public-screen manifest, queued Playwright snapshot job, private snapshot streaming/retry/zip routes, backups table thumbnail/gallery UI, runtime Playwright dependency, system-only retention prune correction, snapshot file cleanup on explicit delete and prune, and focused tests. Step 10R-S1a is complete. |
| Public Front v2 Step 10R-S1a settings export/import wizard core | Complete | `30e413c feat: add settings export and import wizard` | Adds schema-agnostic lifecycle units, public settings/backups JSON export actions, a hidden admin import page, upload-or-backup source validation, dry-run selection/filter/search UI, replace-mode selected-unit apply with before-import backup, cache invalidation, and S2V audit corrections for after-commit jobs, timeout ordering, desktop fractional thumbnails, and after-commit prune file deletion. Step 10R-S1b is complete. |
| Public Front v2 Step 10R-S1b import locks and add-only mode | Complete | `ada29fb feat: add settings import locks and add-only mode` | Adds persistent import locks, the hidden import-locks manager, schema-owned virtual route-label/card-template units, front-text lock preset, server-side lock enforcement, add-only current-wins merge behavior, accurate applied-path summaries, upload MIME validation, dot-segment schema guard, and focused tests. Importer Workbench gate is open. |
| Public Front v2 Step 10R-HF2 MySQL snapshot index migration hotfix | Complete | `f719d30 fix: bound snapshot index column lengths for mysql` | Edits the existing snapshot migration in place to drop orphan pre-fix tables before create and bound indexed finite-token strings so MySQL/InnoDB utf8mb4 key math fits. Adds duplicate target-row regression coverage and records the durable MySQL index-length lesson. |
| Public Front v2 Step 10R-UX3 Hebrew smart slugs and key contract alignment | Complete | `0f3aed6 feat: add hebrew smart slugs and key contract alignment` | Adds Hebrew-aware shared slugging, optional form-side smart slug UX with regenerate action, Spatie tag slug generation/repair, import/form key-length alignment, and MySQL-safe demo seeder reference keys. S1c and Importer Workbench were not part of this run. |
| Public Front v2 Step 10R-S1c inline import locks on settings page | Complete | `389cb0f feat: add inline import locks on settings page` | Adds inline import-lock section and field actions to Public Content Settings, records D29 import-only semantics, links the header manager action, keeps locks out of normal form save blocking, prevents locks-only snapshot scheduling, fixes S1b audit items, and adds the canary test bootstrap for SQLite `:memory:` test safety. Importer Workbench remains not started. |
| Public Front v2 Step 10R-MP1 maintenance mode page | Complete | `8458a5d feat: add maintenance mode page and settings` | Adds settings-controlled public maintenance/coming-soon mode only: a Public Content Settings maintenance group, 503 + `Retry-After` public middleware, admin bypass, trusted raw maintenance HTML, import/export coverage, and cache-toggle invalidation. Importer Workbench was not started. |
| Public Front v2 Step 10R-S1d import result report and MP1 hardening | Complete | `5b3593c feat: add import result report and maintenance hardening` | Adds structured import reports anchored on before-import backups, dry-run/completion/report UI visibility, sensitive maintenance import semantics, MP1 middleware/save hardening, Horizon gate alignment, panel audit notes, and test-performance findings. Importer Workbench was not started. |
| Importer Workbench WB1 connections foundation | Complete | `1148867 feat: add importer connections foundation` | Opens the WB track with encrypted `ImportConnection` records, Google Drive service-account/OAuth connector boundaries, Spotify client-credentials metadata connector, custom Importer Settings page, OAuth routes, `importer:probe-formats`, research/plan/findings/handoff docs, and WB ledger rows. NAV1 later moves Importer Settings out of the old ייבוא nav group into Site management; future importer tools get their own placement in TOOLS1. Next WB step is WB2. |
| Step 10R-HF3 imports-exports queue and export row loading hotfix | Complete | `7d80c99` plus `8d24ce8 fix: complete imports-exports hotfix across exporters with shared lifecycle tracing`; coda commit `07df116 fix: localize import export completion notifications` | Adopted the manual queue fix retroactively and completed the bug class. The `imports-exports` queue was absent from Horizon supervisor config and waits, so Filament imports/exports sat unconsumed in Redis. Export row processing then lazy-loaded relation-derived exporter columns under non-production `Model::preventLazyLoading()` and failed all rows locally. The tracer and `import_export` daily channel are now in place; all five exporters share queue/batch/tag/notification lifecycle behavior; ContentGroup, ContentItem, Category, and Transcription exporters now eager-load every relation their export columns read; AuthorExporter has no relation-derived columns. HF3 coda localized import/export completion notifications; its full coda gate passed with 391 tests and 3633 assertions. |
| IMG-R images/media research and plan | Complete | `docs/research/images-media/00-images-media-research.md`; `docs/phase-02/images-media-track-plan.md` | Docs-only research is complete; IMG-A and IMG-B implemented the approved IMG-1/IMG-2 pieces, IE-1 is implemented separately, and zip import packages remain future-gated until Yoni explicitly selects them. |
| IMG-A image naming foundation and Curator media library | Complete | `988676e feat: add image naming foundation and curator media library` | Implemented the one-run IMG-1 + IMG-2 merge: slug-default `AdminUxSettings::media_naming_strategy`, Curator v5 admin media library, cover alt text, app-owned Curator/FileUpload picker factory that stores path strings, app-owned cover cleanup, and in-place legacy cover/settings asset registration. |
| Step EP1 - Episode workspace | Complete | `e70d6f3 feat: add episode workspace with single transcription lens` | Implements the episode workspace with a single-transcription lens, `title_prefix`, trusted `embed_html` media-component raw mode, Admin UX workspace settings, workspace create/edit pages, replace transcription action, Spotify lookup/fetch helper, focused EP1/public boundary tests, and passing final gate. |
| IMG-B episode images, TB1 actions, media guards, images export | Complete | `8c590ab58f1b4b4b89ec85b7c0541d95a41cde90 feat: add episode images, media guards, and content images export`; `docs/phase-02/images-arc-imgb-handoff.md` | Implements D-IMG-A v2 egress naming, D-IMG-E library retention cleanup semantics, referenced-media delete blocking, `content_items.image_path`, workspace/table image actions, queued external thumbnail downloads, effective-image table thumbnails, and queued content-images ZIP export. Final green gate: `php artisan test` 421 tests / 3,805 assertions, FilaCheck 0 issues, and `npm run build` passed. |
| IE-1 - Relation import modes and tag export scope | Complete | `6f1cea7 feat: add relation import modes and tag export scope`; `docs/phase-02/import-relations-ie1-handoff.md` | Adds shared native Filament importer `relation_mode` options for category, content tag, and transcription transcriber relations; keeps `replace` as default; adds `add_only`; makes blank relation cells preserve existing links in both modes; skips disabled content tags with a completion warning; and adds enabled-only/all-tags content-item tag export scope. No Composer changes. |
| NAV1 - Admin navigation restructure and suite timing diagnosis | Complete | `e59705b feat: restructure admin navigation groups and defer badges`; `docs/phase-02/admin-navigation-nav1-handoff.md` | Adds central navigation groups/order, ungroups the episode workspace create item, public-form submissions, and Media, defers the new public-form submission badge until badge evaluation, labels workspace actions as default and classic actions as system actions, and records final `php artisan test --profile` timing findings in the handoff. |
| NAV1-F - Sidebar badge fold | Complete | `app/Support/NavigationBadgeCount.php`; `app/Enums/NavigationBadge.php` | Folds all three sidebar count badges (episodes, media, form submissions) onto one home: `NavigationBadge` names them, `NavigationBadgeCount` owns key, TTL, formatting and the void `forget()`; the model's private key constant is retired. All three now use `Cache::flexible([60, 600])`. Two findings drove it: caching the *formatted* value cached `null` at zero, and a cached null is indistinguishable from a miss, so an empty queue re-ran its COUNT on every render — the helper now caches the integer; and the helper had to move out of `app/Filament/Support` because a model may not import the Filament layer. FilaCheck Pro's `navigation-badge-not-cached` is a syntactic walk of the method body and follows no indirection, so each `Cache::` call stays at its call site — pinned by a test. Deliberate non-uniformity kept and documented: episodes/media are inventory counters that may lag their TTL; submissions is a work queue whose model forgets the key on every write. Deferral was **not** generalised — the public-panel cost that would justify it does not reproduce (measured: zero badge queries on a public render). |
| MP2 - Forms page and maintenance form embedding | Complete | `465967f feat: add forms management page and maintenance form embedding`; `docs/phase-02/maintenance-form-mp2-handoff.md` | Adds a dedicated public forms settings page while storage remains `PublicContentSettings::$public_forms`, removes forms editing from the big public settings page, adds generic settings item cloning for forms, embeds an enabled public form into maintenance responses through a guarded plain POST route, and keeps maintenance/public form settings in lifecycle export/import coverage. The historical MP2 gate-output gap is closed as documented: exact suite counts were not recoverable and must not be invented. |
| TS1 - Test suite performance | Complete | `docs/research/test-suite/00-ts1-implementation-plan.md`; `docs/phase-02/test-suite-performance-ts1-handoff.md` | Restores Dashboard to the first admin sidebar position, suppresses backup snapshot job execution only in non-backup settings-page tests, keeps backup/snapshot assertions intact, records before/after suite timing, and defers validator-normalization migration plus parallel enablement to future evidence. Final gate passed: `php artisan test --profile` 431 tests, 3,891 assertions, 472.585s; profile recovery passed at 481.962s. |
| SP1 - Settings performance instrumentation | Complete | `c6c9587 perf: instrument settings page and fix maintenance marker copy`; `docs/phase-02/settings-performance-sp1-handoff.md` | Adds env-gated measurement instrumentation for the Public Content Settings page without structural performance fixes, records phase/payload timings for load/save/live-update/settings-saved paths, fixes the MP2 maintenance marker copy state bug, and captures ranked follow-up recommendations for Yoni review. No Composer changes. |
| SP2 - Settings split and scoped validation (TS2) | Complete | `fb3f515 perf: optimize public settings lock hints`; `docs/phase-02/settings-performance-sp2-handoff.md` | Attribution gate stopped the page split. The shipped change memoizes inline import-lock hint unit-path lookup, keeping the monolith at about 71-83 ms `form.total_build` on the 37 KB local payload; adds `PublicFrontConfigValidator::validateGroups()`; and adds `settings:normalize-public-content` dry-run/backup-first apply coverage. Domain pages, TS2 test relocation, and the card-template clone rider were not executed because the split no longer had supporting numbers. |
| FETCH1 - Spotify fetcher reduced-mode upgrade | Complete | `524a292 feat: enrich spotify fetcher reduced mode with opengraph and previews`; `docs/phase-02/spotify-fetcher-fetch1-handoff.md` | Enriches credential-less Spotify fetches with cached/throttled public oEmbed plus static OpenGraph/LD-JSON metadata, fixes the render-side missing thumbnail preview, keeps imports URL-only with no media downloads, normalizes descriptions to Markdown for table/CSV/workspace surfaces, and styles the MP2 raw-maintenance missing-marker fallback. No Composer changes. |
| FIX1 - Fetcher direct import and workspace publishing fixes | Complete | `700de7f feat: add fetcher direct import and workspace publishing fixes`; `docs/phase-02/fetcher-workspace-fix1-handoff.md` | Makes fetcher CSVs importable by strict native importers with stable keys, adds direct import create/link/skip flow, improves workspace Spotify fetch options, applies publication-date autofill, upgrades trusted raw HTML editors/rendering, adds settings cache rider coverage, and enforces HTTP fixture discipline. No Composer/npm changes. |
| MAIL1 - Mail foundation and email OTP form verification | Complete | `330350d feat: add mail foundation and email otp form verification`; `docs/phase-02/forms-mail-mail1-handoff.md` | Adds Resend-first mail foundation, queued email OTP verification for public forms, maintenance-form OTP enforcement, form hardening, import failure cause summaries, admin public-site nav link, and tiered workspace Spotify recognition. Final gate passed: `vendor/bin/pint --test`, `vendor/bin/filacheck` 0 issues, `npm run build`, and `PAO_DISABLE=1 php artisan test` 478 tests / 4,228 assertions in 360.18s. Composer addition is limited to `resend/resend-php`; no npm changes. |
| ROLES1 - User roles and multi-transcription gates | Complete | `9cd7349 feat: add user roles and multi-transcription visibility gates`; `docs/phase-02/roles-gates-roles1-handoff.md` | Adds the fixed role enum/user column/assign-role command, admin+ panel access, super-admin-only Users resource, centralized `multi-transcription`/`super-admin` gates and macros, default single-transcription mode, gated settings/admin multi affordances, and save guards that preserve stored hidden state against forged settings payloads. Final gate passed after fixing Pint formatting and one FilaCheck role-filter issue: `vendor/bin/pint --test`, `vendor/bin/filacheck` 0 issues, `npm run build`, and `php artisan test` 490 tests / 4,336 assertions in 382.935s. No Composer/npm changes. |
| LENS1 - Single-mode ontology and vocabulary sweep | Complete | `2299c71 feat: apply single transcription ontology across public and admin`; `docs/phase-02/single-lens-lens1-handoff.md` | Adds a mode-aware label resolver, distinct-effective-episode public counting, runtime episode-count suppression, public viewer policy clamping, shared second-row creation validation with a narrow workspace-replacement exemption, and one-current-row standalone resource scoping plus super-admin history. The episode relation manager and item/group admin operational columns remain unchanged by operator direction. No Composer/npm changes. |
| SP3A - Settings measurement, lifecycle memoization, lock surface | Complete | `88fdda2 perf: add settings measurement protocol, lock surface, and import overlay`; `docs/phase-02/settings-sp3a-handoff.md` | Adds the reproducible measurement yardstick, payload-aware scoped lifecycle memoization, visible lock-surface registry, import-path authorization overlays, and bounded-versus-growing Select policy. Lifecycle units remain byte-identical and no dependency changes were made. |
| MAIL2 - Inline email verification UX | Complete | `docs/phase-02/forms-otp-ux-mail2-handoff.md` | Places the send and verify suffix actions inside the configured email field group, adds resend countdown and verified/error states, resets verification and rotates the guest challenge when the email changes, preserves the maintenance signed two-step flow, and adds server-side changed-address refusal coverage. No Composer/npm changes. |
| OTP-POLICY1 - OTP policy config and expiry copy | Complete (`0394ab5`) | `docs/phase-02/forms-otp-policy-config-handoff.md` | Moves expiry, attempt ceiling, and resend cooldown to env-backed config; changes the default expiry from 10 to 5 minutes; pluralizes mail copy; adds config-fed expiry hints; and places adjacent actions at logical inline-end on both verification surfaces. No dependency or OTP-mechanics changes. |
| SP3B - Settings subject pages and fresh owned-path saves | Complete | `docs/phase-02/settings-sp3b-handoff.md` | Replaces the public-settings monolith UI with eight focused owner pages, temporary Card Templates, and upgraded Manage Public Forms; uses a complete ownership registry plus fresh canonical snapshot, owner-only validation/overlay, and one existing lifecycle save to preserve sequential stale disjoint-page changes. Legacy URLs redirect safely; lifecycle memoization and operational writers remain unchanged. The SP3A fixture is retired. Browser samples were unavailable in that historical run, and settings metrics Mini-task 2 retired the later follow-up route. No Composer/npm changes. |
| SP3C - Template library and one-template editor | Complete | `docs/phase-02/settings-sp3c-handoff.md` | Replaces the temporary whole-list editor with a read-only custom-data library and hidden create/edit pages. The selected Builder-preview mechanism mounts controls only for the chosen top-level/nested part; one focused fresh-snapshot writer guards create/edit/allowed rename/clone/delete with sequential fingerprints, references, defaults, current capability, and preservation boundaries. No migration, dependency, lock, import, or lifecycle rewrite; no simultaneous-request serialization or literal database-payload-byte claim. |
| AUTHZ1 - Dormant package foundation and legacy migration utility | Complete / done for now; command closure implemented | `docs/phase-02/authz-command-closure-handoff.md`; reset master plan; plan 20; historical reports 12–18 | The three `authz:roles:*` commands are withheld and the retained migration namespace is application-isolated. Legacy enum/rank/Gates remain authoritative. Shield stays unregistered with no `HasRoles`, grants, cutover, or role UI. AUTHZ1-D–I and recursive remediation are stopped. |
| MAINT-LW-UX1 - Stale-tab maintenance UX and regressions | Named deferred; not implemented or prompted | `docs/research/settings-performance/14-maintenance-livewire-enforcement-effects-audit.md` | Independent medium UX/test follow-up due before the first later public Livewire navigation/polling/lazy/deferred/stream/upload expansion. |
| ARCH1 - Versioned Card Template and Public Form aggregates | Deferred / not current | Historical reports 07, 09, and 11; reset master plan controls | No present feature requires the migration. Current SP3C/Step 6 storage and writers remain authoritative. ARCH1 does not depend on completing AUTHZ1 and is not a prerequisite for Card Template preview/side-panel UX. |
| Step 5B - Card Template Admin Preview UX | Complete through FU03 path-corrected validation | Preview `c75d0f2b`; restricted `69813dbd`; auto-refresh `cdf0e897`; editor UX `d889d4f6`; UX2 `82e639d0`; FU01 `2d028255`; O1 `215340d3`; O2 `f56ef369` + stamp `27f38aea`; FU02 `a8be0aa4` + stamp `23c3ac9`; FU03 `659762f9` + hash-stamp/corrective closeouts; research/plans 21–40 and dedicated handoffs | Preserves the O1 1024px single-root shell/focus contract, position-canonical editing, O2 actual-part geometry, FU02 effective-image ranking, restricted/query boundaries, and global order compatibility. FU03 preserves structured validator issues and maps positional root/top/nested paths to current UUID-owned inline or native slide-over Builder state, with transient reveal, exact focus, verified fallback only after a recognized root/parts owner, notification-only rejection for invalid singleton prefixes, one-request routing, and no persistence or key/slug/value-based mis-target. Final serial gate: 809 tests / 11,807 assertions. FU04 order-compatibility closure is next and unapproved; FU05–FU06 remain later and unapproved. Nested images, production normalization, persistence, migrations, dependencies, lifecycle, permissions, and generalized renderer work remain deferred. |
| WB-PROBE-HF1 and Google format probe | Conditional Later checkpoint; not implemented or run | Report 07 and recovered checkpoint queue | If Workbench resumes, first harden connection selection, refresh, output privacy, and partial-failure resume behavior; then run the private 20-document probe before WB2/WB4 or paste-cleanup planning. |
| LENS1 review packs | Pending operator review; not implementation scope | Report 07 and recovered checkpoint queue | Review roughly 25–40 rows per page/domain pack with key, HE, EN, context, and decision. Do not restore wholesale approval of the old 269-row table. |
| SP3 browser acceptance evidence | Retired by settings metrics Mini-task 2 | `docs/phase-02/settings-development-metrics-retirement-mini2-handoff.md` | Historical evidence remains provenance only. Do not reactivate or generalize the bespoke helper; future observability starts with a fresh audit defining a concrete consumer and measurement plane. |
| SIMPLIFY-REVIEW1 - Project simplification opportunities audit | Optional Later; suggestions only; not started | Recovered checkpoint queue | Read-only inventory of possible deletion, consolidation, and reuse. It does not block AUTHZ closure or Step 5B, and no finding authorizes implementation without separate review, estimate, and approval. |
| CURATOR-HF1 - Curator picker hydration repair | Complete | `23a6ce9 fix: preserve curator picker selections on reload`; `docs/phase-02/curator-picker-hydration-hf1-handoff.md` | Repairs `PathCuratorPicker` raw-state hydration and UUID-keyed state handling so registered Menu/Header logos and Display default images remain visibly selected after reload. The storage contract, legacy-path preservation, and SP3B lifecycle remain unchanged. |
| CURATOR-G1 - Full app-owned Curator image library | Complete locally; historical compatibility baseline | Canonical implementation hash is recorded in `docs/phase-02/curator-g1-image-library-o2-handoff.md` and the mini-step ledger | Completes all seven O2 minis: positive validation, app Resource/picker, explicit abilities, immutable journal-backed keys, shared typed attachments, compatibility/settings/import-export cutover, rowless legacy registration, fenced mutations/repair, SSRF controls, query budgets, translations, and runbooks. The local incident later applied the three relational migrations and the settings-shape migration in batch 8; production did not. G1 remains compatibility evidence, while the approved MediaAsset program controls forward architecture. |
| CURATOR-G1 LMTC - Legacy media transition correction | Complete locally; production cutover gated | `f73be008b3dbc49e09f01b645327b4083d8f70f8`; `docs/phase-02/curator-g1-legacy-media-transition-correction-handoff.md` | Closes the existing noncanonical null-key row gap with exact-digest same-ID raster/SVG transition, rowless fixed-root import, ordered backfills/integrity, production-shaped closure tests, and safe owner repair/default fallback. No schema/dependency change or real environment mutation; IDs 6/7, production cutover, root-level dispositions, recovery gallery, dependency upgrades, and empty permission-schema cleanup remain separately gated. |
| PODTEXT MEDIA ASSET PROGRAM - inventory-first Package 1 | Complete locally | `ca483c0c0072e0791fe6c26755aadae341ece0a5`; audit `LS-20260723-MEDIA-INVENTORY-FIRST-RESET-01`; option `MEDIA-INV-O1-RESET-CLEANUP-P1-MINIMAL-KERNEL`; `docs/phase-02/media-program-p1-kernel-conversion-handoff.md` | Adds the minimal portable asset/binding kernel, nullable attachment bridge retaining `media_id`, report-only/explicit-apply idempotent database conversion, exact owner/settings reconciliation and 15-null/15-valid/403-row proof. Final Pint/FilaCheck/Vite gates passed and the full suite passed outside the known macOS browser sandbox: 1,005 tests / 13,316 assertions. Trust/status/proof machinery remains rejected; Package 2 requires a fresh audit. No dependency or real environment action occurred. |
| PODTEXT MEDIA ASSET PROGRAM - inventory-first Package 2 | Complete locally | `2a6de67816b9a7c8e53bcd29795a5b306a36dbaf`; audit `LS-20260723-PODTEXT-MEDIA-P2-INVENTORY-PICKER-REPLACE-01`; option `MEDIA-P2-O1-REUSE-PICKER-SAME-PAGE-REPLACE`; `docs/phase-02/media-program-p2-inventory-picker-replace-handoff.md` | Makes every Curator row visible in All Media, adds visible Needs Repair diagnostics, makes `media_id` authoritative, enforces D01 delivery/SVG boundaries, clears the picker context through All Media, and reuses Gallery/Upload for always-visible same-page podcast/episode Add/Replace Image actions with current-image display and cancel no-op. Existing selection is attachment-only. Packages 3-5, dependencies and real environment actions remain gated. |
| PODTEXT MEDIA ASSET PROGRAM - Package 3 acquisition picker | Complete locally | `656a7c2ed1b64b3f6fd8392bff88f7cca36d2695`; audit `LS-20260723-PODTEXT-MEDIA-P3-ACQUISITION-PICKER-01`; option `MEDIA-P3-O1-IMMEDIATE-SHARED-ADMISSION`; `docs/phase-02/media-program-p3-acquisition-picker-handoff.md` | Adds one shared immediate Upload/URL/Storage admission path, atomic Curator/asset/provider binding creation, preserved raster bytes, all-purpose sanitized SVG, original-filename/naming settings, opaque bounded Storage candidates, four-source bilingual picker and Spotify URL convergence. Focused matrix passed 218 tests / 1,631 assertions; final serial suite passed outside the macOS browser sandbox: 1,037 / 13,633. No dependency, relational schema, live data, Package 4 or Package 5 action. |
| PODTEXT MEDIA ASSET PROGRAM - post-Package-3 acquisition picker UX | Complete locally | Audit `LS-20260723-PODTEXT-MEDIA-P3-POST-ACQUISITION-UX-01`; option `MEDIA-P3-POST-O3-IMMEDIATE-SOURCE-WORKSPACE`; `docs/research/media-program/packages/03-post-acquisition-picker-ux-plan.md`; `docs/phase-02/media-program-p3-post-acquisition-picker-ux-handoff.md` | Completes all five sequential mini-tasks: active-doc closure; single/batch/Storage correctness; total URL deadline and safe categories; immediate source workspace with direct single choice and busy/offline truth; accessibility, nested/offline lifecycle browser proof and canonical closeout. Focused proof reached 86 tests / 1,112 assertions and the complete picker browser file reached 7 / 139. The first complete ordered gate passed Pint, FilaCheck, Vite and the full 1,080 / 14,165 suite outside the macOS browser sandbox. Immediate permanence and pending owner attachment remain fixed. No Package 4/5, migration, live-data, production or push action. The operator explicitly amended only the dependency exclusion for Filament 5.7.1 to 5.7.3; no manifest, npm or unrelated dependency changed. |
| PODTEXT MEDIA ASSET PROGRAM - Package 4 owner image UX | Complete locally | `52875222916558542cfde19f8a1987b78e72c121`; audit `LS-20260724-PODTEXT-MEDIA-P4-POSTP3-OWNER-UX-01`; option `MEDIA-P4-POSTP3-O1-INTEGRATED-IMAGE-WORKSPACE`; `docs/phase-02/media-program-p4-owner-image-ux-handoff.md` | One lazy effective-source/metadata presenter; stale-safe normal replace/remove; one integrated action across six owner surfaces; bounded table preview/detail; safe copy/download/review; broken-association repair/default UX; the independently estimated 43-action Resource-table rider; HE/EN, RTL/narrow/browser and performance proof. Complete ordered gate passed Pint, FilaCheck, Vite and 1,127 tests / 14,725 assertions outside the macOS browser sandbox. No schema, dependency, file lifecycle, live-data, production or push action. |
| PODTEXT MEDIA ASSET PROGRAM - Package 4 inline owner picker correction | Complete locally | `f905de83e996d34c65b767deb7ce121283f0786a`; audit `LS-20260724-PODTEXT-MEDIA-OWNER-PICKER-CORRECTIONS-01`; option `MEDIA-OWNER-CORR-O3-INLINE-PICKER-TABS`; `docs/phase-02/media-program-p4-inline-picker-correction-handoff.md` | Replaces the extra picker modal with one schema-owned inline Gallery/Upload/URL/Storage workspace in the first/default Replace Image tab and keeps Details/Effective Image second. Adds bounded acquisition-only owner multi-upload, explicit standalone Media batch upload, and regression proof for Storage, cancellation, busy/offline, focus, touch, RTL/LTR and narrow screens. Focused proof: 105 / 1,517; combined browser proof: 8 / 212; full suite: 1,131 / 14,797. The separately approved local database activation settled 15/15 Media rows with zero diagnostics. No new migration/dependency, Package 5, broader Storage discovery, media-file mutation, production or push action. |
| PODTEXT MEDIA ASSET PROGRAM - post-P3/Package 4 visual UX correction | Complete locally | `50b25d660099727e42791a1f9c6bbf0db6ec47a7`; audit `LS-20260724-PODTEXT-MEDIA-P3-POSTP3-P4-VISUAL-UX-01`; option `MEDIA-P3-POSTP3-P4-VUX-O2-NATIVE-CARD-GALLERY`; `docs/phase-02/media-program-p3-postp3-p4-visual-ux-handoff.md` | Native Media Resource card gallery; seven-extra-large non-autofocusing owner workspace; one compact contain-fit selection; source-first narrow picker; server-backed 1500-millisecond Storage search with bounded recursive Laravel-public and `public/images` discovery; fresh file evidence and reference-query/existence-probe budgets; capture-only static-topbar visual harness. Focused proof: 117 / 1,454; browser proof: 10 / 266; full suite: 1,144 / 14,943. No schema/dependency, Package 5, arbitrary public/build scan, live/prod action or push. Broader Media workflow redesign requires a fresh audit. |
| PODTEXT MEDIA OPERATIONS UX3 - Mini-task 1 Media Library card hierarchy | Complete locally | `0e42ea47d2813141fa8583fc36532c3a85250c33`; audit `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX-03`; option `MEDIA-OPS-UX3-O2-PDF-CONTRACT-TARGETED-WORKSPACES`; `docs/phase-02/media-operations-ux3-mini1-library-card-hierarchy-handoff.md` | Implements only the corrected contract's Media Library card/action hierarchy: stable identity, bounded truthful known references, persistent primary issue plus additional count, quiet technical facts, explicit details destination and one accessible More actions group. Preserves complete inventory, Needs Repair, query/probe budgets and every Package 1–4 safety authority. Mini-task 2, complete Care/fix/results workspaces, Package 5, schema/dependencies/data/production/push remain gated. |
| DEP-UPGRADE-O1 - Bounded full dependency refresh | Complete locally | `4d2a063954975fa07dde43f6113a97c671fc5724`; `docs/phase-02/dependency-refresh-handoff.md`; `docs/research/dependency-refresh/` | Refreshes Composer and npm lock graphs within unchanged manifest constraints; moves Laravel to 13.21.1, Filament and its Spatie plugins to 5.7.1, Horizon to 5.48.1, Pest to 4.7.5, Boost to 2.4.13, Tailwind to 4.3.3, Vite to 8.1.5, and Laravel Vite plugin to 3.1.3. Preserves Livewire 4.3.3 and Curator 5.1.2, corrects one Filament action API call and one tooltip-first Escape browser expectation, and records 18 byte-attributable tracked Filament asset changes. No manifest, schema, migration, production, worker, or local-development database change. |
| DEP-REFRESH-WEBHOOK-O1 - Resend wrapper, safe delivery logging, and Fontaine | Complete locally | `c9d064aa9b3921b5b99f83859f305b2fc8332cc9`; audit `LS-20260724-PODTEXT-DEPENDENCY-RESEND-WEBHOOK-02`; option `DEP-REFRESH-WEBHOOK-O1-BUILTIN-SAFE-LOGGING`; `docs/phase-02/dependency-resend-webhook-fontaine-handoff.md` | Replaces the direct SDK with the official wrapper while retaining the SDK transitively; completes bounded lock refreshes, Fontaine 0.8.0 installation and Boost discovery; keeps one built-in webhook controller behind missing-secret and signature guards; synchronously logs an exact four-field allowlist for seven delivery events. Focused proof: 27 tests / 88 assertions. Canonical Pint/FilaCheck/Vite gates and the full 1,107-test / 14,253-assertion suite passed. No migration, persistence/admin page, raw payload, engagement/inbound/contact/domain handling, live call, production, Package 4/5 or push action. |
| SETTINGS-METRICS-O1 Mini-task 1 - Runtime retirement | Complete | `3e5c411777994c431417dfd823576286d12d5c29`; `docs/phase-02/settings-development-metrics-retirement-mini1-handoff.md`; research/plans 41-42 | Removed the temporary settings profiler, middleware, runtime fixtures, flags/headers, counters, config/logging, and browser harness. Functional settings/Card Template/Curator behavior and Filament 5.7.1 remain preserved; Mini-task 2 completes the separately audited test-only retirement. |
| SETTINGS-METRICS-M2-O1 Mini-task 2 - Test-only retirement | Complete | `c37bcf7f5cd7ea0408623218edeee4842d8e6592`; `docs/phase-02/settings-development-metrics-retirement-mini2-handoff.md`; research/plans 43-44 | Deletes only the eight-file synthetic canary graph; removes five switches/eight report blocks and their exclusive metrics; removes the bilingual canary-only subtree; migrates six consumed hooks to durable Card Template names; removes four unused markers; supersedes active routing while retaining historical provenance. Preserves all functional, security, exact-query, browser-interaction/deadline, Filament 5.7.1, and Curator G1/LMTC invariants. No dependency/schema/data/environment action; no later mini-task selected. |
| Prompt 13 dashboard metrics | **COMPLETE — all 4 phases + 2R** (phase 4 · Evidence landed 2026-08-05) | Phase 1 `7bba038` (release 74516766); phase 2 `894870e`; remediation through `86f7e85`; E4/E5 `96af988`/`9859145`; M2 fix `0b99bf8`; verify round `0e80c84`, `bf0c063`; F block `b24490a` (F1+F2 localization home + guard + fixture), `b3d6de4` (F3 breakdown roll-up); A block `2831ee9`/`b9825c6`/`103b728`/`c36f6c4`; B1 `168a618` (hover layer) + `87aa8bf` (browser evidence). **Stack pushed 2026-08-03 on operator instruction** (was 23 commits past `7bba038`). Phase 3 (2026-08-04, implemented from the twice-reconciled plan): `e2010b8` StreamEventType, `9b494a4` intake metrics + observer (incl. the dispatcher-halt fix), `f0ac4df` ImportPolicy::view, `55a30b6` IntakeQueueWidget, `d353482` SpotifyConnectionWidget, `1e64467` MediaFindingsWidget, `90b7fb9` Intake lens + command bar, `0e80cc0` E4 pair + storage_created, `6e6a03a` imports-provider-stamp (Q1), `c399eaa` imports-listing-minimal (Q4), `bdc0b78` gate fold. Full gate 2026-08-04: pest 1651/1651 (19,931 assertions), pint --test pass, full filacheck 0, build ✓ — pushed and deployed the same day (`dabb70d`, REVISION verified, /up 200, imports migration ran). Phase 4 (Evidence, 2026-08-05, implemented from `dashboard-metrics-phase-4-plan.md`): `55e5a22` decision-10 pairs 1–2, `3d5af8a` pairs 3–4, `9426dc6` intake reconciliation + range-switch/legend behavior pins, `a62fe38` the on-demand `rtl-board` browser group (decision 6/9). Phase-4 gate: pest 1658/1658 (19,971 assertions, rtl-board excluded by design), pint --test pass, full filacheck 0, rtl-board soaked ×4 green alone (10 assertions/run), build not needed (test-only). Authoritative brief: `docs/phase-02/dashboard-metrics-phase-2R-handoff.md`; cause-pattern ledger + route checklist: `docs/research/defect-cause-patterns.md` | Phase 2 shipped Board 1 (living funnel, composite cards, heatmap, typed stream, composition band with podcast health and the transcriber board), the podcast scope filter, legend-as-filter, H7's burn-down and stock/flow tags. Phase 2R then remediated what phases 1-2 got wrong: blocked split into two tiers with the previously untracked `unpublished_group` reason, the visible series bucketed on the day an episode actually became visible, filawidgets adopted then removed in favour of five in-house value objects, `FunnelStage`/`DashboardReason`/`DashboardTier` enums ending eight duplication sites and four colour drifts, `canView()` on every widget, cache invalidation on editorial writes, and a loading skeleton. The UI timezone was consolidated out of 50 files behind `UiTimezone::name()` with an anti-drift test. The 2026-08-03 orchestrated route then verified the stack (V1–V4: stock/flow tag gaps closed with a structural lens-loop test, `PublicFormTargetStatus` scoped per request, the owner-image browser flake proven cured by the M2 fix over a ×10 soak, key-shift assertions confirmed intact, raw-payload settings writers pinned by invariant test) and delegated F1–F3 to a side-session, which landed them the same day: `UiFormats` beside `UiTimezone` as the localization home (day-first date formats, locale-routed numbers through `Illuminate\Support\Number`), a statement-scanned anti-drift guard pinning the 7 date + 5 number = 12 dashboard format sites at zero, all 12 routed through the home, a near-midnight Jerusalem-day fixture closing the bucketing oracle gap, and breakdown tails rolled into a reconciling "Other" row in `EditorialMetrics` (full gate: pest 1,571/19,430, pint, filacheck 0, build ok). The A block landed 2026-08-03 in a second side-session: A1 `2831ee9` (sparklines normalise over max−min in an inset band, midline for spanless series, exact-coordinate tests), A2 `b9825c6` (`SparklineTrend` enum owns the up/down/neutral stroke/delta palette via `SeriesRow::trend()`/`BreakdownRow::trend()`, a source-scan test bans hand-written trend literals, and the `app/Enums` `@source` gap that left enum-only colour classes uncompiled — colourless draft-funnel and reason bars — is fixed and pinned), A3 `103b728` (shared dashed empty-state partial across stream/composition/gap, `x-filament::link` + `Heroicon` enum doorways for chips, stat cards, funnel labels and heatmap clear), A4 `c36f6c4` (P11 closure: reason bars now really open the blockers queue filtered to their reason, via `dashboard-reason-selected` into the queue's own filter — widget table filters are never URL-hydrated, so the honest doorway is on-board dispatch, and tests pin rows, dispatch and narrowing). B1 landed 2026-08-03 in a third side-session: `168a618` gives the funnel sparklines an Alpine-local hover layer — a crosshair that snaps to the nearest point inside the existing svg, a tooltip whose day-first labels and grouped values are rendered server-side through `UiFormats` into per-point data attributes (`SeriesRow` now carries the range's Jerusalem `days` aligned with `points`), keyboard access (focusable layer, arrows walk the LTR axis, Home/End, Escape dismisses, visible focus ring) and screen-reader naming (role="img" + aria-label on the focusable element, an aria-live region outside that subtree announcing each day–value); `87aa8bf` proves hover, arrow walk, live announcements and Escape in a real browser on the RTL board, soaked ×5. Phase 3 landed 2026-08-04 (implemented task-by-task from `dashboard-metrics-phase-3-plan.md` after its fresh re-reconciliation and the operator's same-day rulings): the Intake lens got its own five-widget board (context + self-hiding form-target warnings + work queue + Spotify connection echo + media findings bars), `StreamEventType` now owns the stream/queue vocabulary with every call site routed and a palette-pinning guard, the intake metrics ride their own observer-invalidated snapshot (finding en route: a model-event closure returning `Cache::forget()`'s bool was HALTING all later saved-listeners — fixed in `PublicFormSubmission`), any admin may read failure CSVs via `ImportPolicy`, the E4 pair carries label contracts with the missing `storage_created` key added in both locales, the `imports` table gained the process-stamped `provider` (+ optional `name`, + WB-reserved `import_connection_id`) per the Q1 override — the modal never offers a source select; `StampImportSource` stamps manual via listener auto-discovery — and a minimal read-only imports listing (System cluster) gives the queue its second doorway. Phase 4 (Evidence) closed Prompt 13 on 2026-08-05: `DashboardConsistencyTest` makes decision 10's four pairs real (visible triple with the `scopePublished` contract as independent oracle; heatmap total vs the published series; blocked across gap rows, the verified four-reason queue union and the burn-down; per-podcast health vs the scoped funnel) plus the intake reconciliation extension (honest inequalities for overlapping reason sets), range-switch stock/flow stillness and legend-as-filter cross-widget pins — all green-first (zero inconsistencies found), every pin mutation-verified; and the `rtl-board` browser group (excluded from the main gate per decision 9, run alone on demand) walks all three lenses in a real Hebrew-locale browser proving the RTL board with its deliberate LTR islands. |
| Prompt 14 viewer/studio future plan | Future planning after Prompt 13 | Active prompt/blueprint | Documentation/planning only. |
| Prompt 15 Filament Blueprint security audit | Audit after Prompt 14 | Active prompt/blueprint | Audit-only unless fixes are explicitly approved. |

## Known Blockers and Historical Queue Context

- The accepted feature-first reset supersedes the old AUTHZ1-D–I/ARCH1 sequence
  and the old automatic P2-first continuation. The bounded v1 closure is
  complete. Step 5B is implemented under its accepted Simplifier audit and no
  further implementation is automatically selected.

- The active surviving-work and deferred-work registers are in
  `docs/research/settings-performance/10-pending-decision-question-queue.md`.
  They restore `WB-PROBE-HF1`, the Google probe gate, LENS review packs,
  and production settings/cache/mail checks without reactivating ARCH1, SP3D,
  SP4, LOG1, automatic P2-first execution, or the retired SP3 browser harness.

- Prompt 13 dashboard metrics was explicitly chosen by the operator on 2026-07-31 (dashboard-first, then FETCH2, then 9F-mini). Phases 1-2 of 4 plus the 2R remediation, the 2026-08-03 verify round, the F block (localization home, guard, adoption, breakdown roll-up) the A block (sparkline normalisation, trend palette home, dashed empty states + panel-native doorways, the P11 reason-bar doorway fix) and B1 (Alpine hover crosshair + tooltip with keyboard and screen-reader access, browser-proven) are complete; phase 3 (Intake lens) was implemented 2026-08-04 from the twice-reconciled plan; only phase 4 (on-demand RTL browser evidence) remains.

- Phase 2R (`docs/phase-02/dashboard-metrics-phase-1-2-remediation-audit.md`)
  closed the phase-1/2 gaps before Board 3: blocked split into two tiers
  (invisible vs needs-attention) with the previously untracked
  `unpublished_group` reason, full filawidgets data-contract adoption with a
  Jerusalem-correct series helper, the legend actually scoping the flow widgets,
  restored stream columns and full day-first date-times, `canView()` on every
  widget, and cache invalidation on editorial writes. A4 (visible-series proxy)
  closed 2026-07-31 by `ce15d96` — `becameVisibleAt()` derives the day an
  episode actually became visible — per
  `dashboard-metrics-phase-1-2-remediation-audit.md` (A4 ✅ at :23, restated at
  :118); the phase-4 evidence items remain open. **Cite dashboard findings by
  document, never by bare ID:** that audit and
  `dashboard-metrics-phase-2R-handoff.md` both number findings A1–A4 for
  different things — the handoff's A4 is the reason-bar doorway closure
  (`c36f6c4`), an unrelated finding. Conflating the two is what made an earlier
  pass of this line cite the wrong commit.

## Deferred Items

- `transcript_file` import support is deferred until an approved import package structure for referenced `.md`/`.txt` files exists.
- Curated homepage query sections are deferred until a concrete query-builder spec exists.
- Homepage result previews in admin forms remain deferred.
- Step 5B saved width/sample preferences, server/per-user Builder display-mode
  persistence, custom live synchronization from cloned slide-over action state,
  synthetic or persisted samples, autosave, revisions, collaboration, and
  generalized preview infrastructure remain deferred. Finite rendered
  presentation Selects and authoritative inline Builder state now refresh;
  key/label identity fields intentionally do not.
- Step 5B FU01 strict top-level item/group image ordering, FU02 effective
  sample-ranking parity, and FU03/O4 path-corrected invalid-field navigation
  are complete. FU04 order-compatibility closure is next and unapproved;
  FU05–FU06 remain sequentially later and unapproved. Nested image blocks, O2
  inline header editing, O3 global
  explicit-order cutover, and the remaining research-31 corrections/evidence
  gaps remain unimplemented. FU03/O4 was an internal Step 5B validation bug,
  not a GitHub issue. Existing global explicit-order validation/import/restore
  compatibility remains authoritative; no production normalization is selected
  or prescribed.
- Footer-builder v2 and nested/dropdown public menu editing remain deferred beyond Step 10. Step 9F/10F foundation should wait until Step 10R-M1 through Step 10R-M6, Step 10R-IP1 through Step 10R-IP3, Step 10R-P1 through Step 10R-P3, Step 10R-B4, and Step 10R-C2 are complete and should still run before Step 11 seeders if footer/rich-section demo content is required. The post-M6 UX/V/S settings enhancement mini-steps run before or around P1-P3 as recorded in the central ledger.
- Public form email notifications remain deferred.
- Public form file uploads remain deferred.
- Advanced homepage section manual-selection controls such as "select all filtered results" and "deselect all filtered results" are deferred; Step 4 ships explicit include/exclude ID selection with public visibility rechecks.
- Associate-existing transcription remains deferred because `Transcription` belongs to one `ContentItem`.
- A separate public volunteer/contributor profile table remains deferred; Prompt 11B uses `Author` as the public-safe contributor/transcriber entity.
- `ContentItemForm::featured_transcription_id` remains create-disabled; transcriptions are created through item-scoped relation manager/full Resource workflows.
- `TranscriptionForm::content_item_id` remains create-disabled; creating a content item inline from a transcript form is too large for a safe selector modal.
- `SpatieTagsInput` remains plugin-managed and was not replaced with custom pivot or modal behavior.
- The Add transcription table/relation-manager row action reuses the existing author selector and remains options-only because it is not a relationship-bound Resource form selector.
- Editorial dashboard widgets belong to Prompt 13.
- Viewer/studio sync planning belongs to Prompt 14; no sync/studio implementation is active yet.
- Public Front v2 Step 2 / Reserved transcription publication policy is deferred. Keep the current featured/effective transcription behavior unless a later isolated prompt explicitly promotes the policy work.

## Tooling State

*Verified against `composer show --direct` and `php -r 'echo PHP_VERSION;'` on 2026-08-12 (Phase U close).*

- Laravel: 13.24.0 (deliberately held during the Pest 5 batch — 13.25.0 was available and excluded from the narrow update).
- PHP: 8.4.23 from the local CLI; Laravel Boost reports PHP 8.4. **Xdebug v3.4.0alpha2-dev installed 2026-08-12** (Herd-bundled .so, `xdebug.mode=off` default, `XDEBUG_MODE=coverage` per-run for TIA; ini backup `php.ini.pre-xdebug-backup-2026-08-12`).
- Filament: 5.7.6.
- Livewire: 4.3.5.
- Laravel Horizon: 5.48.2.
- Laravel Boost: 2.5.3 installed and available through MCP (with `laravel/roster` 1.0; upgrade both together).
- Pest: **5.1.0** (Phase U, `8617bb2`, 2026-08-12; PHPUnit 13.3.0 underneath) with pest-plugin-browser 5.0.1, pest-plugin-laravel 5.0.1, pest-plugin-drift 5.0.0, and new dev plugins pest-plugin-phpstan 5.0.2 + pest-plugin-rector 5.0.3. Suite gate at the upgrade: 2,012 tests / 20,991 assertions / ~370s on the MySQL lane. Full record: `docs/research/test-suite-rethink-notes.md` § Phase U record.
- FilaCheck: 1.2.5 installed.
- FilaCheck Pro: 1.2.7 installed.
- Awcodes Curator: 5.1.5 installed.
- Spatie Laravel Tags: 4.12.0 installed.
- Filament Spatie Laravel Tags plugin: 5.7.6 installed.
- Spatie Laravel Settings: 3.9.0 installed.
- Filament Spatie Laravel Settings plugin: 5.7.6 installed.
- Tailwind CSS: 4.3.3.
- Vite: 8.2.1; Laravel Vite plugin: 3.1.3.
- App locale from `php artisan about`: `he`.
- App timezone from `php artisan about`: `UTC`; Phase 02 UI requirements still require Israel/Hebrew date presentation in `Asia/Jerusalem` while storing dates with Laravel's normal conventions.

## Boost MCP Status

Laravel Boost MCP tools were exposed and usable during Prompt 10.

- Boost tools used: `application_info`, `database_schema`, and `search_docs`.
- Boost confirmed Laravel 13.17.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and SQLite.
- Boost schema inspection confirmed the post-Prompt-08/09 tables and fields listed below.
- Boost `search_docs` was used for current Filament import/export APIs before code changes.
- FilamentExamples MCP `search_examples` returned snippet-level examples for `ImportAction`, `ExportAction`, `ExportBulkAction`, `Importer`, and `Exporter` patterns.
- Prompt 11 also used Boost `application_info`, `database_schema`, and `search_docs` before changing Livewire, Filament table/filter, URL-state, Spatie Settings, and settings-page behavior.
- Prompt 11 FilamentExamples research returned snippet/source examples for public Filament table, card, and filter patterns.
- Prompt 11R used Boost `application_info`, `database_schema`, and `search_docs` for Livewire URL state, pagination, Eloquent queries, settings, and Filament page context before changing code.
- Prompt 11R FilamentExamples research returned source snippets for public Filament table/filter examples; those snippets were used only to identify the prior table pattern to remove from the public listing.
- Prompt 11A used Boost `application_info`, detailed `database_schema`, and `search_docs` for Filament 5 `Select::relationship()`, option actions, relation managers, stable relation keys, shared forms, and `hiddenOn()` before changing code.
- Prompt 11A FilamentExamples research returned source snippets for relation-manager and selector/action patterns; access level was snippet/source through `search_examples`, not a full repository fetch.
- Prompt 11B used Boost `application_info`, `database_schema`, and `search_docs` for Livewire 4 URL attributes, `wire:model.live.debounce`, pagination, Laravel seeding, and public Filament page patterns before changing code.
- Prompt 11B FilamentExamples research returned snippet/source examples for custom multi-panel Filament Pages and Livewire-rendered page content; snippets were used as page-shell design reference, not copied wholesale.
- Prompt 12 used Boost `application_info`, `database_schema`, and `search_docs` for the installed Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, public page, Livewire URL state, Alpine, media rendering, and test behavior before changing code.
- Prompt 12 FilamentExamples research returned snippet/source examples for custom public page and Livewire-rendered page content; snippets were used as reference only.
- Public Front v2 Step 1 used Boost `application_info`, `database_schema`, and `search_docs` for installed Laravel 13.18.0, Filament 5.6.7, Pest 4.7.4, Spatie Settings storage, settings-page save/fill hooks, and array validation behavior before code changes.
- Public Front v2 Step 1 FilamentExamples research returned snippet-level settings/form examples only; no full source/detail fetch tool was exposed.
- CURATOR-G1 used Boost version-aware application/package guidance for Laravel
  13.19.0, Filament 5.6.7, Livewire 4.3.3, and Pest 4.7.4. Refined
  FilamentExamples searches returned snippet-only picker/resource/upload/card
  patterns; installed Curator 5.1.2 source
  `2a79bf031099d2d75351377eae15322fb590ab43` remained authoritative.
- Public Front v2 Step 4 used Boost `application_info`, `database_schema`, and `search_docs` before changing migrations, casts, Filament form fields, Livewire rendering, and tests.
- Public Front v2 Step 4 FilamentExamples research returned snippet/source-level examples for dynamic homepage sections, section manager patterns, looper/query display concepts, and admin selection/table-selection patterns; no parallel agents or worktrees were used.
- Public Front v2 Step 5 used Boost `application_info`, `database_schema`, and `search_docs` before changing Livewire URL state, pagination, Alpine drawer behavior, Blade rendering, card template rendering, and tests.
- Public Front v2 Step 5 FilamentExamples `search_examples` research returned snippet-level examples for public Livewire tables/cards/filters and grid/card patterns; no full source/detail fetch was exposed for the requested latest drawer/looper renderer patterns.
- Public Front v2 Step 6 used Boost `application_info`, `database_schema`, and `search_docs` before changing migrations, Eloquent models/enums/casts, validation, rate limiting, Livewire forms, Filament Resources, Filament form components, and Pest tests.
- Public Front v2 Step 6 FilamentExamples `search_examples` research returned snippet/source-level examples for dynamic forms, public/custom pages, Resource tables, and actions; no full source/detail fetch tool was exposed.
- Public Front v2 Step 7 used Boost `application_info`, `database_schema`, and `search_docs` before changing Spatie Settings JSON normalization, Filament Builder/Repeater/RichEditor/MarkdownEditor/FileUpload usage, public pages, Livewire-safe rendering, and Pest tests.
- Public Front v2 Step 7 FilamentExamples `search_examples` research returned snippet/source-level examples for custom settings pages, repeaters, FileUpload image handling, and custom public pages; no full source/detail fetch tool was exposed.
- Public Front v2 Step 8 used Boost `application_info`, `database_schema`, and `search_docs` before changing public routes, Filament public Pages, Livewire URL state and pagination, Eloquent query scopes/counts, Spatie Settings JSON normalization, Filament settings fields, and Pest tests.
- Public Front v2 Step 8 FilamentExamples `search_examples` research returned snippet/search-level examples for public cards/pages, dynamic sections, content group/list pages, and search/filter patterns; no full source/detail fetch tool was exposed.
- Public Front v2 Step 9 used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament settings tabs/sections, Livewire URL state and pagination, public panel render hooks/layout behavior, Alpine interactions, Filament Builder/Repeater behavior, and Pest tests.
- Public Front v2 Step 9 FilamentExamples `search_examples` research returned snippet/search-level examples for settings tabs/page layouts/menu builder patterns; no full source/detail fetch tool was exposed.
- Public Front v2 Step 9R used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament settings tabs/sections, public panel header rendering, Livewire URL state, Blade rendering, and Pest tests.
- Public Front v2 Step 9R FilamentExamples `search_examples` research was run in focused batches plus a refined second pass for settings tabs, public header/menu, card grids, file/logo upload patterns, and Markdown/RichEditor rendering. No source/read/fetch details tool was exposed beyond search snippets.
- Public Front v2 Step 10 used Boost `application_info`, `database_schema`, and `search_docs` before changing Livewire URL state, pagination, Filament settings fields, Eloquent aggregate/public visibility queries, card rendering, and Pest/Livewire tests.
- Public Front v2 Step 10 FilamentExamples `search_examples` research was run in focused batches plus a refined second pass for directory cards, selector/preview state, top/ranked sections, pagination/grid controls, settings field organization, and public Livewire pages. No source/read/fetch details tool was exposed beyond search snippets.
- Public Front v2 Step 10R-B3 used Boost `application_info`, `database_schema`, and `search_docs` before changing Blade components, presenters, Livewire-rendered card surfaces, and Pest tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for card grids, profile view data, custom view cards, eager-loaded cards, and Livewire card grids; access level was search/snippet only.
- Public Front v2 Step 10R-M1 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before changing migrations, Eloquent many-to-many relationships, model events/helpers, and Pest tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for multiple relationship selects, pivot/repeater patterns, and relationship filters; access level was search/snippet only.
- Public Front v2 Step 10R-M2 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before dropping the old pivot, changing Eloquent relationships, Filament forms/tables/relation managers, native import/export classes, Livewire URL-backed search state, public rendering, and Pest tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for multiple relationship selects, belongs-to-many relation state, searchable filters, and importer/exporter relationship patterns; access level was search/snippet only.
- Public Front v2 Step 10R-M3 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before changing Spatie Settings JSON policy, Eloquent query helpers, aggregate subqueries, Livewire public filter behavior, and Pest tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for settings pages, public page query data, aggregate counts, and Livewire URL state; access level was search/snippet only.
- Public Front v2 Step 10R-M4 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before changing public rendering, Livewire computed properties, settings tokens, lazy-loading prevention, card presenters/registries, and Pest query-count tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for public cards, URL state, nested settings, aggregate stats, eager-loaded view data, and custom public Blade/page patterns; access level was search/snippet only.
- Public Front v2 Step 10R-HF1 used Boost `application_info` and `search_docs` before changing Markdown rendering, transcript viewer Blade output, and Pest rendered-output assertions. Local vendor source was inspected for Symfony HtmlSanitizer `maxInputLength`/`withMaxInputLength(-1)` behavior and CommonMark `renderer.soft_break`, `html_input`, and `allow_unsafe_links` options.
- Public Front v2 Step 10R-M5 used Boost `application_info`, `database_schema`, and `search_docs` before changing card-template settings, validators, presenters, Blade rendering, and Pest rendered-output tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for card grids, nested Builder/repeater settings, safe icon maps, and metadata row rendering; access level was search/snippet only.
- Public Front v2 Step 10R-IP1 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before changing Spatie Settings JSON, settings migrations, Filament SettingsPage tabs/fieldsets, card-template attributes, presenter date formatting, and Pest rendered-output tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for settings tabs, nested settings fields, date badge settings, safe icon maps, and public detail page patterns; access level was search/snippet only.
- Public Front v2 Step 10R-IP2 used Boost `application_info`, `database_schema`, and `search_docs` before changing the episode page header, Spatie Settings JSON, settings migrations, Filament SettingsPage repeaters/sections, eager-loaded public page rendering, and Pest rendered-output tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for public detail pages, settings repeaters, metadata badge links, custom page view data, and public Blade card links; access level was search/snippet only.
- Public Front v2 Step 10R-IP3 used Boost `application_info`, `database_schema`, and `search_docs` before changing the transcript viewer, Spatie Settings JSON, settings migration, Episode page settings tab, public item page media/share layout, Alpine/localStorage controls, and Pest rendered-output tests. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for public detail pages, media sidebar/share actions, settings tabs, Alpine fullscreen/font-size controls, dropdown/action-group patterns, and custom page view data; access level was search/snippet only.
- Public Front v2 Step 10R-M6 used Boost `application_info`, `database_schema`, `database_query`, and `search_docs` before the stabilization audit and default-alignment settings migration. FilamentExamples `search_examples` was run in focused batches plus a refined second pass for settings pages, public detail pages, media sidebars, Livewire public page tests, settings repeaters, Alpine action groups, computed Livewire pages, and clipboard actions; access level was search/snippet only.
- Public Front v2 post-M6 admin/settings enhancement planning used Boost `application_info`, `database_schema`, and `search_docs` for Filament navigation sorting, record-action placement, relation-manager tabs, action modal width, FileUpload, ColorPicker, settings import/export, and transaction/cache behavior. FilamentExamples `search_examples` was run in focused batches plus refined passes for icon selects, relation tabs, table actions, wide action modals, settings pages, FileUpload settings, ColorPicker/custom color fields, and settings import/export examples; access level was search/snippet only.
- Public Front v2 Step 10R-UX1 used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament navigation sorting, `configureUsing()` defaults, table record-action placement, action modal widths, Section spans, and combined relation-manager tabs. FilamentExamples `search_examples` was run in short batches plus refined passes for navigation sort, table action placement, modal/section width patterns, relation manager tabs, and admin theme CSS; access level was search/snippet only.
- Public Front v2 Step 10R-UX2 used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament table actions, modal `fillForm()`/`action()` behavior, `extraModalFooterActions()`, relation-manager action mounting, and `TestAction` assertions. FilamentExamples `search_examples` was run in short batches plus refined passes for editing related records through table modal actions, custom action classes, and modal footer links; access level was search/snippet only.
- Public Front v2 Step 10R-V1a used Boost `application_info`, `database_schema`, and `search_docs` before changing Spatie Settings JSON, settings migrations, Filament SettingsPage FileUpload fields, public image fallback rendering, and Pest rendered-output tests. FilamentExamples `search_examples` was run in short batches plus a refined pass for SettingsPage FileUpload image settings and card fallback patterns; access level was search/snippet only.
- Public Front v2 Step 10R-V1b used Boost `application_info`, `database_schema`, and `search_docs` before changing Heroicon enum settings, Filament `Select` lazy search/HTML labels, Spatie settings migrations, and Pest/Livewire assertions. FilamentExamples `search_examples` was run in short batches plus a refined pass for Yoni's selected icon picker reference and lazy searchable Select patterns; access level was search/snippet only.
- Public Front v2 Step 10R-V1c used Boost `application_info`, `database_schema`, and `search_docs` before changing custom color settings, Filament `ColorPicker` fields, Spatie settings migration/validation, Laravel cache usage, storage-safe cover sampling, and Pest public rendering assertions. FilamentExamples `search_examples` was run in short batches plus a refined pass for ColorPicker/settings-page and conditional-field patterns; access level was search/snippet only.
- Public Front v2 Step 10R-P1 used Boost `application_info`, `database_schema`, and `search_docs` before adding validated config caching, settings-save invalidation, settings-migration watermarking, and cache-fallback tests. FilamentExamples `search_examples` was run in short batches plus a refined settings-page lifecycle pass; access level was search/snippet only and no direct cache-invalidation example was exposed.
- Public Front v2 Step 10R-S2 used Boost `application_info`, `database_schema`, and `search_docs` before adding settings backup versions, package serialization, transactions, streamed downloads, and Filament table actions. Boost did not return useful Spatie settings repository/event docs, so the installed vendor source was inspected for `SettingsSaved`, `Settings::save()`, and `getPropertiesInGroup()`. FilamentExamples `search_examples` was run in short batches plus a refined pass for settings actions, modal forms, confirmation actions, download/compare patterns, and TextEntry modal content; access level was search/snippet only.
- Public Front v2 Step 10R-S2V used Boost `application_info`, `database_schema`, and `search_docs` before adding queued snapshot jobs, Laravel `Process` execution, private filesystem downloads, Filament table image columns, and modal/slide-over gallery actions. FilamentExamples `search_examples` was run in short batches plus a refined pass for image galleries, image columns, table modal content, retry/download actions, and slide-over views; access level was search/snippet only.
- Public Front v2 Step 10R-S1a used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament custom pages/header actions/stream downloads/FileUpload, Livewire upload/download/selection-table state, Laravel queue after-commit/timeout behavior, DB after-commit, Laravel `Process`, Spatie settings, and Pest/Filament action tests. FilamentExamples `search_examples` was run in short batches plus a refined pass for import wizards, settings page header actions, streamed downloads, and upload/preview table patterns; access level was search/snippet only.
- Public Front v2 Step 10R-S1b used Boost `application_info`, `database_schema`, and `search_docs` before changing Livewire file-upload MIME validation, Livewire checkbox/array state, Laravel dot-path helpers, Spatie settings saves, and Filament hidden page/header-action lock-manager patterns. FilamentExamples `search_examples` was run in short batches plus a refined pass for matrix/toggle table, settings lock manager, and bulk toggle patterns; access level was search/snippet only.
- Public Front v2 Step 10R-S1c used Boost `application_info`/`search_docs` and FilamentExamples `search_examples` before changing Filament SettingsPage header actions, section header actions, field hint actions, Livewire settings action state, and Pest action assertions. FilamentExamples access level was search/snippet only. The run also confirmed Pest/Laravel can inherit exported dev DB variables before XML env is effective, so `tests/Pest.php` now forces test env values before app boot and `tests/TestCase.php` aborts unless tests run against SQLite `:memory:`.
- Public Front v2 Step 10R-S1d used Boost `application_info`, `database_schema`, and `search_docs` before changing settings import reports, panel middleware, Horizon authorization, and Livewire/Pest tests. FilamentExamples `search_examples` returned search/snippet-only examples for table action report modals, grouped read-only entries, wizard summary UI, and multi-panel provider patterns.
- Importer Workbench WB1 used Boost `application_info`, `database_schema`, and `search_docs` before adding `import_connections`, Google/Socialite/Spotify connector boundaries, a custom Filament Importer Settings page, and Pest tests. FilamentExamples `search_examples` was run in multiple short batches plus a refined pass for custom pages with schema/table actions, progressive disclosure, FileUpload constraints, and table record actions; access level was search/snippet only.
- Step 10R-HF3 used Boost `application_info`, `database_schema`, and `search_docs` before changing Filament exporters, queue lifecycle hooks, and queue event tracing. Boost confirmed Laravel 13.19.0, Filament 5.6.7, Horizon 5.47.2, and Pest 4.7.4; docs confirmed exporter `modifyQuery()`, batch names, tags, queue hooks, completion notifications, and queue event payload access. FilamentExamples `search_examples` was run in short batches plus a refined pass for exporter/table query patterns; access level was search/snippet only and no source/detail fetch tool was exposed.
- The staged Eloquent strict mode chore ran without Boost MCP exposure (no Boost tools were registered in that session); framework lazy-loading and missing-attribute semantics were verified directly against the installed `laravel/framework` source (`Builder::hydrate`, `HasAttributes::getRelationValue`/`handleLazyLoadingViolation`, `Model::offsetExists`) plus a `php artisan tinker` probe. FilamentExamples was not consulted because no Filament surface changed.

## Application Shape

- Local runtime database driver: MySQL 8 via Herd on `127.0.0.1:3306`, database `podtext`, for production-parity migration checks. The test suite intentionally forces SQLite `:memory:` through `phpunit.xml` and `tests/Pest.php`; the base TestCase aborts before migrations if that safety contract is not active.
- Public panel root: `/`.
- Admin panel root: `/admin`.
- `php artisan route:list --path=contributors` reports the public contributor directory and contributor detail routes.
- Existing public pages remain:
  - `App\Filament\Public\Pages\AboutPage`
  - `App\Filament\Public\Pages\BrowseContentGroups`
  - `App\Filament\Public\Pages\BrowsePublicContentGroups`
  - `App\Filament\Public\Pages\SearchContentItems`
  - `App\Filament\Public\Pages\BrowseCategoryContentItems`
  - `App\Filament\Public\Pages\BrowseTagContentItems`
  - `App\Filament\Public\Pages\BrowseContributors`
  - `App\Filament\Public\Pages\ShowContributor`
  - `App\Filament\Public\Pages\ShowContentGroup`
  - `App\Filament\Public\Pages\ShowContentItem`
- Existing public Livewire components remain:
  - `App\Livewire\Public\ContentGroupBrowser`
  - `App\Livewire\Public\ContentItemBrowser`
- Prompt 11 public homepage/search component:
  - `App\Livewire\Public\ContentItemSearch`
- Prompt 11B public contributor components:
  - `App\Livewire\Public\ContributorDirectory`
  - `App\Livewire\Public\ContributorContentItems`
- Public Front v2 Step 10 public contributor/top-transcriber component:
  - `App\Livewire\Public\TopTranscribersSection`
- Prompt 12 public item transcript viewer component:
  - `App\Livewire\Public\ContentItemTranscriptViewer`
- PodText logo baseline:
  - `public/images/podtext-logo.jpg`
- Prompt 11R public Blade components:
  - `resources/views/components/public/contributor-card.blade.php`
  - `resources/views/components/public/content-item-card.blade.php`
  - `resources/views/components/public/content-group-badge.blade.php`
  - `resources/views/components/public/content-item-grid.blade.php`
  - `resources/views/components/public/public-filter-panel.blade.php`
- Public Front v2 Step 10 public Blade components:
  - `resources/views/components/public/contributor-item-grid.blade.php`
  - `resources/views/components/public/contributor-transcription-list.blade.php`
- Prompt 11 public card option mapper:
  - `App\Support\PublicContent\PublicContentCardOptions`
- Prompt 11B public query helpers:
  - `App\Support\PublicContent\PublicContentItemQueries`
  - `App\Support\PublicContent\PublicContributorDiscovery`
- Prompt 11A admin helper:
  - `App\Filament\Resources\Support\RelationshipOptionForms`
- Prompt 11A admin relation manager:
  - `App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager`
- Public Front v2 Step 10R-UX1 admin navigation support:
  - `App\Filament\Support\AdminNavigationOrder`
  - `App\Filament\Support\Concerns\UsesAdminNavigationOrder`
  - `App\Filament\Pages\Dashboard`
- Prompt 12 parser:
  - `App\Support\Transcripts\TranscriptSegmentParser`
- Public Front v2 Step 1 support classes:
  - `App\Support\PublicFront\PublicFrontConfigRegistry`
  - `App\Support\PublicFront\PublicFrontConfigReader`
  - `App\Support\PublicFront\PublicFrontConfigValidator`
  - `App\Support\PublicFront\PublicFrontConfigResult`
  - `App\Support\PublicFront\PublicFrontInvalidConfig`
- Public Front v2 Step 3 card template support classes:
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry`
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver`
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer`
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplate`
  - `App\Support\PublicFront\Cards\PublicFrontCardPart`
- Public Front v2 Step 4 display section support classes:
  - `App\Support\PublicFront\Sections\PublicDisplaySectionRegistry`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionConfigValidator`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionConfigResult`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionResolver`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionQueryResolver`
  - `App\Support\PublicFront\Sections\PublicDisplaySectionResult`
- Public Front v2 Step 5 Latest/Search UX surfaces:
  - `App\Livewire\Public\ContentItemSearch` now owns latest section controls and multi-select search filter state.
  - `App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer::contentItemPresentation()` returns controlled content-item card presentation metadata.
  - `resources/views/components/public/public-filter-panel.blade.php` renders the public search filter drawer.
- Public Front v2 Step 6 Public Forms and Submissions surfaces:
  - `App\Livewire\Public\PublicFormModal`
  - `App\Models\PublicFormSubmission`
  - `App\Enums\PublicFormFieldType`
  - `App\Enums\PublicFormSubmissionStatus`
  - `App\Support\PublicFront\Forms\PublicFormDefinitionRegistry`
  - `App\Support\PublicFront\Forms\PublicFormPayloadValidator`
  - `App\Support\PublicFront\Forms\PublicFormSchemaFactory`
  - `App\Support\PublicFront\Forms\PublicFormSubmissionPresenter`
  - `App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource`
  - `resources/views/livewire/public/public-form-modal.blade.php`
  - `docs/phase-02/public-front-v2-step6-public-forms-submissions-handoff.md`
- Public Front v2 Step 7 About Page Content and Team Builder surfaces:
  - `App\Filament\Public\Pages\AboutPage`
  - `App\Support\PublicFront\About\PublicAboutPageRegistry`
  - `App\Support\PublicFront\About\PublicAboutPageRenderer`
  - `resources/views/filament/public/pages/about-page.blade.php`
  - `resources/views/components/public/about/team-section.blade.php`
  - `resources/views/components/public/about/profile-card.blade.php`
  - `docs/phase-02/public-front-v2-step7-about-page-content-team-builder-handoff.md`
- Public Front v2 Step 7 About page JSON schema lives under `public_content.about_page` with `enabled`, `title`, `kicker`, `description`, `blocks`, `team_profiles`, and `settings`.
- Public Front v2 Step 7 team profile JSON schema lives under `public_content.about_page.team_profiles`; no `AboutPage`, `AboutPageBlock`, or `TeamProfile` model/table exists.
- Public Front v2 Step 8 podcast/group JSON schema lives under `public_content.podcasts_page` with page labels, title/description, pagination, search/category toggles, template keys, card visibility toggles, and nested group-page display options.
- Public Front v2 Step 8 canonical public routes are `/podcasts` and `/podcasts/{contentGroupSlug}`. The old public `/groups/{contentGroupSlug}` route is absent; admin `admin/content-groups` routes remain unchanged.
- Post-Step-10 content group type-label defaults in `ContentGroupForm` are translation-backed through `public.labels.podcast`, `public.labels.podcasts`, `public.labels.item`, and `public.labels.items` instead of hard-coded English strings.
- Public Front v2 Step 8 public group query helper:
  - `App\Support\PublicFront\Groups\PublicContentGroupQueries`
- Public Front v2 Step 10 contributor JSON schema lives under `public_content.contributors_page` with contributor labels, directory page-size/sort options, preview grid/search options, top-transcriber selector/preview options, compact-card tokens, and full contributor item-list controls.
- Public Front v2 Step 10 contributor directory/page behavior:
  - `/contributors` uses compact contributor selector cards with URL-backed search/sort/page-size/selected state.
  - `/contributors/{authorSlug}` uses URL-backed related-item search/sort/page-size state.
  - Top-transcriber homepage sections render a horizontal selector and selected preview through `App\Livewire\Public\TopTranscribersSection`.
  - Same-author multiple transcriptions on one item count separately but render one related `ContentItem` card with grouped transcription titles.
- Public Front v2 Step 8 handoff:
  - `docs/phase-02/public-front-v2-step8-podcasts-groups-ux-handoff.md`
- Public Front v2 Step 9 Public Menu/Header and UX Fixes surfaces:
  - `App\Enums\PublicMenuItemType`
  - `App\Livewire\Public\PublicHeader`
  - `App\Support\PublicFront\Menu\PublicMenuConfigReader`
  - `App\Support\PublicFront\Menu\PublicMenuRenderer`
  - `App\Support\PublicFront\Menu\PublicRouteRegistry`
  - `App\Support\PublicFront\Menu\PublicUrlSanitizer`
  - `resources/views/livewire/public/public-header.blade.php`
  - `docs/phase-02/public-front-v2-step9-public-menu-header-ux-fixes-handoff.md`
- Public Front v2 Step 9 public settings admin organization uses tabs for Homepage/Sections, General/Display, Menu/Header, Podcasts, About, Forms, and Advanced/Diagnostics.
- Public Front v2 Step 9 menu/header JSON schema lives under `public_content.menu_config` with `enabled`, `items`, and `theme_selector`. No `PublicMenu` or `PublicMenuItem` model/table exists.
- Post-Step-10 public header state:
  - Desktop header search renders before an independent theme selector when `theme_selector.enabled` is true.
  - Desktop theme selector rendering no longer depends on a `theme_selector` menu item in the desktop menu loop.
  - Header search icon and theme menu positioning use RTL-safe logical inset utilities.
  - Mobile theme selector rendering remains menu-item driven.
  - Public panel SPA mode is not enabled; the temporary `->spa()` addition was removed by `2b1c6b3`.
- Public Front v2 Step 9 About/team card settings live under `public_content.about_page.settings.team_card`; Step 7 About/team JSON remains compatible and no `TeamProfile` model/table exists.
- Public Front v2 Step 9 contributor directory keeps `Author` as the public contributor/transcriber model and changes only the compact-card/preview UX.
- Public Front v2 Step 9 homepage chrome is suppressed for default homepage sections while `/search` keeps the discovery search/filter UI.
- Public Front v2 Step 9 adds a minimal JSON-only `content_block` homepage section source; this is not a CMS/page-management system.
- Public Front v2 Step 1 enums:
  - `App\Enums\PublicFrontConfigBlockType`
  - `App\Enums\PublicFrontLayoutVariant`

## Current Domain Schema

Current tables relevant to Phase 02 content after Prompt 08 and Prompt 09:

- `authors`
- `content_groups`
- `content_items`
- `transcriptions`
- `author_transcription`
- `categories`
- `category_content_group`
- `category_content_item`
- `tags`
- `taggables`
- `settings`
- `homepage_sections`
- `public_form_submissions`

Prompt 07 migration status from `php artisan migrate:status` and Boost database inspection:

- `2026_06_29_134855_create_transcriptions_table`: ran.
- `2026_07_08_000000_create_author_transcription_table`: ran.
- `2026_07_08_000001_drop_author_content_item_table`: ran.
- `2026_06_29_134914_add_featured_transcription_id_to_content_items_table`: ran.
- `2026_06_29_134914_backfill_transcriptions_from_content_items_table`: ran.

Prompt 08 migration status from `php artisan migrate:status` and Boost database inspection:

- `2026_06_30_012920_create_tag_tables`: ran.
- `2026_06_30_012921_create_settings_table`: ran.
- `2026_06_30_012923_create_categories_table`: ran.
- `2026_06_30_012931_create_homepage_sections_table`: ran.
- `2026_06_30_012932_add_prompt08_fields_to_content_items_table`: ran.
- `2026_06_30_012933_add_homepage_order_to_content_groups_table`: ran.
- `2026_06_30_012934_create_public_content_settings`: ran.
- `2026_07_02_000000_add_public_content_card_settings`: added by Prompt 11.

Public Front v2 Step 6 migration status from `php artisan migrate:status` and local migration run:

- `2026_07_05_000000_create_public_form_submissions_table`: ran.
- `2026_07_05_000001_normalize_public_forms_setting`: ran.

Public Front v2 Step 7 settings migration status from local migration run:

- `2026_07_05_000002_normalize_about_page_setting`: ran.

Public Front v2 Step 8 settings migration status from local migration run:

- `2026_07_05_000003_add_public_podcasts_page_setting`: ran.

Public Front v2 Step 9 settings migration status from local migration run:

- `2026_07_06_000000_normalize_public_menu_header_and_about_cards`: ran.
- `2026_07_06_000001_ensure_public_about_team_legacy_settings`: ran.

Local data reset note:

- Previous `migrate:status` output showed all migrations in batch 1, which strongly suggests the local database was rebuilt with `migrate:fresh --seed` or an equivalent reset path.
- The exact manual reset command was not observed.

Current physical schema verified through Boost `database_schema`:

- `transcriptions` table exists.
- `content_items.featured_transcription_id` exists.
- Legacy `content_items.transcript_markdown` still exists as a legacy/backfill source and later cleanup target.
- `tags` and `taggables` exist for Spatie tags.
- `tags` includes Phase 02 editorial metadata columns: `is_enabled`, `enabled_at`, `enabled_by_id`, `created_by_id`, and `moderation_state`.
- `settings` exists for Spatie Settings.
- `homepage_sections` exists with section target fields for category, tag, and content group plus Step 4 JSON config columns: `source_config`, `selection_config`, `display_config`, and `pagination_config`.
- `public_form_submissions` exists with Step 6 submission review fields: `form_key`, `form_name_snapshot`, `payload`, `status`, `submitted_at`, `source_url`, hashed submitter fingerprints, and `metadata`.

The local incident applied these G1 migrations in batch 8. Production did not
apply them in the dated read-only snapshot:

- `2026_07_20_000001_add_reference_key_to_curator_table`;
- `2026_07_20_000002_create_media_attachments_table`;
- `2026_07_20_000003_create_media_mutation_operations_table`;
- `2026_07_20_000000_add_public_media_reference_keys` (settings shape only;
  identity population remains in separate commands).

Fresh 2026-07-22 Boost schema inspection confirms local
`curator.reference_key`, `media_attachments`, and
`media_mutation_operations`. Separately, the already-verified 2026-07-21
incident snapshot recorded 15 unconverted Curator rows with null keys, five
group covers matching IDs 1-5, no episode image, header SVG IDs 6-7, default
settings backed by ID 9, and empty attachment/journal tables. Gate 0 did not
freshly probe those data/filesystem counts. The approved MediaAsset program
records the complete local and production profiles in
`docs/research/media-program/03-local-production-baselines.md` and does not run
real migrations or conversion in this task.

## Prompt 07 Implementation Notes

- `ContentItem::transcriptions()`, `ContentItem::featuredTranscription()`, `ContentItem::latestPublishedTranscription()`, and `ContentItem::effectiveTranscription()` exist.
- `Transcription::contentItem()` and `Transcription::author()` exist.
- `Author::transcriptions()` exists.
- `ContentItem::published()` requires a published parent group, a published item, and at least one published child transcription.
- Public item/group pages load and render effective/main transcription content instead of directly rendering legacy item transcript content.
- Featured transcription ownership is validated so a featured transcription must belong to the same `ContentItem`.
- Public effective transcription resolution ignores unpublished featured transcriptions and falls back to the latest published transcription.
- New writes to legacy `content_items.transcript_markdown` are deprecated/blocked in normal application paths.

## Prompt 08 Implementation Notes

- Prompt 08 is implemented and committed.
- Categories are implemented as custom hierarchical records.
- Spatie tags are implemented through the standard `tags` table and `taggables` pivot, scoped to type `content` in admin item forms.
- `App\Models\ContentTag` remains only as the configured Spatie custom tag model for enabled/moderation metadata on the normal Spatie `tags` table.
- Item pinning fields and content group homepage ordering fields exist.
- Prompt 08 media metadata foundation fields exist on `content_items`.
- `App\Settings\PublicContentSettings` works in the admin settings page and persists rows in the `settings` table.
- Public homepage/search pages now consume `PublicContentSettings`.

## Prompt 11 Public Homepage/Search Notes

- Prompt 11 is implemented.
- The public root `/` keeps the existing `BrowseContentGroups` root page class as a compatibility shell but renders `ContentItemSearch`; the homepage result unit is now `ContentItem`/episode cards, not `ContentGroup`/podcast cards.
- New public routes/pages exist for `/search`, `/categories/{categorySlug}`, and `/tags/{tagSlug}`.
- Public item listing visibility requires a published parent group, a published item, and at least one effective/main published transcription.
- Prompt 11R replaced the public Filament Table listing with custom Livewire state, `WithPagination`, URL-backed properties, and Blade-rendered card grids/rows.
- The reusable public item card view is now `resources/views/components/public/content-item-card.blade.php`; `resources/views/filament/tables/columns/public-content-item-card.blade.php` remains only as a compatibility wrapper.
- Public listing output no longer renders `{{ $this->table }}` or public Filament table markup as the primary UI.
- Public group badges are rendered through `resources/views/components/public/content-group-badge.blade.php`, including cover-image and title/initial fallback behavior.
- Card display is controlled by safe semantic Spatie settings, not raw CSS or Tailwind classes from the database.
- Prompt 11 card settings cover image size, density, title size, group badge visibility, authors/categories/tags/date/duration/description visibility, description line count, and cards per page.
- Semantic values are mapped in PHP through `PublicContentCardOptions`; Tailwind source scanning includes that support namespace.
- Public filters include custom Blade search, category with descendant and inherited group matching, enabled content tag, content group, transcriber, provider, effective/original date ranges, duration, and media-presence controls. The transcriber filter keeps the legacy `author` URL query alias for compatibility.
- Sort options include latest/oldest transcription, title A-Z/Z-A, duration shortest/longest, and original newest/oldest.
- Homepage default ordering keeps valid pinned items first unless an explicit sort is selected.
- Visible ordered `HomepageSection` records now render as separate homepage sections for `latest`, `category`, `tag`, and `content_group`, each using `ContentItem` records and the shared card component.
- Prompt 11B adds `top_transcribers` homepage sections, rendered as public `Author` contributor cards ranked by published transcriptions on public content items.
- Curated homepage query sections remain deferred by the blueprint/spec.
- Transcript body search remains deferred and is not part of default live search.
- Prompt 12 later implemented the public item page media/parser overhaul while preserving the Prompt 11R custom Livewire + Blade homepage/search renderer.

## Prompt 11B Public Contributor Discovery Notes

- Prompt 11B is implemented and committed as `8998f7e feat: add public contributor discovery`.
- Contributor/transcriber discovery uses `Author` as the public-safe contributor model. No `User` records are exposed publicly.
- New public routes exist:
  - `/contributors`
  - `/contributors/{authorSlug}`
- `ContributorDirectory` provides URL-backed live search with `#[Url(as: 'q', except: '')]`, paginates contributors, and stores selected preview contributor state in the URL as `contributor`.
- Contributor directory cards show public transcription counts and distinct public content item counts.
- Selecting a contributor card loads a live preview of related public `ContentItem` records through published transcriptions.
- Full contributor pages show the contributor name, safe-rendered public bio Markdown, counts, and paginated public `ContentItem` cards.
- Public contributor visibility/counting requires a published transcription by the author whose content item is public under existing public item rules: published group, published item, and effective/main published transcription.
- Contributor-related content item cards are still `ContentItem` records, never public `Transcription` cards.
- `DemoHebrewContentSeeder` remains idempotent and now creates a visible `top-transcribers` homepage section with stable slug `top-transcribers`.
- Public contributor profile records beyond `Author` remain deferred to a future contributor-profile prompt if needed.

## Prompt 09 and Admin Repair Notes

- Prompt 09 is implemented and committed.
- The post-Prompt-09 admin management UX repair is implemented and committed as `16ab33a`.
- `EditContentItem` uses `getContentTabLabel(): ?string` for the item details tab label.
- `EditContentItem` no longer overrides `getContentTabComponent()` only to change the label, preserving real form fields in the item details tab.
- ContentItem edit renders the item details tab, core item form fields, and the transcriptions tab.
- ContentItem create redirects to the edit page for the created item and notifies admins to add a transcription from the transcriptions tab.
- `ContentItemsTable` has an Add transcription row action.
- Associate-existing transcription was deferred because `Transcription` belongs to one `ContentItem`; associating an existing transcription would move it from another item rather than copy it.
- The first transcription created for an item is automatically set as `featured_transcription_id`.
- The set-featured action is exposed only when the item has more than one transcription.
- Draft transcriptions remain publicly ineffective even if selected as featured.
- `content_items.transcript_markdown` remains out of item forms and relation-manager writes.

## Prompt 11A Admin Relationship UX Notes

- Prompt 11A is implemented and committed locally as `1d81ec0 feat: improve admin relationship management ux`.
- Relationship selector policy:
  - Simple singular selectors get create and edit option modals.
  - Many-to-many selectors get create option modals only because installed Filament 5 does not expose edit-option actions for multiple selects.
  - Complex selectors stay create-disabled and use relation managers or full Resource pages.
- Shared modal schemas live in `App\Filament\Resources\Support\RelationshipOptionForms`.
- Create/edit option modals were added to these singular selectors:
  - `ContentItemForm::content_group_id`
  - `CategoryForm::parent_id`
  - `HomepageSectionForm::category_id`
  - `HomepageSectionForm::content_group_id`
- Create-only option modals were added to these medium/multiple selectors:
  - `TranscriptionForm::transcriber_ids`
  - `TranscriptionsRelationManager::transcriber_ids`
  - `ContentItemForm::categories`
  - `ContentGroupForm::categories`
  - `HomepageSectionForm::tag_id`
- `ContentItemForm::authors` was removed by Step 10R-M2; episode/item transcribers are now managed on transcription records.
- Intentionally unchanged complex selectors:
  - `ContentItemForm::featured_transcription_id`: create/edit transcriptions through the item transcriptions relation manager or full `TranscriptionResource`.
  - `TranscriptionForm::content_item_id`: creating content items inline from a transcript form is too large for a safe selector modal.
  - `SpatieTagsInput::make('tags')`: plugin-managed tag entry remains intact; no custom tag pivot or replacement selector was introduced.
  - Add transcription row action author selector: action data is not a relationship-bound Resource form selector, so it remains options-only while the action itself is reused.
- `ContentGroupResource` now registers `ContentItemsRelationManager` with stable relation key `contentItems`.
- `ContentItemsRelationManager` manages the owner group's `contentItems` relation, lists only current-group items, creates items through the owner context without submitting `content_group_id`, edits items in a modal, exposes delete actions consistently with existing admin conventions, links to the full `ContentItemResource` edit page, and reuses the existing Add transcription action.
- `ContentItemForm::content_group_id` is hidden on `ContentItemsRelationManager`; the owner relationship supplies the group.
- Prompt 11A did not start public contributors/transcribers discovery, public item pages, media embeds, parser work, import/export changes, or permissions work.
- Prompt 11B later implemented public contributors/transcribers discovery while leaving public item pages, media embeds, parser work, import/export changes, and permissions work untouched.

## Prompt 10 Import/Export Notes

- Prompt 10 is implemented.
- Native Filament importers/exporters now include:
  - `App\Filament\Imports\TranscriptionImporter`
  - `App\Filament\Exports\TranscriptionExporter`
  - `App\Filament\Imports\CategoryImporter`
  - `App\Filament\Exports\CategoryExporter`
- Existing importers/exporters were extended:
  - `ContentItemImporter` and `ContentItemExporter`
  - `ContentGroupImporter` and `ContentGroupExporter`
  - existing `AuthorExporter` date output was aligned to day-first date-time formatting.
- Transcription imports create/update `Transcription` child records and never write to legacy `content_items.transcript_markdown`.
- First imported transcription auto-feature behavior remains the existing model behavior and is covered by tests.
- `transcript_file` import support is deferred because the active blueprint/spec does not define an approved import package structure for locating referenced `.md`/`.txt` files. Inline `transcript_markdown` import is supported.
- Category import/export uses portable category paths such as `parent/child` and preserves hierarchy, visibility, sort order, and Markdown description.
- Content item and content group imports attach existing categories by path; missing category paths fail the row.
- Content item imports attach existing enabled Spatie content tags by slug/name using type `content`; missing tags, wrong-type tags, and disabled content tags fail the row.
- Prompt 10 preserves the Spatie tag decision: normal `tags` table, normal `taggables` pivot, `type = content`, and no custom `content_item_tag` pivot.
- Content item import/export now covers pin fields, media metadata fields, category paths, content tag slugs, and `featured_transcription_reference_key`.
- Content group import/export now covers category paths and `homepage_order`.
- Exporters use portable identifiers only: reference keys, category paths, and typed tag slugs. Numeric database IDs are not exported as portable identifiers.
- Exported date-times use `dd/mm/yyyy HH:mm` in `Asia/Jerusalem`; imported day-first date-times are normalized to Laravel storage.
- Exported user/content text is formula-escaped where exporter APIs expose formatting. Failed import rows continue through native Filament failed-row behavior.
- Native `ImportAction`, `ExportAction`, and `ExportBulkAction` are registered for content groups, content items, categories, and transcriptions. Existing author import/export compatibility remains.
- Prompt 10 did not implement public homepage/search, public item page/parser work, dashboard widgets, or studio/sync work.
- Prompt 11 later implemented public homepage/search while preserving Prompt 10 import/export behavior.

## Homepage and Settings Notes

- `HomepageSectionResource` is treated as homepage content configuration: records define which content slices appear on the homepage.
- `HomepageSectionForm` is type-driven:
  - `latest` does not require a category, tag, or content group target.
  - `category` requires `category_id`.
  - `tag` requires `tag_id`.
  - `content_group` requires `content_group_id`.
  - `top_transcribers` does not require a category, tag, or content group target.
  - `curated_query` remains deferred.
- Homepage settings and homepage sections have separate responsibilities:
  - `PublicContentSettings` stores global defaults, limits, and layout choices.
  - `HomepageSection` records configure ordered content slices.
  - Item pinning is separate and affects item ordering where public queries support it.
- Prompt 11 reads `PublicContentSettings` and visible ordered `HomepageSection` records when building the public homepage/search UI.

## Browser Regression Tests

- Pest browser testing is present.
- `tests/Browser/AdminContentItemBrowserTest.php` visits a ContentItem edit page in a real browser.
- The browser test asserts the item details tab label, title field, slug field, content group field, status field, media URL field, and transcriptions tab are visible.
- This test protects the `getContentTabLabel()` repair from regressing into an empty details tab.

## Prompt 12 Public Item Page/Media/Parser Notes

- Prompt 10 is complete.
- Prompt 11 is complete.
- Prompt 11R is complete and committed as `bb4b97c refactor: customize public content item discovery`.
- Prompt 11A is complete and committed as `1d81ec0 feat: improve admin relationship management ux`.
- Prompt 11B is complete and committed as `8998f7e feat: add public contributor discovery`.
- Prompt 12 readiness sync is complete and committed as `23242a1 docs: prepare prompt twelve after public discovery work`.
- Prompt 12 is implemented and committed as `ffba2b3 feat: add public item page media and transcript parser`.
- The public item page resolves only published groups, published items, and items with at least one published effective/main transcription.
- The item page preserves Prompt 11R custom Livewire + Blade homepage/search behavior and Prompt 11B contributor discovery routes, author/contributor links, and `top_transcribers` sections.
- The item page shows day-first dates, duration, categories, enabled content tags, author/contributor links, copy/share actions, safe description Markdown, and the transcript viewer in an RTL public layout.
- `resources/views/components/public/media-embed.blade.php` renders an iframe only for allowlisted HTTPS embed URLs, falls back to a valid HTTPS source link, never renders raw embed HTML, and shows provider/source metadata where available.
- `TranscriptSegmentParser` supports `[HH:MM:SS] Speaker: Transcript text` and `[HH:MM:SS] Speaker:\nTranscript text...`, returning seconds, timestamp, speaker, Markdown, and `t-{seconds}` anchors.
- `ContentItemTranscriptViewer` defaults to the effective transcription, exposes only published transcription tabs/selector choices, keeps selection URL-backed by transcription reference key, and falls back to safe Markdown when parsing finds no segments.
- Viewer controls are local Alpine preferences for show/hide timestamps and speakers; timestamp anchors are direction-safe with `dir="ltr"`.
- Prompt 12 did not add player sync, transcription studio, autosave, dashboard widgets, analytics, metadata extraction automation, import/export behavior changes, admin relationship UX changes, homepage/search rewrites, or contributor discovery changes.
- Prompt 13 dashboard metrics Phase 1 is complete (see Prompt Progress). Public Front v2 post-Step-10 mini-step sequencing is controlled by `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`; Step 10R-M6, Step 10R-UX1 through Step 10R-UX3, Step 10R-V1a through Step 10R-V1c, Step 10R-P1, Step 10R-S2, Step 10R-S2V, Step 10R-S1a through Step 10R-S1d, Step 10R-MP1, urgent Step 10R-HF2, Step 10R-HF3, and Importer Workbench WB1 are complete, Step 10R-C1 is superseded, AX1-AX3 are scheduled by the v4 plan, and the main queue resumes at P2 when Yoni chooses.

## Public Front v2 Planning Notes

- Public Front v2 research, blueprint, blueprint-result, and execution-plan docs exist.
- Public Front v2 Step 10 is complete. The operator chose dashboard metrics first; Prompt 13 Phase 1 is complete and phases 2-4 remain.
- The execution plan is an implementation guide, not a single prompt. Run one implementation prompt per step.
- Corrected step order:
  - Step 1: JSON Settings Architecture.
  - Step 2 / Reserved: Transcription Publication Policy, deferred unless explicitly promoted as an isolated prompt.
  - Step 3: Card Template Builder.
  - Step 4: Public Display Sections and Loopers.
  - Step 5: Latest and Search UX.
  - Step 6: Public Forms and Submissions.
  - Step 7: About Page Content and Team Builder.
  - Step 8: Podcasts and Groups UX.
  - Step 9: Public Menu and Header.
  - Step 10: Contributors and Top Transcribers UX.
  - Step 11: Seeders, Demo Data, Assets, and Cleanup.
  - Step 12: Prompt 13 Dashboard Metrics readiness / next decision.
- Step 1 created `docs/phase-02/public-front-v2-step1-json-settings-handoff.md` for ChatGPT/Yoni and established the final JSON settings API used by Step 3.
- Step 3 Card Template Builder is committed as `a0146ce`.
- Step 4 Public Display Sections and Loopers is committed as `c0ce7d7`.
- Step 5 Latest and Search UX is committed as `eea9164`.
- Step 6 Public Forms and Submissions is committed as `49f6ab0`.
- Step 7 About Page Content and Team Builder is committed as `b4fe4d5`.
- Step 8 Podcasts and Groups UX is committed as `f3d137e`.
- Step 9 Public Menu/Header and UX Fixes is committed as `5cf3363`.
- Step 9R Menu/Header UX Fixes is committed as `bfcda46`.
- Step 9R Podcast Episode Grid Settings follow-up is implemented and committed as `af23555`.
- Step 10 Contributors and Top Transcribers UX is implemented and committed as `37ce738`.
- Post-Step-10 public label/header polish is committed through `cea4f60`.
- Future Step 9F/10F Footer + Rich Section Builder foundation remains planned after all prior Step 10R work, including P1-P3, AX1-AX3, SL1-SL4, B4, and C2, is complete. Step 10R-S1b, urgent Step 10R-HF2, Step 10R-HF3, selected Step 10R-UX3, Step 10R-S1c, Step 10R-MP1, Step 10R-S1d, and Importer Workbench WB1 are complete; WB2 remains a side-track choice and the main queue position remains P2.
- The PodText logo already exists at `public/images/podtext-logo.jpg` and must be preserved by future public-front work.

## Public Front v2 Step 9 Public Menu/Header and UX Fixes Notes

- Step 9 reorganizes the `PublicContentSettings` admin page into major tabs with full-width collapsible sections for homepage/sections, general/display, menu/header, podcasts, about, forms, and advanced/diagnostics.
- Step 9 extends `menu_config` as a JSON-settings-powered public header/menu source and renders it through `App\Livewire\Public\PublicHeader` in the public panel. Default items include Home, Podcasts, About, request-transcription form, volunteer/register-transcriber form, and a theme selector.
- Public form action menu items use Step 6 `PublicFormModal` and the `open-public-form` browser event. Disabled/missing form targets are skipped server-side.
- The header uses the existing `public/images/podtext-logo.jpg` baseline and does not create `PublicMenu`, `PublicMenuItem`, or settings-only menu models.
- About/team profile cards now render uploaded images reliably and support safe semantic settings under `about_page.settings.team_card` for image visibility/size, grid/list layout, density, title/description visibility, and description line clamp.
- About Markdown/RichEditor/content-block output now has explicit H1-H6 public typography classes and keeps the existing safe renderer path.
- Contributor directory compact cards now show only contributor name plus public count badge; they select a Livewire-owned preview row. The preview contains the contributor page link and searchable related public items. Page sizes are 10, 15, and 20, with A-Z/Z-A/count-down/count-up sort toggles.
- Homepage default section mode suppresses top discovery chrome, page intro clutter, and the global search/filter panel while preserving `/search` behavior.
- Latest section headers now place section title, lightweight search, next/previous controls, and show-all action in one responsive header row.
- Step 9 adds a minimal safe `content_block` homepage section source using safe Markdown body, semantic style, and optional route/form action fields. This is not a CMS conversion.
- Step 9 handoff:
  - `docs/phase-02/public-front-v2-step9-public-menu-header-ux-fixes-handoff.md`
- Step 2 transcription policy remains deferred/reserved.
- Prompt 13 has not started.

## Public Front v2 Step 9R Menu/Header UX Fixes Notes

- Step 9R verified Step 8 and Step 9 plans against the current repository and recorded the verification matrix in `docs/phase-02/public-front-v2-step9r-verification-and-fixes-plan.md`.
- Step 9R added durable FilamentExamples MCP research discipline to `AGENTS.md`, `.ai/guidelines/tooling-quality.md`, the agent usage index, tooling quality docs, and AI development lessons. Research was recorded in `docs/research/public-front-v2/13-step9r-menu-header-ux-fixes-mcp-research.md`.
- Homepage root now stays in homepage-section mode even with query parameters such as `/?sort=latest_transcription`; `/search` keeps the full discovery chrome and filters.
- Public page classes with their own public H1s use an empty public page header override to avoid redundant fixed Filament page titles.
- `menu_config` now supports safe logo settings, header global search, desktop item alignment, and separate theme selector display modes while keeping form actions wired to Step 6 `PublicFormModal` and `open-public-form`.
- Public item cards now support safe image fit/radius settings, fall back from item thumbnail to group cover image, and keep group badge/title composition semantic. The JPG logo baseline at `public/images/podtext-logo.jpg` remains the default header fallback.
- About cards and image blocks now support safe image fit/radius markers, and H1-H6 public Markdown heading classes are covered by tests.
- Contributor directory follow-up work remains limited to Step 9 repairs: preview related items render as cards/grid and preview state remains Livewire-owned. Full contributor/top-transcriber redesign remains Step 10.
- Podcast detail pages now have JSON-first episode grid settings under `public_content.podcasts_page.group_page`, including card/list layout, desktop columns, gap, page-size options, search/sort/category/per-page control toggles, allowed/default sorts, and episode card display tokens. `ContentItemBrowser` owns the URL-backed state and public rendering remains custom Livewire + Blade.
- The future footer/rich-section-builder scope split is documented in `docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`; no `FooterSection`, `PublicFooter`, CMS page, `Podcast`, or `Episode` model was created.
- Step 9R settings migrations `2026_07_06_000002_add_public_step9r_card_settings`, `2026_07_06_000003_normalize_public_step9r_json_defaults`, and `2026_07_06_000004_ensure_public_step9r_about_team_card_defaults` were run locally.
- Step 9R follow-up settings migration `2026_07_06_000005_add_podcast_episode_grid_settings` was run locally.
- Step 2 transcription policy remains deferred/reserved.
- Prompt 13 has not started.

## Public Front v2 Step 1 JSON Settings Architecture Notes

- Step 1 adds public-front array settings to `App\Settings\PublicContentSettings`:
  - `card_templates`
  - `menu_config`
  - `about_page`
  - `public_forms`
  - `route_labels`
  - `display_defaults`
- Step 1 adds a Spatie settings migration: `database/settings/2026_07_04_000000_add_public_front_json_settings.php`.
- Step 1 intentionally does not add `transcription_policy`; Step 2 remains deferred/reserved.
- Step 1 intentionally did not add `homepage_sections` JSON columns; Step 4 now adds the deferred `source_config`, `selection_config`, `display_config`, and `pagination_config` columns.
- `PublicFrontConfigReader` is the runtime entry point for normalized config. Future public rendering should call `read()`, `all()`, or `group()` rather than reading raw settings arrays.
- `PublicFrontConfigValidator` normalizes arrays, merges defaults, reports invalid config, and rejects unknown keys plus unsafe HTML, iframe/script strings, JavaScript URLs, non-HTTPS external URLs, raw CSS/Tailwind-looking values, SQL-looking values, PHP class names, and Blade path-looking strings.
- `PublicFrontConfigResult` returns normalized config and safe invalid-config report objects.
- The existing `PublicContentSettings` admin page now fills missing public-front defaults and sanitizes public-front arrays before save.
- Existing `PublicContentCardOptions` behavior is unchanged and remains compatible with the older scalar card settings.
- No settings-only models were introduced.
- No Prompt 13 work started.

## Public Front v2 Step 3 Card Template Builder Notes

- Step 3 stores card templates in the existing `public_content.card_templates` array setting as a flat JSON-first list. It does not create `CardTemplate`, `CardTemplatePart`, `CardFamily`, `PublicDisplaySection`, or `PublicLooper` models.
- Card template runtime reads continue through Step 1 APIs: `PublicFrontConfigReader::read()`, `PublicFrontConfigResult::config()`, `PublicFrontConfigResult::group('card_templates')`, and `PublicFrontConfigResult::invalidConfigArray()`.
- `PublicFrontConfigRegistry` now defines safe families, part types, sources, attributes, and default templates for `content_item`, `content_group`, and `contributor`.
- Supported families are `content_item`, `content_group`, and `contributor`; supported layout variants remain semantic (`cards`, `rows`), with semantic density, image size, title size, part layout, icon, URL target, line clamp, and font-size options.
- Supported part types include `image`, `title`, `description`, `metadata_row`, `entity_attribute`, `group_identity`, `transcriber_line`, `date_read_time`, `taxonomy`, `custom_text`, `action_link`, `divider`, and `spacer`.
- The validator accepts normalized JSON and Filament Builder-shaped part payloads, then normalizes parts to plain JSON arrays. Unknown or unsafe families, part types, sources, attributes, icons, layout values, CSS/Tailwind-looking values, Blade/PHP-looking strings, JavaScript URLs, and HTML/script/iframe strings are reported through invalid config and skipped or defaulted safely.
- `App\Filament\Pages\PublicContentSettings` now includes a card template editing section with a Repeater and Builder-backed parts editor. Live side-by-side preview remains deferred to later public UX work.
- Public item, group, and contributor cards preserve existing Blade output and expose compatibility metadata through `data-card-template-*` attributes resolved from the card template support layer.
- Step 3 does not implement display-section/looper queries, latest/search redesign, public forms, about/team builder, podcasts/group UX changes, menu/header management, contributor UX refinements, seeders, dashboard metrics, or the deferred transcription publication policy.
- Step 4 Public Display Sections and Loopers is committed as `c0ce7d7`. Step 5 Latest and Search UX is committed as `eea9164`. Step 6 Public Forms and Submissions is committed as `49f6ab0`. Step 2 transcription policy remains deferred/reserved. Prompt 13 has not started.

## Public Front v2 Step 4 Display Sections and Loopers Notes

- Step 4 extends the existing `HomepageSection` model; it does not create `PublicDisplaySection`, `PublicLooper`, or other settings-only models.
- The new reversible migration `2026_07_04_221810_add_public_front_config_to_homepage_sections_table.php` adds nullable JSON columns to `homepage_sections`: `source_config`, `selection_config`, `display_config`, and `pagination_config`.
- `HomepageSection` now casts the new JSON columns to arrays, mirrors empty-array defaults in model attributes, and exposes safe helper methods: `sourceConfig()`, `selectionConfig()`, `displayConfig()`, and `paginationConfig()`.
- Step 4 support classes under `App\Support\PublicFront\Sections` normalize section JSON config, report invalid config, resolve public-safe source queries, and build view-ready section results.
- Supported source types are `latest_content_items`, `category_content_items`, `tag_content_items`, `content_group_items`, `manual_content_items`, `content_groups`, `categories`, `contributors`, and `top_transcribers`.
- `curated_query` remains deferred and invalid/unknown section source config is reported and skipped safely during public rendering.
- Public homepage rendering now delegates visible ordered homepage sections through the Step 4 resolver while preserving Prompt 11R custom Livewire + Blade output and legacy `data-section-type` markers for existing section types.
- Public content item sources continue to enforce published group, published item, and published effective/main transcription constraints through the shared public query path.
- Tag sources require enabled `content` tags. Category sources can include descendants and inherited group categories. Manual source include/exclude IDs are database IDs and still recheck public visibility before rendering.
- Content group/category/contributor sources render through existing public Blade components or simple safe category cards; top-transcribers counting behavior remains the existing public contributor discovery behavior.
- Display config composes with the Step 3 `PublicFrontCardTemplateResolver` and compatibility renderer attributes. Step 4 resolves templates and safe semantic overrides; Step 5 adds the practical controlled content-item renderer.
- `HomepageSectionForm` keeps legacy type-driven fields and adds semantic fields for source, selection, display/template, and pagination config. It does not expose raw JSON, raw CSS/classes, arbitrary Blade paths, raw SQL, or arbitrary PHP classes.
- Pagination config stores and normalizes `none`, `simple`, `load_more`, and `next_previous`; infinite scroll remains deferred.
- The handoff file for review is `docs/phase-02/public-front-v2-step4-display-sections-loopers-handoff.md`.
- Step 5 Latest and Search UX is committed as `eea9164`. Step 6 Public Forms and Submissions is committed as `49f6ab0`. Step 7 About Page Content and Team Builder is committed as `b4fe4d5`. Step 2 transcription policy remains deferred/reserved. Prompt 13 has not started.

## Public Front v2 Step 5 Latest and Search UX Notes

- Step 5 makes Latest a looper-driven public section using `PublicDisplaySectionResolver` output and normalized `source_config`, `display_config`, and `pagination_config`.
- Latest sections now support lightweight search, top previous/next controls for `simple` and `next_previous`, and bottom load-more for `load_more`.
- Latest page size normalizes to 4 through 25, and Latest total query size normalizes to at least 50.
- The public search page keeps search and sort visible while moving filters into a custom Blade/Alpine drawer controlled by `resources/views/components/public/public-filter-panel.blade.php`.
- Livewire owns search/filter/sort state; Alpine owns only drawer open/close behavior.
- Category and tag filters now support multi-select toggle buttons/chips with URL-backed CSV state. Disabled tags remain hidden publicly.
- Public content item cards now use the practical controlled renderer `PublicFrontCardTemplateRenderer::contentItemPresentation()` for deterministic card classes, safe line clamps, square image handling, large-image stacking, and `min-w-0` text columns.
- Step 5 did not implement full admin card-template preview; the later focused
  Step 5B preview and UX2 editor closure are now complete. Family, finite
  rendered presentation Selects, authoritative inline parts, native modal
  moves, and applied native slide-over part edits refresh automatically;
  key/label remain identity-only and focused save derives part order from
  Builder position without changing global import/restore compatibility.
- The Step 5 handoff file for review is `docs/phase-02/public-front-v2-step5-latest-search-ux-handoff.md`.

## Public Front v2 Step 6 Public Forms and Submissions Notes

- Step 6 stores public form definitions in the existing `public_content.public_forms` JSON setting under the canonical `public_forms.definitions` shape.
- The Step 1 reader/validator remains the runtime API. Public code should use `PublicFrontConfigReader::read()->group('public_forms')`, not raw settings arrays.
- `PublicFrontConfigValidator` validates and normalizes form keys, names, headings, descriptions, submit/success labels, display modes, fields, options, validation semantics, and rate-limit settings.
- Supported v1 field types are `text`, `email`, `phone`, `textarea`, `select`, `checkbox`, `toggle`, and `url`.
- The transactional submission table/model/resource is `PublicFormSubmission`; no settings-only `PublicFormDefinition` model/table exists.
- `PublicFormSubmissionStatus` values are `new`, `reviewed`, and `archived`.
- `App\Livewire\Public\PublicFormModal` renders enabled forms and owns form state, validation, honeypot, rate limiting, submission, and success/error messages.
- Alpine owns only modal/slide-over open and close state. Public forms remain separate from Step 5 search/filter drawer state.
- The admin `PublicContentSettings` page now includes a safe JSON-first public form definition builder.
- The admin `PublicFormSubmissionResource` lists submissions, filters by status, searches form key/name snapshot, safely renders payload summaries, and supports mark reviewed/archive/reopen actions.
- V1 public forms include honeypot protection and Laravel-native rate limiting before live enablement.
- Email notifications, file uploads, and CAPTCHA package integration remain deferred. Public menu/header integration is implemented in Step 9.
- Step 7 About Page Content and Team Builder is committed as `b4fe4d5`; Step 8 is committed as `f3d137e`; Step 9 is committed as `5cf3363`; Step 9R is committed as `bfcda46`; the Step 9R Podcast Episode Grid Settings follow-up is committed as `af23555`; Step 10 is committed as `37ce738`; post-Step-10 public label/header polish is committed through `cea4f60`. Step 2 transcription policy remains deferred/reserved. Prompt 13 has not started.
- The Step 6 handoff file for review is `docs/phase-02/public-front-v2-step6-public-forms-submissions-handoff.md`.

## Post-Prompt-10 Guidance Sync Notes

- Active prompt workflow guidance now records the requirement to run preflight, read the blueprint/spec stack, stop on conflicts, and classify blueprint completion in final reports.
- Successful implementation prompts must update relevant active Markdown state files before the final commit, not only code and tests.
- Prompt 11 started from the Prompt 10 import/export baseline and did not modify import/export behavior.
- This guidance sync changed Markdown only and did not start Prompt 11; Prompt 11 was implemented later.

## Baseline Issue To Record

Resolved 2026-07-30. `php artisan model:show App\Models\ContentItem` and
`php artisan model:show App\Models\ContentGroup` previously failed with a class
redeclare fatal, and that record stood unretested for months. Both commands were
rerun during the post-Storage-Truth sweep and now exit 0, so the avoidance note is retired
and `model:show` is safe to use again.

## E5 Enum-Typed Settings Properties

- The four enum-backed `AdminUxSettings` properties are typed as their enums
  rather than `string`: `media_naming_strategy`,
  `media_acquisition_filename_strategy`, `transcription_presentation_mode`,
  `transcription_mode`. All four Filament call sites now use
  `->options(Enum::class)`.
- `PublicContentSettings` was audited and deliberately left unchanged. None of
  its ten string properties map to an `App\Enums\*` case; their finite value sets
  live as private const arrays in `PublicContentCardOptions`.
- `database/settings/2026_08_01_000000_type_admin_ux_enum_settings.php` repairs
  stored payloads that match no case. It is required, not cosmetic: Spatie's
  `EnumCast::get()` resolves through `from()`, so an unrecognised value throws a
  `ValueError` on every request that loads the group.
- A repair migration for an enum-typed setting must go through the settings
  repository, not `$this->migrator->update()` —
  `SettingsMigrator::getPropertyPayload()` applies the cast while reading and so
  throws on precisely the values being repaired.
- The read sites that would have failed *silently* are covered by
  mutation-checked tests in `tests/Feature/AdminUxSettingsEnumTypesTest.php`:
  `MultiTranscriptionSurfaces::isMultiMode()`,
  `ContentImageActions::defaultEgressNamingStrategy()`, the workspace
  presentation-mode branch, and the `transcription_mode` visibility closure on
  the settings page.
- `EpisodeWorkspaceForm`'s transcription section gained
  `->key('workspaceTranscriptionSection')` so the collapsible branch is
  assertable. This re-keys the section's children, so component-key assertions
  use `workspaceTranscriptionSection.<field>`; state paths
  (`data.workspaceTranscription.<field>`) are unaffected.
- `tb1_picker_container` was removed outright. It configured a modal-versus-
  slide-over choice for the TB1 table image picker; mini-task 3A (`6da7fda`)
  replaced that action with one canonical modal and kept the setting as inert
  data. The retained row was still required, because the property had no
  default, so losing it fatally broke the whole `admin_ux` group over a value
  nothing read. Property, enum, six translation keys and the row are gone via
  `2026_08_01_000001_drop_admin_ux_tb1_picker_container.php`. The historical
  seed in `2026_07_12_000001` is intentionally untouched.
- `media_naming_strategy`, `show_episode_workspace_hint_line` and
  `show_episode_workspace_language_code` now carry defaults matching their seed
  migrations. A settings property with no default is a required database row: a
  missing row throws `MissingSettings` for the whole group. A default softens
  that but does not remove it — `ensureNoMissingSettings` folds
  `getDefaultValueLoadedProperties()` into the saving check, so a default-loaded
  property still throws on save.
- `tests/Feature/SettingsRowInvariantTest.php` enforces the invariant for both
  settings classes: every declared property has a seeded row, no row outlives
  its property, and each group loads and saves as migrated. Mutation-checked in
  both directions. This moves a missing-row discovery from production to CI; the
  realistic trigger is adding a property and forgetting its seed migration.
- `ContentImageActions::defaultEgressNamingStrategy()` now calls `report()`
  before its fallback. The bare `catch (\Throwable)` there is what hid the
  original E5 type mismatch. Its `media_naming_strategy` read only pre-selects a
  visible, required Select in the export modal — the job receives whatever the
  operator selected, so a wrong default is never silent in the export itself.
- Deleting a class file requires `composer dump-autoload`; the optimised
  classmap otherwise still points at the removed file.

## M2 Media Picker Duplicate-Root Fix

- The `Multiple elements found for partial [action-modals.N]` defect (M2) is
  closed. Root cause: a picker remount whose request still carried the child's
  key in the parent's Livewire memo — the server skips such a child and ships a
  snapshot-less stub, and Filament's `partials.js` grafts it as an
  uninitialised `cloneNode` copy. Settled cycles self-heal because removing the
  child's DOM also removes it from the client-side memo; the production
  failure was the race where close and reopen batch into one Livewire message
  (`unmountAction` + `mountAction`), which is why it appeared in ~13% of
  browser runs and no wait could absorb it.
- Fix: per-mount workspace keys. `PathCuratorPicker::getPickerAction()` mints
  `media_picker_mount_nonce` (`fillForm` + `Hidden`) and appends it to the
  `Livewire` schema component key via a `Get $get` closure;
  `ContentImageActions::imagePickerAction()` does the same with
  `owner_image_workspace_nonce` for the inline owner workspace. A remount can
  never match a stale memo entry, so no stub and no clone, at any click speed.
  Within one mount session the key is stable, so sibling-action cycles (details
  slide-over over the open workspace) keep Filament's designed
  state-preserving clone-heal.
- `tests/Browser/MediaPickerCloneReproBrowserTest.php` is the regression
  suite: it intercepts Livewire messages to detect snapshot-less stubs in
  partials, scans the DOM after every step for duplicate `wire:id`s and roots
  missing `__livewire`, drives the deterministic batched-message race in both
  the relation-manager edit-modal geometry and the owner-image modal, and
  asserts the workspace key changes per mount. 10/10 suite loops green
  post-fix; the suite was red on all three contracts pre-fix.
- Upstream status (checked 2026-08-01): unfixed and unreported through
  Filament 5.7.5/5.x HEAD and Livewire 4.3.4/4.x HEAD; the clone block came
  from filamentphp/filament PR #19242. An upstream report with the
  batched-message repro is worth filing. Full record:
  `docs/research/media-picker-m2-cross-session-brief.md` (closure record) and
  the M2 entry in `docs/phase-02/dashboard-metrics-phase-2R-handoff.md`.

## Admin Table Query-String Key Namespacing

- The "admin table components claim bare URL query-string keys" family is
  closed app-wide by a central convention, superseding the parked
  per-page-string draft in worktree `.claude/worktrees/upbeat-ramanujan-61c4d4`.
- Verified Filament 5.7.5 mechanics (vendor source, not folklore): outside
  Resource `ListRecords`, table `filters`/`search`/`sort` are **not**
  URL-bound at all — the only bare URL key a table component claims is its
  Livewire paginator (`page`), registered lazily by
  `SupportPagination::ensurePaginatorIsInitialized()` and named by
  `getTablePaginationPageName()`. `ListRecords` binds bare
  `filters`/`search`/`sort`/`grouping`/`reordering`/`tab` via static `#[Url]`
  attributes that a `queryStringIdentifier()` cannot rename; relation
  managers already derive `lcfirst(class_basename)` identifiers in
  `InteractsWithRelationshipTable::makeTable()`; even a `->paginated(false)`
  custom-data table (Card Template library) initializes a dormant paginator
  and holds the bare `page` binding.
- The convention: the admin-scoped `Table::configureUsing()` block in
  `AppServiceProvider` now sets
  `queryStringIdentifier(Str::lcfirst(class_basename($livewire)))` for every
  admin table whose component is not a `ListRecords` page, mirroring
  Filament's own relation-manager derivation. Explicit
  `->queryStringIdentifier()` calls in a component's `table()` still win
  (they run after the hook). `ListRecords` pages keep Filament's bare keys on
  purpose: single-table screens, bookmark churn for zero benefit, and
  `ListMedia::mount()` reads the bare `page` request parameter.
- Effect: `BlockersQueueWidget` retrofitted off its bespoke
  `'blockersQueue'` string onto the derived `blockersQueueWidget`
  (`blockersQueueWidgetPage` URL key); `ImporterSettings` pagination now
  reads/writes `importerSettingsPage` and ignores bare `page`;
  `CardTemplateSettings`' dormant paginator moved off the bare `page`
  binding; every future admin table widget or custom page is namespaced by
  construction.
- `tests/Feature/AdminTableQueryStringKeysTest.php` pins the behavior:
  own-key-read vs bare-key-ignored pagination seeding for the importer page
  and the widget, the widget staying off the Dashboard's `#[Url] $filters`
  key, the dormant Card-Template binding, the `ListRecords` bare-key
  exclusion (`ListAuthors` honors `?page=2`), the vendor-derived relation
  manager identifier, and a sweep over `app/Filament` asserting every
  concrete non-`ListRecords`/non-relation-manager `HasTable` component gets
  a distinct non-null identifier. `DashboardOverviewLensTest` updated to the
  convention values.
- Gate 2026-08-02: pest 1542 passed (19315 assertions), pint clean, full
  filacheck 0 issues, `npm run build` green. Research recorded in
  `docs/research/filament-examples-phase-02.md` ("Admin Table URL-Key
  Research", 2026-08-02): the FilamentExamples corpus has no precedent for
  `queryStringIdentifier`/`Table::configureUsing`/`WithoutUrlPagination`;
  decision rests on vendor source and official docs.

## Public Form Modal Duplicate-Mount Dedupe

- Defect: `PublicFormModal` is mounted per form key by three independent
  parents — header `form_mounts`, about-page `formCtas`, and homepage
  content-block sections. Each parent dedupes internally
  (`->unique('form_key')`), but nothing deduped across parents, and every
  mounted instance listened for `open-public-form` and opened on a matching
  key: the same form mounted twice (header + homepage section, header +
  about CTA) produced two stacked dialogs/backdrops/focus traps from one
  click. The listener's `! $event.detail?.formKey ||` fallback additionally
  opened every mounted modal for a key-less event; all dispatchers pass
  `formKey`, so the fallback was dead code that only enabled the failure.
- Fix (client-side dedupe): the listener in
  `resources/views/livewire/public/public-form-modal.blade.php` now requires
  `$event.detail?.formKey === <key>` and claims the event by stamping
  `publicFormClaimed` on the `CustomEvent`. Window listeners for one event
  run synchronously in DOM order, so exactly one matching instance opens per
  event; the claim is per-event (not sticky), so close/reopen cycles keep
  working and whichever duplicate instances remain after partial re-renders
  still respond. Key-less events now open nothing.
- Rejected alternative: page-level single mounting through a shared
  registry/render hook. The three parents re-render in independent Livewire
  update requests, so a request-scoped mount registry cannot decide
  consistently across partial re-renders (the M2 stale-child class), and it
  would move modal ownership onto a new page-level surface to solve a
  client-side listener defect.
- Tests: `tests/Browser/PublicFormModalBrowserTest.php` seeds the real
  duplicate-mount homepage (menu form action + content-block section button
  for the same enabled form) and pins: two mounted roots yield one visible
  dialog per click; key-less and foreign-key dispatches open nothing;
  a matching dispatch opens exactly one dialog; the claim resets across
  close/reopen cycles.
- Gate 2026-08-02: pest 1544 passed (19323 assertions), pint clean, full
  filacheck 0 issues, `npm run build` green. FilamentExamples research
  recorded in `docs/research/filament-examples-phase-02.md` ("Public Form
  Modal Duplicate-Mount Research", 2026-08-02): the corpus has no
  duplicate-mount/window-event precedent; only `search-examples` was
  available; the decision rests on Livewire 4 event docs and the app-owned
  component.| Prompt 13 dashboard metrics | Phases 1-2 + 2R + the full 2026-08-03 orchestrated route complete; route-end push pending operator word; phases 3-4 planned-ready | Mid-route push `987b92f` (release `74621206`); route blocks all orchestrator-verified: V `0e80c84`/`bf0c063`, F `b24490a`/`b3d6de4`, A `2831ee9`…`1efeb35`, B1 `168a618`/`87aa8bf`, fix-batch+hardening `abd46f3`…`f494f0a`/`7768442`/`5da7acc`/`ce95a35` (Q7 inversion riding `11afc21` — see handoff provenance note), scan-scope fix `12285a7`/`ecc9eda`. Authoritative: `dashboard-metrics-phase-2R-handoff.md`; ledger+checklist `docs/research/defect-cause-patterns.md`; principles `dashboard-widget-principles.md` + `dashboard-governance-principles.md` | The route verified the stack, gave formats a home behind a statement-scanned guard, fixed the sparkline/empty-state/doorway layer with an Alpine hover, hardened filters/queries/authz from research findings (incl. the global preload-default inversion and the super-admin backups policy), closed two live public styling gaps (prose contract + badge maps) behind a discovery guard + on-demand sentinels, co-created 14 widget + 7 governance principles, recovered the lost dossier principles (ES-1–ES-7), and reconciled the phase-3 plan to implementable. Remaining: route-end push (on operator word, after this fold), then phase-3 implementation from the reconciled plan and phase-4 evidence. |

## Hebrew Search Folding

- Scope delivered: everything, admin included. A search for `שלום` now finds
  text stored as `שָׁלוֹם`, and the reverse — a pointed term finds unpointed
  text — which is the same defect seen from the other side and applies to
  every field regardless of what is stored.
- Design: folded `*_search` shadow columns written by an overridden
  `setAttribute()`, searched with a plain portable `LIKE`. SQLite executes the
  predicate that ships, so the suite proves the shipped thing. The
  driver-branched `LOCATE`/`COLLATE` alternative stays disqualified for the
  reason in `hebrew-search-folding-spec.md` §1.
- `App\Support\Search\HebrewSearchFold::fold()` is the one normalizer:
  lowercase + strip `\p{Mn}`. `HebrewSlugger` now delegates to it rather than
  repeating the same two lines.
- `App\Support\Search\FoldedSearch` is the driver-agnostic seam (`pattern()`,
  `column()`, `contains()`); `App\Filament\Support\FoldedTableSearch::query()`
  is the Filament closure factory passed to `->searchable(query: …)`.
- Shadow columns cover 17 searched free-text columns across 12 models.
  Deliberately excluded, each for a measured reason: `transcript_markdown`
  (no code path searches it — zero LIKE predicates, zero `->searchable()`
  columns, so a shadow would duplicate the largest column in the schema to
  serve no query); slug columns (the slugger already folds, so a slug is its
  own fold — asserted in `HebrewSluggerTest`); reference keys, language codes,
  providers, URLs and e-mail (ASCII); and Filament's vendor-owned `Import`
  model.
- Write seam is `setAttribute()`, not model events: `Model::fill()` routes
  through it and `forceFill()` delegates to `fill()`, so the five live
  `saveQuietly()` writes in `app/` are covered, as are Filament importers
  (`ImportColumn::fillRecord()` → `data_set()` → `__set()`), proven against a
  real import rather than inferred.
- `php artisan search:backfill-folds` populates and repairs shadows: chunked
  via `eachById`, `withoutTimestamps` so a backfill cannot read as an edit,
  and free to re-run (a row whose shadow already matches leaves nothing dirty,
  so `save()` issues no statement).
- **DEPLOY ORDERING — search is dead between the two steps.** The migration
  adds the shadow columns NULL, and every search predicate now compares against
  a shadow. So from the moment `migrate` finishes until `search:backfill-folds`
  finishes, **every pre-existing row is invisible to every search** — public
  homepage, episode browser, podcast listing, contributors, media library and
  the whole admin panel. Rows written after the migration are fine; the
  `setAttribute()` hook fills their shadows on write. There is no repo-level
  deploy script (Forge owns it), so this is an operator action:

  ```
  php artisan migrate --force && php artisan search:backfill-folds
  ```

  Run them as one step. The backfill is re-runnable, so running it again later
  is free and repairs any drift. Nothing about this is reversible-by-waiting —
  the window stays open until the backfill runs.
- Admin uses a `Column::macro('foldedSearchable')` delegating to
  `FoldedTableSearch::query()`, applied at 28 call sites. FilaCheck's
  `table-without-searchable-columns` cannot see it — the rule decides with
  `preg_match('/->searchable\s*\(/', $snippet)`, so any macro name reads as no
  searchable column — and it reported six genuinely searchable tables as
  having none. **Operator rule (2026-08-06): FilaCheck is not the authority on
  Filament. Deprecations and real errors get fixed; UX suggestions do not get
  to change a good solution.** The rule is disabled in a partial
  `config/filacheck-pro.php` with the reason at the disable site, and its
  signal is replaced macro-aware by
  `tests/Feature/AdminTableSearchabilityTest.php`.
- Indexes: varchar shadows are indexed, longtext shadows are not — MySQL
  cannot index a LONGTEXT without a key prefix length, and expressing one
  means the per-driver schema branching §1 disqualifies. Note a B-tree index
  cannot serve the leading-wildcard `LIKE` that ships either way (measured:
  `EXPLAIN SELECT *` on a leading-wildcard `LIKE` gives `type: ALL`, no key);
  they earn their keep on the backfill's `IS NULL` sweep and future
  equality/prefix work. FULLTEXT remains out of scope per §5.
- Live defect closed: `content_items` id 56 carries a U+05BF RAFE in
  `description_markdown` (`…D794 D6BF 21`). Measured before the change,
  `description_markdown LIKE '%זה למה!%'` returned 0 while
  `LOCATE(…)` returned 1 and the folded predicate returned 1.
  `HebrewSearchFoldingPathsTest` reproduces the exact bytes and pins that the
  phrase is now findable as it renders.
- Niqqud census on the dev database: exactly one row in the whole schema
  carries a Hebrew combining mark (that one). Niqqud here is accidental, not
  systematic — which is why the pointed-search-finds-unpointed-text direction
  carries most of the value.
- Tests: `tests/Unit/HebrewSearchFoldTest.php`,
  `tests/Feature/HebrewSearchFoldingTest.php`,
  `HebrewSearchFoldingPathsTest.php`, `HebrewSearchFoldingAdminTest.php`,
  `BackfillSearchFoldsCommandTest.php`. Regression canaries in
  `AppOwnedMediaResourceTest` and `AppOwnedMediaPickerTest` stay green.
- Gate 2026-08-06: pest 1844 passed (20557 assertions), pint clean, filacheck
  35/35 rules passed, `npm run build` green.
- `composer types:check` reads 652, up from 623 before the macro was restored.
  All 28 of the difference are `Call to an undefined method …::foldedSearchable()`.
  PHPStan cannot see Filament macros at all, which is why the two pre-existing
  macros in `AppServiceProvider` already carry 12 errors of the same kind
  (`multiTranscription` 10, `superAdminOnly` 2).
- Why the usual remedy does not apply: larastan's
  `MacroMethodsClassReflectionExtension` gates on
  `Illuminate\Support\Traits\Macroable` (`:18`, `:79`), and Filament components
  use `Filament\Support\Concerns\Macroable` — a different trait, verified with
  `class_uses_recursive(TextColumn::class)`. No branch matches, so booting the
  registering provider (the advice in larastan discussion #1737) changes
  nothing. The shapes differ too: Laravel's `$macros` is `name => Closure`,
  Filament's is `name => [class-string => Closure]`.
- The fix is a `stubFiles` entry, which teaches rather than suppresses and so
  does not conflict with the file's "NO BASELINE / `ignoreErrors` stays empty"
  policy. **The form matters, both measured:** an `@method` PHPDoc on a stubbed
  class body works and is inherited by subclasses (a stub on `Column` reaches
  `TextColumn`); a real method signature in the stub does **not**, on either
  class. One stub covering `Column`, `Filament\Schemas\Components\Component`
  and `Filament\Actions\Action` takes the repo 652 → **614**, clearing all 40
  macro errors including the 12 that predate this work. Left to whoever owns
  `phpstan.neon` — a concurrent session had it open — and sent to that session.
  `types:check` is deliberately not in the gate.

## larastan Cast Resolution

- Root cause of the "every enum comparison is always false" reports found and
  fixed: larastan's `parseModelCastsMethod`, which ships `false`. Laravel merges
  `casts()` into the cast map in `HasAttributes::initializeHasAttributes()` — a
  trait initializer, so it runs only from `Model::__construct()`. larastan
  builds models with `newInstanceWithoutConstructor()`, so the initializer never
  fires and `getCasts()` sees only the empty `$casts` property. With the flag
  off larastan falls back to the *declared* return type of `casts()` — plain
  `array`, not a constant array — and skips the merge silently.
- Measured, level 5: `composer types:check` reads **507**, down from 614. Cold
  run 20.4s → 21.5s, so the documented cost of the flag is ~5% here.
- The failure was invisible because larastan falls back to the migration column
  type, not to `mixed`. `integer` and `boolean` casts therefore looked correct
  and only `datetime`, `array` and enum casts diverged — a partial pattern that
  reads as an enum- or Filament-specific problem and is neither.
- The whole 107-error difference was false positives: all `match.alwaysFalse`,
  `method.nonObject`, `function.impossibleType`, `booleanAnd.alwaysFalse` and
  `instanceof.alwaysFalse`, plus 7 of 11 `deadCode.unreachable`. That last
  family is the reason this mattered more than wrong types — a cast attribute
  believed to be a string makes guard clauses always-terminate, so PHPStan
  marked the code after them unreachable and stopped analysing it.
- Refuted, with evidence: larastan's bootstrap is **not** failing under
  Filament. The column types it did produce prove the container booted and the
  migration scan ran, and `bootstrap.php:40-51` exits 1 loudly on any throwable.
- Corrected: `match.unhandled` was **already firing** on
  `MediaFilesystemMutationCoordinator.php:1540` before this change — that match
  subject comes from `MediaMutationOperationType::tryFrom(...)`, a native enum,
  so the cast defect never reached it. The earlier "it never fires" reading came
  from PHPStan's agent error formatter truncating its list. Pass `-v` and check
  the `truncated` field before concluding a rule does not fire.
- Guard: `tests/Feature/LarastanCastResolutionGuardTest.php` runs the real
  binary against a `dumpType` probe and pins both halves — the flag resolves the
  enum/datetime/array casts, and with the flag forced off the hazard still
  reproduces. Mutation-checked by flipping `phpstan.neon`; 2 of 3 tests fail.
- Research written up in `docs/research/larastan-playbook.md`: config knobs and
  which matter, level progression, the three distinct Filament interactions
  (macros are out of scope upstream by maintainer policy, per larastan#1935),
  and the `dumpType` probe method that diagnoses this class of problem in one
  run. Upstream: larastan#2512 proposes flipping the default, #2509 is the same
  surprise reported as an enum bug.
- The remaining 507 are triaged by identifier in
  `docs/phase-02/open-findings-triage.md` §B4. Largest family is 171 errors of
  app typing debt at Filament's untyped `Model`/`Builder` boundary — a third of
  the total, and not a tool limitation. Only 10 of the 507 are genuine tool
  limitations. `types:check` stays out of the gate until the count is zero.

## Eloquent Relationship Generics

- 43 of the repo's 45 relationship methods carried no `@return` generic. larastan
  infers a relation's *kind* from the return type but not the *related model*, so
  `$item->transcriptions` was a collection of `Model` and everything reached
  through it was an error.
- Annotating all of them took `composer types:check` from **507 to 445, with
  zero new errors introduced**. The change is 51 lines of PHPDoc and nothing
  else, so it has no runtime surface at all.
- Guarded by `tests/Feature/EloquentRelationshipGenericsGuardTest.php`,
  mutation-checked by deleting one annotation. It asserts every relation carries
  a generic, so a relation added later without one fails a test instead of
  quietly giving back part of the win. Its count canary immediately earned its
  keep: PHP reflection reports a trait's methods as declared by the USING class,
  so Spatie `HasTags::tagsTranslated()` looked like ours. Filter relation
  discovery on `getFileName()`, not `getDeclaringClass()`. `property.notFound` 65→33, `argument.type` 58→44,
  `return.type` 27→20, `method.notFound` 129→121, `argument.unresolvableType` 1→0.
  Combined with the cast-flag fix above: 614 → 444, a 28% cut.
- `$this` on the declaring side is load-bearing — it preserves late-static model
  context so the type survives subclassing.
- Check generic arity against the installed framework rather than guessing:
  `HasMany`/`HasOne`/`BelongsTo`/`MorphTo` take two, `BelongsToMany`/`MorphToMany`
  take two plus defaulted pivot and accessor, `HasOneThrough` takes three.
- **`morphTo()` with no argument cannot be narrowed.** It returns
  `MorphTo<Model, $this>` and PHPStan checks the body against the tag, so
  annotating the union the morph map admits (`ContentGroup|ContentItem`) is a
  claim it rejects and produces a fresh `return.type` error.
  `MediaAttachment::attachable()` now carries a comment saying exactly this,
  because the instinct on a second pass is to re-narrow it and re-break it.
- Three annotations are UNVERIFIABLE by PHPStan and are pinned by test instead:
  `tags()`, `contentTags()` and `enabledContentTags()` call
  `morphToMany(self::getTagClassName(), ...)`, a dynamic class string, so the
  `ContentTag` claim is accepted unchecked. `config/tags.php:9` is the source of
  truth and the guard test asserts it, so editing that config now fails a test
  rather than silently invalidating three annotations.
- DELIBERATELY NOT DONE: `ContentImagesExportManager` guards a `foreach` with
  `if (! $item instanceof ContentItem) { continue; }`, which is now provably
  unreachable and reported as `instanceof.alwaysTrue` — the single error
  separating 445 from 444. Removing it is the only runtime-affecting edit in the
  vicinity, so it was split off as its own decision rather than shipped inside
  51 lines of comments. Still open.
- Rules taken from szepeviktor's `larastan-preflight-reviewer` skill (he is a
  larastan collaborator; the repo is unlicensed, so the rules were applied, not
  copied). Still unapplied and worth doing: `Attribute<TGet, TSet>` generics with
  `never` for the absent side, and `@mixin` on any `JsonResource`. Deliberately
  rejected: its literal array shapes on `casts()`, which duplicate every cast map
  into a docblock forever — `parseModelCastsMethod` replaces all of it.
- What this does NOT fix: errors whose `Model` originates from Filament's own
  contracts (`Exporter::$record`, `Resource::getEloquentQuery()`). That family is
  122 of the remaining 445 and needs narrowing at the Filament boundary instead.

## Laravel 13 Attribute Migration and Support Injection

- 2026-08-08, commits `cc5fc12`, `4c9605a`, `04801eb`, `141769f`. Source of
  truth for the target forms: vendor (`Model.php:1987` scope resolution,
  `Command.php:159` attribute config, `GuardsAttributes.php:49` fillable
  merge), cross-checked against the Mar 2026 LaravelDaily lessons in
  `docs/research/laraveldaily/`.
- All 21 local scopes are `#[Scope] protected` methods named after the scope —
  20 across eight models plus one in `InteractsWithPublicationDate`, which a
  `Models/*.php` glob missed (trait scopes exist; count your sweep). Call
  sites unchanged. PHPStan delta zero against the 445-error baseline.
- All 12 console commands carry `#[Signature]` / `#[Description]` class
  attributes instead of properties. Names, options and defaults verified
  unchanged via `artisan list --raw` and `--help`.
- `#[Fillable]` now covers 17 of 18 models. **`Media` is a deliberate hold,
  not a leftover**: the attribute MERGES into the inherited list
  (`GuardsAttributes::initializeGuardsAttributes`), so it cannot narrow
  Curator's 17-column parent `$fillable`; the property override is what keeps
  `path`/`disk`/`directory` out of mass assignment. The model carries a
  comment saying so.
- Seven `app(X::class)` sites in four Support classes became promoted
  constructor deps (`SettingsBackupManager`, `CardTemplatePreviewer`,
  `MediaFilesystemMutationCoordinator`, `PublicDisplaySectionResolver`).
  The remaining Support `app()` calls are deliberate: clearCache-then-resolve
  freshness rituals, `??=` late defaults, or static-only APIs (conversion of
  those is a registered follow-up, not drift).
- Architecture decision, twice-derived (this session and the LaravelDaily
  research independently): **no Actions/Services/Queries/Data extraction.**
  `app/Support/<Feature>` folders ARE the architecture; the 13 query classes
  and 8 Media value objects are feature-cohesive where they stand. Moving
  them would be churn.

## Eloquent Strict Mode and Verification-Code Pruning

- 2026-08-08, commits `f751455` and the pruning commit following it. Operator
  decisions D2 (strict mode, option A on evidence) and D4 (prune window).
- `Model::shouldBeStrict(! isProduction())` is on — all three guards outside
  production. Adopted only after the full suite ran green under it; the first
  run failed 84 tests and every failure decomposed into a real defect, which
  is the argument for keeping it:
  - the media picker's subset select fed `PublicMediaDelivery` records
    without `updated_at`/`trusted_at`, so the inline-SVG safety cache keyed
    on `0` — a live bug found by the guard, not a false positive;
  - `withExists()` merges a cast into hydrated instances and `refresh()`
    drops the aliased column while the cast lingers, so `hasAttribute()`
    affirms an attribute `getAttribute()` cannot deliver
    (`EpisodePublicState` now asks the attributes array);
  - `AdminResourcesTest` asserted `Storage::assertExists($group->cover_path)`
    against the retired legacy owner-column — `assertExists(null)` checks the
    disk ROOT, so the assertion had been vacuously green since the
    retirement. Strict mode is what exposed it.
- New-code rule: any query that projects a column subset owns the full read
  contract of everything downstream of its rows; strict mode now enforces
  this in dev and CI.
- `FormVerificationCode` is `MassPrunable` — codes 30 days past expiry —
  with `model:prune` scheduled daily at 03:50 next to the quarantine prune.
  No retention expectation for used codes exists in docs or tests (checked
  before choosing the window).
- The Gate surface map (F9) lives at
  `docs/research/authz-gate-surface-map.md` — 113 sites, three deliberate
  layers, zero orphaned abilities; its section 4 lists the real findings,
  led by the policy-less-resources asymmetry (an Admin cannot delete an
  episode but can delete the podcast that owns it).

## Database Alignment — Phase 0 (snapshot tooling + folding deploy)

- The database alignment program is specced in
  `docs/phase-02/database-alignment-spec.md` (adversarially re-verified
  2026-08-08 against the LaravelDaily archive, vendor source, and live
  probes; supersedes the clock half of `hebrew-collation-and-clock-plan.md`
  and rewrites `mysql-test-lane-spec.md` §3/§6/§7 — those two docs still
  need their deferral banners, queued for the next docs pass).
- Phase 0 executed 2026-08-08: `db:snapshot`/`db:restore` commands landed as
  `aabad86` — gzipped table-level dumps + JSON manifest under
  `storage/app/db-snapshots/`, restore guarded by a typed-name confirmation
  and content refusals for the two measured dump traps (B1 CREATE
  DATABASE/USE retargeting, B2 --tz-utc re-rendered literals). Tests pin the
  refusals and exact shell contracts (10 passing); proven end-to-end locally
  by a real snapshot + full restore round trip with identical counts.
- Production caught up the same day behind a native `artisan down` window:
  deploy `75045371` landed `aabad86`, the Hebrew-folding migration ran
  ([42]), `search:backfill-folds` filled 1,053 rows across 12 models
  (shadows 100%), `db:check-settings` baselined the two known findings live
  (utf8mb3 schema default, +03:00 clock), `/up` 200 after `up`. The public
  503 is the operator's deliberate MP1 soft maintenance, pre-existing.
- Backups on record: local `podtext-20260808-180202-phase0-baseline`
  (9.4.0), production `/home/forge/backups/…-pre-folding.sql.gz` (manual,
  pre-deploy) and `podtext-20260808-180633-post-folding` (via the new
  command, 8.0.46). Auto-deploy is OFF — push does not deploy; this deploy
  was triggered deliberately via Forge.
- Next: Phase 1 (dedicated local MySQL user, off root), then the alignment
  migration phase behind its rehearsal-database rule (spec §9).

## Database Alignment — Phases 1–2 executed (collation + DATETIME, everywhere)

- Executed 2026-08-09 via subagent-driven development against the
  implementation plan (`94a3328`); every task independently reviewed, the
  riskiest artifacts (migration, oracle) by adversarial review with
  operator-adjudicated hardening. Commits `837782c..3b142fb` (pushed).
- Local user first: `podtext`@`127.0.0.1` owns local app access
  (grants on `podtext`.* + `podtext_restore_check`.* only); `.env` off
  `root`. A second Herd daemon runs MySQL 8.0.46 on 3307 for rehearsals and
  the future test lane.
- Tooling: `db:preflight-alignment` (generated B3/B5 scans, 30 unique
  indexes), `db:seed-rehearsal-edges` (DST/epoch/collation edge matrix,
  insert-vs-update paths from STATISTICS), `db:alignment-oracle`
  (fail-closed exact-diff certifier: provenance meta, transition allow-list,
  ORDER BY SHA1(line), phase guards), and `db:check-settings` extended with
  column-type + PAD_ATTRIBUTE drift.
- The migration (`2026_08_09_000000`): generated from information_schema,
  driver-guarded, attribute-guarded (throws on unsanctioned
  default/EXTRA/precision/comment), schema-qualified DDL, deterministic
  order, ALTER DATABASE first. Session timezone deliberately untouched —
  each TIMESTAMP materialized as the literal the app always read (spec §4).
- Rehearsed on BOTH engines (9.4.0 + 8.0.46) from restored snapshots with
  seeded hostile rows; drop-and-recreate verification ran the whole sequence
  clean with zero manual steps. Three plan-inherent defects were caught by
  rehearsal and fixed in code before any real run (composite-unique pivots,
  migrations-ledger hashing, truncation-manufactured B5 artifacts) —
  `docs/phase-02/database-alignment-rehearsal-log.md` has the full record.
- Real runs, both oracle-PASS byte-identical (36 tables / 390 columns):
  local `podtext` (591ms) and production behind a native `down
  --secret` window (deploy `75054667` at `3b142fb`, migration rode the
  deploy script). End state everywhere: schema default + 40 tables + 183
  columns `utf8mb4_0900_ai_ci`, `datetime ×80`, zero TIMESTAMP, the
  utf8mb3 finding GONE. `config/database.php` hardcodes the pair (no env
  indirection) so every future CREATE TABLE inherits it.
- Snapshots banked: local `pre-alignment` (9.4.0), production
  `pre-alignment` + `post-alignment` (8.0.46, via the site's shared
  storage). Rehearsal DBs kept in converted state pending operator drop
  approval. Only the clock finding remains — Phase 3 (OS → UTC, connection
  pin, tz tables, schedule intent) is next, behind its own gates.
- Caveat: restoring either banked `pre-alignment` snapshot under the
  now-pinned `+00:00` connection (added below in the Phase 3 code half) and
  replaying the migration would materialize shifted literals the oracle
  cannot catch — the restore-and-replay session is internally
  self-consistent, so nothing inside it flags the drift. Restore only with
  the pin temporarily removed, or onto a connection that was never pinned.

## Database Alignment — Phase 3 server half executed (the clock)

- 2026-08-09, operator-approved window: ikc4 (28 cols, 12 ALTERs) and
  ari_configurator (60 cols, 26 ALTERs) frozen TIMESTAMP→DATETIME with
  nullability restated and their single `failed_jobs.failed_at` default
  dropped — collations untouched (their decision, not podtext's). Backups
  first (`/home/forge/backups/*-20260809-pre-freeze.sql.gz`, gzip-verified);
  literals byte-identical after; zero timestamp columns remain in either.
- ikc4's inert `APP_TIMEZONE="Asia/Jerusalem"` `.env` line deleted, config
  cache rebuilt (zero Jerusalem refs in the cached config).
- One root window: `timedatectl set-timezone UTC`, `default-time-zone =
  '+00:00'` pinned in `mysqld.cnf`, tz tables loaded
  (`CONVERT_TZ('2026-01-15 10:00:00','UTC','Asia/Jerusalem')` →
  `12:00:00`), single mysql restart + both php-fpm units; podtext Horizon
  terminated/respawned. All three apps: `TIMEDIFF(NOW(), UTC_TIMESTAMP())`
  = `00:00:00`; podtext `/up` 200, ikc4 302 (auth redirect), ari 200.
- **`db:check-settings` on production: exit 0, "No drift found" — first
  time.** Remaining Phase 3: the in-repo `+00:00` connection pin + local
  daemons' my.cnf/tz tables (Task 13), schedule timezone intent (Task 14),
  then the Phase 3 deploy gate.

## Database Alignment — Phase 3 code half through Phase 6 executed (test lane, display/input globals, guard inventory)

- Phase 3's code half closed and deployed together: `3911495` declares
  `'timezone' => '+00:00'` on the `mysql` connection in git rather than
  inheriting it from the server's OS, and `d5f3837` pins both
  `routes/console.php` schedule entries to `UiTimezone::name()` so the
  03:30/03:50 jobs run on Israel wall time year-round, guarded by a test
  against any future unpinned entry. Push `3b142fb..d5f3837`, deploy
  `75071476`; verified on production — `/up` 200, `db:check-settings` exit
  0 with the declared pin, `schedule:list` showing both entries in
  Jerusalem time.
- Phase 4 retired SQLite from the suite. A dedicated MySQL 8.0.46 daemon on
  port 3307 (the `mysql_testing` connection, sharing no env key with the
  app) is protected by a one-shape guard — `TestLaneGuardTest` (`3638245`
  + `431edcb`) refuses the app's own database/port/user, any DSN or socket
  escape, and any name collision in the raw env files — plus a
  flock-based run-lock (`9f979b5`) against concurrent suite runs. The
  first run against the real engine turned up 89 failures; 14 attributed
  commits took it to green, including two genuine application defects the
  SQLite suite had never been able to see: `de83d26` (MySQL's unspecified
  `ON UPDATE` is `NO ACTION`, not the `RESTRICT` the legacy-role schema
  contract predicted) and `e031bcb` (the legacy-role analyzer querying a
  configured column that does not exist — MySQL rejects that at parse
  time; SQLite's quoted-identifier fallback had silently absorbed it). The
  §3 Hebrew-folding collation matrix re-measured identical on the 8.0.46
  lane to the spec's 9.4.0 figures, closing the version-identity question.
  Full lane suite: 1934/1934 in ~635s, versus the retired ~557s SQLite
  baseline (the rehearsal log's earlier 615s/1929 figure is a real run from
  an earlier point in the same T19 stabilization wave, not a
  contradiction). Pushed `d5f3837..f3c764b`; no deploy — the lane is
  dev/test-only.
- Phase 5 gave every Filament date surface one source of truth, and
  deployed it: `c093e96` adds the global hooks that give every
  `DateTimePicker`/`DatePicker` its Asia/Jerusalem timezone and day-first
  format from one place, `46ef475` adds the `forDisplay()` macro on both
  `Carbon` and `CarbonImmutable` as the one call site for rendering,
  `0cb9cb8` collapses the per-site `->timezone()`/`->displayFormat()`
  chains this replaced across 44 files (+77/−149), and `6ef6099`/`71dc28b`
  add `ExistsInTimezone`, a validation rule that catches Jerusalem's
  spring-forward wall-clock gap by round-tripping the parsed value through
  its own format. Gate review (`efcebec`) caught two real bugs first: the
  wiring read `DateTimePicker::getFormat()`, which is inert on fields with
  seconds disabled, fixed to `getInternalFormat()` and confirmed against
  vendor source; and `Carbon::createFromFormat()`'s undocumented `null`
  return path, which would have crashed outside the rule's own `try` block
  instead of failing validation cleanly, fixed with an explicit `$parsed
  !== null` guard. Push `f3c764b..efcebec`, deploy `75082462`; verified on
  production — `/up` 200, `forDisplay` rendering `08/08/2026 12:34`,
  day-first Jerusalem.
- Phase 6 closed the program on four standing guards: `ebb6e1a`
  (`MigrationDateColumnPolicyTest`, bans `TIMESTAMP`/DB-generated time in
  any future migration), `a278b33` (`ModelDateFormatPolicyTest`, bans
  per-model `$dateFormat` escape hatches), `ac8c8a8` (a fourth
  `UiTimezonePolicyTest` case, banning `->timezone()`/`->displayFormat()`
  chains from re-entering `app/Filament/`), and Phase 4's
  `TestLaneGuardTest` above. `db:check-settings` — extended across the
  whole program with collation, column-type, and `PAD_ATTRIBUTE` drift
  checks — is now the standing drift alarm: exit 0 on both production and
  local.
- Closed 2026-08-10 at the program's final gate: both rehearsal databases
  (`podtext_restore_check` on 3306, `podtext_rehearsal` on 3307) dropped on
  operator approval — the 3307 daemon now hosts only `podtext_test`. All 55
  program commits are pushed; production deploys carried through `75082462`
  (`efcebec`), and the final six commits (tests/docs/lock-fix) ride the next
  feature deploy.
- Post-program follow-ups (triaged 2026-08-10, operator-confirmed — the full
  residual ledger is `docs/phase-02/open-findings-triage.md` §F):
  - Suite prerequisites now include `mysqldump` and `gzip` on PATH —
    `DatabaseSnapshotCommandsTest` shells the real dump pipeline against the
    lane. A fresh worktree also hard-refuses first lane use by design
    (empty-schema fingerprint); the landed remedy is `php artisan
    db:test-lane-reset` (`0f3a32a`, hardened `274b536`/`fb6b212`, extracted
    onto `App\Support\Testing\TestLaneContract` by `a45efc4`) — a
    refusal-layered, typed-confirmation command that empties the lane and
    removes its fingerprint, verified end-to-end 2026-08-10 (Task 7: 40
    tables dropped, `TestLaneGuardTest` 15/15 green against the re-fingerprinted
    empty schema).
  - ~~Next deploy window checklist: restart `cron` + `rsyslog`~~ — **DONE
    2026-08-12** in the deploy-75255479 window (both restarted, nginx reloaded,
    all active; syslog stamps `+00:00`, `timedatectl` local = universal = UTC).
    Triage F7 closed.
  - Restoring a `pre-alignment` snapshot still follows the pin-removal caveat
    above; `db:restore` now refuses outright — not merely warns — when a dump
    carries TIMESTAMP-column DDL while the target connection pins `+00:00`
    (`17c19ff`, hardened `f83949f`/`3bba8cc`/`a63d23f`).

## Test-Suite Rethink — Phases R, T, and S executed

- Program: `docs/phase-02/test-suite-rethink-spec.md` +
  `docs/phase-02/test-suite-rethink-implementation-plan.md`, executed
  2026-08-10 by subagent-driven development with per-task adversarial review.
  Phase U (Pest 5 + plugins) remains gated on the operator's separate
  go-ahead; DP4 (Rector write passes) stands open with **zero adopts
  recommended**.
- **Phase R** measured the suite (report in
  `docs/research/test-suite-rethink-notes.md`): 1,969 tests / 622.2s, one
  Feature file (`PublicMaintenanceModeTest`) alone at 148.7s, browser share
  25.6%, guard query 1.0s/suite (F9 closed), CI feasible without weakening a
  guard clause (deferred post-U by operator decision, mapping recorded).
- **Phase T** introduced Rector dev-only (`7b6b52e` + three hardening waves):
  dry-run-locked via `composer rector` and a contract guard test that PROVES
  `--dry-run` withholds writes on a live fixture; wired to larastan through
  the two-file `withPHPStanConfigs` (Rector skips extension-installer —
  rectorphp #8006/#8141); **serial on purpose** (`00a4ff6`): parallel mode
  measured nondeterministic and lossy (17 vs 8 changed files run-to-run;
  serial 69/147 byte-identical ×4) — DP4 numbers corrected to the serial
  floor, 0 adopt / 2 defer / 3 reject.
- **Phase S** made the suite faster and steadier: the settings-save
  subprocess tax (every save shelled to `node` ×2 under the sync queue)
  faked out of 7 files (`cef86bc` + `0437705`, ~280s saved); the browser
  single-read flake class retired via 26 identical `stableRead` helpers
  (64 routed reads, hang-proof 250ms frame fallback,
  `c720777`/`f1793cf`/`cd6f88c`); the lane run-lock and fingerprint moved
  **machine-global** under `~/.cache/podtext-test-lane/` (HOME-anchored,
  purge-proof; `89a2ee1`/`810f6f2`) — two worktrees can no longer race the
  lane (once their checkouts carry the S3 code — a pre-S3 checkout still locks the retired per-tree path) and a fresh worktree inherits the fingerprint instead of refusing;
  boot guard consolidated behind `TestLaneContract::assertSafeBoot()`; DP9
  flipped the last sqlite-shaped default to `mysql` (`203125c`, prose
  truth-checked `57c1898`).
- **End state: 1,988 tests / 20,869 assertions in 340.7s — 45% faster than
  the R1 baseline at +19 tests.** Full per-task record: the rethink section
  of `.superpowers/sdd/progress.md` (gitignored ledger); review packages and
  reports alongside it.

## Settings-Backup Snapshot Batching — register 1.8 executed (F12 fix)

- Program: operator-approved fix of triage finding F12 (diagnosis
  `.superpowers/sdd/task-F12-report.md`, handoff
  `.superpowers/sdd/F12-fix-handoff.md`), executed 2026-08-11 with the F12
  diagnosis session as designated reviewer. The operator approved **all
  three** proposed fixes; register 1.9 (non-System prune retention) ~~stays
  independently schedulable and~~ was NOT shipped here — it shipped later
  the same day as its own round (`4da7542`, next section).
- **Fix 1 — one spawn per backup.** `SettingsBackupSnapshotJob` now hands
  every pending row of its backup to
  `SettingsBackupSnapshotManager::processBatch()`, which writes ONE
  uuid-named job JSON (each target carrying its `snapshot_id`, plus a
  `results_path`), spawns `scripts/settings-snapshots.mjs` once, and maps the
  script's per-target results file back to rows — ok→DONE, error→FAILED with
  its own message, missing→FAILED with the process error. The script catches
  per-target errors, always writes the results file before closing the
  browser, and exits non-zero if any target failed. Write-then-spawn ordering
  (register 2.6) is now ASSERTED by tests that read the job file at spawn
  time; per-shot failure isolation is demonstrated on a one-spawn multi-target
  batch with one failing target. The per-row 150ms sleep was DROPPED — it
  paced successive spawns, and the single spawn's targets already serialize
  inside one browser. The spawn timeout scales per target
  (`snapshot_process_timeout × targets`, capped at `snapshot_job_timeout −
  60`).
- **Fix 2 — no-op import short-circuit.** `import()` computes the merge
  before `createBeforeImport()`; when the candidate applied-path list is
  empty it keeps the BeforeImport backup row + `import_report` for audit but
  schedules no snapshots and skips the save→SettingsSaved→createSystem cycle
  (a fully locked no-op import now costs 0 spawns and 0 saves).
- **Fix 3 — full-set dedup.** A full-source backup whose payload-minus-locks
  matches a sibling that OWNS a complete DONE full set for the same
  targets×themes×formats records `full_snapshot_source_backup_id` (new
  nullable FK, nullOnDelete) and schedules thumbnails only. The gallery and
  per-backup zip fall back to the source backup's full set through
  `SettingsBackupVersion::effectiveSnapshots()`; borrowed rows are badged
  (`admin.messages.settings_backup_snapshot_borrowed`, en+he) and never offer
  retry. Deleting the owner nulls the pointer and degrades the borrower to
  its own rows.
- **Measured spawns per operation (before → after):** explicit save 2→1,
  bare import 12→2, content-rich import 18→2, createManual 10→1, ungated
  restore 10→1, gated restore 12→2, fully locked no-op import 10→0. The
  44-row worst configured case (png+pdf+html × both themes) is now one spawn.
  `tests/Feature/F12SettingsBackupDiagnosisTest.php` re-pinned with every
  drop argued inline; `fakeSettingsSnapshotProcess()` (tests/Pest.php) fakes
  the batched results contract.
- **End state: 2,000 tests / 20,947 assertions in 344s green**, pint clean,
  FilaCheck 35/35, `npm run build` clean. Reviewer verdict from the diagnosis
  session ~~gates~~ gated final completion of the register entry — approved
  2026-08-11 (2 Important + 1 Minor, closed in `ee49793`; residual minors
  taken in `41c8819`).

## Settings-Backup Retention — register 1.9 executed

- Program: operator-approved round closing the last unbounded ceiling in the
  settings-backup subsystem (triage §F12 side-flag → register 1.9), executed
  2026-08-11 (`4da7542`) with the 1.8 batching session as designated
  reviewer. Design doc + verdict trail:
  `.superpowers/sdd/task-1.9-design.md` (gitignored session artifact).
- **Policy (operator-chosen at an AskUserQuestion gate):**
  `SettingsBackupManager::prune()` keeps the newest N per source instead of
  System-only — System unchanged at `max(1, retention)`; BeforeImport 25;
  BeforeRestore 25; Manual keep-forever by default. Knobs in
  `config/settings-backups.php` + `.env.example`
  (`SETTINGS_BACKUPS_RETENTION_MANUAL/_BEFORE_IMPORT/_BEFORE_RESTORE`;
  `<= 0` = keep forever for non-System sources). Pruning stays inline in
  `create()` — growth is admin-action-bound, so pruning at the moment of
  growth bounds the disk with no scheduler dependency.
- **Borrow-liveness guard:** an owner whose full set is still borrowed by a
  SURVIVING backup is skipped that pass; a borrower that is itself a
  candidate does not protect its owner (same-pass pair dies together, files
  deleted after commit). Deferral is bounded because **a source whose own
  retention ceiling is keep-forever never borrows**
  (`isKeepForeverSource()`): such a borrower is never a prune candidate, so
  it would pin its mortal owner forever (reachable via import → restore →
  create-manual-backup). The operator decided this at the design gate as
  "Manual never borrows"; the implementation review showed the source-name
  form was a special case whose invariant a supported knob
  (`SETTINGS_BACKUPS_RETENTION_BEFORE_IMPORT=0`) falsifies, so the shipped
  rule follows the ceiling — identical to the operator's decision under
  default config, true by construction under any. Such a create re-renders
  its own full set inside its one spawn; the 1.8 borrow tests were re-pinned
  onto BeforeImport borrowers, and the refusal (Manual default + a
  non-Manual keep-forever source) plus the allowed
  mortal-borrows-from-immortal direction have their own pins.
- **Manual self-containment is a second, independent refusal.** The ceiling
  rule answers *"can this borrower pin its owner past retention?"*; it does
  not answer *"can an unrelated deletion gut this backup's gallery?"* —
  retention refuses to prune a live-borrowed owner, but an admin deleting
  that owner by hand still nulls the pointer and empties the borrower's
  gallery with no retry offered. So `findFullSetSourceBackup()` refuses
  Manual borrowers whatever their ceiling, keeping the operator's literal
  decision intact and costing one render only in a configuration nobody
  runs today (default `retention_manual = 0` makes the two rules coincide).
  Decided on the implementation reviewer's recommendation after this round
  first shipped the ceiling rule alone; each refusal carries its own
  one-line reason in code so removing either is deliberate.
- **Reviewer findings taken:** chain-freedom (no backup both borrows and
  owns full rows) pinned explicitly — it is load-bearing for single-pass
  prune soundness; `createManual()` joined
  `PublicContentSettingsWriteCoordinator` so every prune and borrow
  establishment serialize under the settings write lock (closing the
  select-then-delete race an uncoordinated manual create could run against
  a coordinated import; lock-timeout behavior pinned);
  `deleteBatchFiles()` PHPDoc and the batch-cleanup test comment no longer
  claim non-System backups are never pruned; the old "preserving
  non-system backups" test was renamed to name its real invariant and
  gained a BeforeImport keeper fixture.
- **Admin visibility (operator-chosen):** the backups table states the
  active retention policy as a table `description()`, dynamic from config,
  translation keys en+he
  (`admin.messages.settings_backups_retention_notice_manual_forever` /
  `_manual_capped`).
- Deliberately NOT done: age-based retention, a scheduled prune command,
  files-only retention (keep rows / drop files), structural shared-set
  refcounting (operator floated it mid-round; assessed as a 1.8-scale
  storage refactor whose semantics the skip-guard already delivers — a
  legitimate future round, not a 1.9 fold-in), and any hand-edit of
  `consolidated-open-findings.md` (its header forbids hand-edits; the
  owning docs carry the status).
- **Implementation review** by the 1.8 batching session: approved with 1
  Important + 1 Minor (2026-08-11), both closed in the follow-up commit —
  the ceiling-driven borrow refusal above, and `orderBy('id')` on two
  order-sensitive `pluck()` assertions that had been relying on InnoDB
  index-walk order. Raised and deliberately deferred: `LockTimeoutException`
  from the coordinated `createManual()` reaches the backups table uncaught —
  a **pre-existing** gap shared with the long-coordinated `restore()` action
  (production waits 5s, not the 0 the test injects), worth one future ticket
  wrapping both actions in a caught-and-notified path.
- **Flake sighting: IDENTIFIED, and not a 1.9 defect.** The reviewer's
  independent gates failed one test on the first run of `c1cbae9` (387.6s)
  and again on `1443b7d` (387.5s), each green on an immediate re-run of the
  same commit (357.1s); every run in this session was green. Caught on the
  third occurrence:
  `tests/Browser/MediaPickerUploadFocusReturnBrowserTest` → *"it returns
  focus to the workspace when the upload settles"*, failing at the **bare**
  `assertNoJavaScriptErrors()` (`:138`) on Chromium's
  `ResizeObserver loop completed with undelivered notifications.` The
  identical +30.5s delta across all three makes it the same test each time.
  The message is the already-classified Filament body/sidebar observer
  artifact, not an unknown, and the in-repo remedy for it was simply never
  applied to the media-picker tests — which is what makes it a work item
  rather than a flake. **Details, evidence and fix shape live in
  `open-findings-triage.md` §F13; this note is only the round record**, and
  F13 predates 1.9 and touches none of its surface.
- **End state: 2,010 tests / 20,987 assertions in 362s green**, pint clean,
  FilaCheck 35/35, `npm run build` clean.

## Test-Suite Rethink — TIA measured in filtered mode, SHELVED (2026-08-12)

- **A pre-commit hook now refuses a commit while a pest run holds the MySQL
  test lane** (`scripts/git-hooks/pre-commit` launcher +
  `scripts/git-hooks/pre-commit-lane-guard.php`, pinned by
  `tests/Feature/PreCommitLaneGuardTest.php`). Install is
  `git config core.hooksPath scripts/git-hooks` — **repository-wide, so it
  reaches every worktree, and `.git/hooks/*` is bypassed while it stands**. It
  closes pestphp/pest#1856's window locally and mechanises T23b, which had been
  a discipline and was breached twice in one evening. Worth keeping whether or
  not TIA is ever adopted.
- **TIA verdict: SHELVE, corrected 2026-08-13 — it was a verdict on the Xdebug
  configuration, not on TIA.** `tests/Pest.php` now excludes `vendor/` from
  Xdebug coverage instrumentation (guarded; inert without a coverage driver, so
  the ordinary gate is unchanged). Recording 1,633s -> **657s**, tax 4.61x ->
  **1.86x**, a presenter edit 546s -> **247s** which beats the 354s plain gate,
  and the four canary browser tests stopped failing. 83% of source files now
  beat the plain gate, median 41s. See `TIA-9` in the notes for the correctness
  gates and what still blocks adoption.
- **Original SHELVE record**, with the numbers and the four flip conditions in
  `docs/research/test-suite-rethink-notes.md` → *TIA filtered-mode measurement*.
  Headline: filtered mode requires a coverage driver, so it inherits Xdebug's
  4.6× per-test cost; a one-liner in a broad model costs **1,633s against a
  354s plain full suite**, 33% of source files are slower under TIA than not
  using it, and 34% pull in an Xdebug-fragile browser canary and go red for no
  reason. It does pay for the median file (112s) and is 354× faster for a
  test-file edit. **The cheapest untried lever is pcov** — every cost figure is
  an Xdebug figure.
- Machine-global TIA state purged at close-out; run logs, graph snapshots and
  the analysis script live in `~/.cache/podtext-coord/upstream-pest/`.
- **Gate at close: 2,035 tests / 21,064 assertions / ~350s green**, pint clean,
  FilaCheck 35/35. It moved twice inside one session — 2,029 at session start,
  +3 hook tests (`5312ddd`), +3 fail-open branch tests (`2dc3d55`) — and a peer
  session already read the intermediate 2,032 as the current figure. Quote 2,035.
- Edit scenarios in the notes are labelled **S0–S6** and findings **TIA-1–TIA-8**,
  not `E*`/`F*`: E and F are road letters (larastan, Rector) and `F<number>` is
  the findings-ledger convention in `open-findings-triage.md`.
