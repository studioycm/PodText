<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class ImageUploadValidator
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
        private readonly SvgUploadSanitizer $svgSanitizer,
    ) {}

    public function validateUploadedFile(TemporaryUploadedFile|UploadedFile $file, ImageUploadPurpose $purpose): ValidatedImage
    {
        [$contents, $clientFilename] = $this->readUploadedFile($file);

        return $this->validateBytes($contents, $clientFilename, $purpose);
    }

    public function validateUploadedFileForAdmission(TemporaryUploadedFile|UploadedFile $file, ImageUploadPurpose $purpose): ValidatedImage
    {
        [$contents, $clientFilename] = $this->readUploadedFile($file);

        return $this->validateAdmissionBytes($contents, $clientFilename, $purpose);
    }

    /** @return array{string, string} */
    private function readUploadedFile(TemporaryUploadedFile|UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw new InvalidArgumentException('The uploaded file is unavailable.');
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new InvalidArgumentException('The uploaded file could not be read.');
        }

        return [$contents, $file->getClientOriginalName()];
    }

    public function validateBytes(string $contents, string $clientFilename, ImageUploadPurpose $purpose): ValidatedImage
    {
        return $this->validate(
            $contents,
            $clientFilename,
            $purpose,
            extensionRequired: true,
            preserveRaster: false,
            maxKilobytes: CuratorImageUploadPolicy::MAX_KILOBYTES,
            maxDimensionPixels: CuratorImageUploadPolicy::MAX_DIMENSION_PIXELS,
        );
    }

    public function validateAdmissionBytes(string $contents, string $clientFilename, ImageUploadPurpose $purpose): ValidatedImage
    {
        return $this->validate(
            $contents,
            $clientFilename,
            $purpose,
            extensionRequired: true,
            preserveRaster: true,
            maxKilobytes: $this->policy->maxKilobytes(),
            maxDimensionPixels: $this->policy->maxDimensionPixels(),
        );
    }

    public function validateExternalBytes(string $contents, string $clientFilename, ImageUploadPurpose $purpose): ValidatedImage
    {
        return $this->validate(
            $contents,
            $clientFilename,
            $purpose,
            extensionRequired: false,
            preserveRaster: true,
            maxKilobytes: $this->policy->maxKilobytes(),
            maxDimensionPixels: $this->policy->maxDimensionPixels(),
        );
    }

    private function validate(
        string $contents,
        string $clientFilename,
        ImageUploadPurpose $purpose,
        bool $extensionRequired,
        bool $preserveRaster,
        int $maxKilobytes,
        int $maxDimensionPixels,
    ): ValidatedImage {
        $size = strlen($contents);

        if ($size === 0 || $size > $maxKilobytes * 1024) {
            throw new InvalidArgumentException('The image exceeds the allowed size or is empty.');
        }

        $originalFilename = $this->policy->cleanedOriginalFilename($clientFilename);
        $candidateExtension = mb_strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $clientExtension = $candidateExtension === '' && ! $extensionRequired
            ? null
            : $this->policy->clientExtension($originalFilename);
        $mimeType = $this->contentMimeType($contents, $clientExtension);

        if (! $this->policy->allowsMime($purpose, $mimeType)) {
            throw new InvalidArgumentException('The image type is not allowed for this purpose.');
        }

        if ($clientExtension !== null && ! $this->policy->extensionMatchesMime($clientExtension, $mimeType)) {
            throw new InvalidArgumentException('The client extension does not match the image content.');
        }

        if ($mimeType === 'image/svg+xml') {
            $sanitized = $this->svgSanitizer->sanitize($contents);

            if (strlen($sanitized) > $maxKilobytes * 1024) {
                throw new InvalidArgumentException('The sanitized SVG exceeds the allowed size.');
            }

            return new ValidatedImage(
                purpose: $purpose,
                contents: $sanitized,
                mimeType: $mimeType,
                extension: $this->policy->canonicalExtension($mimeType),
                size: strlen($sanitized),
                width: null,
                height: null,
                sha256: hash('sha256', $sanitized),
                displayFilename: $preserveRaster
                    ? $originalFilename
                    : pathinfo($originalFilename, PATHINFO_FILENAME),
                originalFilename: $originalFilename,
            );
        }

        $this->assertCompleteRasterStructure($contents, $mimeType);
        [$headerWidth, $headerHeight] = $this->rasterHeaderDimensions($contents, $mimeType);
        $this->assertRasterDimensions($headerWidth, $headerHeight, $maxDimensionPixels);

        try {
            $image = ImageManager::gd()->read($contents)->orient();
            $width = $image->width();
            $height = $image->height();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The raster image could not be decoded.', previous: $exception);
        }

        $this->assertRasterDimensions($width, $height, $maxDimensionPixels);

        if (
            ($width !== $headerWidth || $height !== $headerHeight)
            && ($width !== $headerHeight || $height !== $headerWidth)
        ) {
            throw new InvalidArgumentException('The decoded raster dimensions disagree with its header.');
        }

        if ($preserveRaster) {
            return new ValidatedImage(
                purpose: $purpose,
                contents: $contents,
                mimeType: $mimeType,
                extension: $this->policy->canonicalExtension($mimeType),
                size: $size,
                width: $width,
                height: $height,
                sha256: hash('sha256', $contents),
                displayFilename: $originalFilename,
                originalFilename: $originalFilename,
            );
        }

        $normalized = match ($mimeType) {
            'image/jpeg' => $image->toJpeg(quality: 90, progressive: true, strip: true)->toString(),
            'image/png' => $image->toPng(interlaced: false, indexed: false)->toString(),
            'image/webp' => $image->toWebp(quality: 90, strip: true)->toString(),
            default => throw new InvalidArgumentException('The raster image type is not supported.'),
        };

        if (strlen($normalized) > $maxKilobytes * 1024) {
            throw new InvalidArgumentException('The normalized raster image exceeds the allowed size.');
        }

        $this->assertCompleteRasterStructure($normalized, $mimeType);

        return new ValidatedImage(
            purpose: $purpose,
            contents: $normalized,
            mimeType: $mimeType,
            extension: $this->policy->canonicalExtension($mimeType),
            size: strlen($normalized),
            width: $width,
            height: $height,
            sha256: hash('sha256', $normalized),
            displayFilename: pathinfo($originalFilename, PATHINFO_FILENAME),
            originalFilename: $originalFilename,
        );
    }

    private function contentMimeType(string $contents, ?string $clientExtension): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents);

        if (($clientExtension === 'svg' || $clientExtension === null) && $this->hasSvgRoot($contents)) {
            return 'image/svg+xml';
        }

        return is_string($mimeType) ? mb_strtolower($mimeType) : 'application/octet-stream';
    }

    private function hasSvgRoot(string $contents): bool
    {
        return preg_match('/^\s*(?:<\?xml[^>]*>\s*)?<svg(?:\s|>)/i', $contents) === 1;
    }

    private function assertCompleteRasterStructure(string $contents, string $mimeType): void
    {
        match ($mimeType) {
            'image/jpeg' => $this->assertJpegStructure($contents),
            'image/png' => $this->assertPngStructure($contents),
            'image/webp' => $this->assertWebpStructure($contents),
            default => throw new InvalidArgumentException('The raster image type is unsupported.'),
        };
    }

    /** @return array{int, int} */
    private function rasterHeaderDimensions(string $contents, string $mimeType): array
    {
        $dimensions = @getimagesizefromstring($contents);

        if (! is_array($dimensions)) {
            throw new InvalidArgumentException('The raster image header could not be read.');
        }

        $width = $dimensions[0] ?? null;
        $height = $dimensions[1] ?? null;
        $detectedMime = $dimensions['mime'] ?? null;

        if (! is_int($width) || ! is_int($height) || $detectedMime !== $mimeType) {
            throw new InvalidArgumentException('The raster image header disagrees with its detected type.');
        }

        return [$width, $height];
    }

    private function assertRasterDimensions(int $width, int $height, int $maxDimensionPixels): void
    {
        if (
            $width < 1
            || $height < 1
            || $width > $maxDimensionPixels
            || $height > $maxDimensionPixels
        ) {
            throw new InvalidArgumentException('The raster image dimensions are outside the allowed range.');
        }
    }

    private function assertJpegStructure(string $contents): void
    {
        if (! str_starts_with($contents, "\xFF\xD8") || ! str_ends_with($contents, "\xFF\xD9")) {
            throw new InvalidArgumentException('The JPEG structure is incomplete or contains trailing data.');
        }
    }

    private function assertPngStructure(string $contents): void
    {
        if (! str_starts_with($contents, "\x89PNG\r\n\x1A\n")) {
            throw new InvalidArgumentException('The PNG signature is invalid.');
        }

        $offset = 8;
        $length = strlen($contents);
        $sawHeader = false;
        $sawEnd = false;

        while ($offset + 12 <= $length) {
            $chunkLength = unpack('Nlength', substr($contents, $offset, 4))['length'] ?? -1;
            $type = substr($contents, $offset + 4, 4);
            $chunkEnd = $offset + 12 + $chunkLength;

            if ($chunkLength < 0 || $chunkEnd > $length) {
                throw new InvalidArgumentException('The PNG chunk structure is invalid.');
            }

            $expectedCrc = unpack('Ncrc', substr($contents, $chunkEnd - 4, 4))['crc'] ?? -1;
            $actualCrc = crc32(substr($contents, $offset + 4, 4 + $chunkLength));
            $actualCrc = $actualCrc < 0 ? $actualCrc + 4294967296 : $actualCrc;

            if ($expectedCrc !== $actualCrc) {
                throw new InvalidArgumentException('The PNG chunk checksum is invalid.');
            }

            if (! $sawHeader && $type !== 'IHDR') {
                throw new InvalidArgumentException('The PNG header chunk is missing.');
            }

            $sawHeader = true;
            $offset = $chunkEnd;

            if ($type === 'IEND') {
                $sawEnd = true;
                break;
            }
        }

        if (! $sawHeader || ! $sawEnd || $offset !== $length) {
            throw new InvalidArgumentException('The PNG is incomplete or contains trailing data.');
        }
    }

    private function assertWebpStructure(string $contents): void
    {
        if (strlen($contents) < 12 || substr($contents, 0, 4) !== 'RIFF' || substr($contents, 8, 4) !== 'WEBP') {
            throw new InvalidArgumentException('The WebP signature is invalid.');
        }

        $declaredSize = unpack('Vsize', substr($contents, 4, 4))['size'] ?? -1;

        if ($declaredSize < 4 || $declaredSize + 8 !== strlen($contents)) {
            throw new InvalidArgumentException('The WebP is incomplete or contains trailing data.');
        }
    }
}
