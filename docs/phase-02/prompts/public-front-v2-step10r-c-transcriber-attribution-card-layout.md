# Codex Prompt - Public Front v2 Step 10R-C Transcriber Attribution and Card Layout

Work in the current local clone of `studioycm/PodText`.

Implement only Step 10R-C after Step 10R-A and Step 10R-B are committed.

## Goal

Correct public transcriber attribution and normalize public card layout consistency without schema changes.

## Read First

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-rendering-settings-transcriber-audit.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/research/public-front-v2/15-step10r-rendering-settings-card-template-mcp-research.md`

## Scope

- Public item cards use effective/main transcription author by default.
- Contributor-context item cards use contributor-specific transcription authors/titles where relevant.
- Item-level `ContentItem::authors` is not labeled or rendered as transcribers.
- Item detail header stops mislabeling item-level authors as transcribers.
- Eager load transcription authors for public card paths.
- Add safe semantic layout defaults/class maps for consistent card rows:
  - image ratio/source policy;
  - title and description clamps;
  - metadata/taxonomy reserved regions where needed;
  - duplicate group thumbnail suppression.

## Out Of Scope

- `author_transcription` pivot or other schema changes.
- Import/export changes for multi-transcriber transcriptions.
- Footer/rich section builder.
- Generic CMS features.

## Tests

Add focused Pest coverage proving:

- effective transcription author displays when item authors differ;
- item authors do not display as transcribers;
- contributor preview and contributor full page keep grouped transcription titles;
- top transcriber preview uses selected contributor context;
- cards without item image use group cover consistently;
- duplicate group thumbnails stay suppressed;
- semantic layout settings/tokens produce stable card output.

## Quality Gate

Run:

```bash
php artisan test --filter=Public
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Run the full `php artisan test` if attribution or query changes touch shared public visibility behavior.
