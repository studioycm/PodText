# ROLES1 Implementation Plan - User Roles and Multi-Transcription Gates

Date: 2026-07-13

## Constraints

- Execute only `prompts/pre-13-prompts/roles-gates-roles1-codex-prompt.md`
  version v1.
- No Composer changes and no npm changes.
- No push.
- Research and this plan are written before application code changes.
- Final gate order: requirements sweep, `vendor/bin/pint --test`,
  `vendor/bin/filacheck`, `npm run build`, full `php artisan test` last.
- End with implementation commit, then immediate docs-only hash backfill commit.

## Implementation Steps

1. Add role foundation:
   - Create `App\Enums\UserRole` with the five fixed string values, translated
     labels, colors, ranks, options helpers, and `isAtLeast()`.
   - Add `role` to `users` with default `user`, index, and a data step updating
     existing users to `admin`.
   - Cast `User::$role` to `UserRole`, make it fillable, add
     `hasRoleAtLeast()`, and change `canAccessPanel()` to admin+ only.
   - Update `UserFactory` to create `admin` by default and add explicit role
     states for tests.
   - Add `php artisan users:assign-role {email} {role}` with unknown-email and
     invalid-role refusal.

2. Add gates and macros:
   - Define `super-admin` and `multi-transcription` gates once in
     `AppServiceProvider`.
   - Add a central `MultiTranscriptionSurfaces` support class for registered
     settings paths, card-template gated attributes, role/mode checks,
     card-attribute option filtering, and save-guard overlays.
   - Register Filament component/action macros:
     `multiTranscription(?UserRole $minimum = null)` and `superAdminOnly()`.
     Use `hidden()` inside the macro so existing `visible()` conditions still
     apply.

3. Apply settings gates and save guard:
   - Gate `PublicContentSettings` transcription-policy section with
     `multiTranscription(UserRole::SuperAdmin)`.
   - Filter the content-item `transcription_count` attribute option below the
     same super-admin + multi gate.
   - Apply `MultiTranscriptionSurfaces::overlayUnauthorizedSettings()` in
     `PublicContentSettings::mutateFormDataBeforeSave()` after validation.
   - Gate `AdminUxSettings.transcription_mode` with `superAdminOnly()` and add
     honest helper text.
   - Add `AdminUxSettings::mutateFormDataBeforeSave()` using the same overlay.
   - Leave `transcription_presentation_mode` ungated as not multi-specific.

4. Apply admin working UI gates:
   - Gate `ContentItemForm` featured-transcription section with
     `multiTranscription(UserRole::Admin)` while preserving the existing
     "more than one transcription" condition.
   - In `TranscriptionsRelationManager`, hide the create action when it would
     create an additional transcription and the multi gate denies access; gate
     set-featured in the same way.
   - In `EditEpisodeWorkspace`, hide the pick-existing option/select below the
     admin + multi gate and reject forged `replacement_mode=existing` payloads.

5. Add Users resource:
   - `App\Filament\Resources\Users\UserResource` under Site Management.
   - List page only has table row edit action; no create/delete/bulk delete.
   - Edit page only has role select; no password fields.
   - Resource navigation/access/edit gates require `super-admin`.
   - Edit save guard refuses self-demotion and last-super-admin demotion.
   - Add he/en labels, helpers, notifications, validation messages, and role
     labels.

6. Add tests:
   - Role enum/model rank and panel access matrix.
   - Gate truth table for single/multi modes, admin/super-admin, and admin vs
     super-admin surface minimums.
   - Settings save guard tests proving admin forged state cannot alter or wipe
     registered paths in single or multi mode, and super-admin in multi can
     change them.
   - Card-template per-episode transcription-count option and forged save
     protection.
   - Admin working UI visibility/action forged-state coverage.
   - Users resource access, role edit, self-demotion, last-super-admin guard,
     no create/delete/password fields.
   - Command success and refusal paths.

7. Update durable docs and handoff:
   - Append the white-labeling/server-side-save-guard lesson to
     `ai-development-lessons.md`.
   - Add the single/multi white-labeling contract to
     `docs/phase-02/transcriptions-model-spec.md`.
   - Update `docs/phase-02/current-project-state.md` and
     `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`.
   - Create `docs/phase-02/roles-gates-roles1-handoff.md` with gate outcomes
     before the implementation commit and `## Commit hash` pending.

## Requirement Classification Plan

- Implemented: Jobs 1-5, save guard, tests, docs, and final gate if green.
- Already existed: `TranscriptionMode`, single-mode settings migration default,
  PublicContentSettings custom save pipeline, relation manager/workspace
  surfaces.
- Deferred by prompt: LENS1 vocabulary/count semantics sweep, public rendering
  changes for existing count parts, moderator/transcriber/user panel access,
  permission packages, and hardcoded first-super-admin emails.
- Not applicable: Composer/npm changes, package permission framework,
  custom public auth surfaces.
- Blocked: none at planning time.
