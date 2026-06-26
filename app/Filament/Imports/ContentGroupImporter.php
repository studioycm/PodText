<?php

namespace App\Filament\Imports;

use App\Enums\PublicationStatus;
use App\Filament\Imports\Concerns\ConfiguresContentImports;
use App\Models\ContentGroup;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class ContentGroupImporter extends Importer
{
    use ConfiguresContentImports;

    protected static ?string $model = ContentGroup::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('reference_key')
                ->label(__('admin.fields.reference_key'))
                ->example('01JGROUP000000000000000001')
                ->rules(fn (?ContentGroup $record): array => [
                    'nullable',
                    'ulid',
                    Rule::unique('content_groups', 'reference_key')->ignore($record?->getKey()),
                ]),
            ImportColumn::make('title')
                ->label(__('admin.fields.title'))
                ->requiredMapping()
                ->example('פודקאסט לדוגמה')
                ->rules(fn (?ContentGroup $record, array $options): array => [
                    Rule::requiredIf(static::shouldRequireValue($record, $options)),
                    'max:255',
                ])
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('slug')
                ->label(__('admin.fields.slug'))
                ->example('example-podcast')
                ->rules(fn (?ContentGroup $record): array => [
                    'nullable',
                    'max:255',
                    Rule::unique('content_groups', 'slug')->ignore($record?->getKey()),
                ])
                ->ignoreBlankState(fn (?ContentGroup $record): bool => $record?->exists ?? false),
            ImportColumn::make('group_type_label_singular')
                ->label(__('admin.fields.group_type_label_singular'))
                ->example('Podcast')
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('group_type_label_plural')
                ->label(__('admin.fields.group_type_label_plural'))
                ->example('Podcasts')
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('default_item_type_label_singular')
                ->label(__('admin.fields.default_item_type_label_singular'))
                ->example('Episode')
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('default_item_type_label_plural')
                ->label(__('admin.fields.default_item_type_label_plural'))
                ->example('Episodes')
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('description_markdown')
                ->label(__('admin.fields.description_markdown'))
                ->examples([
                    'תיאור בעברית עם **Markdown**.',
                    "פסקה ראשונה\n\nפסקה שנייה.",
                ])
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('original_language_code')
                ->label(__('admin.fields.original_language_code'))
                ->example('he')
                ->rules(['nullable', Rule::in(config('localization.available_locales', ['he', 'en']))])
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('status')
                ->label(__('admin.fields.status'))
                ->example(PublicationStatus::Draft->value)
                ->rules(['nullable', Rule::in(array_column(PublicationStatus::cases(), 'value'))])
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('published_at')
                ->label(__('admin.fields.published_at'))
                ->example('2026-01-01 10:00:00')
                ->rules(['nullable', 'date'])
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
        ];
    }

    public function resolveRecord(): ContentGroup
    {
        /** @var ContentGroup $contentGroup */
        $contentGroup = $this->resolveRecordByReferenceKey(ContentGroup::class);

        return $contentGroup;
    }

    protected function beforeFill(): void
    {
        if ($this->record?->exists && (($this->options['blank_update_behavior'] ?? 'preserve') === 'preserve')) {
            return;
        }

        $this->data['group_type_label_singular'] ??= 'Podcast';
        $this->data['group_type_label_plural'] ??= 'Podcasts';
        $this->data['default_item_type_label_singular'] ??= 'Episode';
        $this->data['default_item_type_label_plural'] ??= 'Episodes';
        $this->data['original_language_code'] ??= 'he';
        $this->data['status'] ??= PublicationStatus::Draft->value;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your content group import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
