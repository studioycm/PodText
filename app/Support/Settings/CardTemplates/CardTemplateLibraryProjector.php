<?php

namespace App\Support\Settings\CardTemplates;

use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use Throwable;

class CardTemplateLibraryProjector
{
    public function __construct(
        private readonly CardTemplateIdentity $identity,
        private readonly CardTemplateAccessPolicy $accessPolicy,
        private readonly CardTemplateReferenceScanner $referenceScanner,
        private readonly PublicFrontConfigValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $settingsSnapshot
     */
    public function project(array $settingsSnapshot): CardTemplateLibraryProjection
    {
        $references = $this->referenceScanner->scan($settingsSnapshot);
        $rawTemplates = $settingsSnapshot['card_templates'] ?? null;

        if (! is_array($rawTemplates) || ! array_is_list($rawTemplates)) {
            return new CardTemplateLibraryProjection([
                $this->diagnosticRecord(0, $rawTemplates, 'invalid_root'),
            ], $references);
        }

        $identityCounts = [];

        foreach ($rawTemplates as $template) {
            if (! is_array($template)) {
                continue;
            }

            $rawIdentity = $this->identity->fromTemplate($template);

            if ($rawIdentity !== null) {
                $identityCounts[$rawIdentity] = ($identityCounts[$rawIdentity] ?? 0) + 1;
            }
        }

        $records = [];
        $configuredIdentities = [];
        $capable = $this->accessPolicy->currentActorCanManageProtectedTemplates();

        foreach ($rawTemplates as $index => $template) {
            if (! is_array($template)) {
                $records[] = $this->diagnosticRecord($index, $template, 'malformed');

                continue;
            }

            $rawIdentity = $this->identity->fromTemplate($template);

            if ($rawIdentity === null || ($identityCounts[$rawIdentity] ?? 0) !== 1 || ! $this->validRow($template)) {
                $records[] = $this->diagnosticRecord(
                    $index,
                    $template,
                    ($rawIdentity !== null && ($identityCounts[$rawIdentity] ?? 0) > 1) ? 'duplicate' : 'malformed',
                );

                continue;
            }

            $family = (string) $template['family'];
            $key = (string) $template['key'];
            $protected = $this->accessPolicy->isProtected($template);
            $reference = $references->for($rawIdentity);
            $isDefault = $this->isDefaultIdentity($family, $key);
            $configuredIdentities[$rawIdentity] = true;

            $records[] = [
                'record_key' => "configured:{$rawIdentity}",
                'kind' => 'configured',
                'identity' => $rawIdentity,
                'family' => $family,
                'key' => $key,
                'label' => (string) ($template['label'] ?? $key),
                'family_label' => PublicFrontCardTemplateRegistry::familyOptions()[$family] ?? $family,
                'layout' => (string) ($template['layout'] ?? 'cards'),
                'layout_label' => __('admin.layouts.'.($template['layout'] ?? 'cards')),
                'parts_status' => ($protected && ! $capable)
                    ? __('admin.settings_sp3c.library.restricted')
                    : trans_choice('admin.settings_sp3c.library.parts_count', count($template['parts']), [
                        'count' => count($template['parts']),
                    ]),
                'where_used' => $this->whereUsedLabel($references, $rawIdentity),
                'explicit_references' => count($reference['settings']) + count($reference['sections']),
                'implicit_references' => $reference['implicit'],
                'default_override' => $isDefault,
                'protected' => $protected,
                'can_edit' => true,
                'can_clone' => ! $protected || $capable,
            ];
        }

        foreach (PublicFrontCardTemplateRegistry::defaultTemplates() as $default) {
            $family = (string) $default['family'];
            $key = (string) $default['key'];
            $defaultIdentity = $this->identity->make($family, $key);

            if (isset($configuredIdentities[$defaultIdentity])) {
                continue;
            }

            $reference = $references->for($defaultIdentity);
            $records[] = [
                'record_key' => "virtual:{$defaultIdentity}",
                'kind' => 'virtual',
                'identity' => $defaultIdentity,
                'family' => $family,
                'key' => $key,
                'label' => __('admin.settings_sp3c.library.virtual_default', [
                    'label' => (string) ($default['label'] ?? $key),
                ]),
                'family_label' => PublicFrontCardTemplateRegistry::familyOptions()[$family] ?? $family,
                'layout' => (string) ($default['layout'] ?? 'cards'),
                'layout_label' => __('admin.layouts.'.($default['layout'] ?? 'cards')),
                'parts_status' => __('admin.settings_sp3c.library.virtual_parts'),
                'where_used' => $this->whereUsedLabel($references, $defaultIdentity),
                'explicit_references' => count($reference['settings']) + count($reference['sections']),
                'implicit_references' => $reference['implicit'],
                'default_override' => false,
                'protected' => $this->accessPolicy->isProtected($default),
                'can_edit' => false,
                'can_clone' => false,
            ];
        }

        return new CardTemplateLibraryProjection($records, $references);
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function validRow(array $template): bool
    {
        try {
            $result = $this->validator->validateGroups([
                'card_templates' => [$template],
            ], ['card_templates']);

            return ! $result->hasInvalidConfig()
                && count($result->group('card_templates')) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    private function isDefaultIdentity(string $family, string $key): bool
    {
        return (PublicFrontCardTemplateRegistry::defaultTemplateKeys()[$family] ?? null) === $key;
    }

    private function whereUsedLabel(CardTemplateReferences $references, string $identity): string
    {
        $reference = $references->for($identity);
        $explicit = count($reference['settings']) + count($reference['sections']);

        return __('admin.settings_sp3c.library.where_used', [
            'explicit' => $explicit,
            'implicit' => $reference['implicit'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnosticRecord(int $index, mixed $value, string $reason): array
    {
        try {
            $hash = $this->identity->fingerprint($value);
        } catch (Throwable) {
            $hash = hash('sha256', get_debug_type($value).":{$index}");
        }

        return [
            'record_key' => "corrupt:{$index}:{$hash}",
            'kind' => 'diagnostic',
            'identity' => __('admin.settings_sp3c.library.corrupt_identity', ['index' => $index]),
            'family' => null,
            'key' => null,
            'label' => __('admin.settings_sp3c.library.corrupt_label'),
            'family_label' => __('admin.settings_sp3c.library.diagnostic'),
            'layout' => null,
            'layout_label' => '—',
            'parts_status' => __('admin.settings_sp3c.library.restricted'),
            'where_used' => __('admin.settings_sp3c.library.recovery'),
            'explicit_references' => 0,
            'implicit_references' => 0,
            'default_override' => false,
            'protected' => true,
            'can_edit' => false,
            'can_clone' => false,
            'diagnostic_reason' => $reason,
        ];
    }
}
