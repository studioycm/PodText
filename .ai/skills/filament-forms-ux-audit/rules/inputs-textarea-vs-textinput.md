# Rule: Textarea vs TextInput

## Severity
Medium

## Problem
Multi-line content like short descriptions, excerpts, bios, or suspension reasons crammed into a single-line TextInput. The admin can't see what they've typed, can't format mentally, and the field gives no hint that longer content is expected. Fields like `short_description` and `excerpt` represent multi-sentence summaries that benefit from more space.

## Detection
- TextInput used for fields named description, bio, notes, excerpt, summary, reason, or similar
- Any field where the expected content is more than ~50 characters or multiple sentences
- Fields where `->maxLength()` suggests 160+ characters

## Recommendation
Switch to `Textarea::make()` with `->rows(3)` or `->rows(4)` to give the admin room. Add `->maxLength()` to guide expected length. Add `->helperText()` if there's a character limit for display purposes.

## Example
```php
use Filament\Forms\Components\Textarea;

// Bad: TextInput::make('short_description')
// Good:
Textarea::make('short_description')
    ->rows(3)
    ->maxLength(160)
    ->helperText('Shown in listing cards, keep under 160 characters.')
    ->columnSpanFull(),

Textarea::make('excerpt')
    ->rows(3)
    ->maxLength(200)
    ->columnSpanFull(),

Textarea::make('bio')
    ->rows(4)
    ->placeholder('Brief professional background...')
    ->columnSpanFull(),

Textarea::make('suspension_reason')
    ->rows(3)
    ->placeholder('Explain why this expert is being suspended...')
    ->columnSpanFull(),
```
