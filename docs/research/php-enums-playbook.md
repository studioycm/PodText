# PHP Enums Playbook — abilities, patterns, rules

A standing reference for enum work in this app. Read it before adding an enum,
before adding a method to one, and before deciding that something an enum
"knows" can be used in a query.

**Status:** research. Nothing here is a spec; the rules section is the part
meant to bind.

---

## Where this came from, and what was actually obtainable

| Source | What it gave | Honesty note |
| --- | --- | --- |
| `studioycm/FilamentExamples` → `v4/full-projects/construction` | **20 enums, ~584 files, every call site.** The primary source. | Read in full from a sparse clone. |
| FilamentDaily — *PHP Enums in Filament: Practical Example* (`8DJpB0LMv8I`, 3:43, Jul 2026) | Its description names its source project: the construction system above. | **Transcript unobtainable.** |
| Laravel — *Supercharge Your Laravel App with Enums* (`eeQVOXgQg88`, 19:54, Sep 2025) | Chapter list: Enum Basics → Using Enums → Enum Casting → Enum Route Binding → Enum Validation. | **Transcript unobtainable.** Topics grounded in Laravel 13 docs instead. |
| Laravel Daily — *Reuse Enums Across Your Laravel/Filament App* (`ZzobbjfEJE8`, 8:22, Mar 2026) | Its own summary: *centralize colors, icons and labels in one enum file; reuse across models, controllers and Filament components.* Mentions FilaCheck. | **Transcript unobtainable.** No chapters. |
| Laravel 13 docs via Boost `search-docs` | Casting, `AsEnumCollection`, implicit enum route binding, `Rule::enum` with `only`/`except`/`when`. | Version-correct for this app. |
| Filament 5 docs via Boost `search-docs` (`docs/09-advanced/03-enums.md`) | The four contracts and every surface that auto-applies them. | Version-correct for this app. |

**On the video transcripts.** All three were attempted in two browsers, signed
in with Premium, through four routes: the "Show transcript" UI panel, the
InnerTube `get_transcript` API, and the `timedtext` endpoint in `json3`, `srv3`
and default formats with credentials. Every route returned the same thing — the
panel expands with zero segments, the API returns zero segments, the endpoint
returns **HTTP 200 with a zero-byte body**. The caption data is gated behind a
player-bound token. This is a server-side block, not a technique that was
missed. **No transcript text was obtained, and none is quoted or paraphrased
here.** What the videos contributed is their titles, chapter lists and
descriptions — from which their topics are known exactly — plus, in the
FilamentDaily case, the source project, which turned out to be worth more than
the transcript would have been.

---

## 1. What an enum can actually do (PHP 8.4)

| Ability | Notes |
| --- | --- |
| Implement interfaces | The whole basis of the Filament contracts below. |
| Hold methods | Instance methods (`$case->foo()`) and static ones (`Foo::bar()`). |
| Hold constants | Including `self::` references to cases. |
| Use traits | Traits may not declare properties. |
| Carry attributes | On the enum and on individual cases. |
| `cases()`, `from()`, `tryFrom()` | `from()` throws `ValueError` on an unknown value — see §6. |
| Be a `match` subject | Identity comparison; exhaustive `match` fails loudly on a new case. |

| Cannot | Consequence |
| --- | --- |
| Hold state / properties | A case is a singleton. Anything "per-record" belongs on the model. |
| Be instantiated or extended | No inheritance hierarchies of enums. |
| Be used in SQL | **The one that matters.** See §5. |

---

## 2. Laravel's integration points

```php
// Casting — the enum is the column's type from the model's side out.
protected function casts(): array
{
    return ['status' => PublicationStatus::class];
}

// Many enum values in one column.
'statuses' => AsEnumCollection::of(PublicationStatus::class),

// Implicit route binding — a bad segment is a 404, not an if-statement.
Route::get('/categories/{category}', fn (Category $category) => $category->value);

// Validation — and note only()/except()/when(), which most code forgets.
'status' => [Rule::enum(PublicationStatus::class)->only([...])],
Rule::enum(Status::class)->when($user->isAdmin(), fn ($r) => $r->only(...), fn ($r) => $r->only(...));
```

Filament's forms wrap the same rule as `->enum(MyStatus::class)`.

> `null` is never cast. A nullable enum column hands back `null`, not a case —
> so every `match` over it needs a null path or a `?->`.

---

## 3. Filament's four contracts, and where they fire by themselves

`Filament\Support\Contracts\` — `HasLabel`, `HasColor`, `HasIcon`,
`HasDescription`.

Implement them and these surfaces stop needing per-surface closures:

| Contract | Fires automatically in |
| --- | --- |
| `HasLabel` | `Select`, `CheckboxList`, `Radio`, `ToggleButtons`, `SelectColumn`, `SelectFilter` — all via `->options(Status::class)`; `TextColumn`/`TextEntry` on a cast attribute |
| `HasColor` | `TextColumn`/`TextEntry` (best with `->badge()`), `ToggleButtons` |
| `HasIcon` | `TextColumn`/`TextEntry` (best with `->badge()`), `ToggleButtons` |
| `HasDescription` | `Radio`, `CheckboxList` option descriptions |

This is the whole of the Laravel Daily video's argument, and it is the cheapest
win in the list: **`->options(Status::class)` instead of a hand-written option
array, `->badge()` instead of a `->color(fn ($state) => match (...))` closure.**
A colour written in a closure is a colour that will disagree with the next
surface someone builds.

Our position: 34 of 45 enums implement `HasLabel`; 11 implement no contract at
all (internal ones, mostly fine); 2 implement all four. The construction
project uses `HasDescription` **nowhere** — we are ahead of it there.

---

## 4. The behaviour patterns worth stealing

Every one of these is lifted from a real call site in the construction project.
It carries behaviour on **17 of its 20 enums** — this is not a labels-only
codebase, and the pattern names below are ours, for referring to them later.

**Predicate.** A yes/no question about a case, asked everywhere.
```php
public function countsTowardsProjectFinancials(): bool
{
    return in_array($this, [self::Approved, self::Sent, self::PartiallyPaid, self::Paid], strict: true);
}
```
Called from 8 services, 2 seeders and a test. One definition of "counts".

**Transition table.** A state machine that lives with the states.
```php
public function allowedTransitions(): array
{
    return match ($this) {
        self::Draft => [self::Submitted, self::Approved, self::Void],
        self::Paid  => [self::PartiallyPaid, self::Void],
        self::Void  => [],
    };
}
public function canTransitionTo(self $status): bool { /* in_array */ }
```
Consumed in three places at once, which is the point:
- **Policies** — `InvoicePolicy::submit()` is `$invoice->status->canTransitionTo(Submitted)`
- **Filament actions** — `->visible(fn ($record) => $record->status->canTransitionTo(Void))`
- **Services** — the write path re-checks it inside the lock

The button hides, the gate refuses, and the service throws, from one table.

**Cross-enum mapping.** `InvoiceType::paymentDirection(): PaymentDirection` and
`PaymentDirection::invoiceType(): InvoiceType` — a typed round trip instead of
two parallel `match`es that drift.

**Column-name mapping.** `ComplianceSubject::foreignKey(): string` returns
`'employee_id' | 'vendor_id' | 'project_id'`. A polymorphic-ish lookup with no
`morphMap`.

**Arithmetic.** `LeaveTransactionType::balanceSign(): int` returns `-1` for
usage, `1` otherwise, so the balance formula is one `sum` and no branch.

**Period key.** `NumberSequenceReset::periodFor(CarbonInterface $moment): string`
— note it **takes the moment as a parameter** rather than calling `now()`. An
enum that reads the clock is untestable and, as we have already learned the
hard way, wrong in production.

**Transition engine with an idempotency short-circuit.** Every public workflow
method is a one-line wrapper over one private
`transition(Model, User, StatusEnum $to, string $event)`, with the destination
enum as a **first-class parameter**. Three enum facts do work at once
(`ContractWorkflowService.php:61-92`): identity equality against the
already-locked row makes a retried transition a **no-op returning the existing
record** (`:67-69`); `canTransitionTo()` enforces the legal graph (`:89`); and
both `$from` and `$to` instances go to the logger (`:81`).

**Enum as an authorization routing table.** Rather than a per-transition
`authorize()` call, `match` on the *destination* case selects the Gate ability
name inside the `authorize()` argument itself, with
**`default => throw new LogicException`** as the exhaustiveness guard
(`ContractWorkflowService.php:71-76`). An unmapped destination is a programmer
error at the call site, never a silent permit or deny.

**Idempotency-tolerant policy.** The approve abilities read
`$record->status === Target || $record->status->canTransitionTo(Target)`
(`InvoicePolicy.php:65`, `ChangeOrderPolicy.php:59`). A double-clicked approve
or a retried job **authorizes cleanly instead of 403-ing**, and the service's
identity short-circuit then makes it a no-op. The plain form is used
everywhere else — the disjunction is deliberate and only on approve.

**Normalize a union at the boundary.** `ActivityLogger` accepts
`BackedEnum|string|null` for both from- and to-state, collapses both through
one private `scalar()` helper, and compares the normalized backing values to
decide **whether to write at all** (`ActivityLogger.php:29-48, 111-114`). That
is what makes the retry-safe transition produce exactly one history row per
real change. Kin of `boundary-type-loss` in the ledger.

**Enum method inside a `booted()` invariant.** `ComplianceRecord` may point at
exactly one of three FK columns; the `saving` hook asks the *requirement's*
enum which column it requires — `$requirement->applies_to->foreignKey()` — and
checks it against the map of non-null keys
(`ComplianceRecord.php:43-62`, call at `:58`). The polymorphic-subject
invariant is enforced at the model boundary with **no `match` in the model at
all**.

**Manual `getLabel()` outside Filament's pipeline.** `HasLabel` is normally
consumed implicitly, but it is dereferenced by hand in the two places Filament
never auto-applies it: raw Blade custom pages, and
`getGlobalSearchResultDetails()`, which is typed `array<string,string>` so an
enum instance would have to be stringified. There the manual call is
**required, not decorative** — and it turns the Filament contract into an
app-wide presenter.

**Enum cast on a custom Pivot model.** `->using(ProjectAssignment::class)` plus
`withPivot([...])` is the **only** way to get an enum cast onto
intermediate-table columns; plain `withPivot()` hands back a raw string. The
pivot class extends `Relations\Pivot` and declares `casts()` like any model.
⚠️ It comes with a trap the project itself fell into — see the anti-patterns
below.

**Capability matrix.** `Role::permissions(): array<Permission>` maps each role
to a list of `Permission` cases; `permissionNames()` flattens to strings for the
Spatie seeder; policies then ask `$user->can(Permission::VoidInvoices->value)`.
The permission list is a typed enum, so a typo is a fatal, not a silent denial.
Worth weighing against our `UserRole::rank()`/`isAtLeast()`.

---

## 5. The rule that matters most here

> **An enum answers questions about a case, in PHP. SQL only ever sees the
> stored value.**

You cannot `GROUP BY`, `ORDER BY`, `WHERE` or index a method. This is the
mistake that would have shipped green on SQLite and 500'd on MySQL.

The construction project's bridge, used verbatim in nine services:

```php
$included = array_map(
    fn (InvoiceStatus $s): string => $s->value,
    array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $s): bool => $s->countsTowardsProjectFinancials()),
);

Invoice::query()->whereIn('status', $included)->sum('total');
```

**PHP enumerates the cases; SQL receives literal column values.** The predicate
stays in one place, the query stays plain, and adding a case updates every
query that used it. Reach for this before writing a `CASE` expression or a raw
`whereRaw`.

### When the value really is derived

`ComplianceStatus` is computed from `expires_at` against a warning window — so
it cannot be a `whereIn` of anything. The project's answer is to **materialise
it**: `ComplianceStatusService::statusFor()` computes it, `refresh()` and
`verify()` write it to a real `status` column, and the Filament table then
filters and sorts that column like any other
(`SelectFilter::make('status')->options(ComplianceStatus::class)`).

The caveat is the honest half, and it is the same trap we hit with
`published_at` and the production clock: **a materialised derived value goes
stale on its own, without any write.** The project accepts that in the table
and recomputes at read time only where correctness matters — the reminder
service re-derives with `->filter(fn ($r) => $service->statusFor($r)->needsAttention())`
rather than trusting the column. Copy the pattern *and* the split, or don't
copy it.

---

## 6. Testing rules

From `tests/Feature/DomainEnumTest.php`, which is the best single artefact in
the whole project:

1. **Pin the case list against the schema.** A Pest dataset of
   `[EnumClass, ['expected', 'values']]` with `toEqualCanonicalizing`. If a
   migration comment and its enum drift apart, this is what fails.
2. **Assert the contracts hold for UI-facing enums** — every case returns a
   non-empty label and colour. Catches the case someone adds without a `match`
   arm.
3. **Round-trip through the cast** — create, `fresh()`, expect the case back.
4. **Assert the column stays a plain string** — `DB::table(...)->value('status')`
   is `'on_hold'`, not an object.
5. **Assert it fails loudly on an unknown value** — write junk with the query
   builder, expect `ValueError` on read. This is the one people skip, and it is
   the one that documents what happens after a bad import or a hand-edited row.
6. **Test the behaviour, not the labels** — transitions, predicates, signs.

---

## 6a. Making a missing `match` arm fail in CI

`match ($this)` with no `default` is the whole safety argument for enums over
strings — but in **this** repo it is only a *runtime* guard. No static
analyser is installed, so a missing arm is an `UnhandledMatchError` at
execution, not a build failure. Three ways to close that, ranked:

**1. PHPStan + larastan at level ≥ 4.** Rule
`PHPStan\Rules\Comparison\MatchExpressionRule`, error identifier
`match.unhandled`, message *"Match expression does not handle remaining
value: …"*. **Level 4 is the floor — levels 0-3 do not report it.** It
strictly dominates the others: it covers all 56 enum matches in this codebase,
including matches whose subject is type-inferred rather than literally naming
cases. Enum-awareness comes from PHPStan core, not from larastan.

The cost is far lower than `app/`'s 513 files / 78k lines suggests. Measured
type coverage: **4,268 parameters, 0 untyped**; 3,525 methods, 152 without a
return type of which **150 are `__construct`** (which cannot have one) — so
exactly **2** real methods lack one; 1,859 closures, 48 untyped; **5** dynamic
property fetches app-wide; 932 `@return array<…>`/`array{…}` shapes and **0**
untyped `@return array`; and **0** existing `@phpstan`/`@psalm` annotations.
This codebase is already written to level 6-7 conventions with no PHPStan
installed. Not verified by running it — installing would have modified
`composer.json` — so treat the small-baseline conclusion as a strong proxy,
not a measurement.

Precedent: the FilamentExamples `construction` project runs **larastan at
level 7** in CI (`phpstan.neon`, `composer types:check` chained into
`composer test`, plus its own GitHub Actions step across PHP 8.3/8.4/8.5).

**2. A parser-based Pest test — no dependency, and it has already paid for
itself.** Walk every `Node\Expr\Match_` under `app/`, `database/`, `routes/`;
for any match whose arms literally name `App\…Enum::Case`, assert every case
appears unless a `default` arm exists. Purely syntactic — needs no type
inference, because the arms themselves identify the enum. ~0.8 s on the
existing gate. **This is the one that found `unhandled-arm` in the ledger.**
Two traps, both non-obvious:

- `NameResolver` must run as its **own** traverser pass. With both visitors on
  one traverser, `Match_` is entered before its children resolve, imported
  enums stay short-named, and the sweep silently reports 17 guarded instead of
  56 — a green test that checks a third of what you think it does.
- `self::`/`static::` are not rewritten by `NameResolver`, so the visitor must
  track the enclosing `Stmt\Enum_` itself.

Put it in `tests/Unit` — it needs the autoloader, not a booted app. Blind
spots: a match with a *wrong* `default` arm, arms referencing cases
indirectly, and Blade.

**3. A reflection sweep** (call every zero-argument public method with every
case). Cheapest, weakest: reaches 38 of the 56 matches, and **would not have
found the live one**, because the enum's own `getLabel()` is exhaustive and
the broken match lives in a Support class. Write #2 instead. Useful data if
you build it anyway: `grep` for `DB::|Cache::|Storage::|Http::|Log::|dispatch(|Auth::`
across `app/Enums` returns **zero** hits, so no mocking is needed — our enums
are already side-effect free.

**Not an option: FilaCheck.** Verified — it has no exhaustiveness rule of any
kind; it is a Filament-API linter (deprecations, missing indicators,
unsearchable columns, string icons). Its `EnumMissingFilamentInterfacesRule` is
a *different* enum guard we already pass, not a substitute. **Also not:**
Psalm (a second analyser and a second baseline, in a codebase with zero
suppression annotations of either dialect) or Rector (it would *add* the
missing arms rather than fail the build — wrong shape for a gate).

## 6b. Anti-patterns, caught in real code

**A pivot-cast enum column renders blank.**
`TextColumn::make('project_role')->badge()` cannot resolve a pivot attribute:
`BelongsToMany::migratePivotAttributes()` moves every `pivot_*` key onto the
pivot relation and **unsets it from the parent**
(`vendor/.../BelongsToMany.php:1255-1271`), while Filament resolves cell state
with `data_get($record, $name)`. The construction project ships this bug
(`UsersRelationManager.php:46`) directly beside two sibling columns that get it
right with `->state(fn ($record) => $record->pivot->…)`.

**Asymmetric exhaustiveness in a codec pair.** `CompanySetting::typedValue()`
(read) lists all five cases with **no default**, so a sixth is an analysis
error. `CompanySettings::encode()` (write) covers three and funnels the rest
into `default => (string) $value`, so the same new case is **silently
stringified** with no error anywhere. Two halves of one codec disagreeing
about strictness is worse than either policy applied consistently — the read
side's guarantee is exactly what makes the write side's silence invisible.

**A string literal compared against `->value`.**
`$invoice->type->value === 'subcontractor'` (`User.php:148`) — in a project
that does it correctly two files away with an enum instance
(`Invoice.php:54`). The `unrouted-enum` pattern in the ledger, in the wild.

**Blade re-deriving what `HasColor` already returns.** A hand-rolled timesheet
grid branches on case identity inside `@class()` to pick Tailwind classes that
`TimeEntryStatus::getColor()` already returns. `one-home`, in a file nobody
thought to look at.

## 7. Rules

**Do**

- Put the question next to the answer. If two places ask "is this status
  final?", that belongs on the enum.
- Implement `HasLabel` on anything a human sees; add `HasColor`/`HasIcon` when
  it appears as a badge or toggle; `HasDescription` when the option needs
  explaining.
- Pass `CarbonInterface $moment` into any enum method that needs time.
- Bridge to SQL with `array_filter(cases())` → `whereIn`, never a derived
  expression.
- Keep `match ($this)` exhaustive with no `default` — a new case should be a
  fatal error at every decision site, which is the entire safety argument for
  enums over strings.
- Use `->options(Status::class)`, not a hand-built array.

**Do not**

- Do not group, sort, filter or index on anything an enum computes.
- Do not call `now()`, hit the container, or touch the database from an enum.
- Do not give an enum a `default` arm in a `match` "for safety" — it converts a
  compile-time guarantee into a silent wrong answer.
- Do not duplicate a label or colour into a Filament closure when the contract
  would supply it.
- Do not put translation-key logic in more than one place; `__("admin.x.{$this->value}")`
  in `getLabel()` is our convention.

---

## 8. Where we stand

Already good: 23 of our 45 enums carry behaviour beyond the contracts —
`DashboardRange::currentPeriod()`, `EpisodePublicState::for()`,
`UserRole::isAtLeast()`, `SparklineTrend::fromDelta()`. The `fromFilter()` /
`options()` / `indicator()` triple on the filter-scope enums is our own house
pattern and has no equivalent in the construction project.

Gaps worth a look, in order:

0. **One `match` is already missing an arm.** `MediaMutationOperationType`
   declares 11 cases; `MediaFilesystemMutationCoordinator.php:1540-1553` covers
   10 with no `default`. Registered as `unhandled-arm` in the ledger. Latent —
   nothing writes `LegacyOwnerRepair` yet — and it arms itself the day the
   parked legacy owner-column retirement lands a writer. Found by §6a's parser
   sweep, which is the argument for building it.
1. **No transition table anywhere.** `PublicationStatus` has no
   `allowedTransitions()`; the rule for what a toggle may do lives in the table
   action. Everything in §4 argues for moving it.
2. **`EpisodeListScope::partitionsLibrary()` is a predicate with no SQL
   bridge** — the queries live in `EpisodeListScopeQuery`. That split is
   defensible (the scopes are genuinely multi-column, not value sets), but the
   `whereIn` bridge is the cheaper answer wherever a scope *is* a value set,
   and it is the shape to prefer for new ones.
3. **11 enums implement no contract.** Fine while they stay internal; the day
   one reaches a form, it needs `HasLabel` rather than an options array.
