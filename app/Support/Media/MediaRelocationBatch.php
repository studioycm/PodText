<?php

namespace App\Support\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MediaRelocationBatch
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
        private readonly MediaFilesystemMutationCoordinator $coordinator,
        private readonly MediaReferenceFinder $references,
        private readonly MediaInventoryDiagnostics $diagnostics,
    ) {}

    /**
     * Root-level rows are the relocation cohort: single-segment public paths.
     *
     * @return Collection<int, Media>
     */
    public function candidates(): Collection
    {
        return Media::query()
            ->where('disk', 'public')
            ->where('path', 'not like', '%/%')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{
     *     relocatable: Collection<int, Media>,
     *     skipped: array<int, array{media: Media, reason: string}>,
     * }
     */
    public function census(User $actor): array
    {
        $gate = Gate::forUser($actor);
        $settingsPaths = $this->references->settingsIdentityCandidates()['paths'];
        $relocatable = new Collection;
        $skipped = [];

        foreach ($this->candidates() as $media) {
            $response = $gate->inspect('relocate', $media);

            if (! $response->allowed()) {
                $skipped[] = [
                    'media' => $media,
                    'reason' => (string) ($response->message() ?: __('admin.media_library.op_blocked_already_managed')),
                ];

                continue;
            }

            if (in_array((string) $media->path, $settingsPaths, true)) {
                $skipped[] = [
                    'media' => $media,
                    'reason' => __('admin.media_library.relocation_skip_settings'),
                ];

                continue;
            }

            if (
                (int) $media->size > CuratorImageUploadPolicy::MAX_KILOBYTES * 1024
                || (int) $media->width > CuratorImageUploadPolicy::MAX_DIMENSION_PIXELS
                || (int) $media->height > CuratorImageUploadPolicy::MAX_DIMENSION_PIXELS
            ) {
                $skipped[] = [
                    'media' => $media,
                    'reason' => __('admin.media_library.relocation_skip_oversized'),
                ];

                continue;
            }

            $relocatable->push($media);
        }

        return ['relocatable' => $relocatable, 'skipped' => $skipped];
    }

    /**
     * Relocates up to $limit census-relocatable rows. Resume is census-based:
     * relocated rows drop out of the candidate query, so re-running continues
     * exactly where the last run stopped.
     *
     * Rows that already failed this run are excluded so a continuation chain
     * never spins on a persistently failing row.
     *
     * @param  array<int, int>  $excludeIds
     * @return array{moved: int, failed: array<int, array{id: int, name: string, reason: string}>, remaining: int}
     */
    public function executeChunk(User $actor, int $limit = 50, array $excludeIds = []): array
    {
        $census = $this->census($actor);
        $pending = $census['relocatable']->reject(
            fn (Media $media): bool => in_array((int) $media->getKey(), $excludeIds, true),
        );
        $moved = 0;
        $failed = [];

        foreach ($pending->take(max(1, $limit)) as $media) {
            $name = (string) ($media->title ?: $media->name);

            try {
                $updated = $this->coordinator->relocate($media, $actor);
                $this->diagnostics->forget($media);
                $this->diagnostics->forget($updated);
                $moved++;
            } catch (InvalidArgumentException) {
                $failed[] = [
                    'id' => (int) $media->getKey(),
                    'name' => $name,
                    'reason' => __('admin.media_library.relocation_fail_unsafe'),
                ];
            } catch (RuntimeException|Throwable $exception) {
                $failed[] = [
                    'id' => (int) $media->getKey(),
                    'name' => $name,
                    'reason' => __('admin.media_library.op_failed_body'),
                ];
                report($exception);
            }
        }

        $remaining = max(0, $pending->count() - $moved - count($failed));

        return ['moved' => $moved, 'failed' => $failed, 'remaining' => $remaining];
    }
}
