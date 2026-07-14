# SP3B Settings Subject Pages Research

Date: 2026-07-14

## Contract and preflight

This session executes only
`prompts/pre-13-prompts/settings-sp3b-codex-prompt.md`, prompt version v3
dated 2026-07-14. Kickoff corrections are `none`.

Preflight found a completely clean `main` checkout at `30be5e5`. Required
history is present: SP3A implementation `88fdda2`, SP3A hash backfill
`27c4d41`, MAIL2 implementation `650b631`, and MAIL2 hash backfill `e076e95`.
SP3B had not started. The ledger still names MAIL2 as complete and awaiting
review; this kickoff explicitly selects SP3B as the single run.

## Installed-version research

Laravel Boost `application_info` reported PHP 8.4, Laravel 13.19.0, Filament
5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2. Boost documentation
search covered SettingsPage save lifecycle, custom-page navigation and
authorization, Spatie settings refresh/save behavior, Livewire component tests,
and dehydrated form state. Useful results confirmed schema visibility testing,
custom action authorization responsibility, and the distinction between
dehydrated fields and validation. Results for the exact Spatie save lifecycle
were incomplete, so installed vendor source is authoritative.

Installed source findings:

- Filament's installed `SettingsPage::save()` runs `canEdit()`, transaction,
  before/after validation hooks, `form->getState()`, mutate-before-save,
  before/after save hooks, one settings `fill()`/`save()`, `rememberData()`,
  notification, and optional redirect.
- Spatie Settings 3.9.0 `refresh()` clears cached locked properties, marks the
  settings object unloaded, and reloads current repository values into the same
  object.
- The current monolith copied the Filament lifecycle to add profiling and
  measurement refusal, but persisted the mounted whole form state without a
  fresh owned-path merge.
- `MultiTranscriptionSurfaces::overlayUnauthorizedSettings()` currently reads
  stored settings internally. SP3B needs an optional stored-snapshot argument
  while leaving every existing caller's behavior unchanged.
- `PublicFrontConfigValidator::validateGroups()` already supports validating
  selected owned groups without touching legacy-invalid siblings.
- `SettingsImportLockSurfaceRegistry` already maps section and six approved
  important-field surfaces to unchanged lifecycle unit paths.

FilamentExamples exposed `search_examples` only. Initial searches covered
custom/settings pages, navigation groups, authorization, section actions, and
loading/performance. Refined searches covered multiple account-settings pages,
page-owned forms, explicit save/notification flows, old-route redirects, and
SettingsPage form state. Relevant snippets were Account Settings Cluster Pages,
Custom Edit Profile Page With Two Forms, and simple custom settings pages. The
useful patterns are small page-owned schemas, explicit state paths, direct save
authorization, and shared notification conventions. Cluster machinery,
ad-hoc Eloquent storage, and inline-style custom forms are not adopted.

## Skill guidance applied

The Laravel, Livewire, Pest, and Spatie PHP skills require existing-project
conventions, server-side authorization and allowlisting, focused behavior
tests, explicit return types, early returns, and no duplicated state ownership.
The Laravel caching reference generically suggests locks for races, but the
higher-priority SP3B contract explicitly forbids database, advisory, and cache
locks and `lockForUpdate()`. SP3B therefore proves only sequential stale-page
preservation and makes no simultaneous-write claim.

## Existing architecture and constraints

- `PublicContentSettings` contains exactly 37 public non-static properties: 23
  scalars and 14 arrays in the single `public_content` storage group.
- The monolith has nine Tabs panels and 293 component construction sites.
  Advanced owns the heavy nine-template/54-part Builder surface.
- `ManagePublicForms` already has a dedicated route and schema but inherits the
  unsafe whole-object SettingsPage save path.
- `AdminUxSettings` persists `admin_ux` separately and remains outside the
  lifecycle/import/lock contract.
- Import, restore, selected merge, normalize, backup, and import-lock writers
  already received SP3A authorization overlays and must remain unchanged.
- The committed SP3A measurement fixture is 37,982 bytes and lifecycle units
  serialize to SHA-256
  `61e551a60016b1ac0c9aa8051463818adf31677bea465ac0e9b269fe3d2386b8`.
- No installed general Filament `->deferred()` exists; collapsed Builder or
  Repeater rows still construct/render state. SP3B uses page separation only.
- Subject pages must not use Livewire islands under any result.

## Direct caller and test inventory

Application callers requiring change:

| Caller | Current dependency | SP3B disposition |
|---|---|---|
| `AdminNavigationOrder` | monolith, forms, Admin UX, backups all under Site Management | Retarget to the frozen Settings group/order; retain specified operational Site Management entries. |
| `ContentGroupForm` default-images hint | monolith Homepage tab URL | Retarget to `DisplaySettings::getUrl()`. |
| `PublicContentSettings` old route | nine-tab form and `public-content-tab` state | Replace with hidden authorized compatibility redirect; no form construction. |

Direct page/tab tests requiring retargeting or new coverage:

| File | Direct coverage | Disposition |
|---|---|---|
| `SettingsSp3aTest.php` | fixture, lifecycle, locks, imports | Keep lifecycle/import tests; add subject-page measurement and unchanged SHA coverage in SP3B tests. |
| `AdminPhase02ResourcesTest.php` | navigation registry, monolith mount/tab state, settings save | Retarget navigation and route access; replace monolith workflow with subject-page coverage. |
| `SettingsImportExportTest.php` | monolith save preservation and inline lock component paths | Retarget only direct save/lock UI cases to owner pages; keep import/export domain tests unchanged. |
| `SettingsPageProfilerTest.php` | monolith profiler phases and measurement refusal | Retarget to shared subject base and representative pages. |
| `RolesGatesTest.php` | monolith policy/template save overlay | Retarget to Display and Card Templates owner pages. |
| `PublicFrontJsonSettingsArchitectureTest.php` | monolith schema/save fields | Retarget only page schema/save cases by owner; validator/rendering tests stay put. |
| `PublicFrontCardTemplateBuilderTest.php` | monolith Advanced editor workflows | Retarget to temporary Card Templates page; rendering/presenter tests stay put. |
| `PublicFormsSubmissionsTest.php` | monolith sibling preservation plus forms page | Retarget forms editing to upgraded Manage Public Forms and cross-owner preservation to SP3B tests. |
| `PublicMaintenanceModeTest.php` | settings persistence helpers and maintenance behavior | Keep behavior tests; add/retarget direct page save/lock cases to Maintenance where present. |
| `PublicTranscriptionPolicyTest.php` | policy service behavior | Keep unchanged except any direct page workflow moves to Display. |
| `SingleTranscriptionLensTest.php` | mode/policy behavior | Keep unchanged; SP3B page save guard is separately covered. |
| `SettingsBackupsTest.php`, `SettingsBackupSnapshotsTest.php`, `PublicContentSettingsNormalizeCommandTest.php` | operational writer regressions | Keep unchanged; these paths are explicitly not rewritten. |
| `PublicMenuHeaderUxFixesTest.php` | monolith menu/header form workflow | Retarget to Menu/Header. |
| `PublicDefaultImagesSettingsTest.php` | monolith default-image form workflow | Retarget to Display. |
| `PublicFrontCustomColorsTest.php` | monolith color field workflows | Retarget each direct field assertion to its owning page. |
| `PublicAboutPageContentTeamTest.php` | monolith About form and upload state | Retarget to About. |
| `PublicFrontIconRegistryTest.php` | monolith icon fields | Retarget direct schema assertions to owning pages/Templates. |
| `ImageMediaCuratorTest.php` | monolith menu logo/media picker save | Retarget to Menu/Header. |

Tests that only resolve `App\Settings\PublicContentSettings`, its readers,
validators, render contexts, public pages, import/export services, or settings
helpers are not mechanically moved. Historical docs and blueprints are not
rewritten merely because they describe the monolith at their original time.

## Save-integrity decision

The fresh page save boundary will use one resolved settings object, call
`refresh()` immediately before taking `toArray()`, preserve omitted protected
owned fields from that snapshot, validate only owned groups, overlay only
registry-owned roots/scalars, run the authorization overlay against the exact
same snapshot, fill the complete guarded candidate, and call `save()` exactly
once. Non-owned integrity is decoded equality plus per-property canonical JSON
equality. No database JSON byte/timestamp promise is made. Lifecycle JSON byte
identity remains an independent frozen regression.

## Measurement direction

The SP3A fixture stays unchanged and is overlaid only onto roots owned by the
current page. A new keyed provider supplies four separate local-only stress
canaries: item-page, menu-header, about, and public-forms. It is accepted only
with `sp3a_measure=1`, is save-refused, and never replaces comparable baseline
rows. The browser script will report page/subject, fixture identity, and owned
section counts instead of tab panels.

## No-conflict conclusion

The v3 prompt, repository rules, installed source, and current SP3A baseline
are compatible. The prompt explicitly resolves the earlier review report's
template-concurrency recommendation by limiting SP3B to sequential disjoint
owner saves and deferring simultaneous or same-owner conflict handling.
