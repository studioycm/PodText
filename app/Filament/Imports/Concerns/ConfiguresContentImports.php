<?php

namespace App\Filament\Imports\Concerns;

use App\Enums\RelationImportMode;
use App\Models\Category;
use App\Models\ContentTag;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;
use JsonException;
use Throwable;

trait ConfiguresContentImports
{
    private const SkippedDisabledContentTagsCachePrefix = 'podtext.imports.skipped_disabled_content_tags.';

    /**
     * @var array<int, string>
     */
    protected array $seenReferenceKeys = [];

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('mode')
                ->label(__('admin.import.options.mode'))
                ->options([
                    'upsert' => __('admin.import.options.modes.upsert'),
                    'create' => __('admin.import.options.modes.create'),
                    'update' => __('admin.import.options.modes.update'),
                ])
                ->default('upsert')
                ->required(),
            Select::make('relation_mode')
                ->label(__('admin.import.options.relation_mode'))
                ->helperText(__('admin.import.options.relation_mode_helper'))
                ->options([
                    RelationImportMode::Replace->value => __('admin.import.options.relation_modes.replace'),
                    RelationImportMode::AddOnly->value => __('admin.import.options.relation_modes.add_only'),
                ])
                ->default(RelationImportMode::Replace->value)
                ->required(),
            Select::make('blank_update_behavior')
                ->label(__('admin.import.options.blank_update_behavior'))
                ->options([
                    'preserve' => __('admin.import.options.blank_update_behaviors.preserve'),
                    'overwrite' => __('admin.import.options.blank_update_behaviors.overwrite'),
                ])
                ->default('preserve')
                ->required(),
        ];
    }

    public function getJobQueue(): ?string
    {
        return 'imports-exports';
    }

    public function getJobRetryUntil(): ?CarbonInterface
    {
        return now()->addHour();
    }

    /**
     * @return array<int, int>
     */
    public function getJobBackoff(): array
    {
        return [30, 120, 300];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = trans_choice('admin.import_export.notifications.import.completed_body', $import->successful_rows, [
            'count' => Number::format($import->successful_rows),
            'label' => self::importNotificationResourceLabel(),
        ]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.trans_choice('admin.import_export.notifications.import.failed_body', $failedRowsCount, [
                'count' => Number::format($failedRowsCount),
            ]);
        }

        if ($skippedDisabledTags = self::skippedDisabledContentTagNames($import)) {
            $body .= ' '.__('admin.import_export.notifications.import.skipped_disabled_content_tags', [
                'tags' => collect($skippedDisabledTags)->sort()->implode(', '),
            ]);
        }

        return $body;
    }

    public function getJobBatchName(): ?string
    {
        return (string) str(class_basename(static::class))
            ->beforeLast('Importer')
            ->kebab()
            ->append('-import');
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function resolveRecordByReferenceKey(string $modelClass): Model
    {
        $referenceKey = filled($this->data['reference_key'] ?? null)
            ? (string) $this->data['reference_key']
            : null;

        if ($referenceKey !== null) {
            if (in_array($referenceKey, $this->seenReferenceKeys, true)) {
                throw new RowImportFailedException(__('admin.import.failures.duplicate_reference_key', [
                    'reference_key' => $referenceKey,
                ]));
            }

            $this->seenReferenceKeys[] = $referenceKey;
        }

        if ($referenceKey === null && $this->importMode() === 'update') {
            throw new RowImportFailedException(__('admin.import.failures.update_requires_reference_key'));
        }

        $record = $referenceKey
            ? $modelClass::query()->where('reference_key', $referenceKey)->first()
            : null;

        if ($record && $this->importMode() === 'create') {
            throw new RowImportFailedException(__('admin.import.failures.create_found_existing_reference_key', [
                'reference_key' => $referenceKey,
            ]));
        }

        if (! $record && $this->importMode() === 'update') {
            throw new RowImportFailedException(__('admin.import.failures.update_missing_reference_key', [
                'reference_key' => $referenceKey,
            ]));
        }

        return $record ?? new $modelClass(array_filter([
            'reference_key' => $referenceKey,
        ]));
    }

    protected static function shouldIgnoreBlankForUpdate(?Model $record, array $options): bool
    {
        return ($record?->exists ?? false)
            && (($options['blank_update_behavior'] ?? 'preserve') === 'preserve');
    }

    protected static function shouldRequireValue(?Model $record, array $options): bool
    {
        return ! ($record?->exists ?? false)
            || (($options['blank_update_behavior'] ?? 'preserve') === 'overwrite');
    }

    protected function importMode(): string
    {
        return $this->options['mode'] ?? 'upsert';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected static function relationMode(array $options): RelationImportMode
    {
        return RelationImportMode::fromOptions($options);
    }

    protected static function isBlankRelationState(mixed $state): bool
    {
        if (is_array($state)) {
            return collect($state)
                ->filter(fn (mixed $value): bool => filled($value))
                ->isEmpty();
        }

        return blank($state);
    }

    /**
     * @param  array<int, int>  $ids
     * @param  array<string, mixed>  $options
     */
    protected static function syncImportRelation(BelongsToMany $relationship, array $ids, array $options): void
    {
        if (static::relationMode($options) === RelationImportMode::AddOnly) {
            $relationship->syncWithoutDetaching($ids);

            return;
        }

        $relationship->sync($ids);
    }

    protected static function castImportedDateTime(mixed $state): ?CarbonInterface
    {
        if ($state instanceof CarbonInterface) {
            return $state->copy()->setTimezone(config('app.timezone', 'UTC'));
        }

        if (blank($state)) {
            return null;
        }

        $state = trim((string) $state);
        $timezone = 'Asia/Jerusalem';

        foreach (['d/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $state, $timezone);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($date instanceof CarbonImmutable && $date->format($format) === $state) {
                return $date->setTimezone(config('app.timezone', 'UTC'));
            }
        }

        throw new RowImportFailedException(__('admin.import.failures.invalid_day_first_date', [
            'value' => $state,
        ]));
    }

    protected static function castImportedJson(mixed $state): ?array
    {
        if (blank($state)) {
            return null;
        }

        if (is_array($state)) {
            return $state;
        }

        try {
            $decoded = json_decode((string) $state, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RowImportFailedException(__('admin.import.failures.invalid_json'));
        }

        if (! is_array($decoded)) {
            throw new RowImportFailedException(__('admin.import.failures.invalid_json'));
        }

        return $decoded;
    }

    protected static function resolveCategoryPath(string $path): ?Category
    {
        $segments = collect(explode('/', $path))
            ->map(fn (string $segment): string => trim($segment))
            ->filter()
            ->values();

        if ($segments->isEmpty()) {
            return null;
        }

        $parentId = null;
        $category = null;

        foreach ($segments as $segment) {
            $category = Category::query()
                ->where('slug', $segment)
                ->where('parent_id', $parentId)
                ->first();

            if (! $category) {
                return null;
            }

            $parentId = $category->getKey();
        }

        return $category;
    }

    /**
     * @param  array<int, string>  $paths
     * @return Collection<int, Category>
     */
    protected static function resolveCategoryPaths(array $paths): Collection
    {
        return collect($paths)
            ->map(fn (string $path): ?Category => static::resolveCategoryPath($path))
            ->filter()
            ->values();
    }

    protected static function categoryPath(Category $category): string
    {
        $segments = collect([$category->slug]);
        $category->loadMissing('parent');
        $parent = $category->parent;

        while ($parent) {
            $segments->prepend($parent->slug);
            $parent->loadMissing('parent');
            $parent = $parent->parent;
        }

        return $segments->implode('/');
    }

    protected static function resolveEnabledContentTag(string $slugOrName): ?ContentTag
    {
        $tag = ContentTag::findFromString($slugOrName, 'content');

        if (! $tag instanceof ContentTag || ! $tag->is_enabled) {
            return null;
        }

        return $tag;
    }

    /**
     * @param  array<int, string>  $slugsOrNames
     * @return Collection<int, ContentTag>
     */
    protected static function resolveEnabledContentTags(array $slugsOrNames): Collection
    {
        return collect($slugsOrNames)
            ->map(fn (string $slugOrName): ?ContentTag => static::resolveEnabledContentTag($slugOrName))
            ->filter()
            ->values();
    }

    protected static function resolveContentTag(string $slugOrName): ?ContentTag
    {
        $tag = ContentTag::findFromString($slugOrName, 'content');

        return $tag instanceof ContentTag ? $tag : null;
    }

    /**
     * @param  array<int, string>  $slugsOrNames
     * @return Collection<int, ContentTag>
     */
    protected static function resolveContentTags(array $slugsOrNames): Collection
    {
        return collect($slugsOrNames)
            ->map(fn (string $slugOrName): ?ContentTag => static::resolveContentTag($slugOrName))
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, ContentTag>  $tags
     * @return Collection<int, ContentTag>
     */
    protected static function enabledImportableContentTags(Collection $tags): Collection
    {
        return $tags
            ->filter(fn (ContentTag $tag): bool => $tag->is_enabled)
            ->values();
    }

    /**
     * @param  Collection<int, ContentTag>  $tags
     * @return Collection<int, ContentTag>
     */
    protected static function disabledImportableContentTags(Collection $tags): Collection
    {
        return $tags
            ->reject(fn (ContentTag $tag): bool => $tag->is_enabled)
            ->values();
    }

    /**
     * @param  Collection<int, ContentTag>  $tags
     */
    protected static function recordSkippedDisabledContentTags(Importer $importer, Collection $tags): void
    {
        $names = $tags
            ->map(fn (ContentTag $tag): string => static::contentTagDisplayName($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($names === []) {
            return;
        }

        $key = static::skippedDisabledContentTagsCacheKey($importer->getImport());
        $lock = Cache::lock("{$key}.lock", 10);

        $write = function () use ($key, $names): void {
            $existing = Cache::get($key, []);
            $merged = collect(is_array($existing) ? $existing : [])
                ->merge($names)
                ->filter()
                ->unique()
                ->values()
                ->all();

            Cache::put($key, $merged, now()->addDay());
        };

        try {
            $lock->block(5, $write);
        } catch (Throwable) {
            $write();
        }
    }

    /**
     * @return array<int, string>
     */
    private static function skippedDisabledContentTagNames(Import $import): array
    {
        $names = Cache::get(static::skippedDisabledContentTagsCacheKey($import), []);

        if (! is_array($names)) {
            return [];
        }

        return collect($names)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function skippedDisabledContentTagsCacheKey(Import $import): string
    {
        return self::SkippedDisabledContentTagsCachePrefix.$import->getKey();
    }

    private static function contentTagDisplayName(ContentTag $tag): string
    {
        $name = $tag->getTranslation('name', app()->getLocale(), false);

        if (filled($name)) {
            return $name;
        }

        return (string) $tag->getTranslation('slug', app()->getLocale(), false);
    }

    private static function importNotificationResourceLabel(): string
    {
        $resourceKey = (string) str(class_basename(static::class))
            ->beforeLast('Importer')
            ->snake();

        return (string) str(__("admin.resources.{$resourceKey}.singular"))->lower();
    }
}
