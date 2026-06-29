# PodText

PodText is a Hebrew-first transcription platform built with Laravel, Filament, and Livewire.

## Stack

- Laravel 13
- Filament 5
- Livewire 4
- Tailwind CSS 4
- Pest
- Laravel Boost
- Filament Blueprint
- FilaCheck and FilaCheck Pro

Installed package versions are the source of truth.

## Domain Terms

Internal names stay generic:

- `ContentGroup`: container of content items; default public concept is podcast.
- `ContentItem`: content item inside a group; default public concept is episode.
- `Author`: credited contributor.
- `Transcription`: transcript record belonging to a content item.

Public labels may say podcast/episode, but PHP classes, database tables, Resources, and namespaces should keep the generic internal names unless the architecture is explicitly changed.

## Active AI Workflow

- `AGENTS.md` contains evergreen repository rules.
- Active specs are under `docs/phase-02/`.
- Active research is under `docs/research/`.
- Active implementation prompts are under `prompts/`.
- Historical docs and prompts are archived and are not active instructions unless a current prompt explicitly references them.

Start here:

- [Documentation index](docs/README.md)
- [Prompt index](prompts/README.md)
- [Phase 02 feature map](docs/phase-02/feature-map.md)
- [Tooling and quality gates](docs/phase-02/tooling-and-quality-gates.md)

## Current Implementation Status

Prompt 07 was run and committed. Prompt 08 is next only after human review and local Prompt 07 migration/test verification.

Current state details live in [docs/phase-02/current-project-state.md](docs/phase-02/current-project-state.md).

## Quality Gates

Implementation prompts normally finish with:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Documentation-only tasks should run the checks required by their prompt.

## Secrets and Local Config

Do not commit or print secrets or machine-local configuration, including:

- `.env`;
- MCP tokens;
- `.codex` configuration;
- Composer auth;
- FilaCheck license data;
- IDE-local configuration.
