# Public Front v2 Step 10R-IP1 Implementation Plan

## 1. Selected Mini-Step And Dependency Verification

Selected mini-step: Step 10R-IP1 - Episode page settings foundation, dates, and info-badge tokens.

Dependencies verified:

- M1 `800218a` exists.
- M2 `e813513` exists.
- M3 `825004c` exists.
- M4 `af9f399` exists.
- Decision override `aaddd95` exists.
- HF1 `2a5ff96` exists.
- M5 is complete locally as `aa7568c feat: add card template grouped parts labels and icons`.
- The ledger marks IP1 as the first pending step after M5. IP2, IP3, M6, P1-P3, B4, C2, 9F, Step 11, and Prompt 13 remain pending or paused as required.

## 2. Current Local Repo Evidence

- Branch: `main...origin/main [ahead 1]`.
- Working tree before IP1 code changes: clean, then docs-only ledger state update marking IP1 in progress.
- `php artisan migrate:status` shows all completed Step 10R migrations as run, including `author_transcription`, `drop_author_content_item_table`, `add_public_transcription_policy_setting`, and `add_public_transcription_display_settings`.
- Direct SQLite schema check confirms `author_content_item` is absent.
- Route checks confirm public `items`, `podcasts`, `contributors`, `search`, and `about` routes plus admin routes are registered.
- No M5/IP/P/B/C/9F/11/13 app-code work was found started outside the ledger.

## 3. Files Inspected

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/public-front-v2-transcription-display-decisions.md`
- `docs/phase-02/public-front-v2-performance-efficiency-audit.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/ai-development-lessons.md`
- M1-M5, HF1, and prior A/B handoffs named by the runner
- `docs/research/public-front-v2/17-step10r-m4-public-rendering-aggregates-mcp-research.md`
- `docs/research/public-front-v2/18-step10r-m5-mcp-research.md`
- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `database/settings/*public*settings*.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicFrontRenderContextTest.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `lang/en/public.php`
- `lang/he/public.php`

## 4. Laravel Boost Findings

Boost tools used: `application_info`, `database_schema`, `database_query`, and `search_docs`.

Relevant findings:

- Installed versions are Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2.
- Data mapping from schema is explicit:
  - "Published on site" = `content_items.published_at`.
  - "Originally published" = `content_items.original_published_at`.
  - "Transcription date" = selected/effective `transcriptions.published_at`.
- Settings are stored in the Spatie `settings` table by group/name.
- Filament nested state paths and fieldsets are appropriate for editing `item_page.dates.*`.
- Dates should be formatted in presenters/rendering as `d/m/Y` in `Asia/Jerusalem`.
- Boost docs search did not return Spatie settings migration docs; existing local settings migrations are the authoritative API reference for this repo.

## 5. FilamentExamples MCP Findings And Access Level

Access level: search/snippet only. No source/read/details tool was exposed.

Query batches:

- `SettingsPage tabs`, `settings repeater`, `nested settings fields`, `date display settings`
- `badge icon label`, `metadata row renderer`, `public detail page`, `safe icon map settings`
- Refined: `public card layout presenter`, `custom public page view data`, `date badge settings`, `SettingsPage date options`

Patterns to copy:

- Group nested settings into clear sections/fieldsets.
- Use finite Select options for mode/icon/size/color values.
- Prepare fixed class maps in PHP support code before Blade rendering.

Patterns to avoid:

- Raw HTML option labels.
- Public Filament Tables for the public front.
- Storing class names, component names, or raw SVG/HTML in JSON.

## 6. Requirement IDs Owned This Run

IP1 lands:

- R1 data/attributes part: add card-side `site_published_date` and preserve original/effective date attributes.
- R2: date display mode setting `site|original|both`.
- R3: long/short/hidden label mode and optional label overrides for dates.
- R4: per-date icon and icon-position settings using finite M5 icon tokens.
- R5: transcription-date label/icon settings and enabled flag.
- R6: dedicated Episode page settings tab in the Public Content Settings page.
- R13 token part: finite info-badge size/color vocabulary and fixed PHP class maps.
- R23: extensible `item_page` JSON root for future IP2/IP3 keys.

Remaining for later IP steps:

- R1 page-rendering part, R11-R18 in IP2.
- R7-R10 and R19-R22 in IP3.

## 7. Schema And Settings Reality Check

- No database table schema changes are needed.
- A settings migration is required for the new `public_content.item_page` settings row.
- Existing scalar `item_page_layout` remains as a compatibility setting and will move into the Episode page admin tab. It is not folded into `item_page` in IP1 because existing page code still reads the scalar and IP2 owns the page layout rebuild.
- Existing `content_items.external_published_at` is not the IP1 original-date source. The runner's data map names `content_items.original_published_at`.

## 8. Data Mapping Check

- Site publish date: `ContentItem::$published_at`, formatted `d/m/Y` in `Asia/Jerusalem`.
- Original publish date: `ContentItem::$original_published_at`, formatted `d/m/Y` in `Asia/Jerusalem`.
- Transcription date: selected/effective `Transcription::$published_at`, already policy-aware through the M3/M4 selector and formatted `d/m/Y` in `Asia/Jerusalem`.

## 9. Current Rendering, Query, And Settings Gaps

- Cards can render `content_item.original_published_at` but cannot render `content_item.site_published_date`.
- The card presenter already computes `effective_date` and `original_date`, but not a separate site-published date.
- The public-front settings registry has no `item_page` root for date controls or future page controls.
- The render context has no `itemPage()` accessor.
- The settings page has no Episode page tab.
- No info-badge size/color token helper exists yet for IP2 to consume.
- IP1 should add no per-render queries. Dates come from already-loaded model attributes.

## 10. Exact JSON Shape, Defaults, And Validator Changes

New `item_page` default:

```json
{
  "dates": {
    "display": "both",
    "site_published": {
      "label_mode": "long",
      "label_override": null,
      "icon": "calendar",
      "icon_position": "inline_before"
    },
    "original_published": {
      "label_mode": "short",
      "label_override": null,
      "icon": "calendar",
      "icon_position": "inline_before"
    },
    "transcription_date": {
      "enabled": true,
      "label_mode": "short",
      "label_override": null,
      "icon": "document",
      "icon_position": "inline_before"
    }
  },
  "badges": {
    "info": {
      "size": "sm",
      "color": "gray"
    }
  }
}
```

Validator changes:

- Accept only date display values `site`, `original`, `both`.
- Accept only label modes `long`, `short`, `hidden`.
- Accept only M5 finite icon keys and icon positions.
- Normalize `before`/`after` icon positions through the existing M5 compatibility path.
- Trim and length-bound custom label overrides as plain text.
- Accept only badge sizes `xs`, `sm`, `md`.
- Accept only badge colors `gray`, `primary`, `info`, `success`, `warning`, `danger`.
- Reject unknown nested keys and unsafe HTML/CSS/Blade/PHP-looking strings through existing invalid-config reporting.

## 11. Exact Files To Change

Application/settings:

- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php` (new)
- `database/settings/2026_07_09_000000_add_public_item_page_settings.php` (new)

Cards:

- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`

Admin/translations:

- `app/Filament/Pages/PublicContentSettings.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `lang/en/public.php`
- `lang/he/public.php`

Tests/docs:

- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicFrontRenderContextTest.php`
- `docs/research/public-front-v2/18-step10r-ip1-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-ip1-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-ip1-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## 12. Tests To Add Or Update

Focused tests:

- Settings defaults include normalized `item_page`.
- Settings migration/backfill creates `public_content.item_page`.
- Invalid date display/icon/position/label/badge tokens normalize safely.
- Date formatting for card template attributes is day-first in `Asia/Jerusalem`.
- A custom content-item card template renders both `site_published_date` and original date with M5 label/icon output.
- Card-template registry exposes `content_item.site_published_date` and original-date attributes safely.
- Public render context exposes `itemPage()`.
- Public Content Settings page saves the new Episode page settings fields.
- Translation keys exist in `lang/en` and `lang/he`.
- `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` remains green.

## 13. Translation Keys To Add Or Update

Admin `lang/en/admin.php` and `lang/he/admin.php`:

- Episode page tab label.
- Date settings section and fieldset labels.
- Date display, label mode, label override, icon, icon position field labels/helpers.
- Transcription date enabled field label/helper.
- Info-badge token field labels/helpers.
- Date display, label mode, badge size, and badge color option labels.
- Card-template attribute labels for `site_published_date` and `original_published_date`.

Public `lang/en/public.php` and `lang/he/public.php`:

- `public.dates.site_published_long`
- `public.dates.site_published_short`
- `public.dates.original_published_long`
- `public.dates.original_published_short`
- `public.dates.transcription_date_long`
- `public.dates.transcription_date_short`

## 14. Performance Implications

- No new public queries are expected.
- Card date attributes use existing `ContentItem` and selected transcription values already available in M4 presenters.
- Settings validation runs as it already does today. F1 validated config caching remains scheduled for P1.
- The bounded public rendering query-count harness must stay green.

## 15. Out Of Scope

- IP2 episode page header/info-line rebuild and actual page placement of the new date fields.
- IP3 transcript action menu, share move, fullscreen, font-size controls, and media-column toggle.
- P1 caching work.
- B4 card options convergence.
- C2 semantic grid token consolidation.
- Step 11 seeders/demo data.
- Prompt 13 dashboard metrics.
- Any new models, public Filament Tables, raw class storage, or player-sync/studio behavior.

## 16. Stop Conditions

Stop before code changes if:

- Required prior commits disappear from local history.
- The ledger no longer has IP1 as first pending/in-progress.
- The working tree gains unexpected app-code dirt.
- A completed-step migration is missing.
- `author_content_item` reappears.
- Step 11 or Prompt 13 becomes selected without explicit Yoni approval.
- A major schema contradiction is found for the IP1 date mapping.
