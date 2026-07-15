<?php

namespace App\Support\Settings\CardTemplates;

use App\Models\HomepageSection;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\Sections\PublicDisplaySectionConfigValidator;

class CardTemplateReferenceScanner
{
    public function __construct(
        private readonly PublicDisplaySectionConfigValidator $validator,
        private readonly CardTemplateIdentity $identity,
    ) {}

    /**
     * @param  array<string, mixed>  $settingsSnapshot
     */
    public function scan(array $settingsSnapshot): CardTemplateReferences
    {
        $startedAt = hrtime(true);
        $references = [];
        $ambiguous = [];

        $podcastsPage = is_array($settingsSnapshot['podcasts_page'] ?? null)
            ? $settingsSnapshot['podcasts_page']
            : [];

        $this->addSettingsReference(
            $references,
            PublicFrontCardTemplateRegistry::CONTENT_GROUP_FAMILY,
            $podcastsPage['template_key'] ?? null,
            'podcasts_page.template_key',
        );
        $this->addSettingsReference(
            $references,
            PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY,
            $podcastsPage['item_template_key'] ?? null,
            'podcasts_page.item_template_key',
        );

        $sections = HomepageSection::query()
            ->select(['id', 'name', 'type', 'source_config', 'display_config'])
            ->orderBy('id')
            ->get();

        foreach ($sections as $section) {
            $displayConfig = $section->displayConfig();
            $rawKey = $displayConfig['template_key'] ?? null;

            if ($rawKey !== null && (! is_string($rawKey) || ! $this->identity->validKey($rawKey))) {
                continue;
            }

            $result = $this->validator->validate($section);
            $family = $result->displayConfig['template_family'] ?? null;

            if (! is_string($family) || ! $this->identity->validFamily($family)) {
                if (is_string($rawKey)) {
                    $ambiguous[$rawKey][] = [
                        'id' => (int) $section->id,
                        'name' => (string) $section->name,
                    ];
                }

                continue;
            }

            if (is_string($rawKey)) {
                $references[$this->identity->make($family, $rawKey)]['sections'][] = [
                    'id' => (int) $section->id,
                    'name' => (string) $section->name,
                ];

                continue;
            }

            $defaultKey = PublicFrontCardTemplateRegistry::defaultTemplateKeys()[$family] ?? null;

            if (is_string($defaultKey)) {
                $references[$this->identity->make($family, $defaultKey)]['implicit'] =
                    ($references[$this->identity->make($family, $defaultKey)]['implicit'] ?? 0) + 1;
            }
        }

        foreach ($references as $key => $reference) {
            $references[$key] = [
                'settings' => array_values($reference['settings'] ?? []),
                'sections' => array_values($reference['sections'] ?? []),
                'implicit' => (int) ($reference['implicit'] ?? 0),
            ];
        }

        return new CardTemplateReferences(
            references: $references,
            ambiguousKeys: $ambiguous,
            sectionRows: $sections->count(),
            milliseconds: (hrtime(true) - $startedAt) / 1_000_000,
        );
    }

    /**
     * @param  array<string, mixed>  $references
     */
    private function addSettingsReference(
        array &$references,
        string $family,
        mixed $key,
        string $path,
    ): void {
        if (is_string($key) && $this->identity->validKey($key)) {
            $references[$this->identity->make($family, $key)]['settings'][] = $path;

            return;
        }

        if ($key !== null) {
            return;
        }

        $defaultKey = PublicFrontCardTemplateRegistry::defaultTemplateKeys()[$family];
        $identity = $this->identity->make($family, $defaultKey);
        $references[$identity]['implicit'] = ($references[$identity]['implicit'] ?? 0) + 1;
    }
}
