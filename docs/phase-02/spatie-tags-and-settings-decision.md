# Spatie Tags and Settings Decision

## Status

Accepted for the post-Prompt-09 admin UX repair.
Still active after Prompt 10 import/export implementation.

## Spatie Tags

PodText should continue using standard Spatie Tags behavior for content tags:

- `App\Models\ContentItem` uses `Spatie\Tags\HasTags`.
- The admin item form uses `SpatieTagsInput::make('tags')->type('content')`.
- Tags are stored in Spatie's `tags` table.
- Tag assignments are stored in Spatie's `taggables` pivot.
- No duplicate custom tag pivot should be introduced.

`App\Models\ContentTag` is kept because it is configured as Spatie's custom tag model in `config/tags.php` and stores Phase 02 editorial metadata on the normal Spatie `tags` table:

- `is_enabled`
- `enabled_at`
- `enabled_by_id`
- `created_by_id`
- `moderation_state`

Those fields are needed so disabled tags remain admin-only while enabled content tags can be used by public queries in later prompts. The model extends `Spatie\Tags\Tag`, uses the existing `tags` table, and does not replace Spatie's pivot behavior.

Prompt 10 import/export uses the same decision: content item imports attach existing Spatie tags scoped to `type = content`; missing tags, wrong tag types, and disabled-public content tags fail by default instead of creating unknown or unscoped tags.

Do not remove `ContentTag` blindly. A future refactor would need a migration/data-impact plan, public visibility replacement, admin Resource changes, and regression tests for enabled/disabled content tags.

## Spatie Settings

`App\Settings\PublicContentSettings` stores global public defaults and limits:

- homepage item limit;
- pinned item limit;
- default public sort;
- default result layout;
- latest-section default visibility;
- item page layout preference.

`HomepageSection` records configure homepage content slices: latest, category, tag, and content-group sections. They are not the same responsibility as settings. Item pinning is also separate and affects ordering where public list queries support pinned-first behavior.

Public pages do not consume `PublicContentSettings` or `HomepageSection` yet because Prompt 11 has not run. Prompt 11 must read both when building the homepage/search UI.

## Deferred

- Curated homepage query sections remain deferred until there is a concrete query-builder spec.
- Homepage result preview in the admin form is deferred to Prompt 11 or a later admin enhancement after the public query service/component exists.
- Associate-existing transcription is deferred because a transcription belongs to one content item, so association would move it from another item rather than copy it.
