# Rule: Uppercase Enforcement

## Severity
Medium

## Problem
Fields like VIN numbers, currency codes (USD, EUR), and registration plates should always be uppercase, but there's no enforcement. Admins type "eur" or "abc123def" and the data gets stored in mixed case, causing search mismatches and display inconsistencies. VINs are always uppercase by convention — the form should enforce this automatically.

## Detection
- Fields for VINs, currency codes, license plates, or reference codes with no casing enforcement
- Data that should be uppercase by convention stored in mixed case
- Fields where `strtoupper()` should always be applied

## Recommendation
Use `->extraInputAttributes(['style' => 'text-transform: uppercase'])` for visual feedback while typing, combined with `->dehydrateStateUsing(fn ($state) => strtoupper($state))` to ensure the stored value is always uppercase.

For fields where you also want to normalize the display state, add `->formatStateUsing(fn ($state) => strtoupper($state))`.

## Example
```php
use Filament\Forms\Components\TextInput;

TextInput::make('vin')
    ->label('VIN')
    ->maxLength(17)
    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
    ->dehydrateStateUsing(fn ($state) => strtoupper($state)),

TextInput::make('currency_code')
    ->maxLength(3)
    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
    ->formatStateUsing(fn ($state) => strtoupper($state))
    ->dehydrateStateUsing(fn ($state) => strtoupper($state)),

TextInput::make('license_plate')
    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
    ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
```
