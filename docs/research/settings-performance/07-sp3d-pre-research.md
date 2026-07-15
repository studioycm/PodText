# SP3D Pre-Research: Settings-Series Closure and Leftover Inventory

Date: 2026-07-15
Status: research-only evidence base for operator and Fable review
Scope boundary: no application code, tests, schema changes, dependency changes,
implementation plan, or execution prompt

## Executive conclusion

SP3A, SP3B, and SP3C shipped the intended storage-preserving redesign, but SP3
is not closed. The authenticated browser evidence that was deliberately left to
the operator has never been collected, SP3B's ordinary-page DOM/TTFB targets are
therefore still unclassified, and SP3C's deterministic caps are only partly
enforced as literal numeric regressions. Those facts make the browser run a
prerequisite for freezing SP3D's final browser budgets, not an optional visual
check.

The old nine-tab UI is gone, but its code shape is not fully gone. The current
tree still has a 2,477-line `BuildsPublicContentSettingsSubjectSchemas` trait
with nine `Tab` factories, including the obsolete whole-list
`cardTemplatesTab()`. The eight subject pages strip the child components out of
those tabs, and the template editor also reuses the trait for part schemas. The
59-line `App\Filament\Pages\PublicContentSettings` is now only a hidden legacy
redirect. SP3D should delete the monolithic schema implementation while
preserving the legacy URL through a clearly named, tested compatibility adapter.
It must not delete or rename the distinct storage class
`App\Settings\PublicContentSettings`.

Three operator vetoes/confirmations must remain visible before SP3D scope is
frozen:

1. Ratify or change the implemented six-field import-lock shortlist. The
   initial Codex session explicitly called it “pending your veto”; the later
   SP3A prompt called it approved without a direct approval turn in the searched
   history.
2. Ratify or change SP3A's shipped Select-classification table. The table was
   implemented, but the searched SP3A session contains no operator review of
   its individual decisions.
3. Ratify or change the LENS1 269-row bilingual label table. The operator
   corrected the relation-manager and item/group operational-column treatment
   during LENS1, and that correction shipped, but no acceptance of the remaining
   table appears in the searched Codex history.

The exact implemented field-lock shortlist still awaiting ratification is:

- `maintenance.enabled`;
- `maintenance.raw_html_override`;
- `public_forms.require_email_verification`;
- `transcription_policy.public_mode`;
- `transcription_policy.count_mode`;
- `transcription_policy.show_multiple_transcriptions_on_item_page`.

There remains one section lock per settings section. Repeater records, template
records, Builder parts, nested children, ordinary template properties, menu
items, labels, card parts, and repeater children have no field-level locks.
`AdminUxSettings.transcription_mode` was the seventh conditional proposal and
correctly remains excluded while that settings class is outside the
lifecycle/import-lock registry. This report does not treat implementation as a
substitute for the operator veto that was explicitly requested.

The Fable list is otherwise substantially accurate. The production sequence
and Google probe remain operator work; SP4, LOG1, ADM1-A, and ADM1-B remain later
work; the AGENTS commit rule and the two UI ledger rows are present. One claimed
verification item is not done: no `filament-performance-audit` skill exists in
either `.claude/skills/` or `.agents/skills/`, and Git history contains no such
path.

## Evidence method and authority

This report reconciles three evidence sets:

- The committed record: the SP3 review, SP3A/B/C research and plans, the SP3C
  prompt audit, all three handoffs, current project state, the central ledger,
  backlog triage, adjacent mini-task handoffs, and the complete accumulated
  lessons file.
- The current tree at `a93c142`: page/schema classes, ownership registry,
  focused template writer, lifecycle/lock registries, measurement middleware
  and script, current regression tests, package manifests, commands, config,
  and translation/mini-task artifacts.
- Codex's own task history, read through the local task-history interface rather
  than recalled from memory. Citations name the task, local date, thread ID, and
  relevant turn when useful.

Verdicts use only the requested four values:

- `needed-in-SP3D`: part of closing the settings performance series.
- `defer-with-reason`: real work, deliberately outside SP3D.
- `already-resolved`: present today or superseded by a shipped implementation.
- `operator-task`: requires an operator run, production/config action, veto, or
  package/product decision.

“Already resolved” does not mean a regression may be deleted. It means SP3D
should preserve the current guarantee rather than reimplement it.

## Current verified baseline

### Shipped shape

- Ordinary settings owners: `HomepageSettings`, `DisplaySettings`,
  `EpisodePageSettings`, `MenuHeaderSettings`, `PodcastSettings`,
  `ContributorSettings`, `AboutSettings`, and `MaintenanceSettings`.
- Separate existing settings surfaces: `ManagePublicForms`, `AdminUxSettings`,
  `ImportPublicSettings`, `ManageSettingsImportLocks`, Importer Settings, and the
  Settings Backups resource.
- Template surfaces: read-only `CardTemplateSettings` library plus hidden
  `CreateCardTemplate` and `EditCardTemplate` pages over the shared
  `CardTemplateEditorPage` shell.
- Storage and lifecycle: one `App\Settings\PublicContentSettings` settings
  class/group; `SettingsSubjectOwnershipRegistry`; the existing lifecycle,
  import-lock, backup, normalize, restore, import, authorization-overlay, and
  `SettingsSaved` paths.
- Template mutation: `CardTemplateFocusedWriter` refreshes a canonical snapshot,
  validates the target fingerprint and identity, changes one template, fills
  the full snapshot, and calls the existing settings save once. It provides
  sequential optimistic stale detection, not simultaneous-request
  serialization.

### Measurement evidence

The immutable pre-split browser contrast was 40,765 DOM elements, 13.8 MB of
live DOM, roughly 26,970 listeners, 2.551 s TTFB, and a 29,404-element Advanced
area. SP3A added a deterministic 37,982-byte fixture, local response headers,
request-scoped profiler/lifecycle counters, and
`scripts/settings-sp3a-browser-metrics.js`. Measurement mode is local-only and
refuses saves.

SP3C froze these server/Livewire canary ceilings:

| Surface | Frozen elements | Frozen HTML bytes | Frozen state bytes |
|---|---:|---:|---:|
| Production-shaped library, 30 rows | 1,262 | 575,913 | 13,973 |
| Preview editor, unselected | 4,212 | 1,508,900 | 21,742 |
| One selected top-level part | 4,341 | 1,562,081 | 21,742 |
| Selected group plus one nested child | 4,469 | 1,616,670 | 21,742 |

The production test currently hard-caps the library's elements/HTML/state and
the unselected editor's elements. The selected/nested numeric ceilings and the
unselected editor's HTML/state ceilings are recorded in the handoff but are not
all literal assertions. The canary still proves deterministic samples and at
least 70% wrapper/control reduction. These are component/server measures, not
authenticated browser DOM, listener, heap, or TTFB measures.

The executable lifecycle regression freezes SHA-256
`61e551a60016b1ac0c9aa8051463818adf31677bea465ac0e9b269fe3d2386b8`.
`05-sp3c-research.md` alone contains the conflicting stale value
`61e551a422280b06ea6a2a66f235da10d1e349c787780f1709369e53c888addc`.

## Complete inventory and verdicts

| ID | Item | Origin | Current state verified now | Verdict | Requirement, dependency, or risk |
|---|---|---|---|---|---|
| P1 | Original remote/local slowness remains objectively unclosed | Initial SP3 diagnosis; review item 2 | The architectural cause was addressed and server/component evidence improved, but no authenticated post-SP3B/C browser sample exists. | `needed-in-SP3D` | Closure depends on P2-P5. Do not infer end-to-end success from component HTML or query counts. |
| P2 | Authenticated browser measurement across SP3B and SP3C surfaces | SP3A protocol; SP3B blocked samples; SP3C pending operator evidence; Fable OPEN list | One cold plus five warm samples, profiler off/on, fixed viewport, DOM/listeners/heap/TTFB, navigation, modal, and Back evidence were never collected. | `operator-task` | This is the prerequisite evidence run. It must use the throwaway fixture/read-only measurement mode and must not mutate the local development settings database. |
| P3 | SP3B ordinary-page DOM under 3,000 and warm median TTFB under 800 ms | SP3B handoff measurement table | Both remain explicitly “unclassified”. Card Templates' SP3B exemption ended with SP3C. | `needed-in-SP3D` | Freeze pass/fail only after P2; measure all eight owners plus Public Forms and the four recorded stress canaries. |
| P4 | SP3C browser budgets and real browser flows | SP3C handoff evidence table 3 | TTFB, actual DOM/listeners, heap, library→editor→save/clone, real teleported modal editing, and dirty Back warning remain pending. | `needed-in-SP3D` | P2 supplies baseline evidence; the durable gate must distinguish browser metrics from component metrics. |
| P5 | Durable browser regression rather than a one-time report | Original SP3D scope and review acceptance contract | The console helper is repeatable but manual; Pest Browser 4.3 and Playwright 1.61 are installed and existing browser tests already own fixtures/authentication. | `needed-in-SP3D` | Recommended hybrid gate is defined below. TTFB needs a fixed runner/profile to avoid machine-noise failures. |
| P6 | Literal enforcement of all frozen SP3C numeric caps | SP3C canary verdict/handoff | Library and part of unselected-editor caps are literal assertions; selected/nested caps and some HTML/state caps are report-only today. | `needed-in-SP3D` | Preserve the installed-version selector semantics and selected-field equivalence; do not pretend teleported modal DOM is in `Testable::html()`. |
| P7 | Delete the remaining monolithic schema implementation | Original SP3D cleanup; current code inspection | UI split shipped, but the 2,477-line trait still owns all eight tab factories, the dead whole-template Repeater, and shared part helpers. | `needed-in-SP3D` | Replace `Tab` wrappers with owner-specific schema providers plus focused shared factories. Registry ownership and current output must remain unchanged. |
| P8 | Retire the legacy page class while retaining old URLs/deep links | Original SP3D cleanup | `App\Filament\Pages\PublicContentSettings` is a 59-line authorized redirect that preserves measurement parameters and old `public-content-tab` mappings. | `needed-in-SP3D` | Delete/rename the misleading page class only after equivalent compatibility-route coverage is green. Keep the redirect adapter; do not delete `App\Settings\PublicContentSettings`. |
| P9 | Direct page/component authorization coverage | Original SP3D integrity list | SP3B covers guest/user/admin/super-admin access for the owner pages and direct URLs; SP3C covers library/create/edit route and writer refusals. | `already-resolved` | Relocation during P7/P8 must retain this matrix, including direct Livewire/writer refusal and protected-state absence. |
| P10 | Save without hydrating/selecting optional content; preserve sibling/hidden state | Original SP3D integrity list | Page separation removed cross-subject lazy children. SP3B proves stale disjoint-owner preservation; SP3C proves unselected-part absence, parent-draft survival, and sibling preservation. | `already-resolved` | Preserve tests through cleanup. Do not add islands merely to satisfy historical wording. |
| P11 | Navigate-away/back, dirty warning, and browser history automation | SP3B deferred; SP3C pending browser evidence | Hash/dirty behavior is covered below the browser, but the real dialog, accepting/staying, and back navigation are not. | `needed-in-SP3D` | Add a real browser regression if reliable; the operator sample remains evidence, not the permanent enforcement mechanism. Full-remount draft recovery is a different deferred feature (C6). |
| P12 | Relocate tests without reducing behavior coverage | Original SP3D scope | SP3B/C retargeted old page tests, but final monolith-trait deletion has not happened. | `needed-in-SP3D` | Preserve ownership completeness, authorization, lifecycle SHA, import/restore/normalize/lock/backup behavior, template writer conflicts, renderer output, and browser budgets. |
| P13 | Durable settings-performance lesson | Original SP3D scope | `ai-development-lessons.md` contains no SP3/40k-DOM/Builder-preview closure lesson. | `needed-in-SP3D` | Write only after final browser evidence; distinguish schema-build time, response size, Livewire state, browser DOM, and TTFB. |
| P14 | SP3 measurement instrumentation | SP3A | Fixture, middleware headers, profiler subjects, browser helper, measurement save refusal, and lifecycle counters are present; SP3C integrates library/editor subjects. | `already-resolved` | Reuse one metric vocabulary. Profiler/log artifacts must never contain settings values, trusted HTML, tokens, credentials, or uploads. |
| P15 | Frozen lifecycle SHA documentation drift | SP3C research versus executable regression | One SP3C research value is wrong; test, SP3A handoff, SP3B research, and SP3C review agree on `61e551a600…`. | `needed-in-SP3D` | Correct documentation without changing fixture/lifecycle bytes. Treat the executable regression as authority. |
| P16 | Current-state and ledger status drift | Current project state and ledger | Current state has both “SP3C complete” and a later stale “implementation not started” row. Ledger `Current Run` still names OTP-POLICY1 although SP3B/C rows and commits follow it. | `needed-in-SP3D` | SP3D closeout should reconcile status once; do not alter main-queue ordering or reclassify unrelated work. |
| A1 | Important-field lock shortlist veto | Initial Codex task; review says veto pending; user reiterated in this kickoff | Code exposes exactly six Public Content fields plus section locks; nested rows/parts have no field decoration. The SP3A prompt labeled the list approved without a direct approval message in searched Codex history. | `operator-task` | Ratify or enumerate changes before SP3D. Any change is to `SettingsImportLockSurfaceRegistry`, not lifecycle units. `AdminUxSettings.transcription_mode` remains excluded while Admin UX is outside lifecycle import/lock registration. |
| A2 | SP3A Select-classification veto | Fable OPEN list; SP3A plan/handoff | The complete table shipped: bounded sets preload, tiny sets are plain, growing sets are async/non-preloaded/capped, and no eager searchable+preloaded chain is intended. No direct operator acceptance turn was found. | `operator-task` | Review before browser budgets freeze because Select policy affects DOM and query behavior. A veto should be a separate bounded correction, not a schema change by default. |
| A3 | Label-column indexes/full-text search | SP3A deferred note | `content_items.title`, `content_groups.title`, `categories.name`, and `authors.name` still have no label index/full-text migration. Current contains searches are server-side, constrained, and capped. | `defer-with-reason` | No measured search-latency evidence justifies a schema change. A normal B-tree does not accelerate leading-wildcard contains search; first define the actual query/index strategy. |
| A4 | `AdminUxSettings.transcription_mode` lifecycle/import lock | Initial seven-field proposal; SP3A classification | Admin UX is still not registered by `SettingsLifecycleGroups`; its page authorization overlay is authoritative. | `already-resolved` | Revisit only if a future step explicitly brings Admin UX into lifecycle import/export/locks. Do not add it incidentally in SP3D. |
| A5 | SP3A manual front check: measurement safety, lock/import overlay, and Select UX | SP3A handoff Local Front Check Report | The handoff specifies 11 operator steps. Automated regressions cover the underlying contracts, but the committed record does not say the operator performed the manual checks. | `operator-task` | Fold these checks into P2 where possible: read-only measurement, section/field lock visibility, retired nested-lock absence, import overwrite refusal, admin versus super-admin overlay, and bounded/async Select behavior. Record any veto separately. |
| B1 | Same-owner conflict resolution and truly simultaneous saves | SP3B deferred boundary | Owner pages preserve sequential stale disjoint-owner saves. No lock, `lockForUpdate()`, advisory lock, or simultaneous serialization exists. | `defer-with-reason` | Not required to enforce rendering budgets. A future concurrency step must include every writer, cache/transaction ordering, and real database semantics rather than SQLite-only claims. |
| B2 | SP3B Card Templates redesign | SP3B deferred to SP3C | SP3C replaced the whole-list editor with library/create/edit pages and a focused writer. | `already-resolved` | Preserve the writer boundary and do not resurrect a whole-list form during trait deletion. |
| B3 | SP3B manual front check: ownership pages, locks, stale disjoint saves, links, and admin gating | SP3B handoff Local Front Check Report | Automated tests exist, but no committed operator-execution result was found for the prescribed page/schema, save, old-link, hint, and access checks. | `operator-task` | Include the visual/interaction portions in P2. Destructive or stateful save checks must use owned test fixtures, never experimental writes to the local development settings database. |
| C1 | SP3C preview-canary decision | SP3C stop/go gate | Native Filament Builder previews passed with over 93% wrapper/control reduction; the fallback nested editor was not needed. | `already-resolved` | This is editor-summary compactness, not a claim of complete PHP schema deferral or a public live preview. |
| C2 | Template-level reorder | SP3C v3 deferred list | Library intentionally exposes no reorder action; configured raw order remains deterministic. Part and nested-part reorder still work. | `defer-with-reason` | Requires an explicit focused writer and identity/reference semantics; unrelated to SP3D budgets. |
| C3 | Template/part lifecycle locks | SP3C deferred list | Import locks remain family/section-level and informational for ordinary editor saves; no per-template/part lock was added. | `defer-with-reason` | Adding fine-grained locks would reverse the approved lock diet and requires a separate lifecycle decision. |
| C4 | Repoint consumers on template rename | SP3C deferred list | Rename/delete are blocked when explicit references exist; the operator repoints consumers first. Default identities are protected. | `defer-with-reason` | Automatic repointing crosses `PublicContentSettings` and `HomepageSection` records and needs an atomic, authorized design. |
| C5 | Persistent where-used/new cache layers | SP3C review and deferred list | Library uses one request-local scan plus one projected `HomepageSection` query. No persistent template-reference cache exists. | `defer-with-reason` | Current evidence is bounded. A cache would need invalidation on both `SettingsSaved` and every relevant section mutation; revisit with SP4 only if measured. |
| C6 | Recover dirty editor draft after accepting Back/full remount | SP3C deferred list | Staying on the page preserves draft; recovery after a full remount is not promised. | `defer-with-reason` | This is persistence/draft UX, not the real-browser warning regression in P11. It introduces storage/privacy/lifecycle choices. |
| C7 | Template writer scan-to-save race | SP3C claim boundary | Fingerprints stop sequential stale overwrites, but two simultaneous requests can still pass checks before either full-blob save. | `defer-with-reason` | Same dependency as B1. SP3D must not relabel optimistic stale detection as atomic serialization. |
| C8 | First-class template records/storage migration | SP3 review deferred option | Templates remain JSON in `App\Settings\PublicContentSettings`; there is no model/table migration. | `defer-with-reason` | Requires explicit architecture/schema approval and portability/lifecycle migration design. Not implicit in monolith-code deletion. |
| C9 | SP3C manual front check: library/editor/create/clone/edit/rename/stale/protected/Back flows | SP3C handoff Local Front Check Report | The server/component suite covers the contracts, but the handoff leaves the real library/editor, public-renderer, modal, stale-state, protected-identity, and Back-warning acceptance run to the operator. No completed run is recorded. | `operator-task` | Merge this with P2/P4. Use test-owned fixtures and authenticated browser state; do not count static component HTML as evidence for teleported modal DOM. |
| C10 | Per-part merge for actors who cannot view protected template parts | SP3C prompt review later-product-decision note | Current policy is conservative: the actor receives a generic shell with `parts` absent; an allowed shell edit restores the fresh complete protected parts server-side. There is no merge of editable parts around a protected part. | `defer-with-reason` | A finer merge changes the authorization and hydration contract and needs explicit product rules for ordering, nested children, forged state, and concurrent freshness. It is not budget enforcement. |
| S1 | SP4 group/slice-scoped settings reads | Initial operator request; SP3 review retained SP4; Fable SCHEDULED | `PublicFrontConfigReader::group()` still calls `read()` and validates the whole config. P1 later added a validated whole-config cache and invalidation watermark. | `defer-with-reason` | SP4 must build on, not duplicate, the current cache and `SettingsSaved` invalidation; preserve current storage. |
| S2 | SP4 changed-fields-only save boundary and server-side diff | Fable SCHEDULED | Owner pages freshen storage and overlay every owned property, then save the full canonical settings object. They do not emit an explicit changed-field set. | `defer-with-reason` | Compute diff server-side after normalization and authorization against the fresh snapshot. Preserve one save/event/lifecycle flow and hidden-field survival. This change-set becomes LOG1's input. |
| S3 | SP4 public/template live preview: Peek versus owned preview | Initial operator request; SP3 review; Fable SCHEDULED | Native Builder summaries exist, but no live public/full-page preview exists. `pboivin/filament-peek` is not installed; no erpsaas reference is committed. | `defer-with-reason` | Recommended default is an owned preview using actual renderers and authorization. Peek remains an option if its unsaved full-page iframe UX justifies operator Composer approval; re-research the erpsaas pattern during SP4. |
| S4 | LOG1 activity log consuming SP4 change-sets | Fable SCHEDULED | `spatie/laravel-activitylog` is absent from `composer.json`/lock and no settings activity log exists. | `defer-with-reason` | Requires operator Composer approval and S2's authoritative change-set. Log safe paths/metadata and authorized before/after summaries; never raw secrets, trusted HTML, or credentials. |
| S5 | ADM1-A form/table reorder | Fable SCHEDULED; MAIL1 prompt says full reorder belongs to ADM1 | No dedicated ADM1 prompt/plan or committed ADM1-A scope exists. MAIL1 moved only the Spotify fetch entry first. | `defer-with-reason` | UX/content task, not performance cleanup. Inventory target forms/tables and lock order only when separately scoped. |
| S6 | ADM1-B hint-icon descriptions and simpler Hebrew | Initial settings task; review registry; Fable SCHEDULED | Explicitly queued outside SP3; current settings fields still commonly use visible helper text/section descriptions. | `defer-with-reason` | Preserve destructive warnings visibly. This broad copy/layout churn would invalidate attribution if mixed with SP3D. |
| O1 | LENS1 269-row label-table veto | Fable OPEN list; LENS1 plan/handoff and Codex task | The table has 269 data rows. LENS1 shipped mode-aware variants. The operator's in-session correction preserved the relation manager and item/group featured/count operational columns. No final acceptance of all other rows was found. | `operator-task` | Ratify or list rejected rows. Keep any resulting label-only correction separate from SP3D unless it directly changes a measured settings surface. |
| O2 | Production role and single-mode switch | ROLES1/LENS1 handoffs; Fable production sequence | `users:assign-role {email} {role}` exists; fresh default is `single`; real production stored state and first super-admin cannot be verified from the repository. | `operator-task` | After deploy, assign the intended account `super-admin`, then save Admin UX `transcription_mode=single`; follow production safety and do not expose account details. |
| O3 | Production settings normalize dry-run | SP2 handoff; Fable production sequence | `settings:normalize-public-content` exists; default mode is dry-run and writes nothing. Apply mode is backup-first. No production run is recorded. | `operator-task` | Run dry-run first and review per-group output. Any production apply is a separate explicit mutating action after backup confirmation. |
| O4 | Production OTP/Resend configuration and DNS | MAIL1 and OTP-POLICY1 handoffs; Fable production sequence | `.env.example` contains the `FORMS_OTP_*` family and `RESEND_KEY`; config defaults are 5/5/60; Resend package/config exists. Repository cannot prove production `MAIL_MAILER=resend`, `RESEND_KEY`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, OTP values, sender-domain DNS, config cache, or worker recycle. | `operator-task` | Set key names/values only in production, verify sending-domain SPF/DKIM with Resend, rebuild config cache, and recycle long-lived workers through the approved deploy path. Never commit secrets. |
| O5 | Google service-account setup and transcript-format probe | WB1 handoff/backlog triage; Fable OPEN list | Service-account/OAuth boundaries and `importer:probe-formats` exist. Findings still say “Documents analyzed: 0”; the real connection/sample run is blocked on operator credentials/sharing. | `operator-task` | Create/share the service account locally, test the real connection, run the 20-doc sample, and review tracked structural findings. It gates WB2/WB4 planning and paste/`[]` conventions. |
| O6 | Production validated-settings cache enablement | P1 handoff production follow-up | The cache, invalidation watermark, and `SETTINGS_CACHE_ENABLED` switch exist; the handoff leaves production enablement, cache-store choice, config refresh, and process recycle to the operator. The repository cannot prove the live store or flag. | `operator-task` | Decide whether to enable it after deploy, prefer Redis if provisioned, then refresh config/recycle only through the approved deployment path. This must precede interpreting production TTFB and must not create a second cache authority during SP4. |
| V1 | AGENTS commit-message rule from SP3C Job 0 | Fable VERIFY list; SP3C research | Present in current `AGENTS.md`: allowed prefixes, imperative subject, canonical backfill subject, no hook. | `already-resolved` | Keep evergreen; no SP3D action beyond preservation. |
| V2 | `filament-performance-audit` mirrored skill | Fable VERIFY list | Absent from both `.claude/skills/` and `.agents/skills/`; `git log --all` shows no historical path. | `needed-in-SP3D` | Create both mirrors only after final SP3D evidence so the skill records the proven two-tier audit, claim boundaries, and secret-safe metrics. This is the durable tooling companion to P13. |
| V3 | Ledger rows for logo/favicon and collapsible navigation | Fable VERIFY list | `UI-HF1` records `9d8296f`; `UI-HF2` records `d128cfd`. Current panel registration/assets and collapsible nav configuration are present. | `already-resolved` | No duplicate ledger row. Preserve current icons/order/collapse behavior. |
| V4 | White-label single-mode terminology follow-up | SP3 review decision-registry item 18 | LENS1 shipped the mode-aware Hebrew/English ontology, route/state non-exposure, creation policy, and regression coverage. The operator's narrower operational-column correction also shipped. | `already-resolved` | Preserve the single/multi branches and server-side guards. The independent whole-table copy veto remains O1; do not reopen the implemented ontology inside SP3D. |
| D1 | ZIP import-package flows and `transcript_file` package support | Known-deferred list; image/import docs and guideline | Export-only content-images ZIP exists. ZIP imports and `transcript_file` packages remain explicitly deferred pending a manifest and safety design. | `defer-with-reason` | Needs zip-slip protection, caps, MIME/count validation, private scratch cleanup, portable IDs, and operator promotion. Do not scope into SP3D. |
| D2 | EP1 per-user presentation preference | EP1 R8/backlog triage | Global Admin UX presentation settings exist; users have no admin-preferences storage/precedence. | `defer-with-reason` | Small post-13 UX/schema task unless explicitly pulled; unrelated to settings rendering closure. |
| D3 | Transcript paste cleanup and `[]` conventions | EP1 R13/backlog triage | Hook boundary is researched; conversion rules remain intentionally absent. | `defer-with-reason` | Wait for O5's real source-shape evidence. Store Markdown only and do not guess conventions. |
| D4 | Main-queue P2/P3 | Ledger/current sequence; Fable known-deferred | P2 listing/query economy and P3 derived transcript segments remain pending in the main queue. | `defer-with-reason` | Preserve their queue order; neither is SP3D settings work. |
| D5 | Post-13 arcs | Ledger/backlog triage; Fable known-deferred | AX/SL/B4/C2/9F rich work, seeders, and later prompts remain governed by the central sequence/operator selection. | `defer-with-reason` | Record only; SP3D must not advance or rescope them. |
| M1 | Curator picker loses selected images after reload | Codex mini-task between SP3B and SP3C | Fixed by `23a6ce9`; CURATOR-HF1 handoff and regression cover header logos, default images, and legacy-path preservation. | `already-resolved` | Preserve raw-path storage and SP3B ownership/lifecycle behavior. |
| M2 | Navigation groups collapsible and item icons | Codex mini-task between SP3B and SP3C | `d128cfd` and UI-HF2 make groups collapsible. All eight subject pages, Public Forms, and Card Templates currently declare Heroicon enums. | `already-resolved` | Preserve central order and icons during P7/P8. |
| M3 | PAO update request | Codex SP3B task | Handoff records `laravel/pao v1.1.2` as current/latest; update changed no manifest, lock, or vendor state. | `already-resolved` | No SP3D dependency change. |
| M4 | OTP action belongs at logical inline-end | Codex OTP-POLICY1 mini-task | Shipped in `0394ab5`; both surfaces assert normal logical order and reject reversed flex order. | `already-resolved` | No settings-performance scope. |
| M5 | Avoid expanding a tiny requested commit into unnecessary process | Codex Herd-mail/Horizon and OTP tasks | The operator stopped extra docs/tests in the Horizon mini-task and later requested commit/push without reruns for OTP. Both tasks were narrowed/finished as directed; no durable feature is missing. | `already-resolved` | Treat as a scope-discipline signal: SP3D should implement only the approved scope. Repository gates still apply unless the operator explicitly overrides them in that run. |
| M6 | Validated whole-config cache implementation | SP3 review's cache-policy deferral; Step 10R-P1 handoff | P1 shipped versioned validated-config caching, settings-migration watermarking, corruption fallback, and `SettingsSaved` invalidation behind `SETTINGS_CACHE_ENABLED`. | `already-resolved` | Preserve one cache authority. Production enablement remains O6; group/slice reads and any revised key strategy remain S1/SP4. |

## Operator action register

No production or credential-bearing action was performed in this research
session. The committed prerequisites and follow-ups form this operator-owned
sequence:

1. After the relevant deploy, assign the intended account the `super-admin`
   role, then set the stored Admin UX `transcription_mode` to `single`.
2. Decide whether to enable the validated-settings cache in production; if
   enabled, prefer the existing Redis store when provisioned and refresh
   long-lived configuration/processes through the approved deployment path.
3. Run `settings:normalize-public-content` in its default dry-run mode and
   review every group. Any apply run remains a separate approved mutating
   action with the command's backup-first protection.
4. Configure `MAIL_MAILER=resend`, `RESEND_KEY`, `MAIL_FROM_ADDRESS`,
   `MAIL_FROM_NAME`, and the `FORMS_OTP_*` family in production; verify the
   Resend sending-domain DNS; then refresh cached configuration and workers
   through the approved deploy path.
5. Separately complete the Google service-account sharing/connection and the
   20-document format probe before WB or paste-convention planning.
6. Before SP3D budgets are frozen, resolve the lock and Select vetoes and run
   the combined authenticated SP3A/B/C front-check and measurement protocol.
   The LENS1 label-table veto stays visible but need not block settings work if
   its corrections remain isolated.

## Codex session-history findings

The following task citations are to Codex's own local history. The audit searched
the SP-series tasks and the mini-tasks between them; it did not rely on memory or
Claude-side content.

| Session citation | Operator point found | Reconciliation |
|---|---|---|
| Codex task **“Initial Settings slowness debug - sp3a-d planned”**, 2026-07-14, thread `019f5e8b-663c-78b1-984c-b708c542e4af`, initial turn/final diagnosis | Locks stay at section level plus a proposed seven-field list “pending your veto”; preview/Peek and group reads stay later; hint icons/simple Hebrew stay queued. | The six applicable Public Content fields shipped; Admin UX remained inapplicable. No later turn in this task approved the list. Preview/group reads/ADM work remain correctly deferred. |
| Codex task **“Implement settings SP3A, plan SP3B v3”**, 2026-07-14, thread `019f5ee1-ba53-71b3-963b-e2db4716ab2a` | Kickoff required the complete Select classification. The task proceeded from SP3A implementation into SP3B research/review; no user turn accepted or vetoed individual table rows. The operator later challenged the SP3B v3 concurrency exclusion. | Select veto remains O/A2. The concurrency question was answered with exact v3 scope and is documented in SP3B; it is not an orphan. |
| Codex task **“LENS1 step fix - hide multi transcriptions”**, 2026-07-14, thread `019f5e31-2506-7a61-a25b-7e763d25f8b9`, turn `019f5e37-2428-7fd1-af74-46bc0afaafa2` | Operator said the relation manager and item/group featured/count columns were intentionally retained and told Codex to change its additions. | The implementation/docs were corrected in-session and shipped. That subset is resolved; the rest of the 269-row table still has no recorded final veto/acceptance. |
| Codex task **“SP3B v3 implementation - Fix owned-path save contract”**, 2026-07-14–15, thread `019f6249-30a4-7aa1-adb8-55aa0736adbf`, turns `019f626c…`, `019f6314…`, `019f6335…`, and `019f6344…` | Operator requested PAO update, debugged Curator selections disappearing, requested collapsible groups/icons, required the Curator repair as a separate documented mini-step, and asked that verified navigation changes be separately committed. | PAO no-op is in the SP3B handoff; Curator is CURATOR-HF1; collapse is UI-HF2; icons already exist. No unresolved mini-task remains from these points. |
| Same SP3B task, 2026-07-15, turns `019f639c…` through `019f6435…` | Operator repeatedly requested deep SP3C prompt auditing and explicitly welcomed converting ambiguities into product decisions. | Landed in `06-sp3c-prompt-review.md`, prompt v3, and the SP3C implementation/handoff. No additional orphan was found. |
| Codex task **“Update OTP policy config”**, 2026-07-15, thread `019f634e-111d-70a1-9e30-4729a5cb9dc2`, turn `019f636b-25fb-72c1-b21d-3a0aafb5b1c5` | Operator reported that the OTP action belongs at the component's inline end (left in Hebrew/RTL). | Shipped and documented by OTP-POLICY1; resolved. |
| Codex task **“Troubleshoot Herd mail queue”**, 2026-07-14, thread `019f6219-5f99-7ce3-b26b-3c1a397dd335`, turns `019f622b…` and `019f622f…` | Operator requested only the Horizon 64→128 commit and stopped extra tests/docs as unnecessary. | Final commit was narrowed to the requested config change. This is a process/scope complaint, not an unimplemented settings feature. |
| Codex task **“Execute settings SP3C prompt”**, 2026-07-15, thread `019f6437-2e48-7e31-9f39-ed527593a8c6` | No mid-run operator request was raised; final status explicitly left browser acceptance pending. | P2/P4 are the only task-history leftover from this execution. |

The unresolved operator-raised decisions found in these Codex tasks are the
lock veto, the absence of a Select-table acceptance, and the absence of a full
LENS label-table acceptance. The committed handoffs additionally leave the
combined SP3A/B/C front checks and browser acceptance run unperformed. Other
searched complaints/requests landed in code/docs or were handled within their
task; the production and Google follow-ups come from the committed record and
remain listed in the operator action register.

## SP3D end-state wireframe

### Recommended responsibility map

```text
/admin/public-content-settings + old public-content-tab links
                         |
                         v
        named, hidden legacy redirect adapter (no form/schema)
                         |
       +-----------------+------------------+
       |                                    |
       v                                    v
eight owner pages + Public Forms       Card Template library
       |                                    |
owner-specific schema provider         Create/Edit editor shell
       |                                    |
       +-----------------+------------------+
                         |
                         v
              SettingsSubjectOwnershipRegistry
                         |
       fresh canonical snapshot -> normalize/authorize owned input
                         |
          one existing settings save / SettingsSaved event
                         |
      lifecycle SHA + import locks + backups + cache invalidation

Normal regression tier: deterministic response/query/HTML/state/ownership caps
Browser regression tier: authenticated DOM/console/navigation/modal/Back caps

SP4 later: slice reads + changed-field change-set + public preview decision
LOG1 later: consumes the SP4 change-set after package approval
```

### Classes/pages that remain

| Remains | End-state responsibility |
|---|---|
| `App\Settings\PublicContentSettings` | Authoritative storage class and lifecycle group. |
| Eight subject pages plus `ManagePublicForms` | One visible owner surface each; no top-level `Tab` wrapper and no foreign schema. |
| `PublicContentSettingsSubjectPage` | Shared authorization, profiler, fresh-snapshot owner save, lock hints, notification, and lifecycle integration only. |
| `SettingsSubjectOwnershipRegistry` | Single complete map of page-owned properties/validator groups. |
| `CardTemplateSettings`, `CreateCardTemplate`, `EditCardTemplate`, `CardTemplateEditorPage` | Lightweight library and one-template create/edit flow. |
| `CardTemplateFocusedWriter` and focused projector/reference/identity/access classes | Sole template mutation/projection responsibilities; sequential stale claim remains honest. |
| Lifecycle/import/restore/normalize/import-lock/backup services and SP3A fixture/profiler/middleware | Unchanged durability and measurement boundaries. |
| A renamed legacy redirect adapter | Old slug/query compatibility only; hidden, authorized, and incapable of form construction or save. |

### Code deleted or replaced

| Delete/replace | Reason |
|---|---|
| `BuildsPublicContentSettingsSubjectSchemas` as one 2,477-line trait | It is the surviving monolith, hides ownership boundaries, and keeps unrelated schemas/helpers coupled. |
| All eight `*Tab()` wrappers | Subject pages no longer use tabs; providers should return their owned components directly. |
| Obsolete `cardTemplatesTab()` and its whole-list Repeater/Builder | SP3C's focused library/editor superseded it; retaining it risks accidental resurrection and needless maintenance. |
| Misleading `App\Filament\Pages\PublicContentSettings` class name | Replace with an explicitly named compatibility adapter while preserving the route contract. |
| Tests whose only purpose is constructing the retired monolith/Tab wrapper | Replace with owner/provider, compatibility-route, and behavior assertions; never delete the underlying behavior coverage. |

Recommended decomposition is one schema provider per owner plus small shared
factories for genuinely shared finite fields and card-part blocks. Keeping one
giant trait and merely deleting `cardTemplatesTab()` is lower effort but does
not finish the monolith deletion. Inlining all schema into page classes makes
ownership visible but would duplicate shared part/field logic. The provider plus
focused-factory option best matches the existing ownership registry and gives
the budget harness a stable subject boundary.

## Making budgets durable

### Options

| Option | Strength | Weakness | Verdict |
|---|---|---|---|
| Keep the DevTools console protocol only | Measures the real signed-in page and local machine | Manual, easy to skip, no merge regression | Keep as operator diagnostic/evidence only |
| Keep only current Livewire/component caps | Deterministic and fast; already owns fixtures | Cannot see hydrated browser DOM, console, listener/heap behavior, real modal teleport, or Back dialog | Necessary but insufficient |
| Standalone Node Playwright benchmark | Excellent browser metrics and JSON artifacts | Must duplicate test fixture/auth/bootstrap and gate semantics | Useful for a controlled local benchmark, not the primary behavior suite |
| Pest Browser plus deterministic component/server caps | Reuses installed stack, app fixtures, authentication, translations, and existing browser conventions | Timing thresholds still need a fixed runner; browser tests are slower | **Recommended hybrid** |

### Recommended durable contract

The ordinary full regression should always enforce deterministic facts:

- frozen response bytes, settings reads, reference queries, lifecycle
  derivations, duplicate loads, component elements, HTML/state bytes, wrappers,
  controls, IDs, and `wire:model` ceilings for every relevant state;
- zero unselected editor fields/paths and bounded query counts as fixture rows
  grow;
- complete ownership and authorization, one-save lifecycle, hidden/protected
  preservation, sibling byte identity, and writer conflict/refusal behavior.

A dedicated serial Pest Browser settings suite should own its user and database
fixtures, fixed viewport, route matrix, modal selections, navigation, Back
warning, and console assertions. It should evaluate a shared browser metric
extractor rather than duplicate the DevTools script. Hard structural caps such
as browser DOM and zero unselected controls can run wherever browser tests run.

TTFB, heap, and listener counts are more environment-sensitive. Freeze them
from P2 and enforce them in a named performance profile on the same fixed local
or CI runner, using the documented cold sample plus five warm samples and
median/p95 output. Do not make an arbitrary developer laptop fail the normal
full suite because its wall-clock TTFB differs. The performance profile must
fail on agreed caps and emit machine-readable counts/timings only—never settings
payloads or secrets.

This preserves the current two honest measurement planes:

1. server/component caps for deterministic regressions;
2. real-browser caps for DOM and interaction regressions.

It also prevents a common failure mode: moving work from the initial response
to deferred requests while claiming a win. The browser artifact must include
initial and aggregate transferred bytes, request count, hydration/update cost,
and the selected editor state where applicable.

## Risks and adjacent mechanisms

- **Ownership registry:** provider extraction must be checked against every
  public property exactly once. A page must not render, validate, or save a
  foreign owner path. `ManagePublicForms` remains a registered owner even though
  it has a custom form method.
- **Focused writer:** monolith deletion must not move template writes back into
  page methods or a whole-list form. The current writer is the mutation
  boundary. Its sequential fingerprint is not a database lock.
- **Lifecycle SHA:** schema-provider refactoring must not change lifecycle units
  or the SP3A fixture. Correct the research typo; do not “fix” the executable
  SHA to match it.
- **One-save event chain:** successful subject/template saves must continue to
  emit the existing `SettingsSaved` path exactly once so backup deduplication,
  validated-config cache invalidation, render-context invalidation, and
  profiler subjects remain coherent.
- **Measurement harness safety:** browser/performance fixtures must use the test
  database or the existing read-only throwaway measurement state. Never use the
  local development database for experiments. Measurement/profile flags must
  remain environment-gated, save-refusing, and incapable of exposing values.
- **Budget comparability:** do not compare the historical 29,404 browser DOM
  count as a percentage against a Symfony/Livewire HTML parser count. Freeze
  caps within the same plane and fixture.
- **Teleported modals:** native Filament action behavior must be tested directly,
  while real modal DOM belongs to the browser suite. Static component HTML must
  not be relabeled as modal DOM.
- **TTFB flakiness:** fixed viewport is not enough; browser version, server
  mode, cache state, fixture, runner, sample order, and profiler state must also
  be fixed and reported.
- **SP4 reads/cache:** current P1 caching validates and stores the full config.
  A slice reader must define cache keys/invalidation without producing a second
  authority or returning partially normalized incompatible shapes.
- **SP4 diff/LOG1:** the authoritative change-set must be produced server-side
  from fresh stored state after normalization and authorization. Client dirty
  fields are not an audit source. LOG1 must consume safe change metadata and
  avoid sensitive values.
- **Label indexes:** no migration belongs in SP3D. If search later proves slow,
  choose prefix/full-text/search-engine semantics before choosing an index and
  verify MySQL `utf8mb4` key math.
- **Operator veto timing:** lock/Select decisions can alter measured settings
  DOM/query shape. Resolve them before P2/final budget freezing. LENS label
  review can stay separate unless it changes settings surfaces.

## Proposed SP3D scope for operator approval

### Prerequisites, not implementation scope

- Operator ratifies or amends the lock and Select tables.
- Operator records the authenticated SP3B/SP3C browser evidence using the fixed
  protocol, folding in the still-unrecorded SP3A/B/C manual front checks. The
  LENS label-table veto remains visible but need not block SP3D if any correction
  is isolated from settings.
- The final numeric browser caps and the fixed TTFB runner/profile are approved
  from that evidence rather than invented in an implementation prompt.

### Include in SP3D

- Delete the monolithic schema trait, `Tab` wrappers, and obsolete whole-template
  editor; replace them with owner-specific schema providers/focused shared
  factories while preserving rendered behavior.
- Preserve the old slug/deep-link mappings through a clearly named hidden
  redirect adapter and remove the misleading legacy page class only after its
  compatibility matrix is green.
- Turn every recorded SP3C numeric ceiling into a literal deterministic
  regression and add equivalent ordinary-page server/query/response ceilings.
- Add the authenticated serial Pest Browser settings regression for DOM,
  console, direct routes, modal editing, navigation, and dirty Back behavior;
  add the separately named fixed-runner performance profile for cold/warm
  TTFB/heap/listener and aggregate request/byte evidence.
- Preserve and, where files move, retarget the complete authorization,
  ownership, hidden-field, stale-page, focused-writer, lifecycle SHA,
  import/restore/normalize/import-lock/backup, and renderer regression set.
- Correct the stale SP3C SHA statement and current-state/ledger SP3C status
  drift as part of the eventual SP3D documentation closeout.
- Add the final settings-performance lesson and mirrored
  `filament-performance-audit` skill after the final evidence is known.

### Explicitly outside SP3D

- Database/schema or settings-storage migration; label/full-text indexes;
  first-class template records.
- Simultaneous-request serialization, same-owner merge policy, database locks,
  repoint-on-rename, template-level reorder, template/part lifecycle locks, or
  dirty-draft recovery after full remount.
- Group/slice reads, changed-fields-only saves/change-set contract, persistent
  reference caches, and public live preview; these remain SP4.
- Activity-log dependency/install and LOG1; ADM1-A/B copy/reorder work.
- Production role/mode/cache/normalize/mail/DNS actions and the Google probe.
- ZIP imports, `transcript_file`, EP1 per-user preferences, paste conventions,
  P2/P3, and post-13 arcs.

### Recommended approval decisions

1. Approve the hybrid regression model: deterministic caps in normal tests,
   structural browser caps in Pest Browser, and wall-clock/heap/listener caps in
   a fixed performance profile.
2. Approve replacement of the legacy page with a named compatibility adapter,
   not deletion of old links.
3. Approve owner-specific schema providers as the definition of “monolith
   deletion,” rather than retaining the giant trait with only dead code removed.
4. Approve the missing mirrored performance-audit skill as SP3D closeout scope.
5. Ratify or amend the lock shortlist, SP3A Select decisions, and LENS1 label
   table as recorded above.

This is a scope evidence report only. It intentionally does not prescribe an
implementation sequence, file-by-file plan, prompt text, or test command list.
