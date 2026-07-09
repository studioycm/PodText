<?php

use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $default = PublicFrontConfigRegistry::defaults()['item_page']['show_transcript_actions_menu'];

        if (! $this->migrator->exists('public_content.item_page')) {
            $this->migrator->add('public_content.item_page', PublicFrontConfigRegistry::defaults()['item_page']);

            return;
        }

        $this->migrator->update('public_content.item_page', function (mixed $itemPage) use ($default): array {
            $itemPage = $this->arrayFrom($itemPage);

            return [
                ...$itemPage,
                'show_transcript_actions_menu' => is_bool($itemPage['show_transcript_actions_menu'] ?? null)
                    ? $itemPage['show_transcript_actions_menu']
                    : $default,
            ];
        });
    }

    public function down(): void
    {
        if (! $this->migrator->exists('public_content.item_page')) {
            return;
        }

        $this->migrator->update('public_content.item_page', function (mixed $itemPage): array {
            $itemPage = $this->arrayFrom($itemPage);

            unset($itemPage['show_transcript_actions_menu']);

            return $itemPage;
        });
    }

    /**
     * @return array<mixed>
     */
    private function arrayFrom(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_object($value)) {
            return [];
        }

        $decoded = json_decode(json_encode($value), true);

        return is_array($decoded) ? $decoded : [];
    }
};
