<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('public_content.homepage_card_image_fit', 'cover');
        $this->migrator->add('public_content.homepage_card_image_radius', 'mid_rounded');
        $this->migrator->add('public_content.homepage_group_badge_mode', 'name_only');
        $this->migrator->add('public_content.homepage_group_title_separator', ' - ');
        $this->migrator->add('public_content.homepage_group_badge_duplicate_thumbnail', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.homepage_card_image_fit');
        $this->migrator->deleteIfExists('public_content.homepage_card_image_radius');
        $this->migrator->deleteIfExists('public_content.homepage_group_badge_mode');
        $this->migrator->deleteIfExists('public_content.homepage_group_title_separator');
        $this->migrator->deleteIfExists('public_content.homepage_group_badge_duplicate_thumbnail');
    }
};
