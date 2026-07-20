<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaMutationStatus: string implements HasLabel
{
    case Staged = 'staged';
    case Copied = 'copied';
    case Committed = 'committed';
    case CleanupPending = 'cleanup_pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __('admin.media_mutation_statuses.'.$this->value);
    }
}
