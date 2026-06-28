# Prompt 07: Phase 02 Transcriptions Model Revision

Work sequentially in the current repository. Read `AGENTS.md` and the Phase 02 specs/guidelines first.

Implement only the transcription domain revision.

Required:

- create `Transcription` model, migration, factory, and relationships;
- move public transcript semantics from `ContentItem::transcript_markdown` to child transcriptions;
- add effective/main transcription resolution;
- support a nullable featured transcription on `ContentItem`;
- backfill existing item transcripts into transcriptions;
- preserve safe Markdown rendering;
- update imports/tests only where required by the domain move.

Do not implement taxonomy, tags, homepage redesign, dashboards, media metadata extraction, or studio features.

Tests must cover relationships, casts, migration/backfill, publication scopes, public visibility, effective/main resolution, draft transcript hiding, and Markdown XSS safety.

Run the project quality gate required by `AGENTS.md` before committing.
