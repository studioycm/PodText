<?php

namespace App\Support\Media;

use App\Enums\ExternalImageFailureReason;
use InvalidArgumentException;
use Throwable;

class ExternalImageFailureMessage
{
    public static function for(Throwable $exception): string
    {
        $reason = match (true) {
            $exception instanceof ExternalImageRejectedException,
            $exception instanceof ExternalImageUnavailableException => $exception->reason,
            $exception instanceof InvalidArgumentException => ExternalImageFailureReason::InvalidImage,
            default => ExternalImageFailureReason::Unexpected,
        };

        return $reason->getLabel();
    }

    public static function isExpected(Throwable $exception): bool
    {
        return $exception instanceof ExternalImageRejectedException
            || $exception instanceof ExternalImageUnavailableException
            || $exception instanceof InvalidArgumentException;
    }
}
