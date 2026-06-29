# Phase 02 Homepage Settings Spec

## Settings

Use Spatie Settings in Prompt 08. This package choice is approved for Phase 02 implementation; do not ask for package approval again when Prompt 08 reaches this work. If the packages are absent at implementation time, Prompt 08 owns adding them as part of that implementation task.

Suggested settings:

- homepage item limit;
- pinned item limit;
- default public sort;
- default result layout;
- item page layout option;
- show/hide latest section;
- media embed display behavior.

## Homepage Sections

Use normal database records for ordered visible homepage sections when dynamic sections are required.

Suggested `homepage_sections` fields:

- `name`
- `slug`
- `type`
- `category_id`, nullable
- `tag_id`, nullable
- `content_group_id`, nullable
- `limit`
- `sort_order`
- `is_visible`

Supported section types should be explicit finite values, such as latest, category, tag, group, and curated query.

## Group Homepage Order

Add a group ordering field only where public group ordering needs it. It does not replace item pinning.

## Blueprint

See `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`.
