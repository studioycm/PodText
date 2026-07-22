# MEDIA-P2 Gallery and Repair Plan Route

## Status

Preliminary route only. Replace with a Filament Blueprint-complete plan after
Package 1 closes and before the first failing test.

## Planned jobs

1. TDD trusted gallery and Needs Repair separation, authorized preview/download
   routes, minimal projection and pagination/search/query budgets.
2. TDD system/custom logical folder model policy, Resource/settings UX,
   deletion reassignment, visibility/default filter behavior and translations.
3. TDD bounded media-library settings for browse/search/upload defaults,
   cleaned filename preference, raster working targets and quarantine days.
4. TDD reusable journaled raster normalize/revalidate and staged SVG sanitation
   across success, malicious input, collisions, failure/retry, cache and
   quarantine; never act on real SVG rows.
5. Reconcile docs/handoff, independent review, focused/final gates and canonical
   implementation plus hash-stamp commits.

## Required Filament plan details before implementation

- full Resource/field/column/filter/action namespaces and documentation URLs;
- action location, visibility, authorization and step-by-step behavior;
- exact table query/eager loads/index use;
- exact settings fields, validation and hard ceilings;
- real workflow feature/Livewire/browser tests;
- no unsafe byte URL and no root/folder selection coupling.

## Out of scope

Four-source acquisition/picker integration, owner hover/detail UX, Files
Discovery, real conversion/sanitation and dependencies.
