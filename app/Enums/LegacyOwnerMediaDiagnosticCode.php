<?php

namespace App\Enums;

enum LegacyOwnerMediaDiagnosticCode: string
{
    case DisallowedLegacyPath = 'disallowed_legacy_path';
    case DisallowedAttachment = 'disallowed_attachment';
    case DuplicateLegacyRows = 'duplicate_legacy_rows';
    case AttachmentPathMismatch = 'attachment_path_mismatch';
    case MissingAttachmentMedia = 'missing_attachment_media';
}
