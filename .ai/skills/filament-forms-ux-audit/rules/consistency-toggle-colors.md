# Rule: Toggle Colors

## Severity
Low

## Problem
In toggle-heavy forms, neutral styling can make it slower to scan current state. However, adding red/green semantics everywhere can also overstate the meaning of simple booleans. This is only a UX issue when quick visual scanning matters.

## Detection
- Toggle fields with no `->onColor()` or `->offColor()`
- Multiple toggles in the same section where quick scanning matters
- Boolean fields where the on/off state has significant meaning (active/inactive, enabled/disabled)

## Recommendation
Use explicit toggle colors only when the form contains many toggles or when state needs to be scanned quickly. Choose colors that match the meaning of the state. For neutral preferences, the default off-state may be clearer than `danger`.

## Example
```php
use Filament\Forms\Components\Toggle;

Toggle::make('is_active')
    ->onColor('success')
    ->offColor('danger'),

Toggle::make('email_notifications')
    ->onColor('success'),

Toggle::make('sms_notifications')
    ->onColor('success'),
```
