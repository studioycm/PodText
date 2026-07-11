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
use Illuminate\Support\Collection;
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
                    'max:26',
                    Rule::unique('transcriptions', 'reference_key')->ignore($record?->getKey()),
                ]),
            ImportColumn::make('content_item_reference_key')
                ->label(__('admin.import.columns.content_item_reference_key'))
                ->requiredMapping()
                ->example('01JITEM0000000000000000001')
                ->rules(['required', 'ulid', 'max:26'])
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
                ->example('01JAUTHOR00000000000000001')
                ->rules(['nullable', 'ulid', 'max:26'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('primary_transcriber_reference_key')
                ->label(__('admin.import.columns.primary_transcriber_reference_key'))
                ->example('01JAUTHOR00000000000000001')
                ->rules(['nullable', 'ulid', 'max:26'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('transcriber_reference_keys')
                ->label(__('admin.import.columns.transcriber_reference_keys'))
                ->multiple('|')
                ->example('01JAUTHOR00000000000000001|01JAUTHOR00000000000000002')
                ->nestedRecursiveRules(['ulid', 'max:26'])
                ->rules([
                    function (string $attribute, mixed $state, \Closure $fail): void {
                        if (blank($state)) {
                            return;
                        }

                        $referenceKeys = collect($state)->filter()->values();
                        $resolvedReferenceKeys = Author::query()
                            ->whereIn('reference_key', $referenceKeys)
                            ->pluck('reference_key');
                        $missingReferenceKeys = $referenceKeys->diff($resolvedReferenceKeys);

                        if ($missingReferenceKeys->isNotEmpty()) {
                            $fail(__('admin.import.failures.unresolved_transcribers', [
                                'reference_keys' => $missingReferenceKeys->implode('|'),
                            ]));
                        }
                    },
                ])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('transcriber_names')
                ->label(__('admin.import.columns.transcriber_names'))
                ->multiple('|')
                ->example('Dana Cohen|Noam Levi')
                ->nestedRecursiveRules(['string', 'max:255'])
                ->rules([
                    function (string $attribute, mixed $state, \Closure $fail): void {
                        if (blank($state)) {
                            return;
                        }

                        $names = collect($state)->filter()->values();
                        $resolvedNames = Author::query()
                            ->whereIn('name', $names)
                            ->pluck('name');
                        $missingNames = $names->diff($resolvedNames);

                        if ($missingNames->isNotEmpty()) {
                            $fail(__('admin.import.failures.unresolved_transcriber_names', [
                                'names' => $missingNames->implode('|'),
                            ]));
                        }
                    },
                ])
                ->fillRecordUsing(fn (): null => null),
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
        $primaryTranscriber = $this->resolvePrimaryTranscriber();

        $transcription = Transcription::query()
            ->where('content_item_id', $contentItem->getKey())
            ->where(function ($query) use ($primaryTranscriber): void {
                $query
                    ->where('author_id', $primaryTranscriber->getKey())
                    ->orWhereHas('authors', fn ($query) => $query->whereKey($primaryTranscriber));
            })
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
        $transcriberIds = $this->resolvedTranscriberIds();

        if ($transcriberIds !== []) {
            $this->record->forceFill(['author_id' => $transcriberIds[0]]);
        } elseif (! $this->record?->exists) {
            throw new RowImportFailedException(__('admin.import.failures.missing_transcriber'));
        }

        if (($this->data['status'] ?? null) !== PublicationStatus::Published->value) {
            return;
        }

        if (filled($this->data['transcript_markdown'] ?? null) || filled($this->record?->transcript_markdown)) {
            return;
        }

        throw new RowImportFailedException(__('admin.import.failures.published_transcription_requires_markdown'));
    }

    protected function afterSave(): void
    {
        $transcriberIds = $this->resolvedTranscriberIds();

        if ($transcriberIds === []) {
            return;
        }

        $this->record->syncTranscribers($transcriberIds);
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

    private function resolvePrimaryTranscriber(): Author
    {
        $transcriberIds = $this->resolvedTranscriberIds();

        if ($transcriberIds === []) {
            throw new RowImportFailedException(__('admin.import.failures.missing_transcriber'));
        }

        return Author::query()->findOrFail($transcriberIds[0]);
    }

    /**
     * @return array<int, int>
     */
    private function resolvedTranscriberIds(): array
    {
        $ids = collect();

        foreach ($this->transcriberReferenceKeys() as $referenceKey) {
            $ids->push($this->resolveAuthor($referenceKey)->getKey());
        }

        foreach ($this->transcriberNames() as $name) {
            $author = Author::query()->where('name', $name)->first();

            if (! $author) {
                throw new RowImportFailedException(__('admin.import.failures.unresolved_transcriber_names', [
                    'names' => $name,
                ]));
            }

            $ids->push($author->getKey());
        }

        return $ids
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function transcriberReferenceKeys(): array
    {
        $keys = collect();
        $primaryReferenceKey = $this->data['primary_transcriber_reference_key'] ?? null;
        $legacyReferenceKey = $this->data['author_reference_key'] ?? null;

        if (filled($primaryReferenceKey)) {
            $keys->push($primaryReferenceKey);
        } elseif (filled($legacyReferenceKey)) {
            $keys->push($legacyReferenceKey);
        }

        $keys = $keys->merge($this->normalizedList($this->data['transcriber_reference_keys'] ?? []));

        if (filled($legacyReferenceKey)) {
            $keys->push($legacyReferenceKey);
        }

        return $keys
            ->filter()
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function transcriberNames(): array
    {
        return $this->normalizedList($this->data['transcriber_names'] ?? [])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizedList(mixed $value): Collection
    {
        if (blank($value)) {
            return collect();
        }

        if (is_array($value)) {
            return collect($value);
        }

        return collect(explode('|', (string) $value));
    }
}
