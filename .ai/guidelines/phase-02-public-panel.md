# Phase 02 Public Panel Guideline

- Public pages are guest-accessible Filament custom Pages.
- Public homepage/search/category/tag listings return `ContentItem` records.
- Do not render public result cards for `Transcription` records.
- Public item visibility requires a published group, published item, and effective/main published transcription.
- Keep reusable public presentation in Blade components.
- Use class-based Livewire only for server-driven search, sorting, pagination, and item transcription selectors.
- Use Alpine only for local UI behavior such as drawers, copy feedback, and viewer preferences.
- All UI strings use translation keys.
- Hebrew/RTL must remain first-class.
