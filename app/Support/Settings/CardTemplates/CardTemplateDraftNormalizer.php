<?php

namespace App\Support\Settings\CardTemplates;

use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontInvalidConfig;

class CardTemplateDraftNormalizer
{
    public function __construct(
        private readonly PublicFrontConfigValidator $validator,
    ) {}

    /**
     * Convert only Filament Builder transport shape. Semantic normalization remains the
     * validator's responsibility and is limited to this one candidate.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function candidate(array $draft): array
    {
        if (! array_key_exists('parts', $draft) || ! is_array($draft['parts'])) {
            return $draft;
        }

        $draft['parts'] = $this->candidateParts($draft['parts']);

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    public function normalizeCandidate(array $candidate): array
    {
        $label = $candidate['label'] ?? null;

        if (! is_string($label) || mb_strlen($label) > CardTemplateIdentity::LABEL_MAX_LENGTH) {
            throw CardTemplateWriteException::validation([
                PublicFrontInvalidConfig::make(
                    'card_templates.0.label',
                    is_string($label) ? 'string_too_long' : 'expected_string',
                    $label,
                ),
            ]);
        }

        $result = $this->validator->validateGroups([
            'card_templates' => [$candidate],
        ], ['card_templates']);
        $templates = $result->group('card_templates');

        if ($result->hasInvalidConfig() || count($templates) !== 1) {
            throw CardTemplateWriteException::validation($result->invalidConfig());
        }

        return $templates[0];
    }

    /**
     * @param  array<int|string, mixed>  $parts
     * @return array<int, mixed>
     */
    private function candidateParts(array $parts): array
    {
        return collect($parts)
            ->filter(fn (mixed $part): bool => is_array($part))
            ->values()
            ->map(function (array $part, int $position): array {
                $type = $part['type'] ?? null;
                $data = is_array($part['data'] ?? null) ? $part['data'] : $part;

                unset($data['order'], $data['_show_label'], $data['_show_icon']);

                if ($type !== 'part_group') {
                    foreach (['columns', 'gap', 'alignment', 'children'] as $groupOnlyField) {
                        unset($data[$groupOnlyField]);
                    }
                } elseif (is_array($data['children'] ?? null)) {
                    $data['children'] = $this->candidateParts($data['children']);
                }

                $data = array_filter($data, fn (mixed $value): bool => $value !== null);
                $data['order'] = ($position + 1) * 10;

                return is_array($part['data'] ?? null)
                    ? ['type' => $type, 'data' => $data]
                    : $data;
            })
            ->all();
    }
}
