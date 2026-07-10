# Step 10R-S1b MCP Research

## Scope

Step 10R-S1b adds persistent settings import locks and the add-only import mode on
top of the S1a settings lifecycle wizard. This run also includes the requested S1a
audit corrections for overlay drift assertions, import result counts, upload
validation, schema dot-segment guards, and the route/card segmentation refinement.

## Laravel Boost

Boost was available and returned installed-version context for this application:
Laravel 13.18, Filament 5.6, Livewire 4.3, Pest 4.7, Horizon 5.47, PHP 8.4.

Queries run:

- Livewire file uploads and Laravel `mimetypes` validation.
- Filament custom pages and header actions for launching hidden admin workflows.
- Livewire checkbox/array state for selection tables.
- Laravel `data_get`, `data_set`, `Arr::has`, and dot-notation behavior.
- Spatie settings save/transaction behavior.

Findings:

- Livewire uploads are validated with normal Laravel file rules. Laravel
  `mimetypes` checks the guessed file MIME type, so the S1b upload rule should add
  `mimetypes:application/json,text/plain` beside the existing required/file/max
  rules.
- `data_get`, `data_set`, and `Arr::has` interpret `.` as path separators. Any
  schema-derived unit segment containing a literal dot is unsafe and must be rejected
  before locks or imports persist it.
- Hidden Filament pages remain the cleanest route target for a reusable Livewire
  manager launched by table header actions.
- Spatie settings writes should keep using the current settings class and validation
  boundary; the lock setting should be normalized before save just like other public
  JSON settings.

## FilamentExamples

The FilamentExamples MCP was available with search/snippet access only. There was no
source/detail fetch tool exposed in this session.

First-pass searches:

- `matrix toggle table`
- `settings lock manager`
- `selection table toggles`

Refined searches:

- `toggle selection rows Livewire table`
- `lock settings page action`
- `bulk toggle matrix settings`

Useful patterns:

- Use a custom Filament page or page action as a shell for complex Livewire state
  instead of forcing every control into a Filament form schema.
- Matrix-style checkbox tables should keep the state in the Livewire component and
  use small action methods for row/group toggles.
- Header actions that link to hidden pages match the existing PodText import wizard
  approach and avoid modal state complexity.

PodText adaptation:

- Reuse the S1a `SettingsLifecycleSelectionTable` boundary for both import dry-runs
  and lock manager state.
- Keep all selectable paths produced by `SettingsLifecycleSchema`; no view or action
  should enumerate path literals.
- The lock manager can be a hidden Filament page launched from the backups table
  header next to Import.

## Implementation Consequences

- `route_labels` and `card_templates` need schema-owned virtual units because their
  storage is list-based but the requested lock granularity is route key and card
  family. Import/apply code must read and write these units through the schema
  boundary instead of direct `data_get` paths.
- The add-only merge engine should be pure and reusable by the future Importer
  Workbench. It should operate on unit values after locks and server-side allowed
  path intersection have been applied.
- Front-text preset tests must derive expected locks from overlay semantics and prove
  every semantic path maps to exactly one lockable unit.
