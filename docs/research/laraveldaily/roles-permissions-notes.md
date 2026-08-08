# LaravelDaily: Roles and Permissions in Laravel 13 — course notes

- **URL**: https://laraveldaily.com/course/roles-permissions — 14 lessons, 57m, video **with full text bodies**, **Apr 2026**
- **Read**: 2026-08-08, from `raw/roles-permissions.json` — 3 lessons in full, all 14 digested
- **Verified against**: this repo's authz surface (`spatie/laravel-permission 8.3.0`, `filament-shield 4.3.1`, `UserRole` enum, 4 policies, gates)
- **Staleness verdict**: **passes** — Laravel 13 era throughout (starter-kit scaffolding, current Pest idiom, Fortify actions)

**Headline: the course's whole arc — boolean → enum role column → policies → spatie — is the
road PodText is already standing on, and it validates the current position rather than
pushing past it.** PodText sits at "enum role + gates + policies, spatie installed but
dormant", which is exactly the point in the course's progression before the spatie lesson,
held deliberately (the AUTHZ dormancy record).

---

## 1. The course's progression, and where this repo sits on it

| stage (lesson) | mechanism | PodText |
| --- | --- | --- |
| 1 — separate areas | `Admin/`/`User/` controllers, route prefixes, `IsAdminMiddleware`, per-role layouts | superseded by **Filament panels** — the panel is the area; no transfer needed |
| 2 — gates → policies | gates in provider outgrow it → per-model Policy classes | **current practice**: 3 gates (`AppServiceProvider:205-206`, Horizon) + 4 policies |
| 3 — roles table + enum | own `roles` table, int-backed enum over magic numbers | PodText uses the *lighter* form: `users.role` cast to `UserRole` with `hasRoleAtLeast()` hierarchy — no join needed for a strict hierarchy |
| 4 — multi-role pivot | `BelongsToMany`, policy checks against collections | not needed — roles here are hierarchical, not additive |
| 5 — spatie package | `HasRoles` trait, string-backed enum, `assignRole()` on registration | **installed, dormant** — see §2 |
| 6–12 — teams/tenancy | clinic build: team_id scoping, current-team switching, per-team roles | N/A — single-team app |

Two framings the course states that are worth keeping verbatim in spirit:

- **Test the permission logic first.** Lesson 1 opens with it: permissions are "one of the
  main security risks for projects, with the most significant consequences if not tested
  properly", and every lesson ships Pest tests (`assertForbidden` on the other role's routes).
  Matches house practice; PodText's policy/gate tests exist and this is the external
  restatement of why they are not optional.
- **The UI check is not the security check.** Lesson 2's pivot moment: hiding buttons with
  `@can` still leaves the URL open — "we have a HUGE security issue" — and the fix is
  `Gate::authorize()` server-side. Obvious, but it is the single most common authz bug class,
  and the course builds its whole structure around it.

## 2. The spatie lesson — and the one sentence that matters for Shield activation

The mechanics (trait, string-backed enum, `assignRole()` in the registration action, seeding,
factory states switching from `roles()->attach()` to `assignRole()`) are standard. The
load-bearing part is the aside:

> Spatie recommends you check by **permissions instead of roles**, in most scenarios. You
> assign permissions to roles, then check `$user->can('edit-task')` in your policies. This
> way, adding a new role doesn't require changing any policy code.

The course's own simple example then checks roles (`hasRole(Role::Administrator)`) anyway,
deferring permission-checks to the teams project. **For PodText this is the design rule for
the day the dormant spatie/Shield layer activates**: policies should end up asking
`can('permission')`, not `hasRole()`, or the activation just re-implements `hasRoleAtLeast()`
with extra tables. Until then, the enum column *is* the course's stage-3 answer and needs no
apology — the course explicitly treats package-free roles as legitimate, not as a beginner
mistake.

### An internal inconsistency worth noting

The spatie lesson adds `protected $with = ['roles']` to `User` — the exact model-level eager
loading the same publisher's Eloquent course (lesson 22) warns against as hidden per-query
cost. PodText has zero `$with` anywhere; if Shield activates, prefer explicit eager-loading
at the call sites (or spatie's own permission cache, which exists for precisely this) over
copying that line.

## 3. Smaller observations

- Lesson 2's Blade sample contains a syntax typo (`@can(''update-task', $task)`) — same
  course-samples-are-not-copy-paste rule as the exceptions course's transaction block.
- The course registers policies by convention (Task → TaskPolicy, auto-discovered). Laravel 13
  also ships the `#[UsePolicy]` model attribute (one of the 24 in
  [eloquent-notes](eloquent-notes.md) §2) for non-conventional pairings — not mentioned by
  the course; PodText's 4 policies are convention-named, so nothing to change.
- Lesson 12 (`managing-tasks-permissions-global-scopes`) combines permissions with global
  scopes for team-scoped visibility — the closest analogue here is the public-visibility
  query contract, which PodText implements as explicit scopes rather than global ones. The
  taxonomy guideline's warning about global scopes ("other developers may not even know the
  scope exists") is repeated nearly word-for-word by the Eloquent course; the two courses
  agree with the house choice.
- Lesson 13 (outro) links a "Roles/permissions in **Filament 4**" resource — the only place
  the catalogue connects authz to Filament. Not fetched; candidate for the filament-4 notes
  pass.

## 4. Proposals

**None.** The course validates the current staged position. The one actionable item —
permission-checks-over-role-checks — is contingent on the AUTHZ dormancy decision being
revisited, and belongs to that record's owner, not to this note.

## 5. Sources

- Read in full:
  [separate admin/user areas](https://laraveldaily.com/lesson/roles-permissions/separate-admin-user-areas),
  [gates & policies](https://laraveldaily.com/lesson/roles-permissions/adding-gates-policies),
  [spatie package](https://laraveldaily.com/lesson/roles-permissions/spatie-laravel-permission-package).
  Digested: the remaining 11 (headings + identifiers in [index.md](index.md)); the teams
  half (lessons 6–12) was deliberately left at digest level as N/A for a single-team app.
- This repo, measured 2026-08-08: `composer show` (spatie 8.3.0, shield 4.3.1);
  `User.php` (`role` fillable, `UserRole` cast, `hasRoleAtLeast`); `app/Policies/` (4);
  `Gate::define` sites (`AppServiceProvider:205-206`, `HorizonServiceProvider:33`);
  `$with` count (0).
- Bonus AI skill (lesson 14, 22.5k chars in corpus): noted, **not installed** — standing
  position, see [queues-notes](queues-notes.md) §8.

### What I could not obtain

- The teams lessons' detail (6–12) — deliberate, not a limitation; revisit only if PodText
  ever grows multi-team semantics.
- The linked Filament-4 authz resource from the outro — queued for the filament-4 notes.
