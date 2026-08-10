# Rector dry-run report — `LaravelSetList::LARAVEL_CODE_QUALITY`

2026-08-10, Task 9 (Phase T). `rector/rector:^2.6` + `driftingly/rector-laravel:^2.5` were
installed this session (clean resolution, no constraint conflicts). `composer rector` is
dry-run-locked (`vendor/bin/rector process --dry-run --ansi`); `composer rector:fix` exists
but is approval-gated and was not run. **No source files were rewritten to produce this
report.**

All verdicts below are **recommendations for the operator's DP4 call**, not decisions.

> **Correction (2026-08-10, cross-session finding — see §0c):** every total from here through
> §4 was measured under Rector's default **parallel** mode, which two same-tree/same-config
> dry runs (A: 17 changed / 50 errors, B: 8 changed / 51 errors, only 6 diff files common to
> both) show is nondeterministic and lossy on this codebase. Serial mode
> (`->withoutParallel()`, `rector.php`'s config default as of this correction) reproduced
> **69 changed files / 147 errors** identically across two separate runs, plus a third,
> independent reproduction in this same pass. **The corrected headline for this set against
> this tree is 69 changed / 147 errored (serial, deterministic floor)** — treat the `20`/`56`
> totals quoted through §1-§4 below as a parallel-mode artifact, not the number to act on; see
> §0c for the full evidence and the corrected §4 table for the per-rule floor.

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

### §0c — parallel nondeterminism (2026-08-10, cross-session finding)

**Found by the Rector Laravel dry run session; artifacts verified by direct JSON comparison.**
`rector.php` had no explicit parallel setting when this report first shipped, so every run
above used Rector 2.6.1's default parallel mode — including the `20`/`56` totals in §1-§2.
Same tree, same config, cold, private cache per run; only the parallel/serial setting varied:

| run | mode | changed_files | errors |
| --- | --- | --- | --- |
| A | parallel (prior default) | 17 | 50 |
| B | parallel (prior default) | 8 | 51 |
| — | serial (`->withoutParallel()`) | 69 | 147 |
| — | serial (`->withoutParallel()`), repeat | 69 | 147 |

Run A and run B disagree with each other — 17 vs 8 changed files, only 6 diff files common to
both — despite identical tree, config, and a cold, private cache each time. Serial mode, run
twice the same way, agrees with itself exactly: byte-identical diff sets both times. **The
`20`/`56` totals this report originally shipped were themselves a parallel-mode artifact** —
notably neither run A's nor run B's own numbers, which is further evidence that parallel output
here is not a stable function of the tree and config alone. Independently reproduced a third
time in this same pass, after `rector.php` was changed to pin `->withoutParallel()`:
`composer rector -- --clear-cache` → `{"changed_files":69,"errors":147}`, with the same
five-rule, 69-file shape recorded below.

**Mechanism (inference — offered as the most consistent explanation of the observed numbers,
not confirmed by reading Rector's parallel-worker source):** a parallel worker that hits §0b's
larastan-boot gap (the `Illuminate\Container\Container`-is-not-`Application` failures) appears
to fail in a way that silently drops the remainder of its assigned chunk rather than surfacing
a partial-chunk error — which would explain both why repeated parallel runs disagree with each
other (different chunk/worker assignment each time) and why serial (one process, no chunk
boundaries) is the only mode that reproduced identically twice.

**Serial-floor corrections, all verified in this pass (`composer rector -- --clear-cache`,
`file_diffs[].applied_rectors` aggregated per rule):**

- `AppToResolveRector`: 60 files, ~121 call sites (parallel artifact: 19 files / 35 sites).
- `EloquentOrderByToLatestOrOldestRector`: 5 files (parallel artifact: 2). The three
  newly-visible files are `app/Support/PublicContent/PublicContentItemQueries.php`,
  `app/Support/PublicContent/PublicTranscriptionSelector.php`, and
  `app/Support/PublicFront/Sections/PublicDisplaySectionQueryResolver.php` — the shared public
  query core, not incidental call sites.
- `SleepFuncToSleepStaticCallRector`: 2 files (parallel artifact: 1) — both of this codebase's
  `usleep()` call sites migrate under serial: `app/Jobs/SettingsBackupSnapshotJob.php` and
  `app/Support/Importer/ImporterThrottle.php`. The parallel run's apparent "skip" of
  `ImporterThrottle.php` was the nondeterminism, not a property of that file.
- `CarbonToDateFacadeRector`: 6 files, 7 call sites (parallel artifact: 1 file / 1 site) —
  `DashboardContextWidget.php`, `AppServiceProvider.php`,
  `Support/Dashboard/Data/Heatmap.php`, `Support/Dashboard/JerusalemDailySeries.php`,
  `Support/Importer/SpotifyLinks/ImporterCsvBuilder.php`, and
  `Support/SettingsLifecycle/SettingsImportReport.php`.
- A fifth rule fires under serial that neither parallel run surfaced:
  `RectorLaravel\Rector\Coalesce\ApplyDefaultInsteadOfNullCoalesceRector`, once, on
  `database/migrations/2026_06_30_012921_create_settings_table.php` (both its `up()` and
  `down()` methods — `config('settings.repositories.database.table') ?? 'settings'` →
  `config('settings.repositories.database.table', 'settings')`). See its row in §4 for the
  verdict.

147 errored files is roughly 23% of this config's ~628 scanned paths — the serial numbers above
are themselves a floor, not a guaranteed ceiling; they are simply the only numbers this project
has that reproduced identically across repeat runs. There is no `--no-parallel` CLI flag in
Rector 2.6.1 to pin this per-invocation instead — determinism has to be pinned in `rector.php`
itself, which is what the fix below does.

**Fix applied:** `rector.php` now ends its fluent chain with `->withoutParallel()`, so
`composer rector` and `composer rector:fix` always run serial by default.
`tests/Feature/RectorScriptContractTest.php`'s wiring-pin test now also asserts `withoutParallel`
is present in `rector.php`'s committed text, since determinism is now load-bearing for every
future DP4 measurement, not just a one-time observation.

Cold-cache contract-test cost (measured final review): the full file runs in ~28s cold / ~16s warm; the fixture test's 300s timeout has ~272s headroom.

## 1. The command

```bash
composer rector > storage/framework/testing/rector-laravel-code-quality.txt 2>&1
```

(`storage/framework/testing/rector-laravel-code-quality.txt` is gitignored scratch — confirmed
with `git check-ignore -v`; not committed, deleted after this report was written.)

**Stale-cache caveat (measured):** a warm `storage/framework/cache/rector` under-reports changed files on re-runs against unchanged source (observed 11, then 6, vs 20 before the cache was cleared — that cleared-cache figure was itself later found to be a parallel-mode artifact, see §0c; the underlying point stands regardless: a warm cache under-reports relative to a cleared one) — and `RectorScriptContractTest` now warms this shared cache on every suite run, so a warm cache is the routine state. Before trusting any dry-run for a DP4 decision, clear the cache **and** run serial: `composer rector -- --clear-cache` (composer appends passthrough args after the baked flags) now runs serial by default, since `rector.php` pins `->withoutParallel()` (§0c) — parallel mode was separately measured nondeterministic and lossy on this codebase. DP4 measurement and fix runs need cold cache **and** serial together; either alone has been observed to under- or mis-report on this project.

## 2. Summary counts

Rector 2.6.1 emits structured JSON for this invocation — not a session artifact, and not
gated on anyone passing `--output-format=json` by hand. `composer.json`'s dev dependencies
already include `laravel/pao` ("Agent-optimized output for PHP testing tools"). Its
`vendor/laravel/pao/src/Drivers/Rector/Starter.php` runs at `vendor/autoload.php` load time —
a Composer `files` autoload entry, so every route into `vendor/bin/rector` picks it up
(composer script, direct binary call, or a subprocess spawned from inside a test) — and, when
`laravel/agent-detector` reports an agent context, rewrites `$_SERVER['argv']`/`$GLOBALS['argv']`
to force `--output-format=json` (its `ensureOutputFormatJson()` method) before Rector's own
CLI ever parses argv. Confirmed by isolating the call outside any interactive tool layer
entirely: a plain PHP script requiring only `vendor/autoload.php`, running the composer
script's exact command string (no `--output-format` flag anywhere in it) through
`Process::run()`, with the result written to a file and read back separately, reproduces the
identical JSON shape with the identical totals shown below. For an operator terminal, console format IS the default (pao's injection is agent-gated); `PAO_DISABLE=1` matters only inside agent sessions that need console output.

The JSON's `totals` sub-object is an equally precise substitute for the "tail -5" rule/file
counts — verified against Rector's own
`Rector\ChangesReporting\Output\Factory\JsonOutputFactory::create()` source:
`totals.changed_files` is `$processResult->getTotalChanged()`, Rector's own bookkeeping of
how many files it rewrote, not something PHPStan reports; `totals.errors` is
`count($processResult->getSystemErrors())`, the per-file PHPStan/larastan analysis failures
that §0b traces to source:

```json
{"totals": {"changed_files": 20, "errors": 56}}
```

**Superseded (§0c):** this `20`/`56` total is a parallel-mode run and does not reproduce — the
deterministic serial floor for this exact set against this exact tree is **69 changed / 147
errored** (five rules, not four). See §0c for the corrected measurement and §4 for the
corrected per-rule table.

4 rules fired across those 20 files (parallel-mode run — see the correction above):

| rule | files touched | call-site occurrences |
| --- | --- | --- |
| `RectorLaravel\Rector\FuncCall\AppToResolveRector` | 19 | 35 |
| `RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector` | 2 | 3 (`ContentGroupBrowser.php` ×1, `ContentItemBrowser.php` ×2) |
| `RectorLaravel\Rector\FuncCall\SleepFuncToSleepStaticCallRector` | 1 | 1 |
| `RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector` | 1 | 1 |

(Files can appear under more than one rule; 20 is `getTotalChanged()`'s de-duplicated file
count, not a sum of the occurrences column — a file with several `app()`/`orderBy()` call
sites counts once per rewrite in "occurrences" but once total in "files touched". Occurrence
counts were recounted by hand against §3's diff, not copied from the file-touched column.)

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

None of the five rules below rewrite a Filament fluent chain (a
`Something::make()->method()->method()` builder chain) — all five rewrite plain PHP global
helpers (including a bare `config()` call whose `??` fallback becomes a second argument),
Eloquent `Builder` methods, or a static facade call, none of which route through Filament's
`Macroable`. That claim needs one boundary drawn precisely: several diffs *do* edit
code that lives inside a closure passed as one argument of a Filament chain —
`BlockersQueueWidget.php`'s `->state(fn (ContentItem $record): array => app(...))` and
`->query(fn (Builder $query, array $data): Builder => ... app(...) ...)` are both
`AppToResolveRector` rewrites of the closure's own body, not of the chain's structure. The
brief's "default-reject anything touching Filament fluent chains" policy is about the chain
itself — its method calls and arguments as Filament/Macroable sees them — not the interior of
a closure a chain happens to carry, so this does not count as the policy firing. The policy is
preserved for future sets.

| rule | files touched | verdict | why |
| --- | --- | --- | --- |
| `RectorLaravel\Rector\FuncCall\AppToResolveRector` | 60 (parallel artifact: 19) | **reject** | `app(X::class)` → `resolve(X::class)`, ~121 call sites under serial (parallel artifact: 35 — see §0c). Behaviourally identical (`resolve()` is a thin wrapper around `app()` in Laravel's own `helpers.php`), but the codebase has an overwhelming, unambiguous existing convention: `app(` appears 457 times in `app/` today; the global `resolve()` helper appears **zero** times anywhere (every `resolve(` hit in the codebase is a method named `resolve()` on a project class, e.g. `PublicFrontCardTemplateResolver::resolve()`, unrelated to this rule). **The reject case is stronger than first reported, not weaker:** adopting would now plant a second, unprecedented idiom across roughly a quarter of every `app()` call site in the codebase (~121 of ~490), not the one-in-thirteen slice the parallel run undercounted — still zero functional gain either way. |
| `RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector` | 5 (parallel artifact: 2) | **defer** | `orderByDesc('published_at')` / `orderBy('original_published_at')` → `latest('published_at')` / `oldest('original_published_at')`. Behaviourally identical: `latest($col)`/`oldest($col)` are `orderBy($col, 'desc'\|'asc')` under a different name (verified via the rule's own `convertOrderByToLatest()`), gated by a conservative default column-name allowlist (`*_at`, `*_date`, `*_on`) confirmed via source — which is why `id`/`title`/`duration_seconds` in the same call chains are correctly left untouched; this is deliberate scoping, not a partial/flaky match. *Originally verdicted adopt on a precedent argument the task review disproved — the operator can still choose to adopt at DP4.* The precedent doesn't hold: `app/Models/ContentItem.php:154-155`'s existing shape is uniform `->latest('published_at')->latest('id')`, and this rule cannot reproduce that — `id` fails the allowlist, so its real output is the **mixed** `->latest($col)->orderByDesc('id')` form, a third shape matching neither the pre-existing `orderByDesc`/`orderByDesc` style nor `ContentItem.php`'s own `latest`/`latest` style. Worse, inside `ContentItemBrowser::applySort()` this breaks the parallelism of a `match` whose other arms (`title_asc`, `title_desc`, `duration_longest`, `duration_shortest`) stay on `orderBy`/`orderByDesc` — a reader now sees two idioms for structurally identical "sort by X, tie-break by id" arms, distinguished only by whether X happens to end in `_at`. **Blast-radius note (§0c):** serial mode shows this rule actually touches 5 files, not 2 — the three additional files are `PublicContentItemQueries`, `PublicTranscriptionSelector`, and `PublicDisplaySectionQueryResolver`, the shared public query core that the homepage, search, category, and tag landing pages all resolve through (per the public-panel and search-filters guidelines). A rewrite here is no longer contained to two Livewire browser components; any future adopt decision must review the shared core's call sites too, not just `ContentGroupBrowser`/`ContentItemBrowser`. The rewrite itself is safe; the consistency case for adopting it *now* is negative, not positive. |
| `RectorLaravel\Rector\FuncCall\SleepFuncToSleepStaticCallRector` | 2 (parallel artifact: 1) | **defer** | `usleep(150000)` → `\Illuminate\Support\Sleep::usleep(150000)`, gated (per the rule's source) to `usleep()`/`sleep()` used as a bare statement. Under serial this fires on **both** of this codebase's `usleep()` call sites: `app/Jobs/SettingsBackupSnapshotJob.php` (already skipped in tests via its own `! app()->runningUnitTests()` guard) and `app/Support/Importer/ImporterThrottle.php`. This row's original defer reasoning was that adopting would leave one of two identical-purpose call sites half-migrated, because `ImporterThrottle.php` appeared invisible to the rule under the parallel run. **That premise is dissolved:** both sites migrate identically under serial (§0c), so there is no half-migration risk. Still **defer**, not adopt — both sites migrate under serial; adopt only if `Sleep::fake()` testability is a feature the operator actually wants here. That trade is the operator's call at DP4, not a correctness or consistency question either way. |
| `RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector` | 6 (parallel artifact: 1) | **reject** | `Carbon::parse(...)` → `\Illuminate\Support\Facades\Date::parse(...)`, 7 call sites under serial (parallel artifact: 1 file / 1 site — see §0c) across `DashboardContextWidget.php`, `AppServiceProvider.php`, `Support/Dashboard/Data/Heatmap.php`, `Support/Dashboard/JerusalemDailySeries.php`, `Support/Importer/SpotifyLinks/ImporterCsvBuilder.php`, and `Support/SettingsLifecycle/SettingsImportReport.php`. The `Date` facade is used **zero** times anywhere else in `app/`; every touched file already uses `Illuminate\Support\Carbon` (Laravel's own Carbon subclass, not raw `nesbot/carbon`), which already supports `setTestNow()` and every other testability hook the facade would add on top. Adopting would introduce a brand-new, unprecedented facade-import pattern across 6 files — not the single call site first reported — for no behavioural or testability gain. |
| `RectorLaravel\Rector\Coalesce\ApplyDefaultInsteadOfNullCoalesceRector` | 1 | **reject** | `config('settings.repositories.database.table') ?? 'settings'` → `config('settings.repositories.database.table', 'settings')`, both call sites (`up()` and `down()`) in `database/migrations/2026_06_30_012921_create_settings_table.php` — an **applied** migration (confirmed via `php artisan migrate:status`: batch 1, ran). Applied migrations are frozen history; a rule that edits one is auto-rejected regardless of the change's merit, independent of whether the rewrite itself is safe. Only surfaced once serial mode processed this file at all — neither parallel run reached it (§0c). |

**Net for this set:** 0 adopt, 2 defer, 3 reject — all evidence-based against this codebase's
actual, measured conventions rather than generic Laravel-community preference. Of the two
defers: `SleepFuncToSleepStaticCallRector` is no longer blocked on §0b — serial mode migrates
both call sites identically (§0c), so it now defers purely on whether `Sleep::fake()`
testability is wanted, an operator preference call, not a technical blocker;
`EloquentOrderByToLatestOrOldestRector` remains flipped from an original adopt this task's
review disproved, and its blast radius grew from 2 files to 5 under serial (§0c). Of the three
rejects: `AppToResolveRector`'s case strengthened under serial (60 files, not 19);
`CarbonToDateFacadeRector`'s reasoning is unchanged, now spanning 6 files instead of 1;
`ApplyDefaultInsteadOfNullCoalesceRector` is newly surfaced under serial and auto-rejected as an
edit to an applied migration.

## 5. What stays registered

Per the brief, the set **stays** in `rector.php` after this report — `composer rector` is
dry-run-locked and `composer rector:fix` is approval-gated, so a registered set is inert
without an explicit DP4 pass. `rector.php`'s committed shape (now pinning serial mode per
§0c):

```php
<?php

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/routes'])
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon'])
    ->withCache(__DIR__.'/storage/framework/cache/rector')
    ->withSets([LaravelSetList::LARAVEL_CODE_QUALITY])
    // Serial on purpose: parallel mode is nondeterministic and lossy here (measured 2026-08-10: parallel 17 vs 8 changed files run-to-run; serial 69, byte-identical twice) — see the dry-run report §0c.
    ->withoutParallel();
```
