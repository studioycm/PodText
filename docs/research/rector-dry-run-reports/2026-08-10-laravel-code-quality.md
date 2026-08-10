# Rector dry-run report — `LaravelSetList::LARAVEL_CODE_QUALITY`

2026-08-10, Task 9 (Phase T). `rector/rector:^2.6` + `driftingly/rector-laravel:^2.5` were
installed this session (clean resolution, no constraint conflicts). `composer rector` is
dry-run-locked (`vendor/bin/rector process --dry-run --ansi`); `composer rector:fix` exists
but is approval-gated and was not run. **No source files were rewritten to produce this
report.**

All verdicts below are **recommendations for the operator's DP4 call**, not decisions.

## 0. Wiring note — the brief's literal config does not boot (read this first)

The task brief specified:

```php
->withPHPStanConfigs([__DIR__.'/phpstan.neon'])
```

This throws before Rector reaches a single file. Reproduced by hand, standalone (bypassing
Rector's own error-output formatting to get the real exception):

```
CLASS: RectorPrefix202608\Illuminate\Container\EntryNotFoundException
MESSAGE: Rector\NodeTypeResolver\DependencyInjection\PHPStanServicesFactory
--- PREVIOUS ---
CLASS: _PHPStan_b3f880679\Nette\Schema\ValidationException
MESSAGE: Unexpected item 'parameters › databaseMigrationsPath'.
```

**Root cause:** `Rector\NodeTypeResolver\DependencyInjection\PHPStanServicesFactory` builds
its own PHPStan container by calling `PHPStan\DependencyInjection\ContainerFactory::create()`
directly. The real `vendor/bin/phpstan` binary does not call that API the same way — its own
bootstrap (`CommandHelper`) additionally merges every `extension.neon` that
`phpstan/extension-installer` discovered via Composer plugin auto-discovery (visible in the
generated `vendor/phpstan/extension-installer/src/GeneratedConfig.php`, which lists
`larastan/larastan → extension.neon` for this project). Rector's factory does not replicate
that merge — confirmed by reading
`Rector\NodeTypeResolver\DependencyInjection\PHPStanServicesFactory::resolveAdditionalConfigFiles()`,
which only adds whatever `withPHPStanConfigs()` supplied plus Rector's own three bundled
config files. Since `phpstan.neon` relies entirely on extension-installer auto-discovery for
larastan (by design — see the file's own header comment and
`docs/research/larastan-playbook.md`), larastan's `extension.neon` — the file that registers
`databaseMigrationsPath` and `parseModelCastsMethod` as valid schema keys — never reaches
Rector's container, and PHPStan's schema validator rejects both keys as unrecognised.

This is documented as **intentional** upstream: "Extensions are ignored on purpose, as some
of them run project code (e.g. Doctrine) and breaks idea of static analysis." —
[getrector.com/documentation/config-configuration](https://getrector.com/documentation/config-configuration).
It is also an independently-reported trap for this exact pairing:
[rectorphp/rector#8006](https://github.com/rectorphp/rector/issues/8006) ("Using Larastan
through Rector") and
[rectorphp/rector#8141](https://github.com/rectorphp/rector/issues/8141) (the feature
request — closed, shipped — that made `withPHPStanConfigs()`/`phpstanConfigs()` accept more
than one path, which the fix below depends on).

**Fix shipped in `rector.php` (deviation from the brief, necessary for the interface to
function):**

```php
->withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon'])
```

Reproduced clean on this exact change — the same standalone probe reports
`NO ERROR — container created successfully`, and `composer rector` (zero rules, before this
task's Step 7) prints Rector's own safe-by-default warning
(`[WARNING] Register rules or sets in your "rector.php" config`) with exit code 0, matching
the documented behaviour exactly.

`tests/Feature/RectorScriptContractTest.php` was adjusted to match — it now pins the two-path
form and additionally spawns the real binary to assert the specific crash signature
(`PHPStanServicesFactory`, `ValidationException`, `fatal_errors`) never reappears, independent
of how many rules are registered. Verified RED (against the brief's literal one-path form)
before GREEN (against the shipped two-path form) — both forms were run for real, not inferred.

### 0b. A second, narrower, deliberately-unfixed gap

Even with the container booting, this dry run reported 56 per-file PHPStan errors alongside
20 real file diffs. All 56 trace to one further cause, distinct from §0: larastan's
`extension.neon` declares `bootstrapFiles: [bootstrap.php]`, and that file (read at
`vendor/larastan/larastan/bootstrap.php`) boots a **full Laravel kernel** —
`require bootstrap/app.php; $app->make(Kernel::class)->bootstrap();` — specifically so
larastan's Laravel-aware type extensions have a real `Illuminate\Foundation\Application` to
call. PHPStan's `bootstrapFiles` execution happens in the real binary's analysis-application
flow, not inside `ContainerFactory::create()` itself, so §0's fix (which only satisfies schema
validation) does not make Rector run it. Buckets, traced to source by hand rather than
guessed:

| count | message | traced cause |
| --- | --- | --- |
| 37 | `Call to undefined method Illuminate\Container\Container::databasePath()` | `Larastan\Larastan\Properties\SquashedMigrationHelper::initializeTables()` — larastan's *squashed-migration schema* scan (a separate, opt-out-by-default feature this project does not use or configure) falls back to the global `database_path('schema')` helper when `squashedMigrationsPath` is empty (larastan's own default — `extension.neon`: `squashedMigrationsPath: []`, `disableSchemaScan: false`). `database_path()` resolves to `app()->databasePath()`, a method only `Illuminate\Foundation\Application` has; Rector's container only has a bare `Illuminate\Container\Container`. |
| 11 | `Target [Illuminate\Contracts\Container\Container] is not instantiable.` | Same family — another Laravel-container-dependent larastan extension failing to resolve a full-application binding. |
| 5 | `Undefined constant "Larastan\Larastan\LARAVEL_VERSION"` | `Larastan\Larastan\Methods\BuilderHelper` (registered unconditionally, backs `EloquentBuilderForwardsCallsExtension`) checks `LARAVEL_VERSION` on essentially any Eloquent builder method resolution. The constant is defined only inside `bootstrap.php`'s success path (`define('LARAVEL_VERSION', $app->version())`), which never runs. Not tied to any opt-in config — unavoidable for any file that touches an Eloquent builder. |
| 3 | `config` (bare message) | Same family — larastan's `config()`-return-type extension. |

**Why this does not undermine this task's actual goal.** The one setting this task's wiring
was for — `databaseMigrationsPath` — is confirmed by reading
`Larastan\Larastan\Properties\MigrationHelper::getMigrationFiles()` to only fall back to
`database_path('migrations')` when the configured array is **empty**
(`if (count($this->databaseMigrationPath) === 0)`). `phpstan.neon` sets it explicitly and
non-empty, so that specific fallback never fires. The 56 errors are real and are a second,
independently-reported (`#8006`) gap in the same integration, but they hit a larastan feature
this project doesn't use (squashed-migration schema scanning) and a check
(`BuilderHelper`/`LARAVEL_VERSION`) that is cosmetic to any specific rule outcome — not the
cast/migration type-knowledge path this task wired up. Confirmed no rule-application file
among the 20 changed files needed the missing constant to produce its diff.

**Why the obvious further fix (`withBootstrapFiles([vendor/larastan/larastan/bootstrap.php])`)
was not attempted.** It would boot this project's *entire* Laravel application — every
service provider — inside Rector's process. That is a materially larger and differently-
shaped risk than including one more config file (unaudited here for side effects: queued
jobs, filesystem writes, outbound calls during provider boot), and it goes beyond what this
task asked for ("so type rules see larastan's cast/generics knowledge" — achieved by §0
alone). Left as a documented, deferred decision for the operator, not added silently.

## 1. The command

```bash
composer rector > storage/framework/testing/rector-laravel-code-quality.txt 2>&1
```

(`storage/framework/testing/rector-laravel-code-quality.txt` is gitignored scratch — confirmed
with `git check-ignore -v`; not committed, deleted after this report was written.)

## 2. Summary counts

Rector 2.6.1 emits structured JSON for this invocation in this environment rather than the
colored console summary shown on stage at Laracon (`--output-format` was not requested
explicitly by the composer script; some layer in this session's tool environment appears to
request it regardless of invocation style — confirmed the JSON is Rector's own native
`JsonOutputFormatter` output, not a wrapper, by cross-checking `vendor/bin/rector/bin/rector.php`'s
formatter selection code). The JSON's own totals are an equally precise substitute for the
"tail -5" rule/file counts:

```json
{"changed_files": 20, "errors": 56}
```

4 rules fired across those 20 files:

| rule | files touched | occurrences |
| --- | --- | --- |
| `RectorLaravel\Rector\FuncCall\AppToResolveRector` | 19 | 19 call sites |
| `RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector` | 2 | 2 call sites |
| `RectorLaravel\Rector\FuncCall\SleepFuncToSleepStaticCallRector` | 1 | 1 call site |
| `RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector` | 1 | 1 call site |

(Files can appear under more than one rule; 20 is the de-duplicated file count PHPStan
reported.)

## 3. Full diff output

```diff
// app/Livewire/Public/ContributorContentItems.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -66,7 +66,7 @@
 
         return view('livewire.public.contributor-content-items', [
             'cardOptions' => $this->cardOptions(),
-            'cardTemplate' => app(PublicFrontCardTemplateResolver::class)->resolve('content_item'),
+            'cardTemplate' => resolve(PublicFrontCardTemplateResolver::class)->resolve('content_item'),
             'config' => $config,
             'items' => PublicContributorDiscovery::contentItemsForContributor($this->author, $this->search, $this->sort)
                 ->paginate((int) ($this->perPage ?? $pageConfig['items_per_page'] ?? $this->cardOptions()->cardsPerPage))
@@ -143,6 +143,6 @@
 
     protected function renderContext(): PublicFrontRenderContext
     {
-        return app(PublicFrontRenderContext::class);
+        return resolve(PublicFrontRenderContext::class);
     }
 }

// app/Livewire/Public/PublicFormModal.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -104,7 +104,7 @@
             return;
         }
 
-        $field = app(PublicFormVerificationPolicy::class)->submitterEmailField($definition);
+        $field = resolve(PublicFormVerificationPolicy::class)->submitterEmailField($definition);
 
         if ($key !== (string) ($field['key'] ?? '')) {
             return;
@@ -196,7 +196,7 @@
     public function render(PublicFormSchemaFactory $schemaFactory): View
     {
         $definition = $this->definition();
-        $verificationPolicy = app(PublicFormVerificationPolicy::class);
+        $verificationPolicy = resolve(PublicFormVerificationPolicy::class);
         $verificationRequired = $definition !== null && $verificationPolicy->requiresEmailVerification($definition);
         $emailVerificationField = $verificationRequired
             ? $verificationPolicy->submitterEmailField($definition)
@@ -286,7 +286,7 @@
 
     private function renderContext(): PublicFrontRenderContext
     {
-        return app(PublicFrontRenderContext::class);
+        return resolve(PublicFrontRenderContext::class);
     }
 
     /**

// app/Jobs/SettingsBackupSnapshotJob.php  [RectorLaravel\Rector\FuncCall\SleepFuncToSleepStaticCallRector]
--- Original
+++ New
@@ -48,7 +48,7 @@
             }
 
             if (! app()->runningUnitTests()) {
-                usleep(150000);
+                \Illuminate\Support\Sleep::usleep(150000);
             }
         });
     }

// app/Livewire/Admin/SettingsImportLocksManager.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -21,9 +21,9 @@
 
     public function mount(): void
     {
-        $schema = app(SettingsLifecycleSchema::class);
-        $locks = app(SettingsImportLocks::class);
-        $registry = app(SettingsImportLockSurfaceRegistry::class);
+        $schema = resolve(SettingsLifecycleSchema::class);
+        $locks = resolve(SettingsImportLocks::class);
+        $registry = resolve(SettingsImportLockSurfaceRegistry::class);
         $payload = $schema->payloadForGroup();
 
         $this->rows = collect($registry->surfaces($payload))
@@ -62,8 +62,8 @@
 
     public function saveLocks(): void
     {
-        $registry = app(SettingsImportLockSurfaceRegistry::class);
-        $lockedPaths = app(SettingsImportLocks::class)->save([
+        $registry = resolve(SettingsImportLockSurfaceRegistry::class);
+        $lockedPaths = resolve(SettingsImportLocks::class)->save([
             ...$registry->unitPathsForSurfaceIds($this->selectedPaths),
             ...$this->retiredLockedPaths,
         ]);
@@ -78,7 +78,7 @@
     {
         $this->selectedPaths = array_values(array_unique([
             ...$this->selectedPaths,
-            ...app(SettingsImportLockSurfaceRegistry::class)->surfaceIdsForFrontText(),
+            ...resolve(SettingsImportLockSurfaceRegistry::class)->surfaceIdsForFrontText(),
         ]));
     }
 

// app/Livewire/Admin/SettingsLifecycleSelectionTable.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -33,19 +33,19 @@
 
     public function toggleGroup(string $group): void
     {
-        $this->selectedPaths = app(SettingsLifecycleSelectionState::class)
+        $this->selectedPaths = resolve(SettingsLifecycleSelectionState::class)
             ->toggleGroup($this->rows, $this->selectedPaths, $group);
     }
 
     public function toggleUnit(string $path): void
     {
-        $this->selectedPaths = app(SettingsLifecycleSelectionState::class)
+        $this->selectedPaths = resolve(SettingsLifecycleSelectionState::class)
             ->togglePath($this->rows, $this->selectedPaths, $path);
     }
 
     public function groupState(string $group): string
     {
-        return app(SettingsLifecycleSelectionState::class)
+        return resolve(SettingsLifecycleSelectionState::class)
             ->groupState($this->rows, $this->selectedPaths, $group);
     }
 

// app/Livewire/Public/ContentGroupBrowser.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector, RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector]
--- Original
+++ New
@@ -88,7 +88,7 @@
             ->when(
                 $this->normalizedSort() === 'title',
                 fn (Builder $query): Builder => $query->orderBy('title')->orderBy('id'),
-                fn (Builder $query): Builder => $query->orderByDesc('published_at')->orderByDesc('id'),
+                fn (Builder $query): Builder => $query->latest('published_at')->orderByDesc('id'),
             );
 
         return $query
@@ -122,7 +122,7 @@
     {
         $config = $this->pageConfig();
 
-        return app(PublicFrontCardTemplateResolver::class)->resolve(
+        return resolve(PublicFrontCardTemplateResolver::class)->resolve(
             family: 'content_group',
             key: $config['template_key'] ?? null,
         );
@@ -143,7 +143,7 @@
 
     private function renderContext(): PublicFrontRenderContext
     {
-        return app(PublicFrontRenderContext::class);
+        return resolve(PublicFrontRenderContext::class);
     }
 
     /**

// app/Livewire/Public/ContentItemBrowser.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector, RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector]
--- Original
+++ New
@@ -156,8 +156,8 @@
             'oldest_transcription' => $query->orderByEffectiveTranscriptionPublishedAt('asc'),
             'title_asc' => $query->orderBy('title')->orderBy('id'),
             'title_desc' => $query->orderByDesc('title')->orderByDesc('id'),
-            'original_newest' => $query->orderByDesc('original_published_at')->orderByDesc('id'),
-            'original_oldest' => $query->orderBy('original_published_at')->orderBy('id'),
+            'original_newest' => $query->latest('original_published_at')->orderByDesc('id'),
+            'original_oldest' => $query->oldest('original_published_at')->orderBy('id'),
             'duration_longest' => $query->orderByDesc('duration_seconds')->orderByDesc('id'),
             'duration_shortest' => $query->orderBy('duration_seconds')->orderBy('id'),
             default => $query->orderByEffectiveTranscriptionPublishedAt(),
@@ -206,7 +206,7 @@
         $config = $this->pageConfig();
         $groupPageConfig = $this->groupPageConfig();
 
-        return app(PublicFrontCardTemplateResolver::class)->resolve(
+        return resolve(PublicFrontCardTemplateResolver::class)->resolve(
             family: 'content_item',
             key: $config['item_template_key'] ?? null,
             overrides: [
@@ -367,7 +367,7 @@
 
     private function renderContext(): PublicFrontRenderContext
     {
-        return app(PublicFrontRenderContext::class);
+        return resolve(PublicFrontRenderContext::class);
     }
 
     /**

// app/Filament/Tables/EffectiveTranscriptionColumn.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -35,7 +35,7 @@
             return parent::applyEagerLoading($query);
         }
 
-        foreach (app(EffectiveTranscriptionResolver::class)->eagerLoadPaths() as $path) {
+        foreach (resolve(EffectiveTranscriptionResolver::class)->eagerLoadPaths() as $path) {
             if (! array_key_exists($path, $query->getEagerLoads())) {
                 $query = $query->with($path);
             }

// app/Filament/Widgets/ActivityStreamWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -55,7 +55,7 @@
         $type = $this->type ?? EditorialMetrics::streamTypeForStatus($this->dashboardStatus());
 
         return [
-            'events' => app(EditorialMetrics::class)->activityStream(
+            'events' => resolve(EditorialMetrics::class)->activityStream(
                 range: $this->dashboardRange(),
                 type: $type,
                 day: $this->day,

// app/Filament/Widgets/BlockersQueueWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -67,7 +67,7 @@
 
     public function table(Table $table): Table
     {
-        $metrics = app(EditorialMetrics::class);
+        $metrics = resolve(EditorialMetrics::class);
         $podcastId = $this->dashboardPodcastId();
 
         return $table
@@ -98,7 +98,7 @@
                 TextColumn::make('blocker_reasons')
                     ->label(__('admin.dashboard.queue.reasons'))
                     ->badge()
-                    ->state(fn (ContentItem $record): array => app(EditorialMetrics::class)->blockerReasonsFor($record))
+                    ->state(fn (ContentItem $record): array => resolve(EditorialMetrics::class)->blockerReasonsFor($record))
                     ->formatStateUsing(fn (string $state): string => DashboardReason::from($state)->getLabel())
                     ->color(fn (string $state): string => DashboardReason::from($state)->getColor())
                     ->icon(fn (string $state): Heroicon => DashboardReason::from($state)->getIcon()),
@@ -111,7 +111,7 @@
                     ->label(__('admin.dashboard.queue.reasons'))
                     ->options(DashboardReason::class)
                     ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
-                        ? app(EditorialMetrics::class)->applyReason($query, $data['value'])
+                        ? resolve(EditorialMetrics::class)->applyReason($query, $data['value'])
                         : $query),
             ])
             ->recordActions([
@@ -130,7 +130,7 @@
      */
     private function burnDown(): HtmlString
     {
-        $progress = app(EditorialMetrics::class)->blockersProgress($this->dashboardPodcastId());
+        $progress = resolve(EditorialMetrics::class)->blockersProgress($this->dashboardPodcastId());
 
         return new HtmlString(view('filament.widgets.partials.queue-burndown', [
             'tiers' => $progress,

// app/Filament/Widgets/Concerns/ReadsDashboardFilters.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -35,7 +35,7 @@
             return null;
         }
 
-        return app(EditorialMetrics::class)->podcastExists((int) $podcast)
+        return resolve(EditorialMetrics::class)->podcastExists((int) $podcast)
             ? (int) $podcast
             : null;
     }

// app/Filament/Widgets/DashboardContextWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector, RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector]
--- Original
+++ New
@@ -39,7 +39,7 @@
     protected function getViewData(): array
     {
         $podcastId = $this->dashboardPodcastId();
-        $metrics = app(EditorialMetrics::class)->snapshot($podcastId);
+        $metrics = resolve(EditorialMetrics::class)->snapshot($podcastId);
         $lens = DashboardLens::fromFilter($this->pageFilters['lens'] ?? null);
 
         return [
@@ -56,7 +56,7 @@
             'source' => $lens === DashboardLens::Intake ? $this->dashboardSource() : null,
             'showSource' => $lens === DashboardLens::Intake,
             'stages' => FunnelStage::cases(),
-            'generatedAt' => Carbon::parse($metrics['generated_at'])
+            'generatedAt' => \Illuminate\Support\Facades\Date::parse($metrics['generated_at'])
                 ->forDisplay(UiFormats::time()),
         ];
     }

// app/Filament/Widgets/EditorialStatsWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -48,7 +48,7 @@
     /** @return array<string, mixed> */
     protected function getViewData(): array
     {
-        $snapshot = app(EditorialMetrics::class)->snapshot($this->dashboardPodcastId());
+        $snapshot = resolve(EditorialMetrics::class)->snapshot($this->dashboardPodcastId());
         $funnel = $snapshot['funnel'];
         $gap = $snapshot['gap'];
         $attention = $snapshot['attention'];

// app/Filament/Widgets/IntakeQueueWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -49,7 +49,7 @@
     protected function getViewData(): array
     {
         $source = $this->dashboardSource();
-        $queue = app(EditorialMetrics::class)->intakeQueue(
+        $queue = resolve(EditorialMetrics::class)->intakeQueue(
             source: $source,
             kind: StreamEventType::tryFrom((string) $this->kind),
         );

// app/Filament/Widgets/LibraryCompositionWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -42,7 +42,7 @@
     /** @return array<string, mixed> */
     protected function getViewData(): array
     {
-        $metrics = app(EditorialMetrics::class);
+        $metrics = resolve(EditorialMetrics::class);
         $podcastId = $this->dashboardPodcastId();
         $structure = $metrics->snapshot($podcastId)['structure'];
 

// app/Filament/Widgets/MediaFindingsWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -29,6 +29,6 @@
     /** @return array<string, mixed> */
     protected function getViewData(): array
     {
-        return app(EditorialMetrics::class)->mediaFindings();
+        return resolve(EditorialMetrics::class)->mediaFindings();
     }
 }

// app/Filament/Widgets/PublicFormTargetWarningsWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -34,13 +34,13 @@
     public static function canView(): bool
     {
         return self::canViewAsAdmin()
-            && app(PublicFormTargetStatus::class)->misconfiguredCounts()['total'] > 0;
+            && resolve(PublicFormTargetStatus::class)->misconfiguredCounts()['total'] > 0;
     }
 
     /** @return array<string, mixed> */
     protected function getViewData(): array
     {
-        $counts = app(PublicFormTargetStatus::class)->misconfiguredCounts();
+        $counts = resolve(PublicFormTargetStatus::class)->misconfiguredCounts();
 
         $rows = collect([
             [

// app/Filament/Widgets/PublicationFunnelWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -48,7 +48,7 @@
     /** @return array<string, mixed> */
     protected function getViewData(): array
     {
-        $metrics = app(EditorialMetrics::class);
+        $metrics = resolve(EditorialMetrics::class);
         $podcastId = $this->dashboardPodcastId();
         $snapshot = $metrics->snapshot($podcastId);
         $series = $metrics->funnelSeries($this->dashboardRange(), $podcastId);

// app/Filament/Widgets/PublicationGapWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -44,7 +44,7 @@
     /** @return array<string, mixed> */
     protected function getViewData(): array
     {
-        $metrics = app(EditorialMetrics::class);
+        $metrics = resolve(EditorialMetrics::class);
         $podcastId = $this->dashboardPodcastId();
         $snapshot = $metrics->snapshot($podcastId);
         $forecast = $metrics->clearanceForecast($podcastId);

// app/Filament/Widgets/PublicationHeatmapWidget.php  [RectorLaravel\Rector\FuncCall\AppToResolveRector]
--- Original
+++ New
@@ -41,7 +41,7 @@
     /** @return array<string, mixed> */
     protected function getViewData(): array
     {
-        $data = app(EditorialMetrics::class)
+        $data = resolve(EditorialMetrics::class)
             ->publicationHeatmap($this->dashboardRange(), $this->dashboardPodcastId());
 
         return [
```

## 4. Per-rule verdict table

None of the four rules below rewrite a Filament fluent chain (a
`Something::make()->method()->method()` builder chain) — all four rewrite plain PHP global
helpers, Eloquent `Builder` methods, or a static facade call, none of which route through
Filament's `Macroable`. The brief's "default-reject anything touching Filament fluent chains"
policy is preserved for future sets but was not triggered by this one.

| rule | files touched | verdict | why |
| --- | --- | --- | --- |
| `RectorLaravel\Rector\FuncCall\AppToResolveRector` | 19 | **reject** | `app(X::class)` → `resolve(X::class)`. Behaviourally identical (`resolve()` is a thin wrapper around `app()` in Laravel's own `helpers.php`), but the codebase has an overwhelming, unambiguous existing convention: `app(` appears 457 times in `app/` today; the global `resolve()` helper appears **zero** times anywhere (every `resolve(` hit in the codebase is a method named `resolve()` on a project class, e.g. `PublicFrontCardTemplateResolver::resolve()`, unrelated to this rule). Adopting would plant a second, unprecedented idiom for identical behaviour in exactly 19 of 476 call sites — a net loss of consistency for zero functional gain. |
| `RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector` | 2 | **adopt** | `orderByDesc('published_at')` / `orderBy('original_published_at')` → `latest('published_at')` / `oldest('original_published_at')`. Verified via the rule's own source (`RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector`) that it only fires on column names matching a conservative default allowlist (`*_at`, `*_date`, `*_on`), which is why `id`, `title`, and `duration_seconds` in the same call chains are correctly left untouched — this is deliberate scoping, not a partial/flaky match. The codebase's own canonical model, `app/Models/ContentItem.php:154-155`, already writes the exact target shape (`->latest('published_at')->latest('id')`); this rule brings two Livewire browser components in line with that existing idiom rather than introducing a new one. Minor, pre-existing caveat unrelated to this rule: the tie-breaker (`->orderByDesc('id')`) does not become `->latest('id')` like `ContentItem.php`'s does, because `id` doesn't match the date-like allowlist — true before and after this rule, not something it worsens. |
| `RectorLaravel\Rector\FuncCall\SleepFuncToSleepStaticCallRector` | 1 | **defer** | `usleep(150000)` → `\Illuminate\Support\Sleep::usleep(150000)` in `app/Jobs/SettingsBackupSnapshotJob.php`, gated (per the rule's source) to `usleep()`/`sleep()` used as a bare statement. Low risk alone — `Sleep::usleep()` is a drop-in, test-fakeable replacement, and this call site is already skipped in tests via its own `! app()->runningUnitTests()` guard. Deferred, not rejected, because this project has exactly one other `usleep()` call site (`app/Support/Importer/ImporterThrottle.php:19`) that this dry run silently did not touch — confirmed by isolating that file that it independently fails PHPStan analysis under Rector for the §0b reason (`Undefined constant "Larastan\Larastan\LARAVEL_VERSION"`), not because the rule rejected it. Adopting today would convert one of two identical-purpose call sites, which is a worse, half-migrated state than leaving both alone. Revisit once §0b's gap is addressed (or resolved upstream), or handle both call sites by hand in the same change. |
| `RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector` | 1 | **reject** | `Carbon::parse(...)` → `\Illuminate\Support\Facades\Date::parse(...)` in `app/Filament/Widgets/DashboardContextWidget.php`. The `Date` facade is used **zero** times anywhere else in `app/`; the file already uses `Illuminate\Support\Carbon` (Laravel's own Carbon subclass, not raw `nesbot/carbon`), which already supports `setTestNow()` and every other testability hook the facade would add on top. Adopting for this single call site would introduce a brand-new, unprecedented facade-import pattern for no behavioural or testability gain here. |

**Net for this set:** 1 adopt, 1 defer (blocked on §0b, not on the rule itself), 2 reject —
all evidence-based against this codebase's actual, measured conventions rather than generic
Laravel-community preference.

## 5. What stays registered

Per the brief, the set **stays** in `rector.php` after this report — `composer rector` is
dry-run-locked and `composer rector:fix` is approval-gated, so a registered set is inert
without an explicit DP4 pass. `rector.php`'s committed shape:

```php
<?php

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/routes'])
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon'])
    ->withCache(__DIR__.'/storage/framework/cache/rector')
    ->withSets([LaravelSetList::LARAVEL_CODE_QUALITY]);
```
