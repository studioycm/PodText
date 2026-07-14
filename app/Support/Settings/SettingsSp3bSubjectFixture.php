<?php

namespace App\Support\Settings;

use App\Filament\Pages\SettingsSubjectOwnershipRegistry;
use App\Support\PublicFront\PublicFrontConfigRegistry;

final class SettingsSp3bSubjectFixture
{
    /**
     * @return array<string, mixed>
     */
    public function payload(string $subject, ?string $fixture): array
    {
        if ($fixture !== $subject || ! in_array($subject, [
            SettingsSubjectOwnershipRegistry::EPISODE_PAGE,
            SettingsSubjectOwnershipRegistry::MENU_HEADER,
            SettingsSubjectOwnershipRegistry::ABOUT,
            SettingsSubjectOwnershipRegistry::PUBLIC_FORMS,
        ], true)) {
            return [];
        }

        $defaults = PublicFrontConfigRegistry::defaults();

        return match ($subject) {
            SettingsSubjectOwnershipRegistry::EPISODE_PAGE => [
                'item_page' => [
                    ...$defaults['item_page'],
                    'info_fields' => array_fill(0, 24, [
                        'field' => 'categories',
                        'label_mode' => 'long',
                        'icon' => 'OutlinedFolder',
                        'icon_position' => 'inline_before',
                        'size' => 'sm',
                        'color' => 'info',
                    ]),
                ],
            ],
            SettingsSubjectOwnershipRegistry::MENU_HEADER => [
                'menu_config' => [
                    ...$defaults['menu_config'],
                    'items' => collect(range(1, 24))
                        ->map(fn (int $position): array => [
                            'key' => "sp3b-menu-{$position}",
                            'type' => 'route',
                            'route_key' => 'home',
                            'label' => "SP3B menu {$position}",
                            'visible' => true,
                            'sort' => $position * 10,
                        ])
                        ->all(),
                ],
                'route_labels' => collect(range(1, 24))
                    ->map(fn (int $position): array => [
                        'route_key' => $position % 2 === 0 ? 'home' : 'search',
                        'label' => "SP3B route label {$position}",
                    ])
                    ->all(),
            ],
            SettingsSubjectOwnershipRegistry::ABOUT => [
                'about_page' => [
                    ...$defaults['about_page'],
                    'blocks' => collect(range(1, 18))
                        ->map(fn (int $position): array => [
                            'type' => 'markdown',
                            'data' => [
                                'key' => "sp3b-block-{$position}",
                                'visible' => true,
                                'sort' => $position,
                                'heading' => "SP3B block {$position}",
                                'content' => str_repeat("SP3B about fixture {$position}. ", 12),
                            ],
                        ])
                        ->all(),
                    'team_profiles' => collect(range(1, 18))
                        ->map(fn (int $position): array => [
                            'key' => "sp3b-team-{$position}",
                            'name' => "SP3B team {$position}",
                            'role' => 'Contributor',
                            'visible' => true,
                            'sort' => $position,
                        ])
                        ->all(),
                ],
            ],
            SettingsSubjectOwnershipRegistry::PUBLIC_FORMS => [
                'public_forms' => [
                    ...$defaults['public_forms'],
                    'definitions' => collect(range(1, 12))
                        ->map(fn (int $position): array => [
                            'key' => "sp3b-form-{$position}",
                            'name' => "SP3B form {$position}",
                            'heading' => "SP3B form {$position}",
                            'display_mode_default' => 'modal',
                            'enabled' => true,
                            'settings' => [
                                'rate_limit_attempts' => 5,
                                'rate_limit_decay_seconds' => 600,
                                'submitter_email_verification' => 'off',
                            ],
                            'fields' => collect(range(1, 6))
                                ->map(fn (int $field): array => [
                                    'type' => 'text',
                                    'data' => [
                                        'key' => "field-{$position}-{$field}",
                                        'label' => "SP3B field {$position}-{$field}",
                                        'required' => false,
                                    ],
                                ])
                                ->all(),
                        ])
                        ->all(),
                ],
            ],
            default => [],
        };
    }
}
