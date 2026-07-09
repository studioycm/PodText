<?php

use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = PublicFrontConfigRegistry::defaults()['item_page'];

        if (! $this->migrator->exists('public_content.item_page')) {
            $this->migrator->add('public_content.item_page', $defaults);

            return;
        }

        $this->migrator->update('public_content.item_page', function (mixed $itemPage) use ($defaults): array {
            $itemPage = $this->arrayFrom($itemPage);
            $dates = $this->arrayFrom($itemPage['dates'] ?? null);
            $badges = $this->arrayFrom($itemPage['badges'] ?? null);

            return [
                ...$defaults,
                ...$itemPage,
                'podcast_identity' => [
                    ...$defaults['podcast_identity'],
                    ...$this->arrayFrom($itemPage['podcast_identity'] ?? null),
                ],
                'info_fields' => is_array($itemPage['info_fields'] ?? null) && array_is_list($itemPage['info_fields'])
                    ? $itemPage['info_fields']
                    : $defaults['info_fields'],
                'dates' => [
                    ...$defaults['dates'],
                    ...$dates,
                    'site_published' => [
                        ...$defaults['dates']['site_published'],
                        ...$this->arrayFrom($dates['site_published'] ?? null),
                    ],
                    'original_published' => [
                        ...$defaults['dates']['original_published'],
                        ...$this->arrayFrom($dates['original_published'] ?? null),
                    ],
                    'transcription_date' => [
                        ...$defaults['dates']['transcription_date'],
                        ...$this->arrayFrom($dates['transcription_date'] ?? null),
                    ],
                ],
                'badges' => [
                    ...$defaults['badges'],
                    ...$badges,
                    'info' => [
                        ...$defaults['badges']['info'],
                        ...$this->arrayFrom($badges['info'] ?? null),
                    ],
                ],
            ];
        });
    }

    public function down(): void
    {
        $defaults = PublicFrontConfigRegistry::defaults()['item_page'];
        $legacyDefaults = $defaults;
        unset($legacyDefaults['show_breadcrumbs'], $legacyDefaults['podcast_identity'], $legacyDefaults['info_fields']);

        if (! $this->migrator->exists('public_content.item_page')) {
            return;
        }

        $this->migrator->update('public_content.item_page', function (mixed $itemPage) use ($legacyDefaults): array {
            $itemPage = $this->arrayFrom($itemPage);
            $dates = $this->arrayFrom($itemPage['dates'] ?? null);
            $badges = $this->arrayFrom($itemPage['badges'] ?? null);

            unset($itemPage['show_breadcrumbs'], $itemPage['podcast_identity'], $itemPage['info_fields']);

            return [
                ...$legacyDefaults,
                ...$itemPage,
                'dates' => [
                    ...$legacyDefaults['dates'],
                    ...$dates,
                    'site_published' => [
                        ...$legacyDefaults['dates']['site_published'],
                        ...$this->arrayFrom($dates['site_published'] ?? null),
                    ],
                    'original_published' => [
                        ...$legacyDefaults['dates']['original_published'],
                        ...$this->arrayFrom($dates['original_published'] ?? null),
                    ],
                    'transcription_date' => [
                        ...$legacyDefaults['dates']['transcription_date'],
                        ...$this->arrayFrom($dates['transcription_date'] ?? null),
                    ],
                ],
                'badges' => [
                    ...$legacyDefaults['badges'],
                    ...$badges,
                    'info' => [
                        ...$legacyDefaults['badges']['info'],
                        ...$this->arrayFrom($badges['info'] ?? null),
                    ],
                ],
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
