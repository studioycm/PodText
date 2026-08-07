# LaravelDaily: Queues — course notes + PodText audit

Notes on two courses, read 2026-08-07:

- **[Queues in Laravel 13](https://laraveldaily.com/course/queues-laravel)** — 18 text lessons,
  1h12m, **Mar 2026**. Current.
- **[Practical Laravel Queues on Live Server](https://laraveldaily.com/course/laravel-queues-server)** —
  7 text lessons, 42 min, **May 2023**. Stale; spot-checked and stopped — §5.

All API claims re-verified against the installed `laravel/framework v13.23.0`. The PodText
audit in §3 is measured against `app/Jobs/`, `config/horizon.php`, `config/queue.php` and
`.env.example` as they stand today.

**Headline: PodText's queue code is already aligned with almost everything the Mar 2026
course recommends** — in several places ahead of it. Two small gaps and one unverified
production setting are in §4.

---

## 0. Staleness verdicts

### Queues in Laravel 13 (Mar 2026) — **passes**

The strongest single marker is that it teaches the **Laravel 13 queue attributes**, which are
genuinely new. Verified — `vendor/laravel/framework/src/Illuminate/Queue/Attributes/`
contains 13 of them:

`Backoff` · `Connection` · `DebounceFor` · `Delay` · `DeleteWhenMissingModels` ·
`FailOnTimeout` · `MaxExceptions` · `Queue` · `ReadsQueueAttributes` · `Timeout` · `Tries` ·
`UniqueFor` · `WithoutRelations`

The course uses the modern single-trait job shape (`Illuminate\Foundation\Queue\Queueable`),
constructor property promotion, and `readonly` — all Laravel 11+ idiom. Nothing pre-11 leaks
through.

Coverage gap rather than rot: it teaches `#[Tries]`, `#[Backoff]`, `#[UniqueFor]`,
`#[WithoutRelations]` and `#[DeleteWhenMissingModels]` — five of the thirteen. `#[DebounceFor]`,
`#[MaxExceptions]`, `#[Timeout]`, `#[FailOnTimeout]`, `#[Delay]`, `#[Queue]`, `#[Connection]`
go unmentioned.

### One claim that is imprecise, and it matters — §2.

---

## 1. What the course teaches, condensed

Only the parts with a decision in them.

| topic | the useful part |
| --- | --- |
| Retries | `$tries` property, `tries()` method, or `#[Tries(5)]`. `#[Backoff(60, 120, 300)]` gives escalating delays — the point being to give a flapping external service time to recover instead of hammering it. |
| `failed()` | Runs *before* Laravel's own error logging. Real uses: notify admins, **clean up partial data** (temp files, half-created records), `report($exception)` to Sentry/Flare. |
| Timeouts | Default 60 s. `$timeout` per job. **Prefer many small jobs over one big one** — smaller payloads, less to lose, faster recovery. |
| Idempotency | A retried job re-runs work that already succeeded. Guard the mutation (`if (is_null($read_at))`), not just the job. |
| Missing models | Pass **IDs, not models**, and guard existence — or use `#[DeleteWhenMissingModels]` to have the job silently dropped rather than throwing `ModelNotFoundException`. |
| Unique jobs | `ShouldBeUnique` locks on **the class name by default** — so a second *different* user's job gets dropped too. Always implement `uniqueId()`. `#[UniqueFor(60)]` bounds the lock. `ShouldBeUniqueUntilProcessing` releases it when a worker picks the job up rather than when it finishes. |
| Priority queues | `->onQueue('priority')` plus `--queue=priority,default`. Strictly ordered: `default` starves until `priority` is empty. |
| Supervisor / Horizon | With Horizon, Supervisor runs **one** process (`artisan horizon`, `numprocs=1`); Horizon manages workers from version-controlled `config/horizon.php`. Deploy hook is `horizon:terminate`, not `queue:restart`. |

The `ShouldBeUnique` class-name-lock gotcha is the single most valuable item — it is a silent
data-loss bug, and it is not obvious from the interface name.

---

## 2. Correction: "a timed-out job is silently killed" is not accurate

The course states:

> By default, a timed-out job is silently killed by the worker. Adding `$failOnTimeout = true`
> makes the job explicitly fail — recorded in `failed_jobs`, `failed()` called […] This is
> almost always what you want.

Measured in `vendor/laravel/framework/src/Illuminate/Queue/Worker.php`. The `SIGALRM` handler
(`registerTimeoutHandler`, `:293-310`) calls three things in order:

```php
$this->markJobAsFailedIfWillExceedMaxAttempts($conn, $job, (int) $options->maxTries, $e);
$this->markJobAsFailedIfWillExceedMaxExceptions($conn, $job, $e);
$this->markJobAsFailedIfItShouldFailOnTimeout($conn, $job, $e);
```

and the first of those (`:665-675`) is:

```php
$maxTries = ! is_null($job->maxTries()) ? $job->maxTries() : $maxTries;
if (! $job->retryUntil() && $maxTries > 0 && $job->attempts() >= $maxTries) {
    $this->failJob($job, $e);
}
```

So the accurate statement is:

- A job that times out **on its last permitted attempt is already failed properly** —
  `failed_jobs` row written, `failed()` called — with no `failOnTimeout` needed. For a
  `tries = 1` job that is *every* timeout.
- `$failOnTimeout = true` (or `#[FailOnTimeout]`) changes something narrower: it fails the job
  **immediately on the first timeout, regardless of attempts remaining**, instead of releasing
  it for retry.

"Silently killed" is therefore wrong as stated, and the correction changes an action item:
it removes what looked like a real gap in this repo (§3, `ExportContentImagesZip`). Worth
adding `#[FailOnTimeout]` only where a timeout should *not* be retried — not as a blanket rule.

Also verified, because it governs how `config/horizon.php` and job classes interact:
**a job's own `$tries` overrides the worker/Horizon setting** (`$maxTries = ! is_null($job->maxTries()) ? … `).

---

## 3. PodText audit — measured

### Configuration

| item | value | verdict |
| --- | --- | --- |
| `queue.default` | `redis` | matches the course's recommendation |
| Horizon supervisor-1 queues | `default`, `imports-exports` | separated by workload |
| Horizon `tries` | `1` | fallback only — overridden per job, §2 |
| Horizon `timeout` | `1850` (`HORIZON_SUPERVISOR_TIMEOUT`) | — |
| `queue.connections.redis.retry_after` | `1900` (`REDIS_QUEUE_RETRY_AFTER`) | **correct ordering** |

The `retry_after` > worker-`timeout` ordering is the classic double-execution trap: if
`retry_after` fires first, Redis re-queues a job that is still running. **This repo already
gets it right and documents the coupling in `.env.example`:**

```
# COUPLING: REDIS_QUEUE_RETRY_AFTER must stay ABOVE HORIZON_SUPERVISOR_TIMEOUT
```

The Mar 2026 course never mentions this coupling at all. PodText is ahead of it here.

### Jobs

Four jobs. Note the first grep I ran for `public $tries` found nothing and would have produced
a false "no retries configured" finding — **this repo uses typed properties (`public int $tries`)**,
which that pattern misses. Corrected inventory:

| | `Download…GroupImage` | `Download…ItemImage` | `ExportContentImagesZip` | `SettingsBackupSnapshotJob` |
| --- | --- | --- | --- | --- |
| `$tries` | 3 | 3 | 1 | 1 |
| `$timeout` | 120 | 120 | 1800 | config, default 1800 |
| `backoff()` | `[30, 120]` | `[30, 120]` | — | — |
| `ShouldBeUnique` + `uniqueId()` | yes | yes | — | — |
| `$uniqueFor` | 600 | 600 | — | — |
| `middleware()` | `RateLimited` | `RateLimited` | — | — |
| `ShouldQueueAfterCommit` | yes | yes | yes | yes |
| `failed()` | yes | yes | yes | **no** |
| passes IDs not models | yes | yes | yes | yes |
| guards missing models | yes | yes | — | — |
| explicit `onQueue()` | yes | yes | yes | — |

Mapped against the course's lessons, PodText already implements: retries with escalating
backoff (lesson 4), `failed()` (5), per-job timeouts (6), idempotency via ID-passing plus
existence guards (7), `ShouldBeUnique` **with** a real `uniqueId()` and a bounded `$uniqueFor`
(11), and dedicated queues (14). It adds two things the course does not cover at all:
`ShouldQueueAfterCommit` on every job, and `RateLimited` middleware on the outbound-HTTP jobs.

`uniqueId()` on the image jobs returns `contentItemId : sha256(expectedUrl)` — so re-dispatch
after the URL changes is correctly *not* deduplicated. That is a level of care beyond the
course's `return (string) $this->user->id`.

---

## 4. Actual gaps

Three, all small. Nothing here is urgent.

### 4a. `SettingsBackupSnapshotJob` has no `failed()`, and stuck snapshots have no reconciler

The job loops snapshots and catches per-snapshot exceptions, marking that snapshot `FAILED`
and continuing — good. But if the **whole job** dies (timeout at 1800 s, worker OOM at the
128 MB Horizon memory limit, deploy restart), every snapshot it had not reached yet stays
`STATUS_PENDING`.

Measured: `STATUS_PENDING` is written in `SettingsBackupSnapshotManager` (`:83`, `:97`, `:278`)
and read nowhere that would clear a stranded one. There is no sweeper.

With `tries = 1`, a timeout *does* record the job in `failed_jobs` (§2) — so the failure is
visible in Horizon. But nothing marks the orphaned snapshot rows, so the backup silently
appears to be still in progress.

**Proposal:** add a `failed(Throwable $e)` to
[SettingsBackupSnapshotJob.php](app/Jobs/SettingsBackupSnapshotJob.php) that marks the
still-pending snapshots for `$this->backupId` as failed with the exception message, mirroring
the per-snapshot handler already in `handle()`. Roughly ten lines, and it makes the existing
in-loop error handling total rather than partial.

### 4b. Forge's Horizon-daemon stop timeout is unverified against a 1800 s job

This is the one durable item recovered from the stale 2023 course (§5). Forge's Horizon daemon
has a **Stop Seconds** setting, and Forge's own guidance is that it must exceed the
longest-running job — otherwise Supervisor kills a job mid-flight on deploy.

PodText's longest jobs are `ExportContentImagesZip` and `SettingsBackupSnapshotJob` at
**1800 s each**. So Forge's Horizon daemon Stop Seconds must be **> 1800**. Not verified —
that is a production console setting, not a repo file, and I did not reach into production
from a research session.

This is a third term in a coupling this repo already tracks two-thirds of:

```
job $timeout (1800)  <  HORIZON_SUPERVISOR_TIMEOUT (1850)  <  REDIS_QUEUE_RETRY_AFTER (1900)
                     ... and Forge daemon Stop Seconds must also exceed the job timeout
```

**Proposal:** check it once, and if it is right, add the Forge value to the existing
`# COUPLING:` comment in `.env.example` so the whole chain is documented in one place.

### 4c. Queue attributes are unused (cosmetic)

Every job uses properties and methods rather than the Laravel 13 attributes. This is purely
stylistic — properties are not deprecated, and `backoff()` as a method is arguably clearer
than `#[Backoff(30, 120)]`. **No action recommended.** Noted only so it is a decision rather
than an oversight.

---

## 5. The May 2023 companion course — stale, stopped, one item kept

Spot-checked two lessons. Three independent staleness markers, all in visible code:

- Forge supervisor config shows `php8.1` and `queue:listen` — this repo is on PHP 8.4, and
  Forge generates `queue:work` now.
- The job stub uses the **pre-Laravel-11 four-trait shape**
  (`Illuminate\Bus\Queueable`, `Foundation\Bus\Dispatchable`, `Queue\InteractsWithQueue`,
  `Queue\SerializesModels`) instead of the single `Foundation\Queue\Queueable`.
- `protected $user;` with manual constructor assignment, no promotion.

Per the operator's rule (anything ≤ Dec 2024 is stale by default), **stopped there** rather
than reading the remaining five lessons. That is the result, not a failure.

**One item survives and is worth keeping** — it is ops guidance, which ages differently from
framework API, and it is what §4b is built on:

> Ensure Graceful Shutdown Seconds is greater than Maximum Seconds Per Job. Otherwise
> Supervisor may kill the job before it is finished processing.

plus the Horizon-daemon form of the same rule (Stop Seconds > longest job), and the detail
that a Forge Horizon daemon runs with **processes = 1** because Horizon manages its own
children.

---

## 6. The bonus "AI skill" — noted, not installed

Lesson 18 of the Mar 2026 course ships a `laraveldaily-queues-audit` skill (a `SKILL.md`)
intended to be dropped into `~/.claude/skills/` or a project's `.claude/skills/`, from
[LaravelDaily/AI-Workflows-For-Laravel](https://github.com/LaravelDaily/AI-Workflows-For-Laravel).
It codifies the course's recommendations as an automated scan: unqueued long operations,
listeners/mailables/notifications not implementing `ShouldQueue`, missing `failed()`, missing
idempotency guards, whole-model payloads, and so on.

**Not installed, and I recommend against installing it as-is.** Three reasons:

1. The author says so himself: *"Those are NOT strict rules, many of them are personal
   preferences."*
2. Installing it means fetching a `curl`-ed file from a third-party GitHub repo straight into
   an agent's instruction path — which is precisely the trust model the security course in
   [laraveldaily-supply-chain-security-notes.md](docs/research/laraveldaily-supply-chain-security-notes.md)
   argues against. A skill file is executable instruction surface, not documentation.
3. §3 shows its findings would be mostly already-satisfied here.

Recorded because the *pattern* is worth knowing — courses shipping agent skills alongside
lessons is new as of 2026 and will recur.

---

## 7. Applies here vs. generic

| lesson group | applies to PodText? |
| --- | --- |
| Basics, mailables, dispatching (01-03) | No — long settled here |
| Failures, `failed()`, timeouts (04-06) | **Partly** — one gap, §4a. Plus the §2 correction |
| Idempotency, unique jobs (07, 11) | Already implemented, more carefully than the course |
| `queue:listen` locally, skip, delay (08-10) | Generic |
| Batches and chains (12-13) | **Unread.** Possibly relevant to bulk image export — see below |
| Priority queues (14) | Already implemented (`default` / `imports-exports`) |
| Redis + Horizon (15-16) | Already the setup; Horizon config is version-controlled |
| Testing queues (17) | **Unread** — see below |
| AI skill (18) | Noted, declined, §6 |

---

## 8. Sources

- [Queues in Laravel 13](https://laraveldaily.com/course/queues-laravel), Mar 2026. Lessons read in full:
  [failed jobs & retrying](https://laraveldaily.com/lesson/queues-laravel/failed-job-restarting-queue-1-1),
  [the failed() method](https://laraveldaily.com/lesson/queues-laravel/method-failed-1),
  [long-running jobs & timeouts](https://laraveldaily.com/lesson/queues-laravel/long-running-jobs-timeouts-1),
  [idempotent jobs](https://laraveldaily.com/lesson/queues-laravel/idempotent-jobs-1),
  [unique jobs](https://laraveldaily.com/lesson/queues-laravel/unique-jobs),
  [multiple queues & priority](https://laraveldaily.com/lesson/queues-laravel/multiple-queues-priority-1),
  [Redis & Horizon](https://laraveldaily.com/lesson/queues-laravel/drivers-redis-horizon),
  [Supervisor & Horizon](https://laraveldaily.com/lesson/queues-laravel/supervisor-multiple-queue-workers-and-horizon-again),
  [bonus AI skill](https://laraveldaily.com/lesson/queues-laravel/bonus-ai-skill-laraveldaily-queues-audit).
- [Practical Laravel Queues on Live Server](https://laraveldaily.com/course/laravel-queues-server), May 2023.
  Spot-checked: [Forge queues](https://laraveldaily.com/lesson/laravel-queues-server/laravel-forge-queues),
  [queue spikes & scaling](https://laraveldaily.com/lesson/laravel-queues-server/queue-spikes-scaling). Stopped — §5.
- Vendor source read directly: `Illuminate/Queue/Worker.php` (timeout handler, `maxTries`
  precedence, `failJob` conditions), `Illuminate/Queue/Queue.php` (`failOnTimeout` default
  `false`), `Illuminate/Queue/Attributes/` (the 13 attributes).
- This repo, measured 2026-08-07: `app/Jobs/*.php`, `config/horizon.php`, `config/queue.php`,
  `.env.example`, `SettingsBackupSnapshotManager.php`.

### What I could not obtain

- **Five of the eighteen lessons went unread**: batches and chains (12), batch progress (13),
  testing queues with `Queue::fake` / `Bus::fake` / `withFakeQueueInteractions` (17), plus the
  intro lessons and `queue:listen` locally. Lesson 17 is the one worth a follow-up, since this
  repo has 1850 tests and none of them were checked against its recommendations in this pass.
  Lessons 12-13 matter only if bulk image export ever needs progress reporting.
- **Production configuration**: the Forge Horizon daemon's Stop Seconds (§4b), and whether the
  deploy script runs `horizon:terminate`. Both unverified — not reached into from a research
  session.
- **The full text of the bonus skill** — only the portion rendered in the lesson page was read;
  the GitHub raw file was deliberately not fetched (§6).
