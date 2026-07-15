# Rule: Relationship Select UX

## Severity
Medium

## Problem
Relationship fields become slow and error-prone when admins have to scroll long option lists or guess how records are labeled. This is especially painful for users, categories, customers, and other shared entities with large datasets.

## Detection
- `Select` fields backed by large relationships with no `->searchable()`
- Relationship fields where labels are ambiguous or hard to distinguish
- Long option lists loaded without clear reason

## Recommendation
For relationship-backed selects, make lookup fast and obvious. Use
`->relationship()` with a meaningful title attribute or label callback. Keep
tiny finite sets plain. Preload only bounded sets. For growing tables, use
server-side `->searchable([...])` without preload, cap results with
`->optionsLimit()`, constrain the relationship query, and verify that existing
selected values still resolve and validate.

## Example
```php
use Filament\Forms\Components\Select;

Select::make('customer_id')
    ->relationship('customer', 'name')
    ->searchable(['name', 'email'])
    ->optionsLimit(50),
```
