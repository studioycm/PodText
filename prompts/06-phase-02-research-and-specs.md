# Prompt 06: Phase 02 Research and Specs

Work in the current repository only.

Do not implement application features. Do not install packages.

Use the configured `filament-examples` MCP server and do not write any MCP token or secret into a file.

Read:

1. `AGENTS.md`
2. `docs/project-description.md`
3. `docs/architecture-decisions.md`
4. `docs/import-export-spec.md`
5. `docs/project-phases.md`
6. Current application state, routes, models, migrations, tests, and existing prompts.

Create or update:

- `docs/research/filament-examples-phase-02.md`
- `docs/phase-02/feature-map.md`
- `docs/phase-02/answers-coverage-matrix.md`
- all Phase 02 spec files
- `.ai/guidelines/phase-02-*.md`
- prompts `07` through `14`

The coverage matrix must confirm:

- public listings/search/homepage results are `ContentItem` records;
- pinning belongs only to `ContentItem`;
- latest transcriptions means items ordered by effective/main published transcription date;
- homepage UX, filters, categories, Spatie tags, media embeds, item page, parser/viewer, studio planning, import/export, dashboards, and settings are covered.

Before committing, run:

```bash
git diff --check
git status --short
```

Commit only documentation, prompt, and guideline changes if the safety checks pass.
