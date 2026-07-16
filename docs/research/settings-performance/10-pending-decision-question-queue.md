# AUTHZ1 / ARCH1 / SP3D Pending Decision Question Queue

Date: 2026-07-16

Status: restart-safe question index after approved Group 15; no application
code, tests, schema, dependency, implementation plan, or prompt

## Restart protocol

On a context reset, read in this order:

1. `07-sp3d-pre-research.md` — full settings-series inventory, steering, and
   ARCH1/SP3D wireframe.
2. `09-arch1-drafts-authorization-research.md` — approved Groups 13–15 and
   current Shield/Spatie/per-user-draft research.
3. This file — the exact remaining question queue.

Do not reconstruct decisions from older JSON-settings plans or restart at the
discarded context-lost Group 13 draft. Groups 1–15 in the active task are
already reconciled. The next unanswered group is **Group 16**.

## Approved checkpoint through Group 15

- AUTHZ1 precedes ARCH1; ARCH1 precedes ADM/L10N/LENS work that changes measured
  surfaces; SP3D follows accepted migration; SP4 and LOG1 follow SP3D.
- Shield + Spatie Permission + owned PodText ability catalog is selected.
- Complete all-panel authorization migration before ARCH1; role grants plus
  optional direct grants; no explicit-denial layer; super-admin authorization
  bypass retains safety invariants; meaningful mini-abilities are grouped.
- Template/Form autosave uses one mutable working draft per parent/user.
- Explicit Save Draft creates an attributed immutable checkpoint.
- Other users cannot mutate a working draft. Authorized visibility is read-only
  and adoption copies into the actor's own row.
- Checkpoints are shared immutable history. Divergent checkpoints are preserved
  but cannot publish before explicit comparison/rebase/acceptance.
- Authorized publishers may publish another author's immutable checkpoint,
  never another user's mutable row.
- Inactive working drafts are hidden as stale after 90 days and are never
  automatically deleted.
- Exact mounted Form revision remains acceptable while the Form parent is
  enabled and its signed authorization is valid; token lifetime remains open.
- Unreferenced immutable revisions retain at least 90 days and the latest 20,
  whichever preserves more.
- Clone copies one selected revision into a new unprotected identity and copies
  no history/default/reference/published/protection/activity authority.

## Questions to answer now

### Group 16 — AUTHZ1 role model and initial grants

16.1 **Single or multiple roles per user** — retain one primary role, allow
multiple Spatie roles, or use one role plus direct grants only?

16.2 **Seeded role set** — retain the existing `super-admin`, `admin`,
`moderator`, `transcriber`, and `user` slugs as managed database roles?

16.3 **Initial default grants per role** — approve the first permission bundles
for panel access, ordinary editorial work, moderation, transcription work,
settings, Templates/Forms, submissions, imports/backups, and system operations.

16.4 **Role/permission managers** — which abilities allow management of roles,
role grants, direct user grants, and another user's panel access?

16.5 **Direct-grant lifecycle** — permanent until removed, optionally expiring,
or always require a reason/review date?

16.6 **Panel access authority** — make `access-admin-panel` an explicit ability,
and decide whether a role alone ever implies panel access.

16.7 **Current role migration fallback** — on an unknown/corrupt legacy role,
stop the migration, quarantine the user without panel access, or map to `user`?

### Group 17 — AUTHZ1 ability catalog and governance

17.1 **Ability naming convention** — approve stable machine keys and whether
they use dot notation, colon notation, or another owned convention independent
of Shield display labels.

17.2 **Assignment groups** — approve the top-level Shield UI groups and bulk
grant controls while retaining atomic server-side abilities.

17.3 **Sensitive field/area abilities** — choose which protected revision,
security policy, locks, credentials/connections, imports/restores, and PII areas
need finer permissions than their parent Resource action.

17.4 **Generated permission drift** — when Shield discovers a new Resource/Page
or a catalog permission disappears, should synchronization fail closed and
report, or automatically create/archive it?

17.5 **Unknown database permissions** — reject, quarantine, or preserve unknown
permission rows during import/restore/deploy synchronization?

17.6 **Direct grant visibility** — decide who can see a user's effective grants,
their source role/direct assignment, and the audit reason/review date.

17.7 **Super-admin bypass exclusions** — confirm whether any authorization
ability itself is non-bypassable, distinct from already-approved validation and
safety invariants.

### Group 18 — ARCH1 working-draft operational UX

18.1 **Autosave trigger/cadence** — debounce interval, blur/change boundaries,
and whether large Builder documents use a longer interval.

18.2 **Autosave status UI** — exact states for saving, saved locally/server-side,
checkpoint-dirty, offline/failure, stale base, and retry.

18.3 **Navigation warning choices** — finalize labels/behavior for Save Draft and
leave, leave with autosaved working draft but no checkpoint, stay, and explicit
discard.

18.4 **Autosave failure/offline buffer** — whether browser-local recovery is
allowed, its privacy limits, expiry, and clearing behavior.

18.5 **Working-draft creation** — create lazily on first change or immediately
when opening the editor?

18.6 **Explicit discard** — required permission, confirmation, reason, and
whether discard creates a final recovery checkpoint.

18.7 **Divergent compare/rebase UX** — whole-document replacement, selected
field/block copy, or both; automatic JSON merge remains prohibited.

### Group 19 — ARCH1 Template lifecycle and defaults

19.1 **Parent states** — exact Template state machine and distinction among
draft availability, published revision, disabled, archived, and protected.

19.2 **Family default rule** — whether every family must always have one
published default and what happens if its current default is disabled/archived.

19.3 **Default switching** — immediate atomic switch, scheduled switch, or
preview/confirmation only; define rollback behavior.

19.4 **System preset update presentation** — how operators see an available
application-supplied revision, compare it, clone it, and explicitly activate it.

19.5 **Semantic key changes** — whether operators may rename human semantic
keys, whether aliases are retained, and what portable exports display.

19.6 **Archive/reference behavior** — exact actions offered when a Template is
still referenced, given that automatic semantic-key repointing is not approved.

19.7 **Template preview modes** — required fixture/entity choices, responsive
sizes, locales, and family-specific preview contexts.

### Group 20 — ARCH1 Public Form lifecycle and runtime

20.1 **Parent/revision states** — exact distinction among draft, published,
enabled, disabled, closed, and archived.

20.2 **Signed mount-token lifetime** — approve the default duration and whether
it differs for verified-email versus non-verified Forms.

20.3 **Runtime revocation** — confirm which changes immediately invalidate an
in-progress mount beyond the approved parent disable/archive rule.

20.4 **Expired/revoked submission UX** — preserve entered values, reload a new
revision with compatible fields, or require restart?

20.5 **Republish compatibility** — whether a compatible new revision may be
offered to the visitor or the mounted revision always remains isolated.

20.6 **Submission field identity snapshots** — exact label/locale/type metadata
to retain beside stable field IDs without copying the full schema.

20.7 **Submission PII access/export/delete abilities** — finalize grouped
permissions and whether deletion is ever allowed while retention/legal policy
is not yet defined.

20.8 **Form preview fixture behavior** — sample values, locale switching,
validation display, and confirmation that preview never triggers OTP/mail/rate
limits/submission side effects.

### Group 21 — ARCH1 migration, portability, and cutover classifications

21.1 **Current Template mapping** — classify code-virtual defaults, configured
overrides, custom Templates, duplicate/corrupt keys, and their initial
parent/revision/default/published states.

21.2 **Current Public Form mapping** — classify enabled/disabled definitions and
their initial published/current/disabled state without inventing history.

21.3 **Legacy submission provenance** — approve the exact label and behavior for
submissions that can only bind to a legacy/unverified cutover revision.

21.4 **Missing/ambiguous references** — stop the entire migration, quarantine
the affected record, or allow cutover with a blocking report?

21.5 **Configuration-package default history scope** — current/published only,
all retained revisions, or an operator-selectable export option.

21.6 **Rollback-window ending** — define when the accepted one-release read-only
legacy rollback boundary may be contracted.

21.7 **Write-pause acceptance** — approve how the final write pause/hash sync is
announced, verified, and released.

21.8 **Migration acceptance evidence** — approve the canonical equality,
renderer equivalence, reference completeness, submission preservation, backup,
and rollback report required before ARCH1 is accepted.

### Group 22 — Implementation slicing and acceptance ownership

22.1 **AUTHZ1 split** — one step or separate package/schema foundation,
catalog/policies, all-panel migration, management UI, and production backfill
acceptance sub-steps?

22.2 **ARCH1 split** — shared revision foundation, Card Templates, Public Forms,
submission migration, reference cutover, and legacy contraction boundaries.

22.3 **Templates versus Forms order** — Templates first, Forms first, or shared
foundation followed by separately accepted parallel domains?

22.4 **Operator/Fable acceptance points** — identify which sub-steps must stop
for review before proceeding.

22.5 **Commit/prompt boundaries** — confirm one canonical implementation ending
per accepted sub-step and no giant combined prompt.

## Questions that wait for their step or evidence

### Group 23 — ADM1 / L10N-SET1 / LENS1 review order

23.1 Choose the order of dedicated settings locale extraction, surviving
form/table audit/reorder, hint-copy conversion, and the chunked LENS review.

23.2 Choose the first LENS page/domain review pack and pack size within the
approved approximately 25–40-row range.

23.3 Decide which copy corrections must land before final SP3D DOM/byte
calibration and which may remain later without invalidating attribution.

23.4 Confirm operator acceptance procedure for each LENS/ADM pack.

### Group 24 — SP3D browser calibration and durable gates

24.1 Select the fixed browser/performance runner environment.

24.2 Confirm final production-shaped fixtures after AUTHZ1/ARCH1 surfaces exist.

24.3 Approve literal component/browser/TTFB ceilings only after two calibration
runs; no unseen numeric ceiling is approved now.

24.4 Decide normal CI versus named performance-profile enforcement placement.

24.5 Decide artifact retention and baseline-change approval procedure.

24.6 If the approved fixture fails, choose among measured optimization options;
pagination/windowing remains unapproved until that evidence exists.

24.7 Approve any Composer/npm repair only if one of the three browser mechanisms
cannot be made reliable with current dependencies.

### Group 25 — SP4 surviving-settings reads, diffs, and previews

25.1 Decide array/Builder change-set identity semantics for surviving settings.

25.2 Decide which before/after values are included, summarized, hashed, or
redacted in the safe server-derived change set.

25.3 Confirm slice-cache key/invalidation behavior without a second authority.

25.4 Finalize owned preview refresh cadence and responsive/locale contexts for
surviving settings previews.

25.5 Decide how import/restore batch changes collapse into one safe change set.

### Group 26 — LOG1 activity-log policy

26.1 Give exact Composer approval for `spatie/laravel-activitylog` when LOG1 is
selected.

26.2 Set activity retention/pruning and backup requirements.

26.3 Finalize logged metadata/value-redaction rules and PII/protected-data
exclusions.

26.4 Finalize who may view/export the activity log and sensitive diffs.

26.5 Approve ActivityLog page filters/detail presentation using the operator's
`ikc-f4` reference adapted to PodText.

26.6 Confirm coordinator/change-set logging only versus any additional model
events; duplicate logging must be prohibited.

## Execution confirmations, not architecture questions

### Group 27 — Production authorization/settings/mail rollout

27.1 Approve the exact production AUTHZ1 backfill/cutover commands after a
read-only role-assignment report and backup.

27.2 Identify the intended initial super-admin account privately and confirm
last-super-admin protection.

27.3 Confirm Redis store/scoping, settings-cache enablement, config refresh, and
worker recycle commands individually.

27.4 Review and accept `settings:normalize-public-content` dry-run output before
any apply action.

27.5 Confirm `transcription_mode=single` after role migration.

27.6 Configure Resend/`FORMS_OTP_*`/sender DNS privately and approve the exact
production verification steps.

### Group 28 — WB-PROBE-HF1 and Google format probe

28.1 Confirm WB-PROBE-HF1 implementation timing after all selected SP/mini tasks.

28.2 Confirm operator-friendly connection selection and service-account
connection without exposing credentials.

28.3 Select the 20-document sample manifest privately and approve privacy-safe
tracked versus local-only output boundaries.

28.4 Confirm refresh/cache behavior and partial-failure/resume acceptance.

28.5 Run and accept the probe before WB2/WB4 and paste-cleanup/`[]` conventions
are planned.

### Group 29 — Final consolidated queue audit

29.1 Reconcile every approved decision, deferred item, mini-task, manual review,
production action, and probe against the ledger/current state.

29.2 Confirm no request is missing and no historical superseded instruction is
still active.

29.3 Approve the final AUTHZ1/ARCH1 implementation-step sequence before any
implementation prompt or file-by-file plan is written.

## Not to reopen unless new evidence changes the premise

- Six sensitive settings locks and section-lock diet.
- Tiny-native/custom-required/growing-async Select policy.
- Versioned parent plus revision-owned JSON architecture.
- Per-user autosave and immutable checkpoints.
- Group 15 collaboration rules.
- Dataset-only Template/Form import locks; no record/node locks.
- No automatic semantic-key repointing, template-level manual reorder, or
  automatic JSON merge.
- AUTHZ1 before ARCH1; ARCH1 before SP3D; SP4 before LOG1.
- Known deferrals recorded in `07-sp3d-pre-research.md`.
