# MEDIA-P5 Files Discovery and Lifecycle Plan Route

## Status

Preliminary route only. Replace with a Filament/state-machine-complete plan
after Package 4 closes and before the first failing test.

## Planned jobs

1. TDD bounded read-only Files Discovery, exclusions, opaque candidate digest,
   metadata projection and authorization.
2. TDD Import/Import-and-Use through the unified acquisition coordinator,
   including stale/forged/symlink/traversal/refusal cases.
3. TDD explicit physical move and rename with collision, failure/retry, provider
   switch, cache invalidation and no logical-folder coupling.
4. TDD trash/restore/purge, 90-day retention, zero-reference proof, incomplete
   journal refusal and private storage cleanup.
5. Reconcile integrity reports, final MediaAsset/SVG/cutover runbooks, all
   active docs, handoff, independent review, complete final gates and canonical
   two commits.

## Required plan details before implementation

- full page/table/action namespaces and version-correct APIs;
- exact root/exclusion configuration and candidate serialization;
- every operation state and pre/post-commit compensation step;
- exact reference queries and indexes;
- retention settings/hard bounds and authorization;
- query/state/filesystem/browser/translation tests;
- explicit statement that no real discovered file is imported or mutated.

## Out of scope

Plugin installation, other media types/providers, production execution and
compatibility-field removal.
