# Phase 02 Taxonomy and Tags Spec

## Categories

Categories are custom hierarchical records, not Spatie tags.

Recommended fields:

- `id`
- `parent_id`
- `name`
- `slug`
- `description_markdown`
- `is_visible`
- `sort_order`
- timestamps

Relationships:

- `category_content_group`
- `category_content_item`

Group categories are inherited by child items for public display/filtering. Item categories can add narrower classification. Filtering by a parent category includes descendants.

## Tags

Use Spatie Laravel Tags later, with the Filament Spatie Tags plugin. Do not install packages in this planning task.

Rules:

- Tags are flat.
- Public content tags use the `content` type/scope.
- Public pages show only enabled public tags.
- Admin forms must not use unscoped free-form tags.
- If a custom tag model is needed, add fields such as `is_enabled`, `enabled_at`, `enabled_by_id`, and `created_by_id`.

## Public Pages

Provide category and tag landing pages that list `ContentItem` records. They must follow the same publication and effective/main transcription visibility rules as search.

## Tests Required Later

- Category hierarchy relationships.
- Group category inheritance.
- Parent category descendant filtering.
- Spatie tag type scoping.
- Disabled tags hidden publicly.
- Public category/tag pages return only visible published items.
