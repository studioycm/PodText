<?php

namespace App\Support\Media;

use App\Models\Media;
use Illuminate\Support\Collection;

readonly class MediaUploadBatchResult
{
    /**
     * @param  Collection<int, MediaAcquisitionResult>  $successful
     */
    public function __construct(
        public Collection $successful,
        public int $failedCount = 0,
        public int $notAttemptedCount = 0,
    ) {}

    /** @return Collection<int, Media> */
    public function media(): Collection
    {
        return $this->successful
            ->map(fn (MediaAcquisitionResult $result): Media => $result->media)
            ->values();
    }

    public function unsuccessfulCount(): int
    {
        return $this->failedCount + $this->notAttemptedCount;
    }

    public function isPartial(): bool
    {
        return $this->successful->isNotEmpty() && $this->unsuccessfulCount() > 0;
    }
}
