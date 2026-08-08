# filament-4 course → Filament 5 docs: link audit

Every `filamentphp.com/docs` link in the `filament-4` course, re-tested with `4.x` → `5.x`
substituted in the URL. Run 2026-08-08 against the live docs site; machine-readable results in
`filament5-linkcheck.json` (gitignored raw sits beside it; this summary is the committed record).

**Result: 23/23 pages resolve · 13/13 anchors still exist · 0 failures.**

The operator's rule is confirmed with no exceptions: Filament keeps its doc path structure
across majors, so `4.x` → `5.x` substitutes directly — pages *and* section anchors. There are
no v5-equivalent hunts to do. The course's links are usable for Filament 5 work by mechanical
substitution.

Two notes:

- Anchors were checked against the page HTML (`id=`/`name=`), not just HTTP status — a page can
  200 while its section is gone. None were.
- One course link is unversioned (`/docs/tables/actions#header-actions`); the site redirects it
  to **5.x**, so unversioned links self-heal and currently mean "5.x".

## The links, by course lesson

| 5.x URL | anchor | lessons citing it |
| --- | --- | --- |
| [components/overview](https://filamentphp.com/docs/5.x/components/overview) | — | view-pages-custom-page-or-infolist-builder |
| [components/table](https://filamentphp.com/docs/5.x/components/table) | — | custom-data-from-api |
| [forms](https://filamentphp.com/docs/5.x/forms) | — | first-crud-menu-product-resource-1 |
| [forms/validation](https://filamentphp.com/docs/5.x/forms/validation#available-rules) | `#available-rules` ✓ | column-sort-search-and-validate |
| [infolists/overview](https://filamentphp.com/docs/5.x/infolists/overview) | — | form-layouts…, view-pages… |
| [resources/nesting](https://filamentphp.com/docs/5.x/resources/nesting) | — | nested-resources |
| [schemas/layouts](https://filamentphp.com/docs/5.x/schemas/layouts) | — | form-layouts-columns-sections-tabs-wizards-1 |
| [styling/colors](https://filamentphp.com/docs/5.x/styling/colors#introduction) | `#introduction` ✓ | change-colors-fonts-themes-1 |
| [styling/css-hooks](https://filamentphp.com/docs/5.x/styling/css-hooks#discovering-hook-classes) | `#discovering-hook-classes` ✓ | change-colors-fonts-themes-1 |
| [styling/css-hooks](https://filamentphp.com/docs/5.x/styling/css-hooks#publishing-blade-views) | `#publishing-blade-views` ✓ | change-colors-fonts-themes-1 |
| [styling/overview](https://filamentphp.com/docs/5.x/styling/overview#creating-a-custom-theme) | `#creating-a-custom-theme` ✓ | change-colors-fonts-themes-1 |
| [tables](https://filamentphp.com/docs/5.x/tables) | — | first-crud-menu-product-resource-1 |
| [tables/columns/overview](https://filamentphp.com/docs/5.x/tables/columns/overview#introduction) | `#introduction` ✓ | first-crud-menu-product-resource-1 |
| [tables/custom-data](https://filamentphp.com/docs/5.x/tables/custom-data#introduction) | `#introduction` ✓ | custom-data-from-api |
| [tables/summaries](https://filamentphp.com/docs/5.x/tables/summaries) | — | table-grouping-and-summarizers |
| [users/multi-factor-authentication](https://filamentphp.com/docs/5.x/users/multi-factor-authentication#app-authentication) | `#app-authentication` ✓ | multi-factor-authentication |
| [users/multi-factor-authentication](https://filamentphp.com/docs/5.x/users/multi-factor-authentication#email-authentication) | `#email-authentication` ✓ | multi-factor-authentication |
| [users/tenancy](https://filamentphp.com/docs/5.x/users/tenancy) | — | multi-tenancy-in-filament-4 |
| [users/tenancy](https://filamentphp.com/docs/5.x/users/tenancy#setting-up-tenancy) | `#setting-up-tenancy` ✓ | multi-tenancy-in-filament-4 |
| [users/tenancy](https://filamentphp.com/docs/5.x/users/tenancy#tenancy-security) | `#tenancy-security` ✓ | multi-tenancy-in-filament-4 |
| [users/tenancy](https://filamentphp.com/docs/5.x/users/tenancy#using-tenant-aware-middleware-to-apply-additional-global-scopes) | long anchor ✓ | multi-tenancy-in-filament-4 |
| [widgets/charts](https://filamentphp.com/docs/5.x/widgets/charts) | — | dashboard-widgets-stats-charts-tables-and-header-footer |
| [tables/actions](https://filamentphp.com/docs/5.x/tables/actions#header-actions) | `#header-actions` ✓ | table-actions-row-bulk-header-1 *(unversioned in course; redirects to 5.x)* |

Non-docs filament links in the course, unaudited (different property or third-party):
`demo.filamentphp.com/...` ×2, `filamentphp.com/plugins` and
`filamentphp.com/plugins/bezhansalleh-shield`.

## Caveat that keeps this honest

A link resolving proves the **page** survived the major version, not that its **content**
still matches what the course teaches on video. The course predates Filament 5; APIs it
demonstrates still need checking against the installed `filament/filament` when its notes get
written. This audit removes the *link* question only — and it does confirm the doc structure
is stable, which raises the prior that the content transferred too.
