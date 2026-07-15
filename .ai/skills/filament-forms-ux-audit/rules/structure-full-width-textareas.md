# Rule: Full-Width Textareas

## Severity
Medium

## Problem
Textarea and RichEditor fields placed inside a multi-column section get cramped into half-width, making them hard to read and write in. Description fields in resources like ExtraService or MaintenanceLog will look broken if squeezed into a 2-column layout.

## Detection
- `Textarea` or `RichEditor` inside a `->columns(2)` section without `->columnSpanFull()`
- Any multi-line content field that doesn't span the full form width

## Recommendation
Add `->columnSpanFull()` to all Textarea and RichEditor fields so they always take the full width, regardless of the parent section's column count.

## Example
```php
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;

Section::make('Details')
    ->columns(2)
    ->schema([
        TextInput::make('title'),
        TextInput::make('status'),

        Textarea::make('description')
            ->rows(4)
            ->placeholder('Brief professional background...')
            ->columnSpanFull(),

        RichEditor::make('notes')
            ->columnSpanFull(),
    ]),
```
