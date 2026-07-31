# Backlog Triage — 2026-07-13 (Fable)

Full sweep of the parked main queue, the WB track, and every deferred/dropped
thread from the HF3-era cluster onward. Verdicts: KEEP-PRE-13 (runs before
Prompt 13), DEFER-POST-13, WAITING-ON-YONI, CLOSED.

## A. Parked main queue (ledger rows, judged against what shipped)

| Step | What it is | Verdict | Reasoning |
|---|---|---|---|
| P2 — listing fetch windows + lazy filter options | Bound public listing queries; stop building filter options/form definitions on every search render; opt-in aggregates | **KEEP-PRE-13** | Still fully valid; the public-search render cost findings were never addressed; launch-relevant. First main-queue step after SP2/FETCH1. |
| P3 — derived transcript segments/viewer economy | Persist/render derived segments + word counts instead of re-parsing Markdown per view | **KEEP-PRE-13 (second)** | Still valid; long-transcript pages are the product's core reading surface. |
| AX1 — GSAP motion foundation | Approved gsap dependency, preset registry, reduced-motion | **DEFER-POST-13** | Visual polish epic; not launch-critical, not 13-blocking. |
| SL1–SL4 — display templates, flip slider, flip/back face, quick-view modal | The slider/motion product arc | **DEFER-POST-13** | Coherent post-launch arc together with AX1-AX3; 5 steps of wow-factor that content-less launch does not need. Yoni may overrule for launch impact. |
| AX2, AX3 — motion retrofit, scroll effects | Depends on AX1 | **DEFER-POST-13** | Rides the same arc. |
| B4 — legacy card-options convergence | Code-debt convergence gated on M+P+SL+AX | **DEFER-POST-13** | Refactor debt; precondition list includes the deferred arc anyway. |
| C2 — card layout markers/semantic tokens | Normalization incl. slider surfaces | **DEFER-POST-13** | Debt + slider-coupled; safe later. |
| 9F-A/B/C — footer + rich section builder | Public footer + builder foundation | **SPLIT — WAITING-ON-YONI** | A minimal settings-driven FOOTER (links/contact/credits) is a real launch gap and a small step ("9F-mini"). The rich section builder defers post-13. Decide whether 9F-mini enters the pre-13 path. |
| Step 11 — seeders/demo/assets/cleanup | Promote demo seed state | **DEFER (approval-gated)** | Real content arrives via workspace/fetcher/importers; demo seeding is not launch-critical. Revisit post-13. |
| Prompt 13 — dashboard metrics | The target | **THE GOAL** | Runs after the pre-13 keeps; scope = editorial metrics over the now-rich schema (episodes, transcriptions, submissions badge, media, imports). |

**Recommended road to 13:** SP2 → FETCH1 → P2 → P3 → (9F-mini if approved) → Prompt 13.

**P2 scope settled 2026-07-31 (RECON2 R6, per the operator's tripwire
instruction):** P2's "lazy filter options" means **PHP-lazy** — memoize/cache
the option builders so they resolve once per request instead of on every
debounced render. Livewire-lazy or deferred option loading (`#[Lazy]`,
`wire:init`, fetch-on-drawer-open) is **out of P2's scope**: the filter drawer
is server-rendered inside `x-show`, so options ride the first payload
regardless, and the client-lazy reading would trip the `MAINT-LW-UX1`
tripwire ("run before any later public Livewire navigation/polling/lazy/
deferred/stream/upload expansion"), which has never run. `MAINT-LW-UX1` must
still run before the SL2/SL4 slider arc, which trips it unambiguously.
**Execution order note:** P3 shipped first (smaller blast radius, no
tripwire, and it contained the real `word_count` bug RECON2 R1 fixed);
nothing technical couples P2 → P3.

**Road updated 2026-07-31 (operator decisions, recorded same day):** the
pre-13 queue is EMPTY — SP2, FETCH1, P2 and P3 have all shipped. The road is
now: **Prompt 13 next, with nothing before it** → **FETCH2** (a second
fetcher round; scope defined at its kickoff — the one inherited candidate is
`MUX3-F022`, the Spotify show-artwork admission choice/receipt partial) →
**9F-mini footer** (approved, but explicitly after 13) → the standing
post-13 queue. This is Path A in effect: launch-first, content through the
workspace/fetcher, WB machinery later.

## B. WB track (content machine)

| Item | Status | Verdict |
|---|---|---|
| Google service-account setup + format probe (`importer:probe-formats`) | **WAITING-ON-YONI** since WB1 | Gates WB2/WB4. The probe also feeds the transcript paste-cleanup + `[]` conventions design. |
| WB2–WB7 | pending, unchanged | **Strategic fork for Yoni**: Path A (recommended) — launch-first: reach 13 with manual/semi-bulk content via workspace + fetcher CSVs, build WB after. Path B — content-first: WB2+WB4 before 13 if bulk transcript import must precede launch. |
| SF1/TOOLS1 relation to WB7 | recorded in ledger | CLOSED — fetcher deliberately standalone; WB7 unchanged. |

## C. Deferred/dropped threads registry (session sweep)

| Thread | Origin | Verdict |
|---|---|---|
| Legacy/custom local settings payload fails current validation | SP1 report | **SP2 Job 0** — normalize-stored-settings command (dry-run + backup-first apply). Latent local save-blocker. |
| TS2 — settings test cost | TS1/SP1 | **MERGED INTO SP2** — test files split along the page split; same root cause, one stroke. |
| Card-template clone (generic cloner wiring) | MP2 rider | **SP2 rider** — the split touches the templates page anyway; cheapest moment. |
| Fetcher: OG fallback tier (plain HTTP, no Playwright) | Yoni approved | **FETCH1** |
| Fetcher: reduced-mode thumbnail missing (oEmbed bug) | Yoni report | **FETCH1** |
| Fetcher: image preview column; description → Markdown in table/CSV and in the EP1 workspace fetch fill | Yoni report | **FETCH1** |
| MP2 maintenance-form fallback styling polish | Yoni report | **FETCH1** |
| EP1: per-user presentation preference (R8) | EP1 deferral | Small; post-13 UX polish unless Yoni pulls it. |
| EP1: transcript paste-cleanup + `[]` conventions | EP1 deferral | Waits on the format probe evidence (B). |
| EXIF stripping | IMG arc | **CLOSED 2026-07-31 (RECON2 R1)** — shipped as a metadata-only strip at admission (no pixel re-encode; orientation preserved). The old "waits for an image re-encoding step" note was misleading: that step existed and was deliberately removed from admission by MI-R044, so the fix took the byte-surgery shape instead. |
| IMG-3 zip packages; C4 category images | IMG arc decisions | Deferred by decision; unchanged. |
| Record-level clone (episodes/podcasts) | Yoni | **CLOSED** — dropped by decision. |
| Playwright-scraping Spotify | Yoni proposal | **CLOSED** — rejected (ToS/fragility/weight); OG tier approved instead. |
| Corpus audit (ChatGPT-era vs Fable-era docs) | parked long ago | On-demand ("run the docs audit"). |
| Admin gate before any non-admin account | standing guardrail | **CLOSED BY ROLES1** — `UserRole` and admin+ panel access replace the earlier `is_admin` placeholder. |
| Real mailer + from-address | env review | Before any mail-sending feature (password reset works only then). |
| Production human checks | growing | Checklist file + per-run handoff lists; SP1 added six more items. |
| Server housekeeping: confirm SESSION_SECURE_COOKIE landed | infra night | Yoni, when convenient. **Corrected 2026-07-31 (operator):** `/home/forge/podtext.data4.work` is simply the original domain folder of this same app on the hosting panel — `podtext.co.il` is now the primary domain and the Google redirect URI was updated weeks ago. The earlier removal proposal was stale and confusing; there is nothing to remove. |
| Spotify credentials on production | fetcher | When the fetcher is used on prod. |
| MP2 gate numbers | process gap | **CLOSED-AS-DOCUMENTED** (permanent). |

## D. Decisions needed from Yoni (compact)

**All answered 2026-07-31:**

1. 9F-mini footer into the pre-13 path — **NO; runs after Prompt 13** (third
   in the post-13 order, after FETCH2).
2. Path A vs Path B — **Path A in effect**: the first work after Prompt 13 is
   **FETCH2**, a second fetcher round (not WB2/WB4).
3. AX/SL arc stays post-13 — **confirmed**.
4. Google probe — **waiting on Google/externals**; unchanged gate for
   WB2/WB4.
5. (Added) Production incident closure check — **done 2026-07-31**: redis and
   memcached now bind localhost only; the public exposure that was the prime
   entry-vector suspect is closed. Incident fully remediated.
