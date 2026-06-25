# Codex Prompt 05 — Final Independent Review

## Role

Act as a senior Laravel 13 / Filament 5 reviewer. Review and correct Bootstrap Slice 0 without expanding product scope.

## Required context

Read:

- `AGENTS.md`
- every file in `docs/`
- the completed implementation and tests
- relevant installed-package documentation through Laravel Boost

Review the repository as it exists. Do not assume earlier Codex reports were accurate.

## Constraints

- Current checkout only; no worktrees.
- Do not add deferred features.
- Do not refactor solely to introduce preferred patterns.
- Make only justified fixes for correctness, security, maintainability, tests, performance, accessibility, or specification compliance.

## Review areas

### 1. Scope and terminology

- No `Podcast` or `Episode` model/table/Resource exists.
- Internal naming is consistently ContentGroup/ContentItem.
- Dynamic display labels work.
- No speculative architecture or deferred subsystem was added.

### 2. Laravel and data integrity

- Migrations are reversible.
- Foreign keys and unique constraints match the domain.
- Indexes support public and import queries.
- Enum casts are correct.
- reference keys are stable and protected.
- slugs resolve unambiguously.
- publication scopes are correct.
- items under non-public groups are never public.
- factories and seeders reflect real states.

### 3. Filament 5

- Resources use current Filament 5 APIs.
- Schema/Table classes are organized cleanly.
- Admin pages are authorized.
- no unnecessary View pages/Infolists exist.
- import/export Actions use current APIs.
- database notifications and queue dependencies are configured.
- tests use current Filament/Livewire testing APIs.

### 4. Public panel

- Public panel is intentionally guest-accessible.
- Admin Resources are not registered publicly.
- all public queries enforce published scopes.
- direct draft URLs return not found.
- query counts do not show obvious N+1 behavior.
- Livewire is used only for dynamic server behavior.
- Alpine is used only for local interactions.
- Blade components do not duplicate logic unnecessarily.

### 5. Security

- Markdown is sanitized on every public output path.
- no arbitrary iframe HTML is stored/rendered.
- embed URL validation is strict and shared between forms/imports.
- file uploads use intended disks/directories/visibility.
- import/export is administrator-only.
- generated file downloads are authorized.
- CSV formula injection is handled through current Filament mechanisms.
- importers do not permit unsafe mass assignment or unexpected columns.
- no credentials or private data are exported.

### 6. Import/export correctness

- new and update behavior matches reference-key rules.
- item relationships resolve by reference key.
- unmapped/blank author behavior is explicit and tested.
- row failure does not cancel valid rows.
- example CSV files match actual columns.
- Hebrew/multiline Markdown round trip is tested.
- transcript is disabled by default in item export.
- queue worker documentation is accurate.

### 7. Localization, RTL, and accessibility

- UI strings use translation files.
- Hebrew is RTL and English is LTR.
- Hebrew diacritics render.
- long titles are handled.
- semantic headings, labels, focus, empty, loading, and error states are reasonable.
- mobile public layout is usable.

### 8. Tests and build

Look for missing or false-positive tests. Add/fix tests only where needed.

## Required clean verification

Run:

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
npm run build
```

With a queue worker running, execute or verify representative native imports and exports.

Use `docs/acceptance-checklist.md` and mark only items that were actually verified.

## Deliverables

1. Apply justified fixes.
2. Update documentation that does not match code.
3. Produce a final handoff report containing:
   - implemented scope;
   - routes and panels;
   - setup commands;
   - queue-worker command;
   - import order and formats;
   - administrator setup;
   - tests/build commands and actual results;
   - known limitations;
   - deferred features;
   - any acceptance item not verified.

Do not declare Bootstrap Slice 0 complete when any required command fails or any critical acceptance item remains unverified.
