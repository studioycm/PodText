# Prompt 12: Phase 02 Media Embed and Item Page

Work sequentially after Prompt 11 is complete and committed.

Implement only media embed refinements and the public item page.

Required:

- URL-only media/embed fields and provider metadata fields;
- strict HTTPS host allowlist;
- application-owned Blade media component;
- fallback original source link;
- item page for one `ContentItem`;
- effective/main transcription default;
- published transcription tabs/selector;
- reading time, duration, transcript length, categories/tags, author links, copy/share actions;
- safe transcript rendering.

Do not fetch remote metadata automatically, build a studio, or add analytics.

Tests must cover approved/rejected embed URLs, fallback link rendering, draft hiding, item page guest access, transcript tabs, metadata display, XSS safety, and RTL layout markers.

Run the project quality gate required by `AGENTS.md` before committing.
