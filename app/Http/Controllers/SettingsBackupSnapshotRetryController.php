<?php

namespace App\Http\Controllers;

use App\Models\SettingsBackupSnapshot;
use App\Support\SettingsLifecycle\SettingsBackupSnapshotManager;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;

class SettingsBackupSnapshotRetryController
{
    public function __invoke(SettingsBackupSnapshot $settingsBackupSnapshot, SettingsBackupSnapshotManager $snapshots): RedirectResponse
    {
        $snapshots->retry($settingsBackupSnapshot);

        Notification::make()
            ->success()
            ->title(__('admin.notifications.settings_backup_snapshot_retry_queued'))
            ->send();

        return back();
    }
}
