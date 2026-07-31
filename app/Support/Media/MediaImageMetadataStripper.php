<?php

namespace App\Support\Media;

use InvalidArgumentException;

/**
 * Removes embedded metadata (EXIF, XMP, IPTC, comments, text chunks) from
 * raster images at admission time without re-encoding pixel data. Display
 * orientation is preserved through a minimal orientation-only EXIF payload.
 */
class MediaImageMetadataStripper
{
    /** APP1 (EXIF/XMP), APP13 (IPTC/Photoshop), COM. */
    private const JPEG_METADATA_MARKERS = [0xE1, 0xED, 0xFE];

    private const PNG_METADATA_CHUNKS = ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'];

    private const WEBP_VP8X_EXIF_FLAG = 0x08;

    private const WEBP_VP8X_XMP_FLAG = 0x04;

    public function stripForAdmission(ValidatedImage $image): ValidatedImage
    {
        if ($image->isSvg()) {
            return $image;
        }

        $stripped = $this->stripBytes($image->contents, $image->mimeType);

        if ($stripped === $image->contents) {
            return $image;
        }

        $this->assertSameRasterShape($image, $stripped);

        return new ValidatedImage(
            purpose: $image->purpose,
            contents: $stripped,
            mimeType: $image->mimeType,
            extension: $image->extension,
            size: strlen($stripped),
            width: $image->width,
            height: $image->height,
            sha256: hash('sha256', $stripped),
            displayFilename: $image->displayFilename,
            originalFilename: $image->originalFilename,
        );
    }

    public function stripBytes(string $contents, string $mimeType): string
    {
        return match (mb_strtolower($mimeType)) {
            'image/jpeg' => $this->stripJpeg($contents),
            'image/png' => $this->stripPng($contents),
            'image/webp' => $this->stripWebp($contents),
            default => $contents,
        };
    }

    private function stripJpeg(string $contents): string
    {
        if (! str_starts_with($contents, "\xFF\xD8")) {
            throw new InvalidArgumentException('The JPEG signature is invalid.');
        }

        $length = strlen($contents);
        $offset = 2;
        $keptSegments = '';
        $removedSegment = false;
        $orientation = null;

        while ($offset + 4 <= $length) {
            if ($contents[$offset] !== "\xFF") {
                throw new InvalidArgumentException('The JPEG segment structure could not be parsed.');
            }

            $marker = ord($contents[$offset + 1]);

            if ($marker === 0xFF) {
                $keptSegments .= "\xFF";
                $offset++;

                continue;
            }

            if ($marker === 0xDA) {
                if (! $removedSegment) {
                    return $contents;
                }

                return "\xFF\xD8"
                    .$this->jpegOrientationSegment($orientation)
                    .$keptSegments
                    .substr($contents, $offset);
            }

            $segmentLength = (ord($contents[$offset + 2]) << 8) | ord($contents[$offset + 3]);

            if ($segmentLength < 2 || $offset + 2 + $segmentLength > $length) {
                throw new InvalidArgumentException('The JPEG segment structure could not be parsed.');
            }

            $segment = substr($contents, $offset, 2 + $segmentLength);

            if ($marker === 0xE1 && $orientation === null) {
                $orientation = $this->exifPayloadOrientation(substr($segment, 4));
            }

            if (in_array($marker, self::JPEG_METADATA_MARKERS, true)) {
                $removedSegment = true;
            } else {
                $keptSegments .= $segment;
            }

            $offset += 2 + $segmentLength;
        }

        throw new InvalidArgumentException('The JPEG segment structure could not be parsed.');
    }

    private function stripPng(string $contents): string
    {
        $signature = "\x89PNG\r\n\x1A\n";

        if (! str_starts_with($contents, $signature)) {
            throw new InvalidArgumentException('The PNG signature is invalid.');
        }

        $length = strlen($contents);
        $offset = 8;
        $output = $signature;
        $removedChunk = false;

        while ($offset + 12 <= $length) {
            $chunkLength = unpack('N', substr($contents, $offset, 4))[1] ?? -1;
            $type = substr($contents, $offset + 4, 4);
            $chunkEnd = $offset + 12 + $chunkLength;

            if ($chunkLength < 0 || $chunkEnd > $length) {
                throw new InvalidArgumentException('The PNG chunk structure is invalid.');
            }

            if (in_array($type, self::PNG_METADATA_CHUNKS, true)) {
                $removedChunk = true;
            } else {
                $output .= substr($contents, $offset, 12 + $chunkLength);
            }

            $offset = $chunkEnd;

            if ($type === 'IEND') {
                break;
            }
        }

        if ($offset !== $length) {
            throw new InvalidArgumentException('The PNG is incomplete or contains trailing data.');
        }

        return $removedChunk ? $output : $contents;
    }

    private function stripWebp(string $contents): string
    {
        if (strlen($contents) < 12 || ! str_starts_with($contents, 'RIFF') || substr($contents, 8, 4) !== 'WEBP') {
            throw new InvalidArgumentException('The WebP signature is invalid.');
        }

        $length = strlen($contents);
        $offset = 12;
        $removedChunk = false;
        $orientation = null;

        /** @var array<int, array{type: string, payload: string}> $chunks */
        $chunks = [];

        while ($offset + 8 <= $length) {
            $type = substr($contents, $offset, 4);
            $size = unpack('V', substr($contents, $offset + 4, 4))[1] ?? -1;
            $payloadEnd = $offset + 8 + $size;

            if ($size < 0 || $payloadEnd > $length) {
                throw new InvalidArgumentException('The WebP chunk structure is invalid.');
            }

            $payload = substr($contents, $offset + 8, $size);

            if ($type === 'EXIF' || $type === 'XMP ') {
                if ($type === 'EXIF' && $orientation === null) {
                    $orientation = $this->webpExifOrientation($payload);
                }

                $removedChunk = true;
            } else {
                $chunks[] = ['type' => $type, 'payload' => $payload];
            }

            $offset = $payloadEnd + ($size % 2);
        }

        if ($offset !== $length) {
            throw new InvalidArgumentException('The WebP is incomplete or contains trailing data.');
        }

        if (! $removedChunk) {
            return $contents;
        }

        $keepOrientation = $orientation !== null && $orientation !== 1;

        foreach ($chunks as $index => $chunk) {
            if ($chunk['type'] !== 'VP8X' || $chunk['payload'] === '') {
                continue;
            }

            $flags = ord($chunk['payload'][0]) & ~(self::WEBP_VP8X_EXIF_FLAG | self::WEBP_VP8X_XMP_FLAG);

            if ($keepOrientation) {
                $flags |= self::WEBP_VP8X_EXIF_FLAG;
            }

            $chunks[$index]['payload'] = chr($flags).substr($chunk['payload'], 1);
        }

        if ($keepOrientation && collect($chunks)->contains(fn (array $chunk): bool => $chunk['type'] === 'VP8X')) {
            $chunks[] = ['type' => 'EXIF', 'payload' => $this->minimalOrientationTiff($orientation)];
        }

        $body = '';

        foreach ($chunks as $chunk) {
            $body .= $chunk['type'].pack('V', strlen($chunk['payload'])).$chunk['payload'];

            if (strlen($chunk['payload']) % 2 === 1) {
                $body .= "\x00";
            }
        }

        return 'RIFF'.pack('V', strlen($body) + 4).'WEBP'.$body;
    }

    private function jpegOrientationSegment(?int $orientation): string
    {
        if ($orientation === null || $orientation === 1) {
            return '';
        }

        $payload = "Exif\x00\x00".$this->minimalOrientationTiff($orientation);

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    private function exifPayloadOrientation(string $payload): ?int
    {
        if (! str_starts_with($payload, "Exif\x00\x00")) {
            return null;
        }

        return $this->tiffOrientation(substr($payload, 6));
    }

    private function webpExifOrientation(string $payload): ?int
    {
        return $this->tiffOrientation(
            str_starts_with($payload, "Exif\x00\x00") ? substr($payload, 6) : $payload,
        );
    }

    private function tiffOrientation(string $tiff): ?int
    {
        $byteOrder = substr($tiff, 0, 2);

        if ($byteOrder !== 'II' && $byteOrder !== 'MM') {
            return null;
        }

        $littleEndian = $byteOrder === 'II';

        if ($this->readUint16($tiff, 2, $littleEndian) !== 42) {
            return null;
        }

        $ifdOffset = $this->readUint32($tiff, 4, $littleEndian);

        if ($ifdOffset === null) {
            return null;
        }

        $entryCount = $this->readUint16($tiff, $ifdOffset, $littleEndian);

        if ($entryCount === null) {
            return null;
        }

        for ($index = 0; $index < $entryCount; $index++) {
            $entryOffset = $ifdOffset + 2 + $index * 12;

            if ($this->readUint16($tiff, $entryOffset, $littleEndian) !== 0x0112) {
                continue;
            }

            if ($this->readUint16($tiff, $entryOffset + 2, $littleEndian) !== 3) {
                return null;
            }

            $orientation = $this->readUint16($tiff, $entryOffset + 8, $littleEndian);

            return ($orientation !== null && $orientation >= 1 && $orientation <= 8) ? $orientation : null;
        }

        return null;
    }

    private function minimalOrientationTiff(int $orientation): string
    {
        return 'II'
            ."\x2A\x00"
            .pack('V', 8)
            .pack('v', 1)
            .pack('v', 0x0112)
            .pack('v', 3)
            .pack('V', 1)
            .pack('v', $orientation)."\x00\x00"
            .pack('V', 0);
    }

    private function readUint16(string $bytes, int $offset, bool $littleEndian): ?int
    {
        if ($offset < 0 || $offset + 2 > strlen($bytes)) {
            return null;
        }

        return unpack($littleEndian ? 'v' : 'n', substr($bytes, $offset, 2))[1] ?? null;
    }

    private function readUint32(string $bytes, int $offset, bool $littleEndian): ?int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            return null;
        }

        return unpack($littleEndian ? 'V' : 'N', substr($bytes, $offset, 4))[1] ?? null;
    }

    private function assertSameRasterShape(ValidatedImage $image, string $stripped): void
    {
        $dimensions = @getimagesizefromstring($stripped);
        $width = is_array($dimensions) ? ($dimensions[0] ?? null) : null;
        $height = is_array($dimensions) ? ($dimensions[1] ?? null) : null;
        $mimeType = is_array($dimensions) ? ($dimensions['mime'] ?? null) : null;

        $sameShape = ($width === $image->width && $height === $image->height)
            || ($width === $image->height && $height === $image->width);

        if (! is_array($dimensions) || ! $sameShape || $mimeType !== $image->mimeType) {
            throw new InvalidArgumentException('The metadata-stripped raster no longer matches its source shape.');
        }
    }
}
