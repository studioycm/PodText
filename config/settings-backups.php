<?php

return [
    'retention' => (int) env('SETTINGS_BACKUPS_RETENTION', 25),
    'snapshot_process_timeout' => (int) env('SETTINGS_BACKUP_SNAPSHOT_PROCESS_TIMEOUT', 120),
];
