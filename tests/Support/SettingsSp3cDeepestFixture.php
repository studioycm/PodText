<?php

namespace Tests\Support;

use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;

final class SettingsSp3cDeepestFixture
{
    public const PROTECTED_TOP_SENTINEL = 'SP3C_PROTECTED_TOP_SENTINEL';

    public const PROTECTED_NESTED_SENTINEL = 'SP3C_PROTECTED_NESTED_SENTINEL';

    public const ORDINARY_SELECTED_SENTINEL = 'SP3C_ORDINARY_SELECTED_SENTINEL';

    public const ORDINARY_UNSELECTED_SENTINEL = 'SP3C_ORDINARY_UNSELECTED_SENTINEL';

    /**
     * @return array<string, mixed>
     */
    public function template(): array
    {
        $parts = [];

        foreach (PublicFrontCardTemplateRegistry::partTypes() as $type) {
            foreach ([1, 2] as $repeat) {
                $parts[] = $this->part($type, $repeat);
            }
        }

        $parts[0]['data']['label'] = self::ORDINARY_SELECTED_SENTINEL;
        $parts[1]['data']['label'] = self::ORDINARY_UNSELECTED_SENTINEL;

        $parts[] = $this->protectedPart(self::PROTECTED_TOP_SENTINEL, 980);

        $groupIndex = collect($parts)->search(
            fn (array $part): bool => $part['type'] === 'part_group',
        );

        if (is_int($groupIndex)) {
            $parts[$groupIndex]['data']['children'][] = $this->protectedPart(
                self::PROTECTED_NESTED_SENTINEL,
                990,
            );
        }

        return [
            'key' => 'sp3c_deepest_canary',
            'family' => PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY,
            'label' => $this->hostile('template-label'),
            'layout' => 'cards',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'title_size' => 'base',
            'parts' => $parts,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templates(int $count): array
    {
        return collect(range(1, $count))
            ->map(function (int $index): array {
                $template = $this->template();
                $template['key'] = "sp3c_scale_{$index}";
                $template['label'] = $this->hostile("scale-template-{$index}");

                return $template;
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sections(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index): array => [
                'id' => $index,
                'name' => $this->hostile("section-{$index}"),
                'type' => 'latest',
                'source_config' => [
                    'source_type' => 'latest_content_items',
                    'template_key' => "sp3c_scale_{$index}",
                ],
                'display_config' => [
                    'template_family' => 'content_item',
                    'template_key' => "sp3c_scale_{$index}",
                ],
            ])
            ->all();
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private function part(string $type, int $repeat): array
    {
        $source = PublicFrontCardTemplateRegistry::defaultSourceForPart($type);
        $attribute = PublicFrontCardTemplateRegistry::defaultAttributeForPart($type);
        $order = (array_search($type, PublicFrontCardTemplateRegistry::partTypes(), true) * 20) + $repeat;
        $data = [
            'visible' => true,
            'label' => $this->hostile("{$type}-label-{$repeat}"),
            'label_position' => 'inline_before',
            'label_alignment' => 'start',
            'icon' => 'none',
            'icon_position' => 'inline_before',
            'layout' => $type === 'part_group' ? 'stacked' : 'inline',
            'order' => $order,
            'line_clamp' => 'none',
            'font_size' => 'base',
            'url_target' => 'self',
            'text' => $this->hostile("{$type}-text-{$repeat}"),
        ];

        if ($source !== null) {
            $data['source'] = $source;
        }

        if ($attribute !== null) {
            $data['attribute'] = $attribute;
        }

        if ($type === 'part_group') {
            $data += [
                'columns' => '2',
                'gap' => 'comfortable',
                'alignment' => 'between',
                'children' => [
                    $this->part('title', 1),
                    $this->part('custom_text', 2),
                    $this->part('divider', 1),
                ],
            ];
        }

        return [
            'type' => $type,
            'data' => $data,
        ];
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private function protectedPart(string $sentinel, int $order): array
    {
        return [
            'type' => 'entity_attribute',
            'data' => [
                'visible' => true,
                'source' => 'content_item',
                'attribute' => 'transcription_count',
                'label' => $sentinel.' '.$this->hostile('protected-label'),
                'label_position' => 'inline_before',
                'label_alignment' => 'start',
                'icon' => 'none',
                'icon_position' => 'inline_before',
                'layout' => 'inline',
                'order' => $order,
                'line_clamp' => 'none',
                'font_size' => 'base',
                'url_target' => 'self',
                'text' => $sentinel.' '.$this->hostile('protected-text'),
            ],
        ];
    }

    private function hostile(string $property): string
    {
        return "SP3C_{$property}_\"><script data-sp3c-hostile=\"{$property}\">alert(1)</script>&'";
    }
}
