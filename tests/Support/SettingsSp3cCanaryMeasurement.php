<?php

namespace Tests\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Livewire\Features\SupportTesting\Testable;

final class SettingsSp3cCanaryMeasurement
{
    public const FIELD_WRAPPER_SELECTOR = '[data-field-wrapper]';

    /**
     * @return array{
     *     elements: int,
     *     field_wrappers: int,
     *     editor_controls: int,
     *     control_ids: int,
     *     wire_models: int,
     *     summary_chrome: int,
     *     action_chrome: int,
     *     html_bytes: int,
     *     serialized_state_bytes: int
     * }
     */
    public function measure(Testable $component): array
    {
        $html = $component->html();
        $instance = $component->instance();
        $state = method_exists($instance, 'sp3cMeasurementState')
            ? $instance->sp3cMeasurementState()
            : [
                'form' => $instance->form->getRawState(),
                'mounted_actions' => $instance->mountedActions,
            ];

        return $this->measureHtml($html, $state);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{
     *     elements: int,
     *     field_wrappers: int,
     *     editor_controls: int,
     *     control_ids: int,
     *     wire_models: int,
     *     summary_chrome: int,
     *     action_chrome: int,
     *     html_bytes: int,
     *     serialized_state_bytes: int
     * }
     */
    public function measureHtml(string $html, array $state): array
    {
        [$document, $xpath] = $this->document($html);
        $wrappers = $xpath->query('//*[@data-field-wrapper]');
        $controls = [];
        $controlIds = [];
        $wireModels = [];

        foreach ($wrappers as $wrapper) {
            if (! $wrapper instanceof DOMElement) {
                continue;
            }

            $wrapperControls = $xpath->query(
                './/input | .//select | .//textarea | .//*[@contenteditable="true"] | .//*[@*[name()="wire:model"] or @*[name()="wire:model.live"] or @*[name()="wire:model.blur"] or @*[name()="wire:model.defer"]]',
                $wrapper,
            );

            foreach ($wrapperControls as $control) {
                if (! $control instanceof DOMElement) {
                    continue;
                }

                $controls[$this->nodeKey($control)] = true;

                if ($control->hasAttribute('id')) {
                    $controlIds[$control->getAttribute('id')] = true;
                }

                foreach (['wire:model', 'wire:model.live', 'wire:model.blur', 'wire:model.defer'] as $attribute) {
                    if ($control->hasAttribute($attribute)) {
                        $wireModels[$control->getAttribute($attribute)] = true;
                    }
                }
            }
        }

        $serialized = json_encode(
            $state,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return [
            'elements' => $document->getElementsByTagName('*')->count(),
            'field_wrappers' => $wrappers->count(),
            'editor_controls' => count($controls),
            'control_ids' => count($controlIds),
            'wire_models' => count($wireModels),
            'summary_chrome' => $xpath->query('//*[@data-sp3c-canary-summary]')->count(),
            'action_chrome' => $xpath->query($this->classQuery('fi-fo-builder-item-preview-edit-overlay'))->count(),
            'html_bytes' => strlen($html),
            'serialized_state_bytes' => strlen($serialized),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function wireModelPaths(string $html): array
    {
        [, $xpath] = $this->document($html);
        $paths = [];

        foreach ($xpath->query('//*[@*[starts-with(name(), "wire:model")]]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            foreach ($node->attributes as $attribute) {
                if (str_starts_with($attribute->name, 'wire:model')) {
                    $paths[] = $attribute->value;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array{DOMDocument, DOMXPath}
     */
    private function document(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [$document, new DOMXPath($document)];
    }

    private function classQuery(string $class): string
    {
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
    }

    private function nodeKey(DOMNode $node): string
    {
        return $node->getNodePath();
    }
}
