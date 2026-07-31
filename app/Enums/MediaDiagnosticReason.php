<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MediaDiagnosticReason: string implements HasColor, HasIcon, HasLabel
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

    /**
     * Severity, not category: a missing file or an unusable disk breaks
     * delivery outright, while metadata and identity gaps degrade it.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::MissingFile, self::StorageDisk => 'danger',
            self::AudienceDenied, self::UnsanitizedSvg => 'warning',
            self::PortableIdentity, self::Metadata => 'info',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::MissingFile => Heroicon::OutlinedDocumentMinus,
            self::StorageDisk => Heroicon::OutlinedServerStack,
            self::AudienceDenied => Heroicon::OutlinedLockClosed,
            self::UnsanitizedSvg => Heroicon::OutlinedShieldExclamation,
            self::PortableIdentity => Heroicon::OutlinedFingerPrint,
            self::Metadata => Heroicon::OutlinedInformationCircle,
        };
    }

    /** Tailwind fill for a solid bar in this finding's colour. */
    public function barClass(): string
    {
        return match ($this) {
            self::MissingFile, self::StorageDisk => 'bg-danger-500',
            self::AudienceDenied, self::UnsanitizedSvg => 'bg-warning-500',
            self::PortableIdentity, self::Metadata => 'bg-info-500',
        };
    }
}
