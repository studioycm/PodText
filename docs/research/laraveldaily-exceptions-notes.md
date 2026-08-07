# LaravelDaily: Handling Exceptions and Errors in Laravel 12 — course notes

Notes on [Handling Exceptions and Errors in Laravel 12](https://laraveldaily.com/course/exceptions-errors-laravel)
(8 text lessons, 30 min, **Mar 2025**), read 2026-08-07. Three lessons read in full.

**Short version: nothing here changes anything in this repo, and one of its code samples is
broken.** That second part is the reason this note is worth having.

---

## 0. Staleness verdict: **mixed — the API is current, but the sample code is not trustworthy**

Mar 2025, Laravel 12-era. The API surface it teaches is still correct in Laravel 13:
`bootstrap/app.php`'s `->withExceptions()` closure, `$exceptions->context()`,
`php artisan make:exception`. Nothing renamed or removed.

But the age shows in a different way from usual. One log sample in the global-handler lesson is
stamped **2023**-05-21, meaning that lesson's screenshots predate the Laravel 12 title by at
least two years — the course was re-badged for a new version without its examples being
re-run. That is worth knowing as a general signal about this particular course, and it is
borne out by §2.

---

## 1. What the lessons say

| lesson | substance |
| --- | --- |
| Custom exceptions | `php artisan make:exception ImportHasMalformedData`. The argument is the good one: classes beat magic strings because a typo in a class name is caught by PHP and the IDE, whereas a typo in `'NOT_READABLE'` is not. Also improves what Sentry/Flare/Bugsnag capture. |
| DB transactions + try/catch | Wrap multi-step writes so a partial failure rolls back; per-row transactions inside an import loop so one bad row does not abort the rest. **See §2 — the sample code is wrong.** |
| Global handler | `->withExceptions()` supports per-type rendering, `$exceptions->context(fn () => ['user' => auth()->user()])` to enrich every logged exception, ignoring specific exception types, and per-exception rendered responses. |

The custom-exception argument (classes over strings) is sound and unglamorous, and it is
already the practice here — see §3.

---

## 2. The transaction sample is broken, in two distinct ways

The lesson's final code:

```php
public function import($data)
{
    foreach ($data as $row) {
        DB::transaction(function () use ($data) {
            DB::beginTransaction();

            try {
                $date = $this->importData($row);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                // Log the error
            }
        });
    }
}
```

**First: it does not run.** The closure imports `$data`, but the body uses `$row`. `$row` is
not in the `use` clause and PHP closures do not capture by scope, so this is an undefined
variable on every iteration.

**Second: even with `use ($row)` fixed, the transaction logic is self-defeating.**
`DB::transaction($callback)` *already* opens a transaction, commits on return, and rolls back
on a thrown exception. Opening a manual `DB::beginTransaction()` inside it creates a nested
savepoint, and — more importantly — the `catch` **swallows** the exception. `DB::transaction()`
therefore sees a clean return and commits the outer transaction. The wrapper is at best
redundant and at worst misleading about what is protected.

The correct forms are one or the other, not both:

```php
// Closure form — commit/rollback handled for you. Let the exception escape,
// catch it outside if you want to record the failed row.
foreach ($data as $row) {
    try {
        DB::transaction(fn () => $this->importData($row));
    } catch (Throwable $e) {
        report($e);   // record the row that failed, then continue
    }
}
```

```php
// Manual form — you own begin/commit/rollback. No outer DB::transaction().
foreach ($data as $row) {
    DB::beginTransaction();
    try {
        $this->importData($row);
        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        report($e);
    }
}
```

This is the sharpest single reason to keep applying the sniff test to *content* and not only
to package names and version numbers. The lesson's prose is correct and the API it names is
current; the code below the prose does not work. **A published, paid, recently-re-versioned
lesson is not a substitute for reading what you are about to copy.**

Relevant here because PodText has **12 files using `DB::transaction` / `DB::beginTransaction`**,
including import paths — exactly the shape this lesson is about. If anyone reaches for this
lesson as a reference while touching those, they would be copying a bug.

---

## 3. PodText's posture — already past this course

Measured:

- **`bootstrap/app.php`'s `withExceptions()` is substantially built out**, well beyond
  anything the lesson shows: `shouldRenderJsonWhen()` scoped to `api/*`, a typed
  `$exceptions->render()` for `TokenMismatchException` that re-renders the maintenance form
  with a CSRF-retry message instead of a generic 419, and a `$exceptions->respond()` hook. The
  lesson's material is a subset.
- **Custom exception classes exist and are domain-named**, which is what the lesson asks for:
  `ExternalImageRejectedException`, `ExternalImageUnavailableException`,
  `UnresolvableMediaIdentityException`, `OwnerImageChangedException`,
  `UnsafeLegacyOwnerMediaException`, `CardTemplateWriteException`, `BackfillException`.
- **There is no `app/Exceptions/` directory** — exceptions live next to the code that throws
  them (`app/Support/Media/`, `app/Auth/LegacyRoleBackfill/`, …) rather than in one bucket.
  That is a deliberate co-location choice and is *more* organised than the lesson's
  `make:exception` default, not less. Nothing to change.

### The one idea it offered, and why it does not survive checking

The lesson proposes `$exceptions->context(fn () => ['user' => auth()->user()])` to enrich every
logged exception. PodText does not do this. **It should not start.**

Laravel already logs the authenticated user id by default —
`Illuminate\Foundation\Exceptions\Handler::context()` (`:661-669`):

```php
protected function context()
{
    try {
        return array_filter(['userId' => Auth::id()]);
    } catch (Throwable) {
        return [];
    }
}
```

So the id is already there. The lesson's change does not *add* identification — it **replaces
`userId` with the entire serialised `User` model**: name, email, `email_verified_at`,
timestamps, written into the log on every error. That is personal data in log files, in
exchange for nothing.

The lesson's own screenshots make the point unintentionally. Its "before" sample already reads
`{"userId":1,"exception":…}` — Laravel doing this correctly — and the "after" sample replaces
that with the full model dump, presented as the improvement.

**Verdict: nothing to adopt from this lesson.** Recorded so the idea is closed rather than
left as an open "maybe".

---

## 4. Applies here vs. generic

| lesson | applies to PodText? |
| --- | --- |
| Custom exceptions | **Already done**, and better organised than the lesson's default. |
| DB transactions + try/catch | **No** — and the sample is broken, §2. Flagged so it is not used as a reference. |
| Global exception handler | **Already exceeded**, and its one suggestion is a regression — §3. |

## 5. Sources

- [Course index](https://laraveldaily.com/course/exceptions-errors-laravel), Mar 2025, 8 text lessons.
  Read: [creating custom exceptions](https://laraveldaily.com/lesson/exceptions-errors-laravel/creating-custom-exceptions),
  [try-catch and DB transactions](https://laraveldaily.com/lesson/exceptions-errors-laravel/06-try-catch-db-transactions),
  [global exception handler](https://laraveldaily.com/lesson/exceptions-errors-laravel/laravel-global-exception-handler).
- This repo, measured 2026-08-07: `bootstrap/app.php` `withExceptions()` block; custom
  exception class inventory; `DB::transaction` / `DB::beginTransaction` file count (12).

### What I could not obtain / did not read

- 5 of 8 lessons: `why-try-catch-exception-open-source-examples`,
  `catching-specific-exceptions`, `log-exceptions-return-messages`, `exceptions-vs-php-errors`,
  `handling-exceptions-laravel-api`. Given §2 and §3, further reading looked unlikely to repay
  the time — a judgement call, not a measurement.
Nothing else was left open: the `userId` question raised while writing §3 was resolved against
`Illuminate\Foundation\Exceptions\Handler::context()` and is recorded there.
