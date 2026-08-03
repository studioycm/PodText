<?php

namespace App\Enums;

/**
 * What applying the import would do to one settings row — the vocabulary
 * SettingsPackageImportAnalyzer::outcome() answers in and the import wizard's
 * summary, selection defaults and report read. Distinct from
 * SettingsImportMode (the operator-chosen strategy), even though the
 * `replace` spelling appears in both.
 */
enum SettingsImportRowOutcome: string
{
    case AddNew = 'add_new';
    case Error = 'error';
    case Remove = 'remove';
    case Replace = 'replace';
    case SkipExists = 'skip_exists';
    case SkipLocked = 'skip_locked';
    case SkipUnchanged = 'skip_unchanged';
}
