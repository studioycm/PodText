<?php

namespace App\Support\Settings\CardTemplates;

use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;

class CardTemplateDraftFactory
{
    /**
     * @return array<string, mixed>
     */
    public function blank(): array
    {
        return [
            'key' => 'new_card',
            'label' => __('admin.settings_sp3c.editor.new_template_label'),
            'family' => PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY,
            'layout' => 'cards',
            'density' => 'comfortable',
            'image_size' => 'medium',
            'title_size' => 'base',
            'parts' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, mixed>  $templates
     * @return array<string, mixed>
     */
    public function clone(array $source, array $templates): array
    {
        $family = (string) ($source['family'] ?? '');
        $sourceKey = (string) ($source['key'] ?? 'template');
        $existing = collect($templates)
            ->filter(fn (mixed $template): bool => is_array($template)
                && ($template['family'] ?? null) === $family)
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key))
            ->flip();

        for ($copy = 1; ; $copy++) {
            $suffix = $copy === 1 ? '_copy' : "_copy_{$copy}";
            $key = substr($sourceKey, 0, CardTemplateIdentity::KEY_MAX_LENGTH - strlen($suffix)).$suffix;

            if (! $existing->has($key)) {
                break;
            }
        }

        $labelSuffix = __('admin.settings_sp3c.editor.copy_suffix', [
            'number' => $copy === 1 ? '' : " {$copy}",
        ]);
        $label = (string) ($source['label'] ?? $sourceKey);
        $label = mb_substr($label, 0, CardTemplateIdentity::LABEL_MAX_LENGTH - mb_strlen($labelSuffix)).$labelSuffix;
        $source['key'] = $key;
        $source['label'] = $label;

        return $source;
    }
}
