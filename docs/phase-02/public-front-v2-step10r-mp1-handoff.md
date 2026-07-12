# Public Front v2 Step 10R-MP1 Handoff

## Scope

Step 10R-MP1 is complete. This run added only the settings-controlled public
maintenance / coming-soon mode. The Importer Workbench was not started.

## What Changed

- Added `public_content.maintenance` settings with defaults for `enabled`, `title`,
  `rich_html`, `raw_html_override`, and `retry_after_hours`.
- Added a Spatie settings migration and wired the group through
  `PublicContentSettings`, `PublicFrontConfigRegistry`, `PublicFrontConfigValidator`,
  `PublicFrontRenderContext`, and settings lifecycle units.
- Added D30 trusted maintenance HTML passthrough: maintenance `title`, `rich_html`,
  and `raw_html_override` remain nullable string passthrough fields and are not
  sanitized by the public-front validator.
- Added D31 public maintenance routing: public panel routes return HTTP 503 in place
  with a `Retry-After` header instead of redirecting.
- Added a Public Content Settings maintenance tab with translated warning text,
  retry-after selection, attachment-disabled RichEditor content, and collapsed raw HTML
  override.
- Added public-panel middleware that reads through the P1 config cache boundary and
  bypasses authenticated users who can access the admin panel.
- Added a standalone RTL Hebrew-first maintenance view with a neutral light/dark shell
  and raw HTML override rendering.
- Added import/export round-trip coverage for the maintenance group.

## Known Consequence

Backup snapshots run as guest requests. While maintenance mode is enabled, backup
snapshots intentionally capture the maintenance page.

## Deploy Notes

- No new environment variables are required.
- Toggling maintenance mode is runtime-only through Public Content Settings.
- Do not use `php artisan down` for this feature; the admin toggle, rich content, and
  admin bypass are app-owned behavior.
- Forge deploy scripts must run `npx playwright install chromium` after the npm
  install/build step so Playwright browser binaries stay aligned after package version
  bumps. This is a maintenance deploy note from the 2026-07-12 browser-staleness
  incident.

## Verification

- `php -l` passed for dirty PHP files.
- `php artisan migrate --no-interaction` applied
  `2026_07_10_000001_add_public_maintenance_setting` to local MySQL.
- `php artisan test tests/Feature/PublicMaintenanceModeTest.php` passed.
- `php artisan test tests/Feature/SettingsImportExportTest.php` passed.
- `php artisan test tests/Feature/PublicFrontRenderContextTest.php` passed.
- Token-level duplicate-key scan passed for edited English and Hebrew language files.
- Full final gate passed sequentially:
  `vendor/bin/pint --dirty --format agent`,
  `php artisan test`,
  `vendor/bin/pint --test`,
  `vendor/bin/filacheck`,
  `npm run build`,
  `git diff --check`.

## Commit hash

Previous completed mini-step S1c: `389cb0f feat: add inline import locks on settings
page`.

MP1 commit message: `feat: add maintenance mode page and settings`.

Final MP1 commit hash is reported in the chat final because this document is part of
that commit.

## Local Front Check Report

1. Enable maintenance in Public Content Settings and open `/` logged out: the public
   middleware returns 503, includes `Retry-After`, renders `lang="he"` and `dir="rtl"`,
   and shows the configured maintenance marker.
2. Open an inner episode URL logged out: the same 503 maintenance response is served in
   place for `/items/{podcast}/{episode}`.
3. Browse the public site as a logged-in admin: the admin-panel access check bypasses
   maintenance and the real public episode page renders.
4. Edit rich content and save: a fresh public request immediately sees the updated
   maintenance content through the settings/cache invalidation path, with no redeploy.
5. Fill the raw override with full HTML: the response renders that HTML verbatim and
   skips the maintenance shell/rich content.
6. Disable maintenance: `/`, `/search`, and `/podcasts` return normal 200 public pages
   and do not show the maintenance marker.
7. Check light/dark and mobile width: the standalone view uses self-contained neutral
   CSS with responsive width and `color-scheme: light dark`; no public chrome/menu or
   Tailwind-purge-dependent classes are required.
