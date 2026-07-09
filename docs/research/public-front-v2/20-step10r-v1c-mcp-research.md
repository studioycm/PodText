# Step 10R-V1c MCP Research

Date: 09/07/2026

## Scope

Step 10R-V1c adds strict custom hex color settings and a persistent, theme-safe podcast
cover palette cache for public item-page podcast identity colors.

## Repository Evidence

- The runner preflight found a clean tree on `main`, with V1b committed as
  `ba43145 feat: expand icon settings with searchable heroicon picker`.
- The ledger, current state, and sequence docs all list Step 10R-V1c as the first
  pending mini-step. Step 11 and Prompt 13 remain approval-gated and not started.
- Existing finite color settings are under `item_page`: podcast identity color, info
  badge color, and each item-page info field color.
- `PublicItemPagePodcastPalette` currently samples three raw hex colors from a local
  public-disk cover on each call and has no persistent cache or light/dark contrast
  variants.
- Public rendering already emits podcast identity style through a controlled page-class
  method and Blade only prints the validated style string.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed the installed stack: PHP 8.4, Laravel 13.18.0, Filament 5.6.7,
  Livewire 4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2, SQLite locally.
- Boost schema summary confirmed the Spatie `settings` table, the `cache` table, and
  `content_groups.cover_path`; no normal database table migration is required for V1c.
- Filament 5 `ColorPicker` defaults to HEX format and supports `hex()`.
- Filament validation supports `hexColor()`, and the docs show regex validation for
  strict 3- or 6-digit hex values.
- Filament conditional fields should use live parent controls and `visible()` closures;
  PodText can mirror the existing default-image upload reveal pattern.
- Laravel cache docs confirmed `Cache::rememberForever()` and `Cache::forever()` for
  persistent entries. Cache tags are explicitly unsupported on the database store, so
  V1c must use a single versioned key without tags.
- Laravel storage docs confirmed public-disk files live under `storage/app/public` and
  can be resolved through the storage disk path; V1c must reject remote URLs and never
  fetch them.

## FilamentExamples MCP Findings

Access level: `search_examples` search/snippet access only. No separate fetch/detail
tool was exposed.

Query batches:

- `color picker settings page`, `custom color field`, `settings page color picker`
- `conditional ColorPicker Select live`, `color picker custom mode select`,
  `settings custom color picker visible`
- `public blade style custom color`, `card color settings`, `theme color settings`
- Refined: `ColorPicker::make hex_color regex`, `Filament Forms ColorPicker make color`,
  `ColorPicker field settings`

Useful examples:

- **Dynamic Custom Fields With Repeater**:
  `v4/forms/form-custom-fields/app/Filament/Resources/Customers/Schemas/CustomerForm.php`
  showed repeated form fields with `Select` controls inside repeaters. PodText adapts
  this only as a sibling-field organization reference.
- **Dependent Sale/Rent Price Filters Driving Columns**:
  `v4/tables/real-estate-table-with-complex-sale-rent-price-filter/.../HomesTable.php`
  showed `Select` / field visibility driven by current form state. PodText adapts the
  live select + conditional custom field pattern, not the table filter logic.
- **Custom Material Design Filament Theme**:
  `v4/full-projects/material-theme/app/Providers/Filament/AdminPanelProvider.php` showed
  Filament accepting hex theme colors in panel configuration. PodText does not use this
  directly because public settings must store strict nested JSON values and render
  dynamic colors through CSS variables, not Filament panel colors.

Patterns to avoid:

- Do not store Tailwind classes, raw CSS snippets, SVG, component names, or arbitrary
  HTML in JSON settings.
- Do not preload or create broad palette/theme configuration beyond the item-page color
  surfaces in V1c.
- Do not use cache tags.
- Do not fetch or decode remote images.

## Implementation Implications

- Add an app-owned color utility for strict hex normalization, light/dark contrast
  variants, and CSS custom-property style strings.
- Extend item-page settings with `custom_color` beside each finite color token.
- Include `custom` as a finite color token only where the admin color picker exists.
- Normalize `#abc` to `#aabbcc`; invalid custom values produce invalid-config entries
  and fall back to semantic defaults rather than storing unsafe values.
- Cache podcast palette arrays under a versioned key based on public cover path and
  file mtime. The cached value must be an array, not a custom object.
- Emit custom and sampled colors only through inline CSS custom properties from
  validated hex values, with Tailwind classes referencing those variables.
