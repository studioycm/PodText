# ROLES1 Research - User Roles and Multi-Transcription Gates

Date: 2026-07-13

## Scope

Executed for `prompts/pre-13-prompts/roles-gates-roles1-codex-prompt.md`
version v1. This run adds the fixed role hierarchy and a two-gate
multi-transcription visibility/save mechanism. LENS1 owns the public vocabulary
and count semantics sweep.

## Preflight

- `git status --short --branch`: `main...origin/main [ahead 1]`, clean working
  tree.
- `git log --oneline --decorate -n 12`: MAIL1 implementation and backfill were
  present near HEAD, followed by the ROLES1 prompt commit.
- MAIL1 canonical ending was verified:
  - `330350d feat: add mail foundation and email otp form verification`
    appears in `docs/phase-02/forms-mail-mail1-handoff.md` under
    `## Commit hash`.
  - The active ledger row in
    `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md` also names
    `330350d`.
  - `b507a59 docs: backfill forms mail mail1 hash` is docs-only and touched
    the MAIL1 handoff, current state, and ledger.
- `docs/phase-02/ai-development-lessons.md` was read in full before planning.
- Laravel Boost `database_query` returned one local user row. The prompt says
  production has two humans; the migration will still promote every existing
  row to `admin` without printing or hardcoding emails.

## Installed Versions and Schema

Laravel Boost `application_info` reported:

- PHP 8.4
- Laravel 13.19.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Tailwind CSS 4.3.2
- MySQL as the runtime database engine

Boost `database_schema` showed `users` currently has the default Laravel
columns only: `id`, `name`, `email`, `email_verified_at`, `password`,
`remember_token`, timestamps, primary key, and unique email index. No `role`
column exists yet.

## Boost Documentation Notes

Boost `search_docs` confirmed the relevant installed-version APIs:

- Laravel gates can accept additional context arguments through
  `Gate::allows('ability', [$argument])`.
- Filament SettingsPage save flows support `mutateFormDataBeforeSave()` after
  validation and before persistence.
- Filament Resource edit pages support `mutateFormDataBeforeSave()` for
  server-side record-save guards.
- Filament tests support resource/page form filling and action visibility
  assertions.
- Filament action visibility can be asserted with `assertActionHidden()` and
  `assertActionVisible()`.

## FilamentExamples Notes

FilamentExamples exposed only `search_examples`; no read/fetch/source-detail
tool was available beyond search snippets.

Relevant snippets:

- `v4/full-projects/spatie-roles-permissions/app/Models/User.php`: shows
  `canAccessPanel(Panel $panel)` as the panel entry point. PodText adaptation:
  use the local enum column instead of Spatie Permission.
- `v4/full-projects/spatie-roles-permissions/app/Policies/UserPolicy.php`:
  shows restricting user management through policy-style role checks. PodText
  adaptation: keep Users resource super-admin only with static Resource guards
  and edit-page invariants.
- `v4/full-projects/spatie-roles-permissions/app/Filament/Resources/Users/*`:
  confirms the current Filament 5 Resource/Schemas/Tables/Pages split. PodText
  adaptation: list + edit only, no create/delete/password fields.
- `v4/full-projects/cms-blog-system-shield/app/Filament/Resources/Posts/Schemas/PostForm.php`:
  shows role-aware `visible()` closures on form fields. PodText adaptation:
  register reusable `multiTranscription()` and `superAdminOnly()` macros.
- `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/RelationManagers/MatchesRelationManager.php`:
  shows relation-manager header and record actions in the installed v5 style.
  PodText adaptation: gate create-additional and set-featured actions without
  changing the relation manager's core edit behavior.
- `v4/full-projects/hotel-management-bookings/app/Filament/Hotel/Pages/MyHotel.php`:
  shows custom page save flow around `$this->form->getState()`. PodText
  adaptation: preserve the existing PublicContentSettings custom save pipeline
  and overlay stored guarded paths after validation.

Patterns intentionally not copied:

- Spatie Permission and Filament Shield examples were not copied because the
  prompt explicitly requires one enum column and no new packages.
- String icon examples were not copied because PodText uses
  `Filament\Support\Icons\Heroicon` enum values.

## Current Code Inventory

Role/access baseline:

- `App\Models\User::canAccessPanel()` currently allows any authenticated user
  into the admin panel when the panel id is `admin`.
- No user policy or roles framework exists.
- `app/Console/Commands` classes are auto-discovered by Laravel's
  `withCommands()`.
- `database/factories/UserFactory.php` currently creates users without a role.

Settings and mode baseline:

- `App\Enums\TranscriptionMode` already has `single` and `multi`.
- `database/settings/2026_07_12_000001_add_episode_workspace_admin_ux_settings.php`
  already defaults `admin_ux.transcription_mode` to `single`.
- `App\Settings\AdminUxSettings` has no property default yet; add one for
  missing/fresh in-memory settings behavior.
- `AdminUxSettings` page has `transcription_mode` and
  `transcription_presentation_mode`.
- `transcription_presentation_mode` controls how the workspace transcription
  section is displayed (`collapsible`, `modal`, `slideover`) and is useful in
  single mode. It is not classified as multi-specific for ROLES1.
- `PublicContentSettings` has a custom save implementation that validates and
  normalizes before `settings->fill($data)->save()`, making it the right place
  for the save guard.
- `ManagePublicForms` saves only `public_forms`, so it cannot contain ROLES1
  registered gated paths.

Multi-transcription surfaces found:

- `PublicContentSettings` transcription-policy fields:
  `transcription_policy.public_mode`, `transcription_policy.count_mode`, and
  `transcription_policy.show_multiple_transcriptions_on_item_page`.
- Public card-template builder attribute option
  `content_item.transcription_count`, which is the per-episode transcription
  count element named by the prompt.
- `AdminUxSettings.transcription_mode` mode switch.
- `ContentItemForm::featured_transcription_id` select.
- `TranscriptionsRelationManager` create action when an owner already has a
  transcription.
- `TranscriptionsRelationManager` set-featured action.
- `EditEpisodeWorkspace` replacement modal's `existing` replacement option and
  `existing_transcription_id` select.

Related but not gated here:

- `content_group.transcription_count`, `author.transcription_count`, and
  `contributor.transcription_count` card attributes are aggregate/contributor
  metrics already used by public front tests. The prompt only names the
  per-episode count entry, so ROLES1 gates `content_item.transcription_count`.
- Public rendering of existing count parts remains LENS1 scope.
- `transcription_presentation_mode` is not multi-specific and remains visible
  to admins with the rest of workspace presentation settings.

## Future Role/Access Map

Implemented in ROLES1:

- `super-admin`: admin panel access, Users resource, mode switch, and every
  multi-transcription surface when mode is `multi`.
- `admin`: admin panel access; multi-transcription working UI only when mode is
  `multi` and the surface minimum is `admin`; no Users resource or mode switch.
- `moderator`, `transcriber`, `user`: no admin panel access in v1.

Future intent, not implemented in ROLES1:

- `moderator`: potential content moderation/publishing surfaces.
- `transcriber`: potential transcript workspace-only surfaces.
- `user`: authenticated public/account surfaces only.

## Risks and Guardrails

- The save guard is the core correctness point: hidden settings must be
  overlaid from stored settings after validation, before persistence.
- Admin tests that use `User::factory()->create()` need factory users to remain
  admin-capable by default; real database default remains `user`.
- The Users resource needs server-side edit-page guards so a forged form cannot
  self-demote or remove the last remaining super-admin.
- The workspace replacement action needs an action-time gate check so a forged
  `replacement_mode=existing` cannot switch to another existing transcription
  in single mode.
