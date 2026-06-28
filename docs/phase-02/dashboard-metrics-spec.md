# Phase 02 Dashboard Metrics Spec

## Scope

Dashboard work is editorial and lightweight. Do not add analytics, search logging, observability dashboards, or retry managers.

## Widgets

Use simple Filament widgets:

- total published items
- draft items
- pinned items
- items with multiple transcriptions
- items missing an effective/main transcription
- content groups
- authors
- categories
- enabled/disabled tags
- recently published items
- items missing media/embed URL
- items without category
- transcriptions by author

## Patterns

Follow simple `StatsOverviewWidget` and `TableWidget` patterns. Avoid polling unless a clear editorial need exists.

## Tests Required Later

- Widgets render for authenticated admins.
- Counts honor publication/effective transcription rules.
- Warning lists link to the relevant admin resources.
