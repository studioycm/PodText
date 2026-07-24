<?php

namespace App\Support\Media;

use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;

class MediaInventoryDiagnostics
{
    /** @var array<string, array<int, string>> */
    private array $reasons = [];

    public function __construct(
        private readonly MediaRecordScope $scope,
        private readonly PublicMediaDelivery $publicDelivery,
    ) {}

    /** @return array<int, string> */
    public function reasons(Media $media): array
    {
        $cacheKey = $this->diagnosticCacheKey($media);

        if (array_key_exists($cacheKey, $this->reasons)) {
            return $this->reasons[$cacheKey];
        }

        $reasons = [];

        if (! $this->scope->hasPortableReferenceKey($media)) {
            $reasons[] = 'portable_identity';
        }

        if (! array_key_exists((string) $media->disk, config('filesystems.disks', []))) {
            $reasons[] = 'storage_disk';
        } elseif (! $this->fileExists($media)) {
            $reasons[] = 'missing_file';
        }

        if ($media->disk !== 'public' || $media->visibility !== 'public') {
            $reasons[] = 'audience_denied';
        }

        if ($this->fileExists($media) && ! $this->publicDelivery->canRenderInline($media)) {
            $reasons[] = 'unsanitized_svg';
        }

        if ($this->hasMetadataIssue($media)) {
            $reasons[] = 'metadata';
        }

        return $this->reasons[$cacheKey] = array_values(array_unique($reasons));
    }

    public function needsRepair(Media $media): bool
    {
        return $this->reasons($media) !== [];
    }

    public function forget(Media $media): void
    {
        unset($this->reasons[$this->diagnosticCacheKey($media)]);
        $this->publicDelivery->forget($media);
    }

    /** @param Builder<Media> $query */
    public function applyNeedsRepairFilter(Builder $query): Builder
    {
        $qualifiedKey = (new Media)->getQualifiedKeyName();
        $managedIds = $this->scope->query()->select($qualifiedKey);
        $missingIds = $this->missingFileIds();

        return $query->where(function (Builder $query) use ($qualifiedKey, $managedIds, $missingIds): void {
            $query->whereNotIn($qualifiedKey, $managedIds);

            if ($missingIds !== []) {
                $query->orWhereIntegerInRaw($qualifiedKey, $missingIds);
            }
        });
    }

    public function selectionBlockedReason(Media $media): ?string
    {
        if (! $this->scope->hasPortableReferenceKey($media)) {
            return __('admin.media_library.selection_portable_identity');
        }

        return match ($this->publicDelivery->fallbackReason($media)) {
            PublicMediaDelivery::AudienceDenied => __('admin.media_library.selection_audience_denied'),
            PublicMediaDelivery::MissingFile => __('admin.media_library.selection_missing_file'),
            PublicMediaDelivery::UnsanitizedInlineSvg => __('admin.media_library.selection_unsanitized_svg'),
            default => null,
        };
    }

    public function freshSelectionBlockedReason(Media $media): ?string
    {
        $this->publicDelivery->forget($media);

        return $this->selectionBlockedReason($media);
    }

    public function previewUrl(Media $media): ?string
    {
        if (
            ! array_key_exists((string) $media->disk, config('filesystems.disks', []))
            || ! $this->fileExists($media)
            || ! $this->publicDelivery->canRenderInline($media)
        ) {
            return null;
        }

        return route('admin.media-files.view', ['media' => $media->getKey()]);
    }

    private function fileExists(Media $media): bool
    {
        return $this->publicDelivery->fileExists($media);
    }

    /** @return array<int, int> */
    private function missingFileIds(): array
    {
        $configuredDisks = array_keys(config('filesystems.disks', []));

        return $this->scope->inventoryQuery()
            ->select(['id', 'disk', 'path'])
            ->whereIn('disk', $configuredDisks)
            ->orderBy('id')
            ->lazyById(250)
            ->reject(fn (Media $media): bool => $this->fileExists($media))
            ->map(fn (Media $media): int => (int) $media->getKey())
            ->values()
            ->all();
    }

    private function diagnosticCacheKey(Media $media): string
    {
        return implode(':', [
            $media->getKey(),
            $media->disk,
            $media->path,
            $media->directory,
            $media->visibility,
            $media->reference_key,
            $media->type,
            $media->ext,
            $media->name,
            $media->size,
            $media->width,
            $media->height,
            $media->getRawOriginal('updated_at'),
        ]);
    }

    private function hasMetadataIssue(Media $media): bool
    {
        $metadataCandidate = $media->replicate();
        $metadataCandidate->forceFill([
            'disk' => 'public',
            'visibility' => 'public',
            'reference_key' => null,
        ]);

        return ! $this->scope->allowsForBackfill($metadataCandidate);
    }
}
