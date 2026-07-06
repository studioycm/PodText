# Public Front v2 Step 9R Menu/Header UX Fixes MCP Research

## Purpose

This note records the FilamentExamples MCP searches used for Step 9R repair work. The goal was not to copy a complete implementation, but to verify Filament 5 patterns for settings page organization, panel/header hooks, custom public pages, card grids, FileUpload image handling, and safe rich-content previews.

## Access Level

Only `mcp__filament_examples.search_examples` was exposed. No separate source/read/fetch/details tool was available, so this research is snippet/search access rather than a full source fetch.

## Query Batches

Batch 1 - settings page organization:

- `settings page tabs`
- `collapsible sections`
- `full width settings form`
- `Filament SettingsPage tabs`
- `settings form schema sections`
- Limit: 8

Batch 2 - public header/menu:

- `public page header`
- `custom panel header`
- `Filament public menu`
- `menu builder`
- `render hook header`
- `theme switcher`
- Limit: 8

Batch 3 - contributor/cards:

- `Livewire card preview`
- `contributor directory`
- `card grid settings`
- `public card layout`
- `search inside preview`
- Limit: 8

Batch 4 - content/heading/images:

- `Markdown content styling`
- `RichEditor public rendering`
- `image crop settings`
- `FileUpload SVG logo`
- `card image fallback`
- Limit: 8

Batch 5 - surrounding best practice:

- `custom page layout`
- `Livewire public page`
- `SettingsPage Builder`
- `repeater menu items`
- `public form modal`
- Limit: 8

Refined second pass:

- `brandLogo render hook`
- `Tabs columnSpanFull settings page`
- `FileUpload svg acceptedFileTypes`
- `card grid contentGrid recordUrl false`
- `RichEditor Markdown preview safe`
- Limit: 8

## Relevant Results And Patterns

### Tabbed Settings And Large Forms

Example: `v4/forms/large-employee-form-with-tabs/app/Filament/Resources/Employees/Schemas/EmployeeForm.php`

- Found pattern: `Tabs::make()->columnSpanFull()->tabs([...])` with grouped tab schemas.
- Pattern to copy: major settings domains should be tabbed and full-width.
- Pattern to avoid: cramped top-level multi-column layouts for large settings pages.
- PodText adaptation: Step 9 already uses vertical tabs and collapsible sections; Step 9R should add new header/image controls inside existing tab structure rather than creating a new settings page or model.

Example: `v4/forms/repeater-five-advanced-use-cases/...`

- Found pattern: nested repeaters and fieldsets for grouped repeatable configuration.
- Pattern to copy: keep repeatable menu item fields grouped inside fieldsets.
- Pattern to avoid: raw ungrouped repeater fields for complex JSON items.
- PodText adaptation: menu items remain JSON settings and registry-validated.

### Panel Branding And Render Hooks

Example: `v4/full-projects/branded-filament-panel-with-sidebar-profile-card/app/Providers/Filament/AdminPanelProvider.php`

- Found pattern: `brandLogo(fn () => view(...))`, `brandLogoHeight(...)`, and `renderHook(...)`.
- Pattern to copy: panel render hooks are the right place for custom public shell insertions while keeping panel navigation disabled.
- Pattern to avoid: relying on Filament navigation for the public menu.
- PodText adaptation: keep `PublicPanelProvider` hook for `<livewire:public.public-header />`; resolve logo paths from safe normalized settings and fallback assets.

Example: `v4/full-projects/investor-broker-assets-multi-panel/...`

- Found pattern: render hooks can inject Blade-rendered fragments around Filament panel surfaces.
- Pattern to copy: use stable panel hooks for custom public chrome.
- PodText adaptation: public header remains Livewire/Blade, not a Filament resource table/menu.

### Card Grids And Preview Layouts

Example: `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`

- Found pattern: card grid breakpoints (`md` and `xl`) and `recordUrl(false)` for explicit card actions.
- Pattern to copy: multiple-column card grids and explicit card click behavior.
- Pattern to avoid: reintroducing public Filament Tables.
- PodText adaptation: contributor preview related items should use the existing Blade card grid with `layout="cards"` instead of forcing row layout.

Example: Livewire/sidebar custom-page snippets from `v4/forms/livewire-component-in-editform-sidebar/...`

- Found pattern: server-owned Livewire state with Blade-rendered side/preview content.
- Pattern to copy: keep selected contributor and preview search in Livewire.
- Pattern to avoid: moving selected contributor state into Alpine.

### Rich Content And Markdown

Example: `v4/forms/markdown-and-rich-editor-preview-forms/...`

- Found pattern: editor preview action that renders RichEditor/Markdown content for admins.
- Pattern to copy: explicit preview/render boundary.
- Pattern to avoid: raw public `{!! !!}` without app sanitization.
- PodText adaptation: preserve `SafeMarkdownRenderer` and `PublicAboutPageRenderer`; use fixed public content class maps for H1-H6.

### FileUpload And Image Handling

Example: `v4/full-projects/eshop-with-front-page/...`

- Found pattern: image uploads with managed disks/directories and media conversion hints.
- Pattern to copy: storage-managed logo/image paths, accepted file types, max size, and fixed semantic styling controls.
- Pattern to avoid: arbitrary external logo URLs or raw classes from JSON.
- PodText adaptation: support safe `header/` logo uploads if configured; preserve fallback to panel logo assets or `public/images/podtext-logo.jpg`. SVG logo support remains limited to storage-managed paths and safe extension validation, not raw inline SVG.

## Decisions For Step 9R

- Use the existing tabbed `PublicContentSettings` page and extend it with safe fields instead of creating menu/header models.
- Keep the public header as a Livewire component rendered from `PublicPanelProvider`.
- Use fixed class maps for menu alignment, theme selector modes, image fit/radius, and badge/title presentation.
- Keep homepage/search/discovery state in Livewire, but make root `/` ignore discovery query state and reserve `/search` for full filters.
- Keep contributor preview state in Livewire and switch preview related items to a multi-column Blade grid.
- Do not implement a full footer manager or full section builder in Step 9R.
