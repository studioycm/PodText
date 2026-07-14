# SP3A Settings Foundation Implementation Plan

Date: 2026-07-14

## Scope guard

Execute only SP3A. Do not split the page, build a template editor, change tabs, change lifecycle unit semantics, add dependencies, push, or use FilaCheck auto-fix.

## 1. Measurement protocol

1. Add a committed `SettingsSp3aMeasurementFixture` that produces the same deterministic nine-template heavy payload on every run and assert its approximate byte size in tests.
2. Activate the fixture only for an authenticated local request carrying the explicit SP3A query flag. Overlay it in page state after the real read so repository query behavior is still measured, and reject save attempts while measurement mode is active.
3. Add a local-only response middleware that, only under the flag, records uncompressed response bytes, total SQL queries, settings-repository reads, lifecycle derivation count, and duplicate lifecycle derivations. Keep it off by default.
4. Add a browser-console script and numbered protocol: fixed viewport; five warm runs plus one cold run; profiler off and on; capture TTFB, DOMContentLoaded, load, encoded bytes, DOM elements, listener estimate, heap, per-panel counts, PHP phases, and decomposed query counts; calculate median and p95.
5. Preserve the review report's observed baseline in the handoff. The harness is a reproducible yardstick, not permission to write the operator's local settings database.

## 2. Lifecycle memoization without unit changes

1. Capture a byte-for-byte lifecycle snapshot fixture before modifying `SettingsLifecycleSchema`.
2. Register `SettingsLifecycleSchema` as scoped.
3. Add canonical payload hashing and group-aware memo tables inside the class for current group payloads, units, and units-by-path. Route `unitFor()`, `unitPaths()`, and semantic-path lookups through the cached unit set.
4. Add request-local counters for fresh derivations and duplicate derivations. A second read of the same key is a cache hit, not another lifecycle load.
5. Test byte identity, memoized-versus-fresh identity, two distinct payloads in one request, and new-container-scope isolation.

## 3. Visible lock-surface registry

Add `SettingsImportLockSurfaceRegistry` with section surfaces and approved field surfaces. Each surface resolves to one or more current lifecycle paths; storage remains lifecycle paths.

Approved field surfaces:

| Visible field surface | Existing unit mapping |
|---|---|
| `maintenance.enabled` | exact lifecycle unit |
| `maintenance.raw_html_override` | exact lifecycle unit |
| `public_forms.require_email_verification` | exact lifecycle unit |
| `transcription_policy.public_mode` | exact lifecycle unit |
| `transcription_policy.count_mode` | exact lifecycle unit |
| `transcription_policy.show_multiple_transcriptions_on_item_page` | exact lifecycle unit |
| `AdminUxSettings.transcription_mode` | Not applicable: Admin UX settings are not lifecycle registered or externally importable |

Page section actions and the lock manager will consume only these surfaces. Inline traversal will decorate only an exact approved field match; repeaters, builders, template rows, and parts receive no individual decoration. Existing stored unit locks outside the visible registry stay enforced and appear in a retired-lock report.

## 4. Import/restore authorization decisions

| External write path | Acting identity | Decision | Reason |
|---|---|---|---|
| Settings import page | authenticated importing user | Apply `MultiTranscriptionSurfaces::overlayUnauthorizedSettings()` at the manager's final `PublicContentSettings` write | Admins may import ordinary settings while gated paths and protected template parts stay byte-identical; super-admin in multi mode receives the full payload. |
| Backup restore action | authenticated restoring user | Pass the user to the same final-write overlay | Restore is an external payload write and must have the same authorization contract as import. |
| Lifecycle selected replace/merge | authenticated importing user | Merge/replace first, then overlay the complete candidate immediately before save | Protects forged selected paths and avoids divergent protection between replace and merge. Import locks are still applied before authorization overlay. |
| `settings:normalize-public-content --apply` | no acting user | Apply an anonymous overlay immediately before persistence | The command may normalize ordinary settings, but cannot silently change super-admin-gated values or guarded card-template parts without an authorized identity. |
| `AdminUxSettings.transcription_mode` | no external path | No overlay needed in SP3A | `AdminUxSettings` is absent from `SettingsLifecycleGroups`; packages, backups, selected lifecycle merge, and normalization do not import it. Its existing page save overlay remains authoritative. |

Tests will cover forged import and restore payloads for admin and super-admin/multi-mode users, ordinary-field application, and the command's preservation behavior.

## 5. Complete Select classification

`Preload` below means bounded choices are included on mount. `Async 50` means searchable, not preloaded, with a server-side result cap of 50. Tiny sets (10 or fewer) are plain selects without search.

| Location / logical selects | Options source | Size | Current state | SP3A action |
|---|---|---|---|---|
| `AdminUxSettings` four mode/strategy/container fields | static arrays | bounded/tiny | plain, not preloaded | Preload; keep plain. |
| `PublicContentSettings` homepage, display, transcription, item-page, menu mode, podcasts, contributors, about, maintenance placement, template family/layout/density/image/title, builder part style/layout fields | static arrays | bounded/tiny | mostly plain, not preloaded; a few searchable | Preload all; remove search from tiny sets. |
| `PublicContentSettings` menu/search route keys and route-label keys | static route registry | bounded (under 20) | mixed plain/searchable | Preload; remove search where 10 or fewer, otherwise bounded search is allowed only if the registry exceeds 10. |
| `PublicContentSettings` menu/about/maintenance form keys | settings-derived public-form registry | growing | searchable and preloaded | Async 50; no preload. |
| `PublicContentSettings` card-template keys | computed service (`PublicFrontCardTemplateResolver`) | settings-derived/growing | closure, mixed preload/search | Async 50; memoize resolver options once per family per scoped request. |
| `IconSelect` usages in public settings | computed static Heroicon registry | large bounded registry | searchable, no explicit limit | Keep async; no preload; limit 50. Existing registry case/label memoization remains. |
| `ImporterSettings` provider/auth/status filters and provider/auth form fields | enums/static arrays | bounded/tiny | plain, not preloaded | Preload; plain. |
| `ContentImageActions.media_naming_strategy` | static array | tiny | plain | Preload; plain. |
| `PublicFormsSettingsForm` display mode, email verification, validation semantics | enums/static arrays | tiny | plain | Preload; plain. |
| `ConfiguresContentImports` mode, relation mode, blank-update behavior | static arrays | tiny | plain | Preload; plain. |
| `ContentItemExporter.tag_scope` | static array | tiny | plain | Preload; plain. |
| `CategoryForm.parent_id` | Category relationship | growing | searchable + preload | Async 50; constrain by existing parent query. Label search is capped; no unsupported index claim. |
| `RelationshipOptionForms.original_language_code` | static language registry | bounded | searchable + preload | Preload; retain search only if registry remains above 10. |
| `PublicationStatusSelect` all usages | enum | tiny (3) | plain | Preload; plain in shared factory. |
| `ContentGroupForm` language | static language registry | bounded | searchable + preload | Preload; retain search only if above 10. |
| `ContentGroupForm.categories` and category table filters | Category relationship | growing | searchable + preload | Async 50; no preload; capped constrained relationship query. |
| `HomepageSectionForm` type/source/sort/direction/style/routes/display/template overrides/pagination | enums/static arrays | bounded/tiny | several searchable, mixed preload | Preload; remove tiny search. |
| `HomepageSectionForm` category, tag, group targets | relationships | growing | searchable + preload | Async 50; no preload; keep type/status constraints and cap. |
| `HomepageSectionForm` include/exclude content items | computed Eloquent closure | growing | eager closure loads up to 100 + preload | Replace with true async server search, no preload, cap 50, selected-label resolver. |
| `HomepageSectionForm.button_form_key` | settings-derived public forms | growing | searchable + preload | Async 50; no preload. |
| `HomepageSectionForm.template_key` | computed template resolver | settings-derived/growing | closure + preload | Async 50; no preload; scoped resolver memoization. |
| `PublicFormSubmissionForm.status` and submission status filter | enum/static | tiny | plain | Preload; plain. |
| `TranscriptionForm.content_item_id`, workspace existing transcription | ContentItem/Transcription relationship | growing | searchable + preload | Async 50; no preload; cap and preserve relationship constraints. |
| Transcriber selectors in forms, table actions, and relation managers | Author relationship helpers | growing | searchable + preload | Async 50; remove preload in shared helper and cap. |
| Content group selectors in content item/workspace forms and tables | ContentGroup relationship | growing | searchable + preload | Async 50; no preload; cap. |
| Category selectors in content item/workspace forms and tables | Category relationship | growing | searchable + preload | Async 50; no preload; cap. |
| `SpatieTagsInput` in item/workspace forms | plugin-managed typed Tag query | growing | plugin async search; no explicit preload | Keep plugin-managed async behavior and typed-tag scope; no extra preload. |
| `ContentItemForm.featured_transcription_id` | record-local relationship | bounded by one item's transcriptions, potentially growing | searchable + preload | Async 50 to avoid eager history growth; constrain to current item. |
| Content item/transcription table status, featured state, language, embed-provider filters | enum/static arrays | bounded/tiny | plain/mixed | Preload; plain for tiny sets. |
| Content item/transcription table author/group/category/tag filters | relationships | growing | searchable + preload | Async 50; no preload; keep role/type/status constraints and cap. |
| Settings backup source filter | enum/static | tiny | plain | Preload; plain. |
| User form role and role filter | enum/static | tiny | plain | Preload; plain. |

The implementation sweep will re-run the construction inventory and verify every direct `Select`, `SelectFilter`, `IconSelect`, `PublicationStatusSelect`, and `SpatieTagsInput` is covered by either a shared factory decision or an explicit local decision.

## 6. Documentation and gates

Patch the durable select rule in `AGENTS.md` and `.ai/guidelines/settings-dashboard.md`. Add en/he translation keys for new measurement and legacy-lock copy. Update current state, ledger, and the SP3A handoff.

Final order on the final code state: requirements sweep, Pint, FilaCheck, build, then the full Pest suite last. Commit implementation with `perf: add settings measurement protocol, lock surface, and import overlay`, then immediately make the docs-only hash backfill commit. Do not push.
