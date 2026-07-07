<?php

namespace App\Support\PublicFront\Cards;

use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;

class PublicFrontCardTemplateResolver
{
    public function __construct(
        private readonly PublicFrontRenderContext $context,
    ) {}

    public function resolve(string $family, ?string $key = null, array $overrides = []): PublicFrontCardTemplate
    {
        return $this->resolveFromTemplates(
            templates: $this->context->cardTemplates(),
            family: $family,
            key: $key,
            overrides: $overrides,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     */
    public function resolveFromTemplates(array $templates, string $family, ?string $key = null, array $overrides = []): PublicFrontCardTemplate
    {
        $family = in_array($family, PublicFrontCardTemplateRegistry::families(), true)
            ? $family
            : PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY;

        $defaultKey = PublicFrontCardTemplateRegistry::defaultTemplateKeys()[$family];
        $selectedKey = $key ?: $defaultKey;
        $templatesByFamily = $this->templatesByFamily($templates);
        $template = $templatesByFamily[$family][$selectedKey]
            ?? $templatesByFamily[$family][$defaultKey]
            ?? PublicFrontCardTemplateRegistry::defaultTemplateForFamily($family);

        return PublicFrontCardTemplate::fromArray($this->mergeOverrides($template, $overrides));
    }

    /**
     * @return array<int, PublicFrontCardTemplate>
     */
    public function all(?string $family = null): array
    {
        $templates = $this->templatesByFamily($this->context->cardTemplates());

        if ($family !== null) {
            return collect($templates[$family] ?? [])
                ->map(fn (array $template): PublicFrontCardTemplate => PublicFrontCardTemplate::fromArray($template))
                ->values()
                ->all();
        }

        return collect($templates)
            ->flatten(1)
            ->map(fn (array $template): PublicFrontCardTemplate => PublicFrontCardTemplate::fromArray($template))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $configuredTemplates
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function templatesByFamily(array $configuredTemplates): array
    {
        $templates = [];

        foreach (PublicFrontCardTemplateRegistry::defaultTemplates() as $template) {
            $templates[$template['family']][$template['key']] = $template;
        }

        foreach ($configuredTemplates as $template) {
            $family = $template['family'] ?? null;
            $key = $template['key'] ?? null;

            if (! is_string($family) || ! is_string($key)) {
                continue;
            }

            if (! in_array($family, PublicFrontCardTemplateRegistry::families(), true)) {
                continue;
            }

            $templates[$family][$key] = $template;
        }

        return $templates;
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergeOverrides(array $template, array $overrides): array
    {
        foreach (['layout', 'density', 'image_size', 'title_size'] as $key) {
            if (! isset($overrides[$key]) || ! is_string($overrides[$key])) {
                continue;
            }

            $allowed = match ($key) {
                'layout' => PublicFrontConfigRegistry::layouts(),
                'density' => PublicFrontConfigRegistry::densities(),
                'image_size' => PublicFrontConfigRegistry::imageSizes(),
                'title_size' => PublicFrontConfigRegistry::titleSizes(),
            };

            if (in_array($overrides[$key], $allowed, true)) {
                $template[$key] = $overrides[$key];
            }
        }

        return $template;
    }
}
