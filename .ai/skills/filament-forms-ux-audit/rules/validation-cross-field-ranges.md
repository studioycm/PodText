# Rule: Cross-Field Ranges

## Severity
High

## Problem
Paired fields like start/end dates, min/max quantities, or price ranges can each be valid on their own while still producing an invalid combination. Without cross-field validation, the form accepts contradictory input that breaks downstream logic.

## Detection
- Start/end, from/to, or min/max fields with no relationship between them
- Range fields that are visually paired but not validated together
- Booking or scheduling forms where end can be before start

## Recommendation
Validate the relationship between the paired fields, not just each field individually. Add clear labels and keep the pair visually grouped so the expected relationship is obvious before submission.

## Example
```php
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;

Grid::make(2)->schema([
    DateTimePicker::make('starts_at')
        ->required()
        ->seconds(false),
    DateTimePicker::make('ends_at')
        ->required()
        ->seconds(false)
        ->after('starts_at'),
]),
```
