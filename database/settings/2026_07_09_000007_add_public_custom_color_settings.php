<?php

use App\Support\PublicFront\Colors\PublicFrontColor;
use App\Support\PublicFront\ItemPage\PublicItemPageRegistry;
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
            $badges = $this->arrayFrom($itemPage['badges'] ?? null);

            return [
                ...$defaults,
                ...$itemPage,
                'podcast_identity' => $this->normalizeCustomColor(
                    [
                        ...$defaults['podcast_identity'],
                        ...$this->arrayFrom($itemPage['podcast_identity'] ?? null),
                    ],
                    'primary',
                ),
                'info_fields' => $this->normalizeInfoFields($itemPage['info_fields'] ?? null, $defaults['info_fields']),
                'badges' => [
                    ...$defaults['badges'],
                    ...$badges,
                    'info' => $this->normalizeCustomColor(
                        [
                            ...$defaults['badges']['info'],
                            ...$this->arrayFrom($badges['info'] ?? null),
                        ],
                        'gray',
                    ),
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
            $badges = $this->arrayFrom($itemPage['badges'] ?? null);

            $itemPage['podcast_identity'] = $this->removeCustomColor(
                $this->arrayFrom($itemPage['podcast_identity'] ?? null),
                'primary',
            );
            $itemPage['info_fields'] = $this->removeInfoFieldCustomColors($itemPage['info_fields'] ?? null);
            $itemPage['badges'] = [
                ...$badges,
                'info' => $this->removeCustomColor($this->arrayFrom($badges['info'] ?? null), 'gray'),
            ];

            return $itemPage;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $defaults
     * @return array<int, array<string, mixed>>
     */
    private function normalizeInfoFields(mixed $fields, array $defaults): array
    {
        if (! is_array($fields) || ! array_is_list($fields)) {
            $fields = $defaults;
        }

        foreach ($fields as $index => $field) {
            $fields[$index] = $this->normalizeCustomColor($this->arrayFrom($field), 'gray');
        }

        return $fields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function removeInfoFieldCustomColors(mixed $fields): array
    {
        if (! is_array($fields) || ! array_is_list($fields)) {
            return [];
        }

        foreach ($fields as $index => $field) {
            $fields[$index] = $this->removeCustomColor($this->arrayFrom($field), 'gray');
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeCustomColor(array $config, string $fallbackColor): array
    {
        $config['custom_color'] = PublicFrontColor::normalizeHex($config['custom_color'] ?? null);

        if (($config['color'] ?? null) !== PublicItemPageRegistry::CUSTOM_COLOR) {
            $config['custom_color'] = null;

            return $config;
        }

        if ($config['custom_color'] === null) {
            $config['color'] = $fallbackColor;
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function removeCustomColor(array $config, string $fallbackColor): array
    {
        if (($config['color'] ?? null) === PublicItemPageRegistry::CUSTOM_COLOR) {
            $config['color'] = $fallbackColor;
        }

        unset($config['custom_color']);

        return $config;
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
