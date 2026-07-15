# SP3D Pre-Research: Reconciled Settings-Series Evidence and Architecture Decisions

Date: 2026-07-16

Status: research-only evidence base for operator and Fable review

Scope boundary: documentation only; no application code, tests, schema changes,
dependency changes, implementation plan, or execution prompt

## Controlling record

This revision supersedes the 2026-07-15 snapshot wherever that snapshot called
an operator decision pending or assumed that Card Templates and Public Forms
would remain settings JSON through SP3D. The controlling source is the complete
Codex task **“Compile SP3D evidence base”**, 2026-07-15–16, thread
`019f66b6-443d-7662-a235-4a1c83b88dfb`. The task transcript was read directly
from Codex's local session record after the operator reported lost context.

The report also reconciles the committed SP3A/B/C record, review reports,
handoffs, lessons, ledger/current state, adjacent mini-task handoffs, current
code/schema, older Codex task history, the installed
`filament-performance-audit` and `filament-forms-ux-audit` skills, Laravel Boost
installed-version documentation, and current official Filament 5 / Livewire 4
documentation.

The installed source of truth is Laravel 13.19.0, Filament 5.6.7, and Livewire
4.3.3. Relevant current primary guidance:

- Filament Builder supports JSON-backed block structures and preview summaries:
  <https://filamentphp.com/docs/5.x/forms/builder>.
- Custom-data tables must own their search, filtering, sorting, and pagination:
  <https://filamentphp.com/docs/5.x/tables/custom-data>.
- Livewire public state is dehydrated and must be treated as untrusted input:
  <https://livewire.laravel.com/docs/4.x/properties> and
  <https://livewire.laravel.com/docs/4.x/security>.
- Lazy components and islands add requests and state-consistency constraints;
  they are not free performance wins:
  <https://livewire.laravel.com/docs/4.x/lazy> and
  <https://livewire.laravel.com/docs/4.x/islands>.

## Executive conclusion

SP3D cannot be scoped as a direct cleanup of the current settings monolith.
The operator approved ARCH1 as a prerequisite architecture step: Card Templates
and Public Forms first leave `PublicContentSettings` and become normal,
independently versioned model/Resource aggregates. Only after that migration is
accepted should SP3D delete the obsolete settings monolith branches and enforce
durable budgets against the architecture that will remain.

The approved order is:

```text
ARCH1 research/implementation/acceptance
    -> SP3D monolith deletion + calibrated durable budgets
    -> SP4 surviving-settings slice reads/change sets + remaining previews
    -> LOG1 activity log consuming safe change sets
```

SP3 is still objectively open because authenticated browser evidence was never
collected, SP3B's DOM/TTFB targets remain unclassified, and not every SP3C
component maximum is a literal assertion. The operator approved a
calibration-first hybrid gate and required all three browser mechanisms to be
repaired and proven: authenticated in-app browser, serial Pest Browser, and the
external Playwright/Node harness. Codex runs the checklist first; the operator
then performs the manual acceptance pass.

The former lock, Select, localization, LENS review, production sequence, SP4,
LOG1, ADM1, deferral, and ARCH1 questions are no longer open. They are recorded
below. The context-lost Group 13 draft was interrupted and discarded; it must
not be treated as approved or as a standing questionnaire.

## Decisions made in this task

### Settings, locks, localization, labels, and browser gates

| ID | Operator decision | Consequence |
|---|---|---|
| V1 | Approve exactly six important field locks for now; add others only as needed. Keep one section lock per settings section and no record/Builder/nested-child locks. | The six fields are `maintenance.enabled`, `maintenance.raw_html_override`, `public_forms.require_email_verification`, `transcription_policy.public_mode`, `transcription_policy.count_mode`, and `transcription_policy.show_multiple_transcriptions_on_item_page`. `AdminUxSettings.transcription_mode` remains outside lifecycle/import locks while Admin UX is not registered there. |
| V2 | Tiny finite sets prefer native controls by default, unless required functionality needs a custom/non-native select or HTML/custom rendering. Growing sources remain async, non-preloaded, and capped at 50. | The former SP3A Select veto is settled. Existing classifications must be re-audited against this policy; browser keyboard/RTL/DOM behavior remains part of the evidence run. |
| V3 / U3 | Do not approve the LENS1 269-row table wholesale. Review it in page/domain packs of roughly 25–40 rows showing key, Hebrew, English, context, and decision; preserve the operator-corrected operational columns. | LENS review is a scheduled manual-review mini-task, not an unresolved binary veto and not SP3D scope unless it changes a measured settings page. |
| L10N-SET1 | Put all surviving settings UI strings in dedicated `lang/he/settings.php` and `lang/en/settings.php`. | Run after ARCH1 establishes ownership and before final browser byte baselines, so moved Resource copy is not misclassified as settings copy. |
| E1 | Codex authenticated checklist first, followed by the operator's manual acceptance pass. | Automated evidence does not replace operator acceptance. |
| E2 | Use a hybrid enforcement model and repair/prove authenticated in-app browser, serial Pest Browser, and external Playwright/Node browser integration. | Deterministic gates and browser gates remain separate, with cross-tool sanity checks. Any dependency change still requires explicit approval. |
| E3 | Calibration-first dual gate. | Use two full fixed-environment samples; exact query/read/lifecycle caps; deterministic component caps at measured maximum plus 10%; browser DOM must satisfy both the existing product target `<3000` and a regression ceiling; fixed-runner warm median TTFB target `<800ms`; heap/listeners remain advisory until repeatable. No unseen numeric ceiling is approved. |
| E4 | Use a production-shaped fixture with 100 configured Card Templates across families, registry defaults/presets, and 50 referencing Homepage Sections. If it fails, stop and present options; pagination is not preauthorized. | Under ARCH1 this is migration/cutover and final Resource evidence, not justification for preserving the current unpaginated settings-backed library. |
| U1 | ADM1-A follows ARCH1: audit/wireframe first, then reorder surviving forms and tables. | Do not reorder surfaces that ARCH1 will remove. |
| U2 | Use `?` hint icons for secondary explanations; keep destructive, security, required, validation, and irreversible guidance visible. Hebrew is simplified but semantically equal to English and accessible. | Scheduled ADM1-B copy/UX work. |

### Production, external probe, preservation, SP4, and LOG1

| ID | Operator decision | Consequence |
|---|---|---|
| P1 | Approved production order: assign intended super-admin; set `transcription_mode=single`; run settings normalize dry-run; configure `FORMS_OTP_*`, Resend keys, and domain DNS; test mail. | These remain operator actions under production per-action safety. No secrets belong in tracked docs. |
| P2 | Verify Redis/scoping and enable validated settings cache through the deployment path. | Exact production mutations still require per-action approval; do not create a second cache authority. |
| P3 | OAuth exists; no missing custom importer UI was found, and the service-account connection type already exists. Create/configure a connection using the operator's local credentials, then run the existing Artisan probe after all SP steps and intervening mini-tasks. | The probe is deliberately sequenced before WB planning, not now. |
| WB-PROBE-HF1 | Before the real probe, provide operator-friendly connection selection, sanitized tracked findings/private local excerpts, refresh, and partial-failure/resume reporting. | Approved mini-task after SP/minis and before the probe. The existing command shape is `php artisan importer:probe-formats CONNECTION_ID --file=/private/tmp/podtext-probe-sample.json --limit=20`. |
| A1 | Delete the surviving monolithic schema only after preserving every existing value through restructuring. | Canonical export/backup first; surviving settings must round-trip; moved Templates/Forms migrate and verify before legacy roots retire; rollback/failure must remain recoverable. |
| A2 | Keep an old-URL/deep-link compatibility adapter only if it does not constrain improvements. | Adapter may redirect only; it owns no schema, storage, navigation, or UI. |
| S1 | SP4 uses group/slice-scoped reads and server-calculated changed-fields-only saves against fresh normalized stored state. | Client dirty paths are not authority. Preserve hidden/sibling values, one lifecycle, current cache authority, and emit a safe change set for LOG1. |
| S2 | Prefer an owned PodText preview using real sanitized renderers and unsaved state; research LaravelDaily, FilamentDaily, and FilamentExamples patterns. | Do not add `filament-peek` without a later concrete benefit and dependency approval. Template/Form previews move into ARCH1; remaining settings preview belongs to SP4. |
| S3 | LOG1 may use `spatie/laravel-activitylog` after SP4 change sets, subject to exact Composer approval. Use the operator's `ikc-f4` ActivityLog page as a reference. | Log safe actor/operation/owner/paths; never secrets, trusted HTML, private transcripts, service-account data, raw request capture, or unbounded growing preloads. |
| D1 | Keep ZIP imports, `transcript_file`, EP1 per-user preferences, paste cleanup/`[]` conventions until the probe, P2/P3 main-queue steps, and post-13 arcs deferred. | Record them; do not scope them into ARCH1 or SP3D. |
| D2 | Keep simultaneous same-owner serialization, DB/advisory locks, template-level reorder, template/part locks, automatic repoint-on-rename, dirty-draft full-remount recovery, and fine protected-node merges outside SP3D unless ARCH1 changes the premise. | ARCH1 supersedes some current implementation boundaries, but it does not silently approve these features. |

### ARCH1: approved blank-sheet architecture

The operator explicitly requested unconstrained blank-sheet research: decide
what would be best for a growing Template Builder and Public Forms system even
without the old settings policy. The resulting approved boundary is:

- `CardTemplate` parent plus immutable `CardTemplateRevision` records whose
  validated parts/children remain owned JSON.
- `PublicForm` parent plus immutable `PublicFormRevision` records whose
  validated fields/options remain owned JSON.
- `PublicFormSubmission` references the exact published form revision and also
  retains stable form/field snapshots needed to interpret the submission.
- Normal paginated Filament Resources provide list/search/filter/archive and a
  one-record Builder editor; settings custom-data tables are not the durable
  home for these growing aggregates.

| ID | Approved choice | Durable rule |
|---|---|---|
| ARCH1-A | Hybrid entity rule | Independently listable, editable, referenceable, auditable things become models/Resources. Bounded global policy remains typed Spatie Settings. Owned nested content remains validated JSON. |
| ARCH1-B | Move Card Templates and Public Forms now; Menu later. Keep About/Team temporarily. | Future Pages records/sections/settings eventually replace temporary Home/About content; that future does not delay moving Templates/Forms. |
| ARCH1-C | JSON children | Template parts/nested children and form fields/options remain validated JSON within immutable revisions. |
| ARCH1-D | Protected system presets | Application updates offer new preset revisions; never silently replace the active operator revision. Activation/clone is explicit. |
| ARCH1-E | Immutable revisions | Both aggregates have draft/preview/publish/archive/revision history. Public runtime reads an atomic published pointer; rollback creates a new revision. |
| ARCH1-F | Stable identity | Immutable portable ULID plus human semantic key. |
| ARCH1-G | Retention/deletion | Archive/soft-delete parents; protect references; never cascade away submission-associated forms/revisions. |
| ARCH1-H | Submission binding | Store `public_form_id`, exact `public_form_revision_id`, form key/name snapshots, and values keyed by stable field IDs/keys; do not copy the full schema into every submission. |
| ARCH1-I | Content localization | Revision JSON stores operator content locale maps: Hebrew required, English optional with explicit Hebrew fallback. Application UI copy stays in language files. |
| ARCH1-J | Portability/privacy | Configuration package exports settings, Templates, Forms, current/published revisions, presets/default mappings, and stable references. Full history is optional. PII submissions require a separate explicitly authorized export. |
| ARCH1-K | Migration | Expand/verify/cutover/contract: canonical backup, dry-run, transactional backfill, canonical/render comparison, short write pause/final hash sync, switch authority, one accepted rollback release with legacy JSON read-only, then delete roots. Never indefinite dual-write. |
| ARCH1-L | Concurrency | Optimistic editing uses base revision/checksum and preserves stale drafts. Publication locks only the parent transactionally and atomically changes the published pointer. |
| ARCH1-M | References | Local database consumers use normal nullable foreign keys. Portable packages use immutable ULIDs; temporary settings consumers use validated ULIDs. Semantic keys are labels, not authority. |
| ARCH1-N | Lifecycle | One after-commit configuration-change coordinator covers settings, Resources, publish/archive, import, and restore; emits a safe change set, invalidates caches, and produces one composite backup. Batch imports suppress per-row event storms. LOG1 consumes this later. |
| ARCH1-O | Locks | Dataset-level locks only for Templates and Forms during import/restore. Preserve the six sensitive settings locks. Do not add record/revision/node locks. |
| ARCH1-P | Protected revision data | Unauthorized actors may receive safe parent metadata/preview only if separately allowed; protected revision JSON never hydrates and cannot be edited, cloned, published, or exported. |
| ARCH1-Q | Uploads | Defer uploads. A later attachment model must define private storage, MIME/size checks, scanning, retention, and authorized download. |
| ARCH1-R | Retention | Keep published, referenced, and current history. Permit configurable bounded pruning only for unreferenced superseded drafts, after composite backup and report. |
| ARCH1-S | Sequence | ARCH1 acceptance precedes SP3D enforcement; SP4 follows SP3D; LOG1 follows SP4. |

Owned previews use the real renderer and normalized unsaved state. Refresh is
explicit or change/blur based, not every keystroke. Public Form preview is
non-submittable and must not send mail, issue OTPs, consume rate limits, or
create submissions.

### Blank-sheet alternatives considered

| Storage/mechanism | What it does well | Why it is not the recommendation |
|---|---|---|
| One settings array per library | Simple backup/import and no new tables | Whole-library hydration/write growth, weak independent identity/querying, no exact published history, and no durable submission-to-definition provenance. This is the current constraint ARCH1 removes. |
| One mutable row per Template/Form | Normal Resource pagination and references | Mutation overwrites the historical definition. It cannot reliably explain what public content or a submission used, and rollback becomes destructive copying. |
| Parent plus immutable revision document | Independent identity/querying on the parent; atomic published pointer; exact history; portable identity; one owned Builder document per revision | Requires a deliberate migration and coordinator, but directly satisfies the actual growth, publication, audit, rollback, and submission-history requirements. **Recommended and approved.** |
| Fully normalized row per field/part/node | Fine SQL querying of every node | Nodes do not have an independent lifecycle and ordering/nesting becomes relationally noisy. It increases joins, write complexity, and migration surface without a demonstrated query requirement. |
| Event-sourced aggregate | Complete event history and replay | Adds projection, replay, event-versioning, privacy, and operational complexity beyond current needs. Immutable revisions already provide the required history and rollback semantics. |

Parent plus immutable revision JSON wins because the parent is the independently
managed entity while the nested Builder document is an owned versioned value.
This is the hybrid boundary requested by ARCH1-A, not a compromise inherited
from the old JSON-settings policy.

### Current-to-target authority map

ARCH1 is broader than moving two form classes. Today
`SettingsSubjectOwnershipRegistry`, `PublicFrontConfigRegistry`, the validator,
reader/render context, lifecycle schema/overlay, settings package, import locks,
backup manager, cache invalidation, resolvers, and renderers all assume
`card_templates` and `public_forms` are settings roots. References also exist in
`HomepageSection.display_config` and Menu/About/Maintenance/podcast settings as
semantic template/form keys. ARCH1 must migrate every authority and reference,
then SP3D may delete the dead settings schemas/writers/projectors. The bounded
`public_forms.require_email_verification` policy remains settings and retains
its approved lock.

Two no-loss limitations require honest migration reporting:

- Existing submissions have a `form_key`, name snapshot, payload/status/hashes,
  but no exact historical definition or revision reference. Migration should
  consult available settings-backup history where possible. Where exact
  provenance cannot be proved, preserve the payload/snapshots and bind only to
  a clearly marked legacy/unverified cutover revision; never fabricate exact
  historical provenance.
- Existing Templates combine code-virtual defaults, configured overrides, and
  semantic family/key references without draft/published pointers. Backfill
  must synthesize initial revisions/default mappings, explicitly map currently
  live versus disabled/draft state, and stop/report duplicate, corrupt, or
  ambiguous identities rather than guessing.

### Discarded, unapproved question draft

The assistant presented a Group 13 question set after losing context. The
operator stopped it and required the research to restart. The proposed Template
draft-save trigger, publish/default/preset authority, clone-source semantics,
and manual ordering questions are therefore neither approved decisions nor an
established standing questionnaire. Fresh ARCH1 research may conclude that
some are implementation-derived rather than operator choices. Any future PII
retention rule likewise requires legal/product evidence; this task did not ask
or settle it.

## Current code and schema reality

Today, before ARCH1 implementation:

- one `App\Settings\PublicContentSettings` group still owns ordinary settings,
  Card Templates, and Public Forms configuration;
- `BuildsPublicContentSettingsSubjectSchemas` remains a 2,477-line monolithic
  trait with old `Tab` factories and obsolete whole-list template Builder code;
- eight focused owner pages and `ManagePublicForms` extract their visible
  schemas from that trait;
- `CardTemplateSettings` is an unpaginated custom-data library with hidden
  create/edit pages and `CardTemplateFocusedWriter` mutating one template in a
  fresh full settings snapshot;
- `public_form_submissions` exists but has no form or revision foreign key; it
  stores `form_key`, a name snapshot, payload/status/hashes, and related data;
- no `CardTemplate`, `CardTemplateRevision`, `PublicForm`, or
  `PublicFormRevision` model/table exists;
- the hidden legacy page is already only a redirect, but its class name remains
  misleading;
- lifecycle SHA authority remains the executable value
  `61e551a60016b1ac0c9aa8051463818adf31677bea465ac0e9b269fe3d2386b8`;
  `05-sp3c-research.md` contains the one stale conflicting SHA.

This current implementation remains the migration source and rollback source;
it is not the approved durable endpoint.

## Reconciled inventory

Verdicts add `approved-prerequisite` because ARCH1 is approved and must happen
before SP3D, while remaining a separate architecture scope.

Inventory IDs are prefixed `INV-` to avoid colliding with this task's decision
question IDs.

| ID | Item | Origin | Current state / actual requirement today | Verdict | Reason / dependency / risk |
|---|---|---|---|---|---|
| INV-P1 | Objective slowness closure | Initial SP3 diagnosis/review | Server/component shape improved; no authenticated final-architecture browser closure exists. | `needed-in-SP3D` | Close only after ARCH1 and E1–E3 evidence. |
| INV-P2 | Authenticated browser run | SP3A protocol; SP3B blocked samples; SP3C handoff | Not performed. | `operator-task` | Codex first repairs/runs the three harnesses in SP3D; operator then performs manual acceptance. |
| INV-P3 | Ordinary-page DOM `<3000` and warm median TTFB `<800ms` | SP3B handoff | Still unclassified. | `needed-in-SP3D` | Calibrate final Resources/surviving policy pages; fixed runner for TTFB. |
| INV-P4 | SP3C browser budgets and real flows | SP3C handoff | DOM/listeners/heap/TTFB/modal/navigation/Back remain unmeasured. | `needed-in-SP3D` | Existing component evidence is not browser evidence. |
| INV-P5 | Durable browser regression | Original SP3D acceptance contract | Manual console helper exists; three automation paths need proof/repair. | `needed-in-SP3D` | Hybrid gate approved by E2. |
| INV-P6 | Literal SP3C component caps | SP3C canary/handoff | Library and unselected element caps are literal; other recorded maxima are not all assertions. | `needed-in-SP3D` | Recalibrate final surfaces; keep historical caps as migration comparisons. |
| INV-P7 | Delete schema monolith | Original SP3D cleanup/current code | 2,477-line trait still mixes all settings, dead Template Builder, and Form schemas. | `needed-in-SP3D` | ARCH1 must first move definitions; then remove dead branches and split surviving owner providers. |
| INV-P8 | Retire misleading legacy page but retain links | Original SP3D cleanup | Current class is already a hidden authorized redirect. | `needed-in-SP3D` | Rename/replace with redirect-only adapter; no schema/storage/navigation ownership. |
| INV-P9 | Direct authorization matrix | SP3D integrity list | SP3B/C server coverage exists. | `already-resolved` | Retarget through Resources/provider moves; preserve protected revision policy. |
| INV-P10 | Save without hydrating optional/foreign state | SP3D integrity list | Existing server regressions preserve siblings/hidden paths. | `already-resolved` | ARCH1-P strengthens protected revision non-hydration; do not trust Livewire state. |
| INV-P11 | Back/dirty/history browser behavior | SP3B/C deferred browser checks | Hash/dirty logic has lower-level coverage; real dialog/history flow does not. | `needed-in-SP3D` | Distinct from deferred full-remount recovery. |
| INV-P12 | Relocate tests without coverage loss | SP3D scope | Final monolith/Resource transition has not happened. | `needed-in-SP3D` | Preserve ownership, lifecycle, migration, reference, renderer, and authorization evidence. |
| INV-P13 | Durable performance lesson | SP3D scope | No final SP3 closure lesson exists. | `needed-in-SP3D` | Write only after accepted final browser evidence. |
| INV-P14 | Measurement instrumentation | SP3A/SP3C | Fixture, middleware/profiler, helper, save refusal, and subjects exist. | `already-resolved` | Reuse safely; never log values, PII, HTML, credentials, or uploads. |
| INV-P15 | Lifecycle SHA documentation drift | SP3C research versus executable regression | The stale research SHA is corrected in this docs run; executable SHA remains unchanged. | `already-resolved` | Historical executable bytes remain authority. |
| INV-P16 | Current-state/ledger drift | Committed active docs | Stale SP3C-not-started and OTP current-run entries were present. | `already-resolved` | This reconciliation removes/replaces them without rewriting shipped history. |
| INV-P17 | High-cardinality Template budget | Performance skill audit | Current real library is unpaginated; old 100-row canary is synthetic. | `approved-prerequisite` and `needed-in-SP3D` | E4 fixture validates migration and final paginated Resource; failure returns to operator. |
| INV-A1 | Important-lock veto | Initial Codex task/SP3A | Six sensitive field locks plus section locks exist. | `already-resolved` | V1 explicitly ratifies exactly the six for now. |
| INV-A2 | Select-classification veto | SP3A/Fable OPEN | Implementation exists; operator policy was previously absent. | `already-resolved` | V2 now governs native tiny/custom-required/growing async behavior; browser validation remains. |
| INV-A3 | Label-column indexes | SP3A deferred note | No new indexes; contains searches remain constrained/capped. | `defer-with-reason` | Measure and choose search semantics first; leading-wildcard contains does not use a normal B-tree. |
| INV-A4 | Admin UX lifecycle/import lock | Initial conditional lock proposal | Admin UX remains outside lifecycle/import registration. | `already-resolved` | Revisit only if ownership later changes explicitly. |
| INV-A5 | SP3A manual front check | SP3A handoff | No committed operator completion record. | `operator-task` | Fold retained checks into final Codex checklist/operator pass. |
| INV-B1 | Same-owner concurrent saves | SP3B deferred boundary | Current settings saves are sequentially stale-aware, not serialized. | `defer-with-reason` | D2 excludes general serialization; ARCH1-L locks parent only at publication. |
| INV-B2 | Card Template redesign | SP3B deferred to SP3C | SP3C shipped focused editor/library; ARCH1 supersedes storage future. | `already-resolved` historically | Preserve as migration/rollback source until cutover. |
| INV-B3 | SP3B manual front check | SP3B handoff | Automated coverage exists; operator run not recorded. | `operator-task` | Fold retained visual/interaction checks into E1/E2. |
| INV-C1 | Builder preview canary verdict | SP3C stop/go gate | Native summary passed with >93% wrapper/control reduction. | `already-resolved` | Historical component result, not full public preview/browser proof. |
| INV-C2 | Template-level reorder | SP3C deferred list | Not present. | `defer-with-reason` | D2 retains deferral; the discarded Group 13 draft is not an approval. |
| INV-C3 | Template/part lifecycle locks | SP3C deferred list | No fine locks. | `already-resolved` as a boundary | ARCH1-O approves dataset import/restore locks only. |
| INV-C4 | Automatic repoint on semantic-key rename | SP3C deferred list | Current references are semantic keys and block unsafe changes. | `approved-prerequisite` in part | ARCH1 migrates authority to local FKs/portable ULIDs; automatic semantic-key repoint remains unapproved. |
| INV-C5 | Persistent where-used cache | SP3C review/deferred list | Request-local scan only. | `defer-with-reason` | Re-evaluate after new model queries; coordinator must remain single invalidation authority. |
| INV-C6 | Dirty draft recovery after full remount | SP3C deferred list | Not promised. | `defer-with-reason` | Immutable stored drafts do not preserve unsaved browser state. |
| INV-C7 | Current writer scan-to-save race | SP3C claim boundary | Two simultaneous legacy writes can pass pre-save checks. | `defer-with-reason` | Current writer retires after ARCH1; do not mislabel sequential fingerprinting as a lock. |
| INV-C8 | First-class Template/Form records | SP3 review deferred option | No definition/revision models or tables exist. | `approved-prerequisite` | ARCH1-A–S now require them before SP3D. |
| INV-C9 | SP3C manual front check | SP3C handoff | Real library/editor/modal/Back acceptance not recorded. | `operator-task` | Reframe against final Resources after ARCH1. |
| INV-C10 | Fine protected-node merge | SP3C later-product note | Current shell restores hidden parts; no node merge. | `defer-with-reason` | ARCH1-P instead protects whole revision JSON from hydration/actions. |
| INV-S1 | SP4 slice-scoped reads | SP3 review/Fable SCHEDULED | Reader still validates/returns full config. | `defer-with-reason` | Approved after SP3D for surviving singleton settings only. |
| INV-S2 | SP4 changed-fields-only save/change set | Fable SCHEDULED | Settings save still overlays owned roots into fresh full snapshot. | `defer-with-reason` | Server computes normalized diff; client dirty paths are not authority. |
| INV-S3 | Live preview decision | SP3 review/Fable SCHEDULED | No full preview; only Builder summaries. | `approved-prerequisite` and `defer-with-reason` | ARCH1 owns Template/Form preview; SP4 owns remaining settings preview. |
| INV-S4 | LOG1 activity log | Fable SCHEDULED | Package absent. | `defer-with-reason` | Approved in principle after coordinator/SP4; exact Composer approval later. |
| INV-S5 | ADM1-A form/table reorder | Fable SCHEDULED | No dedicated implementation. | `defer-with-reason` | U1 requires post-ARCH1 audit/wireframe of surviving surfaces. |
| INV-S6 | ADM1-B hints/simplified Hebrew | SP3 review/Fable SCHEDULED | Broad copy/layout work not implemented. | `defer-with-reason` | U2 defines visible-warning and accessible-hint boundary. |
| INV-O1 | LENS1 269-row review | Fable OPEN/LENS1 | Corrected operational columns shipped; remainder not accepted wholesale. | `operator-task` | V3/U3 require chunked manual review packs. |
| INV-O2 | Production super-admin/single mode | ROLES1/LENS1 handoffs | Commands/settings exist; live state unknown. | `operator-task` | First steps in approved P1 sequence. |
| INV-O3 | Production normalize dry-run | SP2 handoff | Command exists; no production run recorded. | `operator-task` | Dry-run first; apply is a separate approved mutation. |
| INV-O4 | Production OTP/Resend/DNS | MAIL1/OTP handoffs | Config keys/package exist; live values/DNS unknown. | `operator-task` | Secrets remain local; deploy/cache/worker actions require approval. |
| INV-O5 | Google service-account probe | WB1/Fable OPEN | Connection type and probe exist; findings still have zero documents. | `operator-task` | Create/configure the connection after SP/minis and WB-PROBE-HF1; never track credentials. |
| INV-O6 | Production validated-settings cache | P1 handoff | Cache feature/flag exist; live store/flag unknown. | `operator-task` | Verify Redis/scoping and enable through approved deployment path. |
| INV-VER1 | AGENTS commit-message rule | Fable VERIFY | Prefix/imperative/backfill/no-hook rules are present. | `already-resolved` | Preserve evergreen. |
| INV-VER2 | Mirrored Filament audit skills committed | Fable VERIFY/operator skills task | Canonical packages and discovery symlinks are staged in this deliverable. | `already-resolved` after this commit | Do not claim committed if the final commit fails. |
| INV-VER3 | Logo/favicon ledger row | Fable VERIFY | UI-HF1 row/commit exists. | `already-resolved` | No duplicate row. |
| INV-VER4 | Collapsible-nav ledger row | Fable VERIFY | UI-HF2 row/commit exists. | `already-resolved` | No duplicate row. |
| INV-D1 | ZIP import and `transcript_file` | Known-deferred list | Export-only ZIP exists; import package support absent. | `defer-with-reason` | Requires explicit safe manifest/security design. |
| INV-D2 | EP1 per-user preference | Known-deferred list | Global preference only. | `defer-with-reason` | Separate future schema/precedence task. |
| INV-D3 | Paste cleanup/`[]` conventions | Known-deferred list | Rules intentionally absent. | `defer-with-reason` | Wait for real Google probe evidence. |
| INV-D4 | P2/P3 main queue | Ledger | Still pending. | `defer-with-reason` | Preserve queue; unrelated to settings closure. |
| INV-D5 | Post-13 arcs | Ledger/backlog | Still future-gated. | `defer-with-reason` | Do not rescope through ARCH1/SP3D. |
| INV-M1 | Curator reload selections | Between SP3B/C Codex task | CURATOR-HF1 shipped. | `already-resolved` | Preserve raw-path/legacy behavior. |
| INV-M2 | Collapsible groups/icons | Between SP3B/C Codex task | UI-HF2 shipped; icons exist. | `already-resolved` | Preserve central order. |
| INV-M3 | PAO update | SP3B Codex task | Installed version already current; no diff. | `already-resolved` | No dependency action. |
| INV-M4 | OTP logical inline-end | OTP mini-task | OTP-POLICY1 shipped. | `already-resolved` | Preserve RTL/LTR logical order. |
| INV-M5 | Avoid unnecessary task expansion | Horizon/OTP sessions | Requested narrow changes were completed. | `already-resolved` | Standing scope-discipline signal, not feature work. |
| INV-M6 | Validated whole-config cache | SP3 cache deferral/P1 | Shipped behind flag with watermark/fallback/invalidation. | `already-resolved` | Production enablement is INV-O6; SP4 must not duplicate authority. |
| INV-L10N | Dedicated settings locale files | Active task L10N-SET1 | Not implemented. | `defer-with-reason` | Apply after ARCH1 ownership and before final measured copy baseline. |
| INV-U1 | ADM post-ARCH1 audit/reorder | Active task U1 | Approved, not implemented. | `defer-with-reason` | Only surviving forms/tables are reviewed. |
| INV-U2 | Hint-icon/visible-warning rule | Active task U2 | Approved, not implemented. | `defer-with-reason` | Accessibility and semantic HE/EN equality required. |
| INV-U3 | Chunked LENS review pack | Active task U3 | Approved, not produced. | `operator-task` | Operator reviews roughly 25–40 entries per page/domain pack. |
| INV-WBHF1 | Probe hardening | Active task WB-PROBE-HF1 | Not implemented. | `defer-with-reason` | Approved after SP/minis and before the real probe. |
| INV-ARCH1 | Versioned Template/Form aggregate architecture | Active task ARCH1-A–S | Fully approved; absent from code/schema. | `approved-prerequisite` | Research/implementation/no-loss acceptance precedes SP3D; individual decisions are recorded above. |

## Codex session-history findings

The audit used Codex's local task history rather than memory. The active task is
included because it is now the controlling source for the decisions above.

| Session citation | Operator point | Reconciliation |
|---|---|---|
| **“Compile SP3D evidence base”**, 2026-07-15–16, thread `019f66b6-443d-7662-a235-4a1c83b88dfb` | Approved V1–V3, L10N-SET1, E1–E4, P1–P3, WB-PROBE-HF1, U1–U3, A1–A2, S1–S3, D1–D2, and ARCH1-A–S; instructed that Templates/Forms become independent storage/Resources and that research be restarted from the session transcript. | This report is the reconciliation. The context-lost Group 13 draft was halted and is neither approved nor a standing questionnaire. |
| **“Initial Settings slowness debug - sp3a-d planned”**, 2026-07-14, thread `019f5e8b-663c-78b1-984c-b708c542e4af` | One section lock plus proposed important locks were “pending your veto”; preview, group reads, hint icons, and simplified Hebrew were queued. | V1, S1/S2, U2, and L10N-SET1 now settle or schedule these points. |
| **“Implement settings SP3A, plan SP3B v3”**, 2026-07-14, thread `019f5ee1-ba53-71b3-963b-e2db4716ab2a` | Complete Select classification shipped without an operator row-by-row acceptance. | V2 now settles the policy. Re-audit implementation against it; do not reopen the veto. |
| **“LENS1 step fix - hide multi transcriptions”**, 2026-07-14, thread `019f5e31-2506-7a61-a25b-7e763d25f8b9`, turn `019f5e37-2428-7fd1-af74-46bc0afaafa2` | Preserve relation-manager and item/group featured/count operational columns. | Shipped; V3/U3 define review of the remaining table. |
| **“SP3B v3 implementation - Fix owned-path save contract”**, 2026-07-14–15, thread `019f6249-30a4-7aa1-adb8-55aa0736adbf` | PAO check, Curator reload bug, collapsible groups/icons, separate Curator documentation/commit, and verified nav commit. | PAO was a no-op; CURATOR-HF1 and UI-HF2 shipped; no orphan remains. |
| **“Update OTP policy config”**, 2026-07-15, thread `019f634e-111d-70a1-9e30-4729a5cb9dc2`, turn `019f636b-25fb-72c1-b21d-3a0aafb5b1c5` | OTP action belongs at logical inline-end in RTL. | OTP-POLICY1 shipped; resolved. |
| **“Troubleshoot Herd mail queue”**, 2026-07-14, thread `019f6219-5f99-7ce3-b26b-3c1a397dd335` | Do only the requested Horizon 64→128 change; stop unnecessary expansion. | Resolved in that mini-task; retained as scope-discipline guidance. |
| **“Execute settings SP3C prompt”**, 2026-07-15, thread `019f6437-2e48-7e31-9f39-ed527593a8c6` | No new mid-run request; final handoff left browser acceptance pending. | E1–E4 now define how it closes after ARCH1. |

No additional unresolved operator complaint from the searched mini-task sessions
survived reconciliation beyond the explicit operator tasks listed in this
report.

## Intended end-state wireframe

```text
CURRENT MIGRATION SOURCE
PublicContentSettings JSON
  ordinary settings + templates + public forms
               |
               v
ARCH1 expand / verify / cut over / contract
  CardTemplate ──> immutable CardTemplateRevision(parts JSON)
  PublicForm ─────> immutable PublicFormRevision(fields JSON)
       |                         |
       |                         v
       |              PublicFormSubmission -> exact revision
       v
normal paginated Filament Resources
one-record Builder editors + safe owned previews
published pointers + local FKs + portable ULIDs
               |
               v
SP3D RETAINED SETTINGS SURFACES
Homepage / Display / Episode / Menu / Podcast / Contributor /
About / Maintenance / Public Forms Policy
(+ temporary About/Team ownership as decided)
  -> owner-specific schema providers
  -> SettingsSubjectOwnershipRegistry
  -> no monolithic Tab trait or dead Template/Form settings branches
  -> redirect-only old URL compatibility adapter
               |
               v
DURABLE REGRESSION
normal suite: exact ownership/query/read/lifecycle + calibrated component caps
browser suite: authenticated DOM/console/routes/modal/Back + product target
fixed profile: TTFB; heap/listeners advisory until repeatable
               |
               v
SP4: surviving-settings slice reads + authoritative safe change set
LOG1: activity log consumes coordinator/change sets after dependency approval
```

### Retained, replaced, and deleted boundaries

| Boundary | Intended result |
|---|---|
| `App\Settings\PublicContentSettings` | Remains authoritative only for surviving bounded global settings until future Pages/Menu work changes ownership. |
| Eight focused subject pages | Remain unless ARCH1 ownership review removes a now-empty page; each renders only its provider/owned roots. |
| `ManagePublicForms` | Its form-definition Builder is deleted/replaced by `PublicFormResource`. A focused Public Forms Policy settings page remains (or is renamed) for bounded policy such as `public_forms.require_email_verification`, retaining the approved field lock and ownership. |
| `CardTemplateSettings`, hidden create/edit pages, focused JSON writer | Replaced by `CardTemplateResource` and revision workflow after verified migration. |
| Monolithic schema trait and all `Tab` wrappers | Deleted after Template/Form extraction; small focused factories may remain only where genuinely shared by surviving settings. |
| Legacy page/deep links | Redirect through a named, hidden, authorized adapter with no form/storage/navigation ownership. |
| Lifecycle/import/backup/cache machinery | Reconciled through ARCH1-N's one after-commit coordinator and composite backup; no dual event authority. |

## Durable budget recommendation already approved

1. Calibrate twice on the fixed production-shaped state and runner.
2. Freeze exact deterministic query, settings-read, lifecycle, ownership, and
   duplicate-load caps.
3. Freeze component elements/HTML/state at the observed passing maximum plus
   10%, separately per surface/state.
4. Enforce browser DOM against both the existing product target `<3000` and a
   calibrated regression ceiling. A page may not pass merely because a new
   ceiling is looser than the product goal.
5. Enforce warm median TTFB `<800ms` only in the named fixed-runner profile.
6. Keep heap/listeners reported but non-blocking until two runs demonstrate
   stable measurement; present any literal proposed caps for operator approval.
7. Record initial and aggregate requests/bytes so lazy/island/deferred work
   cannot make the first response look smaller while increasing total work.

This turns the budget into a durable regression: a future change that exceeds a
literal calibrated bound fails the appropriate deterministic, browser, or
fixed-runner gate instead of merely making a historical report look worse.

## Risks and adjacent mechanisms

- **Migration integrity:** canonical export/backup, transactional backfill,
  canonical/render comparison, stable identity/reference mapping, rollback
  availability, and no indefinite dual-write are hard ARCH1 gates.
- **Submission interpretation and PII:** every submission must remain bound to
  the exact immutable form revision. Configuration portability must not become
  an implicit PII export.
- **Ownership registry:** after extraction, every surviving settings property
  must have exactly one owner. Resource aggregates must not be reintroduced as
  pseudo-settings roots.
- **Lifecycle SHA:** migrating ownership intentionally changes the composite
  lifecycle domain. Preserve the old SHA as migration-source evidence; create a
  deliberately reviewed new lifecycle contract rather than accidentally
  “fixing” bytes during provider extraction.
- **Focused writer:** keep it intact as rollback-source behavior until cutover;
  do not let SP3D resurrect whole-list settings writes. New revisions replace
  its final authority only after migration acceptance.
- **Coordinator/event authority:** ARCH1-N must prevent duplicate backups,
  invalidation, activity events, and import storms across settings and Resources.
- **Protected data:** revision JSON must not hydrate for unauthorized actors.
  Safe previews cannot leak protected nodes, trusted HTML, credentials, or PII.
- **Measurement comparability:** never compare browser DOM to parsed component
  HTML as the same metric. Fix viewport, browser/server mode, fixture, cache,
  sample order, profiler state, and runner.
- **Cardinality:** final budgets must exercise the real Eloquent Resources,
  revision resolution, references, and renderers. The old synthetic canary is
  migration evidence only.
- **Localization and ADM churn:** finish ownership-driven copy relocation and
  approved layout/copy review before freezing final byte/DOM baselines.
- **SP4/LOG1:** server-side change sets are computed from normalized fresh
  storage, never trusted client dirty paths. Audit data must be deliberately
  safe and value-minimized.

## Proposed SP3D scope for later operator/Fable approval

### Prerequisites outside SP3D

- Complete and accept ARCH1, including data-preserving migration, Resources,
  revisions, protected-data policy, exact submission revision binding, owned
  previews, coordinator, and rollback release.
- Complete ownership-dependent L10N/ADM corrections that would otherwise
  invalidate final settings browser baselines.
- Ensure the final fixture/authentication prerequisites exist without touching
  the development database; SP3D itself owns repair/proof of the three browser
  mechanisms.

### Include in SP3D

- Delete the surviving monolithic settings schema trait, all obsolete `Tab`
  wrappers, and retired Template/Form branches after ARCH1 cutover.
- Extract one schema provider per surviving settings owner plus only genuinely
  shared small factories; preserve exact ownership and authorization.
- Replace the misleading legacy page with the approved redirect-only adapter.
- Exercise, repair, and prove the authenticated in-app browser, serial Pest
  Browser, and external Playwright/Node harness, then calibrate and enforce
  every deterministic and browser budget according to
  E1–E4, including ordinary settings pages and the new Template/Form Resources.
- Preserve/retarget authorization, hidden/sibling value, lifecycle, import,
  restore, normalize, import-lock, backup, cache, renderer, revision, published
  pointer, reference, and protected-data regressions.
- Correct the stale SP3C SHA narrative and leave a durable performance lesson
  after final evidence is accepted.
- Produce the numbered Codex browser checklist and operator manual acceptance
  pass; record failures honestly rather than loosening ceilings.

### Explicitly outside SP3D

- ARCH1 schema/application implementation itself; SP4; LOG1; ADM/LENS copy
  review; WB-PROBE-HF1 and the real Google probe; production mutations.
- Any product choice later re-established by fresh ARCH1 research, same-owner general serialization, automatic
  semantic-key repointing, template-level reorder, fine node locks/merges,
  full-remount unsaved recovery, and persistent reference caches without data.
- Label indexes, uploads, ZIP import, `transcript_file`, EP1 preference, paste
  conventions, P2/P3, and post-13 arcs.

This remains a scope evidence report. It deliberately contains no
file-by-file implementation sequence, executable plan, prompt, or schema code.
