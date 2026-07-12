<?php

namespace App\Filament\Forms\Components;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PathCuratorPicker extends CuratorPicker
{
    private ?string $preservedPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearAfterStateUpdatedHooks();

        $this->afterStateHydrated(function (PathCuratorPicker $component, array|int|string|null $state): void {
            $component->state($component->itemsForState($state));
        });

        $this->afterStateUpdated(function (PathCuratorPicker $component, mixed $state): void {
            if (blank($state)) {
                $component->preservedPath = null;
                $component->state([]);

                return;
            }

            if (is_string($state) || is_int($state)) {
                $component->state($component->itemsForState($state));

                return;
            }

            $component->preservedPath = null;
        });

        $this->dehydrateStateUsing(function (PathCuratorPicker $component, mixed $state): ?string {
            if (blank($state)) {
                return $component->preservedPath;
            }

            $scalarPath = $component->pathFromScalarState($state);

            if ($scalarPath !== null) {
                return $scalarPath;
            }

            $item = collect($state)->first();
            $path = is_array($item) ? ($item['path'] ?? null) : null;

            return is_string($path) && filled($path) ? $path : $component->preservedPath;
        });
    }

    public function getState(): mixed
    {
        $state = parent::getState();

        if (is_string($state) || is_int($state) || $this->isScalarStateArray($state)) {
            $items = $this->itemsForState($state);
            $this->state($items);

            return $items;
        }

        return is_array($state) ? $state : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function itemsForState(array|int|string|null $state): array
    {
        if (blank($state)) {
            $this->preservedPath = null;

            return [];
        }

        $media = $this->mediaForState($state);

        if ($media === []) {
            $this->preservedPath = $this->pathFromScalarState($state);

            return [];
        }

        $this->preservedPath = null;

        return collect($media)
            ->mapWithKeys(fn (array $item): array => [(string) Str::uuid() => $item])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mediaForState(array|int|string $state): array
    {
        if (is_string($state) && ! is_numeric($state)) {
            return $this->mediaByPath($state);
        }

        if (is_array($state) && count($state) === 1) {
            $first = Arr::first($state);

            if (is_string($first) && ! is_numeric($first)) {
                return $this->mediaByPath($first);
            }
        }

        if (is_array($state) && isset($state['id'])) {
            return [$state];
        }

        if (is_array($state) && isset($state[0]) && is_array($state[0]) && isset($state[0]['id'])) {
            return array_values($state);
        }

        $ids = collect(Arr::wrap($state))
            ->filter(fn (mixed $value): bool => is_int($value) || (is_string($value) && ctype_digit($value)))
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return $this->mediaModel()::query()
            ->whereKey($ids)
            ->get()
            ->map(fn (Media $media): array => $media->toArray())
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mediaByPath(string $path): array
    {
        $media = $this->mediaModel()::query()
            ->where('disk', 'public')
            ->where('path', $path)
            ->first();

        if ($media instanceof Media) {
            return [$media->toArray()];
        }

        return Storage::disk('public')->exists($path) ? [] : [];
    }

    /**
     * @return class-string<Media>
     */
    private function mediaModel(): string
    {
        $model = config('curator.model', Media::class);

        return is_string($model) && is_a($model, Media::class, true) ? $model : Media::class;
    }

    private function isScalarStateArray(mixed $state): bool
    {
        if (! is_array($state) || $state === []) {
            return false;
        }

        $first = Arr::first($state);

        return is_string($first) || is_int($first);
    }

    private function pathFromScalarState(mixed $state): ?string
    {
        if (is_string($state) && ! is_numeric($state)) {
            return $state;
        }

        if (is_array($state) && count($state) === 1) {
            $first = Arr::first($state);

            if (is_string($first) && ! is_numeric($first)) {
                return $first;
            }
        }

        return null;
    }
}
