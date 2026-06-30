# Validation and Final Report

## Out of scope

Do not:

- implement the relation manager now;
- scaffold Resources;
- run Filament generators;
- install packages;
- run Prompt 08;
- edit PHP, Blade, migrations, tests, Resources, Livewire components, or config.

## Stale-file check

Search active docs/prompts/guidelines for stale statements that would conflict with this research, including:

- Prompt 09 manages transcriptions only globally and not item-scoped;
- relation managers are not planned;
- legacy `content_items.transcript_markdown` remains in item forms;
- repeaters should be used for full transcript bodies;
- create/edit redirect behavior is unspecified when the research decided it;
- combined relation tabs are missing from the Prompt 09 blueprint if research recommends them.

Patch only active Markdown files. Leave archived files alone.

## Validation commands

Run:

```bash
git diff --check
git status --short
```

Do not run migrations.

Do not run npm build.

Do not run FilaCheck unless the active repo convention requires it for docs. If FilaCheck is run and it modifies app/test files, revert those side effects and document it.

## Final report

Return:

1. FilamentExamples MCP searches performed.
2. Whether MCP returned source snippets or only summaries.
3. Official docs consulted.
4. Recommended admin Resource / Relation Manager approach.
5. Decision on standalone `TranscriptionResource`.
6. Decision on `ContentItemResource` `TranscriptionsRelationManager`.
7. Decision on combined content/relation tabs.
8. Decision on create/edit redirects.
9. Relation manager vs relation page vs repeater decision.
10. Files patched.
11. Historical next-prompt status at the time of the research task.
12. Whether Prompt 09 has been updated for relation-manager/resource UX.
13. Command results.
14. Current git status.

End with exactly:

```text
Admin Resource and Relation Manager research is ready for human review. No application features were implemented.
```
