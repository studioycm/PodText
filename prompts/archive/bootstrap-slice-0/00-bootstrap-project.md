# Codex Prompt 00 — Bootstrap the Project

## Goal

Create the Laravel/Filament foundation for Bootstrap Slice 0 without implementing domain models or content features yet.

## Required context

Read completely before changing code:

- `AGENTS.md`
- `.ai/guidelines/bootstrap-slice-0.md`
- `docs/project-description.md`
- `docs/architecture-decisions.md`
- Phase 0 in `docs/project-phases.md`

Inspect the repository and installed tool versions. Use Laravel Boost and current official documentation for exact Laravel 13, Filament 5, and Livewire 4 commands and APIs.

## Constraints

- Work in the current checkout only.
- Do not create a worktree.
- Do not launch parallel implementation tasks.
- Do not create `ContentGroup`, `ContentItem`, or `Author` yet.
- Do not add any deferred feature.
- Do not install packages merely because they may be useful later.
- Do not claim Boost/MCP integration works without checking available project configuration.

## Implement

### 1. Laravel project health

- Confirm this is a Laravel 13 application.
- Confirm frontend dependencies can install and build.
- Confirm the baseline test suite runs.
- Preserve standard Laravel structure.

### 2. Filament 5

- Install/configure the Filament 5 panel builder if not already installed.
- Create an authenticated Admin panel at `/admin`.
- Create a separate Public panel at `/` intended for guest access.
- Ensure Resource/Page discovery paths are separated by panel.
- Remove login/profile/account behavior from the Public panel.
- Do not expose Admin Resources in the Public panel.

### 3. Admin account

Provide one safe local-development mechanism:

- documented `make:filament-user` command; or
- deterministic local-only admin seeder using environment-safe credentials.

Do not commit a production password.

### 4. Minimal import/export infrastructure

Configure the minimum foundation required by Filament native imports/exports:

- database queue connection;
- queue jobs table if absent;
- job-batches table;
- notifications table;
- Filament Actions import/export support tables;
- Admin-panel database notifications;
- documented local queue-worker command.

Do not create custom jobs, imports, exports, dashboards, retry UI, or logging.

### 5. Localization and direction

- Configure Hebrew as the default locale.
- Make English available.
- Add translation files/keys for shell labels introduced in this phase.
- Make panel/page direction RTL for Hebrew and LTR for English.
- Add the minimal Public-panel theme/assets required for later Blade/Livewire components.
- Use a font that correctly renders Hebrew and diacritics; Varela Round is acceptable.

### 6. Laravel Boost

- Install Laravel Boost as a development dependency if absent.
- Ensure `.ai/guidelines/bootstrap-slice-0.md` is available.
- Run the appropriate Boost installation/update command after Filament is installed.
- Do not overwrite project guidance without reviewing the diff.

### 7. Tests

Add Pest tests for:

- guest cannot access the Admin panel;
- an authenticated admin user can access the Admin panel;
- guest can access the Public panel;
- locale-direction behavior returns RTL for Hebrew and LTR for English.

Use current Filament 5 test APIs from installed documentation.

## Do not implement

- domain models;
- Resources;
- public content pages;
- imports/exporters;
- Shield;
- roles;
- approval workflow;
- categories/tags;
- provider integration;
- transcription features.

## Completion checks

Run and report actual results:

```bash
php artisan migrate:fresh
php artisan test
vendor/bin/pint --test
npm run build
```

Also verify the queue worker command starts without configuration errors, then stop it safely.

## Final report

Report:

- installed package versions;
- files changed;
- panels and their paths;
- migrations added;
- localization approach;
- tests added;
- commands and results;
- assumptions;
- unresolved problems;
- deferred observations.

Do not start Prompt 01.
