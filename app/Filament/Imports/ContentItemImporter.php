<?php

namespace App\Filament\Imports;

use App\Enums\PublicationStatus;
use App\Filament\Imports\Concerns\ConfiguresContentImports;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Rules\ApprovedEmbedUrl;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
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
                    'max:26',
                    Rule::unique('content_items', 'reference_key')->ignore($record?->getKey()),
                ]),
            ImportColumn::make('content_group_reference_key')
                ->label(__('admin.import.columns.content_group_reference_key'))
                ->requiredMapping()
                ->example('01JGROUP000000000000000001')
                ->rules(['required', 'ulid', 'max:26'])
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
                    'starts_with:https://',
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
            ImportColumn::make('embed_provider')
                ->label(__('admin.fields.embed_provider'))
                ->example('youtube')
                ->rules(['nullable', 'max:50'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('media_duration_seconds')
                ->label(__('admin.fields.media_duration_seconds'))
                ->integer()
                ->example('125')
                ->rules(['nullable', 'integer', 'min:0'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('external_id')
                ->label(__('admin.fields.external_id'))
                ->example('abc123')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('external_title')
                ->label(__('admin.fields.external_title'))
                ->example('Provider title')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('external_description')
                ->label(__('admin.fields.external_description'))
                ->example('Provider description')
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('external_thumbnail_url')
                ->label(__('admin.fields.external_thumbnail_url'))
                ->example('https://example.com/thumb.jpg')
                ->rules(['nullable', 'url', 'starts_with:https://', 'max:2048'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('external_published_at')
                ->label(__('admin.fields.external_published_at'))
                ->example('30/06/2026 13:45')
                ->castStateUsing(fn (mixed $state): mixed => static::castImportedDateTime($state))
                ->rules(['nullable'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('media_metadata')
                ->label(__('admin.fields.media_metadata'))
                ->example('{"duration":"PT2M5S"}')
                ->castStateUsing(fn (mixed $state): ?array => static::castImportedJson($state))
                ->rules(['nullable'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('direct_media_url')
                ->label(__('admin.fields.direct_media_url'))
                ->example('https://example.com/audio.mp3')
                ->rules(['nullable', 'url', 'starts_with:https://', 'max:2048'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('original_published_at')
                ->label(__('admin.fields.original_published_at'))
                ->example('30/06/2026 09:00')
                ->castStateUsing(fn (mixed $state): mixed => static::castImportedDateTime($state))
                ->rules(['nullable'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('status')
                ->label(__('admin.fields.status'))
                ->example(PublicationStatus::Draft->value)
                ->rules(['nullable', Rule::in(array_column(PublicationStatus::cases(), 'value'))])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('published_at')
                ->label(__('admin.fields.published_at'))
                ->example('30/06/2026 10:00')
                ->castStateUsing(fn (mixed $state): mixed => static::castImportedDateTime($state))
                ->rules(['nullable'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('is_pinned')
                ->label(__('admin.fields.is_pinned'))
                ->boolean()
                ->example('false')
                ->rules(['nullable', 'boolean'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('pinned_at')
                ->label(__('admin.fields.pinned_at'))
                ->example('30/06/2026 10:00')
                ->castStateUsing(fn (mixed $state): mixed => static::castImportedDateTime($state))
                ->rules(['nullable'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('pinned_until')
                ->label(__('admin.fields.pinned_until'))
                ->example('30/07/2026 10:00')
                ->castStateUsing(fn (mixed $state): mixed => static::castImportedDateTime($state))
                ->rules(['nullable'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('pin_order')
                ->label(__('admin.fields.pin_order'))
                ->integer()
                ->example('5')
                ->rules(['nullable', 'integer'])
                ->ignoreBlankState(fn (?ContentItem $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('category_paths')
                ->label(__('admin.import.columns.category_paths'))
                ->multiple('|')
                ->example('torah|torah/interviews')
                ->nestedRecursiveRules(['string', 'max:255'])
                ->rules([
                    function (string $attribute, mixed $state, \Closure $fail): void {
                        if (blank($state)) {
                            return;
                        }

                        $categories = static::resolveCategoryPaths($state);

                        if ($categories->count() !== count($state)) {
                            $missing = collect($state)
                                ->reject(fn (string $path): bool => $categories->contains(fn (Category $category): bool => static::categoryPath($category) === $path))
                                ->implode('|');

                            $fail(__('admin.import.failures.unresolved_categories', [
                                'paths' => $missing,
                            ]));
                        }
                    },
                ])
                ->fillRecordUsing(fn (): null => null)
                ->saveRelationshipsUsing(function (ContentItem $record, array $state): void {
                    $categories = static::resolveCategoryPaths($state);

                    if ($categories->count() !== count($state)) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_categories', [
                            'paths' => collect($state)->implode('|'),
                        ]));
                    }

                    $record->categories()->sync($categories->pluck('id')->all());
                }),
            ImportColumn::make('content_tag_slugs')
                ->label(__('admin.import.columns.content_tag_slugs'))
                ->multiple('|')
                ->example('torah|interview')
                ->nestedRecursiveRules(['string', 'max:255'])
                ->rules([
                    function (string $attribute, mixed $state, \Closure $fail): void {
                        if (blank($state)) {
                            return;
                        }

                        $tags = static::resolveEnabledContentTags($state);

                        if ($tags->count() !== count($state)) {
                            $fail(__('admin.import.failures.unresolved_content_tags', [
                                'slugs' => collect($state)->implode('|'),
                            ]));
                        }
                    },
                ])
                ->fillRecordUsing(fn (): null => null)
                ->saveRelationshipsUsing(function (ContentItem $record, array $state): void {
                    $tags = static::resolveEnabledContentTags($state);

                    if ($tags->count() !== count($state)) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_content_tags', [
                            'slugs' => collect($state)->implode('|'),
                        ]));
                    }

                    $record->tags()->sync($tags->pluck('id')->all());
                }),
            ImportColumn::make('featured_transcription_reference_key')
                ->label(__('admin.import.columns.featured_transcription_reference_key'))
                ->example('01JTRANSCRIPTION00000000001')
                ->rules(['nullable', 'ulid', 'max:26'])
                ->ignoreBlankState()
                ->fillRecordUsing(fn (): null => null)
                ->saveRelationshipsUsing(function (ContentItem $record, ?string $state): void {
                    if (blank($state)) {
                        return;
                    }

                    $transcription = Transcription::query()
                        ->where('reference_key', $state)
                        ->where('content_item_id', $record->getKey())
                        ->first();

                    if (! $transcription) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_featured_transcription', [
                            'reference_key' => $state,
                        ]));
                    }

                    $record->forceFill([
                        'featured_transcription_id' => $transcription->getKey(),
                    ])->save();
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
}
