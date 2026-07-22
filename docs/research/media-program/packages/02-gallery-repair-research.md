# MEDIA-P2 Gallery and Repair Research Route

## Status

- Package ID: `MEDIA-P2-GALLERY-REPAIR`
- Stage: approved route; detailed source reconciliation waits for Package 1
  canonical closeout.

## Settled requirements

- MediaAsset trusted+active is the primary gallery authority.
- Needs Repair is separate and never emits an unsafe byte URL.
- Logical folders are flat, independent from physical roots and selection.
- Six system folder keys are protected/translatable; custom folders are admin
  manageable; custom deletion moves assets to Legacy library.
- Default folder is an initial filter and All Media is one click.
- Sanitized SVG is trusted and compatible everywhere; unsanitized/failed SVG
  stays Needs Repair.
- Raster normalizer and SVG sanitizer are reusable journaled transitions.
- Working targets/UX limits are admin configurable inside hard ceilings.

## Current evidence to recheck

- current `MediaResource`, `MediaTable`, `MediaForm` and controllers;
- installed Filament Resource/ListRecords tabs, mounted actions, custom column
  and authorized route APIs;
- Package 1 `MediaAssetScope`, models, provider adapter and journal changes;
- G1 validators/normalizer/SVG sanitizer and LMTC failure/retry tests;
- current settings ownership/lifecycle route for app-wide media settings;
- forms UX, security and performance skill findings;
- FilamentExamples multi-query/refinement limitation.

## Required research output before code

- exact Resource/page/tab/schema/table/action class names and namespaces;
- exact folder settings ownership and validation ceilings;
- normalizer/sanitizer reuse map and state machine;
- preview/download route security design;
- query/state budgets and 1/10/50 fixture method;
- HE/EN/RTL interaction map;
- source reconciliation statement confirming no material drift.

Do not implement from this route-only document without reconciling it after
Package 1.
