# LaravelDaily: Handling Exceptions and Errors in Laravel 12 — course notes

- **URL**: https://laraveldaily.com/course/exceptions-errors-laravel — 8 lessons, 30 min, text, **Mar 2025**
- **Read**: 2026-08-07 (3 lessons, live) + 2026-08-08 (remaining 5, from `raw/`) — **8/8, complete**
- **Verified against**: `laravel/framework v13.23.0`; this repo's exceptions and handler
- **Staleness verdict**: **mixed** — the APIs it names are current, but one code sample is
  broken, one screenshot is stamped 2023 under a "Laravel 12" title, and its one handler
  suggestion is a regression against Laravel's default. Trust the prose, not the samples.

Full rewrite of the first version (3/8 lessons). Everything kept was re-verified; the new
material is §3–§5.

**Short version: PodText is already past this course on every axis it covers, and the most
useful things in it are two author remarks in the comment threads, not the lessons.**

---

## 1. Kept finding: the transaction sample is broken, in two ways

Lesson 6's final code wraps `DB::beginTransaction()` *inside* `DB::transaction()` and
swallows the exception:

```php
DB::transaction(function () use ($data) {   // imports $data...
    DB::beginTransaction();
    try {
        $date = $this->importData($row);    // ...but uses $row — undefined variable
        DB::commit();
    } catch (Exception $e) {
        DB::rollBack();
    }
});
```

- **It does not run**: the closure imports `$data` but the body uses `$row`; PHP closures do
  not capture by scope.
- **Even fixed, the logic defeats itself**: `DB::transaction()` already commits on return and
  rolls back on a thrown exception. The inner manual transaction plus a swallowing `catch`
  means the outer wrapper always sees a clean return and commits.

Correct forms are one *or* the other — closure form with the try/catch **outside**, or fully
manual begin/commit/rollback with no outer wrapper. Flagged because this repo has 12 files
using `DB::transaction`/`beginTransaction`, exactly the shape someone might "check against the
course" while touching.

## 2. Kept finding: the global-handler suggestion is a regression

Lesson 7 proposes `$exceptions->context(fn () => ['user' => auth()->user()])`. Laravel already
logs the user id by default (`Foundation/Exceptions/Handler.php:661-669` returns
`['userId' => Auth::id()]`), so the lesson's change **replaces** an id with the entire
serialised `User` model — name, email, timestamps — in the log line of every error. Personal
data in logs, in exchange for nothing. The lesson's own before/after screenshots show
`"userId":1` being replaced by the full dump, presented as the improvement.

---

## 3. The newly read lessons — what survives verification

### Catching specific exceptions (lesson 2) — the course's best material

- Catch the **specific** exception first, the general one last; multiple `catch` blocks give
  each failure its own handling.
- **Union catch** — `catch (InvalidManipulation|FileNotFoundException $e)` — when two
  exceptions share handling.
- A `catch` is not a method exit: their avatar example logs the resize failure and **continues
  uploading the original**. Recovery, not abort.

### Chaining the previous exception (lesson 4) — one real practice

When wrapping a low-level exception in a domain one, pass it as the third constructor
argument (`$previous`) — trackers then show the full chain instead of only the wrapper:

```php
throw new ImportHasMalformedData('The import has malformed data.', 500, $exception);
```

**PodText already does this, verified**: the media exceptions take
`?Throwable $previous = null` and forward it —
`parent::__construct($message, previous: $previous)`
(`app/Support/Media/ExternalImageRejectedException.php:14-16` and siblings). Nothing to adopt;
recorded as confirmed-current practice.

### PHP `\Error` vs `\Exception` (lesson 5) — correct, but misses the modern answer

Accurate as far as it goes: `\Error` (typos, bad arity, division by zero) does not extend
`\Exception`, so `catch (\Exception $e)` misses it; the lesson adds a second
`catch (\Error $e)` block, down to specific errors like `ArgumentCountError`.

**What it never mentions is `\Throwable`** — the common interface of both since PHP 7, and the
single-catch answer to the exact problem the lesson solves with two blocks. This repo already
catches `Throwable` as standard practice (all three jobs with catches, the maintenance
handler, etc.). The lesson's closing "only do this if you understand the implications" caution
is right; the mechanism it teaches is simply the long way around.

### API exceptions (lesson 8) — sound instincts, superseded framing

Its two good points: Laravel's default `ModelNotFoundException` message leaks the model
FQCN and the probed id to API clients, and a global `render(NotFoundHttpException …)`
override is too broad — it also rewrites genuine route-404s. Its answer is controller-level
try/catch per exception type with per-type status codes.

For PodText this is context, not guidance: the API surface is thin, JSON rendering is already
scoped (`shouldRenderJsonWhen(fn => $request->is('api/*'))`), and the maintenance-form CSRF
retry shows the repo already uses the *typed* `$exceptions->render()` form the lesson works
around.

### Open-source examples (lesson 1)

Bagisto (flash + log + fallback response) and Twill (fallback value in `catch`). Fine as
patterns; nothing to act on.

---

## 4. The comment threads carry the two most useful items in the course

Both from the author's replies, neither in any lesson body:

1. **When to wrap at all** — his rule: ask "will the flow break, and do I handle it
   correctly?", and concretely: *"I would not cover retrieving operations with it, unless
   it's crucial. But I would cover majority of insert/update ones!"* Wrap writes, not reads.
   That is a sharper default than anything in the lessons.
2. **A question the framework answers better than the author did.** A reader hits
   `Argument #1 ($id) must be of type int, string given` from `/api/v1/users/3dd`. The
   author suggests per-route regex and says he knows no better way short of middleware.
   The framework ships both better ways, verified in 13.23.0: `Route::pattern('id', '[0-9]+')`
   globally (`Routing/Router.php:1220`) and `->whereNumber('user')` per route
   (`CreatesRegularExpressionRouteConstraints.php:39`). Recorded as the standing reminder
   that author replies age like lessons do — check the framework before adopting an answer
   from a thread.

---

## 5. PodText posture — unchanged, already past the course

- `withExceptions()` in `bootstrap/app.php` is built out beyond anything shown: scoped JSON
  rendering, typed `render()` for `TokenMismatchException` re-rendering the maintenance form,
  a `respond()` hook.
- Custom exceptions are domain-named, co-located with their throwers, and chain `$previous`
  (§3). No `app/Exceptions/` bucket — deliberate, and finer-grained than the course's
  `make:exception` default.
- `catch (Throwable)` is the house norm — ahead of lesson 5.
- The author's wrap-writes-not-reads rule (§4) matches existing practice: the 12
  transaction sites wrap multi-step writes.

**Nothing to adopt. No proposals.**

---

## 6. Sources

- All 8 lessons of [exceptions-errors-laravel](https://laraveldaily.com/course/exceptions-errors-laravel):
  [why try-catch + open-source examples](https://laraveldaily.com/lesson/exceptions-errors-laravel/why-try-catch-exception-open-source-examples),
  [catching specific exceptions](https://laraveldaily.com/lesson/exceptions-errors-laravel/catching-specific-exceptions),
  [custom exceptions](https://laraveldaily.com/lesson/exceptions-errors-laravel/creating-custom-exceptions),
  [log + return messages](https://laraveldaily.com/lesson/exceptions-errors-laravel/log-exceptions-return-messages),
  [exceptions vs PHP errors](https://laraveldaily.com/lesson/exceptions-errors-laravel/exceptions-vs-php-errors),
  [try-catch + DB transactions](https://laraveldaily.com/lesson/exceptions-errors-laravel/06-try-catch-db-transactions),
  [global handler](https://laraveldaily.com/lesson/exceptions-errors-laravel/laravel-global-exception-handler),
  [API exceptions](https://laraveldaily.com/lesson/exceptions-errors-laravel/handling-exceptions-laravel-api) —
  3 live 2026-08-07, 5 from `raw/exceptions-errors-laravel.json` (2026-08-08), including the
  16-comment thread on the API lesson.
- Framework source at v13.23.0: `Foundation/Exceptions/Handler.php:661-669`,
  `Routing/Router.php:1220`, `Routing/CreatesRegularExpressionRouteConstraints.php:39`.
- This repo: `bootstrap/app.php` `withExceptions()`; custom exception inventory;
  `ExternalImageRejectedException.php:14-16` (previous-chaining); `catch (Throwable` greps;
  `DB::transaction` file count (12).

### What I could not obtain

Nothing — the course is fully read, comments included.
