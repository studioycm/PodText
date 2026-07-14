# SP3B Settings Subject Pages Implementation Plan

Date: 2026-07-14

## Scope guard

Execute only SP3B v3. Do not add migrations, dependencies, islands, locks,
`lockForUpdate()`, new caches, storage groups, lifecycle-unit changes,
template-editor redesign, or changes to import, restore, normalize,
import-lock, backup, and Admin UX persistence. Preserve the SP3A fixture and
lifecycle SHA regression byte-for-byte. Do not push.

## Commands

No scaffold command is required: all pages use the existing
`PublicContentSettings` class/storage and existing schemas are moved without
generation. Before implementation run the exact prompt baseline. During
iteration run focused Pest files, `vendor/bin/pint --dirty`, and
`vendor/bin/filacheck --dirty` without `--fix`. Final order is requirements
sweep, Pint test, full FilaCheck, build, then full Pest suite last.

## Models and storage

No model, migration, settings migration, or property change. Continue using
`App\Settings\PublicContentSettings` group `public_content`. Continue using
`App\Settings\AdminUxSettings` group `admin_ux` unchanged.

## Frozen navigation map

All visible settings-family entries use `UsesAdminNavigationOrder` and a new
`AdminNavigationOrder::SETTINGS` group, positioned after Taxonomy and before
Site Management. Labels are bilingual translation keys and icons are
`Filament\Support\Icons\Heroicon` enum values.

| Page/resource class | Stable slug | Label key | Heroicon | Sort | Visible | Settings ownership |
|---|---|---|---|---:|---|---|
| `HomepageSettings` | `settings/homepage` | `admin.pages.homepage_settings.navigation` | `OutlinedHome` | 300 | yes | Homepage Settings |
| `DisplaySettings` | `settings/display` | `admin.pages.display_settings.navigation` | `OutlinedSwatch` | 310 | yes | Display Settings |
| `EpisodePageSettings` | `settings/episode-page` | `admin.pages.episode_page_settings.navigation` | `OutlinedDocumentText` | 320 | yes | Episode Page Settings |
| `MenuHeaderSettings` | `settings/menu-header` | `admin.pages.menu_header_settings.navigation` | `OutlinedBars3` | 330 | yes | Menu/Header Settings |
| `PodcastSettings` | `settings/podcasts` | `admin.pages.podcast_settings.navigation` | `OutlinedMicrophone` | 340 | yes | Podcast Settings |
| `ContributorSettings` | `settings/contributors` | `admin.pages.contributor_settings.navigation` | `OutlinedUserGroup` | 350 | yes | Contributor Settings |
| `AboutSettings` | `settings/about` | `admin.pages.about_settings.navigation` | `OutlinedInformationCircle` | 360 | yes | About Settings |
| `MaintenanceSettings` | `settings/maintenance` | `admin.pages.maintenance_settings.navigation` | `OutlinedWrenchScrewdriver` | 370 | yes | Maintenance Settings |
| existing `ManagePublicForms` | retain current slug | existing key | existing `OutlinedClipboardDocumentList` | 380 | yes | Manage Public Forms |
| `CardTemplateSettings` | `settings/card-templates` | `admin.pages.card_template_settings.navigation` | `OutlinedRectangleStack` | 390 | yes | Card Templates |
| existing `AdminUxSettings` | retain current slug | existing key | existing enum | 400 | yes | separate `admin_ux` |
| existing `SettingsBackupResource` | retain resource URL | existing key | existing enum | 410 | yes | operational backups |
| `ImportPublicSettings` | retain slug | existing key | existing enum | hidden | no | existing import writer |
| `ManageSettingsImportLocks` | retain slug | existing key | existing enum | hidden | no | existing lock writer |

`HomepageSectionResource`, `ImporterSettings`, `AdminTools`,
`SpotifyLinksFetcher`, and `UserResource` remain in Site Management. The panel
group list becomes Content Management, Taxonomy, Settings, Site Management.

## Frozen ownership/classification registry

Create one authoritative registry. Editable rows provide owned properties,
validator groups, schema class, lock surfaces, and profiler subject. Operational
rows are classifications only. The 37 public non-static properties are
classified exactly once.

| Owner/classification | Exact properties | Validator groups |
|---|---|---|
| Homepage Settings | `homepage_item_limit`, `pinned_item_limit`, `show_latest_section` | none |
| Display Settings | `default_public_sort`, `default_result_layout`, `homepage_card_image_size`, `homepage_card_density`, `homepage_card_title_size`, `homepage_card_image_fit`, `homepage_card_image_radius`, `homepage_show_group_badge`, `homepage_group_badge_mode`, `homepage_group_title_separator`, `homepage_group_badge_duplicate_thumbnail`, `homepage_show_authors`, `homepage_show_categories`, `homepage_show_tags`, `homepage_show_duration`, `homepage_show_effective_date`, `homepage_show_description`, `homepage_description_lines`, `homepage_cards_per_page`, `display_defaults`, `default_images`, `transcription_policy` | `display_defaults`, `default_images`, `transcription_policy` |
| Episode Page Settings | `item_page_layout`, `item_page` | `item_page` |
| Menu/Header Settings | `menu_config`, `route_labels` | `menu_config`, `route_labels` |
| Podcast Settings | `podcasts_page` | `podcasts_page` |
| Contributor Settings | `contributors_page` | `contributors_page` |
| About Settings | `about_page` | `about_page` |
| Maintenance Settings | `maintenance` | `maintenance` |
| Manage Public Forms | `public_forms` | `public_forms` |
| temporary Card Templates | `card_templates` | `card_templates` |
| lifecycle service writer, no subject-page editor | `import_locks` | operational only |
| intentionally non-editable consumer configuration | `settings_backups` | operational only |

An independent reflection test obtains property names directly from
`ReflectionClass(PublicContentSettings::class)`, not from registry helpers, and
compares them with flattened metadata for completeness and uniqueness. A
separate schema-isolation test enumerates dehydrated top-level roots for every
editable page and compares them with registry ownership, proving all owned and
no foreign properties are present.

## Frozen writer-scope and lifecycle map

| Writer | SP3B contract |
|---|---|
| Eight subject pages, Card Templates, Manage Public Forms | Shared fresh owned-path page lifecycle. |
| Import, restore, selected replace/add-only merge | Existing SP3A manager path unchanged. |
| Normalize command | Existing anonymous SP3A overlay path unchanged. |
| `SettingsImportLocks` | Existing service writer unchanged. |
| Backup create/restore/snapshots | Existing lifecycle path unchanged. |
| Admin UX persistence | Existing separate SettingsPage path unchanged. |

An ordinary subject save calls `PublicContentSettings::save()` exactly once and
does not dispatch events, create backups, clear caches, or invalidate contexts
manually. Existing `SettingsSaved` subscribers remain authoritative. Non-owned
integrity is decoded equality plus per-property canonical JSON identity;
physical DB JSON bytes/timestamps are outside the claim. Lifecycle-unit
serialization retains literal byte identity and the frozen SHA.

## Shared page lifecycle

Add a small abstract page/concern that extends the installed Filament Settings
page lifecycle without domain fields.

Authorization:

- `canAccess()` and `canEdit()` require an authenticated `App\Models\User`
  whose role is at least `UserRole::Admin`.
- `save()` repeats authorization first and aborts 403 before reading form state.
- Measurement mode refuses save.

Initial fill:

1. Resolve settings once and read `toArray()`.
2. Extract only registry-owned properties.
3. Apply only the owner schema's explicit fill transform.
4. In local measurement mode overlay only owned roots from the unchanged SP3A
   fixture, then optionally apply an allowed keyed subject canary.
5. Fill only the focused form and profile read/fill/build/render phases with the
   registry profiler subject.

Save order:

1. Re-authorize and reject measurement mode.
2. Preserve installed transaction, hook, Halt/error, notification, redirect,
   `rememberData()`, and profiling behavior.
3. Read only declared form state and allowlist registry-owned roots/scalars.
4. Resolve one canonical settings object, call `refresh()` immediately before
   merging, and take its fresh complete `toArray()` snapshot.
5. Apply owner-specific explicit transforms and omission preservation against
   that snapshot before group validation. Preserve dehydrated/gated fields;
   explicit list deletion stays deletion and no generic recursive merge exists.
6. Validate only registry-owned validator groups through
   `PublicFrontConfigValidator::validateGroups()`.
7. Overlay only owned validated groups/scalars onto that same fresh snapshot.
8. Call `MultiTranscriptionSurfaces::overlayUnauthorizedSettings()` with a new
   optional stored-snapshot argument; old callers omit it and keep SP3A behavior.
9. Fill the complete guarded candidate into the same refreshed settings object
   and call `save()` exactly once.

## Pages and schemas

Create focused schema support classes (or equivalently focused typed factories)
for the nine moved schemas and reuse `PublicFormsSettingsForm` for forms. Each
page declares only stable owner ID, typed schema factory, owned lock-surface
identifiers, and profiler subject. Preserve every existing field component,
validation, helper text, reactivity, upload configuration/normalization,
trusted-HTML marker behavior, Select loading policy, and ROLES1 visibility
macro. Use the existing full namespaces already present, including
`Filament\Forms\Components\*`, `Filament\Schemas\Components\Section`,
`Fieldset`, `Get`, and `Set`; do not add `Tabs` to subject pages.

The shared base owns section/important-field lock action plumbing through
`SettingsImportLockSurfaceRegistry`. Forms exposes only
`public_forms.require_email_verification`; Display exposes the three approved
policy fields; Maintenance exposes `enabled` and `raw_html_override`; Templates
exposes its section only. No per-template/part lock UI is added.

`PublicContentSettings` becomes a hidden authorized redirect page and never
registers/builds a form. Map legacy `public-content-tab` values exactly:
homepage, display, item-page, menu-header, podcasts, contributors, about,
maintenance, advanced→Card Templates; missing/unknown→Homepage. Forward only
truthy/string values from `sp3a_measure`, `sp3a_profile`, and
`sp3b_subject_fixture`. Retarget the ContentGroup default-images hint to
Display.

## Measurement and canaries

Keep `SettingsSp3aMeasurementFixture` byte-identical. Add one
`SettingsSp3bSubjectFixture` keyed provider with only item-page, menu-header,
about, and public-forms payloads. Accept canary values only locally when
`sp3a_measure=1`; ignore unknowns; refuse every measured save. Update the
browser script without removing core metrics: add page/subject, fixture
identity, owned-section element counts, and remove tab-panel reporting.

Record one cold and five warm visits for eight pages, Manage Public Forms, and
Card Templates under baseline; record separate canary rows for four subjects.
Targets are DOM under 3,000 and warm median TTFB under 800 ms for ordinary pages
and forms. Card Templates is exempt and any miss is classified for SP3C/D. Add
no islands or improvised lazy forms.

## Tests

Add `SettingsSp3bTest.php` and retarget only direct monolith/page cases from the
inventory.

- Route authorization dataset: guest, non-admin, admin, super-admin for all ten
  shared-contract pages; direct Livewire save aborts 403 before form-state read.
- Ownership: independent reflection completeness/uniqueness; independent
  schema isolation for every editable owner.
- Integrity: each page changes only owned properties; all non-owned decoded and
  canonical JSON identities persist; explicit list deletion persists;
  undeclared forged roots are ignored; legacy-invalid sibling is unchanged and
  non-blocking; dehydrated protected fields survive.
- Sequential stale pages use distinct Livewire/component application scopes:
  mount A and B, save B, then stale A; both survive. Repeat with settings memo
  and `PublicFrontConfigCache` warm; a fresh settings resolve and
  `PublicFrontConfigReader` both see A+B. Document same-owner last-write-wins;
  add no concurrency/serialization test.
- Gates: admin forged policy/template state equals the fresh snapshot;
  super-admin/multi path remains available.
- Lifecycle: one ordinary save dispatches one `SettingsSaved`; current listener
  behavior produces observable backup/cache/render-context invalidation once,
  with no manual calls.
- Locks: Forms/Display/Maintenance important fields and all section actions work
  at relocated component paths; legacy stored locks and import/restore/normalize
  regressions remain green.
- Compatibility: all nine legacy values, missing, unknown, unauthorized access,
  query whitelist, no old form construction, and internal URL targets.
- Measurement: local-only, save-refused for all measured pages; subject canary
  allowlist; unchanged fixture size/determinism and lifecycle SHA.
- Browser back/history: add a browser test only if the existing stack is
  available and reliable; otherwise retain the required numbered manual check.

## Documentation and completion

Update current state and add the ledger row `SP3B - Settings subject pages and
fresh owned-path saves`. Add the SP3B handoff with full requirement
classification, inventory dispositions, measurements, every command/result,
gate outcomes, assumptions/deferrals, and numbered manual checks. Run the final
gate in standing order on the final file state. Commit implementation as
`perf: split settings into subject pages with fresh owned-path saves`, then
immediately commit only handoff/ledger hash stamps as
`docs: backfill settings sp3b hash`. Do not push.
