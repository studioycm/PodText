<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaLibraryTask: string implements HasLabel
{
    case All = 'all';
    case InUse = 'in_use';
    case NoDirectAttachment = 'no_direct_attachment';
    case NeedsAttention = 'needs_attention';
    case Recent = 'recent';

    public function getLabel(): string
    {
        return __("admin.media_library.tasks.{$this->value}");
    }

    public function description(): string
    {
        return __("admin.media_library.task_descriptions.{$this->value}");
    }
}
