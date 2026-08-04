<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an acquisition actually did with the file. The label is the
 * notification title the picker shows on completion.
 */
enum MediaAcquisitionDisposition: string implements HasLabel
{
    case Copied = 'copied';
    case Created = 'created';
    case Registered = 'registered';
    case Reused = 'reused';

    public function getLabel(): string
    {
        return __("admin.media_library.storage_{$this->value}");
    }
}
