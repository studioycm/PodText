# Codex Prompt — Maintenance Livewire Enforcement Effects Audit

Prompt version: v1 — 2026-07-16. (Standing rule: if the committed prompt
differs from the version named in the kickoff, stop and ask.)

Work in the saved local `studioycm/PodText` checkout on its existing branch.
Do not create a worktree, push, mutate production, or access the local
development database.

ONE audit-only run: independently check, research, and audit the maintenance-
access and system-wide effects of the narrow `RenderMaintenanceMode` Livewire
termination correction delivered with the AUTHZ1 foundation. This prompt does
not authorize application, package, config, migration, test, translation, or
frontend implementation changes. Any remediation requires a new accepted
implementation prompt.

## Start and authority

Follow the complete `AGENTS.md` session-start protocol. Read in full:

1. `docs/phase-02/ai-development-lessons.md`;
2. `docs/phase-02/current-project-state.md`;
3. the ledger Current Run and AUTHZ1 rows;
4. the newest two handoffs, including
   `docs/phase-02/authz1-foundation-handoff.md`;
5. `docs/research/settings-performance/13-authz1-foundation-research.md` and
   its implementation plan;
6. the committed v3 AUTHZ1 foundation prompt;
7. report 12 and the pending-decision queue;
8. every relevant active `.ai/guidelines` file.

Verify the AUTHZ1 foundation implementation and immediate hash-stamp commits
from the handoff/ledger, verify a clean worktree, and verify this exact
`Prompt version: v1` before audit work. If the foundation is not committed,
its final suite is not green, or the working tree contains unexpected
application changes, stop and report.

## Skills, tools, and evidence discipline

Run `command -v rg` before discovery and use `rg` / `rg --files`. Activate and
read in full at minimum:

- `livewire-development`;
- `laravel-best-practices`;
- `filament-security-audit`;
- `pest-testing`.

Use Laravel Boost `application-info` and installed-version `search-docs` for
Livewire persistent middleware, Laravel response exceptions, Filament panel
middleware, testing, and error handling. Inspect current official Livewire 4,
Laravel 13, and Filament 5 primary documentation plus exact installed source.
Use FilamentExamples only if the audit reaches a Filament Page/panel pattern;
if used, follow the decomposed/refined protocol and record access level.

Keep these evidence categories separate:

1. official installed-version documentation;
2. exact installed/tagged source;
3. committed application source and configuration;
4. executed isolated tests/local observations;
5. auditor inference and residual uncertainty.

Use at least two bounded read-only evidence workers: one security/runtime
boundary audit and one test/UX/effects audit. They may not edit, install,
migrate, generate, seed, sync, stage, commit, push, access development data, or
touch production. The main controller owns all conclusions and any Markdown
edits.

## Required causal audit

Reconstruct and verify the complete request path:

1. initial public Filament route and middleware stack;
2. child component snapshot `memo.path` / `memo.method` provenance;
3. actual Livewire update endpoint and required request header;
4. persistent-middleware route reconstruction and filtering;
5. `RenderMaintenanceMode` enabled/disabled and five-role bypass decision;
6. the exact termination mechanism and Laravel exception rendering;
7. returned status, body, headers, component effects, session, and logs.

Prove the correction is app-owned, narrowly scoped, and contains no vendor
patch, custom global Livewire update route, component rewrite, package
authorization, or role-authority change.

## Required access matrix

Use the exact five-role enum order `super-admin`, `admin`, `moderator`,
`transcriber`, `user`, plus guest where applicable. Audit both maintenance
disabled and enabled across:

- initial public HTTP requests;
- a real update using a snapshot captured before activation;
- a fresh snapshot/update after disabled mode is restored;
- Admin panel initial and Livewire requests;
- Horizon HTTP and direct Gate access;
- public maintenance form view/send-code/submit flows;
- raw-HTML override and rendered-shell responses.

Expected declared audience remains: Super/Admin bypass; Moderator,
Transcriber, User, and guests receive maintenance denial. No package row,
permission, role assignment, or catalog manifest may change an outcome.

Every denied update must prove HTTP 503, exact `Retry-After`, maintenance body,
no successful Livewire component effects, and no relevant persisted-state
mutation. Every allowed path must prove its previous status/body behavior.

## Required broader-effects audit

Classify each plane as directly tested, source-proven, locally observed,
inferred, not applicable, or deferred:

- stale snapshots and activation/deactivation races;
- bundled or multi-component update payloads;
- lazy, deferred, polling, form-modal, file-upload, and navigation requests;
- CSRF, session, authentication, locale, RTL, and cache invalidation;
- public form submissions and verification mail boundaries;
- response body, content type, status, `Retry-After`, exception reporting, and
  application/browser log noise;
- Livewire client error UX, retry behavior, unsaved local state, focus, and
  accessibility implications;
- Admin panel, Horizon, queues/workers, imports/exports, scheduled tasks, APIs,
  and ordinary non-Livewire routes;
- performance claims, limited to measurements actually taken.

Do not infer browser UX from server tests. If the local application and in-app
browser are safely available without credentials or development-data writes,
perform a local browser observation using test-owned/ephemeral state only.
Otherwise record the browser plane as unobserved and provide numbered manual
operator checks.

## Commands and data safety

Tests must use the repository's forced SQLite `:memory:` environment,
`Http::preventStrayRequests()`, committed fixtures, and `Mail::fake()` where
applicable. Run sequentially. Do not run migrations or probes against MySQL.
Do not execute production, Forge, SSH, queue-process, or dependency commands.

At minimum run:

```bash
php artisan test --compact tests/Feature/PublicMaintenanceModeTest.php
php artisan test --compact tests/Feature/PanelAuthHardeningTest.php
php artisan test --compact tests/Feature/AuthzPackageFoundationTest.php
```

Run additional existing targeted tests only when needed to resolve an audited
plane. Do not modify a failing test or application source; report the failure.

## Outputs and completion

Create Markdown only:

- `docs/research/settings-performance/14-maintenance-livewire-enforcement-effects-audit.md`;
- `docs/phase-02/maintenance-livewire-enforcement-audit-handoff.md`.

The audit report must include the causal request trace, exact access matrix,
effects matrix, docs/source/test/inference separation, security findings with
severity and exploit preconditions, limitations, and numbered recommendations.
The handoff must classify every requirement as Audited, Already proven,
Deferred, Not applicable, or Blocked; list every command/result; state no
application change/no production/no dev DB/no push; and provide a numbered
manual Local Front Check Report in imperative voice.

Update `docs/phase-02/current-project-state.md`, the ledger, and the pending-
decision queue only if needed to record a material audit finding or accepted
next decision. Do not mark AUTHZ1-C started.

Final checks:

```bash
git diff --check
git status --short
```

On a complete audit, commit only the authorized Markdown outputs and any
necessary restart-safe Markdown records using:

```text
docs: audit maintenance Livewire enforcement
```

Do not push. If any finding requires remediation, stop with the exact evidence
and propose a separate implementation prompt; do not fix it in this audit.
