# Phase 02 Homepage and Settings Spec

## Global Settings

Use Spatie Settings later for typed global settings. Do not install packages in this planning task.

Likely settings:

- homepage item limit
- pinned item limit
- default public sort
- public result layout
- item page layout options
- homepage introduction copy keys or content references
- media embed default behavior

## Homepage Sections

If editors need ordered dynamic homepage areas, use a normal database model/resource such as `HomepageSection`.

Likely fields:

- `name`
- `slug`
- `type`
- `category_id`
- `tag_id`
- `content_group_id`
- `limit`
- `sort_order`
- `is_visible`

This follows the FilamentExamples homepage-section pattern. It does not replace item pinning.

## Content Group Ordering

Add explicit ordering fields only where the public UI actually needs them. Group ordering belongs to group display contexts, not item pinning.

## Tests Required Later

- Settings can be edited by admins.
- Defaults are applied when settings rows do not exist.
- Homepage sections render only visible sections.
- Section queries return public `ContentItem` records only.
