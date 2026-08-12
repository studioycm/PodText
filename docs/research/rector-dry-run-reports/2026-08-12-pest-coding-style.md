# Rector dry-run report — `PestSetList::CODING_STYLE` (tests-side, Phase U)

2026-08-12, Phase U Task 6. `pestphp/pest-plugin-rector` **5.0.3** installed by the Phase U
composer batch (with pest 5.1.0; rector 2.6.1 + driftingly/rector-laravel 2.5.0 unchanged).
Probe config: `rector.php` temporarily narrowed to `withPaths([tests])` +
`withSets([PestSetList::CODING_STYLE])`, keeping the committed two-path
`withPHPStanConfigs` string and `->withoutParallel()` verbatim (the
`RectorScriptContractTest` pins), then restored — `git diff rector.php` empty after.
**No source files were rewritten to produce this report.** All verdicts are
recommendations for the operator's DP4 call, not decisions.

## 0. Measurement discipline

- Cold cache **and** serial, per `.ai/rules/general.md`: `composer rector -- --clear-cache`,
  run **twice** — totals byte-identical both runs: **22 changed files / 133 errors**.
  Deterministic; no parallel-mode caveat applies (serial is pinned in the probe config too).
- Output is pao agent-mode JSON (`totals` + `file_diffs[].applied_rectors`), same
  formatter facts as the 2026-08-10 report §2.
- The 133 errors are the **documented §0b larastan-boot family** (2026-08-10 report):
  `Call to undefined method Illuminate\Container\Container::databasePath()` from larastan's
  squashed-migration scan fallback, all citing `:420`, now surfacing once per analysed test
  file instead of per app file. Same deliberately-unfixed cause
  (`withBootstrapFiles()` remains a deferred operator decision), nothing new.
- **Contract-test verification note:** `RectorScriptContractTest` could not be re-run at
  restore time — the session's pest invocations were blocked by a harness permission
  denial at that moment. `git status` proves `rector.php` byte-identical to HEAD; the
  contract test rides the next full gate (Phase U Task 4) and its result lands in the
  Phase U record.

## 1. Per-rule verdict table

22 files, 5 rules. Verdicts argued from this suite's measured conventions.

| rule | files | verdict | why |
| --- | --- | --- | --- |
| `UsesToExtendRector` | 16 | **defer** | Rewrites bare `uses(RefreshDatabase::class);` → `pest()->use(RefreshDatabase::class);`. The suite's dominant idiom is classic `uses()` — R5 counted 113 Pest-style files; this rule reaches only 16 (the bare, unchained shape). Adopting would split one idiom into two across the suite (16 modern / ~97 classic) with no behavioral gain. If modernisation is ever wanted, it must be a deliberate suite-wide sweep with a completeness canary, not this partial rule. Interplay note: `pest-plugin-phpstan` ships `pest.config.redundantLocalUse`, which reads both forms — no analysis pressure either way. |
| `ChainExpectCallsRector` | 2 | **defer** | Merges consecutive `expect()`s into `->and()` chains (`CheckDatabaseSettingsTest`, `FilamentLocalizationDefaultsTest` — diffs below). Cosmetically consistent with the house `->and()` style used elsewhere; zero behavior change. Note the set default `merge_different_variables: true` DID merge different-value expectations here — acceptable in these two diffs, but a write pass should consider `withConfiguredRule(..., ['merge_different_variables' => false])` to keep merges same-value only. |
| `SimplifyToLiteralBooleanRector` | 2 | **REJECT** | `expect($offenders)->toBe([])` → `->toBeEmpty()` in two guard tests (`UiFormatsPolicyTest`, `UiTimezonePolicyTest`). **This is an assertion weakening, not a style change**: `toBe([])` is identity with the empty array; `toBeEmpty()` passes for `''`, `null`, `0`, and `[]` alike. `$offenders` is `->all()` (always array) today, so the weakening is latent — but the house rule is "do not weaken any existing assertion", and a style set that trips it on guard tests is auto-rejected for those sites. |
| `ConvertAssertToExpectRector` | 1 | **defer** | `tests/Unit/ExampleTest.php` (the classic PHPUnit-style stub — the same file R5 flagged as the suite's only class-based test): `$this->assertTrue(true)` → `expect(true)->toBeTrue()`. Harmless; the stub's classic shape is itself a documented R5 curiosity. Not worth a write pass alone. |
| `UseToHaveLengthRector` | 1 | **defer** | `HebrewSluggerTest`: `expect(strlen(...))->toBe(N)` → `expect(...)->toHaveLength(N)`. Genuine diagnostic improvement (failure shows the string, not two ints). Note for the operator: `toHaveLength` on a *Hebrew* string counts bytes vs characters identically to `strlen` only if the matcher uses `strlen` internally — a write pass must verify the matcher's multibyte semantics against this test's intent before applying. |

**Net: 0 adopt, 4 defer, 1 reject** — the reject is a genuine catch (a style rule that
weakens guard assertions), and it seeds the DP4 principle that `SimplifyToLiteralBooleanRector`
stays excluded from any future write pass touching guard tests.

## 2. The six substantive diffs (non-`UsesToExtendRector`)

```diff
// tests/Feature/CheckDatabaseSettingsTest.php  [ChainExpectCallsRector]
     expect(CheckDatabaseSettings::columnTypeProblems(['timestamp' => 3, 'datetime' => 77]))
         ->toHaveCount(1)
         ->and(CheckDatabaseSettings::columnTypeProblems(['timestamp' => 3, 'datetime' => 77])[0])
-        ->toContain('3 column(s) are still TIMESTAMP');
-    expect(CheckDatabaseSettings::columnTypeProblems(['datetime' => 80]))->toBeEmpty();
+        ->toContain('3 column(s) are still TIMESTAMP')
+        ->and(CheckDatabaseSettings::columnTypeProblems(['datetime' => 80]))->toBeEmpty();

// tests/Feature/FilamentLocalizationDefaultsTest.php  [ChainExpectCallsRector]
-    expect($table->getDefaultDateDisplayFormat())->toBe(UiFormats::date());
-    expect($table->getDefaultDateTimeDisplayFormat())->toBe(UiFormats::dateTime());
-    expect($table->getDefaultTimeDisplayFormat())->toBe(UiFormats::time());
+    expect($table->getDefaultDateDisplayFormat())->toBe(UiFormats::date())
+        ->and($table->getDefaultDateTimeDisplayFormat())->toBe(UiFormats::dateTime())
+        ->and($table->getDefaultTimeDisplayFormat())->toBe(UiFormats::time());
     (…two more same-shape hunks…)

// tests/Feature/UiFormatsPolicyTest.php + UiTimezonePolicyTest.php  [SimplifyToLiteralBooleanRector — REJECTED]
-    expect($offenders)->toBe([]);
+    expect($offenders)->toBeEmpty();

// tests/Unit/ExampleTest.php  [ConvertAssertToExpectRector]
-        $this->assertTrue(true);
+        expect(true)->toBeTrue();

// tests/Unit/HebrewSluggerTest.php  [UseToHaveLengthRector]
-    expect(strlen(...))->toBe(N);
+    expect(...)->toHaveLength(N);
```

The 16 `UsesToExtendRector` diffs are one identical shape:
`uses(X::class);` → `pest()->use(X::class);` across 4 Browser + 12 Feature files
(list in the Phase U record).

## 3. What stays registered

Nothing. Unlike the Laravel set (which stays in `rector.php`, inert behind the dry-run
lock), the Pest set was probed via the narrow-then-restore DP4 pattern and `rector.php`
is byte-identical to its committed shape. Registering the Pest set permanently would
add `tests/` to `composer rector`'s default scan and change every future DP4
measurement's scope — a decision for the operator, not a probe's side effect.
