<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if ($this->migrator->exists('admin_ux.media_naming_strategy')) {
            return;
        }

        $this->migrator->add('admin_ux.media_naming_strategy', 'slug');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('admin_ux.media_naming_strategy');
    }
};
