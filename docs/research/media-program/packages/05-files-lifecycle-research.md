# MEDIA-P5 Files Discovery and Lifecycle Research Route

## Status

- Package ID: `MEDIA-P5-FILES-LIFECYCLE`
- Stage: approved route; detailed source reconciliation waits for Package 4.

## Settled requirements

- Files Discovery is separate from the trusted Media gallery and shows actual
  app-owned filesystem candidates that have no canonical asset binding.
- It excludes cache, curations, staging, quarantine, trash internals,
  symlinks/traversal and other disks/roots.
- Import and Import-and-Use use the Package 3 validator/acquisition journal.
- Logical folder changes never move files.
- Physical move, rename, trash, restore and purge are explicit journaled
  copy-verify-commit-cleanup operations.
- Private quarantine/trash defaults to 90 days; purge needs elapsed retention,
  completed journal, zero references, fresh digest and authorization.
- A future file-manager plugin may be UI only and cannot bypass these services;
  no dependency is installed now.

## Current evidence to recheck

- Package 1 journal/fence/provider bridge;
- Package 3 Storage acquisition/candidate contract;
- all canonical roots, disks, cache/curation paths and filesystem config;
- existing rename/swap/delete/repair coordinator code and tests;
- settings/owner/reference finder and integrity reporter;
- Filament custom-data table/page and pagination APIs;
- production five-file discovery shape only as a fixture, never an import.

## Required research output before code

- exact owned-root/exclusion policy and opaque candidate digest;
- Files Discovery query/pagination/state design;
- operation-by-operation journal/failure/compensation matrix;
- trash/quarantine metadata and purge/tombstone decision;
- zero-reference inventory across all consumers;
- cache/Glide/placeholder/palette invalidation map;
- runbook and production approval boundary.

Do not implement from this route-only document without reconciling it after
Package 4.
