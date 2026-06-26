<?php

namespace App\Filament\Imports;

use App\Enums\PublicationStatus;
use App\Filament\Imports\Concerns\ConfiguresContentImports;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Rules\ApprovedEmbedUrl;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class ContentItemImporter extends Importer
{
    use ConfiguresContentImports;

    protected static ?string $model = ContentItem::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('reference_key')
                ->label(__('admin.fields.reference_key'))
                ->example('01JITEM0000000000000000001')
                ->rules(fn (?ContentItem $record): array => [
                    'nullable',
                    'ulid',
                    Rule::unique('content_items', 'reference_key')->ignore($record?->getKey()),
                ]),
            ImportColumn::make('content_group_reference_key')
                ->label(__('admin.import.columns.content_group_reference_key'))
                ->requiredMapping()
                ->example('01JGROUP000000000000000001')
                ->rules(['required', 'ulid'])
                ->fillRecordUsing(function (ContentItem $record, ?string $state): void {
                    $contentGroup = ContentGroup::query()
                        ->where('reference_key', $state)
                        ->first();

                    if (! $contentGroup) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_content_group', [
                            'reference_key' => $state,
                        ]));
                    }

                    $record->contentGroup()->associate($contentGroup);
                }),
            ImportColumn::make('title')
                ->label(__('admin.fields.title'))
                ->requiredMapping()
                ->example('פרק לדוגמה')
                ->rules(fn (?ContentItem $record, array $options): array => [
                    Rule::requiredIf(static::shouldRequireValue($record, $options)),
                    'max:255',
                ])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('slug')
                ->label(__('admin.fields.slug'))
                ->example('example-episode')
                ->rules(fn (?ContentItem $record, array $data): array => [
                    'nullable',
                    'max:255',
                    Rule::unique('content_items', 'slug')
                        ->where('content_group_id', ContentGroup::query()
                            ->where('reference_key', $data['content_group_reference_key'] ?? null)
                            ->value('id'))
                        ->ignore($record?->getKey()),
                ])
                ->ignoreBlankState(fn (?ContentItem $record): bool => $record?->exists ?? false),
            ImportColumn::make('type_label_singular_override')
                ->label(__('admin.fields.type_label_singular_override'))
                ->example('Interview')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('description_markdown')
                ->label(__('admin.fields.description_markdown'))
                ->example('תיאור הפרק עם **Markdown**.')
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('media_url')
                ->label(__('admin.fields.media_url'))
                ->requiredMapping()
                ->example('https://example.com/media/example-episode')
                ->rules(fn (?ContentItem $record, array $options): array => [
                    Rule::requiredIf(static::shouldRequireValue($record, $options)),
                    'url',
                    'max:2048',
                ])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('embed_url')
                ->label(__('admin.fields.embed_url'))
                ->example('https://www.youtube.com/embed/example')
                ->rules(['nullable', 'url', 'max:2048', new ApprovedEmbedUrl])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('duration_seconds')
                ->label(__('admin.fields.duration_seconds'))
                ->integer()
                ->example('125')
                ->rules(['nullable', 'integer', 'min:0'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('transcript_markdown')
                ->label(__('admin.fields.transcript_markdown'))
                ->examples([
                    "## תמלול\n\nשורה ראשונה עם ניקוד: שָׁלוֹם.",
                    "שורה ראשונה\nשורה שנייה\n\n**סיום**",
                ])
                ->rules(['nullable', 'max:100000'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('original_published_at')
                ->label(__('admin.fields.original_published_at'))
                ->example('2026-01-01 09:00:00')
                ->rules(['nullable', 'date'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('status')
                ->label(__('admin.fields.status'))
                ->example(PublicationStatus::Draft->value)
                ->rules(['nullable', Rule::in(array_column(PublicationStatus::cases(), 'value'))])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('published_at')
                ->label(__('admin.fields.published_at'))
                ->example('2026-01-01 10:00:00')
                ->rules(['nullable', 'date'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('author_reference_keys')
                ->label(__('admin.import.columns.author_reference_keys'))
                ->multiple('|')
                ->example('01JAUTHOR00000000000000001|01JAUTHOR00000000000000002')
                ->nestedRecursiveRules(['ulid'])
                ->rules([
                    function (string $attribute, mixed $state, \Closure $fail): void {
                        if (blank($state)) {
                            return;
                        }

                        $authorReferenceKeys = collect($state);
                        $resolvedReferenceKeys = Author::query()
                            ->whereIn('reference_key', $authorReferenceKeys)
                            ->pluck('reference_key');

                        $missingReferenceKeys = $authorReferenceKeys->diff($resolvedReferenceKeys);

                        if ($missingReferenceKeys->isNotEmpty()) {
                            $fail(__('admin.import.failures.unresolved_authors', [
                                'reference_keys' => $missingReferenceKeys->implode('|'),
                            ]));
                        }
                    },
                ])
                ->fillRecordUsing(fn (): null => null)
                ->saveRelationshipsUsing(function (ContentItem $record, array $state): void {
                    $authorIds = Author::query()
                        ->whereIn('reference_key', $state)
                        ->pluck('id', 'reference_key');

                    $missingReferenceKeys = collect($state)
                        ->diff($authorIds->keys());

                    if ($missingReferenceKeys->isNotEmpty()) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_authors', [
                            'reference_keys' => $missingReferenceKeys->implode('|'),
                        ]));
                    }

                    $record->authors()->sync($authorIds->values()->all());
                }),
        ];
    }

    public function resolveRecord(): ContentItem
    {
        /** @var ContentItem $contentItem */
        $contentItem = $this->resolveRecordByReferenceKey(ContentItem::class);

        return $contentItem;
    }

    protected function beforeFill(): void
    {
        if ($this->record?->exists && (($this->options['blank_update_behavior'] ?? 'preserve') === 'preserve')) {
            return;
        }

        $this->data['status'] ??= PublicationStatus::Draft->value;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your content item import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
