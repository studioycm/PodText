<?php

namespace App\Support\PublicFront\Menu;

use App\Enums\PublicMenuItemType;
use App\Support\PublicFront\PublicFormTargetStatus;

/**
 * Admin-side mirror of the PublicMenuConfigReader::resolveItem() skip rules,
 * computed from raw admin form state so editors can see which items the
 * public menu would drop. Keep in sync with resolveItem() when a menu item
 * type gains or changes target requirements.
 */
class PublicMenuItemTargetHealth
{
    public function __construct(
        private readonly PublicRouteRegistry $routeRegistry,
        private readonly PublicUrlSanitizer $urlSanitizer,
        private readonly PublicFormTargetStatus $formTargets,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public function hasUsableTarget(array $item): bool
    {
        $type = $item['type'] ?? null;

        if ($type === PublicMenuItemType::Route->value) {
            $routeKey = $item['route_key'] ?? null;

            return is_string($routeKey) && $this->routeRegistry->url($routeKey) !== null;
        }

        if ($type === PublicMenuItemType::ExternalUrl->value) {
            $url = $item['external_url'] ?? null;

            return $this->urlSanitizer->https(is_string($url) ? $url : null) !== null;
        }

        if ($type === PublicMenuItemType::PublicForm->value) {
            $formKey = $item['form_key'] ?? null;

            return $this->formTargets->hasEnabledDefinition(is_string($formKey) ? $formKey : null);
        }

        return $type === PublicMenuItemType::ThemeSelector->value;
    }
}
