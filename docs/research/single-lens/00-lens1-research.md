# LENS1 Single-Lens Research

## Scope and contract

This note records the installed-code research for prompt LENS1 v1
(2026-07-13). The required outcome is a mode-aware lens over the existing
transcription model: single mode presents one episode transcript and counts
distinct episodes through their effective transcription; multi mode preserves
the existing record-oriented behavior and strings.

No Composer or npm dependency change is required.

## Installed-version and data findings

- Laravel is 13.19.0, Filament is 5.6.7, Livewire is 4.3.3, Pest is 4.7.4,
  and Tailwind CSS is 4.3.2.
- `transcriptions` is indexed for the existing effective-record lookup by
  `content_item_id`, publication status, and publication time.
- `content_items.featured_transcription_id` is the explicit preferred row.
- The effective public transcription is already resolved as the published
  featured row, falling back to the latest published row.
- Existing public aggregate code uses correlated subqueries. This is the right
  place to switch single-mode contributor and podcast counts to distinct
  episode IDs without adding Blade queries or N+1 work.

## Creation-path findings

`Transcription::booted()` is already the shared lifecycle choke point used by
direct model writes, factories, the standalone Filament resource, relation
manager creates, item-table quick creates, and importer persistence.

The existing `created` callback already auto-features the first transcription
when the episode has no featured ID and no other row. It refuses to replace an
existing featured ID and refuses to feature a second row. LENS1 therefore
keeps that rule and adds behavioral coverage for every required creation path.

The missing rule is a shared `creating` guard: in single mode a second row is
currently accepted. The episode workspace's “start fresh” action intentionally
creates a replacement row and then adopts it, so that one operation needs a
narrow, exception-safe bypass around the shared guard. No other path is
exempt.

## Public count and rendering findings

- `PublicTranscriptionPolicy` currently returns stored `all_published` modes
  unchanged. A deployment switched back to single mode can therefore retain a
  hidden multi setting and expose multiple rows/counts. Single mode must force
  effective-only selection at the policy boundary; multi mode must return the
  stored values exactly as before.
- `PublicTranscriptionAggregates` currently counts transcription row IDs for a
  contributor and raw rows for a podcast. Single mode must count distinct
  `content_items.id` after the existing effective-transcription predicate.
- `PublicContentItemCardPresenter` already suppresses the normal metadata badge
  when its computed display option is false, but a stored custom template can
  still address `content_item.transcription_count`. A presenter-level hard
  suppression is required in single mode.
- Contributor and podcast cards use the same base transcription-count key as
  fuller preview/detail surfaces. Separate single variants are needed for the
  approved short “episodes” card wording and “transcribed episodes” fuller
  wording while keeping every existing multi string unchanged.
- `ContentItemTranscriptViewer` can honor a stored multiple-transcriptions
  setting. The policy boundary must make its collection effective-only in
  single mode, and a rendered test must prove the switcher is unreachable.

## Admin resource findings

- The standalone Transcriptions table already leads with the related item
  title and eagerly loads transcribers.
- It has no featured column/filter, but it has a set-featured row action. That
  action is a multi affordance and must be unavailable in single mode.
- Filament applies table query scopes before filters. A mode-aware ternary
  filter can apply the effective-row predicate on its blank/default branch and
  omit it on its active history branch. The filter can be hidden from admins
  and remain visible to super-admins in either mode.
- The episode relation manager shows attached history, featured state, and its
  raw row-count badge. The operator confirmed during the run that this is an
  intentional operational surface and must remain unchanged.
- The item and group-item tables contain effective/featured/count columns. The
  operator likewise confirmed that these columns are intentionally retained in
  single mode; LENS1 does not hide, relabel, or change their queries.

## Filament and framework research

Installed Filament source confirms that table `modifyQueryUsing()` scopes are
applied before filter callbacks, and that `TernaryFilter::queries()` supports a
blank/default query branch. Installed Laravel guidance confirms correlated
`addSelect` subqueries as the appropriate no-N+1 pattern. FilamentExamples
searches covered resource query modification, custom filters, conditional
visibility, and table testing; only search names/snippets were available, not
full example source.

Relevant patterns found:

- table `modifyQueryUsing()` for eager loading and invariant query shaping;
- filter query callbacks for user-selected query changes;
- conditional `visible()`/`hidden()` gates for role- and mode-specific
  columns/actions;
- Filament table assertions for filters, columns, and records.

## Leak-audit surfaces and verdicts

The following is the complete LENS1 verification surface list. “Fix” means the
current code leaks single-mode plurality or multi policy. “Assert” means the
current architecture is compatible but needs regression coverage. “Keep” means
the wording describes a transcript document or transcriber concept rather than
per-episode plurality.

| Surface | Files/components checked | Pre-code verdict | LENS1 treatment |
| --- | --- | --- | --- |
| Public policy boundary | `PublicTranscriptionPolicy`, `PublicTranscriptionSelector` | Fix: stored multi policy can survive a mode switch | Force effective-only display/count modes in single; exact stored modes in multi |
| Contributor aggregates | `PublicTranscriptionAggregates`, `PublicContributorDiscovery` | Fix: counts distinct transcription rows | Count distinct effective episode IDs in single; keep row count in multi |
| Podcast aggregates | `PublicTranscriptionAggregates`, `PublicContentGroupQueries` | Fix: counts transcription rows | Count distinct public episode IDs in single; keep row count in multi |
| Episode aggregates | `PublicTranscriptionAggregates` | Assert: effective-only is mathematically zero/one once policy is forced | Add stray-row regression coverage |
| Contributor cards | `PublicContributorCardPresenter` and card views | Fix: “transcriptions” label | Single short “episodes” variant |
| Contributor previews/pages | top-transcriber, directory, and contributor page views | Fix: raw row label/key | Single fuller “transcribed episodes” variant |
| Contributor episode-list heading | `contributor-transcription-list.blade.php` | Fix: “Contributor transcriptions” | Single “Transcribed episodes” / “פרקים שתומללו” variant |
| Podcast cards | `PublicContentGroupCardPresenter` | Fix: transcription count and latest-transcription date | Single short “episodes” count and latest-episode date variants |
| Podcast detail summary | `show-content-group.blade.php` | Fix: transcription count and latest-transcription date | Single fuller count and latest-episode date variants |
| Episode card custom count part | `PublicContentItemCardPresenter` | Fix: stored template can still address count | Return no part/value in single, regardless of stored template |
| Episode detail count info | `ShowContentItem` and item view | Assert after policy clamp | Ensure count field stays absent in single |
| Public transcript viewer | `ContentItemTranscriptViewer` and Blade | Fix/Assert: stored multi switch can remain true | Force one effective row and assert no switcher in single |
| Standalone Transcriptions resource | resource, form, table, list/create/edit pages | Fix: generic plurality and all-row default | Episode-language variants; effective row default; super-admin history filter |
| Standalone featured action | Transcriptions table | Fix: multi affordance remains visible | Hide in single; unchanged in multi |
| Episode relation manager | table, filters, actions, tab badge | Intentional operational history surface per operator clarification | Keep byte-identical in both modes |
| Item admin table | `ContentItemsTable` | Intentional featured/context surface per operator clarification | Keep byte-identical in both modes |
| Group-items admin table | `ContentItemsRelationManager` | Intentional featured/count surface per operator clarification | Keep byte-identical in both modes |
| Classic item form | `ContentItemForm` | Assert: featured section is already multi-gated | Preserve gate and add/retain coverage |
| Episode workspace | workspace schema/page | Fix: hidden-alternates hint can reveal plurality | Suppress alternate-row hint in single; variant visibility/helper wording |
| Workspace replace flow | `startFreshWorkspaceTranscription()` | Required exception | Allow only the scoped fresh-replacement create, then adopt it |
| Admin dashboard widgets | dashboard widget registration and metrics | Keep: no per-episode transcription-count widget is present | Record audit; no change |
| Navigation badges | resource/navigation definitions | Keep/Fix: no numeric resource nav badge, but plural resource label exists | Single episode-transcript label variant; no numeric badge change |
| Content-item import/export | importers/exporters and column keys | Assert: featured reference fields are multi metadata, not public display | Keep schemas byte-identical; no dependency or format change |
| Transcription import/export | importer/exporter and CSV headers | Keep: row-oriented portability remains an admin data operation | Shared creation guard blocks invalid second imports in single; headers unchanged |
| Card-template editor options | settings and template registry | Assert: ROLES1 blocks new count usage in single | Keep stored data; runtime suppression closes the remaining leak |
| Public settings multi controls | `PublicContentSettings` | Assert: ROLES1 hides and protects values | Keep, with policy-clamp tests after mode switch |
| Translation catalogue | `lang/en`, `lang/he` admin/public | Fix only mode-sensitive episode-plural strings | Add variant keys; never edit base multi strings |

## Test areas selected

- direct, standalone resource, and relation-manager first-create feature rules;
- second-create behavior in both modes, including localized validation;
- workspace fresh replacement in single mode;
- effective-only/distinct episode aggregates with deliberately seeded history;
- helper and representative public rendered labels in both locales/modes;
- custom episode card count-part suppression;
- standalone resource and relation-manager query/visibility behavior by role;
- public viewer no-switcher behavior;
- existing roles, workspace, public-front, and import/export regression suites.

## Uncertainty for operator review

The standalone resource's single-mode noun is planned as “Episode transcript”
(`תמלול הפרק`) rather than merely “Transcript.” This most clearly states the
property-of-episode ontology without renaming the underlying model. The label
inventory in the implementation plan marks every place where that choice is
applied so it can be vetoed in review.
