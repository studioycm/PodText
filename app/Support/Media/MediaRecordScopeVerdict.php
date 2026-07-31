<?php

namespace App\Support\Media;

/**
 * Structured result of a record-scope evaluation: the exact facts that fail,
 * in evaluation order, instead of one folded boolean.
 */
readonly class MediaRecordScopeVerdict
{
    /** @param array<int, string> $failureCodes */
    public function __construct(
        public array $failureCodes,
    ) {}

    public function passes(): bool
    {
        return $this->failureCodes === [];
    }
}
