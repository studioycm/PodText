# Feature-First Decision and Checkpoint Queue

Date: 2026-07-17

Status: feature-first roadmap recovered; bounded AUTHZ command closure
complete; Step 5B specification, audit, implementation, restricted
sample-selector closure, and template-parts auto-refresh/editor UX correction
complete. No implementation is automatically selected.

Controlling plan:
`19-authz-complexity-reset-and-feature-first-master-plan.md`.

## Restart point

Read the controlling plan, current project state, and the ledger. Reports 12–18
and their handoffs are historical AUTHZ evidence, not an active remediation
queue. Do not restart at AUTHZ1-D, ARCH1, SP3D, or former Groups 16–29.

The v1 closure prompt and plan from planning commit `97627b0` were audited as
`LS-20260717-AUTHZ-01`, approved as `AUTHZ-CLOSE-O1-DELETE-3`, and implemented
on the operator-approved descendant baseline. The three reachable commands are
withheld. Do not draft or run another AUTHZ closure, remediation, or audit
chain. AUTHZ1-D–I remains cancelled.

## Accepted AUTHZ boundary

- Legacy `users.role`, ranks, Gates/macros, panel/Horizon/maintenance admission,
  and Users Resource restrictions remain authoritative.
- Shield stays unregistered; `User` stays without `HasRoles`; no compatibility
  grants, package cutover, or role UI.
- Plan 20 and the v1 closure prompt removed the three auto-discovered
  `authz:roles:*` command classes, added focused closure/legacy-authority
  regressions, and updated minimum docs.
- H-01/M-01/L-01 are outside the narrowed non-operational threat boundary once
  those commands are unreachable. They reopen if migration capability returns.
- Existing settings import locks and Card Template storage/writer remain in
  place. Card Template preview/side-panel UX does not depend on ARCH1.
- A small slice is capped at two logical tasks and four estimated engineering
  hours; exceeding either requires operator reapproval.

## Non-AUTHZ work that survives

| ID / work | Status | Trigger or next preparation | Boundary |
|---|---|---|---|
| Step 5B Card Template Admin Preview UX | Complete, including restricted sample-selector, template-parts auto-refresh, and Card Template editor UX closures | No next preparation is implied; the operator selects any later work separately | Current SP3C storage/writer and controlled presenters remain authoritative. Restricted previews hide and server-block sample lookup; accepted slide-over Builder part edits refresh after Apply, inline Builder edits and rendered presentation fields refresh automatically, and preview-only zoom/sample interactions remain transient. Sticky preview, logical-start Builder slide-over, compact header metadata, inline capped sample selection, bilingual summaries, and bilingual Cancel placement are corrected. The remembered Builder display-mode choice is browser-local only. No ARCH1, settings persistence, migration, new permission, or live-in-slide-over bridge was added. |
| Settings import locks | Implemented; preserve | Add a lock only when a concrete workflow proves it necessary | Keep the six approved important fields, section locks, import-only semantics, and no record/Builder/nested-child lock system |
| Filament Select policy | Accepted durable rule | Recheck touched forms during ordinary UX work; use browser evidence where custom controls matter | Tiny finite sets prefer native controls unless required behavior needs custom rendering; growing sources stay async, constrained, and capped |
| `MAINT-LW-UX1` | Pending independent medium UX task | Must run before a later public Livewire navigation, polling, lazy/deferred, stream, or upload expansion | Preserve the committed server `503` enforcement; improve stale-tab maintenance message/retry UX and add report-14 focused regressions |
| Step 10R-P2 | Pending; explicit selection only | Select when listing measurements or a dependent visible feature justify it | Bound fetch windows, lazy option/form-definition loading, and opt-in aggregate subselects |
| Step 10R-P3 | Pending; explicit selection only | Select when transcript-viewer measurements justify it | Derived transcript segments and word-count/render economy with safe fallback; no speculative backfill machinery |
| `WB-PROBE-HF1` | Approved conditional mini-step | Run before the private 20-document Google probe if Workbench resumes | Operator-friendly connection selection, refresh, sanitized tracked output/private excerpts, and partial-failure resume reporting |
| Google 20-document probe | Pending operator/external gate | Configure the existing service-account connection privately after `WB-PROBE-HF1`, then run and accept the probe | No credentials or private excerpts in tracked files; probe evidence precedes WB2/WB4 or paste-cleanup planning |
| LENS1 review packs | Pending operator review | Prepare page/domain packs of roughly 25–40 rows with key, HE, EN, context, and decision | Do not treat the old 269-row table as wholesale approved; preserve already-corrected operational columns |
| Production settings/cache/mail checks | Pending status confirmation, not automatic mutation | Read current production state first; request per-action approval only for work still needed | Check legacy role assignment, `transcription_mode=single`, normalize dry-run, Redis/settings-cache scoping, `FORMS_OTP_*`, Resend/DNS, and mail verification; remove AUTHZ package cutover/backfill from this checklist |
| SP3 browser acceptance evidence | Pending conditional evidence | Resume only when settings performance work or a touched UX needs measured acceptance | Authenticated in-app, serial browser, and external Playwright evidence remain distinct; no fabricated DOM/TTFB ceilings and no SP3D architecture prerequisite |
| Existing Public Front queue | Preserved, not automatic | Operator selects one bounded step at a time | P2, P3, AX1, SL1–SL4, AX2, AX3, B4, C2, and 9F-A–9F-C remain recorded; none is implied by finishing AUTHZ |
| ADM1-B presentation rule | Surviving UX rule, not a standalone automatic project | Apply when a touched form needs copy/hint cleanup | Use accessible `?` hints for secondary help; keep destructive, security, required-format, validation, and irreversible warnings visible; maintain HE/EN semantic parity |
| `SIMPLIFY-REVIEW1` | Optional Later audit; suggestions only | Select when the operator wants a current-state simplification inventory; never as a prerequisite for feature work | Read-only review of opportunities to delete, consolidate, or reuse; classify findings and estimate value/cost; no finding authorizes implementation without separate analysis and approval |

## Deferred / not current

- AUTHZ1-D–I; multiple roles; direct grants; role-management UI; delegated
  assignment; extra panels; dynamic catalog governance; package authority
  cutover; compatibility grants; production backfill/rollback; and MySQL
  migration rehearsal.
- ARCH1 model/Resource/revision migration, per-user working drafts, autosave,
  immutable checkpoints, generalized publication workflow, and collaboration
  machinery.
- SP3D monolith deletion/calibration, SP4 generalized slice/change-set
  coordinator work, and LOG1/activity-log package adoption.
- L10N-SET1 and ADM1-A as architecture-sequenced projects. Reconsider only from
  a concrete current copy/layout problem; do not restore their old ARCH1 chain.
- Broad WB2–WB7 construction without a selected near-term importer need.
- Same-owner serialization, database/advisory locks, template or part locks,
  template-level reorder, automatic semantic-key repointing, full-remount dirty
  recovery, and fine-grained protected-node merge.
- ZIP import packages, `transcript_file` imports, EP1 per-user presentation
  preference, and paste-cleanup/`[]` conventions before real probe evidence.
- Public Form uploads/notifications, generalized result-preview architecture,
  and other post-13 work until explicitly selected.

## Recovered sequence

1. Treat the roadmap recovery, bounded AUTHZ command closure, and Step 5B v1
   specification as complete.
2. The Step 5B Laravel Simplifier audits, approved implementation, restricted
   sample-selector closure, template-parts auto-refresh, and Card Template
   editor UX correction are complete. They do not select or authorize another
   feature.
3. Select one Later item at a time from the surviving register. Apply
   `MAINT-LW-UX1` before its named public Livewire trigger.
4. Run `SIMPLIFY-REVIEW1` only if explicitly selected; its suggestions do not
   interrupt the feature-first sequence or authorize cleanup.

No broad architecture question is pending, and no erased checkpoint is an
automatic implementation instruction.
