<?php

use App\Support\PublicFront\Icons\PublicFrontIconRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->normalizeStoredIcons(reverse: false);
    }

    public function down(): void
    {
        $this->normalizeStoredIcons(reverse: true);
    }

    private function normalizeStoredIcons(bool $reverse): void
    {
        if ($this->migrator->exists('public_content.card_templates')) {
            $this->migrator->update('public_content.card_templates', function (mixed $cardTemplates) use ($reverse): array {
                $cardTemplates = $this->arrayFrom($cardTemplates);

                foreach ($cardTemplates as $index => $template) {
                    $template = $this->arrayFrom($template);
                    $template['parts'] = $this->normalizeParts($template['parts'] ?? [], $reverse);
                    $cardTemplates[$index] = $template;
                }

                return $cardTemplates;
            });
        }

        if ($this->migrator->exists('public_content.item_page')) {
            $this->migrator->update('public_content.item_page', function (mixed $itemPage) use ($reverse): array {
                $itemPage = $this->arrayFrom($itemPage);

                $podcastIdentity = $this->arrayFrom($itemPage['podcast_identity'] ?? []);
                $podcastIdentity = $this->normalizeIconField($podcastIdentity, $reverse, 'podcast');
                $itemPage['podcast_identity'] = $podcastIdentity;

                $infoFields = $this->arrayFrom($itemPage['info_fields'] ?? []);

                foreach ($infoFields as $index => $field) {
                    $infoFields[$index] = $this->normalizeIconField($this->arrayFrom($field), $reverse);
                }

                $itemPage['info_fields'] = $infoFields;

                $dates = $this->arrayFrom($itemPage['dates'] ?? []);

                foreach (['site_published', 'original_published', 'transcription_date'] as $dateKey) {
                    $dates[$dateKey] = $this->normalizeIconField(
                        $this->arrayFrom($dates[$dateKey] ?? []),
                        $reverse,
                        $dateKey === 'transcription_date' ? 'document' : 'calendar',
                    );
                }

                $itemPage['dates'] = $dates;

                return $itemPage;
            });
        }

        if (! $this->migrator->exists('public_content.contributors_page')) {
            return;
        }

        $this->migrator->update('public_content.contributors_page', function (mixed $contributorsPage) use ($reverse): array {
            $contributorsPage = $this->arrayFrom($contributorsPage);
            $cards = $this->arrayFrom($contributorsPage['cards'] ?? []);

            if (array_key_exists('compact_count_icon', $cards)) {
                $cards['compact_count_icon'] = $this->normalizeIconValue(
                    $cards['compact_count_icon'],
                    $reverse,
                    'document-text',
                );
            }

            $contributorsPage['cards'] = $cards;

            return $contributorsPage;
        });
    }

    /**
     * @return array<mixed>
     */
    private function normalizeParts(mixed $parts, bool $reverse): array
    {
        $parts = $this->arrayFrom($parts);

        foreach ($parts as $index => $part) {
            $parts[$index] = $this->normalizePart($part, $reverse);
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePart(mixed $part, bool $reverse): array
    {
        $part = $this->arrayFrom($part);

        if (array_key_exists('data', $part)) {
            $part['data'] = $this->normalizePart($part['data'], $reverse);

            return $part;
        }

        $part = $this->normalizeIconField($part, $reverse);

        if (array_key_exists('children', $part)) {
            $part['children'] = $this->normalizeParts($part['children'], $reverse);
        }

        return $part;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeIconField(array $config, bool $reverse, ?string $preferredLegacyAlias = null): array
    {
        if (array_key_exists('icon', $config)) {
            $config['icon'] = $this->normalizeIconValue($config['icon'], $reverse, $preferredLegacyAlias);
        }

        return $config;
    }

    private function normalizeIconValue(mixed $value, bool $reverse, ?string $preferredLegacyAlias = null): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = $reverse
            ? PublicFrontIconRegistry::legacyAliasFor($value, $preferredLegacyAlias)
            : PublicFrontIconRegistry::normalizeToken($value);

        return $normalized ?? $value;
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
