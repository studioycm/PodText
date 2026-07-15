# Rule: Date Constraints

## Severity
High

## Problem
Date fields without constraints allow invalid dates that cause real business problems. A `drivers_license_expiry` field with no `->minDate()` lets the admin register a customer with an already-expired license. A `pickup_at` field with no minimum lets the admin create a booking in the past. These are data quality issues that surface downstream as customer complaints or operational errors.

## Detection
- DatePicker fields with no `->minDate()` or `->maxDate()` constraints
- Expiry date fields that should require future dates
- Booking/scheduling dates that should not allow past dates
- Date-time fields where `->seconds(false)` would reduce clutter

## Recommendation
- Add `->minDate(now())` for dates that must be in the future (bookings, expiry dates)
- Add `->maxDate(now())` for dates that must be in the past (birth dates, historical records)
- Use `->seconds(false)` to drop seconds precision when not needed
- Use `DateTimePicker` when time selection is required
- Use the app's chosen display format consistently

## Example
```php
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;

// Expiry date — must be in the future
DatePicker::make('drivers_license_expiry')
    ->minDate(now())
    ->native(false)
    ->displayFormat('d/m/Y'),

// Booking date — no backdating
DateTimePicker::make('pickup_at')
    ->minDate(now())
    ->seconds(false)
    ->displayFormat('d/m/Y H:i'),

DateTimePicker::make('dropoff_at')
    ->after('pickup_at')
    ->seconds(false)
    ->displayFormat('d/m/Y H:i'),

// Birth date — must be in the past
DatePicker::make('date_of_birth')
    ->maxDate(now())
    ->native(false)
    ->displayFormat('d/m/Y'),
```
