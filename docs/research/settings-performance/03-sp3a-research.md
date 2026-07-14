# SP3A Settings Foundation Research

Date: 2026-07-14

## Contract and preflight

This session executes only `prompts/pre-13-prompts/settings-sp3a-codex-prompt.md`, prompt version v1 dated 2026-07-14. The kickoff corrections are `none`.

Preflight found a clean `main` checkout with LENS1 at commit `2299c71` and the SP3 review report already committed at `b39eaf7`. The superseded v3 prompt named by SP3A is absent from both the working tree and Git history, so Job 0 will add an archive tombstone instead of claiming that an unavailable file was moved.

Installed-version research through Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2. Boost documentation confirmed Filament's searchable Select, preload, option limit, and server-side search behavior. FilamentExamples exposes search/snippet access only; no source/read/detail tool is available. Initial searches covered settings pages, dependent selects, relationship selects, and page-owned actions.

The refined search batch covered async relationship search, `getSearchResultsUsing`, bounded enum selects, settings action authorization, and select-loading performance. The useful Repair Salon CRM example uses a constrained `getSearchResultsUsing()` query with `limit(50)` plus a selected-label resolver. SP3A adapts that pattern for homepage content-item selection and transcriber option forms. Search-only snippets remain the tool's limit; no deeper source endpoint was available.

## Existing performance evidence

The review report recorded the current worst-case page shape:

| Metric | Observed baseline |
|---|---:|
| Stored settings payload | approximately 37 KB |
| DOM elements | 40,765 |
| Live DOM source | 13,803,472 characters |
| Form inputs | 306 |
| Buttons | 2,975 |
| Alpine roots | 3,787 |
| Livewire roots | 6 |
| Listener estimate | approximately 26,970 |
| TTFB | 2.551 seconds |
| Encoded transfer | 728,936 bytes |
| DOMContentLoaded | 3.926 seconds |
| Load | 5.386 seconds |
| JavaScript heap | approximately 123 MB |
| Total queries | 192 |
| Duplicate lifecycle loads | 182 |

SP2 already reduced `form.total_build` from about 1.3 seconds to 71–83 ms by memoizing per-semantic-path lookup in the page. The remaining repeated work belongs in `SettingsLifecycleSchema`, which still re-derives the same full unit set whenever `units()`, `unitFor()`, `unitsByPath()`, or `unitPathsForSemanticPath()` is called.

## Lifecycle findings

- `SettingsLifecycleGroups` registers only `App\Settings\PublicContentSettings`.
- `App\Settings\AdminUxSettings` is not part of lifecycle import/export or import locking. Its `transcription_mode` therefore must not be added to the lock-surface registry in SP3A.
- Lifecycle units are the canonical import/export and stale-path registry. They must remain byte-identical.
- A request can inspect current, imported, and merged payloads. Memoization therefore needs both group identity and a canonical payload hash.
- `SettingsLifecycleSchema` is currently transient. Registering it as scoped makes its memo tables request-bound while keeping console/test scopes isolated.
- Repository-backed current payload reads also need a group-keyed scoped cache. Explicit payloads must never reuse the current-payload cache.

## Lock findings

`SettingsImportLocks` stores lifecycle unit paths. The settings page currently decorates every field that resolves to one lifecycle unit, including repeated template parts and nested builder rows. `ManageSettingsImportLocks` likewise exposes all lifecycle units.

A separate visible registry can map a small set of surfaces to the unchanged units:

- one surface per top-level section, mapping to every lifecycle unit in that section;
- six approved `PublicContentSettings` fields, mapping to their existing lifecycle units;
- no `AdminUxSettings.transcription_mode` surface because that settings class is not lifecycle-registered.

Legacy stored locks remain valid underlying paths. A path not represented by an approved field surface, and not covered by a fully locked section, is reported as a retired visible lock. It remains enforced and is not fatal or silently deleted.

## External settings write paths

Four externally influenced write paths exist:

1. the settings import page calls `SettingsBackupManager::import()`;
2. backup restore calls `SettingsBackupManager::restore()`;
3. selected lifecycle replace/merge flows through `applySelectedPayload()` and then the manager's payload writer;
4. `settings:normalize-public-content --apply` writes normalized settings directly.

The first three have an acting admin user and can share a central overlay at the manager's final write boundary. The normalization command has no acting user; the safe behavior is an anonymous overlay, preserving all gated transcription-policy values and protected card-template parts while applying the remaining normalization.

## Select inventory findings

The inventory found 211 construction/reference lines under `app/Filament`, covering page fields, resource fields, relationship filters, shared Select factories, icon selects, and Spatie tag inputs. No current Select uses `optionsLimit()`.

Static arrays and enums dominate `PublicContentSettings`; nearly all have 2–10 choices. Relationship-backed selects and filters are frequently both searchable and preloaded, which eagerly loads growing tables. Settings-derived form keys and template keys are computed sets. `PublicFrontCardTemplateResolver::optionsForFamily()` currently recalculates through its settings render context and needs request-scoped per-family memoization. `PublicFrontIconRegistry` already memoizes its static source, but its large icon set should remain searchable without preload and use a limit.

Schema inspection found indexed foreign keys and type/status discriminator columns for relationship constraints. Human label columns such as content title, group title, category name, and author name are not indexed, and Filament's default contains search cannot use an ordinary B-tree label index. SP3A will keep those queries server-side, constrained, and capped; no schema migration is justified without measured search latency. The plan records this explicitly instead of claiming an index that does not exist.

## Measurement harness direction

The committed fixture will be deterministic and in-memory: nine card-template families with six heavy parts each and enough representative route/menu/about configuration to produce the reviewed approximately 37 KB payload. A local-only, query-flagged measurement mode will overlay this throwaway payload on the form state and refuse saving. It will never mutate real settings.

A dev-gated response middleware will report uncompressed response bytes and decomposed query counters only when the local measurement flag is present. The browser protocol will use a fixed viewport, five warm visits and one cold visit, with profiler off and on, and collect navigation timing, encoded bytes, DOM count, listener estimate, heap, and tab-panel counts. The reviewed baseline above is the immutable comparison point for SP3B–D; the new lifecycle counter must show zero duplicate derivations for the same group/payload after memoization.
