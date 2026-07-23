<?php

namespace App\Filament\Imports;

use App\Enums\MediaAttachmentRole;
use App\Enums\PublicationStatus;
use App\Filament\Imports\Concerns\ConfiguresContentImports;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\MediaAttachmentManager;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordScope;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContentGroupImporter extends Importer
{
    use ConfiguresContentImports;

    public function __invoke(array $data): void
    {
        DB::transaction(function () use ($data): void {
            parent::__invoke($data);
        });
    }

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
                    'max:26',
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
            ImportColumn::make('cover_media_reference_key')
                ->label(__('admin.import.columns.cover_media_reference_key'))
                ->example('01JMEDIA000000000000000001')
                ->rules([
                    'nullable',
                    'ulid',
                    'max:26',
                    function (string $attribute, mixed $state, \Closure $fail): void {
                        if (blank($state)) {
                            return;
                        }

                        $media = app(MediaRecordScope::class)->findByReferenceKey(
                            (string) $state,
                            MediaAttachmentRole::Cover->purpose(),
                        );

                        $blockedReason = $media instanceof Media
                            ? app(MediaInventoryDiagnostics::class)->selectionBlockedReason($media)
                            : null;

                        if (! $media instanceof Media || $blockedReason !== null) {
                            $fail(__('admin.import.failures.unresolved_media_reference_key', [
                                'reference_key' => $state,
                            ]));
                        }
                    },
                ])
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options))
                ->fillRecordUsing(fn (): null => null)
                ->saveRelationshipsUsing(function (ContentGroup $record, ?string $state, Importer $importer): void {
                    $actor = $importer->getImport()->user;

                    if (! $actor instanceof User) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_media_actor'));
                    }

                    if (blank($state)) {
                        app(MediaAttachmentManager::class)->detach($record, MediaAttachmentRole::Cover, $actor);

                        return;
                    }

                    $media = app(MediaRecordScope::class)->findByReferenceKey(
                        (string) $state,
                        MediaAttachmentRole::Cover->purpose(),
                    );

                    if (
                        ! $media instanceof Media
                        || app(MediaInventoryDiagnostics::class)->selectionBlockedReason($media) !== null
                    ) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_media_reference_key', [
                            'reference_key' => $state,
                        ]));
                    }

                    app(MediaAttachmentManager::class)->attachByReferenceKey(
                        $record,
                        (string) $state,
                        MediaAttachmentRole::Cover,
                        $actor,
                    );
                }),
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
                ->example('30/06/2026 13:45')
                ->castStateUsing(fn (mixed $state): mixed => static::castImportedDateTime($state))
                ->rules(['nullable'])
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
            ImportColumn::make('homepage_order')
                ->label(__('admin.fields.homepage_order'))
                ->integer()
                ->example('10')
                ->rules(['nullable', 'integer'])
                ->ignoreBlankState(fn (?ContentGroup $record, array $options): bool => static::shouldIgnoreBlankForUpdate($record, $options)),
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
                ->saveRelationshipsUsing(function (ContentGroup $record, array $state, array $options): void {
                    if (static::isBlankRelationState($state)) {
                        return;
                    }

                    $categories = static::resolveCategoryPaths($state);

                    if ($categories->count() !== count($state)) {
                        throw new RowImportFailedException(__('admin.import.failures.unresolved_categories', [
                            'paths' => collect($state)->implode('|'),
                        ]));
                    }

                    static::syncImportRelation($record->categories(), $categories->pluck('id')->all(), $options);
                }),
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
}
