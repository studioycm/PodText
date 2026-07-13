# AGENTS.md Amendment — Session Protocol, Runner Rules, Production Safety

INSERT the three sections below into `AGENTS.md` immediately after the
`## Purpose` section (near the top — the file is long and agent readers
may truncate the tail). They encode the working method evolved across the
Phase-02 runs and SUPERSEDE any conflicting older text further down in
the file (notably: the final gate order below replaces the older
test-first gate listing).

---

## Session start protocol (mandatory, every session)

Before any work — including small ad-hoc fixes — read, in this order:

1. This file, fully.
2. `docs/phase-02/ai-development-lessons.md` — IN FULL. It is the
   accumulated failure/lesson record; every rule in it is binding.
3. `docs/phase-02/current-project-state.md` — where the project stands.
4. The head rows of
   `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md` — the
   step ledger (selected step, status, next step).
5. The newest one or two `docs/phase-02/*-handoff.md` files (by date in
   git log) — what just shipped and what was deferred.

When the operator's kickoff names a prompt file under
`prompts/pre-13-prompts/`, that file is the session's SINGLE task
contract. Check its "Prompt version" line matches the version named in
the kickoff; on mismatch, stop and ask. Execute only that prompt.

Ad-hoc sessions (no prompt file): the lessons and the runner rules below
still apply; diagnose from the docs BEFORE changing code; prefer
reporting findings with numbered operator steps when the question may be
designed behavior rather than a bug.

## Standing runner rules (current)

- Research note + implementation plan docs under `docs/research/<topic>/`
  BEFORE code, for every implementation prompt.
- FINAL GATE ORDER: requirements sweep → `vendor/bin/pint --test` →
  `vendor/bin/filacheck` → `npm run build` → FULL `php artisan test`
  LAST. "Once" means once GREEN on the final code state: after ANY file
  change, re-enter from Pint. Record every run, including failures, in
  the handoff. Never interrupt or parallelize the full suite.
- CANONICAL RUN ENDING: the implementation commit (code + docs + handoff
  with `## Commit hash` pending) is followed IMMEDIATELY by a docs-only
  commit stamping that hash into the handoff and ledger. No pending-hash
  debt is left behind.
- The handoff is a COMMITTED markdown file containing: requirement
  classification (Implemented / Already existed / Deferred / Not
  applicable / Blocked — never silently skip anything), files changed,
  tests added, every command run with results, gate outcomes, and a
  Local Front Check Report written as NUMBERED MANUAL OPERATOR STEPS in
  imperative voice ("open X, click Y, expect Z") — never a self-report
  of test coverage.
- No push unless the operator asks. No `vendor/bin/filacheck --fix`
  without explicit approval. No Composer or npm dependency changes
  without explicit approval.
- Tests own their fixtures. Every HTTP-touching test uses
  `Http::preventStrayRequests()` plus committed fixtures; mail tests use
  `Mail::fake()`. Live network or live mail in tests is never
  acceptable.
- The local development database is OFF-LIMITS for command probes and
  experiments — tests/sqlite first; if a live probe is unavoidable,
  create a backup first.
- Every user-facing label/hint gets both `he` and `en` translation keys.
  Hebrew is the primary audience; date presentation is day-first,
  Asia/Jerusalem for display.
- Tooling deviations (disabling a wrapper, alternative commands) are
  allowed when needed but MUST be disclosed with a one-line reason in
  the handoff.

## Production access safety (Forge MCP / SSH) — binding

Production is a MULTI-TENANT server running other sites beside this one.

- Diagnostics that only READ (status, logs, config listing, `ps`,
  `ls -l`) are allowed. ANY mutating action — restarting services,
  killing processes, editing files or env, running artisan
  writes/migrations, changing Forge configuration, deleting anything —
  requires explicit per-action approval from the operator in this
  session. Propose the exact command and wait.
- Before ANY process action, identify ownership: `ls -l /proc/<pid>/cwd`.
  Extra Horizon masters or workers may belong to OTHER tenant sites;
  killing them takes those sites down. Only a process whose cwd is this
  app's release path AND whose environment shares this APP_NAME is ours.
- Zero-downtime layout: the live site is a `current` symlink into
  `releases/<id>`; `storage` is a shared path. Edits inside a release
  directory vanish on the next deploy — never "fix" production by
  editing release files. After a release activates, PHP-FPM needs a
  reload unless nginx uses `$realpath_root`.
- Never edit the production `.env` without explicit instruction; never
  paste secrets (tokens, keys, passwords) into chat, commits, or
  tracked files — key NAMES only.
- When production behavior differs from local, suspect deploy topology
  (stale release, missing shared path, FPM cache, queue workers on old
  code) before suspecting application code.
