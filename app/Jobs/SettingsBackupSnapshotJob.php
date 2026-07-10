<?php

namespace App\Jobs;

use App\Models\SettingsBackupSnapshot;
use App\Support\SettingsLifecycle\SettingsBackupSnapshotManager;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SettingsBackupSnapshotJob implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    /**
     * @param  array<int, int>  $snapshotIds
     */
    public function __construct(
        public int $backupId,
        public array $snapshotIds = [],
    ) {
        $this->timeout = (int) config('settings-backups.snapshot_job_timeout', 1800);
    }

    public function handle(SettingsBackupSnapshotManager $manager): void
    {
        $query = SettingsBackupSnapshot::query()
            ->where('backup_id', $this->backupId)
            ->where('status', SettingsBackupSnapshot::STATUS_PENDING)
            ->orderBy('id');

        if ($this->snapshotIds !== []) {
            $query->whereKey($this->snapshotIds);
        }

        $query->each(function (SettingsBackupSnapshot $snapshot) use ($manager): void {
            try {
                $manager->processSnapshot($snapshot);
            } catch (Throwable $exception) {
                $snapshot->forceFill([
                    'status' => SettingsBackupSnapshot::STATUS_FAILED,
                    'error' => str($exception->getMessage())->limit(2000)->toString(),
                ])->save();
            }

            if (! app()->runningUnitTests()) {
                usleep(150000);
            }
        });
    }
}
