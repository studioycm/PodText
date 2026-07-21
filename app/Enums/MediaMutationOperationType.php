<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaMutationOperationType: string implements HasLabel
{
    case Upload = 'upload';
    case Rename = 'rename';
    case Swap = 'swap';
    case Delete = 'delete';
    case ExternalImport = 'external_import';
    case Registration = 'registration';
    case ReferenceKeyBackfill = 'reference_key_backfill';
    case LegacyTransition = 'legacy_transition';
    case LegacyOwnerRepair = 'legacy_owner_repair';

    public function getLabel(): string
    {
        return __('admin.media_mutation_operations.'.$this->value);
    }
}
