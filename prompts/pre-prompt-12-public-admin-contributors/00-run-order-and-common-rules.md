# Pre-Prompt-12 Run Order and Common Rules

## Purpose

This pack adds necessary work discovered after Prompt 11 and before Prompt 12.

The key decision is: **do not overload Prompt 12**. Prompt 12 must stay focused on the public item page, safe media rendering, transcription tabs, and parse-only transcript viewer.

## Required order

1. **Prompt 11R** — refactor public homepage/search away from Filament Table into custom Livewire + Blade.
2. **Prompt 11A** — admin relationship UX: create/edit option modals and ContentGroup → ContentItems relation manager.
3. **Prompt 11B** — public contributors/transcribers discovery: top transcribers section, directory, preview, full contributor page, seeders.
4. **Prompt 12 readiness sync** — docs-only sync so Prompt 12 preserves 11R/11A/11B.
5. **Prompt 12 activation wrapper** — run existing Prompt 12 with the new guardrails.

## Common rules

- Work sequentially in the current checkout.
- Do not create worktrees.
- Do not run prompts in parallel.
- Do not push to GitHub unless explicitly asked.
- Use `docs/phase-02/current-project-state.md` as the single rolling progress source.
- Patch other docs only when stable requirements, scope, ownership, or durable lessons change.
- Before implementation, run git status/log preflight and stop on unexpected app-code dirt.
- Use Laravel Boost `application_info`, `database_schema`, and `search_docs` when relevant.
- Use FilamentExamples MCP when the prompt asks for Filament examples. Record whether it returned snippets, summaries, or source-level access.
- Treat the referenced blueprint as the detailed implementation contract.
- Tests must prove real behavior, not only class existence.
- Browser tests are allowed only when they protect real visible UI behavior and remain small.
- Do not run `vendor/bin/filacheck --fix` unless explicitly approved.
- Final implementation gate normally includes:
  - `php artisan test`
  - `vendor/bin/pint --test`
  - `vendor/bin/filacheck`
  - `npm run build`

## Official-doc patterns to verify during implementation

Use Boost docs or installed source for current syntax before coding:

- Filament `Select::relationship()`, `createOptionForm()`, `createOptionUsing()`, `editOptionForm()`, `createOptionAction()`, and `editOptionAction()`.
- Filament relation managers, `getRelations()`, stable relation keys, `CreateAction`, `AssociateAction`, and sharing Resource form/table definitions.
- Livewire `wire:model.live`, `#[Url]`, and pagination.
- Laravel seeders, split seeders, factories, and `WithoutModelEvents`.

## Current strategic decisions

- Public cards remain `ContentItem` records, never public `Transcription` cards.
- Contributors/transcribers use `Author` as the public-safe contributor model for now.
- Do not expose `User` records publicly.
- `Transcription` belongs to `Author`, so contributor rankings and pages should be derived from public transcriptions.
- Only public rows count for top transcribers:
  - published group;
  - published item;
  - published/effective transcription.
- Prompt 12 must not implement admin relationship UX or contributor discovery.
