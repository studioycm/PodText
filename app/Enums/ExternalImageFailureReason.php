<?php

namespace App\Enums;

enum ExternalImageFailureReason: string
{
    case Blocked = 'blocked';
    case InvalidImage = 'invalid_image';
    case InvalidResponse = 'invalid_response';
    case NotFound = 'not_found';
    case TemporarilyUnavailable = 'temporarily_unavailable';
    case TimedOut = 'timed_out';
    case Unexpected = 'unexpected';
}
