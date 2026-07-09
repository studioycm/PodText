<?php

namespace App\Http\Controllers;

use App\Models\SettingsBackupVersion;
use App\Support\SettingsLifecycle\SettingsBackupSnapshotManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingsBackupSnapshotsZipController
{
    public function __invoke(SettingsBackupVersion $settingsBackupVersion, SettingsBackupSnapshotManager $snapshots): BinaryFileResponse
    {
        return $snapshots->zipResponse($settingsBackupVersion);
    }
}
