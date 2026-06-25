# Codex Prompt 03 — Public Guest Panel

## Goal

Implement the guest-facing Filament Public panel so visitors can browse published ContentGroups and read published ContentItems safely.

## Required context

Read:

- `AGENTS.md`
- `docs/project-description.md`
- `docs/architecture-decisions.md`
- Phase 3 in `docs/project-phases.md`

Inspect the completed Admin panel and actual model scopes before coding.

## Constraints

- Current checkout only; no worktrees.
- Keep the Public panel read-only and guest-accessible.
- Do not register Admin Resources in the Public panel.
- Use custom Filament Pages, Blade templates/components, and focused Livewire components.
- Use Alpine only for local interactions.
- Do not add categories, tags, advanced search, user accounts, comments, requests, analytics, or transcript synchronization.
- Do not bypass published scopes in public record resolution.

## Implement

### 1. Public panel shell

- Configure the Public panel at the approved root path.
- Remove authentication/profile/account elements.
- Add translated navigation and page titles.
- Use locale-aware RTL/LTR direction.
- Load the Public-panel theme and required assets.

### 2. BrowseContentGroups page

Create a custom Filament Page and a focused class-based Livewire 4 component for the dynamic browser.

Required behavior:

- published ContentGroups only;
- responsive card grid;
- cover, title, group type label, excerpt, published item count;
- search by title;
- sort by newest and title;
- pagination;
- search/sort in URL query parameters;
- eager loading/aggregates to avoid N+1 queries;
- accessible empty state.

### 3. ShowContentGroup page

Create a slug-bound custom Page that:

- resolves only a publicly visible group;
- returns not found for a draft/future group;
- shows title, cover, type label, and sanitized description;
- lists only publicly visible child items;
- supports simple item sorting through a focused Livewire component if dynamic sorting is implemented;
- shows effective item label, author names, and duration;
- avoids N+1 queries.

### 4. ShowContentItem page

Create a slug-bound custom Page that:

- resolves only a publicly visible item;
- confirms the parent group is public;
- shows group link, effective label, title, authors, dates, duration;
- shows sanitized item description;
- shows an approved controlled media iframe or original source link;
- shows sanitized transcript Markdown;
- returns not found for drafts/future records.

### 5. Blade components

Create only useful reusable components, likely:

```text
public.content-group-card
public.content-item-row
public.type-label
public.markdown-content
public.media-embed
```

The Markdown component must use the central safe renderer.

The media component must never accept arbitrary iframe HTML.

### 6. Alpine enhancements

Use Alpine only for optional local behavior such as:

- long-description disclosure;
- copy-link feedback;
- embed-loading state.

Do not duplicate Livewire query state.

### 7. RTL and content resilience

- Test long Hebrew titles.
- Test Hebrew diacritics.
- Ensure cards/rows remain usable on mobile.
- Use semantic headings and links.
- Ensure focus indicators remain visible.

## Tests

Add Pest/Livewire tests for:

- guest Public-panel access;
- published-only group browse query;
- title search;
- each sort option;
- pagination;
- URL state where current Livewire APIs make it stable to test;
- published group page;
- draft group returns not found;
- group lists published items only;
- published item page;
- draft item returns not found;
- item under draft group returns not found;
- future publication behavior;
- multiple authors rendered;
- Markdown output sanitized;
- malicious scripts/event handlers absent;
- approved embed rendered;
- unapproved embed not rendered;
- source link fallback.

## Manual verification

Log out, then:

1. browse `/`;
2. search and sort;
3. open the published group created in Phase 2;
4. open its item;
5. confirm media fallback/embed;
6. read the Hebrew transcript;
7. try direct URLs for draft records;
8. switch locale/direction if the UI exposes the current bootstrap language mechanism.

## Completion checks

Run and report:

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

## Final report

Report routes, Pages, Livewire components, Blade components, query strategy, security checks, tests, and command results.

Do not start Prompt 04.
