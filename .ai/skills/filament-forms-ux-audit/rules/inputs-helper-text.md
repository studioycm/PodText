# Rule: Helper Text

## Severity
Medium

## Problem
Ambiguous fields where the label alone doesn't communicate what values are expected. "Qualifications" could mean academic degrees, professional certifications, or informal skills. Without guidance, admins enter inconsistent data that's hard to search or filter later.

## Detection
- Fields with vague labels (qualifications, specialties, type, status, code)
- Fields where the expected values follow a convention not obvious from the label
- Fields where admins frequently enter inconsistent data

## Recommendation
Add `->helperText()` with specific examples or a brief explanation of what's expected. Use "e.g." with 2-3 comma-separated examples to show the pattern.

## Example
```php
use Filament\Forms\Components\TextInput;

TextInput::make('qualifications')
    ->helperText('e.g. ACCA, ACA, CIMA'),

TextInput::make('specialties')
    ->helperText('e.g. Tax Planning, Audit, Payroll'),

TextInput::make('short_description')
    ->helperText('Shown in listing cards, keep under 160 characters.'),
```
