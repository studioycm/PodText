<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('public_content.card_templates', []);
        $this->migrator->add('public_content.menu_config', [
            'enabled' => false,
            'items' => [],
        ]);
        $this->migrator->add('public_content.about_page', [
            'enabled' => false,
            'blocks' => [],
            'team_profiles' => [],
        ]);
        $this->migrator->add('public_content.public_forms', [
            'definitions' => [],
        ]);
        $this->migrator->add('public_content.route_labels', []);
        $this->migrator->add('public_content.display_defaults', [
            'layout' => 'cards',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'title_size' => 'base',
            'page_size' => 12,
        ]);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.card_templates');
        $this->migrator->deleteIfExists('public_content.menu_config');
        $this->migrator->deleteIfExists('public_content.about_page');
        $this->migrator->deleteIfExists('public_content.public_forms');
        $this->migrator->deleteIfExists('public_content.route_labels');
        $this->migrator->deleteIfExists('public_content.display_defaults');
    }
};
