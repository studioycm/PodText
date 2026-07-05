<?php

namespace App\Support\PublicFront\Menu;

use App\Enums\PublicMenuItemType;
use App\Support\PublicFront\PublicFrontConfigReader;

class PublicMenuConfigReader
{
    public function __construct(
        private readonly PublicFrontConfigReader $configReader,
        private readonly PublicRouteRegistry $routeRegistry,
        private readonly PublicUrlSanitizer $urlSanitizer,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     items: array<int, array<string, mixed>>,
     *     form_mounts: array<int, array{form_key: string, display_mode: string}>,
     *     theme_selector: array<string, mixed>,
     * }
     */
    public function read(): array
    {
        $result = $this->configReader->read();
        $menuConfig = $result->group('menu_config');
        $routeLabels = $result->group('route_labels');
        $publicForms = $result->group('public_forms');
        $enabledForms = $this->enabledForms($publicForms['definitions'] ?? []);

        if (($menuConfig['enabled'] ?? false) !== true) {
            return [
                'enabled' => false,
                'items' => [],
                'form_mounts' => [],
                'theme_selector' => [
                    'enabled' => false,
                    'mode' => 'light_dark_system',
                ],
            ];
        }

        $items = collect($menuConfig['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['visible'] ?? true) === true)
            ->map(fn (array $item): ?array => $this->resolveItem($item, $routeLabels, $enabledForms))
            ->filter()
            ->values()
            ->all();

        $formMounts = collect($items)
            ->filter(fn (array $item): bool => ($item['type'] ?? null) === PublicMenuItemType::PublicForm->value)
            ->map(fn (array $item): array => [
                'form_key' => (string) $item['form_key'],
                'display_mode' => (string) ($item['display_mode'] ?? 'modal'),
            ])
            ->unique('form_key')
            ->values()
            ->all();

        return [
            'enabled' => true,
            'items' => $items,
            'form_mounts' => $formMounts,
            'theme_selector' => $menuConfig['theme_selector'] ?? [
                'enabled' => true,
                'mode' => 'light_dark_system',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, array<string, string>>  $routeLabels
     * @param  array<string, array<string, mixed>>  $enabledForms
     * @return array<string, mixed>|null
     */
    private function resolveItem(array $item, array $routeLabels, array $enabledForms): ?array
    {
        $type = $item['type'] ?? null;

        if ($type === PublicMenuItemType::Route->value) {
            $routeKey = $item['route_key'] ?? null;
            $url = is_string($routeKey) ? $this->routeRegistry->url($routeKey) : null;

            if ($url === null || ! is_string($routeKey)) {
                return null;
            }

            return [
                'key' => $item['key'] ?? $routeKey,
                'type' => PublicMenuItemType::Route->value,
                'label' => $item['label'] ?? $this->routeRegistry->label($routeKey, $routeLabels),
                'url' => $url,
                'route_key' => $routeKey,
            ];
        }

        if ($type === PublicMenuItemType::ExternalUrl->value) {
            $url = $this->urlSanitizer->https($item['external_url'] ?? null);

            if ($url === null) {
                return null;
            }

            return [
                'key' => $item['key'] ?? md5($url),
                'type' => PublicMenuItemType::ExternalUrl->value,
                'label' => $item['label'] ?? $url,
                'url' => $url,
                'open_in_new_tab' => ($item['open_in_new_tab'] ?? false) === true,
            ];
        }

        if ($type === PublicMenuItemType::PublicForm->value) {
            $formKey = $item['form_key'] ?? null;

            if (! is_string($formKey) || ! isset($enabledForms[$formKey])) {
                return null;
            }

            return [
                'key' => $item['key'] ?? $formKey,
                'type' => PublicMenuItemType::PublicForm->value,
                'label' => $item['label'] ?? ($enabledForms[$formKey]['name'] ?? $formKey),
                'form_key' => $formKey,
                'display_mode' => $item['display_mode'] ?? $enabledForms[$formKey]['display_mode_default'] ?? 'modal',
            ];
        }

        if ($type === PublicMenuItemType::ThemeSelector->value) {
            return [
                'key' => $item['key'] ?? 'theme_selector',
                'type' => PublicMenuItemType::ThemeSelector->value,
                'label' => $item['label'] ?? __('public.menu.theme'),
            ];
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $definitions
     * @return array<string, array<string, mixed>>
     */
    private function enabledForms(array $definitions): array
    {
        return collect($definitions)
            ->filter(fn (mixed $definition): bool => is_array($definition))
            ->filter(fn (array $definition): bool => ($definition['enabled'] ?? false) === true && filled($definition['key'] ?? null))
            ->mapWithKeys(fn (array $definition): array => [(string) $definition['key'] => $definition])
            ->all();
    }
}
