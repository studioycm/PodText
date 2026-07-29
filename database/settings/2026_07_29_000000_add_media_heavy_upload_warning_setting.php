<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('admin_ux.media_heavy_upload_warning_kilobytes')) {
            $this->migrator->add('admin_ux.media_heavy_upload_warning_kilobytes', 2048);
        }
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('admin_ux.media_heavy_upload_warning_kilobytes');
    }
};
