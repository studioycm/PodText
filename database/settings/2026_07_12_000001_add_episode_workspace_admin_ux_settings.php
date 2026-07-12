<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = [
            'admin_ux.transcription_presentation_mode' => 'collapsible',
            'admin_ux.transcription_mode' => 'single',
            'admin_ux.show_episode_workspace_hint_line' => true,
            'admin_ux.show_episode_workspace_language_code' => false,
            'admin_ux.tb1_picker_container' => 'modal',
        ];

        foreach ($defaults as $key => $value) {
            if ($this->migrator->exists($key)) {
                continue;
            }

            $this->migrator->add($key, $value);
        }
    }

    public function down(): void
    {
        foreach ([
            'admin_ux.transcription_presentation_mode',
            'admin_ux.transcription_mode',
            'admin_ux.show_episode_workspace_hint_line',
            'admin_ux.show_episode_workspace_language_code',
            'admin_ux.tb1_picker_container',
        ] as $key) {
            $this->migrator->deleteIfExists($key);
        }
    }
};
