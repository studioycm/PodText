<?php

use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = PublicFrontConfigRegistry::defaults()['settings_backups'];

        if ($this->migrator->exists('public_content.settings_backups')) {
            $this->migrator->update('public_content.settings_backups', $defaults);

            return;
        }

        $this->migrator->add('public_content.settings_backups', $defaults);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.settings_backups');
    }
};
