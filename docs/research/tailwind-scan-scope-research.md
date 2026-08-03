# Tailwind scan-scope research — every app part that emits class strings, and the policy that keeps them compiled

- **Date:** 2026-08-03 · research-only session (no code/config changed; this doc is the sole deliverable).
- **Trigger:** ledger pattern `unscanned-home` (alias P13) in `docs/research/defect-cause-patterns.md` — the admin
  theme never scanned `app/Enums`, so enum-owned colour classes were compiled away and three dashboard bars rendered
  colourless in production. Instance fixed and pinned (`b9825c6`, scan test `tests/Feature/DashboardEnumsTest.php:102`).
  This doc researches the *general* subject: every surface that emits Tailwind class strings, what the ecosystem
  recommends, and one policy + guard for this repo.
- **Evidence key:** claims are tagged **[V]** = verified in source/docs/compiled output this session, or
  **[I]** = inferred (reasoned, not directly observed). Compiled-output claims were read from the local build of
  2026-08-03 07:42 (`public/build/assets/`, manifest-mapped); no class-bearing source changed between that build and
  HEAD at research time (only ledger-doc commits landed since) **[V]**.

## TL;DR

Both Filament themes run with automatic source detection **fully disabled** (`source(none)` in the vendor base), so
the explicit `@source` globs are the *only* thing standing between a class literal and a blank style — and the sweep
found the gap is not hypothetical: two class homes are **missing from the compiled public CSS right now**
(`SafeMarkdownRenderer` prose classes; `PublicItemPageRegistry` badge size/colour maps), and two more admin-side homes
survive only by accidental coverage. Recommendation in three sentences:

1. Keep **targeted explicit `@source` globs** as the mechanism (it is what Filament 5 docs, the FilamentExamples
   corpus, and LaravelDaily all teach), but maintain them as a **declared list of class-home roots shared by both
   themes**, and close the four gaps found here.
2. Keep **class strings out of the database**: revision JSON stores bounded tokens mapped to complete literals inside
   scanned PHP (the card-template renderer already does this correctly), and truly continuous values travel as CSS
   custom properties/inline styles (the podcast-palette path already does this correctly); `@source inline()` is the
   sanctioned v4 safelist but should stay a documented last resort (nothing needs it today).
3. Generalize the existing pinned-glob test into a **scan-scope invariant test**: discover class-emitting PHP by
   regex (FilaCheck-style token patterns), parse the `@source` globs from both theme files, and fail when a
   class-emitting file is outside the scan scope of a panel that can render it — because FilaCheck/FilaCheck-Pro
   verifiably do **not** cover this failure mode once any custom theme exists.

---

## 1. Sources consulted

### Official docs (version-matched via Boost `search-docs`)

| Source | Version | What it settles |
|---|---|---|
| Filament docs `08-styling/01-overview.md` ([github](https://github.com/filamentphp/filament/blob/5.x/docs/08-styling/01-overview.md)) | filament/filament 5.x (installed 5.7.5) | Custom theme creation; `@source` per directory; "a custom theme is **required** to use Tailwind CSS classes in your own code… without one, classes **simply will not work**" **[V]** |
| Filament docs `09-advanced/02-assets.md` ([github](https://github.com/filamentphp/filament/blob/5.x/docs/09-advanced/02-assets.md)) | 5.x | Plugin vendor dirs are added by the *user* via `@source` in their theme (the pattern this repo uses for Curator) **[V]** |
| Tailwind docs `detecting-classes-in-source-files.mdx` ([tailwindcss.com](https://tailwindcss.com/docs/detecting-classes-in-source-files)) | tailwindcss 4.x | Auto-detection scope and exclusions; `@source`, `@source not`, `source("…")`, `source(none)`; `@source inline()` safelist with variant/brace expansion; `@source not inline()`; plain-text token scanning; "don't construct class names dynamically" **[V]** |
| Tailwind docs `functions-and-directives.mdx` ([tailwindcss.com](https://tailwindcss.com/docs/functions-and-directives)) | 4.x | v3 `safelist` config **is not supported in v4**; `@source inline()` is the replacement **[V]** |
| Tailwind docs `styling-with-utility-classes.mdx` ([tailwindcss.com](https://tailwindcss.com/docs/styling-with-utility-classes)) | 4.x | For values from a database/API: inline styles, or CSS variables set inline and consumed by `bg-(--var)`-style utilities **[V]** |

### Ecosystem materials

- FilamentExamples MCP corpus — protocol run recorded in §4.2 (two passes; only `search-examples` is exposed by this MCP, no read/details tool) **[V]**.
- FilamentExamples site tutorial: [Tailwind CSS Class Not Found: How to Activate It](https://filamentexamples.com/tutorial/tailwind-css-class-not-found-how-to-add-them) (v3-era, 2024): create a custom theme; "our class won't always work directly in the blade file, so it's best to style elements via css file" (`@apply` in theme.css) **[V, fetched]**.
- FilaCheck / FilaCheck-Pro vendor source (installed): rule catalogue under `vendor/laraveldaily/filacheck{,-pro}/src` reviewed; `FilacheckPro\Rules\CustomThemeNeededRule` read in full — analysis in §4.3 **[V]**.
- LaravelDaily: [Filament Infolist: Create Custom Components with Tailwind CSS](https://laraveldaily.com/post/filament-infolist-create-custom-components-tailwind-css) (v3, partially paywalled), [Filament Select: Use HTML with CSS in Options](https://laraveldaily.com/post/filament-select-use-html-with-css-in-options), [Change Colors, Fonts, Themes lesson](https://laraveldaily.com/lesson/filament-3/change-colors-fonts-themes). Site-level stance (v4): custom Blade files with Tailwind classes ⇒ create a custom theme and add those folders to `theme.css` **[V at stance level; article bodies partially gated]**.
- Community threads (further reading, not load-bearing): [filamentphp discussion #17284](https://github.com/filamentphp/filament/discussions/17284), [answeroverflow: content detection in custom theme](https://www.answeroverflow.com/m/1161995888609210490), [answeroverflow: classes not working in custom theme](https://www.answeroverflow.com/m/1168519003761815612), [laracasts: public-facing Filament 4 form with Tailwind 4](https://laracasts.com/discuss/channels/laravel/public-facing-filament-4-form-with-tailwind-4).

### Repo evidence

Theme/entry files, panel providers, vendor CSS, compiled build output (2026-08-03 07:42), git archaeology
(`b9825c6` Enums glob, `f56ef36` Cards glob, `2a5ff96`/`0b067e9` prose-class history, `ac9b0e5` welcome-view removal).
All cited inline below.

---

## 2. CSS entry-point map

Three Tailwind entries exist ([vite.config.js:9](../../vite.config.js)); each compiles independently, so **each has its
own scan scope** **[V]**:

| Entry | Loaded by | Tailwind base | Scan scope |
|---|---|---|---|
| `resources/css/filament/admin/theme.css` | Admin panel via `->viteTheme()` ([AdminPanelProvider.php:39](../../app/Providers/Filament/AdminPanelProvider.php)) | `vendor/filament/filament/resources/css/theme.css` → **`@import 'tailwindcss' source(none)`** | Globs at [theme.css:4-11](../../resources/css/filament/admin/theme.css): `app/Filament/**`, `app/Enums/**`, `app/Livewire/**`, `app/Support/PublicFront/Cards/**`, `resources/views/filament/**`, `resources/views/livewire/**`, `resources/views/components/**`, Curator vendor blades |
| `resources/css/filament/public/theme.css` | Public panel via `->viteTheme()` ([PublicPanelProvider.php:38](../../app/Providers/Filament/PublicPanelProvider.php)) | same vendor base, `source(none)` | Globs at [theme.css:3-10](../../resources/css/filament/public/theme.css): `app/Filament/Public/**`, `app/Livewire/Public/**`, `app/Support/PublicContent/**`, `app/Support/PublicFront/Cards/**`, `resources/views/components/public/**`, `resources/views/filament/public/**`, `resources/views/filament/tables/**`, `resources/views/livewire/public/**` |
| `resources/css/app.css` | **Nothing** — no blade in the repo contains `@vite`/`vite(` (grep-verified); the stock welcome view was removed in `ac9b0e5` | `@import 'tailwindcss'` (auto-detection ON) + stock Laravel globs (`resources/views/**`, `resources/js/**`, pagination vendor blades, `storage/framework/views/*`) | Builds a 77 KB `app-*.css` that is never served **[V]** |

Load-bearing mechanics:

- **`source(none)` is the whole story.** [vendor/filament/filament/resources/css/theme.css:1](../../vendor/filament/filament/resources/css/theme.css)
  disables Tailwind v4's automatic project-wide detection, and the vendor CSS tree contains **zero `@source`
  directives** (grep-verified) — Filament 5 ships its own UI as handwritten semantic `.fi-*` component CSS, not
  scanned utilities. Consequence: for panel pages, *only* the app's explicit globs feed the utility compiler. A class
  literal outside them does not exist in the page's CSS, full stop **[V]**. (Tailwind docs explicitly bless this mode
  for "projects that have multiple Tailwind stylesheets where you want to make sure each one only includes the classes
  each stylesheet needs" **[V]**.)
- **The admin scope is a superset of the public scope** for views and Livewire/Filament PHP (it scans all of
  `app/Filament/**`, `app/Livewire/**`, `resources/views/{filament,livewire,components}/**`, which contain the public
  variants), but **not** for `app/Support/PublicContent` and `app/Enums` is admin-only. This asymmetry is what makes
  the admin card-template preview and any future public enum consumer interesting (§3, §6) **[V]**.
- **Out-of-panel pages opt out of Tailwind entirely**: the maintenance page
  ([resources/views/public/maintenance.blade.php](../../resources/views/public/maintenance.blade.php)) carries its own
  handwritten `<style>` block and BEM-style `podtext-maintenance-form__*` classes (31 class attributes, zero Tailwind
  utilities, grep-verified); the raw-HTML override path renders verbatim. The single mail view has no class
  attributes. So today, nothing outside the two panels needs scanning — which is also why the dead `app.css` entry has
  no visible victim **[V]**.

---

## 3. Class-emitting surface inventory

Sweep method **[V]**: repo-wide greps for (a) colour-utility literals (`bg|text|border|ring|divide|fill|stroke` ×
palette × shade) across `app/ config/ database/ lang/`, (b) layout/typography literals (`inline-flex`, `gap-N`,
`rounded-*`, `text-xs…`, `items-center`, …) across all non-Filament app dirs, (c) `HtmlString` construction sites,
(d) Alpine/JS class concatenation in blades (`:class` with `${`/`+`), (e) DB/JSON template storage. Coverage below is
per theme: **Direct** (inside a glob), **Accidental** (outside every glob but the utilities happen to be compiled via
other scanned files — works today, breaks when the coincidence ends), **Missing** (outside globs and absent from the
compiled CSS).

### 3.1 PHP class homes

| # | Home (file:line) | Emits | Renders on | Admin theme | Public theme | Status |
|---|---|---|---|---|---|---|
| 1 | `app/Enums/{FunnelStage,DashboardReason,DashboardTier,SparklineTrend,MediaDiagnosticReason}.php` | dashboard bar/stroke/text colour classes | admin dashboard widgets | **Direct** (glob added `b9825c6`) | not scanned (no public consumer found **[I]**) | SAFE (admin); structural trap if an enum ever styles a public surface |
| 2 | `app/Filament/**` HtmlString builders — [BlockersQueueWidget.php:76-82](../../app/Filament/Widgets/BlockersQueueWidget.php) heading (`inline-flex items-center gap-3`), `EpisodeWorkspaceForm`, `MediaTable`, `OwnerImageColumn` | heading/preview markup utilities | admin | **Direct** | n/a | SAFE |
| 3 | [ShowContentItem.php:381-401](../../app/Filament/Public/Pages/ShowContentItem.php) base badge/identity literals + CSS-var arbitrary utilities (`text-[var(--podcast-identity-color)]` …) | public item page | public (+admin superset) | **Direct** | **Direct** — var-utilities confirmed in compiled public CSS (4 × `podcast-identity-color` refs) **[V]** | SAFE |
| 4 | `app/Support/PublicFront/Cards/*` — presenters + [PublicFrontCardTemplateRenderer.php:106-120,154-168,238-254,435-443](../../app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php) (`lineClampClass` bounded match → `line-clamp-1..5`) | card layout/typography/clamp classes | public cards; admin card-template preview | **Direct** (glob added `f56ef36`) | **Direct** | SAFE — and the model DB-token pattern, see §3.3 |
| 5 | [PublicContentCardOptions.php:111-146](../../app/Support/PublicContent/PublicContentCardOptions.php) (`p-4 gap-3`/`p-5 gap-4` density, `rounded-none…rounded-full` radii, image-fit classes) | card padding/radius | public cards; **admin card-template preview** (`CardTemplateEditorPage::$previewHtml` → `@include('filament.pages.card-template-preview')` at [card-template-editor.blade.php:212](../../resources/views/filament/pages/card-template-editor.blade.php)) | **Accidental** — `.p-5`, `.gap-4`, `.rounded-xl`, `.rounded-sm` each appear once in the compiled admin CSS via other scanned sources **[V]** | **Direct** | AT-RISK (admin preview) |
| 6 | [SafeMarkdownRenderer.php:30,35](../../app/Support/Markdown/SafeMarkdownRenderer.php) — `publicContentClasses()` / `publicTranscriptClasses()` prose contracts: `space-y-4 … [&_a]:text-primary-700 [&_h1]:text-3xl … [&_blockquote]:border-s-2 [&_code]:bg-gray-100 …` | the entire public markdown/transcript prose styling | public: [markdown-content.blade.php:5](../../resources/views/components/public/markdown-content.blade.php), [content-item-transcript-viewer.blade.php:297,314](../../resources/views/livewire/public/content-item-transcript-viewer.blade.php), about page via [PublicAboutPageRenderer.php:29-32](../../app/Support/PublicFront/About/PublicAboutPageRenderer.php) | **Missing** (`[&_…]` variants: 0 hits) | **Missing** — `_a]`, `_h1]`, `_blockquote` probes all **0 hits** in the compiled public theme; only coincidental plain utilities (e.g. one `space-y-4`) survive **[V]** | **BROKEN NOW (ACTUAL)** — headings/links/blockquote/code inside public prose render with Preflight-flattened defaults |
| 7 | [PublicItemPageRegistry.php:249-280](../../app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php) — `infoBadgeSizeClass()` (`gap-1 px-1.5 py-0.5 text-xs` / `gap-2 px-2.5 py-1.5 text-sm` / …), `infoBadgeColorClass()` (six palette rows incl. `border-sky-200 bg-sky-50 … dark:bg-sky-950`), `podcastIdentityTextColorClass()` | item-page info badges + identity link colours | public item page — **delegated to** from scanned [ShowContentItem.php:381-401](../../app/Filament/Public/Pages/ShowContentItem.php) (delegation verified; literals live only in the registry) | **Missing** | **Missing** — `px-2.5`, `py-0.5`, `gap-1.5`, `text-sky-700`, `dark:bg-sky-950` all **0 hits** in compiled public CSS **[V]** | **BROKEN NOW (ACTUAL, config-gated visibility)** — the page-owned base half (`inline-flex … rounded-md border`) compiles, the registry half doesn't; which badges/palettes are visibly wrong depends on episode-page settings |
| 8 | [PublicFrontIconRegistry.php:276,290,295](../../app/Support/PublicFront/Icons/PublicFrontIconRegistry.php) — select-option HTML (`flex items-center gap-2`, `inline-flex h-5 w-5 … rounded border border-gray-300 text-xs text-gray-400 dark:border-gray-600`, `block truncate text-gray-500 dark:text-gray-400`) | admin icon-picker option markup (`IconSelect`, settings subject schemas) | admin | **Accidental** — every probed utility (incl. `dark:` variants) present via other scanned sources **[V]** | n/a | AT-RISK (admin) |
| 9 | `app/Support/PublicFront/Colors/PublicFrontColor.php` + [PublicItemPagePodcastPalette.php:39-41,164](../../app/Support/PublicFront/ItemPage/PublicItemPagePodcastPalette.php) | **no classes** — hex math emitted as CSS custom properties, consumed by the var-utilities of row 3 | public item page | n/a | n/a | SAFE — this is the sanctioned dynamic-value pattern (§4.1) |
| 10 | Everything else — `app/Settings`, `app/Models`, `app/Http`, `app/Jobs`, `config/`, `database/`, `lang/`, remaining `app/Support` subdirs (`About`, `Menu`, `Sections`, `Groups`, `Forms`, `Maintenance`, …) | no Tailwind literals found (colour + layout sweeps) | — | — | — | CLEAN **[V]** |

### 3.2 Blade & JS surfaces

- `resources/views/filament/**`, `livewire/**`, `components/**`: admin **Direct** (full-tree globs); the public theme
  scans only its `…/public/**` subsets plus `filament/tables/**` (which holds the shared
  `public-content-item-card.blade.php` column view — that glob exists precisely because a public table renders it)
  **[V]**.
- `resources/views/public/**` (maintenance) and `resources/views/mail/**`: deliberately Tailwind-free, no scan needed
  **[V]** (§2).
- JS: `resources/js/app.js` is empty (3 bytes); no blade builds class strings in Alpine/JS (`:class` concatenation and
  `-${` template-literal sweeps: zero class-context hits; the single `fi-${…}` hit is a modal id, not a class) **[V]**.

### 3.3 Database / revision JSON

Card-template revision JSON stores **bounded option tokens** (`'sm'`, `'lg'`, densities, radii, clamp integers), never
class strings; scanned PHP `match` arms own the complete literals (renderer rows 4-5 above; `lineClampClass()` clamps
to 1..5 with a default arm). No class-shaped fields exist in the template registry/validator (grep-verified). Podcast
identity colour is the one continuous DB-driven value, and it travels as hex → CSS custom properties → var-utilities
whose *literals* live in scanned `ShowContentItem` (row 3) **[V]**. Static scanning can never see the database — and
this repo currently, correctly, never asks it to. The invariant worth writing down: **a DB value may select a class or
parameterize a CSS variable, but the class literal itself must live in scanned source** (§5).

---

## 4. Findings per research question

### 4.1 Q1 — What the official docs say

Filament 5 **[V]**:
- The default panel stylesheet contains only Filament's own UI styles; *any* Tailwind utility in your own Blade/PHP
  requires a custom theme, and without one the classes silently do nothing (docs state this verbatim). This silent
  failure is exactly the `unscanned-home` mechanism, acknowledged upstream.
- The generated theme ships `@source` for `app/Filament/**` and `resources/views/filament/**`, and the docs instruct:
  "**Add your own directories** where you use Tailwind classes", with `app/Livewire/**`, `resources/views/livewire/**`,
  `resources/views/components/**` as the worked example, linking to Tailwind's explicit-sources docs. PodText's theme
  files are a faithful extension of this pattern.
- Plugins should not precompile Tailwind; users add plugin vendor dirs via `@source` (the Curator glob follows this).

Tailwind v4 **[V]**:
- Scanning is **plain-text token matching** (no parsing): a literal anywhere in a scanned file compiles, string
  interpolation never does. Official guidance: always write complete class names; map tokens to full literals — the
  repo's enum/`match` homes are the sanctioned shape, *provided the file is scanned*.
- Automatic detection (CWD-rooted, `.gitignore`-respecting) exists but Filament opts out with `source(none)`, which the
  Tailwind docs recommend exactly for multi-stylesheet setups. So "Tailwind scans everything by default" is **false
  inside Filament themes** — a common trap when reasoning from generic Tailwind knowledge.
- The v3 `safelist` config **does not exist in v4**. The replacement is `@source inline("…")` in CSS (supports variant
  prefixes `{hover:,focus:,}` and brace-expanded ranges, e.g. `bg-red-{50,{100..900..100},950}`), plus
  `@source not inline()` to exclude. This is the only sanctioned "generate classes with no source file" mechanism.
- For database/API-driven styling, the docs recommend inline styles or CSS variables consumed by static var-utilities —
  not class generation.

### 4.2 Q2 — FilamentExamples MCP protocol run

Protocol per CLAUDE.md: decomposed topics, two passes, limit 8 then 3; only `search-examples` is exposed by this MCP
(no source/read/details tool) — recorded honestly. **Fold this subsection into
`docs/research/filament-examples-phase-02.md` later** (that file is held dirty by another session; deliberately not
touched here).

- **Pass 1** (limit 8): `custom theme css` · `theme @source directive` · `tailwind classes blade view` ·
  `panel viteTheme` · `dark mode classes` · `custom css classes resource` → 8 example projects, 117 KB result read in
  full.
- **Pass 2** (limit 3, refined): `source inline safelist` · `enum badge color tailwind class` ·
  `register css asset FilamentAsset` → 3 projects (headers catalogued; first project + provider sections read).
  **Notable negative result: no example in the corpus uses `@source inline()` or any safelist** — the safelist queries
  matched unrelated `FilamentAsset` projects.

| Example (path prefix `v4/…`) | Pattern to copy | Pattern to reject / note |
|---|---|---|
| `full-projects/github-style-user-profile-with-activity-heatmap` | Class-emitting PHP (`ViewUser::getHeatmapData()` maps ratio → `bg-primary-…` literals via `match`) kept **inside `app/Filament`**, which its theme globs; blade legend repeats the literal list (belt-and-braces scan anchors) | Theme globs only `app/Filament` + `views/filament` — works because *all* class-emitting code lives there; PodText's Support homes break this assumption |
| `full-projects/material-theme` | Restyle vendor UI via `.fi-*` selectors + `@apply`/`@theme`/`@utility` in theme.css — heavy redesign with a single `views/filament` glob, near-zero scan surface | Utilities-in-markup avoided for vendor chrome; good default for admin restyling |
| `full-projects/search-to-sidebar` | Theme with **no `@source` at all** (pure `.fi-*` overrides) — valid when you emit no utilities of your own | — |
| `full-projects/branded-filament-panel-with-sidebar-profile-card` | Imports `tailwindcss source(none)` + vendor `index.css` directly and adds the two standard globs; render-hook blade lives under `views/filament/**` so its utilities are scanned | Confirms render-hook views must live inside globbed dirs (PodText's do: gallery hooks under `views/filament/…`, public header via `app/Livewire/Public` + `views/livewire/public`) |
| `full-projects/hotel-management-bookings`, `repair-salon-crm`, `lms` | Multi-panel apps where **only** the panel that needs custom styling gets `viteTheme()`; status colours via Filament's `->color(Color::…)` / semantic names — the **scan-free** colour channel for admin UI | Enum colour-classes are *not* the corpus norm for Filament-native components; raw class emission belongs to custom blades/dashboards only |
| `tables/table-customized-design-viewcolumn` | DB-driven colour via `style="--industry-color: {{ $color }}"` + handwritten semantic CSS using `color-mix()`; `HtmlString` labels use inline `style=`, zero scanned utilities | Independent confirmation of the CSS-variable strategy for DB values (matches Tailwind docs and PodText's palette path) |
| `full-projects/drag-to-resize-sidebar`, `privacy-mode-toggle` (pass 2) | `FilamentAsset::register([Css::make(…)])` for handwritten static CSS — a second sanctioned "no scanning involved" channel | Don't use it for utility-classed markup; it bypasses Tailwind entirely |

### 4.3 Q3 — LaravelDaily / FilaCheck

- **FilaCheck-Pro ships exactly one theme-related rule**, `custom-theme-needed`
  (`vendor/laraveldaily/filacheck-pro/src/Rules/CustomThemeNeededRule.php`, read in full **[V]**): a **Blade-only**
  rule (`BladeRule`; its AST `check()` returns `[]`) that regex-detects Tailwind-shaped tokens in blade files and
  warns **only when no custom theme exists at all** (`hasCustomTheme()` = any CSS under `resources/css/filament/` or
  any `->viteTheme(` in a provider). Auto-fix runs `make:filament-theme`.
  **Consequences:** once a theme exists — as in PodText — the rule is permanently silent; it never validates `@source`
  coverage and never scans PHP for class emission. **The `unscanned-home` failure mode is invisible to
  FilaCheck/FilaCheck-Pro.** The repo's scan-scope test is already ahead of the ecosystem tooling, and the guard in §5
  fills a real gap rather than duplicating a vendor check. (Bonus: the rule's Tailwind-token regexes are a ready-made,
  battle-tested detector worth borrowing for the guard.)
- The media-embeds guideline's "Avoid Blade Tailwind classes outside theme coverage" therefore has **no mechanical
  enforcement today** beyond the single pinned-glob test at `DashboardEnumsTest.php:102` **[V]**.
- **LaravelDaily materials** (v3-era mostly, v4 stance confirmed at site level): custom Blade files with Tailwind
  classes ⇒ create a custom theme and add those folders to `theme.css`; their tutorials lean toward styling via CSS
  file (`@apply`, semantic classes) over utilities sprinkled in markup — same philosophy as the FilamentExamples
  tutorial. No LaravelDaily material found that addresses PHP-homed class strings or scan-scope verification; the
  subject is effectively undocumented in their catalogue **[V at search level; some articles paywalled]**.

### 4.4 Q4 — Inventory analysis

See §3. The headline: the repo already contains **all three sanctioned patterns done right** (bounded token → literal
maps in scanned PHP; CSS-variable channel for continuous DB values; semantic-CSS overrides in the theme files) *and*
**four live deviations**, all of the same species — a class home born outside the glob list:

1. `SafeMarkdownRenderer` (ACTUAL, both themes) — likely originated as a blade→PHP consolidation
   (`2a5ff96`, `0b067e9` touch the same class strings in views history), i.e. the exact ledger cause: "consolidating
   class literals into a PHP home silently fails if the scan scope does not include that home" **[V for the gap; I for
   the consolidation narrative]**.
2. `PublicItemPageRegistry` (ACTUAL, public theme) — reached only through delegation from a scanned file, which makes
   it invisible to any "does the rendering file look scanned?" reasoning; only the *literal's* home matters.
3. `PublicFrontIconRegistry` (POTENTIAL, admin) — held up by accidental coverage.
4. `PublicContentCardOptions` (POTENTIAL, admin preview surface) — held up by accidental coverage; its *public* use is
   properly scanned.

And one adjacent hygiene finding: `resources/css/app.css` compiles 77 KB of never-served CSS on every build (dead
entry since the welcome view was removed) **[V]**.

### 4.5 Q5 — Weighing the options

| Option | For | Against |
|---|---|---|
| **A. Broad glob (`app/**` per theme)** | Kills the whole bug class; zero maintenance | Junk-token pickup (Tailwind scans plain text, so PHP words like `hidden`, `block`, `table`, `grid`, `flex`, `container` in *any* string compile as utilities); both panels swallow each other's classes; erases the "styles live in declared homes" signal; CSS bloat is real but small (KB-scale) — the principled costs outweigh the KBs |
| **B. Targeted globs (status quo)** | Matches Filament docs, corpus, LaravelDaily; smallest CSS; expresses intent | Proven failure mode — humans forget the glob when a new home is born (once fixed, twice live, twice accidental as of this audit); needs a guard with discovery, not pinned strings |
| **C. `@source inline()` safelists as the mechanism** | Only way to emit classes with no source file; official v4 safelist | Duplicates class lists into CSS where they drift from the PHP that renders them; per-theme repetition; the corpus contains zero uses; right as a *last resort*, wrong as a *policy* |
| **D. Single class-vocabulary home (all class-emitting PHP in one always-scanned dir)** | One glob to rule them all; trivially guardable | Forces file moves and severs class maps from their domain owners (Cards presenters, ItemPage registry are domain code); convention is enforceable only by the same kind of guard B needs anyway — so it adds migration cost without removing the guard |

**Chosen: B hardened** — targeted globs, maintained as a shared root list, plus a discovery-based guard (§5). D's
spirit survives as a naming convention (new class homes go under an existing globbed root when reasonable); A and C are
rejected as defaults but C stays documented for the genuinely source-less case.

---

## 5. Recommended policy and guard sketch

*(Recommendation only — this session changed no code. Implementation belongs to a feature session; see §6 Q3.)*

### Policy (four rules)

1. **Every Tailwind class literal lives in a file covered by the `@source` globs of every panel that can render it.**
   The glob lists in both theme files are maintained as one shared set of *class-home roots* plus per-panel dirs.
   Closing today's gaps means adding `app/Support/Markdown/**` and `app/Support/PublicFront/ItemPage/**` to **both**
   themes, `app/Support/PublicFront/Icons/**` to the **admin** theme, and `app/Support/PublicContent/**` to the
   **admin** theme (card-template preview parity). Enum symmetry is an operator question (§6 Q1).
2. **The database never stores class strings.** Revision JSON and settings store bounded tokens; scanned PHP `match`
   maps own the literals (renderer pattern, rows 4-5/§3.3). Continuous values (colours sampled from artwork, per-show
   accents) travel as CSS custom properties set inline, consumed by var-utilities whose literals live in scanned files
   (palette pattern, rows 3/9). Free-text class fields are banned from template/settings schemas.
3. **`@source inline()` is the escape hatch, not the mechanism** — permitted only when a class provably has no
   possible source-file home, with a comment naming the consumer. Today that set is empty; if rule 2 holds it stays
   empty.
4. **Prefer the scan-free channels where they fit**: Filament `->color()` / semantic colours for Filament-native
   components; `.fi-*` + `@apply` overrides in theme.css for vendor chrome; `FilamentAsset`/handwritten CSS for
   static, non-utility styling. Raw utility emission from PHP remains legitimate for owned blades (dashboard bars,
   cards, prose contracts) — that is exactly the surface the globs and guard protect.

### Guard sketch (generalizing `DashboardEnumsTest.php:102`)

One new Pest test, `tests/Feature/ThemeScanScopeTest.php`, with three assertions:

```php
it('covers every class-emitting php home with the theme @source globs', function (): void {
    // 1. Parse globs from both theme files (single source of truth: the CSS itself).
    $globs = fn (string $css): array => collect(explode("\n", File::get(resource_path($css))))
        ->filter(fn ($l) => str_starts_with(trim($l), '@source'))
        ->map(fn ($l) => Str::of($l)->after("'")->before("'")->replace('../../../../', '')->before('**')->value())
        ->all(); // → path prefixes like "app/Enums/", "app/Support/PublicFront/Cards/"

    $admin  = $globs('css/filament/admin/theme.css');
    $public = $globs('css/filament/public/theme.css');

    // 2. Discover class-emitting PHP under app/ with FilaCheck-style token regexes
    //    (colour-with-shade, spacing-with-number, responsive/variant prefixes, arbitrary values).
    $emitters = collect(File::allFiles(app_path()))
        ->filter(fn ($f) => preg_match(TailwindTokenPatterns::any(), $f->getContents()))
        ->map(fn ($f) => Str::after($f->getPathname(), base_path().'/'));

    // 3. Every emitter is inside BOTH scopes, unless the panel-exception map says otherwise
    //    (each exception names the panel that provably never renders it, with a reason).
    $exceptions = [
        'app/Enums/' => ['public' => 'dashboard-only colour homes; revisit if an enum styles a public surface'],
        // admin-only homes (e.g. Icons registry) would carry a ['public' => …] row here, and vice versa.
    ];

    foreach ($emitters as $file) {
        $coveredByAdmin  = Str::startsWith($file, $admin)  || excepted($exceptions, $file, 'admin');
        $coveredByPublic = Str::startsWith($file, $public) || excepted($exceptions, $file, 'public');
        expect($coveredByAdmin && $coveredByPublic)
            ->toBeTrue("Class-emitting file outside scan scope: {$file}");
    }
});
```

Properties that make this the right guard shape:

- **Discovery, not pins** — a *new* class home fails the test the day it is born, which is the failure mode all four
  live gaps share; the existing pinned-glob assertion stays as the historical regression pin.
- **The CSS is the source of truth** — glob edits are picked up automatically; no parallel list to drift.
- **Default-both coverage with named exceptions** inverts today's silent asymmetry: scanning one panel too many costs
  kilobytes; scanning one too few costs production styling. Exceptions are cheap to grant and self-documenting.
- **Honest limits (stated, not hidden):** regex discovery inherits FilaCheck's heuristics — false negatives for
  exotic literal shapes (e.g. a bare `truncate` with no other token) and rare false positives (a PHP string that merely
  looks like a utility) are possible; the exception map is the escape hatch for both. And a green test does not prove
  the *compiled output* (a glob could be present but misspelled) — if that residual risk matters, one compiled-output
  spot-probe per theme (grep the built asset for one sentinel class per home, as this research did) can ride the
  browser-test suite; recommended as optional, not core.

---

## 6. Open questions for the operator

1. **Enum symmetry:** should `app/Enums/**` join the public theme's globs now (symmetric, future-proof, tiny CSS
   cost), or stay admin-only behind an explicit exception row until a public surface consumes an enum class? (Research
   found no public consumer today **[I]**.)
2. **Dead entry `resources/css/app.css`:** it builds 77 KB of never-served CSS (no `@vite` anywhere since `ac9b0e5`).
   Remove it (and the empty `app.js`) from the Vite inputs, keep it as a future non-panel-page seat, or leave as-is?
   Removal also deletes the misleading impression that "views are scanned anyway" — today that entry is the only place
   auto-detection-style broad globs exist, and nothing uses its output.
3. **Fix ownership and sequencing:** the four glob additions + the guard test are app-code changes outside this
   session's mandate. One small feature session can land globs + `npm run build` + guard together (the two ACTUAL
   gaps are user-visible on the public item/about/transcript pages, so this is a real fix, not hygiene). Where does it
   sit relative to the current route (Prompt 13 → FETCH2 → 9F-mini)?
4. **Split badge home:** `ShowContentItem` holds the badge *base* literals while the registry holds size/colour maps
   (§3 rows 3/7). After the glob fix this works; does the operator also want the one-home cleanup (move base literals
   into the registry, or the maps into the page) while someone is in there?
5. **Guard strictness:** is the optional compiled-output sentinel probe (one grep per theme per home against
   `public/build`) worth adding to the browser-test tier, or is the source-level invariant enough?

## 7. Pattern evidence for the orchestrator (`unscanned-home` follow-through)

*(The ledger is orchestrator-owned; rows below are contribution candidates, not edits.)*

- **ACTUAL — new instance:** `app/Support/Markdown/SafeMarkdownRenderer.php:30,35` — entire public prose contract
  (`[&_h1]:…`, `[&_a]:…`, `[&_blockquote]:…`) absent from **both** compiled themes (probes `_a]`/`_h1]`/`_blockquote`
  = 0 hits, build 2026-08-03 07:42); renders on public markdown/about/transcript surfaces via
  `markdown-content.blade.php:5`, `content-item-transcript-viewer.blade.php:297,314`.
- **ACTUAL — new instance (delegation variant):** `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php:249-280`
  — badge size/colour/identity maps absent from compiled public theme (`px-2.5`, `py-0.5`, `gap-1.5`, `text-sky-700`,
  `dark:bg-sky-950` = 0 hits) while the delegating scanned page `ShowContentItem.php:381-401` contributes only the
  base literals. Demonstrates that the *literal's* home decides scanning, not the renderer's.
- **POTENTIAL:** `app/Support/PublicFront/Icons/PublicFrontIconRegistry.php:276,290,295` (admin icon-picker markup)
  and `app/Support/PublicContent/PublicContentCardOptions.php:111-146` (admin card-template preview) — outside every
  admin glob, alive only through accidental coverage by unrelated scanned files (probes ≥ 1 hit each).
- **POTENTIAL — structural:** admin scans `app/Enums`, public does not; the first enum that styles a public surface
  reproduces the original incident on the public panel.
- **Observation (not a pattern row):** `resources/css/app.css` is a compiled-but-never-served entry (77 KB/build)
  since `ac9b0e5` removed the welcome view — candidate for a small cleanup chip.
