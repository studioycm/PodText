# Rule: Date Format Consistency

## Severity
Medium

## Problem
Different date formats across forms make dates harder to scan and can create ambiguity. However, a native picker and a JavaScript picker are not automatically inconsistent UX by themselves. The real problem is when the app has no intentional date-display convention or uses the wrong picker for the workflow.

## Detection
- DatePicker fields with no `->displayFormat()`
- Different `->displayFormat()` values across different forms
- Ambiguous numeric date formats that are not explained by app convention
- Use of `->native(false)` where keyboard-first entry matters, or `->native(true)` where the app relies on a custom visual date format

## Recommendation
Pick one primary display format for the app and apply it intentionally. Choose between native and JavaScript pickers based on the workflow:
- Use `->native(false)` when a consistent visual picker and formatted display reduce ambiguity
- Keep the native picker when keyboard input speed or platform-native behavior matters more

For date-time input, use `DateTimePicker`, not `DatePicker`.

## Example
```php
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;

DatePicker::make('date_of_birth')
    ->native(false)
    ->displayFormat('d/m/Y'),

DateTimePicker::make('pickup_at')
    ->native(false)
    ->displayFormat('d/m/Y H:i')
    ->seconds(false),
```
