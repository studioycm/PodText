<?php

namespace App\Filament\Imports\Concerns;

use App\Models\Category;
use App\Models\ContentTag;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JsonException;

trait ConfiguresContentImports
{
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
        $parent = $category->parent;

        while ($parent) {
            $segments->prepend($parent->slug);
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
}
