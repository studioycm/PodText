# Dashboard Phase 4 — Evidence: Consistency Tests + the RTL Browser Group

> **STATUS: PLANNED 2026-08-04 — awaiting sequencing.** Authored immediately
> after phase 3 shipped (`dabb70d` deployed, release verified), against the
> FINAL phase 1–3 state. Test-only work: per the merge/push scope rule these
> commits stay LOCAL and never push alone — they ride the next feature push
> or the operator's word.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close Prompt 13 with its Evidence phase — the decision-10 cross-widget
consistency tests, stock/flow-under-range-switching and legend-as-filter
behavior tests, and the on-demand RTL browser group (decision 6 + round-2
decision 9) covering all three boards.

**Architecture:** One new feature suite (`DashboardConsistencyTest`) reconciles
numbers ACROSS independent `EditorialMetrics` query paths on one shared
fixture, plus widget-surface checks at the view-data level; one new browser
suite (`DashboardRtlBoardBrowserTest`) in its own excluded pest group walks the
three lenses in a real RTL browser. No app code changes are expected — any
app-code discrepancy a test exposes is a finding to fix in its own commit,
reported per the ledger protocol, never absorbed silently.

**Tech stack:** Pest 4 (feature + browser), Livewire 4 testing, existing
`EditorialMetrics` surface. No new dependencies, no schema changes, no lang
changes expected.

## Provenance (2026-08-04)

- Scope source: `dashboard-metrics-combined-ux-plan.md` — the phases table row
  "4 · Evidence" ("tests for cross-widget number consistency, stock/flow
  behavior under range switching, legend-as-filter, plus the RTL browser
  test"), round-1 locked decision 6 (RTL evidence via a browser test), round-2
  locked decisions 9 (RTL test in its own group, run on demand, so the main
  gate stays deterministic) and 10 (the four consistency pairs, quoted in
  full below).
- Binding why-layer: `dashboard-widget-principles.md` — this phase MAKES REAL
  three guards the principles already name: `two-truths-two-cells` ("phase-4
  consistency tests, decision 10"), `bounded-or-rolled-up` ("phase-4
  reconciliation tests"), `rtl-home-field` ("the phase-4 RTL browser group,
  on demand").
- Contention status: `docs/research/browser-timeout-contention-investigation.md`
  verdict — the three registered browser timeouts were ONE defect (concurrent
  `Storage::fake` sessions cleaning a shared fake-disk root), CURED always-on
  by per-process `TEST_TOKEN` roots in `tests/Pest.php`. The RTL group
  therefore needs run discipline (run the group alone, no concurrent
  pest/build), not a sterile machine.
- Browser-test disciplines (binding, from the 2R handoff + ledger): labelled
  waits on every step; `x-show` visibility waited as a condition, never
  single-read (`single-read-race`); Alpine boot probes target the `x-data`
  element; per-run-unique fixture names for anything written to disk
  (`test-residue`); no re-run-and-move-on (`flake-label`).

## Global constraints

Inherited from the 2R handoff contracts and the phase-3 route; every task
inherits them.

- Every number a test reconciles comes from `EditorialMetrics` or a rendered
  widget surface — tests never re-derive metrics with their own raw SQL.
- Day bucketing expectations follow Jerusalem walls; fixtures freeze the clock
  with `Carbon::setTestNow(Carbon::parse('…', 'Asia/Jerusalem'))` and store
  UTC wall times (the repo's cast semantics — casts do not tz-convert on
  write; the overview suite's 10:00→13:00 pin is the precedent).
- Mutation-check every assertion that could pass vacuously: strip the
  implementation detail and watch the test fail. Two ledger lessons apply
  directly — never read the expectation from the home under test
  (expectation-from-home), and never `assertSee` a value that other page
  content can satisfy (vacuous-assertSee; prefer `assertViewHas` /
  `assertTableColumnStateSet` / targeted testids).
- The RTL group must NOT run in the default gate: `phpunit.xml` excludes it
  exactly like `compiled-sentinels`; it runs on demand with `--group=rtl-board`.
- Per-task gate: targeted `php -d memory_limit=2G vendor/bin/pest --compact <files>`;
  `vendor/bin/pint <files> --format agent` (pathspec form, never `--dirty` in
  the shared tree); `vendor/bin/filacheck --dirty` only if `app/Filament`
  files are touched AND `git status` shows this session as their sole owner.
  Full suite before the final commit.
- In the shared tree, commit with an explicit pathspec (`git commit <paths>
  -m …`), never a bare `git commit` (`shared-index-entanglement`).
- NEVER `git push` — auto-deploy is ON; pushing deploys production. These are
  test/docs commits: they stay local and never push alone.
- Findings register at discovery in the final report's "open flags + pattern
  evidence" section for the orchestrator-owned ledger; the ledger is never
  edited directly.

## Evidence labels (per `planned-fixture-drift` — orchestrator directive 2026-08-04)

Every fixture-level claim below is labelled so the implementer inherits
hypotheses as hypotheses, not assumptions dressed as facts. The promised
tests serve named guards: `two-truths-two-cells` (pairs 1/3/4),
`bounded-or-rolled-up` (pair 2, D-3), `rtl-home-field` (Task 4).

**VERIFIED against HEAD in the authoring session:**
`EditorialStatsWidget` cards shape (`key`/`value`, one card keyed
`visible`); `Heatmap::total()`; `podcastHealth()` rows as `BreakdownRow`
with `value` = visible / `of` = published; `blockersProgress()` returning
`Burndown` with `public int $remaining` / `public int $total`;
`SeriesRow::$points` as `array<int, float>` (hence the `(int)` cast);
`funnelSeries()` keys `draft|published|transcribed`; the he
`burndown_invisible` string and the `queue-burndown`/`queue-burndown-{key}`
testids; `uses()->group('…')` as the exact in-repo group idiom
(`CompiledThemeSentinelTest.php:37`); the phpunit.xml `<groups><exclude>`
block; UTC-wall cast semantics (pinned empirically by the phase-3 Task-5
test); all factory idioms used by `consistencyFixture()` (they are the
phase-3 suites' own, run green this week); every `data-testid` the browser
suite targets (created/asserted by the phase-3 suites).

**UNVERIFIED HYPOTHESES (implementer verifies at red/green, adjusts the
FIXTURE or the labelled fallback — never the assertion direction):**
1. Queue population = both tiers as distinct episodes (expected count 3) —
   inferred from the phase-1 handoff correction, `queueQuery()`'s body was
   NOT read; if the queue holds a different population, reconcile the pin
   against the actual query and report the discrepancy.
2. `->repeat(3)` as the soak spelling (fallback already stated in Task 4).
3. `$page->script()` returning an associative array to PHP (idiom inferred
   from the B1 suite; adapt to scalar probes if marshalling differs).
4. Lens-pill click-by-label (fallback already stated: labelled-waitFor
   script click).
5. `publicationHeatmap()` counting exactly the published_at events the
   series counts — the pair-2 test IS the check; if it reds, that is the
   decision-10 finding, not a fixture bug.

## Decision 10, verbatim (the four pairs)

> **Phase 4's consistency tests cover** all four pairs: visible across the
> funnel, the stat card and the legend chip (rule 2's namesake case); heatmap
> total vs the funnel's published series; blocked across the gap widget, the
> queue and the burn-down; and per-podcast health totals against the scoped
> funnel.

## Design decisions taken by this plan (with rationale)

- **D-1 · Reconcile across INDEPENDENT query paths, and check widget surfaces
  at the view-data level.** `snapshot()`, `funnelSeries()`,
  `publicationHeatmap()`, `queueQuery()`, `blockersProgress()` and
  `podcastHealth()` are separately implemented queries — agreeing on one
  fixture is real evidence. The three widgets of pair 1 all read
  `snapshot()`, so rendering them and comparing HTML substrings would be
  either tautological or vacuous; instead each widget's `assertViewHas`
  proves the SURFACE hands its blade the right key (a widget reading the
  wrong snapshot key or applying its own arithmetic fails). One additional
  independent oracle anchors pair 1: `ContentItem::query()->published()->count()`
  — the public visibility contract itself.
- **D-2 · Honest relations, not forced equalities.** Where reason sets can
  overlap, the reconciliation is stated as the true relation: attention
  `total` ≤ `missing_media + missing_category` (one episode can carry both);
  gap reasons are asserted per-row on a fixture built WITHOUT overlap plus
  the exact `invisible` total. The media-findings extension asserts
  `flagged` ≤ sum(per-reason counts) and `flagged` ≤ `total` for the same
  reason. A consistency test that forces `==` on an overlapping vocabulary
  would be wrong the day a two-reason episode exists.
- **D-3 · Intake reconciliation is an EXTENSION beyond decision 10's four
  pairs** (which predate Board 3's build-out) and is labelled as such: queue
  chip counts vs `intakeSnapshot()`; media findings relations (D-2). It
  follows the same principles (`bounded-or-rolled-up`: totals reconcile).
- **D-4 · The fixture is arithmetic-first.** One `consistencyFixture()` builds
  every state the pairs need with hand-computable expected numbers (published
  4, visible 2, invisible 2 = missing_transcription 1 + unpublished_group 1,
  attention 1 with both reasons, draft 1, queue 3). If red/green reveals the
  fixture arithmetic drifted from a tier definition, verify against the tier
  contracts and adjust the FIXTURE — assertion direction stands
  (planned-fixture-drift is a registered ledger pattern; three sightings in
  phase 3).
- **D-5 · The RTL browser suite proves structure and direction, not pixel
  layout.** Per lens: the board renders its registered widgets' landmarks, no
  JS errors, the container computes `direction: rtl`, and the deliberate LTR
  islands (time elements, bar tracks) carry `dir="ltr"`. Interaction depth
  stays minimal (lens pills + one Intake source selection) — B1's sparkline
  suite already drives deep interaction on this board and stays untouched.
- **D-6 · Soak ×3 on the browser walk** (B1 used ×5 for a hover race; a
  navigation walk is less timing-sensitive). Any intermittent failure is
  investigated, never re-run past (flake-label).

---

## Task 1: `DashboardConsistencyTest` — pairs 1 + 2 (visible triple; heatmap vs published series)

**Files:**
- Create: `tests/Feature/DashboardConsistencyTest.php`

**Interfaces:**
- Consumes: `EditorialMetrics::snapshot()['funnel']`,
  `funnelSeries(DashboardRange, ?int): array<string, SeriesRow>` (keys
  `draft|published|transcribed`), `publicationHeatmap(DashboardRange, ?int): Heatmap`
  (`->total()`), `SeriesRow->points` (array of ints),
  `ContentItem::scopePublished()` (the public contract, via
  `ContentItem::query()->published()`), the three Overview widgets'
  view data (`DashboardContextWidget` → `funnel`;
  `PublicationFunnelWidget`; `EditorialStatsWidget`).
- Produces: `consistencyFixture(): array{alpha: ContentGroup, draftGroup: ContentGroup}`
  — Tasks 2–3 reuse it verbatim from this file (same-file helpers; NOT moved
  to `tests/Pest.php` — only this suite uses them).

- [ ] **Step 1: Write the file with the fixture and the two failing tests**

```php
<?php

use App\Enums\DashboardRange;
use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Filament\Widgets\DashboardContextWidget;
use App\Filament\Widgets\EditorialStatsWidget;
use App\Filament\Widgets\PublicationFunnelWidget;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    Carbon::setTestNow(Carbon::parse('2026-07-31 10:00:00', 'Asia/Jerusalem'));
    $this->actingAs(User::factory()->admin()->create());
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Every tier the decision-10 pairs reconcile, with hand-computable numbers.
 * All wall times are inside the last-30-days range and stored as UTC walls
 * (the repo's cast semantics).
 *
 * Expected arithmetic (verify against the tier contracts before touching):
 *   published = 4 (visible, noTranscript, orphan, needy)
 *   visible   = 2 (visible, needy — needy is public but incomplete)
 *   invisible = 2 (noTranscript → missing_transcription;
 *                  orphan → unpublished_group; NO overlap by construction)
 *   attention = 1 (needy — missing_media AND missing_category)
 *   draft     = 1
 *   queue     = 3 distinct episodes (noTranscript, orphan, needy)
 *
 * @return array{alpha: ContentGroup, draftGroup: ContentGroup}
 */
function consistencyFixture(): array
{
    $alpha = ContentGroup::factory()->published()->create(['title' => 'Alpha Podcast']);
    $draftGroup = ContentGroup::factory()->create(['title' => 'Shelved Podcast']);
    $category = Category::factory()->create();

    $visible = ContentItem::factory()->for($alpha)
        ->published(Carbon::parse('2026-07-29 09:00'))
        ->create(['title' => 'Fully Visible', 'embed_url' => 'https://open.spotify.com/episode/c1']);
    $visible->categories()->attach($category);
    Transcription::factory()->for($visible)
        ->published(Carbon::parse('2026-07-29 10:00'))
        ->create();

    $noTranscript = ContentItem::factory()->for($alpha)
        ->published(Carbon::parse('2026-07-28 09:00'))
        ->create(['title' => 'Awaiting Transcript', 'embed_url' => 'https://open.spotify.com/episode/c2']);
    $noTranscript->categories()->attach($category);

    $orphan = ContentItem::factory()->for($draftGroup)
        ->published(Carbon::parse('2026-07-27 09:00'))
        ->create(['title' => 'Orphaned Episode', 'embed_url' => 'https://open.spotify.com/episode/c3']);
    $orphan->categories()->attach($category);
    Transcription::factory()->for($orphan)
        ->published(Carbon::parse('2026-07-27 10:00'))
        ->create();

    $needy = ContentItem::factory()->for($alpha)
        ->published(Carbon::parse('2026-07-26 09:00'))
        ->create(['title' => 'Needy Episode', 'embed_url' => null, 'media_url' => '']);
    Transcription::factory()->for($needy)
        ->published(Carbon::parse('2026-07-26 10:00'))
        ->create();

    ContentItem::factory()->for($alpha)->create(['status' => PublicationStatus::Draft]);

    return ['alpha' => $alpha, 'draftGroup' => $draftGroup];
}

it('shows one visible number across the funnel, the stat card and the legend chip', function (): void {
    consistencyFixture();

    $funnel = app(EditorialMetrics::class)->snapshot()['funnel'];

    // The independent oracle: the public visibility contract itself.
    expect($funnel['visible'])->toBe(2)
        ->and($funnel['visible'])->toBe(ContentItem::query()->published()->count())
        ->and($funnel['published'])->toBe(4)
        ->and($funnel['draft'])->toBe(1);

    // Surface checks at the view-data level (assertSee on a bare digit is
    // satisfiable by unrelated page content — vacuous-assertSee).
    Livewire::test(DashboardContextWidget::class)
        ->assertViewHas('funnel', fn (array $viewFunnel): bool => $viewFunnel['visible'] === 2);

    Livewire::test(PublicationFunnelWidget::class)
        ->assertViewHas('funnel', fn (array $viewFunnel): bool => $viewFunnel['visible'] === 2);

    // Verified shape: getViewData()['cards'] rows carry 'key' and 'value',
    // and one card's key is exactly 'visible'.
    Livewire::test(EditorialStatsWidget::class)
        ->assertViewHas('cards', fn (array $cards): bool => collect($cards)
            ->contains(fn (array $card): bool => ($card['key'] ?? null) === 'visible'
                && ($card['value'] ?? null) === 2));
});

it('reconciles the heatmap total with the funnel published series', function (): void {
    consistencyFixture();

    $metrics = app(EditorialMetrics::class);
    $range = DashboardRange::Last30Days;

    $publishedSeries = $metrics->funnelSeries($range)['published'];
    $heatmap = $metrics->publicationHeatmap($range);

    // Two independently implemented aggregations of the same events.
    // SeriesRow::$points is array<int, float> (verified) — cast the sum or
    // the strict toBe() fails on 4.0 vs 4.
    expect($heatmap->total())->toBe((int) array_sum($publishedSeries->points))
        ->and($heatmap->total())->toBe(4);
});
```

The stat-card closure targets the verified `getViewData()` shape (a `cards`
array whose rows carry `key`/`value`, one keyed exactly `visible`) — never a
bare `assertSee('2')`.

- [ ] **Step 2: Run, watch red**

Run: `php -d memory_limit=2G vendor/bin/pest --compact tests/Feature/DashboardConsistencyTest.php`
Expected: PASS is possible — this suite pins EXISTING behavior. Watch-red
comes from the mutation checks in Step 3, which prove each assertion can
fail. If any assertion is RED here, that is a real cross-widget
inconsistency: stop, report it as a finding, and fix it in its own commit
before proceeding.

- [ ] **Step 3: Mutation-check each pin**

Three mutations, each reverted immediately after the run:
1. In `EditorialMetrics::snapshot()`, change the funnel's `'visible'` count
   to `$this->visible($contentGroupId)->count() + 1` → both tests' snapshot
   pins fail.
2. In `DashboardContextWidget::getViewData()`, hand `'funnel' =>
   [...$metrics['funnel'], 'visible' => 0]` → the context surface check
   fails while the service pin still passes (proves the surface check has
   its own teeth).
3. In `publicationHeatmap()`, add `+ 1` to any day's entry → the
   heatmap-vs-series test fails.

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint tests/Feature/DashboardConsistencyTest.php --format agent
git add tests/Feature/DashboardConsistencyTest.php
git commit tests/Feature/DashboardConsistencyTest.php -m "test(dashboard): decision-10 pairs 1-2 — visible triple and heatmap-vs-series"
```

---

## Task 2: Pairs 3 + 4 (blocked triple; podcast health vs scoped funnel)

**Files:**
- Modify: `tests/Feature/DashboardConsistencyTest.php` (append)

**Interfaces:**
- Consumes: `consistencyFixture()` (Task 1),
  `EditorialMetrics::queueQuery(?int): Builder`,
  `blockersProgress(?int): array{invisible: Burndown, attention: Burndown}`
  (`Burndown->remaining`, `->total`), `reasonBreakdown(?int):
  array{gap: array<int, BreakdownRow>, attention: array<int, BreakdownRow>}`
  (`BreakdownRow->value`, `->meta('reason')`), `podcastHealth(?int, int):
  array` of `BreakdownRow` (`value` = visible, `of` = published),
  `snapshot(?int)` (podcast-scoped).

- [ ] **Step 1: Append the two failing tests**

```php
it('reconciles blocked across the gap rows, the queue and the burn-down', function (): void {
    consistencyFixture();

    $metrics = app(EditorialMetrics::class);
    $snapshot = $metrics->snapshot();

    // The gap rows carry exactly the invisible tier, reason by reason —
    // the fixture is built overlap-free, so the per-row pins are exact.
    $gapRows = collect($metrics->reasonBreakdown()['gap'])
        ->mapWithKeys(fn ($row): array => [$row->meta('reason') => (int) $row->value]);

    expect($snapshot['gap']['invisible'])->toBe(2)
        ->and($gapRows->get('missing_transcription'))->toBe(1)
        ->and($gapRows->get('unpublished_group'))->toBe(1);

    // Attention reasons OVERLAP by design (one episode, two findings):
    // total counts episodes, the rows count findings — total ≤ sum (D-2).
    expect($snapshot['attention']['total'])->toBe(1)
        ->and($snapshot['attention']['missing_media'])->toBe(1)
        ->and($snapshot['attention']['missing_category'])->toBe(1);

    // The queue holds BOTH tiers as distinct episodes (phase-1 handoff
    // correction: "status-published minus visible" is the Invisible TIER,
    // not the queue population).
    expect($metrics->queueQuery()->count())->toBe(3);

    // The burn-down bars read the same tier numbers.
    $progress = $metrics->blockersProgress();
    expect($progress['invisible']->remaining)->toBe($snapshot['gap']['invisible'])
        ->and($progress['attention']->remaining)->toBe($snapshot['attention']['total'])
        ->and($progress['invisible']->total)->toBe($snapshot['funnel']['published']);
});

it('reconciles per-podcast health totals against the scoped funnel', function (): void {
    $fixture = consistencyFixture();

    $metrics = app(EditorialMetrics::class);
    $rows = collect($metrics->podcastHealth())
        ->keyBy(fn ($row): string => $row->label);

    foreach ([
        'Alpha Podcast' => $fixture['alpha'],
        'Shelved Podcast' => $fixture['draftGroup'],
    ] as $label => $group) {
        $scoped = $metrics->snapshot($group->getKey())['funnel'];

        expect((int) $rows->get($label)->value)->toBe($scoped['visible'])
            ->and((int) $rows->get($label)->of)->toBe($scoped['published']);
    }

    // Anchor the absolute numbers once so the loop cannot pass on 0 == 0.
    expect((int) $rows->get('Alpha Podcast')->of)->toBe(3)
        ->and((int) $rows->get('Alpha Podcast')->value)->toBe(2)
        ->and((int) $rows->get('Shelved Podcast')->of)->toBe(1)
        ->and((int) $rows->get('Shelved Podcast')->value)->toBe(0);
});
```

- [ ] **Step 2: Run — green expected, or a finding**

Run: `php -d memory_limit=2G vendor/bin/pest --compact tests/Feature/DashboardConsistencyTest.php`
Expected: PASS (pins existing behavior). Any red = real inconsistency →
stop, report, fix in its own commit.

- [ ] **Step 3: Mutation-check**

1. In `queueQuery()`, add `->where('id', '>', 0)` → no effect (sanity that
   the harness runs), then `->limit(2)` → the queue pin fails.
2. In `podcastHealth()`, swap `value:` to `(float) $total` → the health test
   fails on the value/of split (the two-truths-two-cells guard in action).
3. In `blockersProgress()`, pass `total: $invisible` for the invisible
   burndown → the total pin fails.

Revert each after its run.

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint tests/Feature/DashboardConsistencyTest.php --format agent
git commit tests/Feature/DashboardConsistencyTest.php -m "test(dashboard): decision-10 pairs 3-4 — blocked triple and podcast health"
```

---

## Task 3: Intake reconciliation (D-3) + range-switch stock/flow + legend-as-filter

**Files:**
- Modify: `tests/Feature/DashboardConsistencyTest.php` (append)

**Interfaces:**
- Consumes: `intakeSnapshot()`, `intakeQueue()` (counts keys
  `all|submissions|imports`), `mediaFindings()` (`rows`, `rate->covered`,
  `rate->of`), `failedImport()` / `cleanMedia()` / `missingFileMedia()`
  (global helpers in `tests/Pest.php`), `PublicFormSubmission::factory()`,
  `ActivityStreamWidget` (`selectType`, flow), `PublicationFunnelWidget`
  (stock), `Storage::fake('public')` for the media helpers,
  `EditorialMetrics::snapshot()['funnel']`.

- [ ] **Step 1: Append the three tests**

```php
it('reconciles the intake queue chips with the intake snapshot', function (): void {
    Storage::fake('public');
    PublicFormSubmission::factory()->count(2)->create();
    failedImport(failed: 2);

    $metrics = app(EditorialMetrics::class);
    $snapshot = $metrics->intakeSnapshot()['queue'];
    $counts = $metrics->intakeQueue()['counts'];

    expect($counts)->toBe([
        'all' => $snapshot['submissions'] + $snapshot['imports'],
        'submissions' => $snapshot['submissions'],
        'imports' => $snapshot['imports'],
    ])
        ->and($snapshot)->toBe(['submissions' => 2, 'imports' => 1, 'failed_rows' => 2]);
});

it('keeps the media findings relations honest', function (): void {
    Storage::fake('public');
    cleanMedia();
    missingFileMedia();
    missingFileMedia();

    $metrics = app(EditorialMetrics::class);
    $media = $metrics->intakeSnapshot()['media'];
    $findings = $metrics->mediaFindings();

    // flagged counts DISTINCT media; per-reason counts can overlap on a
    // multi-finding file — the honest relations, not a forced equality (D-2).
    expect($media['flagged'])->toBeLessThanOrEqual(array_sum($media['findings']))
        ->and($media['flagged'])->toBeLessThanOrEqual($media['total'])
        ->and($findings['rate']->covered)->toBe($media['total'] - $media['flagged'])
        ->and($findings['rate']->of)->toBe($media['total'])
        ->and($media['total'])->toBe(3);
});

it('keeps stock totals still while a flow widget follows the range and the legend', function (): void {
    consistencyFixture();

    $metrics = app(EditorialMetrics::class);

    // Stock: the funnel's totals are range-blind by contract.
    $stockAtThirty = $metrics->snapshot()['funnel'];
    Livewire::test(PublicationFunnelWidget::class, ['pageFilters' => ['range' => DashboardRange::Last7Days->value]])
        ->assertViewHas('funnel', fn (array $funnel): bool => $funnel === $stockAtThirty);

    // Flow: the heatmap's total moves with the range (all four publications
    // sit 26–29/07, so a 7-day window ending 31/07 still holds 4 while a
    // range check with a distant frozen day would drop them — pin both
    // directions by comparing the two ranges' own sums).
    expect($metrics->publicationHeatmap(DashboardRange::Last7Days)->total())
        ->toBe(array_sum($metrics->funnelSeries(DashboardRange::Last7Days)['published']->points));

    // Legend-as-filter: a status chip narrows the STREAM (flow) and leaves
    // the funnel (stock) untouched — synthesis rule 1 across two widgets.
    Livewire::test(\App\Filament\Widgets\ActivityStreamWidget::class, ['pageFilters' => ['status' => 'visible']])
        ->assertViewHas('activeType', 'transcription');

    Livewire::test(PublicationFunnelWidget::class, ['pageFilters' => ['status' => 'visible']])
        ->assertViewHas('funnel', fn (array $funnel): bool => $funnel === $stockAtThirty);
});
```

Add `use App\Models\PublicFormSubmission;`, `use App\Filament\Widgets\ActivityStreamWidget;`
and `use Illuminate\Support\Facades\Storage;` to the file's import block.

- [ ] **Step 2: Run — green expected, or a finding**

Run: `php -d memory_limit=2G vendor/bin/pest --compact tests/Feature/DashboardConsistencyTest.php`

- [ ] **Step 3: Mutation-check**

1. In `intakeQueue()`, return `'all' => $submissionsCount` (drop the
   imports term) → the chips test fails.
2. In `mediaFindings()`, swap the rate to `covered: $media['flagged']` →
   the relations test fails.
3. In `ActivityStreamWidget::getViewData()`, drop the
   `?? EditorialMetrics::streamTypeForStatus(...)` fallback → the
   legend-as-filter check fails.

Revert each after its run.

- [ ] **Step 4: Format, full consistency suite, commit**

```bash
vendor/bin/pint tests/Feature/DashboardConsistencyTest.php --format agent
git commit tests/Feature/DashboardConsistencyTest.php -m "test(dashboard): intake reconciliation, range-switch stock/flow, legend-as-filter"
```

---

## Task 4: `DashboardRtlBoardBrowserTest` — the on-demand RTL group

**Files:**
- Create: `tests/Browser/DashboardRtlBoardBrowserTest.php`
- Modify: `phpunit.xml` (add `<group>rtl-board</group>` inside the existing
  `<groups><exclude>` block, beside `compiled-sentinels`)

**Interfaces:**
- Consumes: `Dashboard::getUrl()`, the lens pill labels
  (`admin.dashboard.lenses.{overview,blockers,intake}` — the board renders
  in `he`), per-board landmarks already pinned by feature tests
  (`data-testid`: `dashboard-scope-echo`, `funnel-segment-visible`,
  `heatmap-day-*`, `stream-row`, `funnel-gap`/gap bars, `intake-row`,
  `connection-row`, `media-finding-row`, `intake-cap-note`), the
  `consistencyFixture()` shape re-declared locally (browser suites do not
  share the feature file's helpers) plus `failedImport()` / `cleanMedia()` /
  `missingFileMedia()` from `tests/Pest.php`.
- Produces: the on-demand group `rtl-board`; run:
  `php -d memory_limit=2G vendor/bin/pest --compact --group=rtl-board`.

- [ ] **Step 1: Add the group exclusion**

In `phpunit.xml`:

```xml
    <groups>
        <exclude>
            <group>compiled-sentinels</group>
            <group>rtl-board</group>
        </exclude>
    </groups>
```

- [ ] **Step 2: Write the browser suite**

```php
<?php

use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Filament\Pages\Dashboard;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\PublicFormSubmission;
use App\Models\Transcription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class)->group('rtl-board');

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    app()->setLocale('he');
    Carbon::setTestNow(Carbon::parse('2026-07-31 10:00:00', 'Asia/Jerusalem'));
    $this->actingAs(User::factory()->admin()->create());
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** Every board populated; per-run-unique names for disk writes (test-residue). */
function rtlBoardFixture(): void
{
    $token = 'rtl'.getmypid();

    $group = ContentGroup::factory()->published()->create(['title' => "פודקאסט {$token}"]);
    $category = Category::factory()->create();

    $visible = ContentItem::factory()->for($group)
        ->published(Carbon::parse('2026-07-29 09:00'))
        ->create(['title' => "פרק גלוי {$token}", 'embed_url' => 'https://open.spotify.com/episode/r1']);
    $visible->categories()->attach($category);
    Transcription::factory()->for($visible)
        ->published(Carbon::parse('2026-07-29 10:00'))
        ->create();

    $blocked = ContentItem::factory()->for($group)
        ->published(Carbon::parse('2026-07-28 09:00'))
        ->create(['title' => "פרק חסום {$token}", 'embed_url' => 'https://open.spotify.com/episode/r2']);
    $blocked->categories()->attach($category);

    ContentItem::factory()->for($group)->create(['status' => PublicationStatus::Draft]);

    PublicFormSubmission::factory()->create(['form_name_snapshot' => "פנייה {$token}"]);
    failedImport(fileName: "{$token}.csv");
    cleanMedia();
    missingFileMedia();
}

it('walks the three lenses on the RTL board with the LTR islands intact', function (): void {
    Storage::fake('public');
    rtlBoardFixture();

    $page = visit(Dashboard::getUrl())->resize(1280, 900);

    // Board 1 — Overview.
    $page->assertNoJavaScriptErrors()
        ->assertSee(__('admin.dashboard.lenses.overview'))
        ->assertPresent('[data-testid="dashboard-scope-echo"]')
        ->assertPresent('[data-testid="funnel-segment-visible"]')
        ->assertPresent('[data-testid="stream-row"]');

    // The board is RTL-native; the stream timestamps are LTR islands.
    expect($page->script(<<<'JS'
        (() => {
            const echo = document.querySelector('[data-testid="dashboard-scope-echo"]');
            const streamTime = document.querySelector('[data-testid="stream-row"] time');
            return {
                echoDirection: getComputedStyle(echo).direction,
                timeDir: streamTime ? streamTime.getAttribute('dir') : null,
            };
        })()
    JS))->toBe(['echoDirection' => 'rtl', 'timeDir' => 'ltr']);

    // Board 2 — Blockers (lens pills are real buttons; click by label).
    $page->click(__('admin.dashboard.lenses.blockers'))
        ->assertNoJavaScriptErrors()
        ->assertSee(__('admin.dashboard.queue.burndown_invisible', [
            'remaining' => 1,
            'total' => 2,
        ]));

    // Board 3 — Intake: queue rows, connection empty state, findings bars.
    $page->click(__('admin.dashboard.lenses.intake'))
        ->assertNoJavaScriptErrors()
        ->assertPresent('[data-testid="intake-row"]')
        ->assertSee(__('admin.dashboard.connection.none_heading'))
        ->assertPresent('[data-testid="media-finding-row"]');

    // The findings bar track is a deliberate LTR island inside the RTL card,
    // and the intake timestamps carry dir="ltr" exactly like the stream's.
    expect($page->script(<<<'JS'
        (() => {
            const bar = document.querySelector('[data-testid="media-finding-row"] [dir="ltr"]');
            const intakeTime = document.querySelector('[data-testid="intake-row"] time');
            return {
                barIsland: bar !== null,
                intakeTimeDir: intakeTime ? intakeTime.getAttribute('dir') : null,
            };
        })()
    JS))->toBe(['barIsland' => true, 'intakeTimeDir' => 'ltr']);
})->repeat(3);
```

Adaptation notes for the implementer (verify, per the disciplines):
- `->repeat(3)` is D-6's soak; if the installed Pest version spells it
  differently, run the file three times explicitly and record it.
- The burndown copy assertion uses the VERIFIED he string
  (`':remaining מתוך :total פרקים שפורסמו אינם גלויים'`) with the fixture's
  exact numbers (invisible 1 of published 2). If the render composes it
  differently in the browser, fall back to the verified partial testids
  (`queue-burndown`, `queue-burndown-{key}`). Never assert a bare number
  (vacuous-assertSee).
- If a click-by-label misses because the pill renders as a ToggleButtons
  input, fall back to clicking the labelled element via `->script()` with a
  labelled waitFor (the `DashboardSparklineBrowserTest` helper is the house
  pattern) — and wait the lens's landmark as a CONDITION after each switch,
  never single-read (`single-read-race`).

- [ ] **Step 3: Run the group on demand — and confirm the main gate excludes it**

```bash
php -d memory_limit=2G vendor/bin/pest --compact --group=rtl-board
```

Expected: PASS ×3 (repeat). Then confirm exclusion:

```bash
php -d memory_limit=2G vendor/bin/pest --compact tests/Browser/DashboardRtlBoardBrowserTest.php
```

Expected: 0 tests executed (group excluded by phpunit.xml — the
compiled-sentinels mechanism). Run the group ALONE (no concurrent pest or
build processes), per the contention discipline.

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint tests/Browser/DashboardRtlBoardBrowserTest.php --format agent
git add tests/Browser/DashboardRtlBoardBrowserTest.php
git commit tests/Browser/DashboardRtlBoardBrowserTest.php phpunit.xml -m "test(browser): on-demand RTL board group — three-lens walk with LTR islands (decision 6/9)"
```

---

## Task 5: Full gate, state docs, final report

**Files:**
- Modify: `docs/phase-02/current-project-state.md` (Prompt-13 row: phase 4
  complete → Prompt 13 COMPLETE)
- Modify: `docs/phase-02/dashboard-metrics-phase-2R-handoff.md` (the route
  status line gains "phase 4 landed"; nothing else should be stale)

- [ ] **Step 1: Full gate**

```bash
php -d memory_limit=2G vendor/bin/pest --compact
vendor/bin/pint --test
vendor/bin/filacheck
```

Expected: all green (the RTL group stays excluded — that is decision 9
working, not a gap; record its own ×3 on-demand result alongside).
`npm run build` only if any theme-relevant surface changed (none is
expected in this phase — record "not needed: test-only" if so).

- [ ] **Step 2: Run the RTL group once more on the final tree, alone**

```bash
php -d memory_limit=2G vendor/bin/pest --compact --group=rtl-board
```

- [ ] **Step 3: Update the state docs and commit**

`current-project-state.md` Prompt-13 row: status becomes "COMPLETE (all 4
phases + 2R)"; evidence gains the phase-4 commits and both gate numbers
(full suite + rtl-board ×3). The 2R handoff's route-status paragraph gains
one line: "Phase 4 (evidence) landed <date>: decision-10 consistency suite +
the on-demand `rtl-board` browser group."

```bash
git commit docs/phase-02/current-project-state.md docs/phase-02/dashboard-metrics-phase-2R-handoff.md -m "docs(dashboard): record phase 4 (evidence) as implemented — Prompt 13 complete"
```

- [ ] **Step 4: Final report** — per-task table with hashes and gate numbers;
  classify every requirement (decision 10's four pairs, the D-3 extension,
  range-switch, legend-as-filter, RTL group) as done/partial/skipped; end
  with "open flags + pattern evidence" for the orchestrator-owned ledger.
  Commits stay LOCAL — the push is the operator's word.

---

## Cause-pattern guardrails

- **expectation-from-home / tautology:** every reconciliation crosses
  independent query implementations or pins hand-computed constants; widget
  surfaces are checked via `assertViewHas`, never by comparing a widget to
  the service it reads.
- **vacuous-assertSee:** no bare-number `assertSee`; targeted testids,
  view-data closures, and full translated strings only.
- **planned-fixture-drift:** the fixture states its arithmetic in its
  docblock; red means verify the tier contracts first, then fix the FIXTURE,
  never the assertion direction.
- **flake-label / single-read-race / test-residue:** browser wait discipline
  as in B1; per-run-unique fixture names; any intermittent failure is
  investigated, not re-run past.
- **silent-cap:** the RTL group's exclusion from the main gate is DECLARED
  (decision 9) and its on-demand run is recorded in the final report — an
  excluded group without a recorded run would be a silent gap.

## Open questions for the operator

1. **None blocking.** The only judgment call is D-3's intake extension beyond
   decision 10's literal four pairs — it follows `bounded-or-rolled-up` and
   costs one test; flag here per protocol, implemented unless overruled.
