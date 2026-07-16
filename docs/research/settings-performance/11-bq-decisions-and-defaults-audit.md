# BQ1–BQ6 Decisions and Recommended-Defaults Audit

Date: 2026-07-16

Status: operator decisions and audited research defaults; no application code,
tests, schema, dependency, implementation plan, or prompt

## Outcome

The operator approved all six remaining big questions:

| ID | Approved answer | Controlling consequence |
|---|---|---|
| BQ1 | B | A user may hold multiple Spatie roles plus exceptional direct permissions. Roles are permission bundles, not a replacement rank hierarchy. |
| BQ2 | B | The deployed PodText catalog owns ability definitions. Super-admins manage database role bundles and assignments from it. An admin may assign non-privileged roles only when granted the specific delegation ability and may not grant authority they do not possess. |
| BQ3 | A | Every registered runtime Card Template family always has one active published default. Replacement is atomic; the current default cannot be disabled or archived without its replacement. |
| BQ4 | A | Public Form submission PII may be viewed/exported only through separate abilities. No routine permanent-deletion feature is introduced before an approved retention/anonymization policy; future legal/privacy erasure remains possible. |
| BQ5 | A | AUTHZ1/ARCH1 cutover fails closed on corrupt, duplicate, unknown, missing, ambiguous, or unresolved source data. It reports everything, mutates nothing, and reruns only after repair. |
| BQ6 | A | Use separately accepted AUTHZ1 and ARCH1 boundaries, then ADM/L10N/LENS, SP3D, SP4, and LOG1. Operator/Fable review stops remain between major boundaries; no giant combined prompt is authorized. |

There is no remaining broad architecture questionnaire from Groups 16–22.
Exact package commands, destructive production actions, measured performance
ceilings, and other evidence-dependent values remain later execution or
acceptance gates, not reasons to reopen BQ1–BQ6.

## Evidence reviewed

- Current installed environment: PHP 8.4, Laravel 13.19.0, Filament 5.6.7,
  Livewire 4.3.3. Neither Spatie Laravel Permission nor Filament Shield is
  installed.
- Current authorization: `UserRole`, the singular `users.role` cast,
  `User::hasRoleAtLeast()`, `User::canAccessPanel()`, the `super-admin` and
  `multi-transcription` Gates/macros, the Users Resource, and
  `users:assign-role`.
- Current Template/Form authority: JSON settings roots, focused settings
  writers/readers, semantic-key references, current Form submissions, OTP,
  lifecycle/import/restore/backup code, and SP3 measurement fixtures.
- Controlling decisions in `07-sp3d-pre-research.md` and
  `09-arch1-drafts-authorization-research.md`.
- Installed-version Filament/Livewire guidance through Laravel Boost, plus
  current primary documentation:
  - <https://filamentphp.com/docs/5.x/users/overview>
  - <https://livewire.laravel.com/docs/4.x/wire-model>
  - <https://livewire.laravel.com/docs/4.x/navigate>
  - <https://spatie.be/docs/laravel-permission/v8/prerequisites>
  - <https://spatie.be/docs/laravel-permission/v8/best-practices/roles-vs-permissions>
  - <https://spatie.be/docs/laravel-permission/v8/basic-usage/direct-permissions>
  - <https://spatie.be/docs/laravel-permission/v8/basic-usage/super-admin>
  - <https://filamentphp.com/plugins/bezhansalleh-shield>
  - <https://spatie.be/docs/laravel-activitylog/v5/basic-usage/cleaning-up-the-log>

The local database schema MCP was not queried because repository rules prohibit
using the local development database for probes. Migrations and model code were
used as the schema evidence instead.

## Audit findings and corrections

### 1. High — semantic-key immutability was wrong

The shorthand recommendation that Template semantic keys become immutable
after use conflicts with approved ARCH1-F/M. The immutable portable ULID and
local FK are authority precisely so a human semantic key can change safely.

**Corrected default:** semantic keys are unique, validated, operator-editable
metadata. Rename requires confirmation and reference/package validation. It
does not automatically repoint a reference by matching text, and aliases are
added only when a demonstrated external compatibility need exists.

### 2. High — parent state and publication must remain separate axes

Treating `published` as one value in a Template/Form parent state enum would
confuse operational availability, per-user working drafts, immutable
checkpoints, and the published revision pointer.

**Corrected default:** publication is an atomic immutable-revision pointer.
Template parent availability/default/archive metadata and Public Form
enabled/closed/archived state remain separate from revision/checkpoint status
and per-user working drafts. Exact columns/enums belong to later ARCH1 planning.

### 3. High — fail-closed cutover does not describe today's importer

Current settings normalization/import may retain the first duplicate or reset
invalid values. That is valid shipped behavior but cannot silently become the
AUTHZ1/ARCH1 migration policy.

**Corrected default:** migration analysis reads raw legacy values before
normalization. Any corrupt, duplicate, unknown-role, missing-reference, or
ambiguous mapping blocks the whole authoritative cutover. A complete dry-run
report is repaired and rerun; source data is not silently rewritten, discarded,
or mapped to a fallback identity.

Legacy Form submissions do not contain exact historical revision provenance.
A uniquely resolvable definition may bind to an explicitly labelled
legacy-cutover/unverified revision. Missing or ambiguous provenance blocks the
mapping; no historical revision is fabricated.

### 4. Medium — direct-grant metadata is not supplied by Spatie

Spatie supports multiple roles and direct permissions, but its standard
assignment pivots do not provide grant reason, reviewer, review date, or expiry
semantics. Requiring those fields would create another owned schema/lifecycle.

**Corrected default:** permissions are role-first. Direct grants are exceptional
and super-admin-only in v1. Grant/revoke actions pass through one owned
transactional boundary and append permission, user, grantor, reason,
`granted_at`, optional advisory `review_at`, and revocation metadata. Grants do
not expire automatically. LOG1 may later consume the safe event, but stock
Spatie pivots are not misrepresented as carrying this audit metadata.

### 5. Medium — fixed autosave timing needs measurement

Livewire 4 supports live debounce, blur/change synchronization, parallel live
updates, offline indicators, and cancellable navigation hooks. It does not make
an arbitrary request interval safe for a large Builder document.

**Corrected default:** autosave at meaningful boundaries—blur/change for text
and discrete Builder actions—with a measured debounce where repeated structural
changes need coalescing. Every write carries the base revision/checksum and is
ordered safely. Exact milliseconds are selected during ARCH1 browser/payload
measurement and do not require another product veto unless the UX changes.

### 6. Medium — browser persistence is not the first recovery authority

A permanent full-document `localStorage` copy would duplicate server authority
and create privacy, stale-data, logout, and shared-device risks.

**Corrected default:** the per-user server working draft is authoritative.
Initial implementation uses visible offline/failure state and bounded in-page
retry only; it does not persist whole Template/Form documents across browser
sessions. A future durable offline cache needs separate privacy/security scope.

### 7. Medium — Form-token duration is an operational default

The exact signed mount-token duration was previously left to an operational
default. The audit accepts **two hours** as the initial config-backed default
for both verified and unverified Forms because the token is still checked
against live parent/revision availability; it is not a permanent schema rule.
ARCH1 runtime/threat-model evidence may tune the configured duration without
reopening product architecture.

Expiry may re-authorize only the same server-resolved immutable revision while
the parent remains enabled and the revision is not explicitly revoked. Current
in-page Livewire values may remain; the expired client token/revision identity
is never trusted. OTP expiry remains the separate existing five-minute policy.

### 8. Medium — 90-day LOG1 pruning was arbitrary

The approved 90-day rules apply to ARCH1 drafts/revisions, not activity logs.
Spatie Activitylog can delete old rows, so enabling a schedule is a destructive
retention decision.

**Corrected default:** LOG1 starts without automatic pruning. It records only
approved safe change sets and separately approved authorization changes. A
future retention/backup policy must be approved before enabling cleanup. This
does not reopen LOG1 architecture and does not copy raw protected or PII values.

### 9. Low — Groups 23–29 are not one kind of checklist

They mix review packs, evidence-gated numeric approvals, future dependency/data
retention approvals, production actions, and external-probe acceptance.

**Corrected classification:** Group 23 is a review pack; Group 24 is an
evidence-gated performance acceptance; Group 25 is SP4 implementation research;
Group 26 retains exact Composer/retention/access approvals; Groups 27–28 are
execution approvals; Group 29 is the final completeness checkpoint.

## Audited default register

### AUTHZ1 roles, abilities, and management

- Preserve the existing `super-admin`, `admin`, `moderator`, `transcriber`, and
  `user` slugs as the initial managed role bundles.
- Users may hold multiple roles. Effective permission is the union of role
  grants plus exceptional direct grants; no rank or explicit-denial layer
  survives as runtime authority.
- Initial grants are compatibility-first: `admin` retains every currently
  admin-accessible operation except the existing super-admin-only surfaces;
  `moderator`, `transcriber`, and `user` gain no admin-panel access merely from
  their role name. New finer grants are added explicitly through the catalog.
- `panel.admin.access` is an explicit ability checked from `canAccessPanel()`
  together with panel ID `admin`. A role name never bypasses it. Horizon uses a
  separate `system.horizon.view` ability; panel access alone never implies it.
- Ability definitions are immutable deployed code/catalog data, not editable
  Shield records. Super-admins may create/manage role bundles from catalog
  abilities, mark ordinary roles delegable, manage reserved roles, and assign
  direct grants. A delegated admin with `users.roles.assign-delegable` may
  assign only an existing delegable non-privileged role whose complete ability
  set is a subset of the actor's freshly re-queried effective permissions.
  They cannot modify roles, direct grants, themselves, super-admin/reserved-role
  holders, or reserved roles. The rule is rechecked transactionally at save.
  Last-super-admin and self-demotion protection remain mandatory.
- Owned ability keys use literal stable lower-case dot-separated
  `<domain>.<subject>.<action>` names such as `panel.admin.access`,
  `templates.revisions.publish`, and `forms.submissions.pii-export`. Wildcards
  are disabled; labels/grouping and Shield generator defaults are adapters, not
  key authority. A key rename requires explicit migration. Production-
  destructive Shield generation commands are prohibited.
- Catalog synchronization creates/updates only catalog-known permissions. It
  fails and reports unknown database permissions; it never grants, deletes, or
  turns them into active “quarantine” abilities silently.
- All application checks use Laravel Gate/policies/`can()`. Super-admin uses a
  Gate interception; direct Spatie `hasPermissionTo()` checks must not become a
  competing bypass-sensitive authorization path. Feature modes—including
  `transcription_mode=single`—validation, conflicts, locks, state transitions,
  reference/retention rules, and confirmations are evaluated as domain
  preconditions outside that authorization bypass.
- Every assignment path—including UI, Artisan, import/restore, migration, and
  synchronization—uses the owned transactional boundary. With multiple roles,
  last-super-admin means distinct users holding the protected role. The old
  singular `users:assign-role` command must receive explicit add/remove/replace
  semantics or be replaced; it cannot silently call `syncRoles()`.
- Role/permission changes use package APIs and the owned boundary so caches and
  long-lived processes are refreshed deliberately at cutover/deployment.

### Working-draft UX

- Create the per-parent/per-user working row lazily on the first meaningful
  mutation and capture base revision/checksum atomically.
- Use server-authoritative states equivalent to: saving, saved working copy,
  differs from latest checkpoint, offline/failed with retry, and stale base.
  Exact HE/EN labels are settled in the later copy review.
- Navigation offers: Save Draft checkpoint and leave; leave with only the
  recoverable autosaved working copy; or stay. Discard is a distinct confirmed,
  authorized action.
- Discard marks only the actor's mutable working copy discarded/hidden, creates
  no surprise checkpoint, and never deletes immutable history. Immediate hard
  deletion is not the v1 default; later pruning remains policy-controlled.
- Divergent work gets side-by-side comparison, explicit whole-revision
  adoption/rebase, and safe selective block/field copy where stable identities
  support it. Template parts/children and Form fields/options therefore receive
  stable immutable node IDs inside revision JSON. No automatic JSON merge or
  last-write-wins publication.

### Card Templates

- Every family always has one unarchived Template with a published revision as
  its active default. Default replacement is one confirmed atomic transaction.
- A draft-only custom Template may exist without being the family default.
- The current default cannot be archived/disabled until a valid replacement is
  selected. Other referenced parents cannot archive until references are
  explicitly resolved; no semantic-key text match repoints them.
- System preset updates create available protected revisions. Operators with
  the relevant ability compare and explicitly activate or clone them; upgrades
  never replace the active revision automatically.
- Switching the family default may be rolled back by another atomic default
  switch. Rolling back Template content follows ARCH1-E and creates/selects an
  immutable revision rather than mutating published JSON.
- Preview uses the real sanitized renderer, controlled fixtures, locale and
  responsive context, and normalized unsaved working state. It refreshes on
  explicit/change/blur boundaries, not every keystroke.

### Public Forms and submissions

- Parent operational availability, published revision pointer, per-user
  working drafts, and immutable checkpoints are separate concerns. Enabled is
  submittable; closed/disabled and archived are not.
- A mounted visitor remains bound to the exact immutable revision. The initial
  signed authorization lifetime is a config-backed two hours for both verified
  and unverified Forms; OTP retains its independent five-minute expiry.
  Republishing does not move the visitor. Parent disable/archive or explicit
  emergency revision revocation refuses submission immediately.
- On token expiry, the server may issue a new authorization for the same
  immutable revision only after rechecking parent/revision availability. It
  does not migrate the visitor to a newer compatible revision automatically.
- Preview may display sample values, validation, locale, and responsive modes,
  but can never send mail/OTP, consume submission limits, or persist a
  submission.
- Submission interpretation stores stable field identity, displayed label,
  locale, field type, and option value-to-label meaning needed for history. It
  does not duplicate the full Form schema per submission.
- PII viewing and PII export are separate abilities. Initial ARCH1 exposes no
  permanent-delete feature; deletion/anonymization requires a later approved
  retention/legal policy.

### Migration, portability, and cutover

- Export defaults to current/published revisions, protected presets/default
  mappings, stable ULIDs, and current semantic keys. Retained history is an
  explicit option. Other users' mutable working copies belong in protected
  backup/recovery scope, not ordinary configuration export.
- Cutover evidence includes a canonical pre-change backup, raw-source dry run,
  source hashes, deterministic default/preset mappings, reference completeness,
  canonical and renderer equivalence, submission preservation/provenance
  classification, a short final write pause/hash sync, and rollback proof.
- The legacy JSON authority remains read-only for one accepted rollback release
  and is contracted only after the acceptance evidence and backup are confirmed.
  There is no indefinite dual write.

### Accepted high-level sequence

```text
AUTHZ1 package/schema/catalog foundation
    -> AUTHZ1 policy + management UI + verified all-panel backfill/cutover
    -> ARCH1 shared parent/revision/per-user-draft/coordinator foundation
    -> Card Template Resource + migration acceptance
    -> Public Form Resource/runtime + submission migration acceptance
    -> reference/package cutover + one-release rollback acceptance
    -> L10N/ADM/LENS work on surviving surfaces
    -> SP3D three-harness proof + calibration + durable enforcement
    -> SP4 surviving-settings slice reads/change sets/previews
    -> LOG1
    -> production and WB actions at their separately recorded gates
```

Each major arrow is a required operator/Fable stop and canonical implementation
ending. This is a scope sequence, not a file-by-file plan or implementation
prompt. Exact Composer, migration, and production commands still require the
normal later approvals.

### L10N/ADM/LENS, SP3D, SP4, and LOG1 defaults

- After ARCH1 establishes ownership, extract surviving singleton-settings copy
  into dedicated HE/EN settings files and Resource-owned copy into its domain
  files. Then perform ADM structure/hint/simple-Hebrew review and the accepted
  25–40-row LENS packs. All accepted layout/copy that affects measured surfaces
  lands before SP3D calibration.
- SP3D keeps deterministic component/query/read/lifecycle assertions in the
  ordinary suite and runs hydrated browser budgets in a named fixed performance
  profile. Two accepted calibration runs precede literal caps. Browser DOM,
  network/listeners/heap/navigation evidence is never replaced by component
  HTML or developer-laptop timings. Baseline increases require reviewed
  evidence.
- SP4 derives changed paths server-side from fresh normalized authorized state.
  Stable IDs make ordered arrays/Builders diffable; protected values are
  omitted, redacted, summarized, or hashed by explicit policy. One coherent
  after-commit coordinator handles cache invalidation, backup, preview
  refresh, and batch collapse without per-row storms.
- LOG1 consumes the coordinator's safe configuration change set and explicit
  authorization changes. It does not blanket-log every model event or duplicate
  the same change through observers. View, export, and sensitive-diff access
  are separate abilities; credentials, OTP/mail secrets, trusted HTML, private
  transcripts/Form values, and protected revision JSON are excluded.

### Production and WB boundaries

- Every production role cutover, migration, cache/store/config change, worker
  recycle, normalize apply, transcription-mode write, and mail/DNS action still
  requires the exact read-only evidence and per-action approval already
  recorded. No secret value belongs in tracked docs.
- WB-PROBE-HF1 and the service-account 20-document probe remain after all
  selected SP/mini-tasks and before WB planning. Connection choice, refresh,
  sanitized output, partial-failure/resume behavior, and sample privacy are
  accepted at that gate.

## Remaining gates, not unanswered big questions

- Exact compatible Composer constraints and Composer approval for Shield/Spatie
  during AUTHZ1, and Activitylog during LOG1.
- Exact measured autosave intervals after ARCH1 browser/payload evidence. The
  initial event/idle implementation target is text blur plus a three-second
  idle fallback, change for discrete inputs, one-second coalescing for Builder
  structural actions, and a five-second fallback for large documents; only one
  autosave write may be in flight. Measurement may tune these without reopening
  the product decision.
- Exact SP3D runner/profile, component/browser ceilings, artifact retention,
  and any dependency repair after the three harnesses are exercised.
- Exact LOG1 retention/backup policy before any destructive pruning schedule.
- Per-action production approvals, operator browser acceptance, LENS pack
  acceptance, and the WB probe run.

These are deliberately retained because evidence or destructive authority is
required. They are not additional architecture groups and must not be silently
converted into implementation assumptions.
