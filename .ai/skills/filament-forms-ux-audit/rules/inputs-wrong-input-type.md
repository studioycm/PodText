# Rule: Wrong Input Type

## Severity
High

## Problem
Using a plain TextInput for structured data like years, dates, countries, or statuses invites typos and inconsistent data. A TextInput for "year" lets the admin type "20025" or "next year". A TextInput for "date_of_birth" gives no date picker and no format guidance.

## Detection
- TextInput for fields that represent dates, years, countries, currencies, or any finite set of options
- TextInput for fields where the value must match a specific format or range
- Any field where a Select, DatePicker, or Toggle would prevent invalid input

## Recommendation
Match the input component to the data type:
- **Dates**: `DatePicker`
- **Date + time**: `DateTimePicker`
- **Years**: `Select` with a generated range (e.g., 1990 to current+1) — prevents typos
- **Enums/finite options**: `Select` with `->options(Enum::class)`
- **Boolean states**: `Toggle` or `Checkbox`
- **Countries/currencies**: `Select` with a predefined list

## Example
```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;

// Bad: TextInput::make('year') — allows "20025", "next year", etc.
// Good:
Select::make('year')
    ->options(array_combine(
        range(now()->year + 1, 1990),
        range(now()->year + 1, 1990),
    ))
    ->searchable(),

// Bad: TextInput::make('date_of_birth') — no format guidance
// Good:
DatePicker::make('date_of_birth')
    ->native(false)
    ->displayFormat('d/m/Y'),

DateTimePicker::make('pickup_at')
    ->native(false)
    ->displayFormat('d/m/Y H:i')
    ->seconds(false),
```
