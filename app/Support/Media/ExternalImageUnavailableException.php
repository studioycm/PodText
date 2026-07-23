<?php

namespace App\Support\Media;

use App\Enums\ExternalImageFailureReason;
use RuntimeException;
use Throwable;

class ExternalImageUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly ExternalImageFailureReason $reason,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
