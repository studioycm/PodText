# Codex Prompt - Public Front v2 Step 10R-A Settings Render Context

Work in the current local clone of `studioycm/PodText`.

Implement only Step 10R-A.

## Goal

Create a request-scoped public-front render context so public pages, Livewire components, menu renderers, section resolvers, card template resolvers, and form CTA resolvers consume one normalized `PublicContentSettings` snapshot per request/render lifecycle.

## Read First

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-rendering-settings-transcriber-audit.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/research/public-front-v2/15-step10r-rendering-settings-card-template-mcp-research.md`

## Scope

- Add `PublicFrontRenderContext` and a factory/scoped binding.
- Validate public-front settings once per request.
- Move public settings reads toward the context without changing visible behavior.
- Keep persistent derived cache optional; do not add it unless there is a clear reason.
- If any derived cache is added, invalidate it when `PublicContentSettings` is saved.
- Keep `PublicContentSettings` as the storage source.

## Out Of Scope

- Card-template rendering behavior changes.
- Transcriber attribution fixes.
- Layout redesign.
- Footer/rich section builder.
- Schema changes.
- Step 11 and Prompt 13.

## Tests

Add focused Pest coverage proving:

- the context exposes normalized settings groups;
- repeated context use in one request does not re-read/re-normalize settings unnecessarily;
- saved settings are visible on the next request/context;
- card template option reads are not stale after save;
- public URL-backed Livewire state still works where touched.

## Quality Gate

Run:

```bash
php artisan test --filter=Public
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

If the touched surface is broader, run the full `php artisan test`.
