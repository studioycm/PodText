# Rule: Section Height Balance

## Severity
Medium

## Problem
When Sections actually render side-by-side through the page's schema grid or an
explicit parent Grid, very different heights can create an unbalanced layout.
Verify the real responsive layout before flagging this from source alone.

This commonly happens when grouping by purpose alone: one domain (e.g., "General") accumulates many fields while others (e.g., "Billing", "Schedule") have only 2-3 fields each.

## Detection
- One section has 5+ fields (especially with a RichEditor or Textarea) while an adjacent section has 2-3 fields
- Large vertical whitespace visible next to a short section
- A section containing a mix of compact fields and tall fields (RichEditor, Textarea) that inflates its height far beyond neighbors

## Recommendation
Balance visual height across side-by-side sections. Strategies:

1. **Stack smaller sections vertically on one side.** Place the tall section on the left spanning one column, and stack 2-3 shorter sections on the right so they fill the same vertical space.
2. **Split a tall section.** If a section has 6+ fields, check if it can be split into two meaningful sub-sections (e.g., "Identity" and "Details").
3. **Move fields between sections.** A field like "status" might logically fit in either "General" or a smaller section — place it where it helps balance.
4. **Use `columnSpan(1)` on sections** inside a parent Grid to control which sections sit side-by-side vs. stack.
5. Add `->columnSpanFull()` on the outer Grid when its parent schema has
   multiple columns and the grid is intended to use the full available width.

The goal is roughly equal visual height between adjacent columns, not exact field counts. A section with a RichEditor counts as ~3-4 short fields vertically.

## Example
```php
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;

// columnSpanFull() ensures the Grid uses the full form width
// Left column: tall section with identity + description
// Right column: 3 shorter sections stacked to match height
Grid::make(2)
    ->columnSpanFull()
    ->schema([
        Section::make('General')
            ->schema([
                TextInput::make('name')->required(),
                Select::make('client_id')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('project_lead_id')
                    ->relationship('projectLead', 'name')
                    ->searchable()
                    ->preload(),
                RichEditor::make('description'),
            ]),

        // Right side: multiple short sections stacked
        Grid::make(1)
            ->schema([
                Section::make('Status')
                    ->schema([
                        Select::make('status')
                            ->options(ProjectStatus::class)
                            ->required(),
                    ]),

                Section::make('Billing')
                    ->schema([
                        Select::make('billing_type')
                            ->options(BillingType::class)
                            ->required(),
                        TextInput::make('estimated_hours')
                            ->numeric()
                            ->suffix('hours'),
                    ]),

                Section::make('Schedule')
                    ->schema([
                        DatePicker::make('starts_at'),
                        DatePicker::make('ends_at'),
                        DatePicker::make('deadline'),
                    ]),
            ]),
    ]),

Textarea::make('notes')->rows(3)->columnSpanFull(),
```
