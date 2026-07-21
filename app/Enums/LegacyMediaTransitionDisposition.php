<?php

namespace App\Enums;

/**
 * Every legacy observation is deliberately classified. A caller must never
 * infer that an unclassified row is safe to make visible.
 */
enum LegacyMediaTransitionDisposition: string
{
    case KeyOnly = 'key_only';
    case NormalizeExisting = 'normalize_existing';
    case SanitizeSvg = 'sanitize_svg';
    case ImportExactPath = 'import_exact_path';
    case DetachToDefault = 'detach_to_default';
    case Blocked = 'blocked';
}
