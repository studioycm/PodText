# Dashboard Phase 3 — Board 3 · Intake Lens Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the Intake lens its own board — work queue (new submissions + failed import rows), Spotify connection card, media-by-finding bars — plus the `StreamEventType` enum and the Board-3 E4 enum contracts, on the phase-2R foundations.

**Architecture:** Three new custom `Filament\Widgets\Widget` classes fed exclusively by `EditorialMetrics` (new intake surface with its own cache key, invalidated by registering the existing `EditorialMetricsCacheObserver` on the intake models), a `source` command-bar filter narrowed in `ReadsDashboardFilters`, and an `ImportPolicy` so any admin can use the failed-rows CSV doorway. `StreamEventType` becomes the typed home of the stream/queue event vocabulary (P2/P10 closure); `ExternalImageFailureReason` and `MediaAcquisitionDisposition` get their Filament label contracts (E4, Board-3 pair).

**Tech stack:** Filament 5.7.x widgets/schemas, Livewire 4, Pest 4. No new dependencies, no schema changes.

**Planning provenance (2026-08-03).** Written from scratch per
`dashboard-metrics-phase-2R-handoff.md` § "Why phase 3 must be re-planned from
scratch", against the locked decisions in
`dashboard-metrics-combined-ux-plan.md` (rounds 1, 2, 14–18). FilamentExamples
research recorded in `docs/research/filament-examples-phase-02.md` § "Board 3 ·
Intake Lens Research". Every vendor fact cited below (signed route shape,
policy branch, `tab`/`filters` URL aliases, `getFailedRowsCount()`,
`failedRows()->createMany()` firing per-model events) was verified against the
installed vendor sources, not assumed.

## Global constraints

Copied from the 2R handoff contracts; every task inherits them.

- Every number goes through `EditorialMetrics`. No fresh query in a widget.
- Day bucketing is Jerusalem walls in PHP; never SQL `DATE()`; the UI timezone
  comes from `UiTimezone::name()`, never a literal. (Board 3 has no daily
  series, but `last_tested_at` and queue timestamps render through
  `UiTimezone` + `UiFormats`.)
- No polling: `protected ?string $pollingInterval = null` on every widget.
- `AdminOnlyWidget` + `ShowsLoadingSkeleton` (`placeholder()`) on every widget.
- Stock/flow header tag on every widget: the structural loop in
  `tests/Feature/DashboardOverviewLensTest.php` iterates every lens
  registration and fails untagged widgets. All three Board-3 widgets are
  **stock** (*current state*) — decision 7's reason the range is hidden.
- Day-first dates (`UiFormats::date()` / `UiFormats::dateTime()`); numbers via
  `UiFormats::number()`; translation keys in both `lang/en` and `lang/he`.
- Doorway query key is `filters` (the `ListRecords` alias), never
  `tableFilters`; the gallery task doorway key is `tab` (`ListRecords`
  `#[Url(as: 'tab')]`, `vendor/filament/filament/src/Resources/Pages/ListRecords.php:54`).
- Filament action-modal content is not in Livewire HTML at `mountAction` time —
  no Board-3 widget opens a modal, so no test may assert modal prose; repair
  actions stay in the gallery where they already live.
- Mutation-check every render assertion: strip the implementation detail the
  assertion targets and confirm the test fails before trusting it.
- A value is only safe from drift once every call site reads it from the enum
  (consolidations carry an anti-drift test, decision 18).
- The metrics cache is invalidated on editorial writes; 60s TTL is a backstop.
- `$pageFilters` is unvalidated user input: every new key is narrowed in
  `ReadsDashboardFilters` before use.
- Final gate: `php artisan test` (needs `-d memory_limit=2G` via the pest
  wrapper the repo uses), `vendor/bin/pint --test`, `vendor/bin/filacheck`,
  `npm run build`. After touching `app/Filament`, run
  `vendor/bin/filacheck --fix --dirty` locally (never bare `--fix` beyond it).
- NEVER `git push` — auto-deploy is ON; pushing deploys production. Commit
  locally only; the operator owns pushes.

## Locked decisions this plan implements (do not re-litigate)

1. Sources filter = the provider enum (`ImportConnectionProvider`).
2. Spotify card echoes the connection test (`status`, `last_tested_at`); no
   persisted last-fetch record exists and none is added.
3. Media findings show all six `MediaDiagnosticReason` values, zero-count rows
   hidden; the enum's own colour/icon/`barClass()` style the bars.
4. Work queue = new submissions + failed import rows, with
   all/submissions/imports chips.
5. Range control hidden on Intake (nothing there is range-scoped).
6. Bars are built on `BreakdownRow`; `UiFormats` and `StreamEventType` are in
   place from the start — `StreamEventType` (transcription / import / media /
   submission, `HasLabel`+`HasColor`+`HasIcon`) lands here, replacing
   `ActivityStreamWidget`'s hand-written colours (decision 17, pattern P2).
7. E4 scope for Board 3: `ExternalImageFailureReason` and
   `MediaAcquisitionDisposition` get Filament contracts.
8. Intake gets its own widget list in `Dashboard::getWidgetsForLens()`.
9. Board state persistence stays as built: command-bar filters persist
   (URL/session); widget-local chip selections reset on reload — the queue's
   kind chips are plain Livewire props, no `#[Url]`.

## Design decisions taken by this plan (with rationale)

These are the choices the locked decisions under-determined. D-4 and D-5 also
appear under "Open questions" because the operator may overrule them; the plan
proceeds on the recommendation so implementation is never blocked.

- **D-1 · The work queue is a custom `Widget`, not a `TableWidget`.** The queue
  unions `PublicFormSubmission` and Filament's `Import` — a Filament table
  wants one Eloquent query. The board already solved this shape once:
  `ActivityStreamWidget` renders typed rows merged in `EditorialMetrics` with
  chip filters as widget-local state. The corpus survey found no cross-model
  table precedent either (research doc, "Queue-widget shape").
- **D-2 · Failed-import queue entries are one per `Import` with failures, not
  one per `FailedImportRow`.** The mockup's per-row line ("שורה 12") cannot be
  built honestly: `failed_import_rows` stores no row number, only the payload
  and `validation_error`. The actionable unit is the import — its failure CSV
  (the same artifact the completion notification offers) carries every failed
  row with its error, and re-import is per-file. The entry shows
  `failed of total` via `Import::getFailedRowsCount()` semantics
  (`failed_rows_count` aggregate), so no row is silently dropped (P6).
- **D-3 · The failed-rows doorway is the vendor's own signed download URL**,
  built exactly as `ImportAction` builds it
  (`vendor/filament/actions/src/ImportAction.php:317`):
  `URL::signedRoute('filament.imports.failed-rows.download', ['authGuard' => Filament::getAuthGuard(), 'import' => $import], absolute: false)`.
  `DownloadImportFailureCsv` authorizes via an `Import` `view` policy when one
  exists, else owner-only — today no policy exists, so a non-owner admin would
  403. Task 3 adds `ImportPolicy::view` (admin-or-owner), preserving the
  failed-row download authorization rule while making the doorway usable.
- **D-4 · Sources-filter semantics (recommendation).** Imports carry no
  provider: the `imports` table has no provider/connection column, ImportAction
  cannot know a CSV's origin, and the Spotify fetcher's direct importer never
  writes `Import` records. Inventing attribution (e.g. by importer class) would
  be a proxy metric (P5). Recommended semantics, implemented here: `source`
  empty (all) and `manual` show the full queue (both kinds — submissions and
  CSV imports are both manual acts today); `spotify`/`google_drive` show an
  explanatory provider empty state instead of rows. The card and the findings
  widget ignore the source filter (no provider dimension). True attribution is
  future WB work (capture provider at import time).
- **D-5 · The podcast filter hides on Intake.** No Intake widget has a podcast
  dimension (submissions, imports, connections and library-wide media findings
  are all podcast-free). Gap-filler G1: a filter that doesn't apply to the
  active lens disappears rather than being disabled. The Board-3 mockup's
  command bar shows lens pills + sources only. Decision 7 locked only the
  range, so this is flagged as an open question with "hide" implemented.
- **D-6 · The Spotify card lists every Spotify connection** (name, status
  badge, `last_tested_at`), usually one. Picking "the" connection would invent
  a selection rule; the fetcher itself offers all Connected ones
  (`SpotifyLinksFetcher::connectionOptions()`). `reduced = no Connected
  Spotify connection exists` mirrors the fetcher's own
  `reduced_without_connection` warning — the "reduced-mode echo" derived from
  persisted state only.
- **D-7 · Intake numbers live in a separate cached snapshot**
  (`intakeSnapshot()`, own cache key, global — no podcast dimension), and the
  existing observer class is registered on the intake models
  (`PublicFormSubmission`, `Media`, `ImportConnection`, vendor `Import` +
  `FailedImportRow`). `forget()` clears both key families. Rationale: the V1
  audit precedent (`PublicFormTargetStatus`) shows numbers whose write paths
  escape the observer must not enter the cached snapshot — so phase 3 extends
  the observer to cover its sources instead. `failedRows()->createMany()`
  fires per-model `created` events (`ImportCsv.php:126` + Eloquent
  `createMany`), so failure writes invalidate; `ImportCsv`'s counter updates
  use query-builder `update()` (no events), which only the TTL catches — the
  counters feeding the queue (`failed_rows_count`) are read live per render,
  so this staleness window touches chip counts at most.
- **D-8 · Media findings counts come from `MediaInventoryDiagnostics` inside
  the cached snapshot.** The diagnostics scan walks the inventory with file
  existence checks (`diagnosticIds()`, `lazyById(250)`), memoized per request;
  caching the six per-reason counts + flagged + total for 60s/invalidated
  keeps the board off that scan on most renders. The gallery doorway carries
  the reason genuinely (`tab` + `filters[reason][value]` — `MediaTable` has a
  real `reason` `SelectFilter`), unlike `reasonBreakdown()`'s
  comment-vs-URL mismatch on Board 2 (flagged to the orchestrator; not
  copied).
- **D-9 · Chip vocabulary reuse.** The queue's submissions/imports chips are
  `StreamEventType::Submission` / `StreamEventType::Import` labels — one enum
  home for the same concepts the stream chips already show (P10 closure).
- **D-10 · `options(X::options())`, never `options(Enum::class)`, in the
  command bar.** The filters array is URL-bound and session-persisted;
  `options(Enum::class)` installs an `EnumStateCast` and the state type would
  no longer be the string the URL carries (the E5/P9 lesson). The existing
  lens/range fields already use the `::options()` array idiom.

---

## Task 1: `StreamEventType` — the typed stream/queue vocabulary

**Files:**
- Create: `app/Enums/StreamEventType.php`
- Modify: `app/Filament/Widgets/ActivityStreamWidget.php`
- Modify: `resources/views/filament/widgets/activity-stream.blade.php`
- Modify: `app/Support/Dashboard/EditorialMetrics.php` (only
  `streamTypeForStatus()`)
- Test: `tests/Feature/DashboardOverviewLensTest.php` (additions)

**Interfaces:**
- Consumes: existing lang keys `admin.dashboard.stream.types.{transcription,import,media,submission}` (en+he — already present, no lang change).
- Produces: `App\Enums\StreamEventType` with cases `Transcription|Import|Media|Submission` (values `transcription|import|media|submission`), methods `values(): array`, `getLabel(): string`, `getColor(): string`, `getIcon(): Heroicon`, `chipClass(): string`. Tasks 2/4 rely on `StreamEventType::Submission`, `StreamEventType::Import`, `tryFrom()`, `chipClass()`.

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/DashboardOverviewLensTest.php`):

```php
it('renders the stream chips from StreamEventType', function (): void {
    overviewFixture();

    $component = Livewire::test(ActivityStreamWidget::class);

    foreach (\App\Enums\StreamEventType::cases() as $type) {
        $component->assertSeeHtml($type->chipClass());
    }
});

it('narrows selectType to StreamEventType values', function (): void {
    Livewire::test(ActivityStreamWidget::class)
        ->call('selectType', 'submission')
        ->assertSet('type', 'submission')
        ->call('selectType', 'not-a-type')
        ->assertSet('type', null);
});

it('keeps stream chip colours in StreamEventType only', function (): void {
    // Decision 18's anti-drift guard, statement-scanned (P8) and scoped to
    // the stream surface only — FunnelStage legitimately owns identical
    // literals for its own chips, so this must never become app-wide.
    $sources = [
        file_get_contents(app_path('Filament/Widgets/ActivityStreamWidget.php')),
        file_get_contents(resource_path('views/filament/widgets/activity-stream.blade.php')),
    ];

    foreach ($sources as $source) {
        expect($source)
            ->not->toContain('bg-primary-50')
            ->not->toContain('bg-info-50')
            ->not->toContain('bg-warning-50')
            ->not->toContain('bg-success-50');
    }
});
```

- [ ] **Step 2: Run, watch red**

Run: `php artisan test --compact --filter=DashboardOverviewLensTest`
Expected: FAIL — `Class "App\Enums\StreamEventType" not found` on the first
new test; the guard test fails on the literal classes still in the widget.

- [ ] **Step 3: Create the enum**

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The four editorial event kinds the board streams and queues.
 *
 * Decision 17 made durable: before this enum the stream's type list and chip
 * colours were hand-written in `ActivityStreamWidget` next to translation
 * keys derived by string interpolation — the exact drift shape (P2) the
 * funnel fixed with `FunnelStage`. The values are the `activityStream()`
 * vocabulary and must not change: they ride Livewire state and the
 * legend-to-stream mapping.
 */
enum StreamEventType: string implements HasColor, HasIcon, HasLabel
{
    case Transcription = 'transcription';
    case Import = 'import';
    case Media = 'media';
    case Submission = 'submission';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return __("admin.dashboard.stream.types.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Transcription => 'primary',
            self::Import => 'info',
            self::Media => 'warning',
            self::Submission => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Transcription => Heroicon::OutlinedDocumentText,
            self::Import => Heroicon::OutlinedArrowDownTray,
            self::Media => Heroicon::OutlinedPhoto,
            self::Submission => Heroicon::OutlinedInboxArrowDown,
        };
    }

    /** Tailwind classes for this event kind's chip — the shipped palette. */
    public function chipClass(): string
    {
        return match ($this) {
            self::Transcription => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
            self::Import => 'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-300',
            self::Media => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
            self::Submission => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
        };
    }
}
```

- [ ] **Step 4: Route the widget through it**

In `app/Filament/Widgets/ActivityStreamWidget.php`, replace `selectType()` and
`getViewData()`'s `types`/`badges` entries:

```php
use App\Enums\StreamEventType;

    public function selectType(?string $type = null): void
    {
        // Livewire-callable with a browser-supplied argument: narrow it (P3).
        $this->type = StreamEventType::tryFrom((string) $type)?->value;
    }
```

```php
        return [
            'events' => app(EditorialMetrics::class)->activityStream(
                range: $this->dashboardRange(),
                type: $type,
                day: $this->day,
                contentGroupId: $this->dashboardPodcastId(),
            ),
            'types' => StreamEventType::cases(),
            'activeType' => $type,
            'day' => $this->day,
        ];
```

In `resources/views/filament/widgets/activity-stream.blade.php`, the chip loop
and the row badge become:

```blade
            @foreach ($types as $type)
                <button
                    type="button"
                    wire:click="selectType('{{ $type->value }}')"
                    @class([
                        'rounded-full px-2.5 py-0.5',
                        'bg-primary-600 text-white dark:bg-primary-500' => $activeType === $type->value,
                        'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300' => $activeType !== $type->value,
                    ])
                >
                    {{ $type->getLabel() }}
                </button>
            @endforeach
```

```blade
                    @php($eventType = \App\Enums\StreamEventType::from($event['type']))
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" data-testid="stream-row">
                        <span class="{{ $eventType->chipClass() }} rounded-full px-2 py-0.5 text-xs font-medium">
                            {{ $eventType->getLabel() }}
                        </span>
```

In `app/Support/Dashboard/EditorialMetrics.php`, `streamTypeForStatus()`
returns the enum's value instead of a literal:

```php
    public static function streamTypeForStatus(?string $status): ?string
    {
        return match ($status) {
            'transcribed', 'visible' => StreamEventType::Transcription->value,
            default => null,
        };
    }
```

(`activityStream()`'s `$type` parameter stays a string — its values are now
documented as `StreamEventType` values, narrowed at the widget edge.)

- [ ] **Step 5: Run green, mutation-check, format, commit**

Run: `php artisan test --compact --filter=DashboardOverviewLensTest` → PASS.
Mutation-check: revert one chip class in the enum to a wrong literal → the
chips test fails; restore. Run `vendor/bin/pint --dirty --format agent`, then
`vendor/bin/filacheck --fix --dirty` (expect 0).

```bash
git add app/Enums/StreamEventType.php app/Filament/Widgets/ActivityStreamWidget.php resources/views/filament/widgets/activity-stream.blade.php app/Support/Dashboard/EditorialMetrics.php tests/Feature/DashboardOverviewLensTest.php
git commit -m "feat(dashboard): StreamEventType owns the stream vocabulary (decision 17)"
```

---

## Task 2: The intake metrics surface in `EditorialMetrics` + invalidation

**Files:**
- Modify: `app/Support/Dashboard/EditorialMetrics.php`
- Modify: `app/Providers/AppServiceProvider.php` (observer registrations, next
  to the existing three at lines 122–124)
- Test: `tests/Feature/DashboardIntakeMetricsTest.php` (create)

**Interfaces:**
- Consumes: `MediaInventoryDiagnostics::applyReasonFilter(Builder $query, ?MediaDiagnosticReason $reason = null): Builder`, `MediaRecordScope::inventoryQuery(): Builder`, `PublicFormSubmission::scopeStatus()`, vendor `Import::failedRows()`, `Filament::getAuthGuard()`, `StreamEventType` (Task 1).
- Produces (Tasks 4–6 build against these exact signatures):

```php
public function intakeSnapshot(): array
// array{queue: array{submissions: int, imports: int, failed_rows: int},
//       media: array{findings: array<string, int>, flagged: int, total: int},
//       generated_at: string}

/** @return array{rows: array<int, array{type: StreamEventType, title: string, subtitle: ?string, url: string, at: \Illuminate\Support\Carbon}>, counts: array{all: int, submissions: int, imports: int}} */
public function intakeQueue(?ImportConnectionProvider $source = null, ?StreamEventType $kind = null, int $limit = 10): array

/** @return array{connections: array<int, array{name: string, status: ImportConnectionStatus, last_tested_at: ?\Illuminate\Support\Carbon}>, reduced: bool} */
public function spotifyConnectionEcho(): array

/** @return array{rows: array<int, BreakdownRow>, rate: Rate} */
public function mediaFindings(): array

public function failedRowsDownloadUrl(Import $import): string
```

- [ ] **Step 1: Write the failing tests** (`php artisan make:test --pest DashboardIntakeMetricsTest --no-interaction`, then fill):

```php
<?php

use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Enums\MediaDiagnosticReason;
use App\Enums\MediaLibraryTask;
use App\Enums\PublicFormSubmissionStatus;
use App\Enums\StreamEventType;
use App\Enums\TranscriptionMode;
use App\Models\ImportConnection;
use App\Models\Media;
use App\Models\PublicFormSubmission;
use App\Models\User;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

function failedImport(int $failed = 2, int $total = 5, string $fileName = 'episodes.csv'): Import
{
    $import = new Import;
    $import->forceFill([
        'file_name' => $fileName,
        'file_path' => "imports/{$fileName}",
        'importer' => \App\Filament\Imports\ContentItemImporter::class,
        'total_rows' => $total,
        'processed_rows' => $total,
        'successful_rows' => $total - $failed,
        'user_id' => User::factory()->admin()->create()->getKey(),
    ])->save();

    foreach (range(1, $failed) as $index) {
        $import->failedRows()->create([
            'data' => ['title' => "row {$index}"],
            'validation_error' => 'missing identifier',
        ]);
    }

    return $import;
}

/** A media row whose file genuinely exists on the faked disk — no findings. */
function cleanMedia(): Media
{
    $media = Media::factory()->create();
    Storage::disk('public')->put($media->path, 'binary');

    return $media;
}

/** A media row whose file is absent — exactly the missing_file finding. */
function missingFileMedia(): Media
{
    return Media::factory()->create();
}

it('counts the intake queue in the snapshot', function (): void {
    PublicFormSubmission::factory()->count(2)->create();
    PublicFormSubmission::factory()->create(['status' => PublicFormSubmissionStatus::Reviewed]);
    failedImport(failed: 3);

    $snapshot = app(EditorialMetrics::class)->intakeSnapshot();

    expect($snapshot['queue'])->toBe(['submissions' => 2, 'imports' => 1, 'failed_rows' => 3]);
});

it('merges queue rows newest-first with typed kinds and doorways', function (): void {
    $submission = PublicFormSubmission::factory()->create(['form_name_snapshot' => 'Join us']);
    $import = failedImport();

    $queue = app(EditorialMetrics::class)->intakeQueue();

    expect($queue['counts'])->toBe(['all' => 2, 'submissions' => 1, 'imports' => 1])
        ->and(collect($queue['rows'])->pluck('type')->all())
        ->toContain(StreamEventType::Submission, StreamEventType::Import);

    $importRow = collect($queue['rows'])->firstWhere('type', StreamEventType::Import);
    expect($importRow['title'])->toBe('episodes.csv')
        ->and($importRow['url'])->toContain('failed-rows/download')
        ->and($importRow['url'])->toContain('signature=');
});

it('narrows the queue by kind and by source', function (): void {
    PublicFormSubmission::factory()->create();
    failedImport();
    $metrics = app(EditorialMetrics::class);

    expect(collect($metrics->intakeQueue(kind: StreamEventType::Submission)['rows'])->pluck('type')->unique()->all())
        ->toBe([StreamEventType::Submission])
        // D-4: manual = the full queue; a connected-provider source has no
        // attributable rows today and must return none rather than lie.
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Manual)['counts']['all'])->toBe(2)
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Spotify)['rows'])->toBe([])
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Spotify)['counts']['all'])->toBe(0);
});

it('echoes the Spotify connection test and derives reduced mode', function (): void {
    $metrics = app(EditorialMetrics::class);

    expect($metrics->spotifyConnectionEcho())->toBe(['connections' => [], 'reduced' => true]);

    ImportConnection::factory()->create([
        'name' => 'Main Spotify',
        'provider' => ImportConnectionProvider::Spotify,
        'auth_type' => \App\Enums\ImportConnectionAuthType::ClientCredentials,
        'status' => ImportConnectionStatus::Failed,
        'last_tested_at' => now(),
    ]);

    $echo = $metrics->spotifyConnectionEcho();
    expect($echo['reduced'])->toBeTrue()
        ->and($echo['connections'][0]['status'])->toBe(ImportConnectionStatus::Failed);

    ImportConnection::query()->update(['status' => ImportConnectionStatus::Connected]);
    expect(app(EditorialMetrics::class)->spotifyConnectionEcho()['reduced'])->toBeFalse();
});

it('builds media finding bars with hidden zero rows and gallery doorways', function (): void {
    cleanMedia();
    missingFileMedia();

    $findings = app(EditorialMetrics::class)->mediaFindings();
    $missing = collect($findings['rows'])->first(
        fn ($row): bool => $row->meta('reason') === MediaDiagnosticReason::MissingFile->value,
    );

    expect($missing)->not->toBeNull()
        ->and($missing->value)->toBe(1.0)
        ->and($missing->url)
        ->toContain('tab='.MediaLibraryTask::NeedsAttention->value)
        ->and($missing->url)->toContain('filters%5Breason%5D%5Bvalue%5D='.MediaDiagnosticReason::MissingFile->value)
        // Zero-count findings are hidden (decision 5): no bar may report 0.
        ->and(collect($findings['rows'])->every(fn ($row): bool => $row->value > 0))->toBeTrue()
        ->and($findings['rate']->of)->toBe(2);
});

it('forgets the intake snapshot on intake writes', function (): void {
    $metrics = app(EditorialMetrics::class);
    expect($metrics->intakeSnapshot()['queue']['submissions'])->toBe(0);

    PublicFormSubmission::factory()->create();
    expect(app(EditorialMetrics::class)->intakeSnapshot()['queue']['submissions'])->toBe(1);

    failedImport();
    expect(app(EditorialMetrics::class)->intakeSnapshot()['queue']['imports'])->toBe(1);

    Media::factory()->create();
    ImportConnection::factory()->create(['provider' => ImportConnectionProvider::Manual]);
    // Media/connection writes must also invalidate — the snapshot may never
    // contradict a change the editor just made (decision 12's contract).
    expect(app(EditorialMetrics::class)->intakeSnapshot()['media']['total'])->toBe(1);
});
```

Notes for the implementer: `PublicFormSubmission::factory()` exists
(`database/factories/PublicFormSubmissionFactory.php`) and defaults status to
`new`; `Media::factory()` produces a `public`-disk row with a `reference_key`
whose file does not exist until you `put()` it — exactly the missing-file
finding. If a factory default differs, adjust the fixture, never the assertion
direction.

- [ ] **Step 2: Run, watch red**

Run: `php artisan test --compact --filter=DashboardIntakeMetricsTest`
Expected: FAIL — `Call to undefined method ... intakeSnapshot()`.

- [ ] **Step 3: Implement the metrics surface**

Add to `app/Support/Dashboard/EditorialMetrics.php` (imports:
`App\Enums\ImportConnectionProvider`, `App\Enums\ImportConnectionStatus`,
`App\Enums\MediaDiagnosticReason`, `App\Enums\MediaLibraryTask`,
`App\Enums\PublicFormSubmissionStatus`, `App\Enums\StreamEventType`,
`App\Filament\Resources\Media\MediaResource`,
`App\Models\ImportConnection`, `App\Models\Media`,
`App\Support\Media\MediaInventoryDiagnostics`,
`App\Support\Media\MediaRecordScope`,
`Filament\Actions\Imports\Models\FailedImportRow`, `Filament\Facades\Filament`,
`Illuminate\Support\Facades\URL` — `Import` and `PublicFormSubmission` are
already imported):

```php
    private const INTAKE_CACHE_KEY = self::CACHE_PREFIX.':intake';

    /**
     * Board 3's cached numbers. Global by design: submissions, imports,
     * connections and library-wide media findings have no podcast dimension,
     * so the intake snapshot takes no scope argument.
     *
     * The media block rides the inventory diagnostics scan (file existence
     * checks, lazyById) — the reason these counts are cached at all.
     *
     * @return array{queue: array{submissions: int, imports: int, failed_rows: int}, media: array{findings: array<string, int>, flagged: int, total: int}, generated_at: string}
     */
    public function intakeSnapshot(): array
    {
        return Cache::remember(self::INTAKE_CACHE_KEY, self::CACHE_SECONDS, function (): array {
            $diagnostics = app(MediaInventoryDiagnostics::class);
            $inventory = fn (): Builder => app(MediaRecordScope::class)->inventoryQuery();

            return [
                'queue' => [
                    'submissions' => PublicFormSubmission::query()->status(PublicFormSubmissionStatus::New)->count(),
                    'imports' => Import::query()->whereHas('failedRows')->count(),
                    'failed_rows' => FailedImportRow::query()->count(),
                ],
                'media' => [
                    'findings' => collect(MediaDiagnosticReason::cases())
                        ->mapWithKeys(fn (MediaDiagnosticReason $reason): array => [
                            $reason->value => $diagnostics->applyReasonFilter($inventory(), $reason)->count(),
                        ])
                        ->all(),
                    'flagged' => $diagnostics->applyReasonFilter($inventory())->count(),
                    'total' => $inventory()->count(),
                ],
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * The intake work queue: new submissions and imports with failed rows,
     * newest first. One entry per failed import, not per failed row — the
     * failure CSV is the actionable artifact and carries every row, so the
     * queue never silently truncates failures (D-2).
     *
     * `$source` follows D-4: only the manual channel has attributable rows
     * today, so a connected-provider source yields an empty, honest queue.
     *
     * @return array{rows: array<int, array{type: StreamEventType, title: string, subtitle: ?string, url: string, at: Carbon}>, counts: array{all: int, submissions: int, imports: int}}
     */
    public function intakeQueue(?ImportConnectionProvider $source = null, ?StreamEventType $kind = null, int $limit = 10): array
    {
        if ($source !== null && $source !== ImportConnectionProvider::Manual) {
            return ['rows' => [], 'counts' => ['all' => 0, 'submissions' => 0, 'imports' => 0]];
        }

        $snapshot = $this->intakeSnapshot()['queue'];

        $submissions = $kind === StreamEventType::Import ? collect() : PublicFormSubmission::query()
            ->status(PublicFormSubmissionStatus::New)
            ->latest('submitted_at')
            ->limit($limit)
            ->get(['id', 'form_key', 'form_name_snapshot', 'submitted_at'])
            ->map(fn (PublicFormSubmission $submission): array => [
                'type' => StreamEventType::Submission,
                'title' => (string) ($submission->form_name_snapshot ?: $submission->form_key),
                'subtitle' => null,
                'url' => PublicFormSubmissionResource::getUrl('edit', ['record' => $submission->getKey()]),
                'at' => Carbon::parse($submission->submitted_at),
            ]);

        $imports = $kind === StreamEventType::Submission ? collect() : Import::query()
            ->whereHas('failedRows')
            ->withCount('failedRows')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Import $import): array => [
                'type' => StreamEventType::Import,
                'title' => (string) $import->file_name,
                'subtitle' => __('admin.dashboard.intake.failed_rows', [
                    'failed' => (int) $import->failed_rows_count,
                    'total' => (int) $import->total_rows,
                ]),
                'url' => $this->failedRowsDownloadUrl($import),
                'at' => Carbon::parse($import->created_at),
            ]);

        return [
            'rows' => $submissions->concat($imports)
                ->sortByDesc(fn (array $row): int => $row['at']->getTimestamp())
                ->take($limit)
                ->values()
                ->all(),
            'counts' => [
                'all' => $snapshot['submissions'] + $snapshot['imports'],
                'submissions' => $snapshot['submissions'],
                'imports' => $snapshot['imports'],
            ],
        ];
    }

    /**
     * The exact URL the import-completion notification offers, so the queue
     * doorway and the notification can never diverge
     * (vendor `ImportAction.php:317`). Authorization stays with
     * `DownloadImportFailureCsv` + `ImportPolicy::view`.
     */
    public function failedRowsDownloadUrl(Import $import): string
    {
        return URL::signedRoute('filament.imports.failed-rows.download', [
            'authGuard' => Filament::getAuthGuard(),
            'import' => $import,
        ], absolute: false);
    }

    /**
     * Decision 4's card data: the persisted connection-test echo, nothing
     * else. `reduced` mirrors the fetcher's own rule — it only offers
     * Connected Spotify connections and warns it will run reduced otherwise
     * (`SpotifyLinksFetcher::selectedConnection()`).
     *
     * @return array{connections: array<int, array{name: string, status: ImportConnectionStatus, last_tested_at: ?Carbon}>, reduced: bool}
     */
    public function spotifyConnectionEcho(): array
    {
        $connections = ImportConnection::query()
            ->where('provider', ImportConnectionProvider::Spotify)
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'last_tested_at']);

        return [
            'connections' => $connections
                ->map(fn (ImportConnection $connection): array => [
                    'name' => (string) $connection->name,
                    'status' => $connection->status,
                    'last_tested_at' => $connection->last_tested_at === null
                        ? null
                        : Carbon::parse($connection->last_tested_at),
                ])
                ->all(),
            'reduced' => ! $connections->contains(
                fn (ImportConnection $connection): bool => $connection->status === ImportConnectionStatus::Connected,
            ),
        ];
    }

    /**
     * Decision 5's bars: every diagnostic reason with a non-zero count,
     * largest first, each carrying the gallery doorway into the
     * needs-attention task pre-filtered to that reason — where the repair
     * actions live. The rate is the "97% clean" caption.
     *
     * @return array{rows: array<int, BreakdownRow>, rate: Rate}
     */
    public function mediaFindings(): array
    {
        $media = $this->intakeSnapshot()['media'];

        $rows = collect(MediaDiagnosticReason::cases())
            ->map(fn (MediaDiagnosticReason $reason): BreakdownRow => new BreakdownRow(
                label: $reason->getLabel(),
                value: (float) ($media['findings'][$reason->value] ?? 0),
                color: $reason->getColor(),
                url: MediaResource::getUrl('index', [
                    'tab' => MediaLibraryTask::NeedsAttention->value,
                    'filters' => ['reason' => ['value' => $reason->value]],
                ]),
                meta: ['bar' => $reason->barClass(), 'reason' => $reason->value],
            ))
            ->filter(fn (BreakdownRow $row): bool => $row->value > 0)
            ->sortByDesc(fn (BreakdownRow $row): float => $row->value)
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'rate' => new Rate(
                covered: max(0, $media['total'] - $media['flagged']),
                of: $media['total'],
                description: __('admin.dashboard.media_findings.rate_description'),
            ),
        ];
    }
```

Extend `forget()`:

```php
    public function forget(): void
    {
        Cache::forget($this->cacheKey(null));
        Cache::forget(self::INTAKE_CACHE_KEY);

        ContentGroup::query()
            ->pluck('id')
            ->each(fn (int $id): bool => Cache::forget($this->cacheKey($id)));
    }
```

Register the observer on the intake models in
`app/Providers/AppServiceProvider.php`, directly under the existing three
registrations (line 124), with vendor-model imports added:

```php
        // Board 3's snapshot sources. Vendor import models are observable
        // like any Eloquent model; failedRows()->createMany() fires a
        // created event per row, so import failures invalidate immediately.
        PublicFormSubmission::observe(EditorialMetricsCacheObserver::class);
        Media::observe(EditorialMetricsCacheObserver::class);
        ImportConnection::observe(EditorialMetricsCacheObserver::class);
        Import::observe(EditorialMetricsCacheObserver::class);
        FailedImportRow::observe(EditorialMetricsCacheObserver::class);
```

- [ ] **Step 4: Run green, format, commit**

Run: `php artisan test --compact --filter=DashboardIntakeMetricsTest` → PASS.
Also run `--filter=DashboardOverviewLensTest` (the shared `forget()` changed).
`vendor/bin/pint --dirty --format agent`.

```bash
git add app/Support/Dashboard/EditorialMetrics.php app/Providers/AppServiceProvider.php tests/Feature/DashboardIntakeMetricsTest.php
git commit -m "feat(dashboard): intake metrics surface with observer-invalidated snapshot"
```

---

## Task 3: `ImportPolicy` — admins may download failure CSVs

**Files:**
- Create: `app/Policies/ImportPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (one `Gate::policy` line next
  to the existing `CuratorMediaPolicy` registration at line 176)
- Test: `tests/Feature/DashboardIntakeMetricsTest.php` (additions)

**Interfaces:**
- Consumes: `User::hasRoleAtLeast(UserRole::Admin)`, vendor
  `DownloadImportFailureCsv`'s policy branch (it checks
  `Gate::getPolicyFor($import::class)` + `method_exists($policy, 'view')`,
  else falls back to owner-only).
- Produces: `ImportPolicy::view(User $user, Import $import): bool`.

- [ ] **Step 1: Write the failing test** (append to
  `tests/Feature/DashboardIntakeMetricsTest.php`).

Vendor fact that shapes these assertions: the `filament.actions` route group
is `['web']` only (`ActionsServiceProvider.php:35`) — **the signature is not
enforced by middleware**. `DownloadImportFailureCsv` uses `hasValidSignature()`
only to decide which auth guard to read; the real protection is
authentication plus the policy/owner branch. So the boundary tests are guest
→ 401 and non-admin non-owner → 403, not "unsigned → rejected".

```php
it('gates the failure CSV to admins or the owner', function (): void {
    $import = failedImport();   // owned by a freshly created other admin
    $url = app(EditorialMetrics::class)->failedRowsDownloadUrl($import);

    // Signed in as the beforeEach() admin, who does NOT own the import.
    $this->get($url)->assertOk();

    // A non-admin who does not own the import is refused by the policy.
    $this->actingAs(User::factory()->create());
    $this->get($url)->assertForbidden();

    // A guest is refused before the policy runs.
    auth()->logout();
    $this->get($url)->assertUnauthorized();
});
```

- [ ] **Step 2: Run, watch red**

Run: `php artisan test --compact --filter="failure CSV"`
Expected: FAIL — 403 on the first (admin, signed) request: with no policy
registered, the controller's owner-only fallback refuses the non-owner admin.

- [ ] **Step 3: Implement** (`php artisan make:class Policies/ImportPolicy --no-interaction` then fill; policies here are plain classes):

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;

/**
 * Filament's failure-CSV controller honours a `view` policy on the Import
 * model and otherwise allows only the import's owner. Editorial triage is a
 * team activity: any admin may read failure CSVs; everyone else stays with
 * the owner-only rule. The signed-URL requirement is enforced by the
 * controller before this policy runs.
 */
class ImportPolicy
{
    public function view(User $user, Import $import): bool
    {
        return $user->hasRoleAtLeast(UserRole::Admin)
            || $import->user()->is($user);
    }
}
```

Register in `AppServiceProvider::boot()` beside the existing policy line:

```php
        Gate::policy(Import::class, ImportPolicy::class);
```

- [ ] **Step 4: Run green, format, commit**

Run: `php artisan test --compact --filter=DashboardIntakeMetricsTest` → PASS.
`vendor/bin/pint --dirty --format agent`.

```bash
git add app/Policies/ImportPolicy.php app/Providers/AppServiceProvider.php tests/Feature/DashboardIntakeMetricsTest.php
git commit -m "feat(dashboard): admins may download import failure CSVs (ImportPolicy::view)"
```

---

## Task 4: `IntakeQueueWidget`

**Files:**
- Create: `app/Filament/Widgets/IntakeQueueWidget.php`
- Create: `resources/views/filament/widgets/intake-queue.blade.php`
- Modify: `lang/en/admin.php` + `lang/he/admin.php` (the new
  `dashboard.intake` section — full en+he key blocks below; the
  `dashboard.filters` additions belong to Task 7)
- Test: `tests/Feature/DashboardIntakeLensTest.php` (create)

**Interfaces:**
- Consumes: `EditorialMetrics::intakeQueue()` (Task 2), `StreamEventType`
  (Task 1), `ReadsDashboardFilters::dashboardSource()` (Task 7 adds it — until
  then the trait method does not exist, so this task adds it as part of the
  widget step; see Step 3).
- Produces: `App\Filament\Widgets\IntakeQueueWidget` (registered on the lens
  in Task 7), Livewire-callable `selectKind(?string $kind)`.

**Lang keys added in this task** (en / he values; `trans` arrays in both
files, inside the existing `'dashboard' => [...]` section):

```php
// en — 'dashboard' => [... 'intake' => [
'intake' => [
    'heading' => 'Work queue',
    'chips_all' => 'All',
    'failed_rows' => ':failed of :total rows failed',
    'download_hint' => 'The link downloads the failure CSV — every failed row with its error, ready to fix and re-import.',
    'showing_latest' => 'Showing the latest :count of :total',
    'view_new_submissions' => 'All new submissions',
    'empty_heading' => 'Nothing needs intake attention',
    'empty_description' => 'No new submissions and no failed import rows.',
    'source_empty' => ':source intake produces no queue rows yet — import failures surface here as CSV imports.',
],
```

```php
// he — 'dashboard' => [... 'intake' => [
'intake' => [
    'heading' => 'תור טיפול',
    'chips_all' => 'הכול',
    'failed_rows' => ':failed מתוך :total שורות נכשלו',
    'download_hint' => 'הקישור מוריד את קובץ הכשלים (CSV) — כל שורה שנכשלה עם השגיאה שלה, מוכן לתיקון ולייבוא חוזר.',
    'showing_latest' => 'מציג את :count האחרונים מתוך :total',
    'view_new_submissions' => 'לכל הפניות החדשות',
    'empty_heading' => 'אין פריטים לטיפול',
    'empty_description' => 'אין פניות חדשות ואין שורות ייבוא שנכשלו.',
    'source_empty' => 'ערוץ :source אינו מזין כרגע שורות לתור — כשלי ייבוא מופיעים כאן כייבוא CSV.',
],
```

- [ ] **Step 1: Write the failing tests** (create
  `tests/Feature/DashboardIntakeLensTest.php`; the fixture helpers
  `failedImport()`/`cleanMedia()`/`missingFileMedia()` are defined in
  `DashboardIntakeMetricsTest.php` — Pest files share the global function
  namespace, so re-declare them here under different names or move them to
  `tests/Pest.php`; **move them to `tests/Pest.php`** so both files share one
  definition, which is the repo's existing helper idiom
  (`setTestTranscriptionMode` lives there)):

```php
<?php

use App\Enums\DashboardLens;
use App\Enums\ImportConnectionProvider;
use App\Enums\StreamEventType;
use App\Enums\TranscriptionMode;
use App\Filament\Widgets\IntakeQueueWidget;
use App\Models\ImportConnection;
use App\Models\PublicFormSubmission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

it('lists new submissions and failed imports with kind chips', function (): void {
    PublicFormSubmission::factory()->create(['form_name_snapshot' => 'Join us']);
    failedImport(fileName: 'episodes.csv');

    Livewire::test(IntakeQueueWidget::class)
        ->assertSee('Join us')
        ->assertSee('episodes.csv')
        ->assertSeeHtml('data-testid="intake-row"')
        ->assertSeeHtml('data-testid="widget-tag-stock"')
        ->assertDontSeeHtml('wire:poll')
        ->call('selectKind', StreamEventType::Submission->value)
        ->assertSee('Join us')
        ->assertDontSee('episodes.csv')
        ->call('selectKind', 'nonsense')
        ->assertSet('kind', null);
});

it('shows the honest provider empty state under a connected source filter', function (): void {
    PublicFormSubmission::factory()->create(['form_name_snapshot' => 'Join us']);

    Livewire::test(IntakeQueueWidget::class, ['pageFilters' => ['source' => ImportConnectionProvider::Spotify->value]])
        ->assertDontSee('Join us')
        ->assertSeeHtml('data-testid="intake-source-empty"');
});

it('shows the empty state when nothing needs intake attention', function (): void {
    PublicFormSubmission::factory()->reviewed()->create();

    Livewire::test(IntakeQueueWidget::class)
        ->assertSee(__('admin.dashboard.intake.empty_heading'))
        ->assertDontSeeHtml('data-testid="intake-row"');
});
```

- [ ] **Step 2: Run, watch red**

Run: `php artisan test --compact --filter=DashboardIntakeLensTest`
Expected: FAIL — `Class "App\Filament\Widgets\IntakeQueueWidget" not found`.

- [ ] **Step 3: Implement the widget**

`app/Filament/Widgets/IntakeQueueWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Enums\StreamEventType;
use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ReadsDashboardFilters;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Board 3's first widget: what arrived and needs a decision — new public
 * submissions and imports whose rows failed. Chips narrow by kind; the
 * source filter narrows by intake channel (D-4). Chip state is
 * widget-local by decision 8: it resets on reload.
 */
class IntakeQueueWidget extends Widget
{
    use AdminOnlyWidget;
    use InteractsWithPageFilters;
    use ReadsDashboardFilters;
    use ShowsLoadingSkeleton;

    protected string $view = 'filament.widgets.intake-queue';

    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public ?string $kind = null;

    public function selectKind(?string $kind = null): void
    {
        // Livewire-callable with a browser-supplied argument: narrow it (P3).
        // Only the two queue kinds are selectable; anything else means "all".
        $this->kind = in_array($kind, [StreamEventType::Submission->value, StreamEventType::Import->value], strict: true)
            ? $kind
            : null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $source = $this->dashboardSource();
        $queue = app(EditorialMetrics::class)->intakeQueue(
            source: $source,
            kind: StreamEventType::tryFrom((string) $this->kind),
        );

        return [
            'rows' => $queue['rows'],
            'counts' => $queue['counts'],
            'activeKind' => $this->kind,
            'chipKinds' => [StreamEventType::Submission, StreamEventType::Import],
            'source' => $source,
            'submissionsUrl' => PublicFormSubmissionResource::getUrl('index', [
                'filters' => ['status' => ['value' => \App\Enums\PublicFormSubmissionStatus::New->value]],
            ]),
        ];
    }
}
```

`resources/views/filament/widgets/intake-queue.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.intake.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        @if ($source !== null && $source !== \App\Enums\ImportConnectionProvider::Manual)
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400" data-testid="intake-source-empty">
                {{ __('admin.dashboard.intake.source_empty', ['source' => $source->getLabel()]) }}
            </p>
        @else
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium">
                <button
                    type="button"
                    wire:click="selectKind(null)"
                    @class([
                        'rounded-full px-2.5 py-0.5',
                        'bg-primary-600 text-white dark:bg-primary-500' => $activeKind === null,
                        'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300' => $activeKind !== null,
                    ])
                >
                    {{ __('admin.dashboard.intake.chips_all') }} {{ \App\Support\UiFormats::number($counts['all']) }}
                </button>

                @foreach ($chipKinds as $chipKind)
                    <button
                        type="button"
                        wire:click="selectKind('{{ $chipKind->value }}')"
                        @class([
                            'rounded-full px-2.5 py-0.5',
                            'bg-primary-600 text-white dark:bg-primary-500' => $activeKind === $chipKind->value,
                            'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300' => $activeKind !== $chipKind->value,
                        ])
                        data-testid="intake-chip-{{ $chipKind->value }}"
                    >
                        {{ $chipKind->getLabel() }}
                        {{ \App\Support\UiFormats::number($chipKind === \App\Enums\StreamEventType::Submission ? $counts['submissions'] : $counts['imports']) }}
                    </button>
                @endforeach
            </div>

            @if (count($rows) === 0)
                <div class="mt-4" data-testid="intake-empty">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('admin.dashboard.intake.empty_heading') }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.intake.empty_description') }}</p>
                </div>
            @else
                <ul class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        <li class="flex flex-wrap items-center gap-2 py-2 text-sm" data-testid="intake-row">
                            <span class="{{ $row['type']->chipClass() }} rounded-full px-2 py-0.5 text-xs font-medium">
                                {{ $row['type']->getLabel() }}
                            </span>

                            <a href="{{ $row['url'] }}" class="flex-1 truncate text-gray-800 hover:underline dark:text-gray-200">
                                {{ $row['title'] }}
                            </a>

                            @if ($row['subtitle'])
                                <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ __('admin.dashboard.intake.download_hint') }}">
                                    {{ $row['subtitle'] }}
                                </span>
                            @endif

                            <time
                                class="text-xs text-gray-500 tabular-nums dark:text-gray-400"
                                dir="ltr"
                                datetime="{{ $row['at']->toIso8601String() }}"
                            >
                                {{ $row['at']->copy()->timezone(\App\Support\UiTimezone::name())->format(\App\Support\UiFormats::dateTime()) }}
                            </time>
                        </li>
                    @endforeach
                </ul>

                @if ($counts['all'] > count($rows))
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-testid="intake-cap-note">
                        {{ __('admin.dashboard.intake.showing_latest', ['count' => count($rows), 'total' => $counts['all']]) }}
                        · <a href="{{ $submissionsUrl }}" class="hover:underline">{{ __('admin.dashboard.intake.view_new_submissions') }}</a>
                    </p>
                @endif
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

The cap note satisfies the no-silent-caps rule (P6): the list truncates at 10
but says so, states the true total from the snapshot, and offers the
submissions doorway (imports have no list surface until WB — each entry
already carries its own CSV doorway; this asymmetry is stated in the open
questions).

**Also in this step:** add `dashboardSource()` to
`app/Filament/Widgets/Concerns/ReadsDashboardFilters.php` (Task 7 wires the
form field; the reader must exist first):

```php
    /** The intake source scope, if the command bar holds a valid provider. */
    protected function dashboardSource(): ?ImportConnectionProvider
    {
        return ImportConnectionProvider::tryFrom((string) ($this->pageFilters['source'] ?? ''));
    }
```

(import `App\Enums\ImportConnectionProvider` in the trait).

- [ ] **Step 4: Run green, mutation-check, format, commit**

Run: `php artisan test --compact --filter=DashboardIntakeLensTest` → PASS.
Mutation-check: comment out the source gate in `intakeQueue()` → the
source-filter test fails; restore. `vendor/bin/pint --dirty --format agent`,
`vendor/bin/filacheck --fix --dirty` → 0.

```bash
git add app/Filament/Widgets/IntakeQueueWidget.php app/Filament/Widgets/Concerns/ReadsDashboardFilters.php resources/views/filament/widgets/intake-queue.blade.php lang/en/admin.php lang/he/admin.php tests/Feature/DashboardIntakeLensTest.php tests/Pest.php tests/Feature/DashboardIntakeMetricsTest.php
git commit -m "feat(dashboard): intake work queue widget with kind chips and honest caps"
```

---

## Task 5: `SpotifyConnectionWidget`

**Files:**
- Create: `app/Filament/Widgets/SpotifyConnectionWidget.php`
- Create: `resources/views/filament/widgets/spotify-connection.blade.php`
- Modify: `lang/en/admin.php` + `lang/he/admin.php` (`dashboard.connection`)
- Test: `tests/Feature/DashboardIntakeLensTest.php` (additions)

**Interfaces:**
- Consumes: `EditorialMetrics::spotifyConnectionEcho()` (Task 2),
  `ImportConnectionStatus::getLabel()/getColor()` (existing),
  `ImporterSettings::getUrl()`, `SpotifyLinksFetcher::getUrl()` (Filament page
  URL helpers, same mechanism `PublicFormTargetWarningsWidget` already uses).
- Produces: `App\Filament\Widgets\SpotifyConnectionWidget`.

**Lang keys** (`dashboard.connection`):

```php
// en
'connection' => [
    'heading' => 'Spotify connection',
    'last_tested' => 'Last tested · :date',
    'never_tested' => 'Never tested',
    'reduced_note' => 'No connected connection — fetches run in reduced mode.',
    'none_heading' => 'No Spotify connection',
    'none_description' => 'Spotify fetches run in reduced mode until a working connection exists.',
    'manage' => 'Manage connections',
    'open_fetcher' => 'Open the links fetcher',
],
// he
'connection' => [
    'heading' => 'חיבור Spotify',
    'last_tested' => 'נבדק לאחרונה · :date',
    'never_tested' => 'טרם נבדק',
    'reduced_note' => 'אין חיבור פעיל — שליפות ירוצו במצב מצומצם.',
    'none_heading' => 'אין חיבור Spotify',
    'none_description' => 'שליפות מ־Spotify ירוצו במצב מצומצם עד שיוגדר חיבור תקין.',
    'manage' => 'ניהול חיבורים',
    'open_fetcher' => 'לכלי השליפה',
],
```

- [ ] **Step 1: Write the failing tests** (append to
  `tests/Feature/DashboardIntakeLensTest.php`):

```php
it('echoes the Spotify connection test with a day-first timestamp', function (): void {
    ImportConnection::factory()->create([
        'name' => 'Main Spotify',
        'provider' => ImportConnectionProvider::Spotify,
        'auth_type' => \App\Enums\ImportConnectionAuthType::ClientCredentials,
        'status' => \App\Enums\ImportConnectionStatus::Connected,
        'last_tested_at' => \Illuminate\Support\Carbon::parse('2026-07-31 07:55', 'Asia/Jerusalem'),
    ]);

    Livewire::test(\App\Filament\Widgets\SpotifyConnectionWidget::class)
        ->assertSee('Main Spotify')
        ->assertSee(__('admin.importer.statuses.connected'))
        ->assertSee('31/07/2026 07:55')
        ->assertSeeHtml('data-testid="widget-tag-stock"')
        ->assertDontSee(__('admin.dashboard.connection.reduced_note'))
        ->assertDontSeeHtml('wire:poll');
});

it('shows the reduced-mode empty state without a Spotify connection', function (): void {
    Livewire::test(\App\Filament\Widgets\SpotifyConnectionWidget::class)
        ->assertSee(__('admin.dashboard.connection.none_heading'))
        ->assertSee(__('admin.dashboard.connection.none_description'))
        ->assertSeeHtml(\App\Filament\Pages\ImporterSettings::getUrl());
});

it('marks a failed connection reduced', function (): void {
    ImportConnection::factory()->create([
        'name' => 'Main Spotify',
        'provider' => ImportConnectionProvider::Spotify,
        'auth_type' => \App\Enums\ImportConnectionAuthType::ClientCredentials,
        'status' => \App\Enums\ImportConnectionStatus::Failed,
        'last_tested_at' => null,
    ]);

    Livewire::test(\App\Filament\Widgets\SpotifyConnectionWidget::class)
        ->assertSee(__('admin.importer.statuses.failed'))
        ->assertSee(__('admin.dashboard.connection.never_tested'))
        ->assertSee(__('admin.dashboard.connection.reduced_note'));
});
```

(`ImportConnectionFactory` exists; if its defaults fight the provider/auth
pair, pass both explicitly as above — `ImportConnection::validateProviderAuthType()`
runs on save.)

- [ ] **Step 2: Run, watch red** — class not found.

- [ ] **Step 3: Implement**

`app/Filament/Widgets/SpotifyConnectionWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ImporterSettings;
use App\Filament\Pages\SpotifyLinksFetcher;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Widget;

/**
 * Decision 4's card: the persisted connection-test echo (status +
 * last_tested_at) for every Spotify connection, and the derived
 * reduced-mode note when none is Connected — the same rule the fetcher
 * itself warns with. No fetch record exists and none is displayed.
 */
class SpotifyConnectionWidget extends Widget
{
    use AdminOnlyWidget;
    use ShowsLoadingSkeleton;

    protected string $view = 'filament.widgets.spotify-connection';

    protected static ?int $sort = -25;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            ...app(EditorialMetrics::class)->spotifyConnectionEcho(),
            'manageUrl' => ImporterSettings::getUrl(),
            'fetcherUrl' => SpotifyLinksFetcher::getUrl(),
        ];
    }

    protected function skeletonRows(): int
    {
        return 2;
    }
}
```

`resources/views/filament/widgets/spotify-connection.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.connection.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        @if (count($connections) === 0)
            <div class="mt-4" data-testid="connection-empty">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('admin.dashboard.connection.none_heading') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.connection.none_description') }}</p>
            </div>
        @else
            <ul class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($connections as $connection)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" data-testid="connection-row">
                        <span class="flex-1 truncate text-gray-800 dark:text-gray-200">{{ $connection['name'] }}</span>

                        <x-filament::badge :color="$connection['status']->getColor()">
                            {{ $connection['status']->getLabel() }}
                        </x-filament::badge>

                        @if ($connection['last_tested_at'])
                            <time
                                class="text-xs text-gray-500 tabular-nums dark:text-gray-400"
                                dir="ltr"
                                datetime="{{ $connection['last_tested_at']->toIso8601String() }}"
                                data-testid="connection-tested-at"
                            >
                                {{ __('admin.dashboard.connection.last_tested', ['date' => $connection['last_tested_at']->copy()->timezone(\App\Support\UiTimezone::name())->format(\App\Support\UiFormats::dateTime())]) }}
                            </time>
                        @else
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.connection.never_tested') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($reduced)
                <p class="mt-2 text-xs text-warning-600 dark:text-warning-400" data-testid="connection-reduced">
                    {{ __('admin.dashboard.connection.reduced_note') }}
                </p>
            @endif
        @endif

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            <a href="{{ $manageUrl }}" class="hover:underline">{{ __('admin.dashboard.connection.manage') }}</a>
            · <a href="{{ $fetcherUrl }}" class="hover:underline">{{ __('admin.dashboard.connection.open_fetcher') }}</a>
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
```

The `last_tested` line composes translated text with an LTR timestamp; keep
the whole `<time>` `dir="ltr"` as the stream does — the he value places the
date placeholder at the string end so the LTR run renders cleanly in RTL copy.

- [ ] **Step 4: Run green, mutation-check, format, commit**

Mutation-check: make `reduced` always `false` in `spotifyConnectionEcho()` →
the failed-connection test fails; restore.

```bash
git add app/Filament/Widgets/SpotifyConnectionWidget.php resources/views/filament/widgets/spotify-connection.blade.php lang/en/admin.php lang/he/admin.php tests/Feature/DashboardIntakeLensTest.php
git commit -m "feat(dashboard): Spotify connection card echoing the persisted test"
```

---

## Task 6: `MediaFindingsWidget`

**Files:**
- Create: `app/Filament/Widgets/MediaFindingsWidget.php`
- Create: `resources/views/filament/widgets/media-findings.blade.php`
- Modify: `lang/en/admin.php` + `lang/he/admin.php` (`dashboard.media_findings`)
- Test: `tests/Feature/DashboardIntakeLensTest.php` (additions)

**Interfaces:**
- Consumes: `EditorialMetrics::mediaFindings()` (Task 2), `BreakdownRow`
  (`value`, `url`, `meta('bar')`, `meta('reason')`), `Rate`
  (`percent()`, `isEmpty()`), `MediaDiagnosticReason::getIcon()`.
- Produces: `App\Filament\Widgets\MediaFindingsWidget`.

**Lang keys** (`dashboard.media_findings`):

```php
// en
'media_findings' => [
    'heading' => 'Media by finding',
    'rate' => ':percent% clean',
    'rate_description' => 'Media files with no maintenance findings',
    'caption' => 'Each bar exits into the gallery\'s needs-attention task filtered to that finding',
    'empty' => 'No findings — every media file is clean.',
    'no_media' => 'No media files yet.',
],
// he
'media_findings' => [
    'heading' => 'מדיה לפי ממצא',
    'rate' => ':percent% ללא ממצאים',
    'rate_description' => 'קבצי מדיה ללא ממצאי תחזוקה',
    'caption' => 'כל פס יוצא לגלריה במשימת «דורש טיפול» מסונן לממצא',
    'empty' => 'אין ממצאים — כל קבצי המדיה תקינים.',
    'no_media' => 'אין עדיין קבצי מדיה.',
],
```

- [ ] **Step 1: Write the failing tests** (append to
  `tests/Feature/DashboardIntakeLensTest.php`):

```php
it('renders finding bars styled by the enum with gallery doorways', function (): void {
    cleanMedia();
    missingFileMedia();

    Livewire::test(\App\Filament\Widgets\MediaFindingsWidget::class)
        ->assertSee(__('admin.media_library.repair_missing_file'))
        ->assertSeeHtml('data-testid="media-finding-row"')
        ->assertSeeHtml(\App\Enums\MediaDiagnosticReason::MissingFile->barClass())
        ->assertSeeHtml('tab='.\App\Enums\MediaLibraryTask::NeedsAttention->value)
        ->assertSeeHtml('filters%5Breason%5D%5Bvalue%5D='.\App\Enums\MediaDiagnosticReason::MissingFile->value)
        ->assertSee(__('admin.dashboard.media_findings.rate', ['percent' => 50.0]))
        ->assertSeeHtml('data-testid="widget-tag-stock"')
        ->assertDontSeeHtml('wire:poll');
});

it('hides zero-count findings and celebrates a clean library', function (): void {
    cleanMedia();

    Livewire::test(\App\Filament\Widgets\MediaFindingsWidget::class)
        ->assertSee(__('admin.dashboard.media_findings.empty'))
        ->assertDontSeeHtml('data-testid="media-finding-row"');
});
```

(The clean fixture writes the file to the faked disk, ships `reference_key`,
`disk public`, `visibility public` from the factory, and passes the metadata
backfill check; if `allowsForBackfill` flags the factory shape, adjust the
fixture columns until `mediaFindings()['rows']` is empty for it — the
assertion direction stands.)

- [ ] **Step 2: Run, watch red** — class not found.

- [ ] **Step 3: Implement**

`app/Filament/Widgets/MediaFindingsWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Widget;

/**
 * Decision 5's bars: every media diagnostic reason with a non-zero count,
 * styled by the enum's own colour/icon, each exiting into the gallery's
 * needs-attention task pre-filtered to that finding — where the repair
 * actions live. The rate line is the "clean" caption.
 */
class MediaFindingsWidget extends Widget
{
    use AdminOnlyWidget;
    use ShowsLoadingSkeleton;

    protected string $view = 'filament.widgets.media-findings';

    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return app(EditorialMetrics::class)->mediaFindings();
    }
}
```

`resources/views/filament/widgets/media-findings.blade.php` (the bar markup is
the `publication-gap` pattern; widths scale against the largest finding so a
six-item library and a six-thousand-item library both read):

```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.dashboard.media_findings.heading') }}
            </h2>
            @include('filament.widgets.partials.stock-flow-tag')
        </div>

        @if (! $rate->isEmpty())
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" data-testid="media-clean-rate">
                {{ __('admin.dashboard.media_findings.rate', ['percent' => $rate->percent()]) }}
            </p>
        @endif

        @if ($rate->isEmpty())
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.media_findings.no_media') }}</p>
        @elseif (count($rows) === 0)
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400" data-testid="media-findings-empty">
                {{ __('admin.dashboard.media_findings.empty') }}
            </p>
        @else
            @php($peak = max(array_map(fn ($row): float => $row->value, $rows)))
            <dl class="mt-4 space-y-2 text-sm">
                @foreach ($rows as $row)
                    @php($reason = \App\Enums\MediaDiagnosticReason::from($row->meta('reason')))
                    <div class="flex items-center gap-3" data-testid="media-finding-row">
                        <dt class="flex w-44 shrink-0 items-center gap-1.5 text-gray-600 dark:text-gray-300">
                            <x-filament::icon :icon="$reason->getIcon()" class="h-4 w-4 shrink-0" />
                            <a href="{{ $row->url }}" class="truncate hover:underline">{{ $row->label }}</a>
                        </dt>
                        <dd class="flex flex-1 items-center gap-2">
                            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" dir="ltr">
                                <div
                                    class="{{ $row->meta('bar', 'bg-info-500') }} h-2.5 rounded-full"
                                    style="width: {{ max(6, (int) round(($row->value / $peak) * 100)) }}%"
                                ></div>
                            </div>
                            <span class="w-8 text-end font-semibold tabular-nums">{{ \App\Support\UiFormats::number((int) $row->value) }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{ __('admin.dashboard.media_findings.caption') }}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

- [ ] **Step 4: Run green, mutation-check, format, commit**

Mutation-check: drop the `->filter(...)` zero-hiding line in
`mediaFindings()` → the clean-library test fails; restore.

```bash
git add app/Filament/Widgets/MediaFindingsWidget.php resources/views/filament/widgets/media-findings.blade.php lang/en/admin.php lang/he/admin.php tests/Feature/DashboardIntakeLensTest.php
git commit -m "feat(dashboard): media findings bars with gallery doorways"
```

---

## Task 7: The Intake command bar, lens list, and scope echo

**Files:**
- Modify: `app/Filament/Pages/Dashboard.php`
- Modify: `app/Filament/Widgets/DashboardContextWidget.php`
- Modify: `resources/views/filament/widgets/dashboard-context.blade.php`
- Modify: `app/Enums/ImportConnectionProvider.php` (add `options()`)
- Modify: `lang/en/admin.php` + `lang/he/admin.php` (`dashboard.filters`
  additions)
- Test: `tests/Feature/DashboardIntakeLensTest.php` +
  `tests/Feature/DashboardOverviewLensTest.php` (structural loop floor)

**Interfaces:**
- Consumes: the three widgets (Tasks 4–6), `dashboardSource()` (Task 4),
  `ImportConnectionProvider::options()` (added here).
- Produces: `Dashboard::getWidgetsForLens(DashboardLens::Intake)` returning
  the five-widget Intake list; `source` in `Dashboard::FILTER_KEYS`.

**Lang keys** (`dashboard.filters` additions):

```php
// en
'all_sources' => 'All sources',
'source_hint' => 'Narrows the work queue to one intake channel.',
// he
'all_sources' => 'כל המקורות',
'source_hint' => 'מצמצם את תור הטיפול לערוץ קליטה אחד.',
```

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/DashboardIntakeLensTest.php`:

```php
it('renders the intake board in board 3 order', function (): void {
    expect(\App\Filament\Pages\Dashboard::getWidgetsForLens(DashboardLens::Intake))->toBe([
        \App\Filament\Widgets\DashboardContextWidget::class,
        \App\Filament\Widgets\PublicFormTargetWarningsWidget::class,
        \App\Filament\Widgets\IntakeQueueWidget::class,
        \App\Filament\Widgets\SpotifyConnectionWidget::class,
        \App\Filament\Widgets\MediaFindingsWidget::class,
    ]);
});

it('hides range and podcast on intake and shows the sources filter', function (): void {
    Livewire::test(\App\Filament\Pages\Dashboard::class)
        ->dispatch('dashboard-filter', key: 'lens', value: DashboardLens::Intake->value)
        ->assertDontSee(__('admin.dashboard.filters.podcast_hint'))
        ->assertSee(__('admin.dashboard.filters.all_sources'))
        ->assertDontSee(\App\Enums\DashboardRange::Last7Days->getLabel());
});

it('accepts only command-bar keys for the source filter', function (): void {
    Livewire::test(\App\Filament\Pages\Dashboard::class)
        ->dispatch('dashboard-filter', key: 'source', value: ImportConnectionProvider::Spotify->value)
        ->assertSet('filters.source', ImportConnectionProvider::Spotify->value);
});

it('echoes the source scope instead of podcast and range on intake', function (): void {
    Livewire::test(\App\Filament\Widgets\DashboardContextWidget::class, [
        'pageFilters' => ['lens' => DashboardLens::Intake->value, 'source' => ImportConnectionProvider::Spotify->value],
    ])
        ->assertSee(ImportConnectionProvider::Spotify->getLabel())
        ->assertDontSee(__('admin.dashboard.filters.all_podcasts'));
});
```

In `tests/Feature/DashboardOverviewLensTest.php`, raise the structural-loop
floor so the three new widgets are provably inside the loop:

```php
    expect($widgets->count())->toBeGreaterThanOrEqual(10);
```

(The `$flow` list is untouched: all three Board-3 widgets are stock, and the
loop asserts the stock tag for anything not listed as flow — a new Intake
widget cannot ship untagged.)

- [ ] **Step 2: Run, watch red**

Run: `php artisan test --compact --filter="DashboardIntakeLensTest|DashboardOverviewLensTest"`
Expected: FAIL — Intake list still equals the Overview list; sources filter
absent; echo shows podcast.

- [ ] **Step 3: Implement**

`app/Enums/ImportConnectionProvider.php` — add (mirrors
`DashboardLens::options()`; D-10 keeps the filter state string-typed):

```php
    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $provider): array => [$provider->value => $provider->getLabel()])
            ->all();
    }
```

`app/Filament/Pages/Dashboard.php`:

1. `FILTER_KEYS` gains `source`:

```php
    /** The only filter keys the command bar owns. */
    private const FILTER_KEYS = ['lens', 'range', 'podcast', 'status', 'source'];
```

2. In `filtersForm()`, the scope row becomes lens-aware on all three fields
   (G1: a filter that does not apply to the active lens disappears — range
   was already lens-aware for Blockers; Intake joins it, the podcast select
   hides on Intake per D-5, and the sources select shows only on Intake):

```php
                Grid::make()
                    ->key('dashboardScopeRow')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        ToggleButtons::make('range')
                            ->key('dashboardRange')
                            ->hiddenLabel()
                            ->grouped()
                            ->live()
                            ->default(DashboardRange::Last30Days->value)
                            ->options(DashboardRange::options())
                            // A blocker is a blocker at any age, and nothing
                            // on Intake is range-scoped (decision 7): the
                            // range filter disappears on both lenses.
                            ->visible(fn (Get $get): bool => ! in_array(
                                DashboardLens::fromFilter($get('lens')),
                                [DashboardLens::Blockers, DashboardLens::Intake],
                                strict: true,
                            )),
                        Select::make('podcast')
                            ->key('dashboardPodcast')
                            ->hiddenLabel()
                            ->live()
                            ->placeholder(__('admin.dashboard.filters.all_podcasts'))
                            ->helperText(__('admin.dashboard.filters.podcast_hint'))
                            ->options(fn (): array => app(EditorialMetrics::class)->podcastOptions())
                            ->searchable()
                            ->preload(false)
                            ->optionsLimit(50)
                            // No Intake widget has a podcast dimension (D-5).
                            ->visible(fn (Get $get): bool => DashboardLens::fromFilter($get('lens')) !== DashboardLens::Intake),
                        Select::make('source')
                            ->key('dashboardSource')
                            ->hiddenLabel()
                            ->live()
                            ->placeholder(__('admin.dashboard.filters.all_sources'))
                            ->helperText(__('admin.dashboard.filters.source_hint'))
                            // Three fixed options: native Select, no search
                            // (settings-dashboard rule for tiny finite sets).
                            ->options(ImportConnectionProvider::options())
                            ->visible(fn (Get $get): bool => DashboardLens::fromFilter($get('lens')) === DashboardLens::Intake),
                    ]),
```

(import `App\Enums\ImportConnectionProvider`.)

3. `getWidgetsForLens()` splits Intake out:

```php
    /** @return array<int, class-string<Widget>> */
    public static function getWidgetsForLens(DashboardLens $lens): array
    {
        return match ($lens) {
            DashboardLens::Overview => [
                DashboardContextWidget::class,
                PublicFormTargetWarningsWidget::class,
                PublicationFunnelWidget::class,
                EditorialStatsWidget::class,
                PublicationHeatmapWidget::class,
                ActivityStreamWidget::class,
                LibraryCompositionWidget::class,
            ],
            DashboardLens::Blockers => [
                DashboardContextWidget::class,
                PublicationGapWidget::class,
                BlockersQueueWidget::class,
            ],
            DashboardLens::Intake => [
                DashboardContextWidget::class,
                PublicFormTargetWarningsWidget::class,
                IntakeQueueWidget::class,
                SpotifyConnectionWidget::class,
                MediaFindingsWidget::class,
            ],
        };
    }
```

(imports for the three new widgets; `PublicFormTargetWarningsWidget` stays on
Intake deliberately — its warnings are intake-adjacent and it hides itself
when clean.)

`app/Filament/Widgets/DashboardContextWidget.php` — `getViewData()` gets
lens-aware scope entries:

```php
        return [
            'funnel' => $metrics['funnel'],
            'lens' => $lens,
            'range' => in_array($lens, [DashboardLens::Blockers, DashboardLens::Intake], strict: true)
                ? null
                : $this->dashboardRange(),
            'status' => $this->dashboardStatus(),
            'podcast' => $lens === DashboardLens::Intake || $podcastId === null
                ? null
                : ContentGroup::query()->whereKey($podcastId)->value('title'),
            'showPodcast' => $lens !== DashboardLens::Intake,
            'source' => $lens === DashboardLens::Intake ? $this->dashboardSource() : null,
            'showSource' => $lens === DashboardLens::Intake,
            'stages' => FunnelStage::cases(),
            'generatedAt' => Carbon::parse($metrics['generated_at'])
                ->timezone(UiTimezone::name())
                ->format(UiFormats::time()),
        ];
```

`resources/views/filament/widgets/dashboard-context.blade.php` — the scope
echo swaps the podcast segment for the source segment on Intake:

```blade
        <p class="text-xs text-gray-500 dark:text-gray-400" data-testid="dashboard-scope-echo">
            {{ __('admin.dashboard.context.showing') }}: {{ $lens->getLabel() }}
            @if ($range)
                · {{ $range->getLabel() }}
            @endif
            @if ($showPodcast)
                · {{ $podcast ?? __('admin.dashboard.filters.all_podcasts') }}
            @endif
            @if ($showSource)
                · {{ $source?->getLabel() ?? __('admin.dashboard.filters.all_sources') }}
            @endif
            @if ($status)
                · {{ __('admin.dashboard.context.status_scope', ['status' => \App\Enums\FunnelStage::from($status)->getLabel()]) }}
            @endif
            · {{ __('admin.dashboard.context.as_of', ['time' => $generatedAt]) }}
        </p>
```

- [ ] **Step 4: Run green, run the full dashboard suites, format, commit**

Run: `php artisan test --compact --filter="Dashboard"` → PASS (Overview,
Blockers behavior unchanged; structural loop now covers 10+ widgets).
`vendor/bin/pint --dirty --format agent`, `vendor/bin/filacheck --fix --dirty`
→ 0.

```bash
git add app/Filament/Pages/Dashboard.php app/Filament/Widgets/DashboardContextWidget.php resources/views/filament/widgets/dashboard-context.blade.php app/Enums/ImportConnectionProvider.php lang/en/admin.php lang/he/admin.php tests/Feature/DashboardIntakeLensTest.php tests/Feature/DashboardOverviewLensTest.php
git commit -m "feat(dashboard): Intake lens gets its own widget list and command bar"
```

---

## Task 8: E4 — Filament contracts for the Board-3 enum pair

**Files:**
- Modify: `app/Enums/ExternalImageFailureReason.php`
- Modify: `app/Enums/MediaAcquisitionDisposition.php`
- Modify: `app/Support/Media/ExternalImageFailureMessage.php`
- Modify: `app/Livewire/Admin/MediaPickerPanel.php` (lines 674 and 1305)
- Test: `tests/Feature/DashboardIntakeMetricsTest.php` (additions; or the
  existing media-picker feature test file if the implementer finds the
  assertions fit better there — keep them mutation-checked either way)

**Interfaces:**
- Consumes: existing lang keys `admin.media_library.url_failure_*` (all 7
  exist) and `admin.media_library.storage_{copied,created,registered,reused}`
  (all 4 exist) — **no lang changes**.
- Produces: both enums implement `HasLabel`; `getLabel()` returns the same
  strings the call sites already show. Labels only: nothing renders these
  enums with colour or icon today, and E4's scope is contracts for what
  actually renders — adding unused `HasColor`/`HasIcon` would be dead
  vocabulary (the inverse of P2).

- [ ] **Step 1: Write the failing tests**:

```php
it('labels the Board-3 E4 enums through their Filament contracts', function (): void {
    expect(\App\Enums\ExternalImageFailureReason::TimedOut->getLabel())
        ->toBe(__('admin.media_library.url_failure_timed_out'))
        ->and(\App\Enums\MediaAcquisitionDisposition::Reused->getLabel())
        ->toBe(__('admin.media_library.storage_reused'));

    // The contract is the interface, not just the method (the corpus's
    // OrderStatus bug-shape: getColor() without implements HasColor).
    expect(\App\Enums\ExternalImageFailureReason::TimedOut)
        ->toBeInstanceOf(\Filament\Support\Contracts\HasLabel::class)
        ->and(\App\Enums\MediaAcquisitionDisposition::Reused)
        ->toBeInstanceOf(\Filament\Support\Contracts\HasLabel::class);
});

it('routes the failure message and picker notification through the enums', function (): void {
    $message = \App\Support\Media\ExternalImageFailureMessage::for(
        new \InvalidArgumentException('bad image'),
    );

    expect($message)->toBe(__('admin.media_library.url_failure_invalid_image'));
});
```

- [ ] **Step 2: Run, watch red** — `getLabel` undefined.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a safe external-image fetch was refused or failed. The label is the
 * operator-facing message the picker notifications show; it lived as a
 * string-interpolated key in ExternalImageFailureMessage until E4 gave the
 * enum its contract (one home, every call site routed — P2).
 */
enum ExternalImageFailureReason: string implements HasLabel
{
    case Blocked = 'blocked';
    case InvalidImage = 'invalid_image';
    case InvalidResponse = 'invalid_response';
    case NotFound = 'not_found';
    case TemporarilyUnavailable = 'temporarily_unavailable';
    case TimedOut = 'timed_out';
    case Unexpected = 'unexpected';

    public function getLabel(): string
    {
        return __("admin.media_library.url_failure_{$this->value}");
    }
}
```

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an acquisition actually did with the file. The label is the
 * notification title the picker shows on completion.
 */
enum MediaAcquisitionDisposition: string implements HasLabel
{
    case Copied = 'copied';
    case Created = 'created';
    case Registered = 'registered';
    case Reused = 'reused';

    public function getLabel(): string
    {
        return __("admin.media_library.storage_{$this->value}");
    }
}
```

Route the call sites:

- `ExternalImageFailureMessage::for()` last line becomes
  `return $reason->getLabel();`.
- `MediaPickerPanel.php:674`:
  `->title(__("admin.media_library.storage_{$result->disposition->value}"))`
  becomes `->title($result->disposition->getLabel())`.
- `MediaPickerPanel.php:1305`: the hand-written
  `$disposition = $reused ? 'reused' : 'created';` becomes
  `$disposition = ($reused ? MediaAcquisitionDisposition::Reused : MediaAcquisitionDisposition::Created)->value;`
  (the composed `acquisition_{$disposition}_{$mode}` key is a different key
  family and keeps its own translation strings; the enum now owns which
  disposition words exist).

- [ ] **Step 4: Run green — including the media-picker suites — format, commit**

Run: `php artisan test --compact --filter="DashboardIntakeMetricsTest|MediaPicker"`
Expected: PASS (the picker suites prove the routed call sites still render the
same strings).

```bash
git add app/Enums/ExternalImageFailureReason.php app/Enums/MediaAcquisitionDisposition.php app/Support/Media/ExternalImageFailureMessage.php app/Livewire/Admin/MediaPickerPanel.php tests/Feature/DashboardIntakeMetricsTest.php
git commit -m "feat(enums): Filament label contracts for the Board-3 E4 pair"
```

---

## Task 9: Full gate, state docs, final report

**Files:**
- Modify: `docs/phase-02/current-project-state.md` (Prompt-13 row: phase 3
  implemented)
- Modify: `docs/phase-02/dashboard-metrics-phase-2R-handoff.md` (decisions
  ledger item 8 and the "ActivityStreamWidget hand-writes colours" finding row
  flip to closed; the phase-3 bullet list under "Why phase 3 must be
  re-planned from scratch" gains a one-line "implemented per
  `dashboard-metrics-phase-3-plan.md`" note)

- [ ] **Step 1: Full gate**

```bash
php artisan test --compact
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Expected: all green, filacheck 0 violations. Record the outputs in the final
report. (Memory note: the pest run needs the raised CLI memory limit the repo
already configures; do not pass `-d` flags expecting them to reach subprocesses.)

- [ ] **Step 2: Update the state docs** (the prompt-completion protocol:
  `current-project-state.md` is the single rolling source; patch the 2R
  handoff only where its statements are now stale).

- [ ] **Step 3: Commit docs**

```bash
git add docs/phase-02/current-project-state.md docs/phase-02/dashboard-metrics-phase-2R-handoff.md
git commit -m "docs(dashboard): record phase 3 (Intake lens) as implemented"
```

- [ ] **Step 4: Final report** — classify every requirement above as
  done/partial/skipped with reasons; end with an "open flags + pattern
  evidence" section for the ledger owner. Do **not** push.

---

## Cause-pattern guardrails (why this plan does not re-create P1–P10)

- **P1/P2 (duplicated values / unrouted enums):** `StreamEventType` and the E4
  pair land with every call site routed in the same task, each with an
  anti-drift or contract test; chip classes exist in exactly one home per
  vocabulary (`FunnelStage` for stages, `StreamEventType` for event kinds).
- **P3 (raw live state):** the new `source` key is narrowed in
  `ReadsDashboardFilters::dashboardSource()` (`tryFrom`), `selectKind()` and
  `selectType()` narrow their browser-supplied arguments.
- **P4 (shared implicit keys):** no new tables or query-string surfaces; the
  queue is a plain widget; new filters ride the page's existing `filters`
  state with explicit component `->key()`s in the schema.
- **P5 (proxy metrics):** no provider attribution is invented for imports
  (D-4); the connection card shows persisted test state only; queue
  timestamps are the events themselves (`submitted_at`, import `created_at`).
- **P6 (silent caps):** the queue states "showing latest N of M" from the
  snapshot's true totals; findings bars are complete (six reasons, zero-count
  hidden by design, with the clean-rate denominator shown).
- **P7 (flake labels):** no browser tests here; any intermittent feature-test
  failure gets investigated, not re-run (tooling gate rule).
- **P8 (line-based guards):** the Task-1 drift guard scans file contents for
  literals, not line-anchored regexes, and is scoped to the stream surface.
- **P9 (`options(Enum::class)` state type):** the command bar keeps the
  `::options()` array idiom (D-10).
- **P10 (concept without a type home):** the stream/queue event vocabulary
  (`StreamEventType`) and the intake channel (`ImportConnectionProvider`
  narrowed reader) now have typed homes; the ledger's "provider/source strings
  on intake paths" candidate is answered.

## Open questions for the operator

1. **Sources-filter semantics for unattributable imports (D-4).** Implemented:
   all/manual show the full queue; spotify/google_drive show an explanatory
   empty state (imports carry no provider — the table has no such column, and
   the Spotify fetcher's direct importer bypasses Filament imports entirely).
   Alternatives: (b) treat submissions as the only source-scoped kind and
   always show imports; (c) scope nothing and drop the filter until WB records
   provider at import time. If (c), delete the `source` key and the Select —
   the plan isolates them for exactly that reversal.
2. **Queue granularity (D-2).** Implemented: one entry per failed import
   (failure CSV as the fix artifact). Alternative: one entry per
   `FailedImportRow` with `validation_error` as the line — honest only if the
   row-number-less display is acceptable, and it floods the queue on a bad
   CSV. Flip = swap the `imports` branch of `intakeQueue()` to query
   `FailedImportRow` directly.
3. **Podcast filter hidden on Intake (D-5).** Decision 7 locked only the
   range; hiding the podcast select follows G1 and the mockup. If the operator
   wants it visible-but-inert instead, remove the podcast `->visible()`
   closure — nothing else changes.
4. **Imports have no "view all" surface.** Submissions overflow doorways to
   the filtered Resource; imports have no admin listing until WB. Acceptable
   asymmetry, or should phase 3 add a minimal read-only imports listing?
   (Plan's position: WB scope, not dashboard scope.)
5. **Empty-state principles P1–P7 are unrecoverable as text.** They exist only
   by reference in the combined plan (the artifact they came from does not
   contain them either — verified by fetching both linked artifacts). This
   plan restates concrete empty states per widget instead. If the operator has
   the original P1–P7 text, it should be committed into
   `docs/phase-02/dashboard-metrics-combined-ux-plan.md`; any conflict with
   the empty states specced here should be resolved before Task 4.
6. **`PublicFormTargetWarningsWidget` stays on Intake** (self-hiding,
   intake-adjacent). Confirm or drop from the Intake list (one-line change in
   `getWidgetsForLens()`).

## Research references

- `docs/research/filament-examples-phase-02.md` § "Board 3 · Intake Lens
  Research (2026-08-03)" — corpus patterns adopted/rejected, and the
  no-connection-card-precedent verdict.
- Vendor verifications: `ListRecords` `#[Url(as: 'tab')]` / `(as: 'filters')`
  (`vendor/filament/filament/src/Resources/Pages/ListRecords.php:39,54`);
  signed failure-CSV route + `authGuard` parameter
  (`vendor/filament/actions/src/ImportAction.php:317`,
  `vendor/filament/actions/routes/web.php:16`); policy-else-owner branch in
  `DownloadImportFailureCsv`; `Import::getFailedRowsCount()`;
  `failedRows()->createMany()` (`vendor/filament/actions/src/Imports/Jobs/ImportCsv.php:126`).
- Boost `search-docs` (2026-08-03): ToggleButtons `grouped()`/`options()`,
  SelectFilter `options()`/`default()`, table widgets, Livewire lazy
  placeholders — and the doc warning that filter `options()` are a UI
  affordance, not an authorization boundary (why `dashboardSource()` narrows).
