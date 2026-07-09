<?php

use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = PublicFrontConfigRegistry::defaults()['item_page']['podcast_identity'];

        if (! $this->migrator->exists('public_content.item_page')) {
            return;
        }

        $this->migrator->update('public_content.item_page', function (mixed $itemPage) use ($defaults): array {
            $itemPage = $this->arrayFrom($itemPage);

            return [
                ...$itemPage,
                'podcast_identity' => [
                    ...$defaults,
                    ...$this->arrayFrom($itemPage['podcast_identity'] ?? null),
                ],
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
            $podcastIdentity = $this->arrayFrom($itemPage['podcast_identity'] ?? null);

            unset($podcastIdentity['position'], $podcastIdentity['size']);

            return [
                ...$itemPage,
                'podcast_identity' => $podcastIdentity,
            ];
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
