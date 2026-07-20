<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('public_content.menu_config', function (mixed $menuConfig): array {
            $menuConfig = is_object($menuConfig) ? (array) $menuConfig : $menuConfig;
            $menuConfig = is_array($menuConfig) ? $menuConfig : [];
            $logo = $menuConfig['logo'] ?? [];
            $logo = is_object($logo) ? (array) $logo : $logo;
            $logo = is_array($logo) ? $logo : [];
            $logo['light_media_reference_key'] ??= null;
            $logo['dark_media_reference_key'] ??= null;
            $menuConfig['logo'] = $logo;

            return $menuConfig;
        });

        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;
            $aboutPage = is_array($aboutPage) ? $aboutPage : [];
            $aboutPage['blocks'] = collect($aboutPage['blocks'] ?? [])
                ->map(function (mixed $block): mixed {
                    $block = is_object($block) ? (array) $block : $block;

                    if (! is_array($block)) {
                        return $block;
                    }

                    if (($block['type'] ?? data_get($block, 'data.type')) !== 'image') {
                        return $block;
                    }

                    $data = $block['data'] ?? null;
                    $data = is_object($data) ? (array) $data : $data;

                    if (is_array($data)) {
                        $block['data'] = $data;
                        $block['data']['image_media_reference_key'] ??= null;
                    } else {
                        $block['image_media_reference_key'] ??= null;
                    }

                    return $block;
                })
                ->all();
            $aboutPage['team_profiles'] = collect($aboutPage['team_profiles'] ?? [])
                ->map(function (mixed $profile): mixed {
                    $profile = is_object($profile) ? (array) $profile : $profile;

                    if (! is_array($profile)) {
                        return $profile;
                    }

                    $profile['image_media_reference_key'] ??= null;

                    return $profile;
                })
                ->all();

            return $aboutPage;
        });

        $this->migrator->update('public_content.default_images', function (mixed $defaultImages): array {
            $defaultImages = is_object($defaultImages) ? (array) $defaultImages : $defaultImages;
            $defaultImages = is_array($defaultImages) ? $defaultImages : [];

            return collect($defaultImages)
                ->map(function (mixed $family): mixed {
                    $family = is_object($family) ? (array) $family : $family;

                    if (! is_array($family)) {
                        return $family;
                    }

                    $family['media_reference_key'] ??= null;

                    return $family;
                })
                ->all();
        });
    }

    public function down(): void
    {
        $this->migrator->update('public_content.menu_config', function (mixed $menuConfig): array {
            $menuConfig = is_object($menuConfig) ? (array) $menuConfig : $menuConfig;
            $menuConfig = is_array($menuConfig) ? $menuConfig : [];

            $logo = $menuConfig['logo'] ?? null;
            $logo = is_object($logo) ? (array) $logo : $logo;

            if (is_array($logo)) {
                $menuConfig['logo'] = $logo;
                unset($menuConfig['logo']['light_media_reference_key'], $menuConfig['logo']['dark_media_reference_key']);
            }

            return $menuConfig;
        });

        $this->migrator->update('public_content.about_page', function (mixed $aboutPage): array {
            $aboutPage = is_object($aboutPage) ? (array) $aboutPage : $aboutPage;
            $aboutPage = is_array($aboutPage) ? $aboutPage : [];
            $aboutPage['blocks'] = collect($aboutPage['blocks'] ?? [])
                ->map(function (mixed $block): mixed {
                    $block = is_object($block) ? (array) $block : $block;

                    if (is_array($block) && ($block['type'] ?? data_get($block, 'data.type')) === 'image') {
                        $data = $block['data'] ?? null;
                        $data = is_object($data) ? (array) $data : $data;

                        if (is_array($data)) {
                            $block['data'] = $data;
                            unset($block['data']['image_media_reference_key']);
                        } else {
                            unset($block['image_media_reference_key']);
                        }
                    }

                    return $block;
                })
                ->all();
            $aboutPage['team_profiles'] = collect($aboutPage['team_profiles'] ?? [])
                ->map(function (mixed $profile): mixed {
                    $profile = is_object($profile) ? (array) $profile : $profile;

                    if (is_array($profile)) {
                        unset($profile['image_media_reference_key']);
                    }

                    return $profile;
                })
                ->all();

            return $aboutPage;
        });

        $this->migrator->update('public_content.default_images', function (mixed $defaultImages): array {
            $defaultImages = is_object($defaultImages) ? (array) $defaultImages : $defaultImages;
            $defaultImages = is_array($defaultImages) ? $defaultImages : [];

            return collect($defaultImages)
                ->map(function (mixed $family): mixed {
                    $family = is_object($family) ? (array) $family : $family;

                    if (is_array($family)) {
                        unset($family['media_reference_key']);
                    }

                    return $family;
                })
                ->all();
        });
    }
};
