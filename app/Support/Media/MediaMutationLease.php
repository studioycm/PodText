<?php

namespace App\Support\Media;

use App\Models\Media;
use Closure;

class MediaMutationLease
{
    /** @var array<int, int> */
    private array $mediaIds = [];

    private int $creationDepth = 0;

    private int $referenceKeyIssuanceDepth = 0;

    public function allowsCreation(): bool
    {
        return $this->creationDepth > 0;
    }

    public function allows(Media $media): bool
    {
        return ($this->mediaIds[(int) $media->getKey()] ?? 0) > 0;
    }

    public function allowsReferenceKeyIssuance(): bool
    {
        return $this->referenceKeyIssuanceDepth > 0;
    }

    public function runReferenceKeyIssuance(Closure $callback): mixed
    {
        $this->referenceKeyIssuanceDepth++;

        try {
            return $callback();
        } finally {
            $this->referenceKeyIssuanceDepth--;
        }
    }

    public function run(Media|int $media, Closure $callback): mixed
    {
        $mediaId = $media instanceof Media ? (int) $media->getKey() : $media;
        $this->mediaIds[$mediaId] = ($this->mediaIds[$mediaId] ?? 0) + 1;

        try {
            return $callback();
        } finally {
            $this->mediaIds[$mediaId]--;

            if ($this->mediaIds[$mediaId] < 1) {
                unset($this->mediaIds[$mediaId]);
            }
        }
    }

    public function runCreation(Closure $callback): mixed
    {
        $this->creationDepth++;

        try {
            return $callback();
        } finally {
            $this->creationDepth--;
        }
    }
}
