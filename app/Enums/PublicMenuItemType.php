<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PublicMenuItemType: string implements HasLabel
{
    case Route = 'route';
    case ExternalUrl = 'external_url';
    case PublicForm = 'public_form';
    case ThemeSelector = 'theme_selector';

    public function getLabel(): string
    {
        return match ($this) {
            self::Route => __('admin.public_menu_item_types.route'),
            self::ExternalUrl => __('admin.public_menu_item_types.external_url'),
            self::PublicForm => __('admin.public_menu_item_types.public_form'),
            self::ThemeSelector => __('admin.public_menu_item_types.theme_selector'),
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->getLabel()])
            ->all();
    }
}
