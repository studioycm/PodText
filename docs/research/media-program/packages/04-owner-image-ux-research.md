# MEDIA-P4 Owner Image UX Research Route

## Status

- Package ID: `MEDIA-P4-OWNER-IMAGE-UX`
- Stage: approved route; detailed source reconciliation waits for Package 3.

## Settled requirements

- Podcast and episode image columns show a normal thumbnail and hover/focus
  preview capped at 300px.
- Click opens one modal/slide-over with trusted image, source/fallback label,
  metadata, safe download, copy cleaned filename, permitted metadata edit and
  change image.
- Change image reuses the same MediaAssetPicker and owner repair action.
- Direct asset, podcast fallback, configured default and static fallback must
  be distinguished; changing an owner never mutates the fallback asset.
- Unsafe current media mounts safely with diagnostic replace/detach-to-default
  and no unsafe preview URL.
- Reuse one action/column across Resource, relation manager and workspace
  surfaces.

## Current evidence to recheck

- ContentGroup and ContentItem tables/forms/pages;
- ContentItems relation manager and episode workspace;
- `ContentImageActions` and LMTC diagnostic projector;
- effective image/default resolver and existing eager loads;
- Package 3 MediaAssetPicker contract;
- installed Filament 5.7 column action/modal/slide-over APIs;
- record URL click behavior, keyboard/focus/RTL and browser conventions.

## Required research output before code

- exact reusable column/action/view contract and target surface matrix;
- displayed-source resolver contract;
- authorization matrix for download/edit/change/fallback;
- thumbnail/hover/detail URL and lazy-load design;
- query/state/browser budgets and HE/EN/RTL test plan.

Do not implement from this route-only document without reconciling it after
Package 3.
