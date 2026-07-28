<?php

namespace App\Support\Media;

use App\Models\Media;
use Illuminate\Support\Collection;

readonly class MediaUploadBatchResult
{
    public int $failedCount;

    public int $notAttemptedCount;

    /**
     * @param  Collection<int, MediaAcquisitionResult>  $successful
     * @param  Collection<int, array{index: int, reason: string}>  $failed
     * @param  array<int, int>  $notAttemptedIndexes
     * @param  array<int, int>  $admittedIndexes
     */
    public function __construct(
        public Collection $successful,
        public Collection $failed = new Collection,
        public array $notAttemptedIndexes = [],
        public array $admittedIndexes = [],
    ) {
        $this->failedCount = $this->failed->count();
        $this->notAttemptedCount = count($this->notAttemptedIndexes);
    }

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

    public function nothingAdmittedForInvalidFiles(): bool
    {
        return $this->admittedIndexes === [] && $this->failed->isNotEmpty();
    }
}
