# LaravelDaily: Queues — course notes + PodText audit

- **URL**: https://laraveldaily.com/course/queues-laravel — 18 lessons, 1h12m, text, **Mar 2026**
- **Also covers**: [Practical Laravel Queues on Live Server](https://laraveldaily.com/course/laravel-queues-server) — May 2023, **stale**, §6
- **Read**: 2026-08-07 (9 lessons, live) + 2026-08-08 (the rest, from the scraped corpus in `raw/`) — **18/18, complete**
- **Verified against**: `laravel/framework v13.23.0`; this repo's jobs, Horizon and queue config
- **Staleness verdict**: **passes** — Laravel 13 queue attributes, current job stub, current testing helpers

Full rewrite of the first version of this note, which covered 9 of 18 lessons. Every retained
claim was re-checked against the repo as it stands today; one claim from the draft of this
rewrite was itself caught false by that re-check (§4).

**Headline: PodText's queue code and tests already implement almost everything the course
recommends — ahead of it in places.** The remaining gaps are §7, and they are small.

---

## 1. Correction to the course, kept: "a timed-out job is silently killed" is not accurate

Measured in `Illuminate/Queue/Worker.php`: the `SIGALRM` handler calls
`markJobAsFailedIfWillExceedMaxAttempts(...)` **before** the `failOnTimeout` check, and that
method fails the job properly — `failed_jobs` row written, `failed()` called — whenever the
timeout lands on the job's last permitted attempt:

```php
$maxTries = ! is_null($job->maxTries()) ? $job->maxTries() : $maxTries;
if (! $job->retryUntil() && $maxTries > 0 && $job->attempts() >= $maxTries) {
    $this->failJob($job, $e);
}
```

For a `tries = 1` job — both of this repo's long jobs — **every** timeout is already a proper
failure. `$failOnTimeout` / `#[FailOnTimeout]` changes something narrower: fail immediately on
the *first* timeout instead of releasing for retry. Add it only where a timeout should not be
retried.

Also verified: **a job's own `$tries` overrides the worker/Horizon setting**
(`$job->maxTries()` wins), so Horizon's `tries => 1` here is a fallback, not a cap.

---

## 2. Retries, failures, idempotency, uniqueness — condensed, all verified

| topic | the useful part |
| --- | --- |
| Retries | `$tries` property, `tries()` method, or `#[Tries(5)]`; `#[Backoff(60, 120, 300)]` escalating delays. All **13** Laravel 13 queue attributes exist in `Illuminate/Queue/Attributes/`; the course teaches five. |
| `failed()` | Runs before Laravel's own logging. Uses: notify admins, clean up partial data, `report($exception)`. |
| Timeouts | Default 60 s. Prefer many small jobs over one big one. |
| Idempotency | A retried job re-runs work that already succeeded — guard the mutation, not the job. Pass IDs, not models; or `#[DeleteWhenMissingModels]`. |
| Unique jobs | `ShouldBeUnique` locks on **the class name by default** — a second *different* user's job is dropped too. Always implement `uniqueId()`. `#[UniqueFor(60)]` bounds the lock. `ShouldBeUniqueUntilProcessing` releases at pickup instead of completion. |
| Priority queues | `->onQueue('priority')` + `--queue=priority,default`; strict ordering — `default` starves until `priority` drains. |
| Horizon | Supervisor runs one `artisan horizon` process; workers are config-driven; deploy hook is `horizon:terminate`, not `queue:restart`. |

The `ShouldBeUnique` class-name-lock gotcha remains the most valuable single item in the
course — silent data loss, invisible from the interface name.

---

## 3. Newly read lessons — delay/release, skip middleware, chains, batches, progress

### Delayed dispatch vs conditional release (lesson 8)

`dispatch($user)->delay(now()->addMinutes(15))` delays the start;
`$this->release(60)` inside `handle()` re-queues a job whose precondition is unmet (their
example: email not yet verified). Two correct emphases:

- **`release()` requires max attempts to be set** — each release counts as an attempt and the
  job fails when they run out; without `$tries` it can loop.
- `#[Backoff]` automates *retry* delays; `release()` remains right for *conditional*
  re-queueing, where the job decides at runtime.

### Skipping via job middleware (lesson 10)

```php
public function middleware(): array
{
    return [Skip::when(is_null($this->user->email_verified_at))];
}
```

`Illuminate\Queue\Middleware\Skip::when(Closure|bool)` — verified present
(`Skip.php:21`). The job "succeeds" without `handle()` running. This is the declarative form
of the guard-and-return idiom PodText's image jobs already use inside `handle()`; both are
valid, middleware makes the skip visible from the class head.

The middleware directory also ships `FailOnException`, `Release`, **`SkipIfBatchCancelled`**,
`ThrottlesExceptions` — none covered by the course.

### Chains vs batches (lesson 12)

- **`Bus::chain([...])`** — strictly sequential; a failure stops the rest; only the failed job
  reaches `failed_jobs`, and retrying it resumes the chain. `->catch(fn (Throwable $e) => …)`.
- **`Bus::batch([...])`** — parallel, explicitly **random order**; every job needs the
  `Batchable` trait; batches persist in `job_batches` (fresh apps ship the migration;
  `make:queue-batches-table` otherwise — command verified). Hooks:
  `->before/progress/then/catch/finally`, plus `->name(…)`, `->onQueue()`, `->onConnection()`.
- Inside a batch job the course checks `$this->batch()->canceled()` manually — **the framework
  ships `SkipIfBatchCancelled` middleware for exactly this**, which the course never mentions.

### Batch progress (lesson 13)

`$batch = Bus::batch($jobs)->dispatch()` → hand `$batch->id` to the page →
`Bus::findBatch($id)` → `processedJobs()`, `totalJobs`, `progress()` (percent). Their demo
polls by reloading the page; the natural PodText form would be Livewire + `wire:poll` — §7c.

### `queue:listen` locally (lesson 9)

`queue:work` boots the app once and never sees code changes; `queue:listen` re-boots per job.
Development uses `listen`, production uses `work`. Explains the classic "my change doesn't
run" confusion; costs nothing to know.

### Intro lessons (1–3)

`ShouldQueue` on notifications, mailables and listeners — covered in
[events-observers-findings.md](events-observers-findings.md) §4a, including their advice to
queue the **listener** that does the work rather than each notification. Nothing else new.

---

## 4. Testing queues (lesson 17) — and a false claim caught in this rewrite's own draft

The lesson's three tools, all verified in `v13.23.0` (`BusFake.php:355,508`,
`InteractsWithQueue.php:92,219`):

| helper | answers | key assertions |
| --- | --- | --- |
| `Queue::fake()` | was it dispatched, with what payload, on which queue | `assertPushed(Job::class)` · `assertPushed(Job::class, fn ($job) => …)` · `assertPushed(Job::class, 1)` · `assertPushedOn('priority', …)` · `assertNotPushed(…)` |
| `Bus::fake()` | chain/batch structure | `assertChained([A::class, B::class, C::class])` · `assertBatched(fn (PendingBatch $b) => $b->name === '…' && $b->jobs->count() === 3)` |
| `$job->withFakeQueueInteractions()` | what the job did to the queue from inside `handle()` | `assertReleased(60)` · `assertNotReleased()` · also intercepts `delete()`/`fail()` |

Two boundaries the lesson states plainly, both correct and both easy to trip on:

- **`Queue::fake()` does not intercept `Bus::chain()`/`Bus::batch()`** — those need
  `Bus::fake()`.
- **`withFakeQueueInteractions()` fakes only queue interactions** — pair with `Mail::fake()`
  etc. for side effects.

### Applied here: already satisfied — and the draft of this section was wrong

The draft claimed the suite had no `Queue::fake()` and proposed dispatch-contract tests.
**Measured before writing: `Queue::fake` appears 12 times across 6 test files**, with
`assertPushed` in count form (`SettingsBackupSnapshotJob::class, 2`), payload-closure form,
and — at `EpisodeWorkspaceTest.php:537` — a closure asserting
`$job->queue === 'imports-exports'`, which covers what `assertPushedOn` would. The config
side has its own file (`ImportExportQueueConfigurationTest`).

So the lesson's guidance is **already implemented here, including its advanced forms**. No
proposal. `withFakeQueueInteractions()` has no current use — no job calls `release()` — worth
knowing for the day one does. Recorded as the worked example of the count-canary rule: the
draft's claim came from memory of an earlier session, and one grep killed it.

---

## 5. PodText audit — re-confirmed 2026-08-08

`app/Jobs`, `config/horizon.php`, `config/queue.php`, `.env.example` unchanged since the
first audit (git-clean on all four).

| | `Download…GroupImage` | `Download…ItemImage` | `ExportContentImagesZip` | `SettingsBackupSnapshotJob` |
| --- | --- | --- | --- | --- |
| `$tries` / `$timeout` | 3 / 120 | 3 / 120 | 1 / 1800 | 1 / config 1800 |
| `backoff()` | `[30, 120]` | `[30, 120]` | — | — |
| `ShouldBeUnique` + `uniqueId()` + `$uniqueFor` | ✓ 600 | ✓ 600 | — | — |
| `RateLimited` middleware | ✓ | ✓ | — | — |
| `ShouldQueueAfterCommit` | ✓ | ✓ | ✓ | ✓ |
| `failed()` | ✓ | ✓ | ✓ | **✗** |
| IDs not models / missing-model guards | ✓ | ✓ | ✓/– | ✓/– |

Coupling chain already documented in `.env.example` and correct:

```
job $timeout (1800) < HORIZON_SUPERVISOR_TIMEOUT (1850) < REDIS_QUEUE_RETRY_AFTER (1900)
```

`Batchable` / `Bus::batch` / `Bus::chain`: **zero uses in `app/`**, and no `job_batches`
migration exists — batches are wholly new surface here, not something half-adopted.

---

## 6. The May 2023 companion course — stale, one item kept

Three markers in visible code: `php8.1` in the Forge supervisor config, `queue:listen` as the
generated worker command, the pre-Laravel-11 four-trait job stub. Stopped per the era rule —
and per the newer rule ([README](README.md) §2) its *architecture* would need re-checking even
where its APIs run.

Kept, because ops guidance ages differently: **Forge's Graceful Shutdown / Stop Seconds must
exceed the longest job** — here that means **> 1800 s** on the Horizon daemon. Still
unverified against production (§7b).

---

## 7. Gaps and proposals (none applied)

### 7a. `SettingsBackupSnapshotJob` has no `failed()` — stranded `STATUS_PENDING` rows

If the whole job dies (timeout, OOM, deploy restart), snapshots it had not reached stay
`pending` forever; nothing sweeps them (`STATUS_PENDING` written in
`SettingsBackupSnapshotManager:83,97,278`, cleared nowhere on failure). With `tries = 1` the
*job* failure is visible in Horizon (§1), but the backup rows silently look in-progress.
**Proposal:** a `failed(Throwable $e)` marking that backup's still-pending snapshots failed —
mirrors the in-loop handler, ~10 lines.

### 7b. Verify Forge's Horizon daemon Stop Seconds > 1800

Then record it as the fourth term of the `# COUPLING:` comment in `.env.example`. One
production-console look.

### 7c. Batches fit two existing loops — future option, costs stated, not proposed

- `SettingsBackupSnapshotJob` runs all snapshots inside one 1800 s job with `usleep` pacing.
  As a named batch of per-snapshot jobs it would gain retry granularity, a real progress
  number, and `catch()` — at the cost of the `job_batches` migration and moving pacing into
  middleware.
- `ExportContentImagesZip`: batch progress via `Bus::findBatch` + `wire:poll` is exactly the
  lesson-13 pattern if bulk export ever needs UI progress.

### 7d. Queue attributes unused (cosmetic)

Properties/methods everywhere, attributes nowhere. Not deprecated; recorded as a decision,
not an oversight.

---

## 8. The bonus "AI skill" (lesson 18)

Its full text now sits in the corpus (`raw/queues-laravel.json`, 21k chars), so the earlier
"deliberately not fetched" caveat is obsolete. Position unchanged: **not installed** — the
author calls it personal preference, §4–5 show it mostly already satisfied, and a third-party
`SKILL.md` is executable instruction surface
([supply-chain-security-notes.md](supply-chain-security-notes.md)).

## 9. Sources

- All 18 lessons of [queues-laravel](https://laraveldaily.com/course/queues-laravel) —
  9 read live 2026-08-07, 9 from `raw/queues-laravel.json` (scraped 2026-08-08,
  premium-verified by canary). Newly read:
  [batches & chains](https://laraveldaily.com/lesson/queues-laravel/jobs-batch-chain-1),
  [batch progress](https://laraveldaily.com/lesson/queues-laravel/progress-batch-1),
  [testing queues](https://laraveldaily.com/lesson/queues-laravel/testing-queues-queue-fake-bus-fake-withfakequeueinteractions),
  [delay & release](https://laraveldaily.com/lesson/queues-laravel/delay-jobs-condition-1),
  [skip middleware](https://laraveldaily.com/lesson/queues-laravel/skip-processing-job),
  [queue:listen](https://laraveldaily.com/lesson/queues-laravel/queue-listen-local),
  plus intro lessons 1–3.
- [laravel-queues-server](https://laraveldaily.com/course/laravel-queues-server) — stale, §6.
- Framework source at v13.23.0: `Queue/Worker.php`, `Queue/Attributes/` (13 files),
  `Queue/Middleware/Skip.php:21` + directory listing,
  `Support/Testing/Fakes/BusFake.php:355,508`, `Queue/InteractsWithQueue.php:92,219`;
  `php artisan list` for `make:queue-batches-table`.
- This repo, measured 2026-08-08: `app/Jobs/*`, queue/Horizon config, `.env.example`
  (git-clean since first audit); greps for `Queue::fake` (12 hits, 6 files),
  `assertPushed` forms, `imports-exports` assertions, `Batchable` (0), `job_batches`
  migration (absent).

### What I could not obtain

- **Production configuration** (§7b): Forge Horizon daemon Stop Seconds and the deploy hook.
- Nothing else — the course is fully read.
