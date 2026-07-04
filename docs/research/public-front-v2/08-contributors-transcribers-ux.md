# Public Front v2 Research: Contributors and Transcribers UX

## Purpose

Plan UX refinements for public contributor/transcriber discovery using `Author` as the public-safe model.

## Topic Scope

Contributor directory layout, compact list, selected preview, top transcribers homepage section, counts, duplicate item handling, and public-safe queries.

## Exact Search Terms Used

- Boost: "Livewire URL attribute query string properties selected item"
- Boost: "Livewire pagination resetPage WithPagination query string"
- FilamentExamples MCP: "Filament master detail page list preview Livewire"
- FilamentExamples MCP: "Filament two column page selectable list preview"
- FilamentExamples MCP: "Filament top authors leaderboard card preview"
- FilamentExamples MCP: "Filament livewire component in edit form sidebar preview"

## Boost Docs Used

- Livewire URL state and pagination docs.
- Tailwind layout docs for grid/flex sizing, `min-w-0`, and responsive layout.

## FilamentExamples MCP Examples Found

- `v4/forms/livewire-component-in-editform-sidebar/resources/views/livewire/ticket-sidebar.blade.php`: sticky sidebar preview-like content with status cards.
- `v4/full-projects/hotel-management-bookings/app/Filament/Booking/Pages/FindHotel.php`: selectable/search-result page pattern.
- No public contributor-specific example was found.

## Actual Files, Classes, and Snippets Observed

- Local: `app/Support/PublicContent/PublicContributorDiscovery.php` counts public transcriptions through public item/group constraints.
- Local: `app/Livewire/Public/ContributorDirectory.php` has URL-backed search and selected contributor state.
- Local: `resources/views/livewire/public/contributor-directory.blade.php` currently renders a broader grid and sticky preview.
- Local: `resources/views/components/public/contributor-card.blade.php` includes actions not suitable for compact list cards.

## GitHub/Source Files Inspected

- No external contributor-discovery source was found. Research is based on local code, Boost docs, and FilamentExamples layout snippets.

## Pattern To Copy

- Keep selected contributor in Livewire URL state.
- Use the existing public visibility query and author/transcription relationship logic.
- Use a master/detail layout with compact selectable list and rich preview.

## Pattern To Avoid

- Do not create a separate Transcriber model.
- Do not count credited authors; count actual published transcriptions by `Transcription.author_id`.
- Do not duplicate the same item card badly when the same author has multiple transcriptions for one item.

## PodText Adaptation Notes

The compact list should be a distinct Blade component or mode, not a hacked variant of the full contributor card if that makes semantics unclear.

## JSON-First Settings Recommendation

Settings should control:

- contributor page label and route label
- compact list page size
- preview latest item count
- top transcribers section default count
- top transcribers preview page-size choices `5`, `10`, `15`
- selected card template family where applicable

## Model/Table Considered

Rejected: no contributor UX setting needs a model. Continue using `Author`.

## Recommended Model/Schema Options

No schema. Future denormalized counters are out of scope unless performance proves a need.

## Recommended Filament Patterns

Use settings fields for labels and counts, likely under PublicContentSettings or a new public-front settings section. No Filament Resource changes are required for UX only.

## Public Livewire/Blade Implications

- Desktop: right compact list about 25%, selected preview about 75%.
- Compact card: name plus number badge, no label text, full-card click.
- Preview: name, transcription count, link to full transcriber page, latest related content items.
- Homepage top transcribers: horizontal compact list, preview underneath, default five, selectable page size.

## Tests

- Counts include each published transcription by author with public parent item/group.
- Same item with two transcriptions counts two but renders one item with both transcription names.
- Draft/unpublished parent records are excluded.
- Selected contributor state is URL-backed.
- Compact card has no full-page action link.

## Security Notes

Contributor pages must not reveal unpublished transcriptions through counts, names, or latest items. Safe Markdown renderer remains required for bios.

## Open Questions

- Should transcriber route label/path be admin-configurable in v1?
- Should top transcribers include authors with zero public bio?
- How should ties be sorted after transcription count?
