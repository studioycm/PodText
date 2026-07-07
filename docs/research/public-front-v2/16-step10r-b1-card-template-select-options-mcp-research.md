# Public Front v2 Step 10R-B1 MCP Research

## Mini-Step

Step 10R-B1 - Card template select/options and settings UX fixes.

## Laravel Boost

- `application_info`: confirmed installed stack is Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind 4.3.2, and SQLite.
- `database_schema`: confirmed the `settings` table remains the only persistence layer needed for `PublicContentSettings`; no schema change is required.
- `search_docs` queries:
  - `Filament SettingsPage form schema select options reactive after state updated`
  - `Filament Select options closure Get live reactive settings page`
  - `Filament Repeater Builder options closure dependent select`
  - `Livewire test Filament SettingsPage form set data`
  - `Pest assert settings saved Livewire Filament`
- Boost guidance used:
  - Filament select options can be provided by closures and can use `Get` to derive options from current form state.
  - `->live()` is the current Filament pattern for reactive dependent field updates.
  - Livewire/Filament tests can inspect schema components and call page actions directly.

## FilamentExamples Access Level

Access level: search/snippet only. The available MCP tool exposed `search_examples` results with names, snippets, paths, and class names. No source fetch/detail tool was available.

## FilamentExamples Query Batches

First pass:

- `SettingsPage Repeater select options`
- `Filament Builder dependent select`
- `SettingsPage custom settings tabs`
- `Repeater live options closure`
- `card template settings page`

Refined pass:

- `SettingsPage dynamic select options Get`
- `Repeater options from sibling state`
- `Select options from Repeater state`
- `SettingsPage Repeater live afterStateUpdated`
- `Builder block dependent select options`

## Relevant Results

- `Five Advanced Repeater Use Cases`
  - Paths included `v4/forms/repeater-five-advanced-use-cases/app/Filament/Resources/PricingTables/Schemas/PricingTableForm.php`.
  - Pattern: use `Get` inside select option closures to derive options from sibling/root repeater state, for example `collect($get('../../features') ?? [])->pluck('label', 'key')`.
  - PodText adaptation: use current `data.card_templates` state to build podcast template select options in the settings page before the form is saved.

- `Dependent Country City Dropdowns`
  - Path included `v4/forms/parent-child-dependent-dropdowns/app/Filament/Resources/Shops/Schemas/ShopForm.php`.
  - Pattern: parent select is `->live()`, child select options closure reads the parent state and resets stale child state when the parent changes.
  - PodText adaptation: homepage section `template_family` already uses `->live()`. It should read options through a family-scoped helper so wrong-family custom templates do not appear.

- `Invoice Editor With Live Totals` and `Multi-Step Invoice Creation Wizard`
  - Paths included invoice form/resource snippets.
  - Pattern: Settings-like forms and resource forms can use `Get`/`Set` callbacks without separate models for derived UI state.
  - PodText adaptation: avoid adding any card-template models or tables; keep template options derived from sanitized settings arrays.

## Code Findings

- `PublicContentSettings` currently builds podcast card template select options from `PublicFrontCardTemplateResolver::all($family)`, which reads persisted settings through the scoped render context. That means unsaved templates in the same settings session are not visible in dependent selects.
- `HomepageSectionForm` also reads persisted resolver options. That is acceptable for saved settings, but it needs a resolver helper that consistently returns defaults plus custom templates by selected family.
- `PublicFrontConfigValidator` already normalizes card template definitions:
  - semantic keys only;
  - finite families, layouts, density, image size, title size, parts, sources, attributes, labels, icons, and URL target values;
  - Builder `data` unwrapped before persistence;
  - unsafe HTML, scripts, raw CSS/classes, Blade paths, SQL-looking strings, and PHP class names rejected by existing scalar sanitizers.
- `contributors_page` has contributor card scalar settings, but no contributor card `template_key` setting in the current schema. B1 should document this as deferred instead of inventing a new setting.

## Implementation Notes

- Add a family-scoped options API to `PublicFrontCardTemplateResolver` that can build select options from persisted context templates or a supplied normalized/transient template array.
- Use `PublicFrontConfigValidator`/`PublicFrontConfigReader` normalization before deriving options from unsaved settings-page state.
- Update `PublicContentSettings` podcast template selects to derive options from current `card_templates` form state through `Get`, keeping saved behavior unchanged and enabling same-session visibility where safe.
- Keep homepage section options persisted-settings based, but route them through the same resolver API.
- Add focused tests for saved options, same-session settings-page options, wrong-family exclusion, unsafe value rejection, and contributor template setting deferral.
