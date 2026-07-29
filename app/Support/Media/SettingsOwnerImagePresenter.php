<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Filament\Pages\SettingsSubjectOwnershipRegistry;
use App\Models\Media;
use App\Support\PublicFront\About\PublicAboutBlockKey;
use App\Support\PublicFront\PublicDefaultImageResolver;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SettingsOwnerImagePresenter
{
    /** @var array<int, Media|null> */
    private array $openedMediaById = [];

    private ?PublicDefaultImageResolver $defaultImageResolver = null;

    public function __construct(
        private readonly MediaIdentityResolver $identityResolver,
        private readonly MediaRecordProjector $mediaProjector,
        private readonly MediaRecordScope $mediaRecordScope,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public function snapshot(array $settings, string $subject): SettingsOwnerImageSnapshot
    {
        $slots = match ($subject) {
            SettingsSubjectOwnershipRegistry::MENU_HEADER => $this->logoSlots($settings),
            SettingsSubjectOwnershipRegistry::DISPLAY => $this->defaultImageSlots($settings),
            SettingsSubjectOwnershipRegistry::ABOUT => $this->aboutSlots($settings),
            default => [],
        };
        ksort($slots);
        $identities = collect($slots)
            ->map(fn (array $slot): array => [
                'direct_state' => $slot['direct_state'],
                'configured_evidence_hash' => $slot['configured_evidence_hash'],
                'configured_reference_key' => $slot['configured_reference_key'],
                'configured_path' => $slot['saved_path'],
                'resolved_media_id' => $slot['direct_media']?->getKey(),
                'resolved_reference_key' => $slot['direct_media']?->reference_key,
                'resolved_path' => $slot['direct_media']?->path,
            ])
            ->all();

        return new SettingsOwnerImageSnapshot(
            subject: $subject,
            fingerprint: hash('sha256', json_encode([
                'subject' => $subject,
                'identities' => $identities,
            ], JSON_THROW_ON_ERROR)),
            slots: $slots,
        );
    }

    /**
     * @return array<string, array{
     *     direct_state: string,
     *     reference_key: string|null,
     *     legacy_path: string|null,
     *     evidence_hash: string|null,
     *     resolved_media_id: int|null,
     *     can_choose_automatic: bool
     * }>
     */
    public function openedEvidence(SettingsOwnerImageSnapshot $snapshot): array
    {
        return collect($snapshot->slots)
            ->map(fn (array $slot): array => [
                'direct_state' => $slot['direct_state'],
                'reference_key' => $slot['configured_reference_key'],
                'legacy_path' => $slot['saved_path'],
                'evidence_hash' => $slot['configured_evidence_hash'],
                'resolved_media_id' => $slot['direct_media'] instanceof Media
                    ? (int) $slot['direct_media']->getKey()
                    : null,
                'can_choose_automatic' => $slot['can_choose_automatic'],
            ])
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $evidence
     */
    public function primeOpenedEvidence(array $evidence): void
    {
        $ids = collect($evidence)
            ->pluck('resolved_media_id')
            ->filter(fn (mixed $id): bool => is_int($id) && $id > 0)
            ->unique()
            ->reject(fn (int $id): bool => array_key_exists($id, $this->openedMediaById))
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        foreach ($ids as $id) {
            $this->openedMediaById[$id] = null;
        }

        $this->mediaRecordScope
            ->inventoryQuery()
            ->whereKey($ids->all())
            ->get()
            ->filter(fn (Media $media): bool => Gate::allows('view', $media))
            ->each(function (Media $media): void {
                $this->openedMediaById[(int) $media->getKey()] = $media;
            });
    }

    /**
     * @param  array{commit: string, cancel: string, admission: string}  $commitBoundary
     * @param  array{
     *     direct_state?: string,
     *     reference_key?: string|null,
     *     legacy_path?: string|null,
     *     evidence_hash?: string|null,
     *     resolved_media_id?: int|null,
     *     can_choose_automatic?: bool
     * }|null  $openedEvidence
     */
    public function choice(
        SettingsOwnerImageSnapshot $snapshot,
        string $slotKey,
        int|string|null $pendingIdentity,
        array $commitBoundary,
        ?array $openedEvidence = null,
    ): OwnerImageChoicePresentation {
        $slot = $snapshot->slot($slotKey) ?? $this->prospectiveSlot($slotKey);
        $usesOpenedEvidence = $this->isOpenedEvidence($openedEvidence);
        $openedMedia = $usesOpenedEvidence
            ? $this->openedMedia($openedEvidence['resolved_media_id'] ?? null)
            : null;
        $savedMedia = $usesOpenedEvidence ? $openedMedia : $slot['direct_media'];
        $savedMediaId = $savedMedia instanceof Media ? (int) $savedMedia->getKey() : null;
        $savedReferenceKey = $usesOpenedEvidence
            ? (is_string($openedEvidence['reference_key'] ?? null)
                ? $openedEvidence['reference_key']
                : ($savedMedia instanceof Media ? (string) $savedMedia->reference_key : null))
            : (filled($slot['configured_reference_key'])
                ? (string) $slot['configured_reference_key']
                : ($savedMedia instanceof Media ? (string) $savedMedia->reference_key : null));
        $directState = $usesOpenedEvidence
            ? (string) $openedEvidence['direct_state']
            : $slot['direct_state'];
        $expectedLegacyPath = $usesOpenedEvidence
            ? (is_string($openedEvidence['legacy_path'] ?? null)
                ? $openedEvidence['legacy_path']
                : null)
            : $slot['saved_path'];
        if (
            $usesOpenedEvidence
            && is_int($openedEvidence['resolved_media_id'] ?? null)
            && ! $savedMedia instanceof Media
        ) {
            $directState = 'broken';
        }
        $openedEvidenceIsMalformed = $usesOpenedEvidence
            && $directState === 'broken'
            && blank($savedReferenceKey)
            && blank($expectedLegacyPath)
            && filled($openedEvidence['evidence_hash'] ?? null);
        $canChooseAutomatic = $usesOpenedEvidence
            ? (bool) ($openedEvidence['can_choose_automatic'] ?? false)
            : (bool) $slot['can_choose_automatic'];
        $savedMediaCanSelect = $savedMedia instanceof Media && Gate::allows('select', $savedMedia);
        $pendingMedia = $savedMediaCanSelect
            && $this->pendingMatchesSavedMedia($pendingIdentity, $savedMedia)
            ? $savedMedia
            : $this->pendingMedia($pendingIdentity, $slot['purpose']);
        $pendingKind = match (true) {
            $pendingMedia instanceof Media && (int) $pendingMedia->getKey() !== $savedMediaId => 'replacement',
            $pendingMedia instanceof Media => 'unchanged',
            filled($pendingIdentity) => 'unchanged',
            $savedMedia instanceof Media => 'automatic_fallback',
            default => 'unchanged',
        };
        $pendingChoiceMedia = $pendingKind === 'replacement' && $pendingMedia instanceof Media
            ? $this->choiceMedia($pendingMedia)
            : null;

        if ($directState === 'broken' && is_array($pendingChoiceMedia)) {
            $pendingChoiceMedia['preview_url'] = null;
            $pendingChoiceMedia['details_url'] = null;
        }

        return new OwnerImageChoicePresentation(
            ownerKind: $slotKey,
            ownerKindLabel: $slot['owner_kind_label'],
            ownerLabel: $slot['owner_label'],
            slotKind: $slot['slot_kind'],
            slotLabel: $slot['slot_label'],
            isProspective: $slot['prospective'],
            directState: $directState,
            directMedia: $usesOpenedEvidence
                ? ($savedMedia instanceof Media && $directState !== 'broken'
                    ? $this->choiceMedia($savedMedia)
                    : null)
                : $slot['direct_choice_media'],
            shownNowSource: $directState === 'broken' ? 'none' : $slot['shown_source'],
            shownNowMedia: $directState === 'broken' ? null : $slot['shown_choice_media'],
            shownNowPreviewUrl: $directState === 'broken' ? null : $slot['shown_url'],
            savedMediaId: $savedMediaId,
            savedReferenceKey: $savedReferenceKey,
            pendingKind: $pendingKind,
            pendingMedia: $pendingChoiceMedia,
            canClearPending: $pendingKind !== 'unchanged',
            canChooseAutomatic: ! $openedEvidenceIsMalformed
                && ($savedMedia instanceof Media || $directState === 'broken')
                && $canChooseAutomatic,
            expectedMediaId: $savedMediaId,
            expectedLegacyPath: $expectedLegacyPath,
            commitBoundary: $commitBoundary,
            warningCodes: [],
            savedMediaCanSelect: $savedMediaCanSelect,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    private function logoSlots(array $settings): array
    {
        $logo = data_get($settings, 'menu_config.logo', []);
        $logo = is_array($logo) ? $logo : [];

        return collect([
            'light' => ['light_media_reference_key', 'light_path'],
            'dark' => ['dark_media_reference_key', 'dark_path'],
        ])->mapWithKeys(function (array $fields, string $variant) use ($logo): array {
            [$referenceField, $pathField] = $fields;
            $media = $this->resolveMedia($logo, $referenceField, $pathField, ImageUploadPurpose::HeaderLogo);
            $key = "menu_config.logo.{$referenceField}";

            return [$key => $this->slot(
                purpose: ImageUploadPurpose::HeaderLogo,
                ownerKindLabel: __('admin.sections.public_menu_logo'),
                ownerLabel: null,
                slotKind: "logo_{$variant}",
                slotLabel: __("admin.fields.public_menu_logo_{$variant}_path"),
                directState: $this->directState(
                    $media,
                    $logo[$referenceField] ?? null,
                    $logo[$pathField] ?? null,
                ),
                directMedia: $media,
                shownMedia: $media,
                shownSource: $media instanceof Media ? 'configured_default' : 'none',
                shownUrl: $this->previewUrl($media),
                savedPath: is_string($logo[$pathField] ?? null) ? $logo[$pathField] : null,
                configuredReferenceKey: is_string($logo[$referenceField] ?? null)
                    ? $logo[$referenceField]
                    : null,
                configuredEvidenceHash: $this->configuredEvidenceHash(
                    $logo[$referenceField] ?? null,
                    $logo[$pathField] ?? null,
                ),
                canChooseAutomatic: ! $this->hasMalformedConfiguredEvidence(
                    $logo[$referenceField] ?? null,
                    $logo[$pathField] ?? null,
                ),
            )];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    private function defaultImageSlots(array $settings): array
    {
        $defaultImages = is_array($settings['default_images'] ?? null)
            ? $settings['default_images']
            : [];

        return collect(PublicFrontConfigRegistry::defaultImageFamilies())
            ->mapWithKeys(function (string $family) use ($defaultImages): array {
                $projection = $this->defaultImageResolver()->projectDefaultFamily($family, $defaultImages);
                $config = is_array($defaultImages[$family] ?? null) ? $defaultImages[$family] : [];
                $key = "default_images.{$family}.media_reference_key";
                $directState = $projection['direct_media'] instanceof Media
                    ? $projection['mode']
                    : $this->directState(
                        null,
                        $config['media_reference_key'] ?? null,
                        $config['path'] ?? null,
                        absentState: $projection['mode'],
                    );

                return [$key => $this->slot(
                    purpose: ImageUploadPurpose::DefaultImage,
                    ownerKindLabel: __("admin.default_image_families.{$family}"),
                    ownerLabel: null,
                    slotKind: $family,
                    slotLabel: __('admin.fields.default_image_path'),
                    directState: $directState,
                    directMedia: $projection['direct_media'],
                    shownMedia: $projection['shown_media'],
                    shownSource: $projection['shown_source'],
                    shownUrl: $projection['shown_url'],
                    savedPath: is_string($config['path'] ?? null) ? $config['path'] : null,
                    configuredReferenceKey: is_string($config['media_reference_key'] ?? null)
                        ? $config['media_reference_key']
                        : null,
                    configuredEvidenceHash: $this->configuredEvidenceHash(
                        $config['media_reference_key'] ?? null,
                        $config['path'] ?? null,
                    ),
                    canChooseAutomatic: false,
                )];
            })
            ->all();
    }

    private function defaultImageResolver(): PublicDefaultImageResolver
    {
        return $this->defaultImageResolver ??= app(PublicDefaultImageResolver::class);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    private function aboutSlots(array $settings): array
    {
        $about = is_array($settings['about_page'] ?? null) ? $settings['about_page'] : [];
        $blocks = collect($about['blocks'] ?? [])
            ->filter(fn (mixed $block): bool => is_array($block))
            ->values();
        $blockKeys = $blocks->map(
            fn (array $block, int $index): ?string => PublicAboutBlockKey::fromPersistedBlock($block, $index),
        );
        $blockKeyCounts = $blockKeys
            ->filter()
            ->countBy();
        $profiles = collect($about['team_profiles'] ?? [])
            ->filter(fn (mixed $profile): bool => is_array($profile) && filled($profile['key'] ?? null))
            ->values();
        $profileKeyCounts = $profiles->countBy(fn (array $profile): string => (string) $profile['key']);
        $this->primeAboutMedia($blocks, ImageUploadPurpose::AboutImage);
        $this->primeAboutMedia($profiles, ImageUploadPurpose::TeamImage);
        $slots = [];

        foreach ($blocks as $index => $block) {
            $key = $blockKeys->get($index);

            if (! is_string($key)) {
                continue;
            }

            $media = $this->resolveMedia(
                $block,
                'image_media_reference_key',
                'image_path',
                ImageUploadPurpose::AboutImage,
            );
            $slotKey = $blockKeyCounts->get($key) === 1
                ? "about_page.blocks.{$key}.image_media_reference_key"
                : "about_page.blocks.__invalid_duplicate.{$key}.{$index}.image_media_reference_key";
            $slots[$slotKey] = $this->slot(
                purpose: ImageUploadPurpose::AboutImage,
                ownerKindLabel: __('admin.fields.about_page_blocks'),
                ownerLabel: $key,
                slotKind: 'about_block',
                slotLabel: __('admin.fields.about_block_image'),
                directState: $this->directState(
                    $media,
                    $block['image_media_reference_key'] ?? null,
                    $block['image_path'] ?? null,
                ),
                directMedia: $media,
                shownMedia: $media,
                shownSource: $media instanceof Media ? 'configured_default' : 'none',
                shownUrl: $this->previewUrl($media),
                savedPath: is_string($block['image_path'] ?? null) ? $block['image_path'] : null,
                configuredReferenceKey: is_string($block['image_media_reference_key'] ?? null)
                    ? $block['image_media_reference_key']
                    : null,
                configuredEvidenceHash: $this->configuredEvidenceHash(
                    $block['image_media_reference_key'] ?? null,
                    $block['image_path'] ?? null,
                ),
                canChooseAutomatic: false,
            );
        }

        foreach ($profiles as $index => $profile) {
            $key = (string) $profile['key'];
            $media = $this->resolveMedia(
                $profile,
                'image_media_reference_key',
                'image_path',
                ImageUploadPurpose::TeamImage,
            );
            $slotKey = $profileKeyCounts->get($key) === 1
                ? "about_page.team_profiles.{$key}.image_media_reference_key"
                : "about_page.team_profiles.__invalid_duplicate.{$key}.{$index}.image_media_reference_key";
            $slots[$slotKey] = $this->slot(
                purpose: ImageUploadPurpose::TeamImage,
                ownerKindLabel: __('admin.fields.about_page_team_profiles'),
                ownerLabel: is_string($profile['name'] ?? null) ? $profile['name'] : $key,
                slotKind: 'team_profile',
                slotLabel: __('admin.fields.about_team_profile_image'),
                directState: $this->directState(
                    $media,
                    $profile['image_media_reference_key'] ?? null,
                    $profile['image_path'] ?? null,
                ),
                directMedia: $media,
                shownMedia: $media,
                shownSource: $media instanceof Media ? 'configured_default' : 'none',
                shownUrl: $this->previewUrl($media),
                savedPath: is_string($profile['image_path'] ?? null) ? $profile['image_path'] : null,
                configuredReferenceKey: is_string($profile['image_media_reference_key'] ?? null)
                    ? $profile['image_media_reference_key']
                    : null,
                configuredEvidenceHash: $this->configuredEvidenceHash(
                    $profile['image_media_reference_key'] ?? null,
                    $profile['image_path'] ?? null,
                ),
                canChooseAutomatic: ! $this->hasMalformedConfiguredEvidence(
                    $profile['image_media_reference_key'] ?? null,
                    $profile['image_path'] ?? null,
                ),
            );
        }

        return $slots;
    }

    /**
     * @return array<string, mixed>
     */
    private function slot(
        ImageUploadPurpose $purpose,
        string $ownerKindLabel,
        ?string $ownerLabel,
        string $slotKind,
        string $slotLabel,
        string $directState,
        ?Media $directMedia,
        ?Media $shownMedia,
        string $shownSource,
        ?string $shownUrl,
        ?string $savedPath,
        ?string $configuredReferenceKey = null,
        ?string $configuredEvidenceHash = null,
        bool $prospective = false,
        bool $canChooseAutomatic = true,
    ): array {
        $directChoiceMedia = $directMedia instanceof Media ? $this->choiceMedia($directMedia) : null;
        $shownChoiceMedia = match (true) {
            ! $shownMedia instanceof Media => null,
            $directMedia instanceof Media && $shownMedia->is($directMedia) => $directChoiceMedia,
            default => $this->choiceMedia($shownMedia),
        };

        return [
            'purpose' => $purpose,
            'owner_kind_label' => $ownerKindLabel,
            'owner_label' => $ownerLabel,
            'slot_kind' => $slotKind,
            'slot_label' => $slotLabel,
            'direct_state' => $directState,
            'direct_media' => $directMedia,
            'direct_choice_media' => $directChoiceMedia,
            'shown_media' => $shownMedia,
            'shown_choice_media' => $shownChoiceMedia,
            'shown_source' => $shownSource,
            'shown_url' => $shownUrl,
            'saved_path' => $savedPath,
            'configured_reference_key' => $configuredReferenceKey,
            'configured_evidence_hash' => $configuredEvidenceHash,
            'prospective' => $prospective,
            'can_choose_automatic' => $canChooseAutomatic,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function primeAboutMedia(
        Collection $rows,
        ImageUploadPurpose $purpose,
    ): void {
        $this->identityResolver->primeReferenceKeys(
            $rows->pluck('image_media_reference_key'),
            $purpose,
        );
        $this->identityResolver->primeLegacyPaths(
            $rows->pluck('image_path'),
            $purpose,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function prospectiveSlot(string $slotKey): array
    {
        $profile = str_contains($slotKey, '.team_profiles.');
        $purpose = $profile ? ImageUploadPurpose::TeamImage : ImageUploadPurpose::AboutImage;

        return $this->slot(
            purpose: $purpose,
            ownerKindLabel: $profile
                ? __('admin.fields.about_page_team_profiles')
                : __('admin.fields.about_page_blocks'),
            ownerLabel: str($slotKey)->between(
                $profile ? '.team_profiles.' : '.blocks.',
                '.image_media_reference_key',
            )->toString(),
            slotKind: $profile ? 'team_profile' : 'about_block',
            slotLabel: $profile
                ? __('admin.fields.about_team_profile_image')
                : __('admin.fields.about_block_image'),
            directState: 'absent',
            directMedia: null,
            shownMedia: null,
            shownSource: 'none',
            shownUrl: null,
            savedPath: null,
            prospective: true,
        );
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function resolveMedia(
        array $state,
        string $referenceField,
        string $pathField,
        ImageUploadPurpose $purpose,
    ): ?Media {
        try {
            return $this->identityResolver->resolve(
                is_string($state[$referenceField] ?? null) ? $state[$referenceField] : null,
                is_string($state[$pathField] ?? null) ? $state[$pathField] : null,
                $purpose,
            );
        } catch (InvalidArgumentException|UnresolvableMediaIdentityException|UnsafeLegacyOwnerMediaException) {
            return null;
        }
    }

    private function pendingMedia(int|string|null $identity, ImageUploadPurpose $purpose): ?Media
    {
        if (! filled($identity)) {
            return null;
        }

        try {
            $media = $this->identityResolver->resolve(
                is_string($identity) && ! ctype_digit($identity) ? $identity : null,
                null,
                $purpose,
            );

            if (! $media instanceof Media && (is_int($identity) || ctype_digit((string) $identity))) {
                $media = $this->mediaRecordScope->findInventory((int) $identity);
            }
        } catch (InvalidArgumentException|UnresolvableMediaIdentityException|UnsafeLegacyOwnerMediaException) {
            return null;
        }

        return $media instanceof Media && Gate::allows('select', $media) ? $media : null;
    }

    private function pendingMatchesSavedMedia(int|string|null $identity, mixed $savedMedia): bool
    {
        if (! $savedMedia instanceof Media || ! filled($identity)) {
            return false;
        }

        if (is_int($identity) || ctype_digit((string) $identity)) {
            return (int) $identity === (int) $savedMedia->getKey();
        }

        return mb_strtoupper(trim((string) $identity))
            === mb_strtoupper((string) $savedMedia->reference_key);
    }

    private function directState(
        ?Media $media,
        mixed $configuredReferenceKey,
        mixed $configuredPath,
        string $absentState = 'absent',
    ): string {
        if ($media instanceof Media) {
            return 'present';
        }

        if (
            $this->hasConfiguredEvidence($configuredReferenceKey)
            || $this->hasConfiguredEvidence($configuredPath)
        ) {
            return 'broken';
        }

        return $absentState;
    }

    private function hasConfiguredEvidence(mixed $value): bool
    {
        return $value !== null && (! is_string($value) || filled($value));
    }

    /**
     * @param  array<string, mixed>|null  $evidence
     */
    private function isOpenedEvidence(?array $evidence): bool
    {
        if (! is_array($evidence)) {
            return false;
        }

        $directState = $evidence['direct_state'] ?? null;
        $hash = $evidence['evidence_hash'] ?? null;
        $resolvedMediaId = $evidence['resolved_media_id'] ?? null;

        if (
            ! is_string($directState)
            || ! in_array($directState, ['absent', 'broken', 'custom', 'inherit', 'none', 'present'], true)
            || (($evidence['reference_key'] ?? null) !== null && ! is_string($evidence['reference_key']))
            || (($evidence['legacy_path'] ?? null) !== null && ! is_string($evidence['legacy_path']))
            || ! is_bool($evidence['can_choose_automatic'] ?? null)
            || ($resolvedMediaId !== null && (! is_int($resolvedMediaId) || $resolvedMediaId < 1))
            || ($hash !== null && (! is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1))
        ) {
            return false;
        }

        return $directState !== 'broken' || is_string($hash);
    }

    private function openedMedia(mixed $id): ?Media
    {
        if (! is_int($id) || $id < 1) {
            return null;
        }

        if (! array_key_exists($id, $this->openedMediaById)) {
            $this->primeOpenedEvidence([
                'slot' => ['resolved_media_id' => $id],
            ]);
        }

        return $this->openedMediaById[$id] ?? null;
    }

    private function configuredEvidenceHash(
        mixed $configuredReferenceKey,
        mixed $configuredPath,
    ): ?string {
        if (
            ! $this->hasConfiguredEvidence($configuredReferenceKey)
            && ! $this->hasConfiguredEvidence($configuredPath)
        ) {
            return null;
        }

        return hash('sha256', json_encode([
            'reference' => $configuredReferenceKey,
            'path' => $configuredPath,
        ], JSON_THROW_ON_ERROR));
    }

    private function hasMalformedConfiguredEvidence(
        mixed $configuredReferenceKey,
        mixed $configuredPath,
    ): bool {
        return ($configuredReferenceKey !== null && ! is_string($configuredReferenceKey))
            || ($configuredPath !== null && ! is_string($configuredPath));
    }

    /**
     * @return array{id: int, reference_key: string, label: string, preview_url: string|null, details_url: string|null}
     */
    private function choiceMedia(Media $media): array
    {
        $projection = $this->mediaProjector->project($media, withOwnerDetails: true);

        return [
            'id' => (int) $media->getKey(),
            'reference_key' => (string) $media->reference_key,
            'label' => (string) $projection['pretty_name'],
            'preview_url' => is_string($projection['preview_url']) ? $projection['preview_url'] : null,
            'details_url' => is_string($projection['details_url'] ?? null) ? $projection['details_url'] : null,
        ];
    }

    private function previewUrl(?Media $media): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        $projection = $this->mediaProjector->project($media);

        return is_string($projection['preview_url']) ? $projection['preview_url'] : null;
    }
}
