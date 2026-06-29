# Initial Codex Prompt — Bootstrap the Existing Herd Folder

You are the implementation agent for this repository. Work directly in the current PhpStorm project folder.

## Known starting state

- This folder is already served locally by Laravel Herd.
- Codex is already connected and running in PhpStorm. Do not perform account setup.
- A local `.git` repository already exists.
- There are no Git commits yet.
- There is no GitHub repository or remote, and none is required.
- The repository currently contains the Bootstrap Slice 0 planning pack.
- Laravel has not been installed in this folder yet.
- A `.gitignore` file is currently missing.

## Goal

Safely turn this existing folder into the Laravel application described by the planning pack, while preserving every planning file.

Then execute **only**:

- Phase 0 in `docs/project-phases.md`
- `prompts/00-bootstrap-project.md`

Do not begin `prompts/01-foundation-domain.md`.

The intended stack is:

- Laravel 13
- Filament 5 panel builder
- Livewire 4
- Alpine.js as supplied by Livewire/Filament
- Tailwind CSS 4
- Pest
- Laravel Boost

## Read before modifying anything

Read these files completely:

1. `AGENTS.md`
2. `.ai/guidelines/bootstrap-slice-0.md`
3. `docs/project-description.md`
4. `docs/architecture-decisions.md`
5. `docs/import-export-spec.md`
6. `docs/project-phases.md`
7. `docs/acceptance-checklist.md`
8. `prompts/00-bootstrap-project.md`

Confirm that all expected files exist. If an expected planning file is missing, stop and report it before installation.

Also fetch and follow the current official agent/install guidance from:

- `https://laravel.com/for/agents`
- `https://laravel.com/docs/13.x/installation`
- `https://filamentphp.com/docs/5.x/introduction/installation`
- `https://filamentphp.com/docs/5.x/panel-configuration`
- `https://livewire.laravel.com/docs/4.x/installation`

Treat installed-package documentation and official current documentation as authoritative. Do not rely on remembered Laravel, Filament, or Livewire APIs.

## Non-negotiable operating rules

- Work sequentially in the current checkout.
- Do not create or use worktrees.
- Do not launch parallel agents or parallel implementation tasks.
- Do not initialize another Git repository.
- Do not create a GitHub repository, add a remote, push, or publish anything.
- Do not delete or replace the existing `.git` directory.
- Do not delete, rename, or overwrite `AGENTS.md`, `.ai/`, `docs/`, `prompts/`, or the project `README.md`.
- Do not use `laravel new . --force`.
- Do not run destructive commands such as `git clean -fdx` or recursive deletion against the repository root.
- A temporary installation directory outside the repository is allowed.
- Do not install a Laravel starter kit. Filament provides the initial authenticated admin interface.
- Do not install Laravel Boost until after Filament is installed, so Boost can detect the actual package set.
- Do not add any deferred feature from the planning documents.
- Do not create `ContentGroup`, `ContentItem`, `Author`, Resources, importers, exporters, or public content pages in this task.
- Never commit `.env`, credentials, tokens, local database contents, vendor dependencies, Node dependencies, IDE metadata, or generated build output.
- If the repository contains unexpected application source files or a filename collision that cannot be merged safely, stop and report it instead of overwriting it.

## Step 1 — Preflight and repository inspection

Before changing files:

1. Confirm the current working directory and Git root.
2. Run and inspect:
   - `git status --short --branch`
   - `git log --oneline --all`
   - the top-level directory listing
3. Confirm that there are no existing commits.
4. Confirm that Laravel is not already installed by checking for `artisan` and `composer.json`.
5. Inspect local tooling:
   - `php -v`
   - `composer --version`
   - `laravel --version`
   - `node --version`
   - `npm --version`
   - `git --version`
6. Inspect `laravel new --help` before selecting installer flags.
7. Confirm that the active PHP version and extensions satisfy Laravel 13 and Filament 5.
8. Detect the current Herd site URL if the Herd CLI exposes it. Do not change Herd global configuration.
9. Report any blocker before proceeding.

## Step 2 — Add `.gitignore` and create a planning baseline commit

Create a Laravel-compatible root `.gitignore`.

Start from the current Laravel 13 skeleton rules and ensure it ignores at least:

```text
*.log
.DS_Store
.env
.env.backup
.env.production
.phpunit.result.cache
/.codex
/.cursor
/.idea
/.nova
/.phpunit.cache
/.vscode
/.zed
/auth.json
/node_modules
/public/build
/public/hot
/public/storage
/storage/*.key
/storage/pail
/vendor
/database/*.sqlite
_ide_helper.php
Homestead.json
Homestead.yaml
Thumbs.db
```

Requirements:

- Do not ignore `AGENTS.md`.
- Do not ignore `.ai/`.
- Do not ignore `docs/`.
- Do not ignore `prompts/`.
- Do not ignore `.env.example`.
- Keep the file organized and free of duplicate entries.

Before committing:

1. Search tracked planning files for likely credentials, bearer tokens, API keys, private keys, or passwords.
2. Show `git status --short`.
3. Show the staged diff summary.
4. Confirm that only planning files and `.gitignore` are included.

If Git user name or email is not configured, do not invent an identity. Stop and tell me exactly what is missing.

Otherwise, create the first local commit:

```text
docs: add bootstrap slice zero plan
```

Do not add a remote.

## Step 3 — Safely install Laravel into this non-empty repository

The repository is non-empty, so create the fresh Laravel application in a temporary directory outside the Git root first.

Preferred approach:

1. Use the current official Laravel installer.
2. Select:
   - no starter kit;
   - Pest;
   - SQLite;
   - no Boost during temporary creation;
   - no Node installation during temporary creation.
3. Use non-interactive flags only after confirming them with `laravel new --help`.

A likely command shape is:

```bash
laravel new <temporary-directory> \
    --pest \
    --database=sqlite \
    --no-boost \
    --no-node \
    --no-interaction
```

Do not blindly use that command if the installed Laravel installer exposes different current options. Use its help output and official documentation.

If the Laravel installer cannot safely create the temporary application, use an official Composer-based Laravel 13 installation in the temporary directory and then install/configure Pest according to current official guidance.

After temporary creation:

1. Verify that the temporary application reports Laravel major version 13.
2. Verify that Pest is installed and the baseline tests pass there.
3. Copy the Laravel application into the current repository using an OS-appropriate safe copy operation.
4. Never copy or replace a temporary `.git` directory.
5. Do not overwrite the project planning files or project `README.md`.
6. Do not overwrite `.gitignore`; merge any missing Laravel entries into the existing file.
7. Do not copy `vendor/`, `node_modules/`, generated build files, or the temporary SQLite data file.
8. For every unexpected filename collision, compare first and preserve the project planning version unless a safe merge is clearly required.
9. Remove the temporary directory only after the current repository passes the Laravel health checks.

In the current repository:

1. Install Composer dependencies.
2. Copy `.env.example` to `.env` if needed.
3. Generate the application key.
4. Configure SQLite for this bootstrap slice.
5. Create the local SQLite file.
6. Ensure the SQLite file remains ignored by Git.
7. Set a sensible local `APP_URL` based on the detected Herd URL when reliable; otherwise retain a safe default and report it.
8. Install Node dependencies.
9. Run the baseline asset build.
10. Confirm:
    - `php artisan --version` reports Laravel 13;
    - the Pest baseline suite passes;
    - `npm run build` passes.

## Step 4 — Execute Prompt 00 / Phase 0

Now execute the requirements in:

```text
prompts/00-bootstrap-project.md
```

and Phase 0 of:

```text
docs/project-phases.md
```

In particular:

### Filament and Livewire

- Install the Filament 5 panel builder using current official commands.
- Do not use the individual-components scaffold in place of the panel builder.
- Create the authenticated Admin panel at `/admin`.
- Create a separate Public panel and configure it at `/`.
- Keep panel discovery directories separate.
- Remove authentication/account behavior from the Public panel.
- Ensure the generated Laravel `/` route does not take precedence over the Public panel.
- Verify the installed Livewire major version is 4.
- Do not manually add or initialize a second Alpine.js copy.

### Admin account

Provide a safe local-development method to create the first Filament user.

Do not commit a production password or a real credential.

### Minimal import/export infrastructure

Create only the queue, batch, notification, and Filament Actions migration/configuration foundation required by the later native Filament import/export phase.

- Use the database queue for Slice 0.
- Add the jobs migration only if it is not already present.
- Add job-batch and database-notification migrations.
- Publish the required Filament Actions migrations.
- Enable database notifications for the Admin panel.
- Document the local queue-worker command.
- Do not create importers or exporters yet.

### Localization, RTL, and assets

- Configure Hebrew as the default locale.
- Make English available.
- Use translation keys for shell UI introduced in this phase.
- Render Hebrew as RTL and English as LTR.
- Add the minimal Public-panel theme/assets needed by later Blade and Livewire components.
- Use a font that displays Hebrew and Hebrew diacritics correctly. Varela Round is acceptable.
- Keep Alpine usage limited to Filament/Livewire defaults in this phase.

### Laravel Boost

After Filament is installed:

1. Install Laravel Boost as a development dependency.
2. Preserve and include `.ai/guidelines/bootstrap-slice-0.md`.
3. Run the current Boost installer.
4. Configure the detected Codex/IDE integration when supported.
5. Do not perform ChatGPT or Codex account authentication; it is already connected.
6. Review generated files before changing `.gitignore`.
7. Keep project-wide guidance such as `AGENTS.md` and `.ai/guidelines` tracked.
8. Treat IDE- or machine-specific MCP configuration as local when appropriate.
9. If this PhpStorm/Codex environment cannot expose Boost MCP tools, still install Boost and its guidelines, then report the limitation without pretending the tools are active.

### Tests

Add the Phase 0 Pest tests required by the plan:

- a guest cannot access the Admin panel;
- an authenticated user can access the Admin panel;
- a guest can access the Public panel;
- Hebrew resolves to RTL;
- English resolves to LTR.

Use current Filament 5 and Livewire 4 testing APIs from official or Boost documentation.

## Step 5 — Verification

Run the actual project commands and fix failures within Phase 0 scope:

```bash
php artisan migrate:fresh
php artisan test
vendor/bin/pint --test
npm run build
```

Also:

- inspect `composer show` for the installed Laravel, Filament, Livewire, and Boost versions;
- inspect registered routes and confirm `/admin` and `/`;
- verify the database queue worker starts without a configuration error, then stop it safely;
- inspect `git status --short`;
- confirm `.env`, `vendor/`, `node_modules/`, the SQLite file, `.idea/`, and build output are untracked/ignored.

Do not create the Phase 0 implementation commit yet. Stop so I can review the diff first.

## Final response format

Return a concise but complete implementation report containing:

1. Preflight environment and versions.
2. The baseline Git commit hash.
3. Installation method used for the non-empty folder.
4. Installed package versions.
5. Admin and Public panel paths.
6. Database and queue configuration.
7. Localization and RTL approach.
8. Boost integration status.
9. Files changed.
10. Tests added.
11. Every command run and whether it passed.
12. Current `git status`.
13. Assumptions made.
14. Deferred observations.
15. Any unresolved issue.

End with exactly:

```text
Phase 0 is ready for human review. Prompt 01 has not been started.
```
