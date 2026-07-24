<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaDiagnosticReason: string implements HasLabel
{
    case PortableIdentity = 'portable_identity';
    case StorageDisk = 'storage_disk';
    case MissingFile = 'missing_file';
    case AudienceDenied = 'audience_denied';
    case UnsanitizedSvg = 'unsanitized_svg';
    case Metadata = 'metadata';

    public function getLabel(): string
    {
        return __("admin.media_library.repair_{$this->value}");
    }
}
