# Settings SP3A Handoff

> **Historical shipped-state notice — 2026-07-16:** This remains authoritative
> for SP3A measurement, locks, and lifecycle behavior. Forward Template/Form
> ownership and final SP3D gating now follow ARCH1/E1–E4 in
> `docs/research/settings-performance/07-sp3d-pre-research.md`.

Date: 2026-07-14

## Scope

Executed only `prompts/pre-13-prompts/settings-sp3a-codex-prompt.md`, prompt version v1 (2026-07-14). Kickoff corrections were `none`. No Composer or npm dependency changes were made, no production state was touched, and nothing was pushed.

## Commit hash

`88fdda2 perf: add settings measurement protocol, lock surface, and import overlay`

## Requirement classification

- Implemented: deterministic nine-template/54-part measurement fixture (37,982 bytes); local-only query-gated response metrics; browser metric script and fixed protocol; request-scoped lifecycle schema with group-and-canonical-payload memo keys and counters; byte-identity regression; section plus approved-field lock surfaces; legacy-lock reporting/preservation; acting-user import/restore/merge overlay; anonymous normalize overlay; full Select classification and loading sweep; request-scoped template-option memoization; durable guidance; bilingual copy; focused tests; state, ledger, research, plan, and handoff docs.
- Already existed: SP1 PHP phase profiler; SP2 page semantic-lookup memo; lifecycle units/import locks; ROLES1 page-save overlay; import reports; role and mode gates; review-report browser baseline.
- Deferred: SP3B page architecture, SP3C template editor/tabs, and SP3D budget enforcement. Label-column indexing/full-text search is deferred until measured search latency justifies a schema change; growing searches are constrained and capped now.
- Not applicable: `AdminUxSettings.transcription_mode` import lock because `SettingsLifecycleGroups` registers only `PublicContentSettings`; dependency updates; migrations; public UI work; production diagnostics.
- Blocked: none.

## Measurement protocol and baseline

The measurement flag is off by default and accepted only locally. `?sp3a_measure=1` overlays the committed fixture after the real settings read and refuses save; it never persists the fixture. Add `&sp3a_profile=1` to enable the existing PHP phase profiler.

The response exposes `X-SP3A-Uncompressed-Bytes`, `X-SP3A-Total-Queries`, `X-SP3A-Settings-Reads`, `X-SP3A-Lifecycle-Derivations`, and `X-SP3A-Duplicate-Lifecycle-Loads`. A read-only unauthenticated local request verified all headers and duplicate loads `0`; authenticated browser measurements remain an operator protocol because this run did not use account credentials.

| Metric | Frozen baseline |
|---|---:|
| Fixture payload | 37,982 bytes |
| DOM elements | 40,765 |
| Live DOM source | 13,803,472 characters |
| Inputs / buttons | 306 / 2,975 |
| Alpine / Livewire roots | 3,787 / 6 |
| Listener estimate | approximately 26,970 |
| TTFB | 2.551 seconds |
| Encoded transfer | 728,936 bytes |
| DOMContentLoaded | 3.926 seconds |
| Load | 5.386 seconds |
| JavaScript heap | approximately 123 MB |
| Total queries | 192 |
| Settings-repository reads | newly reported separately; the review baseline did not isolate this count |
| Duplicate lifecycle loads | 182 before SP3A; 0 after memoization for the same group/payload |
| Largest panel | Advanced: 29,404 elements (72.1%) |

1. Sign in locally and set the browser viewport to 1440 × 1000 CSS pixels.
2. Open `https://podtext.test/admin/public-content-settings?sp3a_measure=1`; expect the throwaway nine-template fixture and no writable measurement state.
3. Open DevTools Network, disable cache only for the cold run, and record the five `X-SP3A-*` response headers.
4. Paste `scripts/settings-sp3a-browser-metrics.js` into DevTools Console and record TTFB, encoded/decoded bytes, DOMContentLoaded, load, DOM elements, listener estimate, heap, and each tab-panel count.
5. Reload once with cache disabled for the cold sample, then enable cache and reload five times for warm samples; run the script after every load.
6. Repeat with `&sp3a_profile=1` and read PHP phases from `storage/logs/settings-profiling.log`.
7. Calculate the five-warm-run median and nearest-rank p95; report the cold sample separately.
8. Confirm the fixture is 37,982 bytes, duplicate lifecycle loads are `0`, and real settings remain unchanged after leaving measurement mode.

## Lifecycle and lock results

- Before and after memoization, lifecycle JSON is exactly 30,413 bytes with SHA-256 `61e551a60016b1ac0c9aa8051463818adf31677bea465ac0e9b269fe3d2386b8`.
- Current, imported, and merged payloads use separate memo entries. A new application scope starts with zero derivations.
- Visible controls are sections plus `maintenance.enabled`, `maintenance.raw_html_override`, `public_forms.require_email_verification`, and the three approved `transcription_policy` fields. Nested template/repeater/builder parts have no individual decoration.
- Stored invisible unit locks remain enforced, are reported, and persist until explicit Unlock all.
- Section surfaces store the same underlying lifecycle paths, preserving import analysis and enforcement.

## Import authorization outcome

| External write | Outcome |
|---|---|
| Settings import | Overlay the final candidate with the authenticated importer immediately before save. |
| Backup restore | Overlay with the authenticated restoring user immediately before save. |
| Selected replace/add-only merge | Merge first, overlay the complete candidate, and exclude authorization-restored paths from applied reporting. |
| Normalize `--apply` | Anonymous overlay preserves gated values/card parts while ordinary normalized groups persist. |
| Admin UX mode | Not externally importable; its existing page-save overlay remains authoritative. |

## Select sweep

The complete options-source/action table is in `docs/research/settings-performance/03-sp3a-implementation-plan.md`. Bounded Selects preload by default; tiny sets are plain. Growing relationship selects/filters are searchable with preload disabled and a 50-result cap. Homepage item selection uses true server search/selected-label resolution. Icon search remains async. Settings-derived form/template choices are non-preloaded, and the scoped template resolver reads its context once per family.

Schema inspection confirmed indexed foreign keys and type/status constraints. Human label columns used by contains search are not indexed; this handoff does not claim otherwise.

## Files changed

- Measurement: fixture, middleware, bootstrap registration, browser script, page measurement state, and SP3A tests.
- Lifecycle/locks: lifecycle schema, lock-surface registry, manager/page/Blade, service-provider scopes, translations, and tests.
- Authorization: backup manager, normalize command, and restore/import/normalize tests.
- Selects: shared defaults, icon/status factories, growing page/resource/filter fields, homepage async options, relationship helpers, and template resolver.
- Docs: `AGENTS.md`, settings guideline, SP3A research/plan, archive tombstone, current state, ledger, and this handoff.

## Tests added or updated

- Added `SettingsSp3aTest` for fixture determinism, lifecycle identity/isolation, lock mapping/report/enforcement, admin restore, super-admin import, bounded/async Select behavior, and once-per-request template options.
- Updated lifecycle lock tests for the reduced visible surface while preserving unit enforcement.
- Added anonymous normalize gated-byte preservation.

## Commands run before final gate

- Preflight: `git status --short --branch`; `git log --oneline -6` (clean; LENS1 present; review report committed).
- Mandatory reads: full repository instructions/lessons, state, ledger, newest handoffs, prompt/version, review, blueprints/checklists, guidelines, code, tests, and vendor source.
- Research: Boost application info/docs/schema/URL; FilamentExamples initial and refined batches (search/snippets only).
- Lifecycle proof: throwaway SQLite before/after serialization returned the same 30,413 bytes, SHA-256, and `BYTE_IDENTICAL`.
- Fixture check: 37,982 bytes after reducing an initial 46,244-byte draft.
- Syntax: `php -l` passed changed/new PHP files.
- Focused tests: the first sandbox run failed only because Pest could not bind its local port and passed when rerun with approved port access. The first SP3A run passed 6/7 and exposed invalid test-only policy tokens; the corrected file passed. A combined settings run exposed four obsolete lock-UI expectations; the updated focused rerun passed 4 tests / 67 assertions. SP3A + normalize passed 12 tests / 99 assertions. Backup + ROLES1 + LENS1 + lifecycle passed 52 tests / 490 assertions.
- Iteration: `vendor/bin/pint app tests config bootstrap --dirty` formatted changed files; `vendor/bin/filacheck --dirty` passed with 0 issues.
- Local route: sandbox curl could not resolve Herd; approved read-only curl returned HTTP 302 and all five measurement headers, including duplicate loads `0`.
- Browser tooling deviation: the in-app browser client failed to initialize with `Cannot redefine property: process`; no credentials or alternate login automation were attempted.
- First final-gate attempt: requirements sweep, Pint, FilaCheck (0 issues), and build passed. The full suite then passed 504 of 505 tests / 4,450 assertions in 412.576 seconds. The one failure was the pre-SP3A card-template test helper reading only preloaded options from the now-async Homepage template Select. The helper now falls back to the component's server search results; its focused rerun passed 1 test / 10 assertions. Because that test-file change followed the gate, the final order restarts from Pint.

## Final gate outcomes

- Requirements sweep: passed. `git diff --check` passed; no Composer/npm manifest or lockfile diff exists; all four external write paths reach the authorization overlay decision; the registry contains exactly the six approved Public Content fields; no searchable-plus-preloaded chain remains under `app/Filament`; required fixture/script/research/plan/handoff/state/ledger artifacts are present; lifecycle byte identity is covered by the frozen SHA-256 regression.
- Pint: `vendor/bin/pint --test` passed.
- FilaCheck: `vendor/bin/filacheck` passed with 0 issues.
- Build: `npm run build` passed.
- Full suite, last: the corrected implementation run passed 505 tests / 4,454 assertions in 403.338 seconds. After recording these outcomes, the mandatory documentation-state re-verification repeats Pint → FilaCheck → build → full suite and is required to remain green before commit.

## Local Front Check Report

1. Run the fixed measurement protocol above and expect the frozen baseline shape, a 37,982-byte fixture, decomposed query headers, and zero duplicate lifecycle loads.
2. Open Admin → Manage import locks and expect only section rows plus the six approved Public Content fields.
3. Lock a section, import changes to two of its fields, and expect both values to remain unchanged.
4. Expand an Advanced card template and part and expect no individual lock decoration on templates, parts, repeaters, or builders.
5. Lock `maintenance.enabled`, import a changed value, and expect it unchanged while an unlocked ordinary value applies.
6. Retain an old nested unit lock, reopen the manager, and expect a retired-lock report while import remains blocked.
7. As admin, import changed transcription-policy values plus a homepage value and expect only the homepage value to apply.
8. As super-admin in multi mode, repeat and expect the complete policy payload to apply.
9. Open publication status and expect three immediate choices without search.
10. Open a category/group relationship Select, search, and expect capped server results without eager full-table loading.
11. Open Homepage Section include-items and template-key Selects and expect async item search and instant family-scoped template results.

## Assumptions and deviations

- The unavailable never-tracked v3 prompt could not literally be moved; an archive tombstone records its never-executed status and points to SP3A–D.
- Browser medians/p95 are not fabricated: the immutable review baseline is recorded, the harness is verified, and the authenticated operator run above produces fresh samples.

## Current git status

The implementation is committed as `88fdda2`. The only follow-up change is the required docs-only hash backfill. Preflight had no unrelated dirt.
