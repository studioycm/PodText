<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('public_content.homepage_card_image_size', 'medium');
        $this->migrator->add('public_content.homepage_card_density', 'comfortable');
        $this->migrator->add('public_content.homepage_card_title_size', 'base');
        $this->migrator->add('public_content.homepage_show_group_badge', true);
        $this->migrator->add('public_content.homepage_show_authors', true);
        $this->migrator->add('public_content.homepage_show_categories', true);
        $this->migrator->add('public_content.homepage_show_tags', true);
        $this->migrator->add('public_content.homepage_show_duration', true);
        $this->migrator->add('public_content.homepage_show_effective_date', true);
        $this->migrator->add('public_content.homepage_show_description', true);
        $this->migrator->add('public_content.homepage_description_lines', 3);
        $this->migrator->add('public_content.homepage_cards_per_page', 12);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.homepage_card_image_size');
        $this->migrator->deleteIfExists('public_content.homepage_card_density');
        $this->migrator->deleteIfExists('public_content.homepage_card_title_size');
        $this->migrator->deleteIfExists('public_content.homepage_show_group_badge');
        $this->migrator->deleteIfExists('public_content.homepage_show_authors');
        $this->migrator->deleteIfExists('public_content.homepage_show_categories');
        $this->migrator->deleteIfExists('public_content.homepage_show_tags');
        $this->migrator->deleteIfExists('public_content.homepage_show_duration');
        $this->migrator->deleteIfExists('public_content.homepage_show_effective_date');
        $this->migrator->deleteIfExists('public_content.homepage_show_description');
        $this->migrator->deleteIfExists('public_content.homepage_description_lines');
        $this->migrator->deleteIfExists('public_content.homepage_cards_per_page');
    }
};
