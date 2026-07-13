<?php

namespace App\Support\Publication;

use App\Enums\PublicationStatus;
use Carbon\CarbonInterface;

class PublicationDateAutofill
{
    public static function valueFor(mixed $status, mixed $currentPublishedAt, ?CarbonInterface $now = null): mixed
    {
        if (! self::isPublished($status) || filled($currentPublishedAt)) {
            return $currentPublishedAt;
        }

        return $now ?? now('Asia/Jerusalem');
    }

    private static function isPublished(mixed $status): bool
    {
        if ($status instanceof PublicationStatus) {
            return $status === PublicationStatus::Published;
        }

        return $status === PublicationStatus::Published->value;
    }
}
