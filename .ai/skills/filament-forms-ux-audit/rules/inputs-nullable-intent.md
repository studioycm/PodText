# Rule: Nullable Intent

## Severity
Medium

## Problem
When a field is optional and `->nullable()`, an empty value could mean "not applicable", "unknown", "zero", or "intentionally left blank". The admin has no way to know what leaving the field empty communicates. For example, an empty `deposit_amount` — does it mean no deposit required, or did the admin forget to fill it in?

## Detection
- `->nullable()` fields with no `->helperText()` explaining the empty state
- Optional monetary, date, or reference fields where empty has a specific business meaning
- Any field where "null" and "zero" mean different things

## Recommendation
Add `->helperText()` that explicitly states what leaving the field empty means. This removes ambiguity and prevents follow-up questions.

## Example
```php
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;

TextInput::make('deposit_amount')
    ->numeric()
    ->prefix('£')
    ->nullable()
    ->helperText('Leave empty if no deposit required'),

DatePicker::make('drivers_license_expiry')
    ->nullable()
    ->helperText('Leave empty if not applicable'),

TextInput::make('referral_code')
    ->nullable()
    ->helperText('Leave empty if no referral'),
```
