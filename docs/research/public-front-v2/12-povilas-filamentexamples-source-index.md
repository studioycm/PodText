# Public Front v2 Research: Povilas/LaravelDaily and FilamentExamples Source Index

## Purpose

Index all external and MCP examples observed for this planning task, including access limitations.

## Access Level

- Laravel Boost: available and used for application info, schema, and version-aware docs.
- FilamentExamples MCP: available as `search_examples` only. No source/fetch/read/detail tool was exposed.
- LaravelDaily/FilamentExamples public site: browsed for discoverable public pages and snippets.
- GitHub public source: LaravelDaily menu demo was accessible through GitHub and raw URLs.
- `github.com/studioycm/FilamentExamples`: GitHub API returned 404, so direct source access was unavailable.

## Exact Search Terms Used

- "Spatie settings page JSON array Filament Builder settings page"
- "Filament settings page Repeater array settings Spatie"
- "Filament Builder blocks JSON content settings page"
- "Filament card template builder preview ViewColumn custom card"
- "Filament Builder block preview side by side form preview"
- "Filament custom table card design ViewColumn frontend theme"
- "Filament homepage sections dynamic sections reorderable table"
- "Filament query builder filter sections saved configuration"
- "Filament table bulk select all matching select filtered records"
- "Filament menu builder public header NavigationItem renderHook"
- "LaravelDaily menu builder Filament menu items"
- "Filament public action modal slide over form"
- "Filament CMS page builder Builder RichEditor FileUpload team profiles"
- "Filament dynamic form fields Builder public form submissions"
- "Filament master detail page list preview Livewire"
- "Filament filter drawer slide over search filters"
- "Laravel Filament demo seeder factories database seeding"

## FilamentExamples MCP Examples Found

- `v4/full-projects/hotel-management-bookings/app/Filament/Hotel/Pages/MyHotel.php`
- `v4/full-projects/hotel-management-bookings/app/Filament/Booking/Pages/FindHotel.php`
- `v4/full-projects/hotel-management-bookings/app/Providers/Filament/HotelPanelProvider.php`
- `v4/full-projects/hotel-management-bookings/app/Providers/Filament/BookingPanelProvider.php`
- `v4/forms/edit-profile-custom-forms/app/Filament/Pages/EditProfile.php`
- `v4/forms/edit-profile-custom-forms/resources/views/filament/pages/edit-profile.blade.php`
- `v4/full-projects/cms-blog-system-shield/app/Filament/Resources/Posts/Schemas/PostForm.php`
- `v4/full-projects/schedule-for-doctors/app/Filament/Resources/Doctors/Schemas/DoctorForm.php`
- `v4/forms/livewire-component-in-editform-sidebar/resources/views/livewire/ticket-sidebar.blade.php`
- `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/Pages/ManagePlayerStats.php`
- `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/RelationManagers/MatchesRelationManager.php`
- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`
- `v4/tables/public-products-table/app/Livewire/Products.php`

## GitHub/External Files Inspected

- `https://github.com/LaravelDaily/Filament-Menu-Builder-Demo`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/config/filament-menu-builder.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/database/migrations/2026_02_16_061744_create_menus_table.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/database/migrations/2026_02_16_061745_create_menu_items_table.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/resources/views/components/layouts/app.blade.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/routes/web.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/app/Providers/Filament/AdminPanelProvider.php`
- `https://laraveldaily.com/post/filament-appointment-booking-re-use-admin-panel-form-on-public-page`
- `https://filamentexamples.com/`

## Patterns To Copy

- Custom Page forms with `statePath('data')` and explicit save actions.
- Builder/Repetater admin forms for structured arrays.
- Card-table admin patterns for preview and selection UIs.
- Public layout rendering a menu from a stable key.
- Real submission/booking records for public user-generated data.

## Patterns To Avoid

- Menu package migrations as default PodText architecture.
- Raw link/wrapper class storage.
- Public Filament Tables replacing the custom Prompt 11R Livewire/Blade listings.
- Arbitrary dynamic query or route storage.

## PodText Adaptation Notes

The examples validate the feasibility of Filament Pages, Builders, Repeaters, Actions, and custom cards, but PodText must apply the stricter JSON-first and safe registry rules from the user prompt.

## Open Source Access Needed For Deeper Follow-Up

- Authenticated FilamentExamples source access if the user wants exact full project files beyond search snippets.
- Private `studioycm/FilamentExamples` access if that repository exists and is intended as a source.
