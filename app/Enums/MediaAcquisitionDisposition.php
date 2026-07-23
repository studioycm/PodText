<?php

namespace App\Enums;

enum MediaAcquisitionDisposition: string
{
    case Copied = 'copied';
    case Created = 'created';
    case Registered = 'registered';
    case Reused = 'reused';
}
