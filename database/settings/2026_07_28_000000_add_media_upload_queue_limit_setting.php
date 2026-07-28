<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('admin_ux.media_upload_queue_limit')) {
            $this->migrator->add('admin_ux.media_upload_queue_limit', 40);
        }
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('admin_ux.media_upload_queue_limit');
    }
};
