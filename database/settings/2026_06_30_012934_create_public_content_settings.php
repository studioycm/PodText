<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('public_content.homepage_item_limit', 12);
        $this->migrator->add('public_content.pinned_item_limit', 6);
        $this->migrator->add('public_content.default_public_sort', 'latest_transcription');
        $this->migrator->add('public_content.default_result_layout', 'cards');
        $this->migrator->add('public_content.show_latest_section', true);
        $this->migrator->add('public_content.item_page_layout', 'standard');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.homepage_item_limit');
        $this->migrator->deleteIfExists('public_content.pinned_item_limit');
        $this->migrator->deleteIfExists('public_content.default_public_sort');
        $this->migrator->deleteIfExists('public_content.default_result_layout');
        $this->migrator->deleteIfExists('public_content.show_latest_section');
        $this->migrator->deleteIfExists('public_content.item_page_layout');
    }
};
