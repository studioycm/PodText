<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class MediaOwnerTitleApplier
{
    public function __construct(
        private readonly MediaReferenceFinder $references,
        private readonly MediaRecordScope $mediaRecordScope,
    ) {}

    /**
     * Applies the owner-derived title to a just-selected media record, at the
     * moment the owner image choice commits.
     */
    public function applyOwnerSelectionTitle(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        string $referenceKey,
        User $actor,
    ): void {
        $media = $this->mediaRecordScope->findByReferenceKey($referenceKey, $role->purpose());

        if (! $media instanceof Media || ! Gate::forUser($actor)->allows('update', $media)) {
            return;
        }

        $title = trim((string) $owner->title);

        if ($title === '') {
            return;
        }

        $media->forceFill(['title' => $title, 'alt' => $title])->save();
    }

    public function composedTitle(Media $media, string $prefix = '', string $suffix = ''): ?string
    {
        $derived = $this->references->ownerTitleForMedia($media);

        if (blank($derived)) {
            return null;
        }

        return trim((string) preg_replace('/\s+/u', ' ', "{$prefix} {$derived} {$suffix}"));
    }

    /**
     * @param  Collection<int, Media>  $records
     * @return array{titled: array<int, array{name: string, title: string}>, skipped: array<int, string>}
     */
    public function preview(Collection $records): array
    {
        $titled = [];
        $skipped = [];

        foreach ($records as $media) {
            $title = $this->composedTitle($media);
            $name = (string) ($media->title ?: $media->name);

            if ($title === null) {
                $skipped[] = $name;

                continue;
            }

            $titled[] = ['name' => $name, 'title' => $title];
        }

        return ['titled' => $titled, 'skipped' => $skipped];
    }

    /**
     * @param  Collection<int, Media>  $records
     * @return array{titled: int, skipped: int}
     */
    public function apply(Collection $records, User $actor, string $prefix = '', string $suffix = ''): array
    {
        $gate = Gate::forUser($actor);
        $titled = 0;
        $skipped = 0;

        foreach ($records as $media) {
            $title = $this->composedTitle($media, $prefix, $suffix);

            if ($title === null || ! $gate->allows('update', $media)) {
                $skipped++;

                continue;
            }

            $media->forceFill(['title' => $title, 'alt' => $title])->save();
            $titled++;
        }

        return ['titled' => $titled, 'skipped' => $skipped];
    }
}
