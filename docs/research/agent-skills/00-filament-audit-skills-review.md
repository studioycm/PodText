# Filament Audit Skills Review

> **Architecture reconciliation — 2026-07-16:** The skill validation remains
> current. Forward SP3 findings are superseded by the transcript-controlled
> ARCH1/SP3D disposition in
> `docs/research/settings-performance/07-sp3d-pre-research.md` and the reconciled
> audit in `08-sp3-filament-audit-skills-report.md`.

Date: 2026-07-15

## Scope

Review the supplied `filament-forms-ux-audit` and
`filament-performance-audit` skills, update them for the installed Filament 5
and Livewire 4 stack, install canonical project packages plus global Codex
copies, expose the project packages to Agents, Claude, and Junie, and exercise
both skills against the SP3 settings surfaces. This is an ad-hoc tooling task,
not an SP3D implementation run. It must not change SP3 application behavior,
storage, dependencies, migrations, or the local development database.

## Installed-version evidence

Laravel Boost reports PHP 8.4, Laravel 13.19.0, Filament 5.6.7, and Livewire
4.3.3. Installed package source confirms Filament 5 exposes operation-aware
`hiddenOn()`, `visibleOn()`, `disabledOn()`, hidden-state dehydration,
`Select::optionsLimit()`, `Select::searchDebounce()`, and blur-aware `live()`.

## Official documentation review

- Filament 5 resource forms already ignore the bound Eloquent record for
  `unique()` validation. A skill must not recommend `ignoreRecord: true` as a
  repair: <https://filamentphp.com/docs/5.x/forms/validation>.
- Growing relationship selects should search on the server. `preload()` loads
  relationship options when the page loads and is appropriate only for bounded
  sets; custom search callbacks must also resolve selected labels and cap their
  result queries: <https://filamentphp.com/docs/5.x/forms/select>.
- Custom-data tables do not receive automatic filtering/searching from an
  Eloquent query. Search, filters, sorting, and pagination must be implemented
  inside `records()`: <https://filamentphp.com/docs/5.x/tables/custom-data>.
- Filament tables support `deferLoading()` when initial table work is measured
  as expensive: <https://filamentphp.com/docs/5.x/tables/overview>.
- Filament stats and chart widgets poll every five seconds by default. Expensive
  aggregates should disable or lengthen polling and use correctly scoped,
  invalidated caches: <https://filamentphp.com/docs/5.x/widgets/charts>.
- Livewire 4 computed values are memoized for one request by default; persisted
  or shared computed caching needs explicit keys, expiry, invalidation, and
  tenant/user scope: <https://livewire.laravel.com/docs/4.x/computed-properties>.
- Livewire 4 islands, lazy/deferred components, and bundled lazy loads can
  improve initial rendering, but they shift work to other requests. Islands
  have scope and concurrent-state constraints and should not be prescribed
  without a measured, independent region:
  <https://livewire.laravel.com/docs/4.x/islands> and
  <https://livewire.laravel.com/docs/4.x/lazy>.
- The Livewire 4 upgrade guide records non-blocking polling, parallel live
  updates, consolidated array updates, new request interceptors, islands, and
  deferred/bundled loading. Audit guidance must account for request overlap and
  use current v4 hooks: <https://livewire.laravel.com/docs/4.x/upgrading>.

## Source-skill findings

### Forms UX skill

- Keep the existing finding-first workflow, severity model, and materiality
  guardrails.
- Replace the nonexistent `hiddenOnCreate()` example with Filament 5's
  `hiddenOn('create')` or an operation-aware callback.
- Remove `unique(ignoreRecord: true)` from the slug example because it
  contradicts both the skill guardrail and Filament 5 behavior.
- Correct the claim that `maxLength()` automatically displays a character
  counter; the documented guarantee is frontend and backend validation.
- Correct a paired date-time example that uses `DatePicker` for time-bearing
  fields.
- Avoid suggesting a visual `https://` prefix beside a full-value `url()` field
  unless the application deliberately stores only the host/path. Do not suggest
  reachability validation without discussing latency and network policy.
- Add required installed-version/documentation checks, localization/RTL and
  project-convention checks, hidden-state/dehydration review, selected-option
  validation, and a compact UI metadata file.

### Performance skill

- Keep the current table/query/widget/global-search reference routing.
- Add Livewire 4-specific review guidance for public state size, computed
  memoization boundaries, islands, lazy/deferred loading, bundled requests,
  parallel updates, and polling.
- Make clear that Sections and Tabs improve organization but do not by
  themselves reduce schema construction, HTML, or hydration state.
- Add custom-data table responsibilities, `deferLoading()`, server-side selected
  option lookup, request/tenant cache scope, and invalidation requirements.
- Add a two-plane measurement model: deterministic server/component/query/state
  caps versus authenticated browser DOM/listener/heap/network/navigation
  evidence. Never relabel Livewire test HTML as browser or teleported-modal DOM.
- Require comparable fixtures, runner/profile, cache state, cold/warm sampling,
  and explicit verified/inferred claim boundaries.

## SP3 audit baseline

The supplied skills support the SP3D pre-research conclusions rather than a new
application change:

- The visible settings split and one-template editor are already implemented,
  with meaningful helpers, bounded finite selects, protected-state handling,
  and focused save boundaries.
- The remaining 2,477-line subject-schema trait still contains nine Tab
  factories and the obsolete whole-list Card Templates editor. This is a
  verified coupling/cleanup finding, while its runtime impact is not yet
  measured.
- SP3C component/server ceilings are not substitutes for authenticated browser
  DOM, teleported modal, listener, heap, TTFB, or navigation evidence.
- Several recorded SP3C maxima remain report-only rather than literal test
  assertions.
- The focused pages still normalize and validate a whole settings snapshot, and
  successful saves still serialize/upsert the full settings group. This is a
  verified SP4 read/write boundary, not a safe SP3D application change.
- The unpaginated legacy Card Template library has a real cardinality risk:
  its synthetic 100-row canary does not exercise the production projector,
  settings reads, per-template validation, or reference scanner. ARCH1 replaces
  it with a normal paginated Resource; SP3D measures the final Resource and may
  not silently choose extra pagination/windowing if the approved fixture fails.
- Livewire 4 islands or lazy children should not be added merely to satisfy the
  old SP3 wording. First prove an independently updateable measured region and
  count the aggregate requests/bytes.
- No additional material visible form-UX defect is established from static
  inspection. The remaining UX uncertainty is the already-recorded real-browser
  modal, Back-warning, responsive/RTL, and navigation acceptance run.

## Validation outcome

- This Codex task discovered and activated both project skills through the
  `.agents/skills/` links, then applied their workflows to the SP3 files.
- The canonical project and global Codex skill trees compare byte-for-byte, and
  the Agents, Claude, and Junie links all resolve to the canonical `.ai/skills/`
  packages.
- The skill-creator `quick_validate.py` entry point could not start because both
  available Python runtimes lack its unbundled `PyYAML` dependency. No package
  was installed because this task does not authorize dependency changes. The
  same frontmatter key, type, name-format, name-length, description, and YAML
  checks were run with macOS Ruby's bundled YAML parser instead.
- The installed examples were scanned for the stale Filament APIs corrected in
  this review. The only `hiddenOnCreate` occurrence is explanatory text that
  explicitly rejects that nonexistent method.
