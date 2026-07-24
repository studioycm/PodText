<?php

namespace App\Support\Media;

use App\Enums\MediaDiagnosticReason;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;

class MediaInventoryDiagnostics
{
    /** @var array<string, array<int, string>> */
    private array $reasons = [];

    /** @var array<string, array<int, int>>|null */
    private ?array $diagnosticIds = null;

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
            $reasons[] = MediaDiagnosticReason::PortableIdentity->value;
        }

        if (! array_key_exists((string) $media->disk, config('filesystems.disks', []))) {
            $reasons[] = MediaDiagnosticReason::StorageDisk->value;
        } elseif (! $this->fileExists($media)) {
            $reasons[] = MediaDiagnosticReason::MissingFile->value;
        }

        if ($media->disk !== 'public' || $media->visibility !== 'public') {
            $reasons[] = MediaDiagnosticReason::AudienceDenied->value;
        }

        if ($this->fileExists($media) && ! $this->publicDelivery->canRenderInline($media)) {
            $reasons[] = MediaDiagnosticReason::UnsanitizedSvg->value;
        }

        if ($this->hasMetadataIssue($media)) {
            $reasons[] = MediaDiagnosticReason::Metadata->value;
        }

        return $this->reasons[$cacheKey] = array_values(array_unique($reasons));
    }

    public function needsRepair(Media $media): bool
    {
        return $this->reasons($media) !== [];
    }

    public function forget(Media $media): void
    {
        $this->diagnosticIds = null;
        unset($this->reasons[$this->diagnosticCacheKey($media)]);
        $this->publicDelivery->forget($media);
    }

    /** @param Builder<Media> $query */
    public function applyNeedsRepairFilter(Builder $query): Builder
    {
        return $this->applyReasonFilter($query);
    }

    /** @param Builder<Media> $query */
    public function applyReasonFilter(
        Builder $query,
        ?MediaDiagnosticReason $reason = null,
    ): Builder {
        $diagnosticIds = $this->diagnosticIds();
        $ids = $reason instanceof MediaDiagnosticReason
            ? $diagnosticIds[$reason->value]
            : collect($diagnosticIds)->flatten()->unique()->sort()->values()->all();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIntegerInRaw((new Media)->getQualifiedKeyName(), $ids);
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

    /** @return array<string, array<int, int>> */
    private function diagnosticIds(): array
    {
        if (is_array($this->diagnosticIds)) {
            return $this->diagnosticIds;
        }

        $ids = collect(MediaDiagnosticReason::cases())
            ->mapWithKeys(fn (MediaDiagnosticReason $reason): array => [$reason->value => []])
            ->all();

        $this->scope->inventoryQuery()
            ->select([
                'id',
                'disk',
                'directory',
                'visibility',
                'name',
                'path',
                'width',
                'height',
                'size',
                'type',
                'ext',
                'reference_key',
                'updated_at',
            ])
            ->lazyById(250)
            ->each(function (Media $media) use (&$ids): void {
                foreach ($this->reasons($media) as $reason) {
                    if (array_key_exists($reason, $ids)) {
                        $ids[$reason][] = (int) $media->getKey();
                    }
                }
            });

        return $this->diagnosticIds = $ids;
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
