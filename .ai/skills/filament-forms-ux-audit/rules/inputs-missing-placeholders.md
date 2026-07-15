# Rule: Missing Placeholders

## Severity
Low

## Problem
Some fields benefit from an example value when the expected format is not obvious. But placeholders are not mandatory for every field, and they should not be used instead of labels or helper text.

## Detection
- TextInput fields with no `->placeholder()` where the format is non-obvious
- Phone number, postcode, license number, ID fields with no example value
- Any field where the data format varies by convention and the label alone is not enough

## Recommendation
Add `->placeholder()` only when a short example will materially reduce ambiguity. Use realistic examples, and prefer helper text when the rule needs explanation rather than a sample value.

## Example
```php
use Filament\Forms\Components\TextInput;

TextInput::make('phone')
    ->tel()
    ->placeholder('+1 555 123 4567'),

TextInput::make('postcode')
    ->maxLength(10)
    ->placeholder('e.g. 90210'),

TextInput::make('drivers_license_number')
    ->placeholder('e.g. 12345678'),

TextInput::make('cost')
    ->numeric()
    ->placeholder('0.00')
    ->prefix('£'),
```
