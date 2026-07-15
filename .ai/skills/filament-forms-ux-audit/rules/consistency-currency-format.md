# Rule: Currency Format Consistency

## Severity
Medium

## Problem
Inconsistent monetary formatting across forms forces admins to guess whether amounts are in the same currency and how rates should be interpreted. The exact presentation may differ by locale or product, but the app should be internally consistent.

## Detection
- Monetary fields with no visible currency context when the currency is not obvious
- Different currency symbol placement (prefix vs suffix) across forms
- Numeric fields representing money with no currency indicator at all
- Rates with no time or usage unit

## Recommendation
Follow the app's monetary convention consistently. If the app uses a single currency, surface it the same way across forms. If the app supports multiple currencies, make the currency explicit in the field label, prefix, suffix, or adjacent context. For rates, include both the money and usage unit.

## Example
```php
use Filament\Forms\Components\TextInput;

TextInput::make('cost')
    ->numeric()
    ->prefix('$')
    ->placeholder('0.00'),

TextInput::make('hourly_rate')
    ->numeric()
    ->prefix('$')
    ->suffix('/hr')
    ->placeholder('0.00'),

TextInput::make('deposit_amount')
    ->numeric()
    ->prefix('$')
    ->nullable()
    ->helperText('Leave empty if no deposit required'),
```
