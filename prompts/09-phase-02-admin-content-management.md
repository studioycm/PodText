# Prompt 09: Phase 02 Admin Content Management

## Goal

Implement admin management for Phase 02 models and fields created by Prompts 07 and 08.

## Current state assumptions

- Prompts 07 and 08 are complete and committed.
- Packages approved in Prompt 08 are installed and configured.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- all Phase 02 specs relevant to transcriptions, taxonomy, tags, settings, media, and homepage.

## Blueprint and guidelines

- `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`
- `docs/research/filament-examples-admin-resource-relation-managers.md`
- `.ai/guidelines/transcriptions.md`
- `.ai/guidelines/taxonomy-tags.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/media-embeds.md`
- `.ai/guidelines/tooling-quality.md`

## Blueprint contract

The blueprint file named above is the detailed implementation contract for this prompt.

Before changing code:

1. Read the entire blueprint.
2. Summarize the blueprint sections that apply to this prompt.
3. Compare the blueprint against the current repository state.
4. If the blueprint conflicts with the active prompt, Phase 02 specs, `AGENTS.md`, or current code, stop and report the conflict before implementing.
5. If the prompt body is shorter than the blueprint, follow the blueprint details.
6. Do not omit blueprint fields, relationships, constraints, Filament components, tests, or quality checks unless the blueprint marks them optional or the current code makes them impossible.
7. In the final report, include a "Blueprint completion checklist" with:
   - implemented;
   - already existed;
   - deferred by blueprint;
   - not applicable;
   - blocked.

Implement the admin form/table/action details from `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`.

- The shared admin form rules in the blueprint are required, not optional.
- All slug fields must auto-generate from the relevant name/title field while allowing manual override.
- Slug fields must use Hebrew-friendly labels/helper text, for example `מזהה כתובת`.
- Technical fields such as `reference_key`, `slug`, `provider`, `external_id`, metadata JSON, pin fields, parser JSON, language codes, and `featured_transcription_id` must have hints/help text/descriptions.
- Date and date-time fields and table columns must use day-first Israeli/Hebrew display/input.
- Use `Asia/Jerusalem` for UI timezone presentation while storing dates normally.
- Use translation keys for labels, helpers, hints, section headings, validation messages, date labels, and sort labels.

## Scope

- Transcription Resource/management.
- Category Resource.
- HomepageSection management.
- Settings page.
- ContentItem/ContentGroup admin field updates for categories, tags, pinning, featured transcription, and media metadata.

## Admin form requirements

- All slug fields should auto-generate from the relevant name/title field using current Filament v5 patterns, preferably live-on-blur / `afterStateUpdated` behavior, and should not overwrite a manually edited slug.
- Slug labels should be Hebrew-friendly, for example `מזהה כתובת`, with helper text explaining the value is used in the URL.
- Technical fields such as `reference_key`, `slug`, `provider`, `external_id`, metadata JSON, pin fields, and `featured_transcription_id` need hints, helper text, or descriptions.
- Date fields must display and accept `dd/mm/yyyy`.
- Date-time fields must display and accept `dd/mm/yyyy HH:mm` unless the docs/blueprint define another day-first Israeli format.
- UI timezone for date/time fields is `Asia/Jerusalem`; store dates using Laravel's normal storage convention.
- Admin table date columns must also display day-first Israeli/Hebrew format.
- Use translation keys for all labels, helpers, hints, section headings, and validation messages.
- Check FilamentExamples/Povilas-style slug auto-generation examples through MCP or Boost docs before implementing slug behavior.
- Ensure FilaCheck passes on form schemas.

## Out of scope

No public homepage/search, item page rebuild, import/export, dashboards, or studio.

## Package/tool assumptions

Use Boost docs, Filament Blueprint, FilamentExamples MCP, FilaCheck, and FilaCheck Pro. Do not install new packages unless already approved by Prompt 08.

## Relation manager and Resource UX research contract

Before implementing admin Resources, read:

- `docs/research/filament-examples-admin-resource-relation-managers.md`
- `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`

Required:

- Implement the researched `ContentItemResource\RelationManagers\TranscriptionsRelationManager` plan unless the blueprint marks it deferred.
- Keep standalone `TranscriptionResource` for global search/filtering and use the item relation manager as the primary item-scoped transcript editing surface.
- Use the researched decision for combined content/relation tabs on `EditContentItem`.
- Use the researched tab label, icon, badge, and badge-deferral guidance.
- Use the researched redirect behavior for standalone Create/Edit pages.
- Keep relation manager create/edit actions on the owner item edit page.
- Use Resource URLs, not hard-coded route names.
- Do not use a Repeater for full transcript Markdown.
- Treat a dedicated `ManageRelatedRecords` page as future optional unless implementation proves the relation manager is insufficient.
- Include a Blueprint completion checklist section for relation managers, combined tabs, redirects, create-another behavior, and relation-page/repeater decisions.

## Implementation plan

1. Write Resource smoke and validation tests.
2. Scaffold Resources/pages with Filament commands.
3. Implement schemas/tables/actions using split classes.
4. Add admin translations.
5. Run focused tests and FilaCheck dirty scans during iteration.

## Acceptance criteria

Admins can manage transcriptions, categories, tags, homepage sections/settings, item pins, featured transcriptions, and media metadata without exposing public-only routes.

## Required tests

Resource smoke, create/edit validation, feature transcription action, `ContentItemResource` transcriptions relation manager rendering/create/edit/filtering/owner scoping, combined content/relation tabs, standalone create/edit redirects, disabled create-another behavior where specified, relation manager create/edit staying on the owner item edit page, pin controls, category/tag assignment, settings management, and admin access protection.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Check FilaCheck Pro pitfalls listed in `tooling-and-quality-gates.md`.

## Final report format

Report files changed, tests added, commands/results, assumptions, deferred issues, and FilaCheck output.

## Commit behavior

Commit only after the full quality gate passes.
