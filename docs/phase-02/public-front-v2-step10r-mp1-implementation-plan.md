# Public Front v2 Step 10R-MP1 Implementation Plan

## Scope

Implement only Step 10R-MP1: a Public Content Settings controlled
maintenance/coming-soon mode. Do not start the Importer Workbench.

## Commit Baseline

- Current prompt commit: `9267597 docs: added prompt to plan and research about
  maintenance mode with admin HTML content, 503 response, and settings toggle - not
  actual feature yet`.
- Previous completed S1c commit: `389cb0f feat: add inline import locks on settings
  page`.
- Target MP1 commit: `feat: add maintenance mode page and settings`.

## Implementation Steps

1. Settings shape
   - Add `public_content.maintenance` defaults:
     `enabled`, `title`, `rich_html`, `raw_html_override`, `retry_after_hours`.
   - Add the Spatie settings property and migration.
   - Add registry/schema entries and a render-context accessor.

2. Validator and package lifecycle
   - Normalize `enabled` as bool and `retry_after_hours` as finite int
     `1|6|12|24|48`.
   - Pass `title`, `rich_html`, and `raw_html_override` through as nullable strings
     without sanitizer or finite-token checks per D30.
   - Add lifecycle/import-export unit coverage for the new `maintenance` group.

3. Admin settings UI
   - Add an own Public Content Settings tab/section.
   - Include a clear translated danger/warning helper near the enable toggle.
   - Use `RichEditor::make('maintenance.rich_html')->fileAttachments(false)` for basic
     HTML content.
   - Add a collapsed advanced textarea for `raw_html_override`, monospace, with helper
     text explaining that it replaces rich content and renders verbatim.
   - Keep progressive disclosure: content fields are visible when the mode is enabled
     or when a field already has content.

4. Middleware and view
   - Add a public-panel middleware class attached only in `PublicPanelProvider`.
   - Read `maintenance` through `PublicFrontConfigReader` so the check uses the cached
     validated config path.
   - Bypass for authenticated users whose user can access the admin panel.
   - Return status `503` with `Retry-After` header and render a standalone Blade view.
   - Keep admin panel routes, login/password routes, and admin snapshot routes
     unintercepted.

5. Tests
   - Add a focused `MaintenanceModeTest` covering enabled public URLs, admin bypass,
     admin/login reachability, disabled regression, raw override precedence, fallback,
     byte-identical HTML validation, import/export round-trip, and cache invalidation
     on settings save.
   - Update settings import/export tests only if lifecycle coverage needs to live
     there.

6. Docs and handoff
   - Update ledger from in-progress to complete.
   - Update current state with the final MP1 hash placeholder before commit.
   - Record D30/D31 in the enhancement-plan decisions.
   - Add MP1 handoff with `## Commit hash` and `## Local Front Check Report`.

## Local Front Check Plan

1. Enable maintenance in admin and open the public home logged out: expect Hebrew RTL
   maintenance page, 503, and `Retry-After`.
2. Open an inner episode URL logged out: expect the same maintenance response.
3. Open public pages as a logged-in admin: expect the real site.
4. Edit rich content and save: expect updated maintenance HTML without redeploy.
5. Fill raw override with full HTML: expect it to replace the rich-content shell and
   render verbatim.
6. Disable maintenance: expect the public site back.
7. Check mobile width and light/dark neutral styling.

## Gate

Run commands sequentially only:

1. `vendor/bin/pint --dirty --format agent`
2. `php artisan test`
3. `vendor/bin/pint --test`
4. `vendor/bin/filacheck`
5. `npm run build`
6. `git diff --check`

