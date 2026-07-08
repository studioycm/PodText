# Step 10R-M5 MCP Research - Card Labels, Icons, And Grouped Parts

## Scope

Selected mini-step: Step 10R-M5 - Card-template rows/groups, icons, labels, and nested part rendering.

This research covers safe card-template label/icon rendering, `part_group` nested children, Filament settings form patterns, owned icon maps, and rendered-output Pest coverage.

## Laravel Boost Access

Access level: installed-version application/package guidance and database inspection.

Tools used:

- `application_info`
- `database_schema`
- `search_docs`

Findings:

- Installed stack: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind 4.3.2, local SQLite.
- Current schema includes `settings`, `content_items`, `content_groups`, `transcriptions`, `authors`, and `author_transcription`; no M5 database table migration is required.
- Filament Builder and Repeater store JSON arrays and are appropriate for existing `card_templates.parts` editing.
- Filament `Heroicon` enum values can be passed to Blade components; icons should come from an app-owned finite key map, not from JSON-provided class names.
- Blade output remains escaped by default. M5 should keep label and custom text rendering through `{{ }}` and owned view branches.
- Pest rendered-output assertions can verify escaped labels/icons/groups without relying on local seed data.

## Boost Search Docs Queries

- `Filament settings page form tabs sections repeater builder nested array state`
- `Filament Builder component nested blocks repeater schema options hidden visible`
- `Filament Select enum options helper text reactive state path`
- `Blade component escaped output dynamic attributes class map`
- `Pest rendered output assert see html escaped`
- `Tailwind CSS v4 flex gap grid responsive classes dark mode`
- `Filament support Heroicon enum icon values forms select options`
- `Filament icon component blade heroicon enum`
- `Filament forms builder block nested schema repeater nested arrays max depth`

Usable guidance:

- Continue using the existing Filament Builder block shape for card parts and normalize it before persistence/rendering.
- Use finite option lists for all layout/position/icon controls.
- Use the `Heroicon` enum from PHP code for icons.
- Keep dynamic class output limited to fixed PHP/Blade class maps.

## FilamentExamples MCP Access

Access level: search/snippet access only. No separate source/read/fetch/details tool was exposed.

First-pass query batches:

- `card template part group`
- `nested builder settings`
- `badge icon label`
- `metadata row renderer`
- `safe icon map settings`
- `SettingsPage tabs`
- `settings repeater`
- `builder nested blocks`

Refined query batches:

- `public card layout presenter`
- `custom view card grid`
- `repeater itemLabel collapsed settings`
- `nested settings state path`

Relevant examples and patterns:

- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`
  - Pattern to copy: prepare card-like layout as structured components/data before rendering.
  - Pattern to avoid: table-specific public UI; PodText public cards remain custom Blade, not public Filament Tables.
  - PodText adaptation: keep presenters preparing part arrays and render through owned Blade components.
- `v4/forms/select-with-custom-html-values-and-search-results/app/Filament/Resources/Categories/Schemas/CategoryForm.php`
  - Pattern to copy: finite icon selection from `Heroicon` enum.
  - Pattern to avoid: saving raw HTML option labels into PodText JSON.
  - PodText adaptation: expose simple translated icon labels in admin; render actual icons from a PHP map.
- `v4/forms/repeater-five-advanced-use-cases/.../ProductForm.php` and `ProjectForm.php`
  - Pattern to copy: nested repeaters with `itemLabel()`, `collapsed()`, `reorderable()`, and live dependent fields.
  - Pattern to avoid: unbounded nested child editing.
  - PodText adaptation: allow one nested child level for `part_group`, excluding `part_group` from child blocks.
- `v4/forms/wizard-invoice-form/app/Filament/Pages/ManageSettings.php`
  - Pattern to copy: settings page form state under an array state path.
  - Pattern to avoid: model-backed settings tables for this feature.
  - PodText adaptation: continue storing card templates in existing Spatie Settings JSON.

## Code Inspection Summary

Inspected:

- `app/Support/PublicFront/Cards/PublicFrontCardPart.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplate.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/contributor-card.blade.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

Key findings:

- `label`, `label_position`, `icon`, and `icon_position` already exist in storage/presenter arrays but are not rendered.
- Existing label/icon position tokens are old `before` / `after`; M5 needs `inline_before` / `inline_after` plus label `above` / `below`.
- No `label_alignment`, `part_group`, `columns`, `gap`, `alignment`, or `children` support exists.
- Existing views repeat per-part `data-card-part` markup in three card families; a reusable part-shell component will reduce duplication for labels/icons.
- Existing tests already provide fixture-owned card-family coverage and are the right place to extend M5 assertions.

## Resulting Implementation Direction

- Add finite registry tokens for:
  - label positions: `hidden`, `inline_before`, `inline_after`, `above`, `below`;
  - label alignment: `start`, `center`, `end`, `between`;
  - icon positions: `hidden`, `inline_before`, `inline_after`;
  - group layouts: `inline`, `stacked`, `grid`;
  - group columns: `1`, `2`, `3`, `4`, `auto`;
  - group gaps: `compact`, `comfortable`, `spacious`;
  - group alignment: `start`, `center`, `end`, `between`.
- Add an app-owned `PublicFrontCardIconResolver` mapping finite icon keys to `Heroicon` enum cases.
- Add a shared Blade part shell for escaped label text and safe icon rendering.
- Add `part_group` support in validator, DTO, renderer, presenters, and card views with one nested level only.
- Add nested admin Builder support for `part_group.children` while excluding `part_group` from child blocks.
- Preserve old `before` / `after` tokens by normalizing them to `inline_before` / `inline_after`.
- Avoid a settings migration because M5 adds optional fields inside existing `card_templates` entries, not a new settings key or default group.
