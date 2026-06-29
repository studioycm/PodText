# Phase 02 Public Panel UX Spec

## Architecture

- Keep the guest Filament Public panel.
- Use custom Filament Pages for homepage/search/category/tag/item pages.
- Use class-based Livewire components for search, filters, sorting, pagination, and transcription tab selection.
- Use Blade components for content cards, group badges, media embeds, type labels, tags/categories, and safe transcript output.
- Use Alpine only for local behavior such as filter drawer, copy feedback, and viewer show/hide preferences.

## Homepage

Homepage result cards are `ContentItem` records.

The homepage is one combined pinned-first/latest `ContentItem` list. It should not split public results into separate competing record types.

Default layout should use mixed pinned cards and latest rows where appropriate. Search results should use a consistent card grid so filtered and landing-page results feel predictable.

Default combined list order:

1. valid pinned items first;
2. `pin_order` ascending;
3. `pinned_at` descending;
4. effective/main transcription `published_at` descending;
5. item `published_at` fallback.

No separate pinned result model exists.

## Group Badge

Show content group cover image where available. Fallback to initials/title badge.

## Search and Landing Pages

- Show a search result count.
- Provide a sort dropdown with translation-key labels.
- Provide clear filters behavior.
- Category and tag landing pages reuse the same public item-card component as search results.
- Date/date-time displays on public pages use Hebrew/Israel locale behavior with day-first `dd/mm/yyyy` dates and `dd/mm/yyyy HH:mm` date-times where shown.
- Public UI date/time presentation uses `Asia/Jerusalem`.

## Item Page

Prompt 12 implements:

- one `ContentItem`;
- media player/source component;
- effective/main transcription default tab;
- other published transcriptions as tabs/selector;
- safe Markdown rendering;
- timestamp/speaker parser when present;
- show/hide timestamps and speakers;
- timestamp anchors;
- no player sync;
- reading time;
- audio duration;
- transcript length;
- categories/tags;
- author links;
- copy/share actions;
- desktop and mobile layout defaults.

Default item page layout:

- Desktop: header/meta at the top, sticky player in a side/top area, and transcript in a readable main column.
- Mobile: sticky player at the top and transcript below.
- Timestamp displays should be direction-safe in Hebrew RTL layouts.

Later, not Prompt 12:

- copy link to timestamp;
- request this episode;
- report correction.

## Blueprint

See `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md` and `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`.
