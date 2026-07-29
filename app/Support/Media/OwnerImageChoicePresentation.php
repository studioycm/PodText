<?php

namespace App\Support\Media;

readonly class OwnerImageChoicePresentation
{
    /**
     * @param  array{id: int, reference_key: string, label: string, preview_url: string|null, details_url: string|null}|null  $directMedia
     * @param  array{id: int, reference_key: string, label: string, preview_url: string|null, details_url: string|null}|null  $shownNowMedia
     * @param  array{id: int, reference_key: string, label: string, preview_url: string|null, details_url: string|null}|null  $pendingMedia
     * @param  array{commit: string, cancel: string, admission?: string}  $commitBoundary
     * @param  array<int, string>  $warningCodes
     */
    public function __construct(
        public string $ownerKind,
        public string $ownerKindLabel,
        public ?string $ownerLabel,
        public string $slotKind,
        public string $slotLabel,
        public bool $isProspective,
        public string $directState,
        public ?array $directMedia,
        public string $shownNowSource,
        public ?array $shownNowMedia,
        public ?string $shownNowPreviewUrl,
        public ?int $savedMediaId,
        public ?string $savedReferenceKey,
        public string $pendingKind,
        public ?array $pendingMedia,
        public bool $canClearPending,
        public bool $canChooseAutomatic,
        public ?int $expectedMediaId,
        public ?string $expectedLegacyPath,
        public array $commitBoundary,
        public array $warningCodes,
        public bool $savedMediaCanSelect = false,
        public ?string $pendingFallbackSource = null,
        public ?string $pendingFallbackPreviewUrl = null,
    ) {}
}
