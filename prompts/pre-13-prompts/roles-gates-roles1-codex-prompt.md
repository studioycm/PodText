# Codex Prompt — ROLES1: User Roles + Multi-Transcription Visibility Gates

Prompt version: v1 — 2026-07-13. (Standing rule: if the committed prompt
differs from the version named in the kickoff, stop and ask.)

Work in the current local clone of `studioycm/PodText`.

ONE run: add the role system and the two-gate visibility mechanism that
makes multi-transcription capability invisible below super-admin AND
invisible to everyone (including super-admin, except the switch itself)
while the app runs in single-transcription mode. This is white-labeling by
configuration: one codebase, per-deployment illusion. The ontology/label
sweep (public vocabulary, count semantics, resource episode-language) is
the NEXT run (LENS1) — do not start it here.

Standing runner rules: read `docs/phase-02/ai-development-lessons.md` IN
FULL during preflight; research note + implementation plan docs BEFORE
code; no push unless asked; no `filacheck --fix`; fixture-owned tests;
en+he translations for every new label; NO Composer/npm changes.

CANONICAL RUN ENDING (standing): implementation commit (`## Commit hash`
pending) → immediately a docs-only commit stamping the hash into handoff +
ledger (`docs: backfill roles gates roles1 hash`).

Handoff: `docs/phase-02/roles-gates-roles1-handoff.md`; gate outcomes
written in before committing; Local Front Check Report = numbered MANUAL
OPERATOR STEPS (imperative).

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test`
→ `vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change;
record every run).

## Preflight

```bash
git status --short --branch
git log --oneline -6
```

Expect MAIL1's implementation + backfill commits at or near HEAD. If MAIL1
has not landed, STOP and ask. Verify MAIL1 self-stamped its hash per the
canonical ending; report in the handoff. Stop on unexpected app-code dirt.

## Decisions this run implements (Yoni + Fable, final)

- Roles: `super-admin`, `admin`, `moderator`, `transcriber`, `user` —
  strictly hierarchical. Today only two humans exist: Yoni (super-admin)
  and the client (admin). Moderator/transcriber/user are defined but
  dormant (no panel access v1).
- Two gates, combined: a multi-transcription surface is visible iff
  `AdminUxSettings.transcription_mode === multi` AND the actor meets that
  surface's minimum role. The mode SWITCH itself is visible to super-admin
  ONLY, regardless of mode — for an admin, the capability must not appear
  to exist at all.
- Minimum roles per surface kind: settings knobs (`transcription_policy`
  fields, presentation options) = super-admin; admin working UI (featured
  transcription select, transcriptions relation manager multi affordances,
  workspace "replace with existing") = admin.
- Default `transcription_mode` for fresh deployments = `single`. PodText
  production itself runs `single`.
- Server-side enforcement is mandatory: hidden-by-gate values can never be
  changed OR wiped by an actor who cannot see them.

## Job 0 — verification carried items

1. Verify MAIL1's canonical ending (hash stamped in its handoff + ledger);
   note the result. Fable may list MAIL1 audit corrections in the kickoff —
   execute any listed there as part of this job.
2. Append to `docs/phase-02/ai-development-lessons.md`: per-deployment
   white-labeling is done with mode flags + role gates in ONE codebase —
   never forks; illusion features require server-side save guards, not
   only visibility.

## Job 1 — role foundation

- `App\Enums\UserRole` string-backed enum: `SuperAdmin = 'super-admin'`,
  `Admin = 'admin'`, `Moderator = 'moderator'`,
  `Transcriber = 'transcriber'`, `User = 'user'`; label contract with
  he+en translation keys; a rank map and
  `User::hasRoleAtLeast(UserRole $role): bool`.
- Migration: `role` string column on `users`, default `'user'`, indexed;
  cast to the enum. DATA STEP in the same migration: all EXISTING users
  get `'admin'` (there are exactly two accounts; no privilege is lost, no
  lockout is possible). Down: drop column.
- `php artisan users:assign-role {email} {role}` console command
  (validated against the enum; clear output; refuses unknown email).
  Deploy note: after deploy, Yoni promotes himself with
  `users:assign-role <his email> super-admin` — the command is how the
  first super-admin comes to exist; no emails are hardcoded anywhere.
- NO new packages: five fixed roles need one enum column, not a
  permission framework.

## Job 2 — access gates

- `User::canAccessPanel()`: requires `hasRoleAtLeast(Admin)` for the admin
  panel (moderator/transcriber panel access is future scope — document the
  intended map in the research doc, implement admin+ only).
- Named gates (or equivalent policy methods), defined once:
  `super-admin` (role check) and `multi-transcription` (mode AND minimum
  role — accepts the surface's minimum role, default super-admin).
- Tests: each role × panel access; gate truth table (mode single/multi ×
  role admin/super-admin × surface minimum).

## Job 3 — the visibility mechanism (registry + macros + save guard)

- `MultiTranscriptionSurfaces` registry: one central list of gated
  settings state paths, each with its minimum role. It drives everything:
  field visibility, the save guard, and tests.
- Component macros (service-provider registered):
  `->multiTranscription(?UserRole $minimum = null)` → applies
  `visible()` via the gate; `->superAdminOnly()` → pure role visibility
  (for the mode switch and the Users resource role field).
- SAVE GUARD (the correctness core): on every settings save path that can
  contain registered paths (`PublicContentSettings` monolith,
  `AdminUxSettings`), re-overlay the STORED value for every registered
  path the actor's gate does not allow — after validation, before persist.
  A crafted Livewire payload must not be able to alter them. Tests: an
  admin saving settings leaves every gated value byte-identical (both
  modes); a forged state attempt is neutralized; super-admin in multi mode
  can change them normally.

## Job 4 — apply the gates to today's surfaces

- Settings knobs (super-admin minimum, mode-gated):
  `transcription_policy` group fields (`public_mode`, `count_mode`,
  `show_multiple_transcriptions_on_item_page`) in the monolith;
  `transcription_presentation_mode` in AdminUxSettings IF research shows
  it is multi-specific (classify it explicitly either way); the
  card-template PICKER entry for the per-episode transcription-count
  element (its public rendering is LENS1's scope — here only stop new
  template usage below the gate).
- The mode switch `AdminUxSettings.transcription_mode`:
  `->superAdminOnly()`, always visible to super-admin in both modes, with
  honest helper text (he+en) that flipping it reveals/hides the
  multi-transcription feature set app-wide. Its stored value must survive
  admin saves of the same page (save guard covers it).
- Admin working UI (admin minimum, mode-gated — hidden in single mode for
  EVERYONE including super-admin): `featured_transcription_id` select in
  the system item form; the TranscriptionsRelationManager's
  create-additional affordances; the workspace "replace transcription"
  modal's PICK-EXISTING option (create-fresh replacement stays in both
  modes). Research enumerates any further multi affordances and gates them
  with the same mechanism; list them in the plan doc.
- Default flip: `AdminUxSettings.transcription_mode` default becomes
  `single` for fresh installs (settings class default + any seeder).
  Stored values on existing installs are untouched by code — the handoff
  instructs Yoni to set PodText production to `single` in the UI (front
  check step).

## Job 5 — Users resource (super-admin only)

Minimal by decision: LIST + ROLE EDIT only. Table: name, email, role
(badge), created date (day-first). Edit: role Select only (enum options,
helper text). Guards, tested: a super-admin cannot demote themself; the
last remaining super-admin cannot be demoted by anyone; no create, no
delete, no password fields in v1. Navigation under ניהול אתר, visible via
the super-admin gate. he+en labels.

## Tests

Jobs 1–5 as listed; plus regression: existing settings tests stay green
with the save guard active; the workspace single-lens flow is unchanged in
single mode; panel access denied below admin. Full gate per header order.

## Docs and handoff

Ledger row `ROLES1 - User roles and multi-transcription gates`;
`current-project-state.md`; the decision set above recorded in
`docs/phase-02/transcriptions-model-spec.md` (or the guideline the
research identifies as the durable home) as the single/multi
white-labeling contract; research/plan docs BEFORE code
(`docs/research/roles-gates/00-roles1-research.md`,
`00-roles1-implementation-plan.md` — research includes the full inventory
of multi affordances found and the intended future role/access map);
handoff per header rules. Local Front Check Report (operator steps): run
the promote command for your account → the mode switch appears in Admin
UX settings and shows `multi`→ set it to `single`; log in as (or
impersonate) the admin account → no switch, no transcription-policy
fields, no featured select, no relation-manager add-second, no
pick-existing in the replace modal; save Admin UX settings as admin →
re-login as super-admin → the mode value is unchanged; open Users →
change a role → self-demotion and last-super-admin guards refuse; set
production to single after deploy.

Commit: `feat: add user roles and multi-transcription visibility gates`
Then the canonical docs-only backfill commit (see header).

End with exactly:

```text
Roles and gates ROLES1 is complete. Waiting for Yoni review before continuing.
```
