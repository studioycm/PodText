# Prompt 08: Phase 02 Taxonomy, Tags, Pinning, and Settings

Work sequentially after Prompt 07 is complete and committed.

Implement only:

- custom hierarchical categories;
- item-level pinning fields on `ContentItem`;
- global settings foundation;
- tag package integration only after confirming package approval.

Pinning belongs only to `ContentItem`. Do not add transcription, group, category, or tag pinning.

Categories are custom hierarchical records. Tags are flat Spatie tags scoped to `content`, with public enablement fields.

Do not implement public homepage/search UI, admin polish, dashboards, or studio features in this prompt.

Tests must cover category hierarchy, inherited item categories, item pin scopes, settings defaults, tag scoping, disabled tag hiding, and no public exposure of unpublished content.

Run the project quality gate required by `AGENTS.md` before committing.
