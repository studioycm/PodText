# Rule: Slug Auto-Generation

## Severity
Medium

## Problem
Slug fields on Tour, BlogPost, Destination, Category, and similar resources are plain TextInputs with no auto-generation. The admin has to manually type a URL-safe slug, which is error-prone (spaces, uppercase, special characters) and wastes time. Most admins expect the slug to be derived from the title automatically.

## Detection
- A `slug` field that is a plain TextInput with no auto-fill behavior
- A `name`/`title` field exists alongside a `slug` field with no connection between them
- Slug fields that are editable on create but should auto-populate

## Recommendation
On create forms, auto-generate the slug from the title/name field while allowing
the application's intended manual-override policy. Use the application's shared
slugger so non-Latin locales and collision rules stay consistent. Filament 5
uses `hiddenOn('create')`, not `hiddenOnCreate()`. Do not hide the field when the
admin must review the generated public URL before saving.

## Example
```php
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('name')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
        if ($operation === 'create') {
            $set('slug', Str::slug($state ?? ''));
        }
    }),

TextInput::make('slug')
    ->required()
    ->unique()
    ->helperText('Auto-generated from name. Edit only if needed.'),
```

In a model-bound Filament 5 resource, `unique()` already ignores the current
record. Use scoped uniqueness when tenant/global-scope behavior requires it.
