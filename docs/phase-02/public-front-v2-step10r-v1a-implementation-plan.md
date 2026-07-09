# Public Front v2 Step 10R-V1a Implementation Plan

## Selected Step

Step 10R-V1a - Default/no-image fallback settings.

Dependencies satisfied: Step 10R-UX2 is complete as
`e99f22a feat: add effective transcription edit action to episode lists`. The v4
enhancement plan is active, the ledger says V1a is first pending, and Step 11 / Prompt
13 have not started.

## Current Repo Evidence

- `git status --short --branch`: clean `main...origin/main`.
- `git log --oneline --decorate -20` includes UX2 commit `e99f22a`.
- `php artisan migrate:status` reports migrations through
  `2026_07_09_000004_align_public_transcription_display_defaults` as ran.
- Route preflight confirms `/podcasts`, `/podcasts/{contentGroupSlug}`,
  `/contributors`, `/contributors/{authorSlug}`, and `/search`.
- `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md` is v4 and the
  sequence/current-state docs mark V1a as next.
- `PublicFrontConfigRegistry`, `PublicFrontConfigValidator`,
  `PublicFrontRenderContext`, and settings migrations own public-front JSON settings.
- Content item/group/contributor cards already render placeholders when no image URL is
  present.
- Public item and group detail pages currently implement image fallbacks inline; the
  contributor detail page currently renders no image area.

## Research Notes

Research note:
`docs/research/public-front-v2/20-step10r-v1a-mcp-research.md`.

Boost confirmed FileUpload APIs and storage constraints for Filament 5. FilamentExamples
provided snippet-level patterns for SettingsPage image upload fields and card image
fallback/placeholder branching.

## Settings Design

Add `default_images` under `public_content`:

```php
[
    'global' => ['mode' => 'inherit', 'path' => null],
    'content_item' => ['mode' => 'inherit', 'path' => null],
    'content_group' => ['mode' => 'inherit', 'path' => null],
    'contributor' => ['mode' => 'inherit', 'path' => null],
]
```

Finite modes:

- `inherit`: use the next fallback level.
- `custom`: use the configured public-disk image path when no more specific image exists.
- `none`: stop the fallback chain for that family and render the existing placeholder or
  initials block.

Storage contract:

- public disk;
- `default-images/` directory;
- JPEG/PNG/WebP only;
- 2048 KB max;
- no remote fetching;
- validator rejects arbitrary directories, traversal, absolute paths, and non-image
  extensions.

## Fallback Policy

Content item:

1. `content_items.external_thumbnail_url`;
2. parent `content_groups.cover_path`, unless `content_item.mode` is `none`;
3. `default_images.content_item.path` when mode is `custom`;
4. `default_images.global.path` when content item mode is `inherit` and global mode is
   `custom`;
5. placeholder when no URL resolves.

Content group:

1. `content_groups.cover_path`;
2. `default_images.content_group.path` when mode is `custom`;
3. `default_images.global.path` when content group mode is `inherit` and global mode is
   `custom`;
4. initials placeholder when no URL resolves.

Contributor:

1. future contributor image field, if one is later introduced;
2. `default_images.contributor.path` when mode is `custom`;
3. `default_images.global.path` when contributor mode is `inherit` and global mode is
   `custom`;
4. initials placeholder when no URL resolves.

Current schema has no contributor image column, so V1a starts at step 2 for contributor
surfaces.

## Files To Change

- `app/Settings/PublicContentSettings.php`.
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`.
- `app/Support/PublicFront/PublicFrontConfigValidator.php`.
- `app/Support/PublicFront/PublicFrontRenderContext.php`.
- Add a focused public-front default image resolver under `app/Support/PublicFront`.
- `app/Filament/Pages/PublicContentSettings.php`.
- Public card presenters and contributor/card Blade where image rendering is needed.
- Public item/group/contributor detail page classes/views.
- `lang/en/admin.php` and `lang/he/admin.php`.
- Add `database/settings/2026_07_09_000005_add_public_default_image_settings.php`.
- Add/update focused public-front tests.
- Update V1a docs, ledger, current state, and handoff.

## Tests

- Registry/validator normalizes defaults, modes, paths, and unknown/unsafe input.
- Settings migration adds the `default_images` row.
- Admin settings page saves custom/default/no-image settings.
- Content item card and item detail page preserve explicit item thumbnail and podcast
  cover precedence.
- Content item `custom`, `inherit` global, and `none` modes render the expected card and
  detail output.
- Content group card/detail resolve cover, group custom/global fallback, and `none`
  initials.
- Contributor card/detail resolve contributor custom/global fallback and `none` initials.
- Existing bounded public query-count harness remains green.

## Risks

- Content item `none` needs to suppress the current podcast-cover fallback only when the
  item lacks its own explicit image. Tests should make this distinction clear.
- Contributor detail did not previously have a visual image block; V1a must add it
  without disrupting the existing name/count/bio hierarchy.
- FileUpload path tampering is handled by isolated directory plus validator
  normalization. The settings page should not accept SVG for default content images.
- Existing tests that assert podcast cover fallback must still pass under default
  `inherit` settings.

## Out Of Scope

- Step 10R-V1b icon picker and Heroicon registry.
- Step 10R-V1c custom colors and sampled palette cache.
- P1 config cache.
- Contributor-owned image schema.
- Remote image fetching or image generation.
- Any AX/SL/slider/motion work.

## Stop Conditions

- Stop if app-code dirt appears before implementation.
- Stop if defaults cannot be added as a settings migration without schema changes.
- Stop if FileUpload constraints require a package or storage change outside the
  existing public disk.
- Stop if V1a would require implementing V1b/V1c settings.
