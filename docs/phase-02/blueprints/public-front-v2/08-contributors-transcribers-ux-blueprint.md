# Contributors and Transcribers UX Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: public contributors/transcribers UX refinements.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Refine contributor discovery using `Author` as the public-safe contributor/transcriber model.

## Dependencies

- Prompt 11B contributor discovery.
- Existing `PublicContributorDiscovery`.
- Existing `ContributorDirectory` and contributor Blade components.
- Docs: https://livewire.laravel.com/docs/4.x/url, https://livewire.laravel.com/docs/4.x/pagination.

## Primary User/Admin Flows

- Admin configures contributor labels/counts in settings.
- Guest opens contributors page.
- Guest searches/selects a compact contributor list item.
- Selected preview updates and remains URL-backed.
- Guest clicks full transcriber page link.
- Homepage top transcribers section allows horizontal selection and preview below.

## Filament Primitive Mapping

- Settings Page fields only.
- Field: `Filament\Forms\Components\TextInput`, Validation: integer ranges for counts/page sizes, Config: preview count and default labels.
- Field: `Filament\Forms\Components\Select`, Validation: allowed page sizes, Config: 5/10/15 for homepage preview.
- No Filament Resource changes.

## JSON Settings/Configuration Shape

```json
{
  "contributors": {
    "public_label": "Transcribers",
    "directory_compact_list_width": "quarter",
    "preview_latest_count": 5,
    "top_section_default_count": 5,
    "top_section_page_sizes": [5, 10, 15]
  }
}
```

## Models/Migrations

No model or migration. Continue using `Author`.

## Casts/Enums/Support Classes

- Extend contributor settings reader.
- Optional `ContributorDirectoryLayout` enum.

## Relationships

Use `Transcription.author_id`, `Transcription.contentItem`, and public group/item constraints.

## Filament Resources/Pages

No Resource changes.

## Form Schemas

Settings fields:

- Preview latest count: numeric, min 1, max 15.
- Top page sizes: allowed fixed options 5/10/15.
- Labels: translatable settings text where feasible.

## Tables/Actions

No admin tables/actions.

## Public Pages/Livewire/Blade

- Directory desktop layout: compact list right side about 25%, preview left/main about 75%.
- Compact card: name only and number badge only; whole card selectable; no page action.
- Preview: name, count, full page link, latest related content items.
- Top transcribers section: horizontal compact list and preview underneath.

## Settings

Contributor labels and counts live in JSON settings.

## Seeders

Demo content can include multiple transcribers and duplicate transcriptions on one item to test counting.

## Tests

- Count any published transcription by author whose item/group is public.
- Same item with two published transcriptions counts two and renders one item with two transcription names.
- URL-backed selected contributor works.
- Hidden/unpublished parents excluded.
- Compact card has no full-page action.

## Security

No unpublished records in counts, latest items, or profile links. Bios still use safe Markdown renderer.

## State/Configuration Transitions

- Selecting contributor updates Livewire state and URL.
- Changing page size refreshes preview list.
- Missing selected slug falls back to top/first contributor or empty state.

## Out Of Scope

- New Transcriber model.
- Contributor account management.
- Denormalized counters.

## Quality Gate

Implementation later runs Livewire public component tests and full quality gate.

## Final-Report Checklist

- State counting rules.
- State layout behavior.
- State duplicate-transcription handling.
- Confirm no new contributor model.
