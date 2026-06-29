# Phase 02 Taxonomy and Tags Spec

## Categories

Categories are custom hierarchical records.

Suggested table: `categories`.

Fields:

- `id`
- `parent_id`, nullable self-reference
- `name`
- `slug`
- `description_markdown`, nullable
- `is_visible`, boolean
- `sort_order`, integer
- timestamps

Pivots:

- `category_content_group`
- `category_content_item`

Rules:

- `ContentGroup` has categories.
- `ContentItem` can have categories.
- Public item category set is item categories plus inherited group categories.
- Parent category filters include descendants.
- Category slug fields auto-generate from `name` in admin forms, allow manual override, and include helper text explaining public URL use.

## Tags

Use `spatie/laravel-tags` with the Filament Spatie Tags plugin in Prompt 08. This package choice is approved for Phase 02 implementation; do not ask for package approval again when Prompt 08 reaches this work. If the packages are absent at implementation time, Prompt 08 owns adding them as part of that implementation task.

Rules:

- Tags are flat.
- Use Spatie's `taggables`; do not create a duplicate custom tag pivot.
- Content tags are scoped to type `content`.
- Public pages show enabled tags only.
- Plan a custom Spatie Tag model/extra fields:
  - `is_enabled`
  - `enabled_at`
  - `enabled_by_id`
  - `created_by_id`
  - future moderation state
- Tag slug/reference fields shown in admin must include helper text and use translation-key labels.

## Admin/Public Behavior

- Admins manage categories and tags.
- Public category/tag landing pages list `ContentItem` records only.
- Disabled tags never create public pages or public filters.

## Blueprint

See `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md` and `docs/phase-02/blueprints/09-admin-content-management-blueprint.md`.
