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
| EXIF stripping | IMG arc | Waits for an image re-encoding step; recorded. |
| IMG-3 zip packages; C4 category images | IMG arc decisions | Deferred by decision; unchanged. |
| Record-level clone (episodes/podcasts) | Yoni | **CLOSED** — dropped by decision. |
| Playwright-scraping Spotify | Yoni proposal | **CLOSED** — rejected (ToS/fragility/weight); OG tier approved instead. |
| Corpus audit (ChatGPT-era vs Fable-era docs) | parked long ago | On-demand ("run the docs audit"). |
| Admin gate before any non-admin account | standing guardrail | **CLOSED BY ROLES1** — `UserRole` and admin+ panel access replace the earlier `is_admin` placeholder. |
| Real mailer + from-address | env review | Before any mail-sending feature (password reset works only then). |
| Production human checks | growing | Checklist file + per-run handoff lists; SP1 added six more items. |
| Server housekeeping: remove `/home/forge/podtext.data4.work` dir + DNS record; confirm SESSION_SECURE_COOKIE landed | infra night | Yoni, when convenient. |
| Spotify credentials on production | fetcher | When the fetcher is used on prod. |
| MP2 gate numbers | process gap | **CLOSED-AS-DOCUMENTED** (permanent). |

## D. Decisions needed from Yoni (compact)

1. 9F-mini footer into the pre-13 path — yes/no.
2. Path A (launch-first, recommended) vs Path B (WB-first).
3. AX/SL arc stays post-13 — confirm.
4. When the Google probe happens (unblocks WB2/WB4 planning regardless of path).
