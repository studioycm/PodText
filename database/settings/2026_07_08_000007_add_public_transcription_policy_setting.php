<?php

use App\Support\PublicContent\PublicTranscriptionPolicy;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('public_content.transcription_policy', PublicTranscriptionPolicy::defaults());
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.transcription_policy');
    }
};
