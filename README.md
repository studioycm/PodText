# Bootstrap Slice 0 — Codex Implementation Pack

This pack defines the fastest useful first version of the application without locking the full project into premature architecture.

The first complete loop is:

1. An administrator creates or imports an author.
2. An administrator creates or imports a content group, displayed by default as a podcast.
3. An administrator creates or imports a content item, displayed by default as an episode.
4. The administrator publishes the group and item.
5. A logged-out visitor browses the group and reads the item's Markdown transcript.

The internal domain names are deliberately generic:

- `ContentGroup` — default display type: **Podcast**
- `ContentItem` — default display type: **Episode**
- `Author`

Display type labels may be changed by administrators without renaming PHP classes or database tables.

## Workflow philosophy

This pack follows the staged AI-development approach used by Povilas Korop/Laravel Daily:

- Give the agent a narrow project description.
- Add repository-level AI instructions.
- Separate implementation into reviewable phases.
- Define acceptance criteria and automated tests for every phase.
- Commit after each phase before starting the next.
- Use Laravel Boost and installed-package documentation instead of relying on remembered APIs.

This is a **bootstrap-specific planning pack**, not the final full-project specification. Full user stories, the final database design, the complete authorization matrix, auditing, workflow/versioning, provider drivers, and the transcription studio remain deferred until the wider product discovery is complete.

## No worktrees for Slice 0

Implement this slice sequentially in the main project checkout.

Do not:

- create Codex worktrees;
- run multiple implementation agents against the repository in parallel;
- start the next phase before the current phase has passed its tests and been committed.

Recommended commit sequence:

```text
chore: bootstrap Laravel Filament application
feat: add content group item and author domain
feat: add admin content management
feat: add public content panel
feat: add content import and export
test: harden bootstrap slice zero
```

## Files in this pack

```text
AGENTS.md
.ai/guidelines/bootstrap-slice-0.md
docs/project-description.md
docs/architecture-decisions.md
docs/import-export-spec.md
docs/project-phases.md
docs/acceptance-checklist.md
prompts/00-bootstrap-project.md
prompts/01-foundation-domain.md
prompts/02-admin-panel.md
prompts/03-public-panel.md
prompts/04-import-export.md
prompts/05-final-review.md
```

## How to use this pack with Codex App

1. Create the new Laravel repository.
2. Copy this pack's `AGENTS.md`, `.ai`, `docs`, and `prompts` directories into the repository root.
3. Open the repository in Codex App.
4. Run the prompt files in numerical order, one at a time.
5. Review the diff after each prompt.
6. Require Codex to run all phase completion commands.
7. Manually verify the phase.
8. Commit the phase before continuing.

Do not give Codex the whole application request as one implementation prompt. Keep each run bounded to the current prompt file.

## Baseline installation direction

The exact installer output should be inspected rather than assumed, but the intended stack is:

```text
Laravel 13
PHP version supported by Laravel 13 and Filament 5
Filament 5 panel builder
Livewire 4
Alpine.js bundled through Livewire/Filament
Tailwind CSS 4
Pest
Laravel Boost
```

Install Filament before running the final Boost installation so Boost can include the installed Filament guidance.

The native Filament importer/exporter uses queued job batches and database notifications. Slice 0 therefore includes a minimal database queue foundation solely for imports and exports. It does not include the later operations dashboard, custom retry management, activity logging, or full observability system.

Typical supporting migrations include:

```bash
php artisan make:queue-table          # only when the project does not already contain one
php artisan make:queue-batches-table
php artisan make:notifications-table
php artisan vendor:publish --tag=filament-actions-migrations
php artisan migrate
```

For local development with imports/exports:

```bash
php artisan queue:work
```

## Phase completion commands

Codex must use the commands available in the generated repository. The expected quality gate is equivalent to:

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Before the final review, also verify a clean rebuild:

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
npm run build
```

## Deliberately deferred

Do not let Codex add these during Slice 0:

- Filament Shield and the full role/permission matrix
- volunteer registration or author self-service
- auditor panel or approval workflow
- transcription requests
- hierarchical categories or tags
- tag moderation
- transcript speakers or timestamp syntax
- synchronized media playback
- custom transcription studio
- provider URL analysis or metadata APIs
- file-based media upload
- transcript revision history
- model activity logging
- process/action/error logging
- custom retry or replay management
- notifications beyond Filament import/export completion notifications
- analytics
- comments
- CMS pages
- full-text search engine
- legacy image download/import
- Filament Blueprint for the complete project

Any useful idea discovered during implementation should be recorded as a deferred note instead of being implemented opportunistically.
