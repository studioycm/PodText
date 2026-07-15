# Rule: Unit Suffixes

## Severity
Medium

## Problem
Numeric fields without units are ambiguous. "Hourly Rate: 50" — is that GBP, EUR, USD? "Experience: 5" — years, months, projects? The admin has to guess, and different admins will interpret it differently, leading to inconsistent data. When currency is shown as a prefix but the rate also needs a time unit, both prefix and suffix should be used for full clarity.

## Detection
- Numeric fields with no `->suffix()` or `->prefix()` where the unit is non-obvious
- Rate fields (hourly_rate, daily_rate) without currency prefix AND time unit suffix
- Duration, distance, weight, or capacity fields without unit indicators

## Recommendation
Add `->suffix()` for units (years, /hr, kg, km, liters) and `->prefix()` for currency symbols (£, €, $). Use both when the field represents a rate.

## Example
```php
use Filament\Forms\Components\TextInput;

TextInput::make('years_of_experience')
    ->numeric()
    ->suffix('years'),

TextInput::make('hourly_rate')
    ->numeric()
    ->prefix('£')
    ->suffix('/hr'),

TextInput::make('trunk_capacity')
    ->numeric()
    ->suffix('liters'),

TextInput::make('mileage')
    ->numeric()
    ->suffix('km'),
```
