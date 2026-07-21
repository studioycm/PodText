# Bounded Dependency Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use
> `superpowers:executing-plans` to execute this plan task by task.

**Goal:** Refresh all Composer and npm packages permitted by the existing
manifest constraints while preserving application behavior and leaving the
result uncommitted.

**Architecture:** Treat both manifests as immutable boundaries and let their
native resolvers update only lockfiles, installed packages, and package-owned
published assets. Review the resolution and repository diff before exercising
the highest-risk Filament, Livewire, Curator, queue, import/export, and public
form paths, then run the canonical final gates serially.

**Tech Stack:** PHP 8.4, Laravel 13, Filament 5, Livewire 4, Horizon 5, Curator
5, Pest 4, Node.js 22, npm 10, Tailwind CSS 4, Vite 8.

## Global constraints

- Implement only audit `LS-20260721-DEPENDENCY-REFRESH-01`, option
  `DEP-UPGRADE-O1-BOUNDED-FULL-REFRESH`.
- Do not edit `composer.json` or `package.json`.
- Do not accept a major-version, schema, migration, or security-boundary change.
- Do not probe the local development database or mutate production, workers,
  processes, caches, or deployed files.
- Keep the work sequential in the current checkout.
- The initial result must remain uncommitted for operator review. The operator's
  later explicit `then commit` instruction authorizes the canonical local
  implementation and hash-stamp commits only. Do not push, publish, or create a
  pull request.
- Stop for an amended Stage 1 audit if material scope or baseline drift appears.
- After any edit following a final gate, restart the gate sequence at Pint.

### Task 1: Validate resolver boundaries

- [ ] Run `composer validate --strict`.
- [ ] Run
      `composer update --with-all-dependencies --dry-run --no-interaction`.
- [ ] Confirm the Composer result stays within current constraints and does not
      require a manifest, major-version, or platform-boundary change.
- [ ] Run `npm update --dry-run --ignore-scripts`.
- [ ] Confirm the npm result stays within current constraints and does not
      require a manifest or major-version change.
- [ ] Recheck that the worktree and both manifests remain unchanged.

### Task 2: Refresh the Composer graph

- [ ] Run `composer update --with-all-dependencies --no-interaction`.
- [ ] Verify `composer.json` is byte-for-byte unchanged.
- [ ] Inspect `composer.lock` and any package-owned Filament assets for the exact
      resolved versions and unexpected scope.
- [ ] Run targeted `composer show` checks for Laravel, Filament, Livewire,
      Horizon, Curator, Pest, Boost, Shield, and Laravel Permission.
- [ ] Run `composer audit` and record any advisories without crossing the
      approved constraints automatically.

### Task 3: Refresh the npm graph

- [ ] Run `npm update --ignore-scripts`.
- [ ] Verify `package.json` is byte-for-byte unchanged.
- [ ] Inspect `package-lock.json` and `npm ls --depth=0` for the exact resolved
      versions and invalid peer dependencies.
- [ ] Run `npm audit` and record any advisories without using a force or major
      upgrade.

### Task 4: Review compatibility and run focused tests

- [ ] Inspect `git status --short`, `git diff --stat`, manifest diffs, changed
      package versions, published assets, and any application-code changes.
- [ ] Confirm there are no migrations, schema changes, new dependencies,
      security-boundary changes, or unrelated files.
- [ ] Run the Filament admin and app-owned media Resource/picker feature tests.
- [ ] Run the Card Template Builder and preview feature tests.
- [ ] Run import/export and queue-configuration feature tests.
- [ ] Run public-form and settings feature tests.
- [ ] Diagnose any failure systematically and make only a directly required,
      in-scope compatibility correction.

### Task 5: Synchronize durable tooling state

- [ ] Update only the installed tooling-version snapshot in
      `docs/phase-02/current-project-state.md` when the successful resolver
      result differs from its current values.
- [ ] Check the research and plan documents for placeholders or unstated scope.
- [ ] Run `git diff --check` and complete a requirements sweep.

### Task 6: Run canonical final gates

- [ ] Run `vendor/bin/pint --test`.
- [ ] Run `vendor/bin/filacheck` without `--fix`.
- [ ] Run `npm run build`.
- [ ] Run the full `php artisan test` suite last, serially and uninterrupted.
- [ ] Recheck versions, both immutable manifests, the full diff, and final Git
      status.
- [ ] Report all files, tests, commands, assumptions, deferrals, and failures;
      perform the later-authorized canonical local commit closeout, and do not
      push.
