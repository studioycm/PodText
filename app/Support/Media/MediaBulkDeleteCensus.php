<?php

namespace App\Support\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MediaBulkDeleteCensus
{
    public function __construct(
        private readonly MediaReferenceFinder $references,
        private readonly MediaFilesystemMutationCoordinator $coordinator,
    ) {}

    /**
     * @param  Collection<int, Media>  $records
     * @return array{eligible: Collection<int, Media>, blocked: array<int, array{media: Media, reason: string}>}
     */
    public function classify(Collection $records, User $actor): array
    {
        $this->references->prime($records);
        $gate = Gate::forUser($actor);
        $eligible = new Collection;
        $blocked = [];

        foreach ($records as $media) {
            $response = $gate->inspect('delete', $media);

            if ($response->allowed()) {
                $eligible->push($media);

                continue;
            }

            $blocked[] = [
                'media' => $media,
                'reason' => (string) ($response->message() ?: __('admin.media_library.op_blocked_unmanaged')),
            ];
        }

        return ['eligible' => $eligible, 'blocked' => $blocked];
    }

    /**
     * @param  Collection<int, Media>  $records
     */
    public function censusView(Collection $records, User $actor): View
    {
        $census = $this->classify($records, $actor);

        return view('filament.resources.media.bulk-delete-census', [
            'eligibleCount' => $census['eligible']->count(),
            'blocked' => array_map(fn (array $row): array => [
                'name' => (string) ($row['media']->title ?: $row['media']->name),
                'reason' => $row['reason'],
            ], $census['blocked']),
        ]);
    }

    /**
     * Deletes only the census-eligible records, skipping blocked ones.
     *
     * @param  Collection<int, Media>  $records
     * @return array{deleted: int, blocked: int, failed: int, deleted_ids: array<int, int>}
     */
    public function execute(Collection $records, User $actor): array
    {
        $census = $this->classify($records, $actor);
        $deleted = 0;
        $failed = 0;
        $deletedIds = [];

        foreach ($census['eligible'] as $media) {
            try {
                $this->coordinator->delete($media, $actor);
                $deleted++;
                $deletedIds[] = (int) $media->getKey();
            } catch (ValidationException|RuntimeException) {
                $failed++;
            }
        }

        return [
            'deleted' => $deleted,
            'blocked' => count($census['blocked']),
            'failed' => $failed,
            'deleted_ids' => $deletedIds,
        ];
    }
}
