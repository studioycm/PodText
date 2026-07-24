<?php

namespace App\Support\Media;

readonly class OwnerImagePresentation
{
    /**
     * @param  array<string, mixed>|null  $media
     * @param  array<int, array{id: int, label: string, url: string}>  $reviewMedia
     * @param  array<int, string>  $warningCodes
     */
    public function __construct(
        public string $effectiveSource,
        public ?string $effectivePreviewUrl,
        public ?string $effectiveAlt,
        public bool $hasDirectAttachment,
        public bool $brokenDirect,
        public bool $canRemoveDirect,
        public bool $canImportExternal,
        public ?int $expectedMediaId,
        public ?string $expectedLegacyPath,
        public ?string $unsafeFingerprint,
        public ?array $media,
        public array $reviewMedia,
        public array $warningCodes,
    ) {}
}
