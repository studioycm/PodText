# MEDIA-P4 Owner Image UX Plan Route

## Status

Preliminary route only. Replace with a Filament Blueprint-complete plan after
Package 3 closes and before the first failing test.

## Planned jobs

1. TDD a typed displayed-image source resolver and minimal trusted thumbnail
   projection for direct/fallback/unsafe states.
2. TDD one app-owned table image column/view with 300px hover/focus preview and
   click isolation from record URLs.
3. TDD one Filament 5 detail slide-over/modal action with metadata, safe
   download, copy filename, edit metadata and MediaAssetPicker change action.
4. Integrate/test ContentGroup table/edit, ContentItem table/edit/workspace and
   relation manager; prove unsafe repair/default behavior and 1/10/50 bounds.
5. Add real Livewire/browser, HE/EN/RTL and accessibility coverage; reconcile
   docs/handoff/review/gates and canonical two commits.

## Required plan details before implementation

- exact full namespaces and installed-version docs URLs for column/actions;
- modal location, visibility, authorization and step behavior;
- Blade/CSS/theme source coverage and keyboard/focus semantics;
- each eager load/query projection and performance assertion;
- fallback ownership language and forged/stale input coverage.

## Out of scope

Files Discovery/lifecycle, dependency installation and real environment action.
