<?php

namespace App\Support\Settings\CardTemplates;

use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;

class CardTemplatePartSummaryFormatter
{
    /**
     * @param  array<string, mixed>  $part
     * @return array{title: string, context: string|null, text: string|null}
     */
    public function summarize(array $part): array
    {
        $label = is_string($part['label'] ?? null) && filled($part['label'])
            ? $part['label']
            : __('admin.settings_sp3c.editor.unlabelled_part');
        $source = is_string($part['source'] ?? null) ? $part['source'] : null;
        $attribute = is_string($part['attribute'] ?? null) ? $part['attribute'] : null;
        $sourceLabel = $this->optionLabel($source, PublicFrontConfigRegistry::cardSourceOptions());
        $attributeLabel = $this->optionLabel(
            $attribute,
            MultiTranscriptionSurfaces::filterCardAttributeOptions(
                $source,
                PublicFrontConfigRegistry::cardAttributeOptions($source),
                $attribute,
            ),
        );
        $context = ($source !== null || $attribute !== null)
            ? __('admin.settings_sp3c.editor.part_source', [
                'source' => $sourceLabel ?? '—',
                'attribute' => $attributeLabel ?? '—',
            ])
            : null;

        return [
            'title' => (string) $label,
            'context' => $context,
            'text' => is_string($part['text'] ?? null) && filled($part['text']) ? $part['text'] : null,
        ];
    }

    /**
     * @param  array<string, string>  $options
     */
    private function optionLabel(?string $value, array $options): ?string
    {
        if ($value === null) {
            return null;
        }

        return $options[$value] ?? __('admin.settings_sp3c.editor.unknown_value', ['value' => $value]);
    }
}
