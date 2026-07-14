<?php

namespace App\Support\Settings;

use App\Support\PublicFront\PublicFrontConfigRegistry;

class SettingsSp3aMeasurementFixture
{
    /** @return array<string, mixed> */
    public function payload(): array
    {
        $payload = PublicFrontConfigRegistry::defaults();
        $payload['card_templates'] = collect(PublicFrontConfigRegistry::cardFamilies())
            ->flatMap(fn (string $family): array => collect(range(1, 3))
                ->map(fn (int $variant): array => $this->template($family, $variant))
                ->all())
            ->values()
            ->all();

        return $payload;
    }

    public function bytes(): int
    {
        return strlen(json_encode($this->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    private function template(string $family, int $variant): array
    {
        $source = match ($family) {
            'content_group' => 'content_group',
            'contributor' => 'contributor',
            default => 'content_item',
        };

        return [
            'key' => "sp3a_{$family}_{$variant}",
            'label' => "SP3A measurement {$family} {$variant}",
            'family' => $family,
            'layout' => $variant % 2 === 0 ? 'rows' : 'cards',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'title_size' => 'base',
            'parts' => collect(range(1, 6))
                ->map(fn (int $position): array => [
                    'type' => 'custom_text',
                    'source' => $source,
                    'attribute' => 'title',
                    'visible' => true,
                    'order' => $position * 10,
                    'label_mode' => 'custom',
                    'label' => "Measurement part {$position}",
                    'text' => str_repeat("SP3A deterministic heavy fixture {$family} {$variant} {$position}. ", 6),
                    'layout' => 'stacked',
                    'alignment' => 'start',
                    'font_size' => 'base',
                    'url_target' => 'self',
                ])
                ->all(),
        ];
    }
}
