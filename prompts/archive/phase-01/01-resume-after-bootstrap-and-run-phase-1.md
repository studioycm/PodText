# Codex Continuation Prompt — Close Phase 0 and Execute Phase 1

Work directly in the current PhpStorm project repository.

## Known state

- The project is served by Laravel Herd at `D:\0-Dev\Herd\PodText`.
- Laravel 13 is installed.
- Filament 5 is installed with separate Admin and Public panels.
- Livewire 4 is installed.
- Laravel Boost is installed.
- The PhpStorm MCP configuration is managed outside this task.
- Git is local only. Do not create a GitHub repository, remote, worktree, or parallel implementation branch.
- Bootstrap Phase 0 has been implemented, but its exact commit state must be inspected.
- The next implementation task is `prompts/01-foundation-domain.md`.

## Goal

1. Inspect and verify the completed Phase 0 implementation.
2. Commit Phase 0 if it is not already committed.
3. Execute `prompts/01-foundation-domain.md` completely.
4. Stop before Prompt 02.
5. Leave Phase 1 changes uncommitted for human review.

## Read before changing anything

Read completely:

1. `AGENTS.md`
2. `.ai/guidelines/bootstrap-slice-0.md`
3. `docs/project-description.md`
4. `docs/architecture-decisions.md`
5. `docs/import-export-spec.md`
6. `docs/project-phases.md`
7. `docs/acceptance-checklist.md`
8. `prompts/01-foundation-domain.md`

Inspect the existing implementation and tests before generating code.

Use Laravel Boost MCP tools and version-specific documentation when they are available. If Boost MCP is unavailable, state that fact clearly and use current official Laravel 13, Filament 5, Livewire 4, and Pest documentation instead. Do not pretend an MCP tool was used.

## Operating rules

- Work sequentially in the current checkout.
- Do not use worktrees.
- Do not launch parallel agents.
- Do not add a Git remote or push.
- Do not start Prompt 02.
- Do not implement deferred features.
- Do not create `Podcast` or `Episode` models, tables, Resources, or classes.
- Use `ContentGroup`, `ContentItem`, and `Author` exactly as required by the project files.
- Do not add broad speculative Services, Actions, DTOs, repositories, events, observers, or traits.
- A focused safe Markdown renderer is required and allowed.
- Do not create or expose an administrator password in source code, seeders, logs, Git history, or the final report.
- Do not modify PhpStorm application-level MCP settings from project code.
- Never discard existing work with destructive Git commands.

## Step 1 — Inspect repository and Phase 0

Run and inspect:

```bash
git status --short --branch
git log --oneline --decorate --all -10
php artisan about
php artisan route:list
composer show laravel/framework filament/filament livewire/livewire laravel/boost
```

Inspect:

- both Filament panel providers;
- `bootstrap/providers.php`;
- queue and notification migrations;
- localization and direction implementation;
- existing Phase 0 tests;
- `.gitignore`;
- current uncommitted diff.

Confirm:

- `/admin` is authenticated;
- `/` is guest-accessible;
- the Public panel does not discover Admin Resources;
- Hebrew is RTL and English is LTR;
- database queue infrastructure exists;
- database notifications are enabled for the Admin panel;
- no deferred domain or audit functionality was accidentally added.

If unrelated or unexplained changes exist, stop and report them instead of committing.

## Step 2 — Verify Phase 0 quality gate

Run:

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Fix only Phase 0 defects required to pass these checks.

If the current local database contains an administrator account, note that the Phase 1 command `php artisan migrate:fresh --seed` will remove it. Do not preserve credentials in code. The user will recreate the local account after Phase 1.

## Step 3 — Commit Phase 0 when needed

If Phase 0 is not already committed and all checks pass:

1. Review the complete staged diff.
2. Confirm `.env`, SQLite database files, `vendor/`, `node_modules/`, `.idea/`, build output, and machine-specific MCP configuration are not staged.
3. Commit Phase 0 with:

```text
chore: bootstrap Laravel and Filament foundation
```

If an equivalent Phase 0 commit already exists, do not create an empty or duplicate commit.

Report the resulting commit hash.

## Step 4 — Execute Prompt 01

Treat `prompts/01-foundation-domain.md` as the active implementation specification and execute it completely.

Implement only Phase 1:

- `PublicationStatus`;
- `ContentGroup`;
- `ContentItem`;
- `Author`;
- item-author pivot;
- reference-key behavior;
- publication scopes;
- label inheritance and override behavior;
- safe Markdown rendering and sanitization;
- representative Hebrew seed data;
- all required Pest tests.

Follow the exact fields and decisions in the project documentation. Do not invent missing product features.

When syntax or package behavior is uncertain:

1. use Boost documentation search if available;
2. otherwise inspect installed package source and current official documentation;
3. do not use remembered Filament 3/4 or older Laravel APIs.

## Step 5 — Phase 1 verification

Run:

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
npm run build
```

Also inspect:

```bash
php artisan migrate:status
git diff --check
git status --short
```

Review generated schema constraints and indexes.

Do not create a Phase 1 commit. Leave the Phase 1 diff available for human review.

## Final report

Return:

1. Phase 0 verification result.
2. Phase 0 commit hash, or the existing equivalent commit.
3. Whether Boost MCP tools were actually available.
4. Schema and models created.
5. Publication visibility rule.
6. Slug uniqueness strategy.
7. Reference-key generation and immutability behavior.
8. Pivot deletion behavior.
9. Markdown conversion and sanitization mechanism.
10. Seed data added.
11. Tests added.
12. Every verification command and result.
13. Current `git status`.
14. Assumptions and deferred observations.
15. The exact local command to recreate a Filament administrator after the destructive migration.

End with exactly:

```text
Phase 1 is ready for human review. Prompt 02 has not been started.
```
