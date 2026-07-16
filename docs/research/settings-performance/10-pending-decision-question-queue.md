# AUTHZ1 / ARCH1 / SP3D Decision and Checkpoint Queue

Date: 2026-07-16

Status: restart-safe index after AUTHZ1-C remediation planning; the exact v1
remediation prompt is ready but unimplemented; AUTHZ1-D remains blocked and
legacy authorization remains authoritative

## Restart protocol

On a context reset, read in this order:

1. `07-sp3d-pre-research.md` — full settings-series inventory, steering, and
   ARCH1/SP3D wireframe.
2. `09-arch1-drafts-authorization-research.md` — approved per-user drafts and
   AUTHZ1 boundary.
3. `11-bq-decisions-and-defaults-audit.md` — approved BQ1–BQ6, corrected
   recommendations, and audited defaults.
4. `12-authz1-pre-implementation-research.md` — exact current surfaces,
   compatible package solve, catalog/role metadata, staged cutover, and
   acceptance evidence.
5. `13-authz1-foundation-research.md` and the foundation handoff — shipped
   package/schema/catalog evidence.
6. `14-maintenance-livewire-enforcement-effects-audit.md` — completed audit v1
   and independent deferred stale-tab UX finding.
7. `15-authz1c-analyzer-backfill-research.md` and its paired implementation
   plan — accepted AUTHZ1-C planning contract.
8. `16-authz1c-independent-analyzer-backfill-audit.md` and the audit handoff —
   decision 2 and the required C remediation boundary.
9. `17-authz1c-audit-remediation-research.md`, its paired implementation plan,
   and `prompts/pre-13-prompts/authz1c-audit-remediation-codex-prompt.md` v1 —
   the implementation-ready but unexecuted remediation contract.
10. This file — remaining evidence/approval checkpoints.

Do not restart at Group 16. Groups 1–15, BQ1–BQ6, and the audited minor
defaults are settled. There is no unanswered broad architecture question in
the old Groups 16–22.

## Single continuation point

The reversible AUTHZ1 foundation, maintenance-effects audit v1, AUTHZ1-C
implementation/two-commit closeout, and independent C audit are complete. The
audit's decision-2 remediation now has v1 research, implementation plan, and an
exact implementation prompt. It is not implemented. The next action is
operator review and exact-version kickoff of that prompt. Do not plan or begin
AUTHZ1-D–I until the remediation is implemented and accepted. The disposable
two-connection MySQL rehearsal
remains a separately approved future gate; it is not a substitute for the C
remediation. Do not apply compatibility grants or switch policies/Gates.

`MAINT-LW-UX1` is independently deferred for the medium production stale-tab
maintenance UX and focused missing regression coverage from report 14. Run it
before the first later public Livewire navigation/polling/lazy/deferred/stream/
upload expansion or before AUTHZ1 final acceptance, whichever comes first. It
is not coupled to or a blocker for AUTHZ1-C.

## Approved big-question checkpoint

| ID | Approved answer | Result |
|---|---|---|
| BQ1 | B | Multiple additive roles plus exceptional additive direct grants; abilities replace rank as runtime authority. |
| BQ2 | B | Deployed PodText catalog owns ability definitions; super-admins manage role bundles/assignments; explicitly delegated admins assign only safe delegable roles. |
| BQ3 | A | Every registered runtime Template family always has one active published default revision; replacement is atomic. |
| BQ4 | A | Separate PII view/export abilities; no routine hard-delete/prune feature before an approved retention/privacy policy. |
| BQ5 | A | AUTHZ1/ARCH1 cutover fails closed, reports all raw-source problems, mutates nothing, and reruns after repair. |
| BQ6 | A | Separately accepted AUTHZ1 → ARCH1 → L10N/ADM/LENS → SP3D → SP4 → LOG1 boundaries. |

`11-bq-decisions-and-defaults-audit.md` is controlling for the full wording,
corrections, and defaults.

## Disposition of the former question groups

| Former group | Disposition |
|---|---|
| 16 — role model/default grants | Resolved by BQ1/BQ2 and the compatibility-first role/grant defaults in report 11. |
| 17 — ability catalog/governance | Resolved by BQ2 and the owned literal dot-key catalog/delegation/synchronization defaults in report 11. |
| 18 — working-draft UX | Product policy resolved. Exact measured autosave intervals and final HE/EN copy are implementation evidence, not another operator questionnaire. |
| 19 — Template lifecycle/defaults | Resolved by BQ3 plus ARCH1-D–G/M and report 11. Publication pointer, operational availability, and revision status remain separate axes. |
| 20 — Public Form lifecycle/runtime | Resolved by BQ4 plus report 11. Initial mount-token default is config-backed two hours; runtime/security measurement may tune it without reopening architecture. |
| 21 — migration/portability/cutover | Resolved by BQ5 plus ARCH1-J/K and report 11. Raw mapping/provenance evidence is produced by the migration dry run. |
| 22 — slicing/acceptance ownership | Resolved by BQ6. Exact prompt/file plans are intentionally not written in research. |

## Implementation-research checkpoints

These are requirements for the future step research/plan, not open product
questions to ask again unless new evidence exposes a material tradeoff.

### AUTHZ1 checkpoint

Pre-implementation evidence is consolidated in
`12-authz1-pre-implementation-research.md`; shipped foundation evidence is in
`13-authz1-foundation-research.md` and the foundation handoff. The accepted
AUTHZ1-C audit v1 and executable plan are in the two
`15-authz1c-analyzer-backfill-*` documents, with the implementation contract at
`prompts/pre-13-prompts/authz1c-analyzer-backfill-codex-prompt.md` v1.
AUTHZ1-C implementation and two-commit closeout are complete; its original
evidence is in `docs/phase-02/authz1c-analyzer-backfill-handoff.md`. The
independent audit at `16-authz1c-independent-analyzer-backfill-audit.md` found
one High, four Medium, and two Low gaps. The controlling remediation contract
is now `17-authz1c-audit-remediation-research.md`, its paired implementation
plan, and `prompts/pre-13-prompts/authz1c-audit-remediation-codex-prompt.md` v1.
It is unimplemented; AUTHZ1-D–I remain unstarted.

- The installed exact solve is Shield 4.2.0 + Permission 7.3.0 plus transitive
  Plugin Essentials 1.2.1, with no unrelated update/removal. Shield remains
  configured but unregistered; no package assignment or runtime cutover exists.
- Inventory every rank/gate/policy/Resource/Page/widget/action/command/writer,
  Workbench, import/restore, and Horizon authorization surface.
- Produce the compatibility-first current-access matrix. Migration maps each
  legacy role value to exactly one protected seeded role and does not
  automatically add a universal `user` role.
- Define the deployed immutable Ability catalog, bilingual labels/groups, and
  role metadata (`protected`, `reserved`, `delegable`) without making Shield an
  independent key authority.
- Use `panel.admin.access` and a separate `system.horizon.view`; no role name or
  panel access implicitly grants Horizon.
- Define the single transactional role/direct-grant service, append-only direct-
  grant metadata, last-super-admin concurrency safety, cache invalidation, and
  safe replacement/add/remove command semantics.
- Prove raw legacy role assignments, unknown/corrupt values, and catalog drift
  before switching authority. Rank remains authoritative until verified
  cutover, then it is removed as runtime authority.

### ARCH1 shared/draft checkpoint

- Define parent/revision/working-draft fields and constraints while preserving
  publication pointer, operational state, and immutable checkpoint separation.
- Add stable immutable node IDs inside Template part/child and Form field/option
  JSON so selective compare/copy and submission interpretation are reliable.
- Verify event/idle autosave under Livewire 4 with one in-flight write,
  coalescing, base checksum, stale response handling, offline status, internal
  navigation modal, and generic browser-close fallback.
- Keep the server working draft authoritative. Do not add persistent whole-
  document browser storage in v1.
- Verify discarded working rows use a hidden/tombstoned recovery boundary, not
  surprise checkpoints or immutable-history deletion.

### Card Template checkpoint

- Backfill every registered runtime family and code default into deterministic
  protected parents/revisions/default mappings before runtime cutover.
- Prove exactly one active published default per registered runtime family and
  atomic replacement/rollback behavior.
- Keep ULID/FK identity immutable while semantic keys remain unique validated
  editable metadata. Do not auto-repoint consumers by text key.
- Prove protected system-revision compare/preview/explicit activation/clone and
  referenced-parent archive refusal.

### Public Form/submission checkpoint

- Keep parent availability, exact published pointer, working copies, immutable
  checkpoints, and revision revocation separate.
- Bind signed authorization to server-resolved parent/revision identity,
  visitor/session nonce, locale, issue time, and expiry. Start with the accepted
  config-backed two-hour lifetime; OTP remains separately five minutes.
- Re-authorize privileged Livewire actions server-side. Republishing preserves
  the mounted revision; close/archive/revoke refuses it.
- Prove preview is server-side non-submittable and cannot invoke submitter,
  mail, OTP, rate limiting, or persistence.
- Finalize the minimal submission interpretation snapshot: stable field/option
  identity, semantic key/value, resolved label, locale, and type without a full
  duplicate schema.
- Expose separate PII view/export abilities and no routine hard-delete/prune UI.
  Preserve a later privacy/legal erasure boundary; BQ4 does not mean PII can
  never be erased.

### Migration/cutover checkpoint

- Analyze raw settings before current normalization can hide duplicates or
  invalid values. Never invent fallback identities or historical provenance.
- Map uniquely resolvable legacy submissions to an explicitly labelled
  legacy-cutover/unverified revision; missing/ambiguous mapping blocks cutover.
- Require canonical backup/export, raw-source hashes, deterministic preset/
  default mapping, reference completeness, canonical/renderer equivalence,
  submission preservation, final write pause/hash sync, and rollback proof.
- Keep one accepted read-only legacy rollback release, then contract; never
  maintain indefinite dual-write authority.

## Later review and evidence gates

### Group 23 — L10N/ADM/LENS review pack

- After ARCH1 fixes ownership, extract singleton-settings copy to dedicated
  HE/EN settings files and Resource copy to its domain files.
- Then run ADM structure/order/hint/simple-Hebrew review and accepted LENS packs
  of roughly 25–40 rows by page/domain.
- Land all accepted wording/layout changes affecting measured pages before
  SP3D calibration. Each pack still receives operator acceptance.

This is a review workflow, not another architecture round.

### Group 24 — SP3D evidence-gated acceptance

- Repair/prove authenticated in-app browser, serial Pest Browser, and external
  Playwright/Node integration on the final AUTHZ1/ARCH1 surfaces.
- Select the named fixed runner/profile and production-shaped fixtures.
- Run two calibration passes before proposing literal component/browser/TTFB
  ceilings. Deterministic component/query/read/lifecycle checks stay in the
  ordinary suite; hydrated browser budgets stay in the controlled profile.
- Approve evidence-backed literal ceilings, artifact retention, and any
  dependency repair. Pagination/windowing remains unapproved until failure
  evidence requires a choice.

This contains real evidence-dependent approvals; no unseen number is approved.

### Group 25 — SP4 implementation research

- Derive changed paths server-side from fresh normalized authorized state;
  client dirtiness is never authority.
- Define stable-ID/order-aware Builder/array diff semantics and explicit
  omit/redact/summarize/hash rules for sensitive values.
- Preserve one settings/cache authority and one after-commit coordinator.
- Collapse import/restore batches into one safe change set and prevent per-row
  cache/backup/log storms.
- Use owned preview with normalized unsaved state and explicit/change/blur
  refresh boundaries.

These defaults are selected; exact value classes and diff mechanics belong to
SP4 research.

### Group 26 — LOG1 future approval gate

- Obtain exact Composer approval for `spatie/laravel-activitylog`.
- Consume safe coordinator change sets and explicit authorization changes;
  prohibit duplicate blanket model-observer logging.
- Keep separate view/export/sensitive-diff abilities and exclude secrets, PII
  values, trusted HTML, private transcripts/Form payloads, and protected JSON.
- Start without automatic pruning. Approve retention, backup, and destructive
  cleanup policy before enabling the package cleanup schedule.
- Adapt the operator's `ikc-f4` ActivityLog presentation without unbounded
  preloads.

Composer and retention are genuine later approvals, not new architecture.

## Execution confirmations

### Group 27 — Production authorization/settings/mail rollout

- Read-only role report and backup precede exact AUTHZ1 backfill/cutover command
  approval; intended super-admin identity remains private.
- Redis/store/scoping, settings cache, config refresh, and worker recycle remain
  exact per-action approvals.
- Review normalize dry-run before apply; confirm `transcription_mode=single`;
  configure Resend/`FORMS_OTP_*`/DNS privately; run approved verification.

### Group 28 — WB-PROBE-HF1 and Google format probe

- Keep the approved timing after all selected SP/mini-tasks and before WB
  planning.
- Finish operator-friendly connection choice, refresh, privacy-safe output, and
  partial-failure/resume behavior.
- Select the private 20-document manifest, run the service-account probe, and
  accept it before WB2/WB4 or paste-cleanup/`[]` planning.

### Group 29 — final consolidated audit

- Reconcile all decisions, deferrals, mini-tasks, manual reviews, production
  actions, and probe gates against current state and ledger.
- Confirm no historical superseded instruction is active and no operator point
  is missing.
- Operator/Fable accept each selected scope boundary before its implementation
  prompt. This is completeness review, not a new design questionnaire.

## Not to reopen unless new evidence changes the premise

- Six sensitive settings locks and section-lock diet.
- Tiny-native/custom-required/growing-async Select policy.
- Versioned parent plus revision-owned JSON architecture.
- Per-user autosave, immutable checkpoints, and Group 15 collaboration rules.
- Multiple additive roles, ability-not-rank authorization, strict delegated
  assignment, and no explicit-denial layer.
- Dataset-only Template/Form import locks; no record/node locks.
- No automatic semantic-key repointing, template-level manual reorder, or
  automatic JSON merge.
- Exactly one active published default per registered runtime Template family.
- BQ4 interim PII deletion freeze and BQ5 fail-closed cutover.
- AUTHZ1 before ARCH1; ARCH1 before ADM/L10N/LENS and SP3D; SP4 before LOG1.
- Known deferrals recorded in `07-sp3d-pre-research.md`.
