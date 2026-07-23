<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;

readonly class ValidatedImage
{
    public function __construct(
        public ImageUploadPurpose $purpose,
        public string $contents,
        public string $mimeType,
        public string $extension,
        public int $size,
        public ?int $width,
        public ?int $height,
        public string $sha256,
        public ?string $displayFilename = null,
        public ?string $originalFilename = null,
    ) {}

    public function isSvg(): bool
    {
        return $this->mimeType === 'image/svg+xml';
    }
}
