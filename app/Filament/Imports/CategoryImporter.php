<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\ConfiguresContentImports;
use App\Models\Category;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Validation\Rule;

class CategoryImporter extends Importer
{
    use ConfiguresContentImports;

    protected static ?string $model = Category::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('path')
                ->label(__('admin.import.columns.category_path'))
                ->example('torah/interviews')
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('name')
                ->label(__('admin.fields.name'))
                ->requiredMapping()
                ->example('Interviews')
                ->rules(fn (?Category $record, array $options): array => [
                    Rule::requiredIf(static::shouldRequireValue($record, $options)),
                    'max:255',
                ])
                ->ignoreBlankState(fn (?Category $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('slug')
                ->label(__('admin.fields.slug'))
                ->example('interviews')
                ->rules(fn (?Category $record): array => [
                    'nullable',
                    'max:255',
                    Rule::unique('categories', 'slug')->ignore($record?->getKey()),
                ])
                ->ignoreBlankState(fn (?Category $record): bool => $record?->exists ?? false),
            ImportColumn::make('parent_slug')
                ->label(__('admin.import.columns.parent_category_path'))
                ->example('torah')
                ->fillRecordUsing(function (Category $record, ?string $state): void {
                    if (blank($state)) {
                        $record->parent()->dissociate();

                        return;
                    }

                    $parent = static::resolveCategoryPath((string) $state);

                    if (! $parent) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_parent_category', [
                            'path' => $state,
                        ]));
                    }

                    $record->parent()->associate($parent);
                }),
            ImportColumn::make('is_visible')
                ->label(__('admin.fields.is_visible'))
                ->boolean()
                ->example('true')
                ->rules(['nullable', 'boolean'])
                ->ignoreBlankState(fn (?Category $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('sort_order')
                ->label(__('admin.fields.sort_order'))
                ->integer()
                ->example('10')
                ->rules(['nullable', 'integer'])
                ->ignoreBlankState(fn (?Category $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('description_markdown')
                ->label(__('admin.fields.description_markdown'))
                ->example('Category **description**.')
                ->ignoreBlankState(fn (?Category $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
        ];
    }

    public function resolveRecord(): Category
    {
        $this->normalizePathData();

        $category = filled($this->data['path'] ?? null)
            ? static::resolveCategoryPath((string) $this->data['path'])
            : null;

        $category ??= filled($this->data['slug'] ?? null)
            ? Category::query()->where('slug', $this->data['slug'])->first()
            : null;

        if ($category && $this->importMode() === 'create') {
            throw new RowImportFailedException(__('admin.import.failures.create_found_existing_category', [
                'path' => $this->data['path'] ?? $this->data['slug'],
            ]));
        }

        if (! $category && $this->importMode() === 'update') {
            throw new RowImportFailedException(__('admin.import.failures.update_missing_category', [
                'path' => $this->data['path'] ?? $this->data['slug'],
            ]));
        }

        return $category ?? new Category(array_filter([
            'slug' => $this->data['slug'] ?? null,
        ]));
    }

    protected function beforeValidate(): void
    {
        $this->normalizePathData();
    }

    protected function beforeFill(): void
    {
        if ($this->record?->exists && (($this->options['blank_update_behavior'] ?? 'preserve') === 'preserve')) {
            return;
        }

        $this->data['is_visible'] ??= true;
        $this->data['sort_order'] ??= 0;
    }

    private function normalizePathData(): void
    {
        if (blank($this->data['path'] ?? null)) {
            return;
        }

        $segments = collect(explode('/', (string) $this->data['path']))
            ->map(fn (string $segment): string => trim($segment))
            ->filter()
            ->values();

        if ($segments->isEmpty()) {
            return;
        }

        $this->data['slug'] = filled($this->data['slug'] ?? null)
            ? $this->data['slug']
            : $segments->last();

        if ($segments->count() > 1 && blank($this->data['parent_slug'] ?? null)) {
            $this->data['parent_slug'] = $segments->slice(0, -1)->implode('/');
        }
    }
}
