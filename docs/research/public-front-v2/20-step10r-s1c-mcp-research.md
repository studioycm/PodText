# Public Front v2 Step 10R-S1c MCP Research

Date: 10/07/2026

## Scope

Step 10R-S1c surfaces existing settings import locks inline on the Public Content
Settings page and completes the S1b/HF2 audit corrections from the active prompt.

The Importer Workbench, MP1, P2/P3, AX, SL, B4, C2, 9F, Step 11, and Prompt 13 were
not started.

## Local Repository Evidence

- UX3 is complete as `0f3aed6 feat: add hebrew smart slugs and key contract alignment`.
- S1b is complete as `ada29fb feat: add settings import locks and add-only mode`.
- HF2 is complete as `f719d30 fix: bound snapshot index column lengths for mysql`.
- Local runtime DB is MySQL 8 through Herd for production-parity migration checks.
- Tests must run against SQLite `:memory:` only. `phpunit.xml`, `tests/Pest.php`, and
  the base TestCase canary enforce this because the shell may export dev DB variables.

## Laravel Boost Findings

Boost was available during this S1c run. Installed versions were Laravel 13.18.0,
Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and PHP 8.4.

Relevant installed-version findings:

- Filament schema component actions can be mounted from rendered schema-component
  contexts. Section header actions render as schema actions with the section context.
- Field hint actions render as schema actions with the full tab/section/field context.
- Filament tests should use the actual schema context where action mounting is
  version-sensitive.

## FilamentExamples Findings

FilamentExamples was available as `search_examples` search/snippet access only. No
source/read/fetch tool was exposed.

Short query batches covered:

- settings page header actions
- section header actions
- field hint actions
- Livewire settings page action state
- lock/toggle table patterns
- import wizard selection state

Pattern adopted: keep the inline lock controls as schema actions and verify them through
the same rendered action contexts Livewire emits.

## Implementation Notes

- Inline section actions call the existing `SettingsLifecycleSelectionState` group
  toggle behavior and persist through `SettingsImportLocks::save()`.
- Inline field hint actions resolve through
  `SettingsLifecycleSchema::unitPathsForSemanticPath()`. Deep fields only receive an
  action when they resolve to exactly one containing unit.
- D29 is import-only: locks never make fields read-only and never block normal settings
  saves.
- Locks-only settings saves still create a system backup row, but snapshot scheduling is
  skipped when canonical payload-minus-`import_locks` is unchanged.

## Commit hash

Previous completed mini-step UX3: `0f3aed6`.

S1c commit message: `feat: add inline import locks on settings page`.

Final S1c commit hash is reported in the chat final because this document is part of
that commit.

## Local Front Check Report

1. Settings page lock hint: Public Content Settings renders inline lock controls beside
   lockable fields and section headers.
2. Field lock flow: locking `homepage_item_limit` inline persists to the manager and
   makes that dry-run row locked/non-selectable in the import wizard.
3. Section lock flow: locking the homepage section toggles all scalar-group units via
   the same selection service.
4. D29 edit flow: a locked settings field remains editable and saves normally.
5. Locks-only backup flow: a locks-only save creates a system backup row with no
   snapshot rows.
6. Snapshot gallery flow: a done thumbnail row exposes a recapture action.
7. Hebrew RTL/light-dark flow: Hebrew settings page rendering includes RTL direction and
   translated lock-copy; light/dark safety remains through existing Filament theme
   classes.
