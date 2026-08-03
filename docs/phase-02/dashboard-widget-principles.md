# Dashboard Widget Principles

The why-layer above the 2R handoff's contracts: short, memorable rules for
anyone designing or building a dashboard widget. Co-created by the operator
and the route orchestrator on 2026-08-03, merging the recovered phase-1
dossier principles (ES-1–ES-7, recovered from the design session transcript —
see the combined plan's provenance section) with the lessons this route
earned. Phase 3 is the first customer.

**Admission rule.** A principle enters this doc only with: a one-line *why*
traceable to evidence, a named enforcing *guard* (test or contract) — or an
explicit "aspirational — guard wanted" marker — and a kebab slug per the
naming convention. Slugs are the canonical ids; the ordinals below are
reading order only.

**The two sections split by the kind of question they answer — not by
reader. Designers and builders read both.**

## Designing a widget

1. **`every-number-a-door` · כל מספר הוא דלת** — every stat links to a
   filterable surface showing its records; a dead-end number is a defect.
   *Operator qualifier (2026-08-03): a number never leaves the board for
   lacking a doorway — when no filterable surface exists yet, it ships
   doorway-less and the missing surface registers as work; any
   record-displaying filterable surface satisfies the door, not only full
   Resources.*
   *Why:* the pre-redesign dashboard answered nothing (dossier ES-1); the
   Board-2 reason bars proved an unpinned doorway promise rots
   (`unpinned-promise`, fixed `c36f6c4`).
   *Guard:* doorway URL-shape/behavior tests per widget; the A4 dispatch
   test.
2. **`two-truths-two-cells` · פורסם ≠ גלוי** — published and visible never
   share one widget cell; invisible = status-published minus visible,
   exactly.
   *Why:* dossier ES-2; the `unpublished_group` fourth reason existed only
   because the equality was made exact (phase 2R).
   *Guard:* the tier-ownership contracts; phase-4 consistency tests
   (decision 10).
3. **`true-zero` · אפס אמיתי, אפס שימושי** — empty and low-data states are
   designed first-class with honest copy ("no transcripts were published in
   this range", never a misleading zero); in the filling era the queue is
   the hero.
   *Why:* dossier ES-3 (evidence at authoring: 1 transcription in prod);
   the deep-search verdict confirmed ES-3 *is* the empty-state rulebook.
   *Guard:* the A3 shared empty-state partial and its per-widget tests; the
   phase-3 plan's per-widget empty states.
4. **`fresh-enough-no-pulse` · טרי מספיק, בלי דופק** — cached aggregates
   invalidated on editorial writes, explicit `pollingInterval = null`
   everywhere, a small "נכון ל־HH:mm" freshness marker instead of live
   polling.
   *Why:* dossier ES-4; the metrics cache + observer invalidation landed in
   phase 2R (decision 12).
   *Guard:* per-widget no-polling assertions; `EditorialMetricsCacheObserver`
   tests.
5. **`jerusalem-walls` · יום ירושלים** — day bucketing is Jerusalem walls
   computed in PHP, never SQL `DATE()`; dates day-first; formats and
   timezone read from their homes.
   *Why:* dossier ES-5; production MySQL vs SQLite tests diverge on database
   timezones; the near-midnight window is the only observable difference
   (`proxy-oracle`).
   *Guard:* `UiTimezonePolicyTest`, `UiFormatsPolicyTest`, the near-midnight
   fixture (`b24490a`).
6. **`rtl-home-field` · RTL היא המגרש הביתי** — the board is RTL-native;
   time axes inside charts stay left→right (locked operator decision).
   *Why:* dossier ES-6, flagged as a decision there and locked in round 1
   (decision 6: RTL browser evidence).
   *Guard:* view conventions; the phase-4 RTL browser group (on demand).
7. **`one-lens-one-question` · עדשה אחת, שאלה אחת** — each widget answers
   one question for one posture; a self-hiding observability widget may
   attend multiple lenses (the Q6 ruling is the recorded exception:
   `PublicFormTargetWarningsWidget` on Overview + Intake).
   *Why:* dossier ES-7; lens structure is what makes the range/filter
   semantics decidable (gap-filler G1).
   *Guard:* `getWidgetsForLens()` registrations and the lens/order tests.

## Building a widget

8. **`widgets-compute-nothing` · הווידג'ט לא מחשב** — every number goes
   through `EditorialMetrics`; no fresh query in a widget, and per-row costs
   are batch-primed in the service, not hidden behind a closure.
   *Why:* "one number, one source" (synthesis rule 2); the blockers queue
   ran up to 4 hidden queries per row behind a `state()` closure
   (`service-hop-cost`).
   *Guard:* the query-budget regression test; the handoff contract line.
9. **`stock-or-flow-declared` · מלאי או זרימה, מוצהר** — every widget wears
   the stock/flow header tag.
   *Why:* synthesis rule 3 kills "what does the range affect?"; two widgets
   shipped untagged while the rule was asserted per hand-listed widget.
   *Guard:* the structural lens-loop tag test in
   `DashboardOverviewLensTest`.
10. **`whole-set-contracts` · חוזה על כל הקבוצה** — a widget contract is
    enforced by a structural loop over the registrations, never by a
    hand-list of members.
    *Why:* the V1 lesson (`unrouted-enum` generalized): a contract enforced
    only on listed members protects nothing outside the list.
    *Guard:* the loop tests themselves are the pattern; new contracts adopt
    it on admission.
11. **`bounded-or-rolled-up` · תחום או מגולגל** — no silent caps: a
    breakdown's tail rolls into a reconciling "Other" row riding
    `BreakdownRow::meta`, or the bound is declared in the frame (a latest-N
    feed says so); totals must reconcile across widgets.
    *Why:* `silent-cap` — `take($limit)` dropped every podcast past six
    while the band read as a totality (fixed `b3d6de4`).
    *Guard:* the F3 roll-up tests; phase-4 reconciliation tests
    (decision 10).
12. **`state-narrows-at-the-door` · הקלט מצטמצם בדלת** — every
    browser-writable input (pageFilters, URL state, Livewire events, action
    arguments) validates before it reaches a query or a translation key.
    *Why:* `raw-state` — Filament documents `pageFilters` as user input;
    events and action calls are browser-forgeable (the A4 fix validates
    both ends via `DashboardReason::tryFrom`).
    *Guard:* `ReadsDashboardFilters` narrowing tests; the A4 validation
    tests.
13. **`speaks-both-languages` · דובר שתי שפות** — every label ships en+he
    translation keys, and their presence is asserted so a missing key
    cannot pass vacuously.
    *Why:* the house locale rule; F3 proved the vacuous-pass risk and
    mutation-checked both locales.
    *Guard:* translation-presence assertions in widget tests (the F3
    pattern).
14. **`one-home-compiled` · בית אחד, והמהדר קורא אותו** — a visual
    vocabulary (colours, strokes, badges) lives in one enum/home, every
    call site routes through it, **and the theme's `@source` globs scan that
    home** — routing protects nothing the compiler doesn't also read.
    *Why:* `one-home` + `unscanned-home`: three production bars rendered
    colourless with every call site correctly routed, because `app/Enums`
    was unscanned (fixed `b9825c6`).
    *Guard:* the scan-scope test at `DashboardEnumsTest.php:102`; the
    per-vocabulary source-scanning literal bans (A2 pattern).

## Change protocol

Adding, amending, or retiring a principle follows the admission rule above
and the dashboard governance principles
(`docs/phase-02/dashboard-governance-principles.md`): evidence first, guard
named, slug minted, registration at decision time.
