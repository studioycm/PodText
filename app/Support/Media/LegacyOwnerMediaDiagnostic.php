<?php

namespace App\Support\Media;

use App\Enums\LegacyOwnerMediaDiagnosticCode;
use App\Enums\MediaAttachmentRole;

/** Safe-to-render diagnostic: never includes a file path, URL, or bytes. */
readonly class LegacyOwnerMediaDiagnostic
{
    public function __construct(
        public LegacyOwnerMediaDiagnosticCode $code,
        public string $ownerType,
        public int $ownerId,
        public MediaAttachmentRole $role,
        public string $fingerprint,
    ) {}
}
