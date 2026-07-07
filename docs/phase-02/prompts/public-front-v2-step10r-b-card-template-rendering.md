# Codex Prompt - Public Front v2 Step 10R-B Card Template Rendering

Work in the current local clone of `studioycm/PodText`.

Implement only Step 10R-B after Step 10R-A is committed.

## Goal

Make public card templates visibly affect content item, content group, and contributor cards through safe finite part rendering, not only `data-card-template-*` compatibility attributes.

## Read First

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-rendering-settings-transcriber-audit.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/research/public-front-v2/15-step10r-rendering-settings-card-template-mcp-research.md`

## Scope

- Make custom template options available in podcast, homepage-section, and contributor-related settings paths.
- If feasible, make newly added templates available in dependent selects during the same settings form session.
- Implement safe part renderers for:
  - `content_item`
  - `content_group`
  - `contributor`
- Make hidden/reordered parts affect actual HTML output.
- Fold or adapt `PublicContentCardOptions` so it does not conflict with template-driven parts.
- Keep fixed class maps in code.

## Out Of Scope

- Transcriber schema changes.
- Footer/rich section builder.
- Generic CMS features.
- Raw Tailwind, raw CSS, raw Blade, PHP class names, raw HTML, scripts, or SQL in JSON.

## Tests

Add focused Pest coverage proving custom template output on:

- homepage latest cards;
- podcast detail item cards;
- podcast/group index cards;
- contributor item cards;
- top transcriber selector/preview cards.

Also test:

- hidden parts disappear;
- part order affects rendered output;
- invalid/unsafe parts are rejected.

## Quality Gate

Run:

```bash
php artisan test --filter=Public
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

If rendering changes touch shared public components broadly, run the full `php artisan test`.
