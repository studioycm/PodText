# Rule: Autofocus First Field

## Severity
Low

## Problem
Autofocus can speed up repetitive create flows, but it is not always the right default. In modals, on mobile, or in edit forms, it can be distracting or can move the viewport unexpectedly.

## Detection
- High-frequency create form where the first field is almost always the next action
- No modal or mobile behavior that would make autofocus disruptive

## Recommendation
Use `->autofocus()` selectively on simple, repetitive create flows where admins benefit from immediate typing. Do not treat missing autofocus as a default UX flaw.

## Example
```php
use Filament\Forms\Components\TextInput;

TextInput::make('name')
    ->required()
    ->autofocus(),
```
