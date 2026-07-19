# Step 5B Card Template sample-ranking parity research

Date: 2026-07-19
Audit: `LS-20260719-STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-01`
Approved option: `STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-PARITY`
Verified baseline: `27f38aeaebc8ab2ff4279abd2a905efdce82b495` on clean `main`, four commits ahead of `origin/main`

## Scope and approval boundary

FU02 aligns the focused Card Template preview's automatic sample, ten-option
preload, capped search, and rendered image with one current validated public
render context. It covers only effective-image ranking for content items and
content groups, current configured family/global defaults, the existing public
eligibility queries, unchanged contributor ordering, and the related
feature/Livewire/browser evidence.

The approval does not authorize FU03/O4 validation targeting, FU04 order
compatibility, FU05 interaction refresh closure, FU06 copy cleanup, legacy UX2
inline-header editing, a global explicit-order cutover, nested image enablement,
new contributor image fields, preview preference persistence, migrations,
dependencies, permission or settings-lifecycle changes, generalized renderer
work, production actions, another roadmap step, a branch/worktree, push, or PR.

## Verified provenance and preserved contracts

- O1 commits `215340d3` and `14285b34`, O2 commits `f56ef369` and
  `27f38aea`, and the navigation baseline `d8f42da` are present.
- `d8f42da..HEAD` has no change in the navigation map, its navigation test, or
  `lang/en/admin.php` / `lang/he/admin.php`.
- O1's responsive shell, focus restoration, exact `lg` boundary, and single
  preview-root behavior remain binding.
- O2's final visible-part flow, geometry, exact diagnostics, and Tailwind source
  coverage remain binding.
- The selector remains transient scalar state with exactly ten preloaded
  options, independently capped fifty-result search, public-safe selected-label
  lookup, and zero render/query/direct/forged behavior while restricted.
- The preview must stay read-only: no settings writer, lifecycle event, backup,
  reference scan, cache invalidation, local-development database probe, remote
  HTTP, filesystem-existence probe, or persistence.

The O2 handoff's status sentence still says its docs-only hash stamp is pending,
although `27f38aea` completed that stamp. FU02's required documentation sync may
correct only that stale statement.

## Installed-version and external research

Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2. Version-aware documentation confirms
that custom searchable Selects use `getSearchResultsUsing()` together with
`getOptionLabelUsing()`, and that server results should be bounded before they
are returned.

The required FilamentExamples research used two passes: direct queries for
custom searchable/preloaded Selects, selected labels, and bounded results, then
refined queries for `getSearchResultsUsing()`, `getOptionLabelUsing()`, preload,
and result limits. Results supplied real code snippets using a constrained
Eloquent query plus `limit(50)`. The configured integration exposes only
`search_examples`; no separate read/fetch/detail tool is available, so this was
search/snippet research rather than deep source retrieval.

The local development database remains off-limits. Query shape and columns were
verified from the current models, migrations, query services, and test-owned
SQLite fixtures rather than a live schema probe.

## Current mismatch

`CardTemplatePreviewer::preview()` calls the ordinary `sampleQuery()` and takes
its first result. That path does not apply image ranking. The ten-option preload
and fifty-result search route through `sampleOptionsQuery()`, which enables a
separate direct-image-only order. Content items recognize only nonblank
`image_path` or `external_thumbnail_url`; content groups recognize only
nonblank `cover_path`; contributors keep their discovery order.

The preview also constructs a registry-default `PublicFrontRenderContext`,
constructs a registry-default single-mode transcription policy, and explicitly
passes `inheritGroupCover: false` to the item presenter. It therefore cannot
rank or render an inherited group cover or a current configured family/global
default even though `PublicDefaultImageResolver` already defines those public
semantics.

The authenticated Stage 1 browser audit reached the signed-in Hebrew/RTL editor
at 1470 x 745 CSS pixels. It observed exactly ten preloaded options, live search
that narrowed `טכנולוגיה` to two results, native search focus plus ArrowDown and
Escape focus restoration, and a current selected preview with
`data-card-image-source="fallback"`. Because the audit claimed an existing tab
without reload and the visible labels contain no image provenance, it did not
claim that selection as a fresh automatic choice or infer local/external/
inherited/default cases. The fifty-result browser cap, restricted account, and
network/query planes remain fixture-backed Stage 2 requirements.

## Effective-image ranking contract

### Content items

1. Rank 0: nonblank own local `image_path` or own
   `external_thumbnail_url`. They are equal rank; when both exist the resolver
   continues to render the local path first.
2. Rank 1: a nonblank inherited ContentGroup `cover_path`, only when the
   content-item default-image mode is not `none`.
3. Rank 2: a validated configured content-item custom path, otherwise a
   validated configured global custom path whenever the content-item mode is
   not `none` and no usable family path won.
4. Rank 3: no effective URL. The existing visual fallback remains available
   when an image part is visible.

Within every item tier, keep the existing effective-transcription publication
order descending and item ID descending.

### Content groups

1. Rank 0: nonblank own `cover_path`.
2. Rank 2: validated configured group custom path, otherwise validated global
   custom path whenever group mode is not `none` and no usable family path won.
3. Rank 3: no effective URL/fallback.

Within every group tier, keep title ascending and ID ascending.

### Contributors

Contributor records have no image field. A configured contributor/global
default is family-wide, so it cannot distinguish records inside one query.
Retain the existing public transcription count descending, public item count
descending, name ascending, and ID ascending order. Do not invent a field.

### Shared selection behavior

- Public eligibility and search filtering run before the shared rank/order.
- Automatic selection takes the first shared ranked result.
- Preload takes the first exactly ten shared ranked results.
- Search takes at most fifty shared ranked results.
- An explicit selected ID remains accepted when the same public-safe family
  query finds it; rank never overrides an explicit choice.
- Selected-label resolution remains constrained to that same public query, and
  the page may keep using its locked current label without another query.
- Configured-default availability is uniform for otherwise image-less records
  in one family. Default and none cases are therefore proven under separate
  validated settings contexts.
- Ranking uses normalized configured modes and nonblank stored strings. It does
  not test whether a local file exists and does not fetch an external URL.

## Smallest safe implementation

Reuse the application-scoped validated `PublicFrontRenderContext` through
constructor injection in the existing `CardTemplatePreviewer`. Construct the
preview renderer, transcription services, default-image resolver, and ranking
from that one context. Preserve the preview's explicit unsaved
`PublicFrontCardTemplate`; no configured template is resolved in its place.

Remove the `imageFirst` fork. Route automatic selection, preload, search, and
selected-label lookup through one ranked family query. Keep item/group ranking
in SQL before `first()` / `limit()` / `get()`. Use model-derived table names,
bound configuration flags, and a bounded group-cover subquery; do not load an
unbounded candidate set into PHP.

Expose only the minimum query-neutral image-policy facts from
`PublicDefaultImageResolver` needed to keep the SQL tiers identical to rendered
semantics: whether an item may inherit its group cover and whether a family has
an effective configured default. Do not create a new generalized service.

Stop explicitly disabling group-cover inheritance in the preview item
presenter. The existing resolver then renders the same effective source that
the query ranked.

No change is expected in `CardTemplateEditorPage`, Blade, translations,
navigation, models, migrations, or configuration ownership.

## Security and performance boundaries

- Keep `PublicContentItemQueries::base()`, `PublicContentGroupQueries::base()`,
  and `PublicContributorDiscovery::contributors()` as the public eligibility
  sources. Do not replace them with direct unscoped model queries.
- Keep every page-level `canChoosePreviewSample()` check ahead of preload,
  search, label, selection, and preview work.
- Keep only sample ID, label, family, status, and rendered HTML in Livewire
  state; do not serialize option maps, models, relations, or config.
- Use bound search/config values and model-derived table names. No user input is
  interpolated into raw ranking SQL.
- Ranking must add no per-record query, resolver call, filesystem probe, or
  remote request. Query counts are measured separately from browser network,
  DOM, heap, listener, and timing planes.
- Preserve current cold/warm request behavior. If resolving the current context
  moves a numeric query count, report the context read separately and keep the
  sample-query count fixture-scale constant.

## Required verification

Focused Pest coverage must prove local, external, inherited, configured family,
configured global, and none cases; automatic/preload/search parity; explicit
selection; exact 10/50 bounds; family tie rules; contributor continuity;
public-eligibility exclusions; forged identity/label guards; restricted
zero-render/zero-query behavior; query-count constancy; no lazy loading; and no
settings writer/lifecycle/backup/reference/cache/persistence side effect.

Authenticated browser coverage must use test-owned fixtures to prove automatic
choice, visible ten-option order, capped search, rendered source markers for the
five effective-image cases, HE/RTL and EN/LTR context, viewport, keyboard/focus,
restricted absence, Livewire request observations where supported, and strict
console/smoke behavior. Preserve O1/O2 browser coverage and classify Chromium
bootstrap/rendezvous failures as infrastructure before retrying the identical
suite with the permitted runner.

Final verification follows the repository order on the final tree:
requirements sweep, `vendor/bin/pint --test`, `vendor/bin/filacheck`,
`npm run build`, then full serial `php artisan test` last. Any later file change
restarts at Pint.

## Stop conditions

Return to Stage 1 if implementation requires another bounded task, exceeds the
approved forecast materially, changes public eligibility or contributor
semantics, needs a new abstraction beyond the small resolver policy seam,
touches navigation or non-card-template translation copy, changes settings
lifecycle/ownership, introduces a migration/dependency/permission boundary,
or needs any excluded renderer, validation, interaction, persistence,
production, branch, push, or PR work.
