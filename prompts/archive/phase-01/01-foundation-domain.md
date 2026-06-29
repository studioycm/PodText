# Codex Prompt 01 — Domain Foundation

## Goal

Implement the generic content domain and safe Markdown foundation for Bootstrap Slice 0.

## Required context

Read:

- `AGENTS.md`
- `.ai/guidelines/bootstrap-slice-0.md`
- `docs/project-description.md`
- `docs/architecture-decisions.md`
- Phase 1 in `docs/project-phases.md`

Inspect the completed Phase 0 implementation and its tests. Use Laravel Boost documentation search when uncertain.

## Constraints

- Work sequentially in the current checkout.
- Do not create worktrees.
- Do not use the model names `Podcast` or `Episode`.
- Do not generate Filament Resources yet.
- Do not implement import/export yet.
- Do not add broad Actions, Services, DTOs, repositories, observers, events, or traits.
- A focused safe Markdown renderer is allowed and required.

## Implement

### 1. PublicationStatus Enum

Create a backed Enum with only:

```text
Draft
Published
```

Use normal string database columns and Eloquent Enum casts.

### 2. ContentGroup

Create the model, migration, factory, and published scope with fields described in `docs/project-description.md`.

Required concepts:

- numeric database primary key unless repository conventions already differ;
- immutable unique ULID-compatible `reference_key`;
- unique public slug;
- group singular/plural labels;
- default item singular/plural labels;
- default Podcast/Podcasts/Episode/Episodes values;
- Markdown description;
- optional cover path;
- original language code default `he`;
- status and publication timestamp;
- relationship to ContentItems.

### 3. ContentItem

Create the model, migration, factory, published scope, and relationships.

Required concepts:

- immutable unique `reference_key`;
- parent ContentGroup;
- title;
- slug with a tested uniqueness strategy that permits unambiguous public routes;
- nullable item singular-label override;
- effective item-label method/accessor;
- description Markdown;
- media URL;
- nullable embed URL;
- nullable non-negative duration seconds;
- transcript Markdown;
- status and dates;
- many-to-many Authors.

The published scope must exclude an item whose parent group is not publicly visible.

### 4. Author

Create the model, migration, factory, and item relationship.

Required concepts:

- immutable unique `reference_key`;
- name;
- unique slug;
- optional biography Markdown;
- many-to-many ContentItems.

### 5. Pivot

Create the ContentItem/Author pivot with:

- foreign keys;
- a unique pair constraint;
- explicit deletion behavior.

Avoid extra pivot fields unless required by the current specification.

### 6. Reference-key behavior

- Generate a key automatically for manually created records.
- Permit an importer to supply a key for a new record later.
- Prevent ordinary edits from replacing an existing key.
- Keep this behavior simple and testable.

### 7. SafeMarkdownRenderer

Create a focused class under a clear namespace such as:

```text
App\Support\Markdown\SafeMarkdownRenderer
```

It must:

- convert Markdown to HTML using installed Laravel/Filament-supported tools;
- sanitize the generated HTML;
- provide one reusable public rendering path;
- be container-resolvable without unnecessary interface abstraction.

### 8. Seed data

Add representative Hebrew data:

- at least two authors;
- one published group with Podcast/Episode defaults;
- one draft group;
- one published item with multiple authors;
- one draft item;
- optional future-dated record according to the chosen publication rule;
- Hebrew transcript text with diacritics.

Do not seed unsafe content outside tests.

## Tests

Add Pest tests for:

- all model relationships;
- Enum casts;
- unique generated reference keys;
- reference-key immutability behavior;
- label defaults;
- effective item label inheritance;
- item label override;
- published group scope;
- draft group exclusion;
- published item scope;
- draft item exclusion;
- item under draft group exclusion;
- future publication behavior;
- Markdown output formatting;
- Markdown XSS sanitization.

## Completion checks

Run and report:

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
npm run build
```

Review generated SQL constraints and indexes before finishing.

## Final report

Report:

- schema created;
- publication rule chosen;
- slug uniqueness rule;
- deletion behavior;
- Markdown sanitization mechanism;
- tests and results;
- assumptions and deferred concerns.

Do not start Prompt 02.
