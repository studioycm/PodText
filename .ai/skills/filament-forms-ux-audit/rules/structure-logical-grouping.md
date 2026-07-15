# Rule: Logical Grouping

## Severity
High

## Problem
Unrelated fields mixed together in the same section or grid make the form harder to scan. When a user sees "name" next to "engine_hp" next to "color", there's no mental model to anchor to. The brain context-switches for every field, slowing data entry and increasing errors.

A "General Info" tab with 16 fields in a single 2-column grid — identity fields (name, slug, brand) mixed with specs (body type, fuel, transmission) mixed with physical attributes (color, seats, doors) — is overwhelming even though the fields are individually simple.

## Detection
- Fields from different domains sitting side-by-side with no section boundary
- A single section or tab containing more than 6-8 fields from mixed categories
- No clear scanning path through the form

## Recommendation
Group fields by domain meaning, not by data type or creation order. Each Section should represent one concept the admin can think about as a unit. Use descriptive Section headings.

## Example
```php
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

Section::make('Identity')
    ->columns(2)
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('slug'),
        TextInput::make('brand'),
        Select::make('year')
            ->options(array_combine(
                range(now()->year + 1, 1990),
                range(now()->year + 1, 1990),
            )),
    ]),

Section::make('Specifications')
    ->columns(2)
    ->schema([
        Select::make('body_type')->options(BodyType::class),
        Select::make('fuel')->options(FuelType::class),
        Select::make('transmission')->options(Transmission::class),
        TextInput::make('engine'),
        TextInput::make('hp')->numeric(),
    ]),

Section::make('Physical')
    ->columns(2)
    ->schema([
        Select::make('color')->options(Color::class),
        TextInput::make('seats')->numeric(),
        TextInput::make('doors')->numeric(),
        TextInput::make('trunk')->suffix('liters'),
    ]),
```
