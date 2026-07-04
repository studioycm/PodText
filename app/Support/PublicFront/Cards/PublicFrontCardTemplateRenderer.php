<?php

namespace App\Support\PublicFront\Cards;

class PublicFrontCardTemplateRenderer
{
    public function __construct(
        private readonly PublicFrontCardTemplateResolver $resolver,
    ) {}

    public function resolve(string $family, ?string $key = null, array $overrides = []): PublicFrontCardTemplate
    {
        return $this->resolver->resolve($family, $key, $overrides);
    }

    /**
     * @return array<string, string>
     */
    public function compatibilityAttributes(PublicFrontCardTemplate|string $template, ?string $key = null): array
    {
        if (is_string($template)) {
            $template = $this->resolve($template, $key);
        }

        return [
            'data-card-template-family' => $template->family,
            'data-card-template-key' => $template->key,
            'data-card-template-layout' => $template->layout,
            'data-card-template-parts' => implode(',', $template->partTypes(visibleOnly: true)),
        ];
    }
}
