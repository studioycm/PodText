# ARCH1 Per-User Drafts and Authorization Research

Date: 2026-07-16

Status: research and operator-decision record through Group 15 and approved
BQ1–BQ6; no application code, tests, schema, dependency, implementation plan,
or prompt

## Authority and purpose

This note preserves the operator's new steering after ARCH1-A–S and must be
read with `07-sp3d-pre-research.md`. It records the approved per-user working
draft model, immutable checkpoint behavior, retention/clone rules, and the new
authorization prerequisite. It also records the current installed-code and
latest package evidence used to select Spatie Laravel Permission plus Filament
Shield.

## Decisions approved after ARCH1-S

| ID | Approved decision | Durable consequence |
|---|---|---|
| 13.1 | Hybrid autosave plus immutable checkpoints, refined so autosave is per user. | Each user has an isolated mutable working draft for each Template/Form parent. Autosave updates only that user's row. Explicit **Save Draft** snapshots it into a new immutable revision. A dirty-navigation warning offers a Save Draft checkpoint rather than implying that the autosaved working copy is lost. |
| 13.2-D | Use the recommended dynamic permission architecture. | PodText adopts Spatie Laravel Permission plus Filament Shield and keeps an application-owned ability catalog as the stable source of permission keys, groups, bilingual labels, and default role grants. |
| 13.3 | Accept the exact immutable Form revision mounted by the visitor while the parent remains enabled and the signed mount authorization remains valid. | Republishing alone does not destroy a visitor's in-progress form. Disabling/archiving the parent prevents submission. The audited initial operational default is a config-backed two hours for verified and unverified Forms; OTP retains its separate five-minute expiry. |
| 13.4 | Retain unreferenced immutable drafts by age plus count floor. | Keep them for at least 90 days and always keep the latest 20 per parent, whichever preserves more. Pruning requires the approved composite backup and report. Published/referenced/current revisions remain retained. |
| 13.5 | Clone an explicitly selected revision into a new unprotected draft parent. | The clone receives a new ULID and semantic key. It copies no history, references, default assignment, published pointer/state, protection, or activity history. |
| 14.1 | Filament Shield + Spatie Permission + owned PodText ability catalog. | Shield is the role/permission management UI; Spatie stores assignments and registers permissions with Laravel Gate; PodText owns names and policies. |
| 14.2 | Complete the authorization foundation before ARCH1. | `AUTHZ1` becomes a prerequisite. Do not introduce Shield only for Templates/Forms while the existing enum/rank gates remain a competing authority elsewhere. |
| 14.3 | Role bundles plus optional direct user grants; no explicit-denial layer in v1. | Roles provide normal grants. Direct permissions are exceptional and super-admin-only in v1; their owned mutation boundary records grantor/reason/advisory review/revocation metadata. A custom deny-precedence or automatic-expiry system is not introduced. |
| 14.4 | Super-admin global ability bypass with safety invariants. | Last-super-admin and self-demotion protection, data-integrity checks, confirmation requirements, and other non-authorization safety rules still apply. |
| 14.5 | Mix meaningful mini-abilities with grouped management. | Do not create a permission for every UI click. Keep atomic business/security abilities, group them in Shield for bulk assignment, and allow individual overrides. Field/area abilities are limited to sensitive clusters such as protected revisions, security policy, imports/restores, locks, credentials, and PII. |
| 15.1 | Private editing with controlled visibility/adoption. | Only the owner edits a mutable working draft. `view-other-working-drafts` grants read-only visibility; `adopt-working-draft` copies it into the acting user's own working draft. Nobody edits or overwrites the original. |
| 15.2 | Explicit checkpoints join shared immutable history. | Authorized collaborators may view the attributed draft revision. It changes no published/default pointer and does not alter any user's working draft. |
| 15.3 | Preserve divergent work. | A stale-base working draft may still create an immutable checkpoint, recording its base and an outdated/divergent marker. Publication is blocked until authorized comparison and explicit rebase or acceptance; no automatic JSON merge or last-write-wins publication. |
| 15.4 | Permission-based publication of saved checkpoints. | A user with the relevant publish ability may preview/confirm and publish any authorized immutable checkpoint regardless of author. Another user's mutable working draft can never be published directly. |
| 15.5 | Preserve inactive working drafts indefinitely but hide them when stale. | After 90 inactive days, mark/hide the working draft from default lists. Do not automatically delete it. Authorized users may restore, checkpoint, adopt, or explicitly discard it subject to ability checks. |
| BQ1-B | Multiple roles plus exceptional direct grants. | Effective permission is the additive union of role/direct grants. Migration assigns exactly the one role represented by the legacy column; no synthetic highest-role/rank and no automatic universal `user` role. |
| BQ2-B | Strict delegated role assignment. | The deployed catalog owns ability definitions. Super-admins manage role bundles/assignments. An explicitly authorized admin may assign only existing delegable, non-privileged roles whose complete grant set is within the actor's fresh authority; no self/reserved/super-admin/direct-grant/catalog management. |
| BQ3-A | Exactly one active published default per registered runtime Template family. | Default replacement is atomic. The current default cannot close/archive without a validated replacement in the same transaction. |
| BQ4-A | Interim submission-PII deletion freeze. | Separate view/export abilities; no routine hard-delete/prune UI until retention/privacy/anonymization policy exists. Preserve a future legal/privacy erasure boundary. |
| BQ5-A | Fail-closed AUTHZ1/ARCH1 cutover. | Analyze raw source; report all corrupt/duplicate/unknown/missing/ambiguous/unresolved data; mutate nothing; repair separately and rerun. Never invent identity or historical provenance. |
| BQ6-A | Separately accepted forward sequence. | AUTHZ1 foundation/cutover, then ARCH1 shared foundation/Templates/Forms/cutover, then L10N/ADM/LENS, SP3D, SP4, and LOG1. Major boundaries stop for operator/Fable acceptance. |

## Per-user working-draft boundary

The approved model distinguishes mutable recovery state from immutable history:

```text
Template/Form parent
├── working draft: parent + user A (mutable autosave)
├── working draft: parent + user B (mutable autosave)
├── working draft: parent + user C (mutable autosave)
└── immutable revisions
    ├── explicit Save Draft checkpoints
    └── published revisions
```

Required invariants already implied by 13.1 and ARCH1-L:

- a working draft is uniquely owned by a parent/user pair and records its base
  revision/checksum;
- autosave never edits a different user's row and never creates noisy revision
  history;
- an explicit checkpoint records author and base revision and does not change a
  published/default pointer;
- publication snapshots or selects an immutable revision and moves the parent
  pointer atomically under the approved parent-only publication lock;
- “dirty” means different from the last explicit immutable checkpoint. The UI
  must distinguish “Save draft and leave” from leaving with the recoverable
  autosaved working copy and no new checkpoint.

Group 15 fixes the collaboration boundary:

- working drafts are private for mutation; authorized visibility is read-only;
- adoption clones into the acting user's own working draft and never transfers
  or overwrites the source;
- explicit checkpoints are shared immutable history with author/base metadata;
- a stale-base checkpoint is preserved and marked divergent, while publication
  waits for explicit compare/rebase/acceptance;
- an authorized publisher may publish another author's immutable checkpoint
  after preview/confirmation, but never another user's mutable working row;
- inactive working drafts are marked stale and hidden after 90 days but are not
  automatically deleted.

The audited operational defaults are in
`11-bq-decisions-and-defaults-audit.md`: lazy creation on first material
mutation; one acknowledged autosave in flight; event/idle coalescing rather
than every-keystroke writes; server-authoritative offline/failure state; owned
internal navigation choices plus generic browser-close fallback; logical
discard tombstones; and stable node IDs for selective compare/copy.

## Current authorization implementation

Current PodText code has no `spatie/laravel-permission` or
`bezhansalleh/filament-shield` dependency. Authorization currently uses:

- one `users.role` value cast to the fixed `UserRole` enum;
- rank comparison through `User::hasRoleAtLeast()`;
- `super-admin` and `multi-transcription` gates in `AppServiceProvider`;
- Filament schema/action macros that hide those gated surfaces;
- a super-admin-only Users Resource with last-super-admin/self-demotion guards.

That implementation is valid shipped history but cannot provide operator-
managed mini-abilities. AUTHZ1 must preserve its protection while replacing
rank checks with explicit abilities.

## Latest package and framework evidence

Research was refreshed on 2026-07-16 against the installed Laravel 13.19.0,
Filament 5.6.7, Livewire 4.3.3, and PHP 8.4 environment.

- Spatie Laravel Permission supports Laravel 12/13 in current v7/v8 lines and
  registers permissions with Laravel Gate:
  <https://spatie.be/docs/laravel-permission/v8/prerequisites> and
  <https://spatie.be/docs/laravel-permission/v8/introduction>.
- Filament Shield 4.x supports Filament 4/5. Shield 4.2 adds Laravel 13 support
  and currently depends on Spatie Permission `^6|^7`, so the expected compatible
  PodText line is Shield `^4.2` plus Spatie Permission `^7`; exact dependency
  resolution must still be verified at implementation:
  <https://github.com/bezhanSalleh/filament-shield/releases> and
  <https://github.com/bezhanSalleh/filament-shield>.
- Shield can generate Resource policy permissions, add Resource-specific policy
  methods, expose configured custom permissions, group them in its Role
  Resource, and localize labels. It does not provide user-role assignment UI by
  itself, so PodText's Users Resource remains responsible for safe assignments.
- Filament automatically re-authorizes standard Resource policy access on each
  Livewire request. Custom actions/pages/business operations still require
  explicit authorization at their execution boundary:
  <https://filamentphp.com/docs/5.x/advanced/security>.
- Laravel policies remain the application enforcement boundary; hiding a
  navigation item, action, field, or page is not sufficient authorization:
  <https://laravel.com/docs/13.x/authorization>.

## Approved authorization responsibility map

```text
App\Auth\Ability (stable keys)
        |
        v
App\Auth\AbilityCatalog
group + HE/EN label + description + default role grants
        |
        +------> idempotent Spatie permission/role synchronization
        |
        +------> Laravel model policies and explicit custom-action checks
        |
        +------> Filament Shield Role Resource and grouped assignment UI
        |
        +------> PodText Users Resource for role/direct-grant assignment
```

Spatie's database becomes the assignment authority, but generated Shield names
must not become an accidental public contract. The owned catalog prevents
renames, generator defaults, or UI grouping from silently changing application
authorization.

Approved initial ability groups consistent with 14.5 are:

| Group | Examples of meaningful atomic abilities |
|---|---|
| Access/discovery | panel access; view Resource/Page; view protected metadata |
| Working drafts | create/edit own working draft; view another draft; adopt another draft; discard own draft |
| Revision checkpoints | create checkpoint; view history; compare revisions; restore as new revision |
| Publication | publish; disable/unpublish; archive/restore parent |
| System presets/defaults | activate preset; manage family defaults; view/export protected revision |
| Portability/lifecycle | export configuration; import; restore; manage dataset/field locks; prune with report |
| Public Form submissions | view submission; change status; view PII; export PII; delete when policy permits |
| Security/administration | manage roles; manage permissions; direct user grants; last-super-admin-safe user management |

The final catalog must be audited across all existing Resources, Pages, custom
actions, settings writers, imports, restores, backups, Workbench, Horizon, and
special gates before ARCH1 begins. “Grouped” means bulk assignment and clear
presentation, not replacing atomic server-side checks with one broad UI flag.

## Migration and dependency risks

- Spatie documents that the User model must not expose conflicting `role` or
  `roles` properties/relations. PodText currently has a singular `users.role`
  column/cast, so AUTHZ1 needs a lossless staged backfill/rename-or-removal
  boundary; it cannot simply add `HasRoles` and leave both authorities active.
- Existing role slugs and every production assignment must be preserved as
  seeded Spatie roles. The current `users:assign-role` command, User Resource,
  panel-access rule, last-super-admin guard, factories, and tests need an
  explicit compatibility disposition during later planning.
- Initial grants are compatibility-first. `admin` and `super-admin` keep their
  current effective panel audiences; `moderator`, `transcriber`, and `user` do
  not gain panel access merely from their names. Panel access is the explicit
  `panel.admin.access` ability. Horizon is the separate
  `system.horizon.view` ability.
- Rank inheritance must stop being runtime authority once abilities are live.
  Existing roles may remain named presets, but policies check permissions.
- Shield permission generation must be constrained by the owned catalog and
  reviewed. Regenerating package defaults must not create unexpected grants or
  remove custom mini-abilities.
- Permission caches must be invalidated after role/permission changes and
  during deployment/backfill. Long-lived workers must not retain stale access.
- Super-admin bypass applies to authorization only. It cannot bypass data
  validation, revision conflicts, retention/reference protection, confirmation,
  last-super-admin safety, or operational feature modes such as
  `transcription_mode=single`.
- Every UI/command/import/migration assignment path must use one transactional
  boundary. With multiple roles, last-super-admin means distinct users holding
  that protected role. The singular `users:assign-role` command must gain
  explicit add/remove/replace semantics or be replaced; it cannot silently
  become `syncRoles()`.
- Composer installation, package migrations, and role backfill are future
  AUTHZ1 implementation actions. They were not executed by this research task.

## Sequence impact

The controlling forward order is now:

```text
operator/Fable scope review
    -> AUTHZ1 authorization foundation and verified role migration
    -> ARCH1 versioned Template/Form aggregates and no-loss cutover
    -> ADM/L10N/LENS work that affects final measured surfaces
    -> SP3D monolith deletion + browser repair/calibration/enforcement
    -> SP4 surviving-settings reads/change sets
    -> LOG1
    -> approved production tasks and WB-PROBE-HF1/probe at their recorded gates
```

No implementation prompt or file-by-file plan is authorized by this note.

## Decision closure and remaining gates

Groups 1–15 and BQ1–BQ6 are settled. The detailed audited defaults and
corrections are controlling in `11-bq-decisions-and-defaults-audit.md`.
`10-pending-decision-question-queue.md` now indexes only implementation-
research, evidence, dependency, destructive-action, manual-review, and final
acceptance checkpoints. Do not restart at Group 16 or reconstruct the former
minor-question list.
