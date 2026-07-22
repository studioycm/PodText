<?php

namespace App\Support\SettingsLifecycle;

use App\Enums\ImageUploadPurpose;
use App\Models\Media;
use App\Support\Media\MediaIdentityResolver;
use App\Support\PublicFront\PublicFrontConfigRegistry;

class SettingsMediaIdentityProjector
{
    public function __construct(
        private readonly MediaIdentityResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function portable(array $payload): array
    {
        return $this->reconcilePayload($payload, removeLegacyPaths: true, requireRegisteredMedia: true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function localize(array $payload, bool $portable = false): array
    {
        return $this->reconcilePayload(
            $payload,
            requireRegisteredMedia: $portable,
            requireReferenceKey: $portable,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function backfill(array $payload): array
    {
        return $this->reconcilePayload($payload, requireRegisteredMedia: true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $referenceKeysByPath
     * @param  array<int, string>  $knownReferenceKeys
     * @return array{
     *     payload: array<string, mixed>,
     *     filled: array<int, string>,
     *     unresolved: array<int, string>
     * }
     */
    public function backfillFromUniquePaths(
        array $payload,
        array $referenceKeysByPath,
        array $knownReferenceKeys,
    ): array {
        $filled = [];
        $unresolved = [];
        $knownReferenceKeyLookup = array_fill_keys($knownReferenceKeys, true);
        $logo = data_get($payload, 'menu_config.logo');

        if (is_array($logo)) {
            [$payload['menu_config']['logo'], $outcome] = $this->fillUniquePathPair(
                $logo,
                'light_media_reference_key',
                'light_path',
                $referenceKeysByPath,
                $knownReferenceKeyLookup,
            );
            $this->recordUniquePathOutcome($outcome, 'menu_config.logo.light', $filled, $unresolved);
            [$payload['menu_config']['logo'], $outcome] = $this->fillUniquePathPair(
                $payload['menu_config']['logo'],
                'dark_media_reference_key',
                'dark_path',
                $referenceKeysByPath,
                $knownReferenceKeyLookup,
            );
            $this->recordUniquePathOutcome($outcome, 'menu_config.logo.dark', $filled, $unresolved);
        }

        foreach (PublicFrontConfigRegistry::defaultImageFamilies() as $family) {
            $state = data_get($payload, "default_images.{$family}");

            if (! is_array($state)) {
                continue;
            }

            [$payload['default_images'][$family], $outcome] = $this->fillUniquePathPair(
                $state,
                'media_reference_key',
                'path',
                $referenceKeysByPath,
                $knownReferenceKeyLookup,
            );
            $this->recordUniquePathOutcome($outcome, "default_images.{$family}", $filled, $unresolved);
        }

        if (is_array(data_get($payload, 'about_page.blocks'))) {
            foreach ($payload['about_page']['blocks'] as $index => $block) {
                if (! is_array($block) || ($block['type'] ?? data_get($block, 'data.type')) !== 'image') {
                    continue;
                }

                $outerHasIdentity = array_key_exists('image_media_reference_key', $block)
                    || array_key_exists('image_path', $block);

                if (is_array($block['data'] ?? null) && $outerHasIdentity) {
                    throw new \InvalidArgumentException('The about-page image block has ambiguous media identity fields.');
                }

                if (is_array($block['data'] ?? null)) {
                    [$block['data'], $outcome] = $this->fillUniquePathPair(
                        $block['data'],
                        'image_media_reference_key',
                        'image_path',
                        $referenceKeysByPath,
                        $knownReferenceKeyLookup,
                    );
                } else {
                    [$block, $outcome] = $this->fillUniquePathPair(
                        $block,
                        'image_media_reference_key',
                        'image_path',
                        $referenceKeysByPath,
                        $knownReferenceKeyLookup,
                    );
                }

                $payload['about_page']['blocks'][$index] = $block;
                $this->recordUniquePathOutcome($outcome, "about_page.blocks.{$index}", $filled, $unresolved);
            }
        }

        if (is_array(data_get($payload, 'about_page.team_profiles'))) {
            foreach ($payload['about_page']['team_profiles'] as $index => $profile) {
                if (! is_array($profile)) {
                    continue;
                }

                [$payload['about_page']['team_profiles'][$index], $outcome] = $this->fillUniquePathPair(
                    $profile,
                    'image_media_reference_key',
                    'image_path',
                    $referenceKeysByPath,
                    $knownReferenceKeyLookup,
                );
                $this->recordUniquePathOutcome($outcome, "about_page.team_profiles.{$index}", $filled, $unresolved);
            }
        }

        return [
            'payload' => $payload,
            'filled' => $filled,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function legacyRegistrationLocations(
        array $payload,
        string $legacyPath,
        ImageUploadPurpose $purpose,
    ): array {
        return $this->projectLegacyRegistration($payload, $legacyPath, $purpose)['locations'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, locations: array<int, string>}
     */
    public function registerLegacyPath(
        array $payload,
        string $legacyPath,
        ImageUploadPurpose $purpose,
        Media $media,
    ): array {
        return $this->projectLegacyRegistration($payload, $legacyPath, $purpose, $media);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public function expandSelectedPaths(array $paths): array
    {
        $pairs = [
            'menu_config.logo.light_media_reference_key' => 'menu_config.logo.light_path',
            'menu_config.logo.dark_media_reference_key' => 'menu_config.logo.dark_path',
            'default_images.global.media_reference_key' => 'default_images.global.path',
            'default_images.content_item.media_reference_key' => 'default_images.content_item.path',
            'default_images.content_group.media_reference_key' => 'default_images.content_group.path',
            'default_images.contributor.media_reference_key' => 'default_images.contributor.path',
        ];

        foreach ($pairs as $referencePath => $legacyPath) {
            if (in_array($referencePath, $paths, true)) {
                $paths[] = $legacyPath;
            }

            if (in_array($legacyPath, $paths, true)) {
                $paths[] = $referencePath;
            }
        }

        foreach ($paths as $path) {
            $legacyPath = match (true) {
                str_ends_with($path, '.light_media_reference_key') => str_replace(
                    '.light_media_reference_key',
                    '.light_path',
                    $path,
                ),
                str_ends_with($path, '.dark_media_reference_key') => str_replace(
                    '.dark_media_reference_key',
                    '.dark_path',
                    $path,
                ),
                str_ends_with($path, '.image_media_reference_key') => str_replace(
                    '.image_media_reference_key',
                    '.image_path',
                    $path,
                ),
                str_ends_with($path, '.media_reference_key') => str_replace(
                    '.media_reference_key',
                    '.path',
                    $path,
                ),
                default => null,
            };

            if (is_string($legacyPath)) {
                $paths[] = $legacyPath;
            }

            $referencePath = match (true) {
                str_ends_with($path, '.light_path') => str_replace(
                    '.light_path',
                    '.light_media_reference_key',
                    $path,
                ),
                str_ends_with($path, '.dark_path') => str_replace(
                    '.dark_path',
                    '.dark_media_reference_key',
                    $path,
                ),
                str_ends_with($path, '.image_path') => str_replace(
                    '.image_path',
                    '.image_media_reference_key',
                    $path,
                ),
                str_ends_with($path, '.path') => str_replace(
                    '.path',
                    '.media_reference_key',
                    $path,
                ),
                default => null,
            };

            if (is_string($referencePath)) {
                $paths[] = $referencePath;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function reconcilePayload(
        array $payload,
        bool $removeLegacyPaths = false,
        bool $requireRegisteredMedia = false,
        bool $requireReferenceKey = false,
    ): array {
        $logo = data_get($payload, 'menu_config.logo');

        if (is_array($logo)) {
            $payload['menu_config']['logo'] = $this->reconcilePair(
                $logo,
                'light_media_reference_key',
                'light_path',
                ImageUploadPurpose::HeaderLogo,
                $removeLegacyPaths,
                $requireRegisteredMedia,
                $requireReferenceKey,
            );
            $payload['menu_config']['logo'] = $this->reconcilePair(
                $payload['menu_config']['logo'],
                'dark_media_reference_key',
                'dark_path',
                ImageUploadPurpose::HeaderLogo,
                $removeLegacyPaths,
                $requireRegisteredMedia,
                $requireReferenceKey,
            );
        }

        foreach (PublicFrontConfigRegistry::defaultImageFamilies() as $family) {
            if (! is_array(data_get($payload, "default_images.{$family}"))) {
                continue;
            }

            $payload['default_images'][$family] = $this->reconcilePair(
                $payload['default_images'][$family],
                'media_reference_key',
                'path',
                ImageUploadPurpose::DefaultImage,
                $removeLegacyPaths,
                $requireRegisteredMedia,
                $requireReferenceKey,
            );
        }

        if (is_array(data_get($payload, 'about_page.blocks'))) {
            $payload['about_page']['blocks'] = collect($payload['about_page']['blocks'])
                ->map(function (mixed $block) use ($removeLegacyPaths, $requireRegisteredMedia, $requireReferenceKey): mixed {
                    if (! is_array($block)) {
                        return $block;
                    }

                    if (($block['type'] ?? data_get($block, 'data.type')) !== 'image') {
                        return $block;
                    }

                    $outerHasMediaIdentity = array_key_exists('image_media_reference_key', $block)
                        || array_key_exists('image_path', $block);
                    if (is_array($block['data'] ?? null) && $outerHasMediaIdentity) {
                        throw new \InvalidArgumentException('The about-page image block has ambiguous media identity fields.');
                    }

                    if (is_array($block['data'] ?? null)) {
                        $block['data'] = $this->reconcilePair(
                            $block['data'],
                            'image_media_reference_key',
                            'image_path',
                            ImageUploadPurpose::AboutImage,
                            $removeLegacyPaths,
                            $requireRegisteredMedia,
                            $requireReferenceKey,
                        );

                        return $block;
                    }

                    return $this->reconcilePair(
                        $block,
                        'image_media_reference_key',
                        'image_path',
                        ImageUploadPurpose::AboutImage,
                        $removeLegacyPaths,
                        $requireRegisteredMedia,
                        $requireReferenceKey,
                    );
                })
                ->all();
        }

        if (is_array(data_get($payload, 'about_page.team_profiles'))) {
            $payload['about_page']['team_profiles'] = collect($payload['about_page']['team_profiles'])
                ->map(fn (mixed $profile): mixed => is_array($profile)
                    ? $this->reconcilePair(
                        $profile,
                        'image_media_reference_key',
                        'image_path',
                        ImageUploadPurpose::TeamImage,
                        $removeLegacyPaths,
                        $requireRegisteredMedia,
                        $requireReferenceKey,
                    )
                    : $profile)
                ->all();
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, locations: array<int, string>}
     */
    private function projectLegacyRegistration(
        array $payload,
        string $legacyPath,
        ImageUploadPurpose $purpose,
        ?Media $media = null,
    ): array {
        $locations = [];

        if (is_array(data_get($payload, 'menu_config.logo'))) {
            foreach ([
                'light' => ['light_media_reference_key', 'light_path'],
                'dark' => ['dark_media_reference_key', 'dark_path'],
            ] as $name => [$referenceField, $pathField]) {
                [$payload['menu_config']['logo'], $matched] = $this->projectRegistrationPair(
                    $payload['menu_config']['logo'],
                    $referenceField,
                    $pathField,
                    ImageUploadPurpose::HeaderLogo,
                    $purpose,
                    $legacyPath,
                    $media,
                );

                if ($matched) {
                    $locations[] = "menu_config.logo.{$name}";
                }
            }
        }

        foreach (PublicFrontConfigRegistry::defaultImageFamilies() as $family) {
            $config = data_get($payload, "default_images.{$family}");

            if (! is_array($config)) {
                continue;
            }

            [$payload['default_images'][$family], $matched] = $this->projectRegistrationPair(
                $config,
                'media_reference_key',
                'path',
                ImageUploadPurpose::DefaultImage,
                $purpose,
                $legacyPath,
                $media,
            );

            if ($matched) {
                $locations[] = "default_images.{$family}";
            }
        }

        if (is_array(data_get($payload, 'about_page.blocks'))) {
            foreach ($payload['about_page']['blocks'] as $index => $block) {
                if (! is_array($block) || ($block['type'] ?? data_get($block, 'data.type')) !== 'image') {
                    continue;
                }

                if (is_array($block['data'] ?? null)) {
                    if (array_key_exists('image_media_reference_key', $block) || array_key_exists('image_path', $block)) {
                        throw new \InvalidArgumentException('The about-page image block has ambiguous media identity fields.');
                    }

                    [$block['data'], $matched] = $this->projectRegistrationPair(
                        $block['data'],
                        'image_media_reference_key',
                        'image_path',
                        ImageUploadPurpose::AboutImage,
                        $purpose,
                        $legacyPath,
                        $media,
                    );
                } else {
                    [$block, $matched] = $this->projectRegistrationPair(
                        $block,
                        'image_media_reference_key',
                        'image_path',
                        ImageUploadPurpose::AboutImage,
                        $purpose,
                        $legacyPath,
                        $media,
                    );
                }

                $payload['about_page']['blocks'][$index] = $block;

                if ($matched) {
                    $locations[] = "about_page.blocks.{$index}";
                }
            }
        }

        if (is_array(data_get($payload, 'about_page.team_profiles'))) {
            foreach ($payload['about_page']['team_profiles'] as $index => $profile) {
                if (! is_array($profile)) {
                    continue;
                }

                [$payload['about_page']['team_profiles'][$index], $matched] = $this->projectRegistrationPair(
                    $profile,
                    'image_media_reference_key',
                    'image_path',
                    ImageUploadPurpose::TeamImage,
                    $purpose,
                    $legacyPath,
                    $media,
                );

                if ($matched) {
                    $locations[] = "about_page.team_profiles.{$index}";
                }
            }
        }

        return [
            'payload' => $payload,
            'locations' => $locations,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{array<string, mixed>, bool}
     */
    private function projectRegistrationPair(
        array $state,
        string $referenceField,
        string $pathField,
        ImageUploadPurpose $expectedPurpose,
        ImageUploadPurpose $purpose,
        string $legacyPath,
        ?Media $media,
    ): array {
        if (($state[$pathField] ?? null) !== $legacyPath) {
            return [$state, false];
        }

        if ($expectedPurpose !== $purpose) {
            throw new \InvalidArgumentException('The legacy settings image path is stored under an incompatible purpose.');
        }

        $referenceKey = $state[$referenceField] ?? null;

        if ($referenceKey !== null && (! is_string($referenceKey) || filled($referenceKey))) {
            throw new \InvalidArgumentException('The legacy settings image already carries another media identity.');
        }

        if ($media instanceof Media) {
            $state[$referenceField] = $media->reference_key;
            $state[$pathField] = $media->path;
        }

        return [$state, true];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function reconcilePair(
        array $state,
        string $referenceField,
        string $pathField,
        ImageUploadPurpose $purpose,
        bool $removeLegacyPath,
        bool $requireRegisteredMedia,
        bool $requireReferenceKey,
    ): array {
        foreach ([$referenceField, $pathField] as $field) {
            if (
                array_key_exists($field, $state)
                && $state[$field] !== null
                && ! is_string($state[$field])
            ) {
                throw new \InvalidArgumentException("The settings media identity field {$field} must be a string or null.");
            }
        }

        $referenceKey = is_string($state[$referenceField] ?? null) && filled($state[$referenceField])
            ? mb_strtoupper(trim($state[$referenceField]))
            : null;
        $legacyPath = is_string($state[$pathField] ?? null) && filled($state[$pathField])
            ? trim($state[$pathField])
            : null;

        if ($requireReferenceKey && $legacyPath !== null && $referenceKey === null) {
            throw new \InvalidArgumentException('Portable settings media identity requires a media reference key.');
        }

        $media = $this->resolver->resolve($referenceKey, $legacyPath, $purpose);

        if (
            $requireRegisteredMedia
            && ($referenceKey !== null || $legacyPath !== null)
            && ($media === null || blank($media->reference_key))
        ) {
            throw new \InvalidArgumentException('Portable media identity requires a registered media reference key.');
        }

        $state[$referenceField] = $media?->reference_key;

        if ($removeLegacyPath) {
            unset($state[$pathField]);
        } else {
            $state[$pathField] = $media?->path
                ?? $this->resolver->path(null, $legacyPath, $purpose);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, string>  $referenceKeysByPath
     * @param  array<string, true>  $knownReferenceKeyLookup
     * @return array{array<string, mixed>, ?string}
     */
    private function fillUniquePathPair(
        array $state,
        string $referenceField,
        string $pathField,
        array $referenceKeysByPath,
        array $knownReferenceKeyLookup,
    ): array {
        $referenceKey = $state[$referenceField] ?? null;
        $hasReferenceKey = is_string($referenceKey) && filled($referenceKey);

        if ($hasReferenceKey && isset($knownReferenceKeyLookup[$referenceKey])) {
            return [$state, null];
        }

        $path = $state[$pathField] ?? null;

        if (! is_string($path) || blank($path)) {
            return [$state, $hasReferenceKey ? 'unresolved' : null];
        }

        if (! isset($referenceKeysByPath[$path])) {
            return [$state, 'unresolved'];
        }

        $state[$referenceField] = $referenceKeysByPath[$path];

        return [$state, 'filled'];
    }

    /**
     * @param  array<int, string>  $filled
     * @param  array<int, string>  $unresolved
     */
    private function recordUniquePathOutcome(
        ?string $outcome,
        string $location,
        array &$filled,
        array &$unresolved,
    ): void {
        if ($outcome === 'filled') {
            $filled[] = $location;

            return;
        }

        if ($outcome === 'unresolved') {
            $unresolved[] = $location;
        }
    }
}
