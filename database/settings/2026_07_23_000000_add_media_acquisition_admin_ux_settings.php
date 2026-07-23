<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = [
            'admin_ux.media_acquisition_max_kilobytes' => 2048,
            'admin_ux.media_acquisition_max_dimension' => 3000,
            'admin_ux.media_acquisition_upload_batch_limit' => 10,
            'admin_ux.media_picker_browse_limit' => 25,
            'admin_ux.media_picker_search_limit' => 50,
            'admin_ux.media_acquisition_filename_strategy' => 'app_generated',
        ];

        foreach ($defaults as $key => $value) {
            if (! $this->migrator->exists($key)) {
                $this->migrator->add($key, $value);
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'admin_ux.media_acquisition_max_kilobytes',
            'admin_ux.media_acquisition_max_dimension',
            'admin_ux.media_acquisition_upload_batch_limit',
            'admin_ux.media_picker_browse_limit',
            'admin_ux.media_picker_search_limit',
            'admin_ux.media_acquisition_filename_strategy',
        ] as $key) {
            $this->migrator->deleteIfExists($key);
        }
    }
};
