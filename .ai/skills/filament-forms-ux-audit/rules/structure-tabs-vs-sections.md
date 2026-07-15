# Rule: Tabs vs Sections

## Severity
Medium

## Problem
Tabs can reduce overload in large forms, but they also hide fields, validation errors, and dependencies. Using tabs too early makes scanning harder and can force the admin to hunt across multiple panels for related inputs.

## Detection
- Tabs used for a form that would be clear with a few well-named sections
- Related fields split across tabs without a strong reason
- Important validation errors or required fields hidden behind inactive tabs

## Recommendation
Prefer Sections for small and medium forms. Introduce Tabs only when sections alone still leave the form too long or cognitively dense. Keep tightly related fields in the same tab, and avoid hiding key dependencies across tabs.

## Example
```php
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

Section::make('Identity')
    ->columns(2)
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('slug'),
    ]),

Section::make('Publication')
    ->columns(2)
    ->schema([
        TextInput::make('meta_title'),
        TextInput::make('meta_description'),
    ]),
```
