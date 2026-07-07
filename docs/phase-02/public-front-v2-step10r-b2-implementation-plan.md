# Public Front v2 Step 10R-B2 Implementation Plan

## Selected Mini-Step

Step 10R-B2 - Real content-item card part renderer.

## Goal

Make `content_item` card templates visibly control rendered card parts while preserving current public behavior for default cards. Move content-item card data preparation out of Blade into app-owned PHP presenter/renderer code. Keep rendering safe through finite maps and escaped output.

## Preconditions Confirmed

- Working tree was clean before implementation except for the required ledger update that marked Step 10R-B2 in progress.
- Current branch is `main`, ahead of `origin/main` by one local B1 commit.
- Latest local B1 commit is `34c6032 fix: expose custom public card templates in settings`.
- Ledger and current state agree that Step 10R-B2 is next.
- Prompt 13 has not started.
- Step 11 has not started and remains blocked without explicit approval.
- Step 2 transcription publication policy remains deferred/reserved.

## Scope

In scope:

- `content_item` cards only.
- Homepage latest/manual/category/tag/group-item sections.
- Search/category/tag listing cards.
- Podcast detail item cards using `podcasts_page.item_template_key`.
- Existing compatibility data attributes.
- Focused Pest tests for visible part order, hidden parts, escaped custom text, and public page surfaces.

Out of scope:

- `content_group` and `contributor` card part rendering.
- Transcriber attribution correction from `ContentItem::authors` to `Transcription::author`.
- Full convergence of `PublicContentCardOptions` with templates.
- New settings keys or migrations.
- Public Filament Tables.

## Implementation Steps

1. Add a content-item card presenter under `App\Support\PublicFront\Cards`.
   - Prepare static view data for item URL, image state, title, description, current author badges, date/duration metadata, taxonomy links, and ordered template parts.
   - Use already loaded relations where available.
   - Keep custom text plain and escaped at render time.

2. Extend `PublicFrontCardTemplateRenderer`.
   - Include all B2-supported content-item parts in the controlled part list.
   - Expose an ordered `contentItemParts()` method using finite part types from `PublicFrontCardTemplate`.
   - Keep presentation class maps static and app-owned.

3. Update `resources/views/components/public/content-item-card.blade.php`.
   - Resolve presenter data once.
   - Loop through prepared parts.
   - Render known part types through static Blade branches.
   - Preserve card attributes and current default visual behavior where possible.

4. Add/update focused tests.
   - Homepage latest cards render a custom part order and hide absent parts.
   - Search/category/tag item cards render custom parts.
   - Podcast detail item cards visibly use `podcasts_page.item_template_key`.
   - Hidden parts do not render.
   - Unsafe custom values are escaped.
   - Default output still includes the existing expected card content.

5. Update documentation.
   - Research note.
   - This implementation plan.
   - Handoff.
   - Ledger.
   - Current project state, including B1 commit correction and B2 completion state.

6. Run focused tests and the required quality gate.

7. Commit with:

```text
feat: render content item card template parts
```

## Safety Decisions

- No raw Blade paths, raw HTML, PHP class names, raw CSS, scripts, iframe HTML, SQL, or unsafe URLs will be rendered from template JSON.
- Template `custom_text` renders escaped text only.
- URL rendering uses existing app route helpers for content item, category, and tag links.
- Current `transcriber_line` output remains backed by the existing item-author display until Step 10R-C1 changes attribution source.
- If a configured template contains no visible supported parts, the public card remains safe and still exposes compatibility metadata.

## Focused Test Command

```bash
php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php tests/Feature/PublicDisplaySectionsLoopersTest.php tests/Feature/PublicHomepageSearchTest.php tests/Feature/PublicPodcastsGroupsUxTest.php
```

## Final Quality Gate

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```
