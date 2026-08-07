# larastan playbook

Research notes for `larastan/larastan` v3.10.0 on `phpstan/phpstan` 2.2.8, Laravel 13,
Filament 5. Written while diagnosing the cast-resolution defect recorded as
`open-findings-triage.md` §B1, and kept as the reference for future PHPStan work here.

Everything measured below was measured on this repo, on 2026-08-07, at level 5.

---

## 1. The one thing to know first: `parseModelCastsMethod`

**Laravel 11+ models declare casts in a `casts()` method. larastan ignores that method
unless you turn it on.** The parameter is `parseModelCastsMethod`, it defaults to `false`,
and nothing warns you.

### Mechanism

Laravel merges `casts()` into the cast map in `HasAttributes::initializeHasAttributes()`
(`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php:209`):

```php
protected function initializeHasAttributes()
{
    $this->casts = $this->ensureCastsAreStringValues(
        array_merge($this->casts, $this->casts()),
    );
    ...
}
```

`initializeHasAttributes()` is a **trait initializer** — it runs from `Model::__construct()`
via `initializeTraits()`, and only from there. `getCasts()` itself never calls `casts()`;
it just reads the `$casts` property.

larastan builds its model instances with `newInstanceWithoutConstructor()`
(`src/Properties/ModelCastHelper.php:257`), precisely to avoid running app code. So the
constructor never fires, `initializeHasAttributes()` never fires, and
`$modelInstance->getCasts()` at `:270` returns **only the `$casts` property** — empty in
any Laravel 11-style model — plus the primary key.

larastan then tries to recover the rest at `:272`:

```php
if ($this->parseModelCastsMethod) {
    $castsMethodReturnType = $this->parseCastsMethod($modelClassReflection);   // AST parse
} else {
    $castsMethodReturnType = $modelClassReflection->getMethod('casts', new OutOfClassScope())
        ->getVariants()[0]->getReturnType();                                    // declared type
}

if ($castsMethodReturnType->isConstantArray()->yes()) { /* merge */ }
```

With the flag **off**, the "declared type" branch reads the signature `protected function
casts(): array`. `array` is not a constant array, `isConstantArray()` answers `no`, and the
merge is skipped silently. The cast map stays empty.

### Why the failure looks partial (and therefore looks like something else)

The attribute type does not become `mixed` — larastan falls back to the **column type from
the migration scan**. So the breakage only shows where the column type and the cast
diverge:

| cast | column | resolved without the flag | looks broken? |
| --- | --- | --- | --- |
| `'integer'` | `int` | `int` | no |
| `'boolean'` | `tinyint` | `bool` | no |
| `'datetime'` | `timestamp` | `string\|null` | yes |
| `'array'` / `'json'` | `json`/`text` | `string\|null` | yes |
| `BackedEnum::class` | `varchar` | `string` | yes |

That partial-looking pattern is what sends you hunting for an enum-specific or
Filament-specific cause. It is neither. It is every cast declared in the method.

### The blast radius is bigger than "wrong types"

A json column resolving to `string|null` makes `$model->meta['k'] ?? null` always-null,
strict comparisons always-false, and guard clauses "always terminate" — so PHPStan reports
the code *after* them as `deadCode.unreachable` and **stops analysing it**. Real errors hide
behind the false ones. With a baseline in place that happens invisibly.

### Upstream

- [larastan#2509](https://github.com/larastan/larastan/issues/2509) — "Enum casts work for
  casts property but not casts method." Closed. The collaborator's answer is to annotate:
  `@return array{visibility: 'App\Models\VisibilityFoo'}` — a literal-string array shape on
  `casts()` makes the *declared type* branch a constant array, so it works with the flag
  off. That is a per-model workaround; the flag is the global one.
- [larastan#2512](https://github.com/larastan/larastan/issues/2512) — open, proposing the
  default flip or a warning. Reports a ~80-model codebase where enabling the flag revived
  15 wrongly-dead code regions and net-resolved ~300 baseline errors.
- [`docs/custom-config-parameters.md`](https://github.com/larastan/larastan/blob/3.x/docs/custom-config-parameters.md#parsemodelcastsmethod)
  documents the flag, but frames it as an enhancement for "class constants or
  concatenation" — not as the thing that makes `casts()` work at all. Nothing in
  `getting-started`, `features.md` or `rules.md` mentions it.

### Cost

Docs say parsing "might be slower and is disabled by default for that reason." Measured
here, cold cache, isolated `tmpDir`, `app/` + `database/` + `routes/`:

| | cold wall-clock | errors |
| --- | --- | --- |
| flag off | 20.4 s | 614 |
| flag on | 21.5 s | 507 |

~5%. The AST parse only runs for classes that actually declare `casts()`.

---

## 2. How to diagnose cast/property resolution — the method that worked

Do not reason about it. Ask PHPStan what it thinks, in isolation, with `dumpType`. Put a
scratch file **outside** the configured `paths` and pass it as an explicit argument (CLI
paths override config paths):

```php
<?php
use App\Models\ContentItem;
use function PHPStan\dumpType;

$i = new ContentItem();
dumpType($i->status);
dumpType($i->published_at);
dumpType($i->media_metadata);
```

```bash
vendor/bin/phpstan analyse /path/to/probe.php --no-progress -v
```

This repo's before/after, which is also the signature of the defect:

| expression | flag off | flag on |
| --- | --- | --- |
| `$item->status` | `string` | `App\Enums\PublicationStatus` |
| `$item->published_at` | `string\|null` | `Carbon\Carbon\|null` |
| `$item->media_metadata` | `string\|null` | `array\|null` |

**Read the "off" column as evidence, not just symptom.** `string|null` is the *migration
column type*. Its presence proves the migration scan ran, which proves the Laravel
container booted, which refutes the whole "bootstrap fails silently under Filament"
family of hypotheses in one shot. If bootstrap had failed, larastan's
`BootstrapErrorHandler` would have printed a styled error and `exit(1)`
(`vendor/larastan/larastan/bootstrap.php:40-51`) — it fails loudly, not silently.

Two more things that mislead here:

- **The agent error formatter truncates.** PHPStan 2.x detects an AI agent and switches to
  a JSON format that caps the error list and appends `"truncated": true, "hint": "Pass -v
  to see all errors."` It ignores `--error-format=json` and survives unsetting `CLAUDECODE`.
  Always pass `-v` before drawing conclusions from a list, and check the `truncated` field.
  A conclusion of the form "rule X never fires" drawn from a truncated list is worth nothing.
- `vendor/bin/phpstan clear-result-cache` exits 1 and prints nothing under the agent
  formatter. To force a genuinely cold run, point `tmpDir` at a scratch directory instead.

---

## 3. Config knobs that matter, and why

Defaults are larastan's, from `vendor/larastan/larastan/extension.neon:1-35`.

| parameter | default | verdict here |
| --- | --- | --- |
| `parseModelCastsMethod` | `false` | **Must be `true`.** §1. |
| `databaseMigrationsPath` | `[]` | **Must be set.** Without it there is no column-type fallback at all. Accepts globs (`modules/*/migrations`). Note: `if` statements in migrations are assumed true and their bodies applied. |
| `squashedMigrationsPath` | `[]` (looks in `database/schema`) | Only if you squash. PostgreSQL dumps need `phpmyadmin/sql-parser ^5.9` installed to parse reliably; the bundled `iamcal/sql-parser` is MySQL-focused. Not applicable here (MySQL, no dumps). |
| `enableMigrationCache` | `false` | Optional speed-up; invalidates on migration mtime. Not worth it at 20 s. |
| `disableMigrationScan` / `disableSchemaScan` | `false` | Leave off. Turning either on removes the column-type source and re-creates §1's symptom by a different route. |
| `checkModelProperties` | `false` | Beta rule — validates model property *names* passed as string arguments. Candidate for later; would catch typo'd `where('colum', …)`. Not enabled yet. |
| `checkModelAppends` | `true` | On by default; fine. |
| `checkConfigTypes` | `false` | Types `config()` returns by parsing `config/`. Attractive here given `PublicFrontConfigValidator`. Parses lazily+cached, but adds overhead. Candidate. |
| `generalizeEnvReturnType` | `false` | Widens `env('X', 'y')` to `string`. Cosmetic. |
| `checkModelMethodVisibility` | `false` | Enforces `protected` scopes/accessors. Would conflict with this repo's existing `public function scopeX`. |
| `noModelMake`, `noUnnecessaryCollectionCall`, `noEnvCallsOutsideOfConfig`, `RelationExistenceRule` | on | larastan's own rules, on by default. One `larastan.noUnnecessaryCollectionCall` fires here. |

### Level progression

PHPStan 2.x has levels 0–10 (`max` = 10). The commonly repeated advice — level 5 for most
projects, 9 for the largest safety net, `max` for new packages, ratchet +1 per sprint —
is generic; it is not calibrated to what a given codebase has already paid for. This repo's
reasoning is recorded in `phpstan.neon` itself and is better than the generic advice
because it is measured: level 5 because return/param type coverage is already ~98%, and a
documented stop at 8 because level 9's cost is Filament's own `mixed` closure contract
rather than neglect.

The one correction worth making to the generic advice: **raise the level only after the
current level is clean.** Climbing while 507 errors are unread converts a signal into
wallpaper — the same reason `types:check` is deliberately not wired into `composer test`
here.

---

## 4. Filament 5 interactions

Three distinct things, only one of which is a larastan limitation.

### 4a. Macros — larastan structurally cannot see them (confirmed upstream)

larastan's `MacroMethodsClassReflectionExtension` gates on
`Illuminate\Support\Traits\Macroable`. Filament components use
`Filament\Support\Concerns\Macroable` — a different trait, with a different storage shape
(`name => [class-string => Closure]` vs Laravel's `name => Closure`). No branch matches.

[larastan#1935](https://github.com/larastan/larastan/issues/1935) asked for support and was
closed as out of scope, with maintainers stating the position directly: *"there is no plan
to support anything outside of Laravel"* — and recommending either a custom extension or
that Filament ship its own auto-discoverable PHPStan extension, as Carbon does.

This repo's answer is `phpstan/filament-macros.stub` with `@method` PHPDoc on stubbed class
bodies. That is the cheaper half of the maintainers' advice and it **teaches** rather than
suppresses, so it does not compromise the no-baseline policy. Note the form matters:
`@method` PHPDoc on a stubbed class body is inherited by subclasses; declaring a real
method signature in the stub is not.

### 4b. Filament's untyped `Model` / `Builder` contracts — the largest remaining family

Filament's base classes hand you `Illuminate\Database\Eloquent\Model` and unparameterized
`Builder`: `Exporter::$record`, `Importer::$record`, `Resource::getEloquentQuery()`,
`modifyQueryUsing(fn (Builder $query) => …)`. App code then calls concrete-model methods on
them, and PHPStan is right that the declared type does not have them.

171 of the 507 remaining errors are this — a third of the total:

- `Access to an undefined property Illuminate\Database\Eloquent\Model::$reference_key` (48)
- `Call to an undefined method Illuminate\Database\Eloquent\Model::transcriptions()` (20)
- `Call to an undefined method Illuminate\Database\Eloquent\Builder::releasedBy()` (61,
  counting `Builder` and `Builder<Model>`) — these are ordinary `scopeReleasedBy()` /
  `scopeCurrentlyPinned()` methods on `ContentItem`/`ContentGroup`/`Transcription`.
  larastan resolves scopes fine on `Builder<ContentItem>`; it cannot on a raw `Builder`.
- plus 38 `argument.type` and 4 `assign.propertyType` of the same shape.

**This is app typing debt at the Filament boundary, not a larastan limitation.** The remedy
is to narrow at the boundary — type the closure parameter `Builder<ContentItem>`, or give
the Filament class a covariant `@property ContentItem $record` — not to loosen the check.

### 4c. Bootstrap — not a problem

Filament 5 with many panels and providers boots fine under larastan's `bootstrap.php`. See
§2 for why the migration-scan types are the proof. Should it ever break, it breaks loudly.

### 4d. Relationship generics — the cheapest error reduction available, and it fixes part of 4b

larastan infers a relation's *kind* from the return type, but not the *related model*, unless
you tell it. Without a generic, `$item->transcriptions` is a collection of `Model`, and every
concrete method or property reached through it is an error. Annotate:

```php
/** @return HasMany<Transcription, $this> */
public function transcriptions(): HasMany
```

`$this` on the declaring side is load-bearing — it preserves late-static model context so the
type survives subclassing.

**Measured here, 2026-08-07 — and then reverted.** The change was implemented, measured, and
backed out because it fell outside that session's approved scope. The numbers below are real
measurements of a working change, not estimates, but **the annotations are not in the code**;
`open-findings-triage.md` §B5 carries this as an open item.

This repo has 45 relationship methods and 2 generics. Adding the other 43 took `507 → 444`,
with **zero** new errors introduced, and left the suite byte-identical at 1850 passed /
20573 assertions. Where the errors went:

| identifier | before | after |
| --- | --- | --- |
| `property.notFound` | 65 | 33 |
| `argument.type` | 58 | 44 |
| `method.notFound` | 129 | 121 |
| `return.type` | 27 | 20 |
| `argument.unresolvableType` | 1 | 0 |

Three things worth knowing before doing this elsewhere:

- **Check the generic arity against the installed framework**, don't guess. Read the
  `@template` lines on the relation class: `HasMany`/`HasOne`/`BelongsTo`/`MorphTo` take two,
  `BelongsToMany`/`MorphToMany` take two plus defaulted pivot and accessor, `HasOneThrough`
  takes three (related, intermediate, declaring).
- **`morphTo()` with no argument cannot be narrowed.** It returns `MorphTo<Model, $this>`, and
  PHPStan checks the body against your tag — so annotating the union the morph map actually
  admits is a claim it *rejects*, producing a fresh `return.type` error. Use `Model`. The one
  place this bites is worth an explicit comment in the code, because the natural instinct on a
  second pass is to re-narrow it and re-break it.
- **Know which annotations PHPStan is actually checking.** It validates each `@return` against
  the method body — that is how it caught the `morphTo()` mistake above, and it is the reason
  a bulk pass is safe. But where the related class is a *dynamic string*, it cannot: this
  repo's `tags()` calls `morphToMany(self::getTagClassName(), …)`, so a `ContentTag` claim is
  accepted unverified. True today per `config/tags.php`, but nothing pins it. Either pin it
  with a test or leave those relations un-annotated; do not let an unverifiable annotation
  pass as a verified one.

The annotation can also expose dead defensive code: a `foreach` guarded by
`if (! $item instanceof ContentItem) { continue; }` becomes provably redundant once the
relation is typed, because the guard existed only to compensate for the missing type. Deleting
that guard is the change working as intended — and note it is then the *only* runtime-affecting
line in an otherwise comment-only change, so it deserves separate scrutiny from the rest.

This does **not** fix the Filament half of 4b. Errors whose `Model` originates from
`Exporter::$record` or `getEloquentQuery()` still need narrowing at that boundary.

Rules distilled from [szepeviktor's `larastan-preflight-reviewer` skill](https://github.com/szepeviktor/skills/blob/master/skills/larastan-preflight-reviewer/SKILL.md)
(szepeviktor is a larastan collaborator; the repo carries no license, so the rules were
applied, not copied). The same skill prescribes literal array shapes on `casts()` as the
per-model alternative to `parseModelCastsMethod` — **do not adopt that here**: it duplicates
every cast map into a docblock on every model, forever, with silent drift when the two
disagree. §1's one config line replaces all of it. The skill's `Attribute<TGet, TSet>` rules
(use `never` for the absent side) and its `@mixin` rule for `JsonResource` remain unapplied
and are open candidates.

---

## 5. What larastan can and cannot do

**Can**: model attribute types from migrations + casts; relations (when the return type is
declared, the method is `public`, and relation arguments are literal strings — `@return`
docblocks can substitute); generics for `HasBuilder` / `HasCollection` / factories; Laravel
9+ `Attribute` accessors when `protected` and annotated with generic types;
`HigherOrderCollectionProxy`; config value types (opt-in); view/translation existence
(opt-in).

**Cannot**: anything outside Laravel itself, by explicit maintainer policy — Filament,
third-party macro systems, package-specific magic. Higher-order messages on
`Support\Collection` (as opposed to `Eloquent\Collection`) are documented as too magical
and are the one thing larastan's own docs suggest ignoring. PostgreSQL schema dumps only
partially.

**Deliberately will not, here**: no baseline and no `@phpstan-ignore`. A baseline would
freeze the exact defects that justified the install — `PublicFrontConfigValidator.php:69`
being the standing example. Prefer, in order: fix the code → narrow the type at the
boundary → teach PHPStan with a stub → and only then consider a scoped `ignoreErrors`
entry with a comment saying why the rule is wrong about this codebase.

### The mainstream advice is the opposite, and its own worked example is the argument against it

Worth knowing, because "just baseline it" is what most tutorials say and someone will
propose it here eventually. LaravelDaily's Larastan course devotes its entire "Don't get
scared by errors" lesson to `--generate-baseline` as *the* answer to a large first run.

Read the lesson's own example, though. The baseline it generates freezes, among twelve
entries, several missing Eloquent relations (`roles` on `User`, `permissions` on `Role`)
and an access to an undefined property. Those are not noise — they are real bugs, of
exactly the class larastan exists to find. The lesson then demonstrates the tool working
"correctly" afterwards by showing a *newly written* `with(['roles', 'permissions'])` call
reporting only `permissions`, while `roles` stays silent because it is baselined. The
demonstration of the feature working is simultaneously a demonstration of a real defect
being rendered invisible in new code.

That is the whole argument for this repo's policy, made unintentionally by the best-known
tutorial on the subject. A baseline does not defer the work; it converts findings into
permanent silence, and does so most reliably for the findings that recur.

---

## 6. Sources

- [larastan `docs/custom-config-parameters.md`](https://github.com/larastan/larastan/blob/3.x/docs/custom-config-parameters.md)
  — the only complete config reference; not shipped in the composer dist, read it on GitHub.
- [larastan `docs/rules.md`](https://github.com/larastan/larastan/blob/3.x/docs/rules.md),
  [`features.md`](https://github.com/larastan/larastan/blob/3.x/docs/features.md),
  [`errors-to-ignore.md`](https://github.com/larastan/larastan/blob/3.x/docs/errors-to-ignore.md)
- [larastan#2512](https://github.com/larastan/larastan/issues/2512),
  [#2509](https://github.com/larastan/larastan/issues/2509),
  [#1935](https://github.com/larastan/larastan/issues/1935)
- [Running PHPStan on max with Laravel](https://laravel-news.com/running-phpstan-on-max-with-laravel) — level progression, generic-type friction
- [larastan.org](https://larastan.org/),
  [Larament](https://filamentphp.com/plugins/codewithdennis-larament) (a Filament starter kit;
  its `phpstan.neon` is the stock three-key file at level 5 — no Filament-specific configuration)
- [LaravelDaily's Larastan course](https://laraveldaily.com/course/larastan) — 8 text lessons,
  30 min, read in full 2026-08-07 on the operator's account. **Published March 2023 and not
  revised since**, confirmed three ways: it installs `nunomaduro/larastan` and hand-writes
  `includes: ./vendor/nunomaduro/larastan/extension.neon` (both pre-rename, and the
  `includes:` line is now redundant under `phpstan/extension-installer`); it states level 9
  is the highest (PHPStan 2.x has 10); newest discussion comment is three years old. It
  therefore predates Laravel 11's `casts()` method by a year and **cannot** speak to the
  defect in §1. Lesson 6's level-by-level breakdown independently confirms the level 4/5
  reasoning in `phpstan.neon` — dead-code checks begin at 4, caller-side argument checking
  is what 5 adds — but contributes nothing new. Lesson 7 is the baseline argument, treated
  in §5.
- Vendor source read directly: `vendor/larastan/larastan/{bootstrap.php,extension.neon}`,
  `src/Properties/ModelCastHelper.php`, `vendor/laravel/framework/.../HasAttributes.php`

### What I could not obtain

No YouTube transcripts, and none were needed in the end — the LaravelDaily course turned
out to be text, not video, and was read in full (see above). The FilamentDaily channel
surfaced no larastan/PHPStan content at all. Nothing authored by Nuno Maduro specifically
about larastan configuration turned up beyond the package README — he co-created larastan
with Can Vural in 2019, but the current maintainers (`canvural`, `calebdw`, `szepeviktor`)
are the ones answering the issues cited above.

**Bottom line on secondary sources: none of them knew about this defect.** Every tutorial
and course found predates Laravel 11's `casts()` method or does not mention it. The
substantive findings here came from vendor source and the issue tracker, and the one
external source that materially changed a decision did so by arguing the opposite case
(§5).
