<?php

use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = PublicFrontConfigRegistry::defaults()['maintenance'];

        if ($this->migrator->exists('public_content.maintenance')) {
            $this->migrator->update('public_content.maintenance', $defaults);

            return;
        }

        $this->migrator->add('public_content.maintenance', $defaults);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.maintenance');
    }
};
