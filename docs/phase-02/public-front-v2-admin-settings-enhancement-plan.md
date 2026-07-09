# Public Front v2 Admin and Settings Enhancement Plan

Date: 09/07/2026

## Purpose

This plan expands the post-M6 Public Front v2 continuation queue with Yoni's admin, visual-settings, and settings-lifecycle requests before resuming the performance/cache sequence.

The central ledger remains authoritative for per-run selection:

`docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Current State

- Step 10R-M6 is complete and closed the M1-M5 plus IP1-IP3 arc.
- R1-R23 are landed.
- Step 10R-C1 is superseded.
- Step 10R-P1 was previously next, but the new requests change the queue before P1.
- The planning addendum is documentation-only; no app code, migrations, or settings rows are changed by this plan.

## Research Inputs

- Laravel Boost tools used: `application_info`, `database_schema`, `search_docs`.
- FilamentExamples MCP used: `search_examples`.
- FilamentExamples access level: search/snippet only.
- Research note: `docs/research/public-front-v2/19-admin-settings-enhancement-mcp-research.md`.

## Active Sequence Addendum

| # | Step | Depends on | Request IDs | One-line scope |
|---|---|---|---|---|
| 1 | Step 10R-UX1 | M6 | 1, 6, 7, 8 | Admin navigation order, relation-manager tab placement, record-action column placement, and modal width/full-width schema standards. |
| 2 | Step 10R-UX2 | UX1 | 9 | Add reusable effective-transcription edit action to episode lists in the Episodes resource and podcast episode relation manager. |
| 3 | Step 10R-V1 | M6, UX1 | 2, 3, 4, 5 | Default/no-image uploads, expanded safe icon picker, custom-color controls, and light/dark-safe podcast-image color sampling. |
| 4 | Step 10R-P1 | UX1, UX2, V1 | F1 | Cache validated public-front config after the new visual/settings shape is known. |
| 5 | Step 10R-S1 | P1, V1 | 10 | Plan and implement a versioned JSON settings import/export package with validation, dry-run, backup-before-import, and cache invalidation. |
| 6 | Step 10R-S2 | P1, S1 | 11 | Plan and implement settings backup versions, retention, compare/download, and restore flow. |
| 7 | Step 10R-P2 | S2 | F2, F7, F12, F15 | Listing fetch-window, lazy options/form definitions, and opt-in aggregate subselects. |
| 8 | Step 10R-P3 | P2 | F3 | Derived transcript segments and viewer render economy. |
| 9 | Step 10R-B4 | P3 | F11 | Converge legacy card options with card presentation services. |
| 10 | Step 10R-C2 | B4 | F13 | Normalize card layout consistency and semantic layout tokens. |
| 11 | Step 9F-A | M1-M6, IP1-IP3, P1-P3, B4, C2 | 9F | Rich homepage columns foundation. |
| 12 | Step 9F-B | 9F-A | 9F | Footer config and footer renderer. |
| 13 | Step 9F-C | 9F-B | 9F | Footer/rich section admin UX and integration polish. |
| 14 | Step 11 | all above and explicit Yoni approval | Step 11 | Seeders/demo/assets/cleanup. |
| 15 | Prompt 13 | explicit Yoni approval | Prompt 13 | Dashboard metrics. |

## Step 10R-UX1 Plan

Goal: normalize admin navigation and table/page UX before adding more settings and actions.

Scope:

- Add explicit navigation sort values in this order:
  - Podcasts: `ContentGroupResource`
  - Episodes: `ContentItemResource`
  - Transcriptions: `TranscriptionResource`
  - Transcribers: `AuthorResource`
  - Categories: `CategoryResource`
  - Tags: `ContentTagResource`
  - Form submissions: `PublicFormSubmissionResource`
  - Homepage sections: `HomepageSectionResource`
  - Settings: `PublicContentSettings`
- Keep existing translated navigation labels and group labels unless the implementation discovers mismatched wording.
- Audit all edit/view pages that expose relation managers and make relation managers display as tabs above the main content/form where a persisted record exists.
- Do not force relation managers onto create pages before a record exists; document any create-page non-applicability in the handoff.
- Add admin theme CSS for larger relation-manager tab labels instead of per-page inline styling.
- Move all record actions to `RecordActionsPosition::BeforeColumns`, which keeps bulk checkboxes first and places actions before data columns.
- Standardize action modal width for table actions, favoring `Width::SevenExtraLarge` or `Width::Screen` where forms are dense.
- Ensure action modal form sections are `columnSpanFull()` where the section is a major logical group.

Tests:

- Admin resource navigation order smoke test.
- Resource/relation-manager table assertions for record action placement where testable.
- Relation-manager tab behavior for content item/content group edit pages.
- Existing admin resource smoke tests.
- FilaCheck full gate.

Out of scope:

- Changing public navigation/menu behavior.
- Creating new admin resources or clusters.
- Implementing settings import/export/backups.

## Step 10R-UX2 Plan

Goal: add a single reusable edit action for the effective/featured/main transcription on episode list surfaces.

Scope:

- Add the action to:
  - `ContentItemsTable` on the Episodes resource page.
  - `ContentGroups\RelationManagers\ContentItemsRelationManager` on podcast episode lists.
- Resolve the transcription in the same priority order used by public rendering: featured/effective/main published transcription where available, with an explicit fallback policy documented before coding.
- Disable or hide the action when no editable transcription exists.
- Reuse the existing transcription form schema where safe, preserving multi-transcriber state and `transcriptions.author_id` compatibility.
- Use the wide modal and full-width section standards from UX1.
- Keep this as an admin edit modal, not a public-page feature.

Tests:

- Action appears on both requested episode-list surfaces.
- Action edits the effective/featured/main transcription, including transcriber relation state.
- Action is hidden/disabled when an item has no transcription.
- Public rendering and bounded query-count harness remain green.

Out of scope:

- Associate-existing transcription workflow.
- Studio/editor autosave.
- Public transcript action changes.

## Step 10R-V1 Plan

Goal: add visual fallback assets and broaden safe icon/color controls.

Settings shape draft:

```json
{
  "default_images": {
    "content_item": null,
    "content_group": null,
    "contributor": null,
    "global": null
  },
  "color_controls": {
    "allow_custom_hex": true
  }
}
```

Implementation notes:

- Add registry defaults, validator normalization, settings migration, `PublicFrontRenderContext` accessors, and admin fields.
- File uploads use the public disk, constrained directories, accepted image MIME types, max size, and image previews.
- Public image fallback order should remain specific before generic:
  - Episode cards/pages: item thumbnail or episode image, podcast cover, configured content-item fallback, configured global fallback.
  - Podcast cards/pages: podcast cover, configured content-group fallback, configured global fallback.
  - Contributor cards/pages: contributor image if present, configured contributor fallback, configured global fallback.
- Do not fetch remote image URLs during public rendering.
- Expand icon settings through an app-owned icon registry built from `Filament\Support\Icons\Heroicon::cases()`.
- Preserve compatibility aliases for current stored icon keys such as `calendar`, `document`, `podcast`, and `arrow_right`.
- Use Yoni's selected FilamentExamples reference for the icon picker:
  - **Icon Picker Select Field with Live Icon Display - Select With Custom HTML Values and Search Results**
  - `https://filamentexamples.com/project/filament-v4-filament-icon-select-field-with-preview`
  - `https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/forms/select-with-custom-html-values-and-search-results`
- Adapt the example's Select pattern: `allowHtml()`, `searchable()`, HTML icon labels/results, live icon preview, and option/search-result generation from `Heroicon::cases()`.
- Keep the copied behavior as a PodText-owned helper, not ad hoc duplicated fields, so all icon settings share the same preview/search UI.
- Render icons only through app-owned resolver output; never render raw component names or arbitrary strings from JSON.
- Add a safe `custom` color mode where requested color settings expose a `ColorPicker`.
- Store only strict `#rrggbb` values for custom colors; never store classes, raw CSS snippets, HTML, Blade, SVG, PHP, or SQL.
- Extend `PublicItemPagePodcastPalette` to expose dark samples for light theme and light samples for dark theme. Fall back to semantic colors when GD is unavailable, the image is remote, or the local cover is unreadable.

Tests:

- Default image settings normalize and migrate.
- Uploaded fallback image appears on item/group/contributor card/page surfaces without seeded data assumptions.
- Existing image precedence is preserved.
- Expanded icon options normalize safely; invalid icon values fall back.
- Existing icon aliases still render.
- Custom hex color accepts valid hex and rejects/normalizes invalid input.
- Podcast-image color samples are contrast-normalized into dark/light variants.
- Public pages render without extra public-request image fetching.

Out of scope:

- Image generation, remote image fetching, or queued image analysis.
- Replacing existing `cover_path`/thumbnail schema.

## Step 10R-S1 Plan

Goal: design and implement settings import/export as a versioned JSON package.

Scope:

- Export a JSON package with:
  - schema version;
  - generated timestamp;
  - app/package metadata;
  - public settings groups included;
  - normalized `public_content` payload;
  - checksum/hash for integrity.
- Import flow:
  - upload JSON file in admin settings;
  - validate schema version and payload shape;
  - dry-run preview/diff before applying;
  - create a settings backup before import;
  - apply inside a database transaction;
  - clear Spatie/settings cache and app-level public-front config cache.
- Initial implementation should focus on Spatie public settings. Homepage sections and public form definitions are normal database records and should only be included if the S1 plan explicitly defines them as an optional package section.

Tests:

- Export produces valid JSON with normalized settings.
- Import dry-run reports changes without saving.
- Import applies valid JSON and invalidates cache.
- Invalid/mismatched package is rejected.
- Backup-before-import is created when S2 storage exists, or the handoff records the interim behavior.

Out of scope:

- Demo content imports.
- CSV importer/exporter changes.
- Step 11 seed data.

## Step 10R-S2 Plan

Goal: add admin-only settings backup versions and restore behavior.

Schema draft:

```text
settings_backup_versions
- id
- scope
- label
- payload_json
- checksum
- created_by_user_id nullable
- source: manual|before_import|before_restore|system
- created_at
- updated_at
```

Implementation notes:

- Use MySQL/SQLite-compatible columns; store JSON as text or JSON according to the existing project migration convention.
- Backups are admin-only and must not expose `User` publicly.
- Provide manual backup, download, compare, and restore actions from the settings area.
- Restore should create a pre-restore backup, run inside a transaction, and invalidate all relevant settings/public config caches.
- Add retention settings only if they remain finite and safe, for example keep last 25 manual/system versions.

Tests:

- Manual backup stores normalized settings payload.
- Restore applies a prior version and invalidates cache.
- Before-import and before-restore backups are created.
- Unauthorized/public access is absent.
- MySQL/SQLite-compatible schema and rollback.

Out of scope:

- Full audit log UI.
- Versioning arbitrary editorial content.
- Public exposure of backup metadata.

## Impact on Existing Queue

- P1 should wait until V1 lands so the cached validated public-front config includes the new default-image/icon/color settings from its first implementation.
- S1/S2 should run after P1 so import/restore can use the single public-front config cache invalidation path.
- P2/P3 remain performance work and still resolve their existing F findings after the settings lifecycle mini-steps.
- B4, C2, 9F, Step 11, and Prompt 13 keep their prior guardrails.

## Stop Conditions

- Stop before app-code changes if the ledger and current state disagree on the first pending mini-step.
- Stop if a requested custom-color implementation would require storing raw classes/CSS snippets instead of strict sanitized hex values.
- Stop if image palette sampling requires remote fetches on public requests.
- Stop if a create page cannot support relation managers because no persisted record exists; document non-applicability instead of forcing a brittle workaround.
- Stop if settings import/export scope expands into demo/content seeding; that belongs to Step 11 after explicit approval.
