# Bootstrap Slice 0 Project Guideline

Apply this guideline only while implementing Bootstrap Slice 0.

## Goal

Deliver one usable content loop:

```text
Admin creates or imports an author, content group, and content item
→ Admin publishes the group and item
→ Guest visitor browses and reads the transcript
```

## Stable internal terminology

- Model and table naming must use `ContentGroup` / `content_groups`.
- Model and table naming must use `ContentItem` / `content_items`.
- Use `Author` / `authors` for credited contributors.
- Do not introduce `Podcast` or `Episode` classes or tables.
- Default display labels are Podcast/Podcasts and Episode/Episodes.
- Administrators may override display labels without altering internal names.

## UI architecture

- Admin CRUD: Filament 5 Resources.
- Temporary public frontend: a guest Filament 5 panel with custom Pages.
- Reusable presentation: Blade components.
- Server-driven search, sort, and pagination: Livewire 4.
- Immediate browser-local interactions: Alpine.js.
- Do not use Alpine for authoritative or persistent data.

## Backend architecture

- Prefer Eloquent relationships, casts, and explicit published scopes.
- Use a focused `SafeMarkdownRenderer` for public rendering.
- Do not create broad generic services such as `ContentService`.
- Do not add speculative actions, DTOs, repositories, event buses, or provider abstractions.
- Use a PHP backed Enum for publication status.

## Import/export

- Use Filament-native Import and Export Actions.
- Use stable ULID-style `reference_key` values for identity across exports and later imports.
- Support create and update imports.
- Resolve item-to-group and item-to-author relationships by reference key.
- Keep cover and media files outside CSV import for this slice.
- Use the minimum queue/batch/notification infrastructure required by Filament.

## Security

- Admin panel requires authentication.
- Public panel is guest-accessible and read-only.
- Public queries must include publication state and publication date checks.
- A public item is visible only when both the item and its group are published.
- Store Markdown; render only sanitized HTML.
- Store URLs, never arbitrary embed HTML.
- Accept HTTPS embed URLs only and validate permitted hosts.
- Draft records must return a not-found response publicly.

## Localization

- All interface text uses translation keys.
- Hebrew is the default locale and must render RTL.
- English is available from the start.
- Content type labels are database content and may be written in any language.

## Scope exclusions

Do not implement Shield, volunteer/auditor roles, approval workflows, categories, tags, requests, provider APIs, transcript synchronization, activity logging, analytics, comments, or the transcription studio.

## Completion

Every task requires Pest tests, successful formatting, and a successful frontend build. Never skip or fabricate command results.
