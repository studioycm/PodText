# Codex Prompt — Step 10R-MP1: Maintenance / Coming-Soon Mode with Admin HTML Content

Work in the current local clone of `studioycm/PodText`.

ONE mini-step: MP1 — a client-facing "coming soon / maintenance" mode: admin-managed
content, one settings toggle, every public URL serves it while admins bypass. Standing
runner rules apply: full quality gate incl. `git diff --check`, no push unless asked,
no `filacheck --fix`, no `model:show`, fixture-owned tests, en+he translations,
RTL-safe UI, research note + implementation plan doc before code, handoff ends with
`## Commit hash` (backfill the previous run's hash from git log per the standing
rule) and `## Local Front Check Report`. Local runtime DB is MySQL; preflight runs
`php artisan migrate` against it. First docs job: insert the
`Step 10R-MP1 - Maintenance mode page` ledger row after the last completed row
(UX3/S1c) and before the WB-gate note.

## Recorded decisions

- **D30 — trusted raw HTML, maintenance surface only.** The maintenance content is
  admin-authored and renders UNSANITIZED, but ONLY on the maintenance response and its
  bare layout — never inside normal public pages. The validator treats the HTML fields
  as free-text passthrough (nullable strings), exempt from finite-token normalization
  and from any sanitizer. Record D30 in the enhancement-plan decisions list.
- **D31 — 503, not redirects.** Maintenance mode answers every public URL in place
  with HTTP 503 + a `Retry-After` header and the maintenance view. No 302s: redirects
  poison SEO/caches during downtime, 503 tells crawlers "temporary — keep the URLs".
  Laravel's native `php artisan down` is deliberately NOT used (no admin toggle, no
  rich content, blocks the panel).

## Settings (new `maintenance` group inside `public_content`)

```json
{
  "enabled": false,
  "title": null,
  "rich_html": null,
  "raw_html_override": null,
  "retry_after_hours": 24
}
```

- Registry defaults + validator (bool; free-text passthrough for title/rich/raw;
  finite small int set for retry_after_hours e.g. 1|6|12|24|48) + settings migration +
  render-context/cached read through the existing P1 boundary (the middleware check
  must be as cheap as any other config read — no extra queries per request).
- Admin UI on the Public Content Settings page, own tab/section, PROGRESSIVE
  DISCLOSURE: the content fields appear only relevant-when-used; a clearly dangerous-
  looking warning near the enable toggle (translated) states the whole public site
  switches to the maintenance response.
- Content editing per Yoni: research the Filament 5 `RichEditor` (Boost `search_docs`
  + FilamentExamples; the LaravelDaily/Povilas Korop Filament v4/v5 RichEditor
  tutorials are the reference anchors — custom blocks/merge tags if cheap wins, but
  v1 needs solid basics) storing HTML, file attachments DISABLED in v1 (images via
  URL); plus an "advanced" collapsed `raw_html_override` code/textarea field
  (monospace; full-document HTML allowed) with helper text: when filled it REPLACES
  the rich content and is rendered verbatim (D30).

## Middleware + rendering

- One middleware on the PUBLIC panel / public routes (verify the correct attachment
  point for both Filament public panel pages and any non-panel public web routes;
  admin panel routes, login/auth routes, and the snapshot/zip/retry admin routes are
  NEVER intercepted): when `maintenance.enabled` and the request is not from a user
  who can access the admin panel → respond 503 with `Retry-After`
  (retry_after_hours * 3600) rendering the maintenance view.
- The view: bare standalone Blade layout (no public chrome/menu/footer), RTL + `he`
  lang defaults, renders `raw_html_override` verbatim when present, else the
  RichEditor HTML inside a minimal centered shell with the title; if both empty, a
  translated default "coming soon" line. Dark/light neutral (simple, self-contained
  CSS; no Tailwind-purge risk — inline styles or a tiny dedicated stylesheet).
- Admin bypass = authenticated user passing the same admin-panel access check the
  panel uses (session-based; no special tokens). Admins browsing public pages see the
  REAL site while maintenance is on (that is the point — they can prepare content).
- Known consequence to note in the handoff: while enabled, backup snapshots capture
  the maintenance page (the worker is a guest) — honest-by-design, no special-casing.

## Tests

Enabled → home/search/podcasts/a podcast/an episode respond 503 with Retry-After and
the content marker; admin session gets the real pages (200) while enabled; /admin and
login remain reachable for guests; disabled → all normal (regression + bounded public
harness); raw override beats rich content; empty-content fallback renders; settings
validation passthrough keeps the HTML byte-identical (no sanitization — D30 test);
import/export round-trips the maintenance group like any other; toggle via settings
save invalidates the cached config immediately (a fresh public request flips). Full
gate.

## Out of scope

Native `artisan down` integration; scheduled windows; IP allowlists; per-route
exemptions; styling systems for the maintenance page beyond the minimal shell;
Importer Workbench.

## Docs and handoff

Ledger row, current-state, enhancement-plan D30/D31 notes, research + plan docs,
handoff with numbered Local Front Check (enable in admin → open the public home
logged-out in a second browser → 503 coming-soon page in Hebrew RTL; verify an inner
episode URL also serves it; verify as logged-in admin you see the real site; edit
rich content and see it after save without redeploy; fill the raw override with a
full HTML document and see it verbatim; disable → site back; check light/dark
neutrality and mobile width) plus the deploy note: no new env vars; toggling is
runtime-only via settings.

Commit: `feat: add maintenance mode page and settings`

End with exactly:

```text
Public Front v2 mini-step MP1 is complete. Waiting for Yoni review before continuing.
```
