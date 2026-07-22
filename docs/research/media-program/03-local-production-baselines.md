# Media Program Local and Production Baselines

## Evidence policy

These are dated planning/fixture baselines. They do not authorize a live action
and must not be presented as current after time passes. Package 1 tests recreate
the shapes in isolated databases and fake storage only.

## Git baseline — verified 2026-07-23

- Root: `/Users/studioycm/Herd/PodText`
- Branch: `main`
- HEAD: `b455d5d546c5902edebaade2ad31c34bbfef3d2f`
- Ahead/behind `origin/main`: 13/0
- Unfinished draft at approval: 79 modified tracked files, 54 untracked entries
  in default status output and 55 explicitly enumerated files because
  `tests/Support/` was collapsed, tracked diff approximately `+5,481/-641`,
  untracked content approximately 9,818 lines.

The draft is attributable to the unfinished overbuilt Package 1 subject unless
a later per-path/per-hunk preflight identifies overlap. It is not shipped
behavior and its untracked handoff is invalid/incomplete.

## Local data — dated evidence only

- G1 migrations were previously run locally.
- An earlier incident recorded 15 Curator rows with null keys.
- The later operator-provided state says all 15 rows now have valid unique
  reference keys.
- Package 1 performs no fresh local DB/storage/cache probe and no real migration
  or conversion execution.

Fixtures must cover both the original 15-null-key incident and current
15-valid-key idempotence shape.

## Production — dated snapshot only

- Production remains pre-G1 in the last verified snapshot.
- 403 Curator rows.
- 108 matched podcast/group cover paths.
- Three SVGs.
- Five filesystem-only managed files without Curator rows.
- One oversized raster.
- Two duplicate-byte pairs.

The fixture preserves every Curator identity and owner/settings mapping.
Duplicate bytes remain separate assets. Filesystem-only files remain excluded
from Package 1 and are reserved for Files Discovery.

## Real-environment boundary

No migration, conversion, sanitation, cache operation, storage mutation,
deployment or production process action is authorized by Package 1
implementation. Any future production action requires a fresh read-only
preflight and exact per-action approval.
