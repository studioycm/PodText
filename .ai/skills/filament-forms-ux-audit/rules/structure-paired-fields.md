# Rule: Paired Fields

## Severity
Medium

## Problem
Semantically paired fields (min/max, start/end, duration_days/duration_nights, group_size_min/group_size_max) displayed as separate full-width rows waste space and obscure the relationship between them. The admin doesn't immediately see that these fields are a pair — they look like independent inputs.

## Detection
- Fields with matching prefixes or complementary names (min/max, start/end, from/to)
- Duration pairs, range pairs, or date pairs on separate rows
- Related fields that should be visually compared side-by-side

## Recommendation
Place paired fields in a `Grid::make(2)` so they sit side-by-side. This makes the relationship obvious and saves vertical space.

## Example
```php
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;

Grid::make(2)->schema([
    TextInput::make('group_size_min')
        ->numeric()
        ->label('Min Group Size'),
    TextInput::make('group_size_max')
        ->numeric()
        ->label('Max Group Size'),
]),

Grid::make(2)->schema([
    TextInput::make('duration_days')
        ->numeric()
        ->suffix('days'),
    TextInput::make('duration_nights')
        ->numeric()
        ->suffix('nights'),
]),

Grid::make(2)->schema([
    DateTimePicker::make('pickup_at')
        ->displayFormat('d/m/Y H:i')
        ->seconds(false),
    DateTimePicker::make('dropoff_at')
        ->displayFormat('d/m/Y H:i')
        ->seconds(false)
        ->after('pickup_at'),
]),
```
