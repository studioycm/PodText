# Inventory-First Media Program Research

## Question

What is the smallest provider-neutral foundation that preserves every Curator
row and relationship without turning identity or file-quality rules into
visibility authorization?

## Evidence reviewed

- Committed Curator G1 models, migrations, attachment manager, identity
  resolvers, Resource query, picker, settings projection, import/export and
  public fallback tests.
- The unfinished 79-file tracked and 54-file untracked Package 1 draft,
  inspected separately from committed HEAD.
- The dated 15-row local incident evidence and 403-row production snapshot.
- Current Laravel 13, Filament 5, Livewire 4 and Pest conventions.

No live database, storage, cache or production probe was performed for this
reset.

## Findings

### Committed behavior

`MediaRecordScope` combines inventory with a strict validation boundary. It
excludes rows for key, disk, visibility, root, directory depth, MIME,
extension, size, dimension, reconstructed name/path and ASCII syntax. The
Resource and picker consume that scope, so existing rows can silently vanish.

`MediaAttachmentManager` already stores a numeric `media_id`, but identity
resolution also compares the legacy owner path and can veto an otherwise valid
attachment. Public fallback therefore exceeds the four accepted D01 reasons.

### Unfinished draft

The draft adds asset validation/lifecycle state, folders, canonical disk/path,
hashes, normalization proof, source provenance, schema capability states,
manifest digests, private quarantine, raster rewriting, SVG sanitation,
journaled conversion and file cleanup. It can clear or suppress owner/settings
associations for rows classified unsafe.

Those mechanisms are internally coherent with the superseded trusted-media
plan but contradict the inventory-first rule. Salvaging them would retain more
rejected behavior than useful Package 1 code.

### Reusable committed seams

- Curator `Media` remains the provider row and file-location owner.
- `MediaAttachment` already provides the authoritative local owner relation.
- Owner paths and settings path/key pairs remain useful compatibility mirrors.
- Existing factories and relationship fixtures provide production-shaped
  setup patterns.
- Existing authorization and file-delivery/acquisition controls remain owned by
  their current boundaries.

## Minimal kernel conclusion

The kernel needs only a portable asset key and one Curator provider binding.
It does not need to duplicate Curator storage state or decide whether a row is
good enough to display. A nullable `media_asset_id` bridge allows compatibility
while `media_id` remains authoritative.

The conversion command may inspect database relationships and perform an
existence-only storage check for reporting. Its mutations are database-only:
it never reads file bytes or writes storage. One database transaction is
appropriate for the current 15-row and 403-row shapes.

## Conversion resolution rules

1. Existing attachment with an existing Media row wins; bridge it to that
   row's asset and repair/report a stale owner path.
2. No attachment plus one exact path match creates the numeric attachment and
   bridge.
3. Duplicate path matches are reported and never guessed.
4. Settings keys are filled only for one exact path match.
5. Every Curator row receives an asset and binding regardless of file
   existence or metadata quality.
6. Valid unique keys are reused. Missing, malformed or duplicated keys are
   replaced with newly generated ULIDs.
7. Reruns reuse the binding/asset and repair incomplete bridges instead of
   creating duplicates.

## Complexity deliberately removed

No provider interface hierarchy is required for one Curator provider. A
provider string plus model relation is sufficient. No status enums, folders,
storage policy, asset scope, schema state machine, conversion planner,
manifest, canonical JSON, hash proof, normalizer, quarantine controller,
public asset route or asset policy belongs in Package 1.

## Risks

- Duplicate legacy paths require explicit unresolved reporting.
- A pre-existing Curator key may be malformed or duplicated in production-
  shaped fixtures even if current schema normally enforces uniqueness.
- Production remains pre-G1 in dated evidence; real deployment sequencing and
  command execution require later exact production approval.
- Package 1 does not repair the current gallery/picker exclusion behavior;
  Package 2 owns that observable change.
