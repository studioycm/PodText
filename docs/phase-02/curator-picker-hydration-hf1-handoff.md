# CURATOR-HF1 Handoff — Curator Picker Hydration Repair

Date: 2026-07-15

## Scope

CURATOR-HF1 is an in-between repair immediately after SP3B. It fixes the
Curator media picker state used by registered header/logo paths and Display
custom default-image paths when a settings page is loaded again. It does not
alter SP3B's ownership registry, fresh snapshot, authorization overlay, or
single-save lifecycle.

## Commit hash

`23a6ce9 fix: preserve curator picker selections on reload`

## Requirement classification

- Implemented: hydrate `PathCuratorPicker` from its raw persisted state once,
  before its public state normalization runs.
- Implemented: retain a validated UUID-keyed Curator media-item map as picker
  state so the field renders its existing media selection and its actions keep
  their UUIDs.
- Implemented: regression coverage for a registered Menu/Header logo and a
  registered Display custom default image through save and fresh remount.
- Already existed and preserved: settings persist plain relative paths; a
  legacy protected path without a matching Curator media row remains preserved
  for dehydrate/save rather than being rewritten.
- Already existed and preserved: SP3B's fresh owned-path save contract and
  import, restore, normalization, import-lock, backup, and Admin UX flows.
- Not applicable: database, advisory, cache, or `lockForUpdate()` locks;
  simultaneous-request serialization is outside this repair.
- Deferred: live browser repetition. The in-app browser runtime was unavailable
  (`Cannot redefine property: process`); rendered Livewire regression tests
  cover the same hydrated field output and picker state.

## Root cause and repair

Filament resolves an injected `state` parameter in an `afterStateHydrated`
closure through the component's public `getState()`. `PathCuratorPicker`
already overrides that method to transform a scalar stored path into Curator's
UUID-keyed media-item map. The hydration hook therefore normalized that map a
second time, but Curator's scalar/list input resolver did not recognize a
UUID-keyed map and cleared it to an empty array.

The hook now reads raw persisted state directly. The public-state override also
recognizes a validated UUID-keyed media-item map and returns it unchanged. The
existing protected legacy-path sentinel is checked first, so a path with no
Curator row remains safe for the save lifecycle.

## Files changed

- `app/Filament/Forms/Components/PathCuratorPicker.php`
- `tests/Feature/ImageMediaCuratorTest.php`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/curator-picker-hydration-hf1-handoff.md`

## Tests added

- A Menu/Header regression saves a registered logo path, mounts the settings
  page, saves, then remounts and verifies the rendered filename and UUID-keyed
  Curator selection remain present.
- A Display Settings regression verifies a registered custom Content Item
  default-image path remains rendered and selected after a fresh mount.

## Command record

- `php artisan test tests/Feature/ImageMediaCuratorTest.php` — passed: 15
  tests, 61 assertions.
- `php artisan test tests/Feature/ImageMediaCuratorTest.php tests/Feature/PublicDefaultImagesSettingsTest.php tests/Feature/PublicMenuHeaderUxFixesTest.php tests/Feature/SettingsSp3bTest.php`
  — passed: 51 tests, 452 assertions.
- `git diff --check` — passed.
- `vendor/bin/pint --test` — passed.
- `vendor/bin/filacheck` — passed: 0 issues.
- `npm run build` — passed.
- `php artisan test` — passed on the final code state. The local runner did
  not return Pest's compact summary payload after either full-suite invocation;
  the first result was therefore rerun unchanged, and both completed
  successfully.

## Final gate outcomes

Passed in standing order: requirements sweep (`git diff --check`), Pint,
FilaCheck, production build, then the full Laravel suite last. The separate
uncommitted navigation-menu work was present in the checkout and had already
passed its own focused/full verification; it is deliberately excluded from the
CURATOR-HF1 commits.

## Local Front Check Report

1. Open Admin → Settings → Menu & Header.
2. Select an existing Curator image for the light or dark logo, then save.
3. Reload the page and expect the selected image filename and thumbnail to
   remain in the picker.
4. Open Admin → Settings → Display.
5. Select an existing Curator image for a custom default image, then save.
6. Reload the page and expect that picker to retain the selected image.
7. Open a page containing a protected legacy path that has no Curator media
   record, save an unrelated setting, and expect the legacy path to remain
   stored unchanged.

## Assumptions and deferred work

The repair intentionally accepts only map entries that contain a string UUID
key, a numeric media id, and a non-empty string path. Other state shapes still
follow the existing resolver and normalization paths. No schema, dependency,
or browser-runtime change is included.
