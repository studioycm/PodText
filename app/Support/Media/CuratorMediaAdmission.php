<?php

namespace App\Support\Media;

use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaProviderBinding;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CuratorMediaAdmission
{
    public function __construct(
        private readonly MediaMutationLease $lease,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function create(
        ValidatedImage $image,
        string $disk,
        string $path,
        User $actor,
        array $metadata = [],
    ): Media {
        Gate::forUser($actor)->authorize('create', config('curator.model', Media::class));

        return DB::transaction(function () use ($image, $disk, $path, $metadata): Media {
            $referenceKey = (string) Str::ulid();
            $mediaClass = config('curator.model', Media::class);
            /** @var Media $media */
            $media = new $mediaClass;
            $media->forceFill([
                'reference_key' => $referenceKey,
                'disk' => $disk,
                'directory' => dirname($path),
                'visibility' => $disk === 'public' ? 'public' : 'private',
                'name' => pathinfo($path, PATHINFO_FILENAME),
                'path' => $path,
                'width' => $image->width,
                'height' => $image->height,
                'size' => $image->size,
                'type' => $image->mimeType,
                'ext' => $image->extension,
                'alt' => $this->metadata($metadata, 'alt', 255),
                'title' => filled(Arr::get($metadata, 'title'))
                    ? $this->metadata($metadata, 'title', 255)
                    : Str::limit((string) $image->originalFilename, 255, ''),
                'description' => $this->metadata($metadata, 'description', 65000),
                'caption' => $this->metadata($metadata, 'caption', 65000),
                'exif' => json_encode([
                    'original_filename' => $image->originalFilename,
                ], JSON_THROW_ON_ERROR),
            ]);
            $this->lease->runCreation(fn (): bool => $media->save());

            $asset = MediaAsset::query()->create(['reference_key' => $referenceKey]);
            MediaProviderBinding::query()->create([
                'media_asset_id' => $asset->getKey(),
                'provider' => 'curator',
                'provider_record_key' => (string) $media->getKey(),
            ]);

            return $media->load(['mediaAsset', 'providerBinding']);
        });
    }

    /** @param array<string, mixed> $metadata */
    private function metadata(array $metadata, string $key, int $limit): ?string
    {
        $value = Arr::get($metadata, $key);

        if (! is_scalar($value) || blank((string) $value)) {
            return null;
        }

        return Str::limit(trim((string) $value), $limit, '');
    }
}
