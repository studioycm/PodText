# Rule: Max Length Hints

## Severity
Low

## Problem
Short-string fields like postcodes, phone numbers, license plates, and codes
can accept values longer than the storage or domain contract.

## Detection
- TextInput for fields where the value has a known maximum length (postcodes, codes, plates, PINs)
- Fields backed by database columns with a specific `varchar` length
- Any field where overly long input is never valid

## Recommendation
Add `->maxLength()` when the schema or domain has a real maximum. Filament 5
adds frontend and backend length validation. Add a translated hint or example
only when users need to understand the limit or format; do not claim that
`maxLength()` automatically renders a character counter.

## Example
```php
use Filament\Forms\Components\TextInput;

TextInput::make('postcode')
    ->maxLength(10)
    ->placeholder('e.g. SW1A 1AA'),

TextInput::make('license_plate')
    ->maxLength(8)
    ->placeholder('e.g. AB12 CDE'),

TextInput::make('short_description')
    ->maxLength(160)
    ->helperText('Shown in listing cards, keep under 160 characters.'),
```
