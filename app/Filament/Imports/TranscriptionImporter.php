<?php

namespace App\Filament\Imports;

use App\Enums\PublicationStatus;
use App\Filament\Imports\Concerns\ConfiguresContentImports;
use App\Models\Author;
use App\Models\ContentItem;
use App\Models\Transcription;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class TranscriptionImporter extends Importer
{
    use ConfiguresContentImports;

    protected static ?string $model = Transcription::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('reference_key')
                ->label(__('admin.fields.reference_key'))
                ->example('01JTRANSCRIPTION00000000001')
                ->rules(fn (?Transcription $record): array => [
                    'nullable',
                    'ulid',
                    Rule::unique('transcriptions', 'reference_key')->ignore($record?->getKey()),
                ]),
            ImportColumn::make('content_item_reference_key')
                ->label(__('admin.import.columns.content_item_reference_key'))
                ->requiredMapping()
                ->example('01JITEM0000000000000000001')
                ->rules(['required', 'ulid'])
                ->fillRecordUsing(function (Transcription $record, ?string $state): void {
                    $contentItem = filled($state)
                        ? ContentItem::query()->where('reference_key', $state)->first()
                        : null;

                    if (! $contentItem) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_content_item', [
                            'reference_key' => $state,
                        ]));
                    }

                    $record->contentItem()->associate($contentItem);
                }),
            ImportColumn::make('author_reference_key')
                ->label(__('admin.import.columns.author_reference_key'))
                ->requiredMapping()
                ->example('01JAUTHOR00000000000000001')
                ->rules(['required', 'ulid'])
                ->fillRecordUsing(function (Transcription $record, ?string $state): void {
                    $author = filled($state)
                        ? Author::query()->where('reference_key', $state)->first()
                        : null;

                    if (! $author) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_author', [
                            'reference_key' => $state,
                        ]));
                    }

                    $record->author()->associate($author);
                }),
            ImportColumn::make('title')
                ->label(__('admin.fields.title'))
                ->example('Reviewed transcript')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState(fn (?Transcription $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('language_code')
                ->label(__('admin.fields.language_code'))
                ->example('he')
                ->rules(fn (?Transcription $record, array $options): array => [
                    Rule::requiredIf(static::shouldRequireValue($record, $options)),
                    'max:10',
                ])
                ->ignoreBlankState(fn (?Transcription $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('transcript_markdown')
                ->label(__('admin.fields.transcript_markdown'))
                ->requiredMappingForNewRecordsOnly()
                ->example("Speaker: Transcript line\n\nSecond paragraph")
                ->ignoreBlankState(fn (?Transcription $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('status')
                ->label(__('admin.fields.status'))
                ->example(PublicationStatus::Draft->value)
                ->rules(['nullable', Rule::in(array_column(PublicationStatus::cases(), 'value'))])
                ->ignoreBlankState(fn (?Transcription $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('published_at')
                ->label(__('admin.fields.published_at'))
                ->example('30/06/2026 13:45')
                ->castStateUsing(fn (mixed $state): mixed => static::castImportedDateTime($state))
                ->rules(['nullable'])
                ->ignoreBlankState(fn (?Transcription $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
        ];
    }

    public function resolveRecord(): Transcription
    {
        if (filled($this->data['reference_key'] ?? null)) {
            /** @var Transcription $transcription */
            $transcription = $this->resolveRecordByReferenceKey(Transcription::class);

            return $transcription;
        }

        $contentItem = $this->resolveContentItem($this->data['content_item_reference_key'] ?? null);
        $author = $this->resolveAuthor($this->data['author_reference_key'] ?? null);

        $transcription = Transcription::query()
            ->where('content_item_id', $contentItem->getKey())
            ->where('author_id', $author->getKey())
            ->when(
                filled($this->data['published_at'] ?? null),
                fn ($query) => $query->where('published_at', $this->data['published_at']),
                fn ($query) => $query->whereNull('published_at'),
            )
            ->first();

        if ($transcription && $this->importMode() === 'create') {
            throw new RowImportFailedException(__('admin.import.failures.create_found_existing_transcription'));
        }

        if (! $transcription && $this->importMode() === 'update') {
            throw new RowImportFailedException(__('admin.import.failures.update_missing_transcription'));
        }

        return $transcription ?? new Transcription;
    }

    protected function beforeFill(): void
    {
        if ($this->record?->exists && (($this->options['blank_update_behavior'] ?? 'preserve') === 'preserve')) {
            return;
        }

        $this->data['language_code'] ??= 'he';
        $this->data['status'] ??= PublicationStatus::Draft->value;
    }

    protected function beforeSave(): void
    {
        if (($this->data['status'] ?? null) !== PublicationStatus::Published->value) {
            return;
        }

        if (filled($this->data['transcript_markdown'] ?? null) || filled($this->record?->transcript_markdown)) {
            return;
        }

        throw new RowImportFailedException(__('admin.import.failures.published_transcription_requires_markdown'));
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your transcription import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    private function resolveContentItem(mixed $referenceKey): ContentItem
    {
        $contentItem = filled($referenceKey)
            ? ContentItem::query()->where('reference_key', $referenceKey)->first()
            : null;

        if (! $contentItem) {
            throw new RowImportFailedException(__('admin.import.failures.unresolved_content_item', [
                'reference_key' => $referenceKey,
            ]));
        }

        return $contentItem;
    }

    private function resolveAuthor(mixed $referenceKey): Author
    {
        $author = filled($referenceKey)
            ? Author::query()->where('reference_key', $referenceKey)->first()
            : null;

        if (! $author) {
            throw new RowImportFailedException(__('admin.import.failures.unresolved_author', [
                'reference_key' => $referenceKey,
            ]));
        }

        return $author;
    }
}
