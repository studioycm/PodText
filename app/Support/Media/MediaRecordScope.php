<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MediaRecordScope
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
    ) {}

    /**
     * @return Builder<Media>
     */
    public function inventoryQuery(): Builder
    {
        /** @var class-string<Media> $model */
        $model = config('curator.model', Media::class);

        return $model::query();
    }

    /**
     * The managed-file scope remains intentionally narrower than inventory.
     * It protects existing rename, swap, delete and transition operations.
     *
     * @return Builder<Media>
     */
    public function query(?ImageUploadPurpose $purpose = null): Builder
    {
        /** @var class-string<Media> $model */
        $model = config('curator.model', Media::class);
        $table = (new $model)->getTable();
        $purposes = $purpose instanceof ImageUploadPurpose ? [$purpose] : ImageUploadPurpose::cases();

        return $this->applyPathSyntax(
            $model::query()
                ->where("{$table}.disk", 'public')
                ->where("{$table}.visibility", 'public')
                ->whereNotNull("{$table}.reference_key")
                ->whereBetween("{$table}.size", [1, CuratorImageUploadPolicy::MAX_KILOBYTES * 1024])
                ->where(function (Builder $query) use ($table): void {
                    $query
                        ->where("{$table}.type", 'image/svg+xml')
                        ->orWhere(function (Builder $query) use ($table): void {
                            $query
                                ->whereBetween("{$table}.width", [1, CuratorImageUploadPolicy::MAX_DIMENSION_PIXELS])
                                ->whereBetween("{$table}.height", [1, CuratorImageUploadPolicy::MAX_DIMENSION_PIXELS]);
                        });
                })
                ->where(function (Builder $query) use ($purposes, $table): void {
                    foreach ($purposes as $purpose) {
                        $query->orWhere(function (Builder $query) use ($purpose, $table): void {
                            $root = $purpose->root();

                            $query
                                ->where("{$table}.directory", $root)
                                ->where("{$table}.path", 'like', "{$root}/%")
                                ->where("{$table}.path", 'not like', "{$root}/%/%")
                                ->where(function (Builder $query) use ($purpose, $table): void {
                                    foreach ($this->policy->mimeTypesFor($purpose) as $mimeType) {
                                        $query->orWhere(function (Builder $query) use ($mimeType, $table): void {
                                            $query
                                                ->where("{$table}.type", $mimeType)
                                                ->where("{$table}.ext", $this->policy->canonicalExtension($mimeType));
                                        });
                                    }
                                });
                        });
                    }
                }),
        );
    }

    public function findInventory(int|string $id): ?Media
    {
        if (! is_int($id) && (! is_string($id) || ! ctype_digit($id))) {
            return null;
        }

        $media = $this->inventoryQuery()->whereKey((int) $id)->first();

        return $media instanceof Media ? $media : null;
    }

    public function findInventoryOrFail(int|string $id): Media
    {
        return $this->findInventory($id) ?? throw (new ModelNotFoundException)->setModel(
            config('curator.model', Media::class),
            [$id],
        );
    }

    public function find(int|string $id, ?ImageUploadPurpose $purpose = null): ?Media
    {
        if (! is_int($id) && (! is_string($id) || ! ctype_digit($id))) {
            return null;
        }

        $media = $this->query($purpose)->whereKey((int) $id)->first();

        return $media instanceof Media && $this->allows($media, $purpose) ? $media : null;
    }

    public function findOrFail(int|string $id, ?ImageUploadPurpose $purpose = null): Media
    {
        return $this->find($id, $purpose) ?? throw (new ModelNotFoundException)->setModel(
            config('curator.model', Media::class),
            [$id],
        );
    }

    public function findByReferenceKey(string $referenceKey, ?ImageUploadPurpose $purpose = null): ?Media
    {
        $referenceKey = trim($referenceKey);

        if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', mb_strtoupper($referenceKey))) {
            return null;
        }

        $media = $this->inventoryQuery()
            ->whereRaw('LOWER(reference_key) = ?', [mb_strtolower($referenceKey)])
            ->first();

        return $media instanceof Media ? $media : null;
    }

    public function findByPath(string $path, ?ImageUploadPurpose $purpose = null): ?Media
    {
        try {
            $path = $this->policy->normalizePath($path);
        } catch (InvalidArgumentException) {
            return null;
        }

        $media = $this->query($purpose)
            ->where('path', $path)
            ->first();

        return $media instanceof Media && $this->allows($media, $purpose) ? $media : null;
    }

    /**
     * @param  iterable<int, int|string>  $ids
     * @return Collection<int, Media>
     */
    public function lockForUpdateByIds(iterable $ids, ?ImageUploadPurpose $purpose = null): Collection
    {
        return $this->lockByIds($ids, $this->query($purpose));
    }

    /**
     * @param  iterable<int, int|string>  $ids
     * @return Collection<int, Media>
     */
    public function lockInventoryForUpdateByIds(iterable $ids): Collection
    {
        return $this->lockByIds($ids, $this->inventoryQuery());
    }

    public function hasPortableReferenceKey(Media $media): bool
    {
        return is_string($media->reference_key)
            && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', mb_strtoupper($media->reference_key)) === 1;
    }

    public function hasUniqueStorageIdentity(Media $media): bool
    {
        $selectedCount = $media->getAttribute('storage_identity_count');

        if (is_numeric($selectedCount)) {
            return (int) $selectedCount === 1;
        }

        return ! $this->inventoryQuery()
            ->where('disk', $media->disk)
            ->where('path', $media->path)
            ->whereKeyNot($media->getKey())
            ->exists();
    }

    /**
     * @param  iterable<int, int|string>  $ids
     * @param  Builder<Media>  $query
     * @return Collection<int, Media>
     */
    private function lockByIds(iterable $ids, Builder $query): Collection
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Trusted media row locks require an active database transaction.');
        }

        $ids = collect($ids)
            ->map(fn (int|string $id): int => is_int($id) || ctype_digit((string) $id) ? (int) $id : 0)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        $records = $query
            ->whereKey($ids->all())
            ->orderBy((new Media)->getQualifiedKeyName())
            ->lockForUpdate()
            ->get();

        if ($records->count() !== $ids->count()) {
            throw (new ModelNotFoundException)->setModel(
                config('curator.model', Media::class),
                $ids->all(),
            );
        }

        return $records;
    }

    public function allows(Media $media, ?ImageUploadPurpose $purpose = null): bool
    {
        return $this->allowsRecord($media, $purpose, requireReferenceKey: true);
    }

    public function allowsForBackfill(Media $media, ?ImageUploadPurpose $purpose = null): bool
    {
        return $this->allowsRecord($media, $purpose, requireReferenceKey: false);
    }

    private function allowsRecord(
        Media $media,
        ?ImageUploadPurpose $purpose,
        bool $requireReferenceKey,
    ): bool {
        if ($media->disk !== 'public' || $media->visibility !== 'public') {
            return false;
        }

        if (
            ($requireReferenceKey || filled($media->reference_key))
            && ! $this->hasPortableReferenceKey($media)
        ) {
            return false;
        }

        try {
            $recordPurpose = $this->policy->purposeForPath((string) $media->path);
            $path = $this->policy->normalizePath((string) $media->path);
        } catch (InvalidArgumentException) {
            return false;
        }

        if ($purpose instanceof ImageUploadPurpose && $recordPurpose !== $purpose) {
            return false;
        }

        if ($media->directory !== $recordPurpose->root()) {
            return false;
        }

        if (! $this->policy->allowsMime($recordPurpose, (string) $media->type)) {
            return false;
        }

        $canonicalExtension = $this->policy->canonicalExtension((string) $media->type);

        if (
            $media->ext !== $canonicalExtension
            || mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== $canonicalExtension
            || (string) $media->name !== pathinfo($path, PATHINFO_FILENAME)
            || (int) $media->size < 1
            || (int) $media->size > CuratorImageUploadPolicy::MAX_KILOBYTES * 1024
        ) {
            return false;
        }

        if ($media->type === 'image/svg+xml') {
            return true;
        }

        return (int) $media->width >= 1
            && (int) $media->width <= CuratorImageUploadPolicy::MAX_DIMENSION_PIXELS
            && (int) $media->height >= 1
            && (int) $media->height <= CuratorImageUploadPolicy::MAX_DIMENSION_PIXELS;
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    private function applyPathSyntax(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $query
            ->where("{$table}.path", 'not like', '%..%')
            ->where("{$table}.path", 'not like', '%//%');

        return match (DB::connection()->getDriverName()) {
            'sqlite' => $query
                ->whereRaw("{$table}.path NOT GLOB '*[^A-Za-z0-9._/-]*'")
                ->whereRaw("{$table}.path = {$table}.directory || '/' || {$table}.name || '.' || {$table}.ext"),
            'mysql', 'mariadb' => $query
                ->whereRaw("{$table}.path REGEXP '^[A-Za-z0-9][A-Za-z0-9._/-]*$'")
                ->whereRaw("{$table}.path = CONCAT({$table}.directory, '/', {$table}.name, '.', {$table}.ext)"),
            default => $query,
        };
    }
}
