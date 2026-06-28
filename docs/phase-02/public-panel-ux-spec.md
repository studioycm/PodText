# Phase 02 Public Panel UX Spec

## Architecture

Keep the guest Public panel as the public shell. Use custom Filament Pages and class-based Livewire components where server-driven interaction is needed.

Use Blade components for:

- content item cards/rows
- group badges
- type labels
- category/tag chips
- media embed/source links
- sanitized transcript output

Use Alpine only for browser-local behavior such as copy-link feedback, filter drawer toggles, and viewer display preferences.

## Homepage

The homepage should show `ContentItem` records. It may include:

- featured/pinned-first item list
- latest items by effective/main transcription date
- category/tag/group slices configured by admins
- empty states and suggestions

Pinned items are not a separate model or transcript feature. They are `ContentItem` records ordered ahead of normal results when the selected view allows pinned-first ordering.

## Item Page

The item page represents one `ContentItem`.

Required content:

- title, group, display type labels, author links
- media player or original source link
- effective/main transcription by default
- other published transcriptions as tabs/selector
- categories and enabled tags
- reading time, audio duration, transcript length
- copy link and basic share actions
- accessible empty states

Draft groups, draft items, and draft transcriptions must return not found or be absent publicly.

## RTL and Localization

All UI text must use translation keys. Hebrew remains the primary locale and public layouts must render RTL correctly.

## Tests Required Later

- Guest access to homepage/search/item pages.
- Draft records inaccessible.
- Public results are items, not transcriptions.
- RTL markers/classes where feasible.
- Copy/share controls render without breaking layout.
