# Episode Workspace EP1 Implementation Plan

Date: 2026-07-12

This plan follows EP1-R research in `docs/research/episode-workspace/00-ep1-research.md`. It is a planning document only. No app code, migrations, dependency changes, or tests are part of EP1-R.

## D-EP Register

- D-EP1 single-transcription lens: the workspace targets the featured transcription. If no featured transcription exists, it targets the same effective resolution the public side uses: latest published, else newest draft. If zero transcriptions exist, the transcript section creates one. A transcription created here is pinned as featured. Saving an unpinned target adopts it as featured. A replace-transcription action with confirmation is the only way the target changes. Other transcriptions produce at most a one-line hideable hint.
- D-EP2 architecture race: native relationship-bound section wins first, create wizard second, save-draft-first last. Research result: relationship-bound section survives through a dedicated `HasOne` workspace relation, not the current `featuredTranscription` `BelongsTo`. Implements R1-R4.
- D-EP3 title prefix: add `content_items.title_prefix`; auto-fill from selected podcast name when blank; editable; combined title is live preview only; public combined display uses `title_prefix ?? group title`; never store the combined title in `title`. Implements R9 and R12.
- D-EP4 no generic JSON extras column: queryable/exported fields get columns. MP3 URL uses the existing `direct_media_url`. Metadata registries remain for non-queryable metadata only. Implements R10 and R11.
- D-EP5 defaults: transcription presentation modes are `collapsible`, `modal`, and `slideover`, default `collapsible`; `transcription_mode` setting is `single` or `multi`, default `single`; language code is hidden behind a setting, default `he`; slug keeps UX3 smart behavior plus clear action and URL preview; embed input supports both trusted `embed_html` storage and an extract-src helper for the `embed_url` path; categories/tags are collapsed; advanced fields are collapsed; public visibility checklist is shown; inline audio player renders for a valid MP3 URL; Spotify-link fetch action fills form fields. Implements R5-R13.
- D-EMB1 trusted embed HTML: add `content_items.embed_html` nullable text for admin-pasted embed code stored verbatim with no sanitization, rendered only through the owned public media-embed component raw mode, with precedence over `embed_url`. The workspace offers both keep-as-HTML and extract-src-to-URL affordances. Implements R10.

## Guardrails

- EP1 is an admin workspace feature. Do not start Prompt 13, SF1 bulk tooling, IE-1, IMG implementation, WB2, or public-front motion/slider steps.
- Keep standard `ContentItemResource` create/edit pages available.
- The workspace becomes the default episode edit path in episode list tables and relation managers.
- No Composer changes.
- No generic JSON extras column.
- Raw embed HTML is allowed only in `content_items.embed_html` under D-EMB1. It must not render outside the owned public media-embed component.
- No rich HTML transcript storage.
- Use installed package versions and Filament 5 APIs only.

## Implementation Sequence

### 1. Schema And Settings

Migrations:

- Add nullable `content_items.title_prefix` string after `title`.
- Add nullable `content_items.embed_html` text after `embed_url`.
- Add an `AdminUxSettings` settings migration in `database/settings` for:
  - `admin_ux.transcription_presentation_mode = collapsible`
  - `admin_ux.transcription_mode = single`
  - `admin_ux.show_episode_workspace_hint_line = true`
  - `admin_ux.show_episode_workspace_language_code = false`
  - `admin_ux.tb1_picker_container = modal`
  - `admin_ux.media_naming_strategy = slug`

Model/settings updates:

- Add `title_prefix` to `ContentItem::$fillable`.
- Add `embed_html` to `ContentItem::$fillable`.
- Add `App\Settings\AdminUxSettings` and register it in `config/settings.php` alongside auto-discovery. Implements R7.
- Add a Filament `SettingsPage` for admin UX settings, separate from public content settings. Implements R7.
- Do not add `users.admin_preferences` in EP1. Track that as a follow-up. Implements R8.

### 2. Workspace Transcription Boundary

Add a dedicated `ContentItem::workspaceTranscription(): HasOne` relation and helpers:

- If `featured_transcription_id` is present, the relation targets that transcription.
- If no featured transcription exists, order child transcriptions by published status, latest `published_at`, then latest `id`.
- If no child exists, Filament creates one through the `HasOne`.
- Add a helper to resolve/adopt the saved workspace transcription and set `featured_transcription_id`.

Page hooks:

- `CreateEpisodeWorkspace::afterCreate()` adopts the saved workspace transcription as featured.
- `EditEpisodeWorkspace::afterSave()` adopts the saved workspace transcription as featured.
- The replace action is the only UI path that changes the target intentionally.

Implements R1 and R2.

### 3. Workspace Form

Create a shared workspace schema, for example `EpisodeWorkspaceForm`, consumed by create/edit workspace pages.

Sections:

- Podcast and identity: content group select with `RelationshipOptionForms`, `title_prefix`, `title`, combined preview, slug with clear action and URL preview.
- Media and source: `media_url`, trusted `embed_html`, extract-src-to-`embed_url` helper, `embed_url`, `embed_provider`, `external_thumbnail_url`, `direct_media_url`, audio preview, Spotify URL/ID field, fetch-into-form action.
- Taxonomy: categories and Spatie content tags, collapsed by default.
- Transcript: relationship-bound workspace transcription section with title, transcribers, status, published date, transcript Markdown, and language code only when setting enables it.
- Advanced: durations, metadata fields, and featured selector fallback, collapsed.
- Visibility checklist: group published, episode published, workspace/effective transcription published, and public-visible summary.
- Sticky/footer actions: save draft, publish, save, and save-and-add-another.

Reuse:

- `ContentItemMediaRules` for media fields.
- `ContentItemMediaRules` passes `embed_html` through as trusted nullable text; it must not sanitize or rewrite the value.
- `ApprovedEmbedUrl`/HTTPS validators for embed and media URLs.
- `TranscriptionForm` components where the field behavior already matches.
- `SlugInput` plus EP1 clear action and URL preview.

Implements R1, R9, R10, and R11.

### 4. Pages, Routing, Navigation, And Table Actions

Resource pages:

- Add `CreateEpisodeWorkspace extends CreateRecord`.
- Add `EditEpisodeWorkspace extends EditRecord`.
- Register them in `ContentItemResource::getPages()` while keeping classic `create` and `edit`.
- Put fixed workspace create route before wildcard record routes.

Navigation and actions:

- Add a main-navigation item pointing to the workspace create route.
- Add a workspace create header action on `ListContentItems`.
- In `ContentItemsTable`, set `recordUrl()` to `ContentItemResource::getUrl('workspace', ['record' => $record])`.
- Make the first/default row action open the workspace.
- Keep classic edit as a secondary action with translated label.
- Mirror the same default workspace URL/action behavior in `ContentItemsRelationManager`; retain classic modal edit/open-resource as secondary.
- Keep `EditEffectiveTranscriptionAction` available as a quick action only if it remains useful after the workspace lands.

Implements R5 and R6.

### 5. Replace Transcription Action

Add a guarded replace action on the workspace:

- Visible only when the episode already has other transcriptions.
- Modal or slideover with confirmation.
- Lets the admin select an existing same-item transcription.
- Sets `featured_transcription_id` to the selected transcription.
- Reloads workspace state after save.
- Does not create duplicate transcriptions.

Implements D-EP1 and R2.

### 6. Spotify And Embed Helpers

Add app-owned helpers:

- `EpisodeEmbedInputNormalizer`: explicit helper action that accepts an iframe snippet, extracts iframe `src`, and fills `embed_url` for the simple allowlisted URL path.
- `EpisodeSpotifyLookup`: wraps `SpotifyConnector`, normalizes URL/URI/ID input, selects an enabled Spotify `ImportConnection`, and returns form-fill data.

Embed behavior:

- The workspace has two visible affordances: keep pasted code as trusted `embed_html`, or extract `src` into `embed_url`.
- Helper text states that `embed_html` takes precedence over `embed_url` on the public item page.
- Stored `embed_html` remains verbatim. No sanitizer, Markdown renderer, URL validator, or metadata normalizer should touch it.
- `embed_url` continues through `ApprovedEmbedUrl` and the existing HTTPS/provider allowlist path.
- `resources/views/components/public/media-embed.blade.php` gains a raw mode and renders `embed_html` first, else falls back to the existing `embed_url` flow.
- Raw mode must be callable only from the item page/media component path, not from public cards, admin tables, Markdown rendering, or generic presenters.

Fill targets:

- `title` only when the title is blank or the fetch action explicitly confirms overwrite.
- `title_prefix` from show name only when blank.
- `media_url` and/or source URL according to available provider data.
- `embed_url`, `embed_provider`, `external_id`, `external_thumbnail_url`, `duration_seconds`/`media_duration_seconds`, `original_published_at`, and `media_metadata`.
- Keep `direct_media_url` for MP3 URL only when a direct audio URL exists and validates.
- Do not let Spotify fetch overwrite trusted `embed_html` unless a future prompt explicitly adds that behavior.

Implements R10 and R11.

### 7. Public Title Prefix Touch

Add a central display-title helper and update:

- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `resources/views/components/public/content-item-row.blade.php` if still used by any active route

Behavior:

- Combined public card/page display uses `title_prefix ?? contentGroup->title`.
- Raw `content_items.title` remains the episode title.
- Public podcast identity can still render separately according to current item-page settings.

Tests:

- Card combined title uses prefix.
- Card combined title falls back to group title.
- Item page title/header matches the chosen combined display behavior.
- Existing card template `content_item.title` output remains controlled through the presenter.

Implements R9 and R12.

### 8. Transcript Paste Cleanup Boundary

EP1 should add only the boundary if it can be done without hard-coding unknown transcript conventions:

- Admin JS attaches to the workspace MarkdownEditor after EasyMDE is ready.
- It intercepts rich paste, converts only safe known structures to Markdown, and otherwise falls back to native paste.
- It leaves bracket convention conversion deferred to the WB format probe.

If this is not a small bounded addition during EP1, defer it to the format-probe follow-up rather than guessing conversion rules.

Implements R13.

### 9. Media Spec And Guideline Amendments

EP1-R updates durable media docs with D-EMB1; EP1 implementation must preserve these rules:

- `docs/phase-02/media-embed-spec.md`: add `embed_html`, raw component mode, precedence over `embed_url`, trusted-admin verbatim storage, and tests.
- `.ai/guidelines/media-embeds.md`: replace the old absolute URL-only/raw-iframe ban with the narrow `embed_html` exception and owned-component-only rendering rule.

Implements R10.

## Test Plan

Add/update Pest tests for:

- Atomic create-with-transcription through the workspace creates a `ContentItem`, creates one `Transcription`, sets `content_item_id`, and pins it as featured.
- Edit workspace saves episode fields and transcription fields in one transaction.
- Editing an unpinned target adopts it as featured.
- Replace action changes the target only after confirmation and only to a same-item transcription.
- Zero-transcription edit shows a createable transcript section.
- Multiple transcriptions show only the hideable one-line hint when the setting is enabled.
- `AdminUxSettings` defaults and settings page save behavior.
- Collapsible, modal, and slideover presentation modes render the transcript UI.
- Language code hidden by default and visible when setting enables it, defaulting to `he`.
- Visibility checklist truthfully reflects group/item/transcription publication states.
- `embed_html` renders verbatim on the public item page through the media component.
- `embed_html` takes precedence over `embed_url` when both are present.
- `embed_html` renders nowhere else: not public cards, not admin tables, not Markdown output, not generic presenters.
- Extract-src helper fills `embed_url` from pasted iframe code without changing `embed_html`.
- Unknown/HTTP/unapproved embed hosts are rejected by existing validation.
- `title_prefix` preview is live and not stored in `title`.
- Public card/page combined display uses `title_prefix ?? group title`.
- Slug clear action and full URL preview.
- Spotify fetch fills form fields through a faked `EpisodeSpotifyLookup`.
- Inline audio preview appears only for a valid HTTPS `direct_media_url`.
- RTL markers/translation keys on the workspace surface where practical.
- No regression in existing classic `ContentItemResource` create/edit pages.
- `vendor/bin/filacheck` passes for Filament code after implementation.

## Explicit Out Of Scope

- SF1 bulk Spotify tool.
- WB2-WB7 implementation.
- IMG-1/IMG-2/IMG-3 image work.
- IE-1 import/export relation semantics.
- Composer/package changes.
- Per-user `users.admin_preferences`.
- Public redesign beyond the required `title_prefix` display touch.
- Prompt 13 dashboard metrics.
- Raw iframe/embed storage outside `content_items.embed_html`.
- Rendering `embed_html` outside the owned media-embed component.
- Rich HTML transcript storage.
- Hard-coded `[]` transcript convention conversion before the WB format probe.

## Research Decision Mapping

- R1-R4: workspace relationship architecture and fallback rejection.
- R5-R6: resource page routing and default table actions.
- R7-R8: admin settings and per-user preference deferral.
- R9-R12: title prefix, trusted embed HTML storage/rendering, Spotify service, and public title display.
- R13: transcript paste cleanup boundary and deferral.

## EP1 Completion Gate

The implementation prompt should run the normal implementation quality gate unless explicitly superseded:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

EP1-R itself is docs-only and validates only with:

```bash
git diff --check
git status --short
```
