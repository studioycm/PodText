<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a safe external-image fetch was refused or failed. The label is the
 * operator-facing message the picker notifications show; it lived as a
 * string-interpolated key in ExternalImageFailureMessage until E4 gave the
 * enum its contract (one home, every call site routed — unrouted-enum).
 */
enum ExternalImageFailureReason: string implements HasLabel
{
    case Blocked = 'blocked';
    case InvalidImage = 'invalid_image';
    case InvalidResponse = 'invalid_response';
    case NotFound = 'not_found';
    case TemporarilyUnavailable = 'temporarily_unavailable';
    case TimedOut = 'timed_out';
    case Unexpected = 'unexpected';

    public function getLabel(): string
    {
        return __("admin.media_library.url_failure_{$this->value}");
    }
}
