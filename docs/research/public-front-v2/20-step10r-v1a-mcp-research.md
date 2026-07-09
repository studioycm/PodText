# Public Front v2 Step 10R-V1a MCP Research

Date: 09/07/2026

## Scope

Step 10R-V1a adds default/no-image fallback settings for public content item,
content group, contributor, and global image families.

## Local Repository Evidence

- Preflight `git status --short --branch` reported clean `main...origin/main`.
- Recent history includes UX2 commit
  `e99f22a feat: add effective transcription edit action to episode lists`.
- `php artisan migrate:status` reports all migrations through
  `2026_07_09_000004_align_public_transcription_display_defaults` as ran.
- Public route preflight found `/podcasts`, `/podcasts/{contentGroupSlug}`,
  `/contributors`, `/contributors/{authorSlug}`, and `/search`.
- The v4 enhancement plan is active and the ledger/current-state docs agree that
  Step 10R-V1a is the next pending mini-step after UX2.
- Current image fallback behavior is split across card presenters and public detail
  pages:
  - content item cards/detail use item thumbnail, then podcast cover;
  - content group cards/detail use podcast cover, then initials;
  - contributor cards/detail use initials only.
- `PublicFrontConfigRegistry`, `PublicFrontConfigValidator`,
  `PublicFrontRenderContext`, and settings migrations are the existing settings
  boundary for public-front JSON groups.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed installed versions: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3,
  Pest 4.7.4, Tailwind 4.3.2, local SQLite.
- Boost schema confirmed the `settings` table exists, `content_groups.cover_path`
  stores podcast covers, `content_items.external_thumbnail_url` stores item image URLs,
  and `authors` currently has no contributor image column.
- Filament FileUpload supports `disk()`, `directory()`, and `visibility()` for
  storage-managed uploads.
- Filament FileUpload accepts client-controlled string paths by default. Boost docs
  recommend either path-tampering protection or isolating uploads by disk/directory.
  V1a uses a dedicated public `default-images/` directory plus validator path
  normalization.
- Filament image uploads should constrain MIME types and max size. Existing PodText
  public-front image uploads already use JPEG/PNG/WebP and 2048 KB; V1a follows that
  convention.
- File previews require correct public disk URL behavior. V1a uses the existing public
  disk and does not introduce remote fetching.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No separate source/read/fetch
tool was exposed.

Initial query batch:

- `settings page file upload`
- `image upload settings`
- `FileUpload public disk`

Refined query batch:

- `SettingsPage FileUpload logo columnSpanFull`
- `public card image fallback placeholder`
- `Filament settings image upload preview`

Relevant examples and PodText adaptation notes:

- **Eshop With Front Page**:
  - File/class: `app/Filament/Pages/ManageSettings.php`.
  - Pattern to copy: `SettingsPage` with `FileUpload::make('logo')` inside a settings
    form.
  - Pattern to avoid: single-logo-only assumptions.
  - PodText adaptation: add one settings section with four finite family fields instead
    of a one-off logo setting.
- **Eshop With Front Page Product Form**:
  - File/class: `app/Filament/Resources/Products/Schemas/ProductForm.php`.
  - Pattern to copy: image upload components use constrained image handling and
    full-width placement where the image is the primary control.
  - Pattern to avoid: product media-library coupling.
  - PodText adaptation: use native `FileUpload` because V1a stores a single public-disk
    path per family, not a media-library collection.
- **Table As Grid With Cards**:
  - Pattern found: card views branch between stored image URLs and non-image placeholders.
  - Pattern to copy: keep placeholder rendering available when no URL is resolved.
  - Pattern to avoid: custom card-only fallback logic.
  - PodText adaptation: resolve image policy before Blade, then let cards/detail pages
    render either the resolved URL or existing initials/placeholder blocks.

## Implementation Implications

- Add a `default_images` settings group with families `global`, `content_item`,
  `content_group`, and `contributor`.
- Each family stores only `mode` and `path`, where mode is one of `inherit`, `custom`,
  or `none`.
- Reuse the existing validator pattern and public image path guard with a new
  `default-images/` directory.
- Add a render-context accessor and a focused resolver so presenters and detail pages
  consume the same fallback policy.
- Keep existing explicit images first:
  - content item: item thumbnail, then podcast cover, then configured fallbacks;
  - content group: podcast cover, then configured fallbacks;
  - contributor: configured fallbacks until a future contributor image field exists.
- `none` is a per-family stop token: it keeps explicit own images, then suppresses that
  family's fallback chain and forces the existing placeholder/initials block.
- Do not add remote fetching, new image columns, or image generation.

## Stop Conditions

- Stop if the repository is dirty before implementation.
- Stop if the v4 enhancement plan/ledger/current-state docs disagree that V1a is next.
- Stop if implementation would require a new contributor image field, remote image
  ingestion, public visibility changes, V1b/V1c work, or cache implementation from P1.
