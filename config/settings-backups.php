<?php

return [
    'retention' => (int) env('SETTINGS_BACKUPS_RETENTION', 25),
    'retention_manual' => (int) env('SETTINGS_BACKUPS_RETENTION_MANUAL', 0),
    'retention_before_import' => (int) env('SETTINGS_BACKUPS_RETENTION_BEFORE_IMPORT', 25),
    'retention_before_restore' => (int) env('SETTINGS_BACKUPS_RETENTION_BEFORE_RESTORE', 25),
    'snapshot_job_timeout' => (int) env('SETTINGS_BACKUP_SNAPSHOT_JOB_TIMEOUT', 1800),
    'snapshot_process_timeout' => (int) env('SETTINGS_BACKUP_SNAPSHOT_PROCESS_TIMEOUT', 120),
];
