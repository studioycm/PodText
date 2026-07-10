<?php

use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = PublicFrontConfigRegistry::defaults()['import_locks'];

        if ($this->migrator->exists('public_content.import_locks')) {
            $this->migrator->update('public_content.import_locks', $defaults);

            return;
        }

        $this->migrator->add('public_content.import_locks', $defaults);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.import_locks');
    }
};
