# Codex Prompt — MP2 v2: Forms Management Page + Form on the Maintenance Page

Work in the current local clone of `studioycm/PodText`.

ONE implementation run. Standing runner rules: research note + implementation
plan docs BEFORE code, no push unless asked, no `filacheck --fix`,
fixture-owned tests, en+he translations, RTL-safe UI, NO Composer changes.
The handoff is a COMMITTED MARKDOWN FILE
(`docs/phase-02/maintenance-form-mp2-handoff.md`) with `## Commit hash` and a
numbered MANUAL `## Local Front Check Report`. Backfill the previous run's
commit hash per the standing rule.

FINAL GATE ORDER (standing): pre-gate requirements sweep against this
prompt's job/test lists → `vendor/bin/pint --test` → `vendor/bin/filacheck`
→ `npm run build` → the FULL `php artisan test` LAST. "Once" means once GREEN
on the final code state; any later change re-enters the gate from Pint.
Record every full run. Never interrupt or parallelize; do not investigate
suite slowness here.

## Preflight

```bash
git status --short --branch
git log --oneline -5
```

Clean tree; NAV1's commit expected in history (if NAV1 has not landed, STOP
and report).

## What exists (verified by Fable — build on it)

- Form definitions live in `PublicContentSettings::$public_forms` (multiple
  forms, keyed), read through `PublicFormDefinitionRegistry`, rendered
  publicly by `App\Livewire\Public\PublicFormModal`, validated by
  `PublicFormPayloadValidator` via `PublicFormSchemaFactory`, stored as
  `PublicFormSubmission` rows (`PublicFormSubmissionStatus`), managed in
  `PublicFormSubmissionResource` (the NAV1 רשומות טפסים item).
- Maintenance mode (MP1) answers every public request in-place with 503 +
  Retry-After via `RenderMaintenanceMode` on the public panel; admins bypass;
  the view is a bare standalone Blade shell rendering `raw_html_override`
  verbatim (D30) or the rich content + title.

## Yoni decisions this run implements

- **D-MP2-A — dedicated forms page, settings-backed storage**: forms are
  created and managed on a page of their own, but the STORAGE stays
  `PublicContentSettings::$public_forms` so the registry, validator, public
  modal, submissions, and the whole settings lifecycle (backups,
  export/import packages, locks) keep working unchanged. Do NOT move forms
  to a database table.
- **D-MP2-B — plain POST for the maintenance form (safest)**: the
  maintenance-page form is server-rendered plain HTML posting to ONE
  dedicated exempt route. NO Livewire on the 503 shell and NO blanket
  exemption of Livewire's update route (rejected: pre-maintenance browser
  tabs hold live component snapshots and could keep driving public
  components through such an exemption). The endpoint itself verifies
  maintenance is enabled AND a form is configured before accepting, applies
  CSRF, and reuses `PublicFormPayloadValidator`, the existing anti-spam
  mechanics, and the `PublicFormSubmission` model — no parallel pipeline
  beyond the maintenance render layer.

## Job 1 — forms management page (D-MP2-A)

- A dedicated admin page (e.g. `ManagePublicForms`) editing ONLY the
  `public_forms` slice of `PublicContentSettings`: form list with add/remove,
  per-form key (auto-generated from the form name per the slug conventions,
  manual override, immutable-once-submissions-exist or clearly warned),
  fields sub-editing per `PublicFormFieldType`, enable/disable, translated
  labels + helper text on every technical field, progressive disclosure.
- REMOVE the forms editing UI from the big Public Content Settings page (the
  new page is the only editor); keep the settings save pipeline (system
  backups, D29 locks behavior) intact — saving forms through the new page
  behaves exactly like a settings save.
- Navigation: under ניהול אתר per the NAV1 structure (default — record it in
  the handoff so Yoni can re-place it cheaply).
- **Clone action** (Yoni): each form row gets a clone action built on a
  GENERIC settings-collection clone helper (e.g.
  `App\Support\Settings\SettingsItemCloner`: deep-copy an array item, mint a
  unique key with a numeric suffix, apply a translated name suffix like
  "עותק"). The cloned form starts DISABLED, deep-copies all fields, and never
  touches the original. Build the helper collection-agnostic (card templates
  are the known next consumer — do NOT wire them in this run), wire it to
  forms only.
- Tests: page renders and saves definitions; registry/public modal pick up
  changes; clone creates a new unique key, deep-copies fields, starts
  disabled, original byte-identical; cloning twice yields distinct keys; the
  big settings page no longer exposes the forms editor; a settings
  export/import package still round-trips `public_forms` (lifecycle
  regression); unrelated settings saves do not clobber forms (the D29
  save-wipe class).

## Job 2 — research note, then the maintenance form (D-MP2-B)

Research (short, docs in `docs/research/maintenance-form/`): confirm the
existing anti-spam mechanics of `PublicFormModal` (honeypot/throttle/
whatever exists) and how the schema factory's field definitions map to a
server-rendered plain HTML form; cite the reuse points. Then implement:

- Maintenance settings additions (registry defaults + validator + migration
  + admin UI on the maintenance tab, progressive disclosure): `form_key`
  (select from enabled registry forms, nullable = off), `form_location`
  (`rendered_page` | `raw_html`), `form_position` (`before_content` |
  `after_content`, rendered_page only). When `raw_html` is selected, show
  the app-owned MARKER SNIPPET (constant, e.g.
  `<div data-podtext-maintenance-form></div>`) read-only WITH a
  copy-to-clipboard icon action and helper text.
- Rendering in the maintenance view: `rendered_page` places the form before
  or after the title + rich/markdown parts; `raw_html` replaces the FIRST
  marker occurrence inside the verbatim override with the rendered form —
  the surrounding raw HTML stays untouched (D30); missing marker while
  raw_html is selected → render the form after the raw content AND show a
  warning hint on the settings/forms page.
- The POST endpoint: dedicated named route, exempted from the maintenance
  interception, guarded as described in D-MP2-B; success re-renders the
  maintenance page with a translated thank-you state; validation errors
  re-render with errors and preserved input; stale CSRF (419) lands back on
  the maintenance page with a translated retry message, never a bare error
  page.

## Tests

Job 1 list above; plus: settings save/round-trip for the new maintenance
keys; rendered_page before/after positions render the form in the right
place; raw_html marker replaced exactly once at the marker position; missing
marker falls back + warns; a GUEST submits during maintenance → a
`PublicFormSubmission` row with the right form key (and the רשומות טפסים
badge counts it); every other public route still 503 while the form endpoint
works; the endpoint rejects when maintenance is off or no form is configured;
admin bypass unchanged; no-form-configured renders the maintenance page
exactly as before (regression); invalid payload keeps the user on the
maintenance page with errors; anti-spam behaves as on the public modal;
translations en+he; RTL marker where practical. Full gate per the header
order.

## Out of scope

New field types or form-builder features beyond the dedicated page; moving
forms to a database table; Livewire on the maintenance shell; public panel
work beyond the maintenance view; TOOLS1/SF1; IE-1; Composer changes.

## Docs and handoff

Ledger row `MP2 - Forms page and maintenance form`; `current-project-state.md`;
research + plan docs before code; ONE deploy-notes addition recorded in the
handoff and the maintenance deploy notes: the Forge deploy script must run
`npx playwright install chromium` AFTER the npm install/build step (browser
builds go stale on Playwright version bumps — incident recorded 2026-07-12);
the handoff documents the exempt route (name, guards, why safe), `## Commit
hash`, previous-run hash backfill, and manual front checks (create a form on
the new forms page → it appears in the maintenance form select; rendered
page + before content as guest during maintenance → form above the title →
submit → thank-you + row in רשומות טפסים + badge rises; after content;
raw_html → copy marker via the icon → paste into the override → form renders
at that exact spot; remove marker → fallback + warning; wrong input →
errors preserved on the page; other URLs still 503; forms editor gone from
the big settings page; Hebrew RTL + light/dark).

Commit: `feat: add forms management page and maintenance form embedding`

End with exactly:

```text
Maintenance form MP2 is complete. Waiting for Yoni review before continuing.
```
