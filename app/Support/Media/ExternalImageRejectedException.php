<?php

namespace App\Support\Media;

use App\Enums\ExternalImageFailureReason;
use InvalidArgumentException;
use Throwable;

class ExternalImageRejectedException extends InvalidArgumentException
{
    public function __construct(
        public readonly ExternalImageFailureReason $reason,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
