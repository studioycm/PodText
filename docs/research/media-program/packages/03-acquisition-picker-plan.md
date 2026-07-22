# MEDIA-P3 Acquisition and Picker Plan Route

## Status

Preliminary route only. Replace with a Filament/Livewire-complete plan after
Package 2 closes and before the first failing test.

## Planned jobs

1. TDD asset-key MediaAssetPicker gallery tab, logical default/All Media,
   locked minimal state, server re-query/authorization and bounded search.
2. TDD shared acquisition coordinator/result plus Gallery no-op selection and
   Upload validation/filename/batch path.
3. TDD URL acquisition by refactoring the existing SSRF-safe fetch/job flow,
   expected owner comparison, idempotency and failure/retry.
4. TDD Storage exact-candidate import and podcast/episode Spotify URL handoff;
   migrate every current image field to the same picker.
5. Reconcile docs/handoff, independent security/performance review, browser/
   feature verification and canonical two commits.

## Required plan details before implementation

- full component/action namespaces and version-correct APIs;
- exact state fields and validation limits;
- source-specific steps, journal operation and transaction boundary;
- every owner/settings/Spotify integration file;
- HTTP fixtures and `Http::preventStrayRequests()` coverage;
- forged/stale Livewire/key/path/URL/candidate tests;
- query, serialized state, upload and browser budgets.

## Out of scope

Owner image-column detail polish, Files Discovery management UI/lifecycle, real
data actions and dependencies.
