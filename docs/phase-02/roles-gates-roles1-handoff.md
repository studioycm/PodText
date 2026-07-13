# Roles Gates ROLES1 Handoff

Date: 2026-07-13

## Scope

Executed only `prompts/pre-13-prompts/roles-gates-roles1-codex-prompt.md`
version v1.

Added the fixed user role hierarchy, admin-panel role gate, super-admin-only
Users resource, and the two-gate multi-transcription visibility mechanism. The
core deliverable is server-side save enforcement: hidden multi-transcription
settings are overlaid from stored values after validation and before persistence
when the actor cannot see them.

No Composer or npm dependency changes were made.

## Commit hash

`9cd7349 feat: add user roles and multi-transcription visibility gates`

## MAIL1 Verification

- MAIL1 implementation commit was present near HEAD:
  `330350d feat: add mail foundation and email otp form verification`.
- `docs/phase-02/forms-mail-mail1-handoff.md` stamps `330350d` under
  `## Commit hash`.
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md` also stamps
  `330350d` in the MAIL1 row.
- `b507a59 docs: backfill forms mail mail1 hash` is docs-only and updated the
  MAIL1 handoff, current state, and ledger.

## Requirement Classification

- Implemented: `UserRole` enum with translated labels/colors/rank helpers,
  `users.role` migration with existing-user admin promotion, user enum cast and
  admin-panel access gate, role-aware user factory states,
  `users:assign-role`, named `super-admin` and `multi-transcription` gates,
  Filament component/action macros, `MultiTranscriptionSurfaces` registry,
  Admin UX and Public Content settings save guards, gated transcription-policy
  fields, gated per-episode `transcription_count` card-template picker option,
  default single-transcription mode, gated featured-transcription select,
  relation-manager add-second/set-featured affordances, workspace
  pick-existing replacement guard, super-admin-only Users resource, role-edit
  self-demotion and last-super-admin guards, English/Hebrew labels, research,
  plan, state docs, and tests.
- Already existed: `TranscriptionMode`, the settings migration that stores
  `admin_ux.transcription_mode` as `single` by default, Public Content
  Settings' custom save pipeline, the transcription relation manager, the
  episode workspace replacement modal, and the public transcription policy/card
  template settings infrastructure.
- Deferred by prompt: LENS1 public vocabulary/count semantics sweep, public
  rendering changes for already-stored count template parts,
  moderator/transcriber/user panel access, permission packages, hardcoded
  first-super-admin emails, analytics/dashboard work, and public account
  surfaces.
- Not applicable: Composer or npm changes, Spatie Permission/Filament Shield
  adoption, custom public auth surfaces, remote media work, and Prompt 13
  dashboard metrics.
- Blocked: none.

## Files Changed

- Role/access foundation:
  `app/Enums/UserRole.php`, `database/migrations/2026_07_13_010000_add_role_to_users_table.php`,
  `app/Console/Commands/AssignUserRole.php`, `app/Models/User.php`, and
  `database/factories/UserFactory.php`.
- Gates/save guard:
  `app/Providers/AppServiceProvider.php`,
  `app/Support/Transcriptions/MultiTranscriptionSurfaces.php`,
  `app/Settings/AdminUxSettings.php`,
  `app/Filament/Pages/AdminUxSettings.php`, and
  `app/Filament/Pages/PublicContentSettings.php`.
- Admin multi-transcription surfaces:
  `app/Filament/Resources/ContentItems/Schemas/ContentItemForm.php`,
  `app/Filament/Resources/ContentItems/RelationManagers/TranscriptionsRelationManager.php`,
  and `app/Filament/Resources/ContentItems/Pages/EditEpisodeWorkspace.php`.
- Users resource:
  `app/Filament/Resources/Users/*` and
  `app/Filament/Support/AdminNavigationOrder.php`.
- Translations, tests, and docs:
  `lang/en/admin.php`, `lang/he/admin.php`,
  `tests/Feature/RolesGatesTest.php`,
  `tests/Feature/EpisodeWorkspaceTest.php`,
  `tests/Feature/AdminPhase02ResourcesTest.php`,
  `docs/research/roles-gates/*`, this handoff,
  `docs/phase-02/current-project-state.md`,
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`,
  `docs/phase-02/transcriptions-model-spec.md`,
  `docs/phase-02/back-log-triage-2026-07-13.md`, and
  `docs/phase-02/ai-development-lessons.md`.

## Tests Added Or Updated

- Added `tests/Feature/RolesGatesTest.php` for role casting/rank, panel access,
  gate truth table, assign-role command, settings save guards, card-template
  forged-state protection, gated Filament surfaces, Users resource access, and
  role-edit invariants.
- Updated `tests/Feature/EpisodeWorkspaceTest.php` to opt existing-transcription
  replacement coverage into multi mode and to prove forged single-mode
  `replacement_mode=existing` is rejected server-side.
- Updated `tests/Feature/AdminPhase02ResourcesTest.php` for the Users resource
  navigation order and multi-mode opt-ins on admin transcription affordances.

## Save Guard Evidence

- Public Content Settings and Admin UX Settings both call
  `MultiTranscriptionSurfaces::overlayUnauthorizedSettings()` after validation
  and before persistence.
- Admin users saving Public Content Settings leave registered
  `transcription_policy` paths byte-identical in both single and multi mode.
- Admin users saving Admin UX Settings cannot change or wipe
  `transcription_mode`, including forged single-to-multi and multi-to-single
  payloads.
- Card-template forged state is covered for both plain and nested Builder
  payloads: unauthorized actors cannot add a new per-episode
  `content_item.transcription_count` part, and stored hidden count parts are
  preserved rather than wiped.
- Workspace replacement rejects forged `replacement_mode=existing` before the
  replacement action can switch to another existing transcription in single
  mode.

## Local Front Check Report

1. Run `php artisan users:assign-role <your-email> super-admin`.
2. Open Admin UX settings, confirm the mode switch appears, and set
   transcription mode to `single`.
3. Log in as or impersonate the admin account.
4. Confirm there is no Admin UX mode switch.
5. Confirm Public Content Settings show no transcription-policy fields.
6. Confirm the episode form shows no featured-transcription select.
7. Confirm the Transcriptions relation manager shows no add-second
   affordance when the episode already has a transcription.
8. Confirm the Transcriptions relation manager shows no set-featured action.
9. Confirm the workspace replace modal shows no pick-existing option.
10. Save Admin UX settings as admin.
11. Re-login as super-admin and confirm the transcription mode value is
    unchanged.
12. Open Users and change another account's role.
13. Attempt to demote yourself and confirm the save is refused.
14. Attempt to demote the last remaining super-admin and confirm the save is
    refused.
15. Set PodText production to `single` after deploy.

## Commands Run

- Preflight:
  `git status --short --branch`; `git log --oneline -5`; full read of
  `docs/phase-02/ai-development-lessons.md`.
- Research/tools:
  Laravel Boost `application_info`, `database_schema`, `database_query`, and
  `search_docs`; FilamentExamples `search_examples` in short batches plus a
  refined pass; local source inspection with `find`, `grep`, `sed`, and vendor
  source reads.
- Syntax:
  `php -l` passed for changed/new PHP files and edited language files.
- Translation duplicate-key scan:
  `lang/en/admin.php: no duplicate keys detected`;
  `lang/he/admin.php: no duplicate keys detected`.
- Targeted tests:
  `php artisan test tests/Feature/RolesGatesTest.php` passed 11 tests, 103
  assertions.
  `php artisan test tests/Feature/EpisodeWorkspaceTest.php` passed 13 tests,
  113 assertions.
  `php artisan test tests/Feature/AdminPhase02ResourcesTest.php` passed 23
  tests, 419 assertions.
  `php artisan test tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
  passed 13 tests, 310 assertions.
  `php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php`
  passed 24 tests, 316 assertions.
  `php artisan test tests/Feature/PublicFormsSubmissionsTest.php` passed 16
  tests, 121 assertions.
- Final gate:
  requirements sweep passed: no Composer/npm dependency-file diffs; role enum,
  gates/macros, settings overlays, forged workspace guard, Users resource, and
  assign-role command were present.
  First `vendor/bin/pint --test` failed on formatting/import ordering in
  `app/Providers/AppServiceProvider.php` and
  `tests/Feature/RolesGatesTest.php`; `vendor/bin/pint` was run on those files
  and the gate restarted from Pint.
  `vendor/bin/pint --test` then passed.
  First `vendor/bin/filacheck` failed one real issue: the Users table rendered a
  role badge column without a role filter. A role `SelectFilter` was added and
  the gate restarted from Pint.
  Final `vendor/bin/pint --test` passed.
  Final `vendor/bin/filacheck` passed with 0 issues.
  `npm run build` passed.
  `php artisan test` passed 490 tests, 4,336 assertions in 382.935s.

## Tooling Notes

- Laravel Boost was available and used before code changes. It reported PHP
  8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind
  CSS 4.3.2, and MySQL as the runtime database engine.
- Boost docs confirmed Laravel gate context arguments, Filament SettingsPage
  save mutation hooks, Resource edit-page mutation hooks, and Filament/Pest
  action visibility assertions.
- FilamentExamples exposed `search_examples` only. No source/read/detail tool
  was exposed, so access is recorded as search/snippet access only.

## Assumptions

- `transcription_presentation_mode` is not multi-specific: it controls how the
  workspace's current transcription form is presented and remains visible to
  admins in single mode.
- The Filament macros use `hidden()` internally so they compose with existing
  local `visible()` closures without replacing those conditions. This preserves
  the same visibility semantics required by the prompt while avoiding clobbering
  surface-specific visibility rules.
- The real database default for new users remains `user`, while the test
  factory defaults to `admin` to preserve existing admin tests.
- The migration promotes all existing users to `admin`; Yoni creates the first
  super-admin after deploy with the assign-role command.

## Deferred Issues

- LENS1 owns public copy, resource vocabulary, and count-semantics cleanup.
- Moderator, transcriber, and user roles are defined but have no admin panel
  access in v1.
- No first-super-admin email is hardcoded; first promotion is an operator step.

## Current Git Status Before Final Gate

`main...origin/main [ahead 1]` with ROLES1 code, tests, research, and docs
modified or added; no Composer/npm files modified.
