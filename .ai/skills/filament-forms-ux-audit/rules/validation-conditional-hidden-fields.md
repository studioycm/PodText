# Rule: Conditional Hidden Fields

## Severity
High

## Problem
Conditional fields can leave stale values behind or confuse admins when the dependency is unclear. A field that disappears after a toggle change may still dehydrate old state unless the form is configured intentionally.

## Detection
- Fields shown or hidden based on another field with no explanation
- Conditional fields that may keep stale values when hidden
- Dependencies that are not visually or semantically obvious

## Recommendation
When a field depends on another field, make the dependency explicit and decide
what should happen to hidden state. Preserve hidden state when it remains
authoritative; otherwise clear it deliberately or make dehydration conditional.
Do not add `dehydrated(false)` to a field whose value must be saved.

## Example
```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('discount_type')
    ->options([
        'none' => 'No discount',
        'fixed' => 'Fixed amount',
        'percent' => 'Percentage',
    ])
    ->required()
    ->live(),

TextInput::make('discount_value')
    ->numeric()
    ->visible(fn (Get $get): bool => $get('discount_type') !== 'none')
    ->dehydrated(fn (Get $get): bool => $get('discount_type') !== 'none')
    ->helperText('Only required when a discount type is selected'),
```
