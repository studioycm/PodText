<?php

use App\Enums\ImageUploadPurpose;
use App\Models\User;
use App\Support\Media\MediaAcquisitionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
});

function admissionStripFixture(string $name): string
{
    $encoded = file_get_contents(base_path("tests/Fixtures/media/{$name}"));

    return base64_decode(trim((string) $encoded), true)
        ?: throw new RuntimeException("Invalid fixture [{$name}].");
}

function admissionStripBaseJpeg(): string
{
    $canvas = imagecreatetruecolor(8, 8);
    imagefilledrectangle($canvas, 0, 0, 7, 7, (int) imagecolorallocate($canvas, 200, 30, 30));
    ob_start();
    imagejpeg($canvas, null, 90);

    return (string) ob_get_clean();
}

function admissionStripTiff(int $orientation, bool $withGps): string
{
    $ifd0Count = $withGps ? 2 : 1;
    $gpsOffset = 8 + 2 + $ifd0Count * 12 + 4;

    $tiff = 'II'."\x2A\x00".pack('V', 8).pack('v', $ifd0Count);
    $tiff .= pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation)."\x00\x00";

    if ($withGps) {
        $tiff .= pack('v', 0x8825).pack('v', 4).pack('V', 1).pack('V', $gpsOffset);
    }

    $tiff .= pack('V', 0);

    if ($withGps) {
        $tiff .= pack('v', 1);
        $tiff .= pack('v', 0x0001).pack('v', 2).pack('V', 2)."N\x00\x00\x00";
        $tiff .= pack('V', 0);
    }

    return $tiff;
}

function admissionStripJpegWithExif(int $orientation, bool $withGps = true): string
{
    $jpeg = admissionStripBaseJpeg();
    $payload = "Exif\x00\x00".admissionStripTiff($orientation, $withGps);
    $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
}

/** @return array<int, string> */
function admissionStripJpegMarkers(string $bytes): array
{
    $offset = 2;
    $markers = [];

    while ($offset + 4 <= strlen($bytes)) {
        if ($bytes[$offset] !== "\xFF") {
            break;
        }

        $marker = ord($bytes[$offset + 1]);

        if ($marker === 0xDA) {
            $markers[] = 'SOS';
            break;
        }

        $markers[] = sprintf('%02X', $marker);
        $segmentLength = (ord($bytes[$offset + 2]) << 8) | ord($bytes[$offset + 3]);
        $offset += 2 + $segmentLength;
    }

    return $markers;
}

/** @return array<string, mixed>|false */
function admissionStripExifData(string $bytes): array|false
{
    $stream = fopen('php://temp', 'r+b');
    fwrite($stream, $bytes);
    rewind($stream);
    $exif = @exif_read_data($stream);
    fclose($stream);

    return $exif;
}

function admissionStripPngChunk(string $type, string $payload): string
{
    $crc = crc32($type.$payload);
    $crc = $crc < 0 ? $crc + 4294967296 : $crc;

    return pack('N', strlen($payload)).$type.$payload.pack('N', $crc);
}

/** @return array<int, string> */
function admissionStripPngChunkTypes(string $bytes): array
{
    $offset = 8;
    $types = [];

    while ($offset + 12 <= strlen($bytes)) {
        $chunkLength = unpack('N', substr($bytes, $offset, 4))[1];
        $types[] = substr($bytes, $offset + 4, 4);
        $offset += 12 + $chunkLength;
    }

    return $types;
}

/** @return array<int, array{type: string, payload: string}> */
function admissionStripWebpChunks(string $bytes): array
{
    $offset = 12;
    $chunks = [];

    while ($offset + 8 <= strlen($bytes)) {
        $type = substr($bytes, $offset, 4);
        $size = unpack('V', substr($bytes, $offset + 4, 4))[1];
        $chunks[] = ['type' => $type, 'payload' => substr($bytes, $offset + 8, $size)];
        $offset += 8 + $size + ($size % 2);
    }

    return $chunks;
}

/** @param array<int, array{type: string, payload: string}> $chunks */
function admissionStripWebpBuild(array $chunks): string
{
    $body = '';

    foreach ($chunks as $chunk) {
        $body .= $chunk['type'].pack('V', strlen($chunk['payload'])).$chunk['payload'];

        if (strlen($chunk['payload']) % 2 === 1) {
            $body .= "\x00";
        }
    }

    return 'RIFF'.pack('V', strlen($body) + 4).'WEBP'.$body;
}

function admissionStripWebpWithExif(int $orientation, bool $withGps = true): string
{
    $source = admissionStripFixture('valid.webp.base64');
    [$width, $height] = getimagesizefromstring($source);
    $vp8x = chr(0x08)."\x00\x00\x00"
        .substr(pack('V', $width - 1), 0, 3)
        .substr(pack('V', $height - 1), 0, 3);

    return admissionStripWebpBuild([
        ['type' => 'VP8X', 'payload' => $vp8x],
        ...admissionStripWebpChunks($source),
        ['type' => 'EXIF', 'payload' => admissionStripTiff($orientation, $withGps)],
    ]);
}

it('strips exif gps and comment segments from admitted jpeg bytes while preserving pixels', function (): void {
    $source = admissionStripJpegWithExif(orientation: 6);
    $sourceExif = admissionStripExifData($source);

    expect($sourceExif)->toBeArray()
        ->and($sourceExif['Orientation'] ?? null)->toBe(6)
        ->and($sourceExif['GPSLatitudeRef'] ?? null)->toBe('N');

    $media = app(MediaAcquisitionManager::class)->acquireBytes(
        contents: $source,
        originalFilename: 'phone-photo.jpg',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: User::factory()->admin()->create(),
    );

    $stored = Storage::disk('public')->get($media->path);
    $storedExif = admissionStripExifData($stored);
    $markers = admissionStripJpegMarkers($stored);

    expect($storedExif['GPSLatitudeRef'] ?? null)->toBeNull()
        ->and($storedExif['Orientation'] ?? null)->toBe(6)
        ->and($markers)->not->toContain('FE')
        ->and($markers)->not->toContain('ED')
        ->and(array_count_values($markers)['E1'] ?? 0)->toBe(1)
        ->and(substr($stored, (int) strpos($stored, "\xFF\xDA")))
        ->toBe(substr($source, (int) strpos($source, "\xFF\xDA")))
        ->and($media->size)->toBe(strlen($stored))
        ->and(getimagesizefromstring($stored)[0])->toBe(8)
        ->and(getimagesizefromstring($stored)[1])->toBe(8);
});

it('drops the exif carrier entirely when the orientation is upright', function (): void {
    $source = admissionStripJpegWithExif(orientation: 1);

    $media = app(MediaAcquisitionManager::class)->acquireBytes(
        contents: $source,
        originalFilename: 'upright.jpg',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: User::factory()->admin()->create(),
    );

    $stored = Storage::disk('public')->get($media->path);
    $markers = admissionStripJpegMarkers($stored);

    expect($markers)->not->toContain('E1')
        ->and($markers)->not->toContain('FE')
        ->and(admissionStripExifData($stored)['Orientation'] ?? null)->toBeNull();
});

it('strips jpeg metadata on the live upload path too', function (): void {
    $source = admissionStripJpegWithExif(orientation: 3);

    $media = app(MediaAcquisitionManager::class)->acquireUpload(
        upload: UploadedFile::fake()->createWithContent('upload.jpg', $source),
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: User::factory()->admin()->create(),
    );

    $stored = Storage::disk('public')->get($media->path);
    $storedExif = admissionStripExifData($stored);

    expect($storedExif['GPSLatitudeRef'] ?? null)->toBeNull()
        ->and($storedExif['Orientation'] ?? null)->toBe(3);
});

it('strips png text and exif chunks while keeping pixel and physical chunks', function (): void {
    $base = admissionStripFixture('valid.png.base64');
    $insertAt = 8 + 12 + 13;
    $source = substr($base, 0, $insertAt)
        .admissionStripPngChunk('tEXt', "Comment\x00secret note")
        .admissionStripPngChunk('eXIf', admissionStripTiff(1, true))
        .substr($base, $insertAt);

    expect(admissionStripPngChunkTypes($source))->toContain('tEXt', 'eXIf');

    $media = app(MediaAcquisitionManager::class)->acquireBytes(
        contents: $source,
        originalFilename: 'annotated.png',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: User::factory()->admin()->create(),
    );

    $stored = Storage::disk('public')->get($media->path);
    $types = admissionStripPngChunkTypes($stored);

    expect($types)->not->toContain('tEXt')
        ->and($types)->not->toContain('eXIf')
        ->and($types)->toContain('pHYs')
        ->and($types)->toContain('IDAT')
        ->and($stored)->toBe($base);
});

it('strips webp exif chunks and clears the vp8x metadata flags', function (): void {
    $source = admissionStripWebpWithExif(orientation: 1);

    $media = app(MediaAcquisitionManager::class)->acquireBytes(
        contents: $source,
        originalFilename: 'annotated.webp',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: User::factory()->admin()->create(),
    );

    $stored = Storage::disk('public')->get($media->path);
    $chunks = collect(admissionStripWebpChunks($stored));

    expect($chunks->pluck('type')->all())->not->toContain('EXIF')
        ->and($chunks->pluck('type')->all())->toContain('VP8 ')
        ->and(ord($chunks->firstWhere('type', 'VP8X')['payload'][0]) & 0x0C)->toBe(0)
        ->and(getimagesizefromstring($stored))->not->toBeFalse();
});

it('keeps webp orientation through a minimal exif chunk', function (): void {
    $source = admissionStripWebpWithExif(orientation: 6);

    $media = app(MediaAcquisitionManager::class)->acquireBytes(
        contents: $source,
        originalFilename: 'rotated.webp',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: User::factory()->admin()->create(),
    );

    $stored = Storage::disk('public')->get($media->path);
    $chunks = collect(admissionStripWebpChunks($stored));
    $exifChunk = $chunks->firstWhere('type', 'EXIF');

    expect($exifChunk)->not->toBeNull()
        ->and($exifChunk['payload'])->toBe(admissionStripTiff(6, false))
        ->and(ord($chunks->firstWhere('type', 'VP8X')['payload'][0]) & 0x08)->toBe(0x08)
        ->and(ord($chunks->firstWhere('type', 'VP8X')['payload'][0]) & 0x04)->toBe(0);
});

it('admits metadata-free rasters byte-verbatim', function (): void {
    $png = admissionStripFixture('valid.png.base64');
    $webp = admissionStripFixture('valid.webp.base64');
    $actor = User::factory()->admin()->create();

    $pngMedia = app(MediaAcquisitionManager::class)->acquireBytes(
        contents: $png,
        originalFilename: 'plain.png',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: $actor,
    );
    $webpMedia = app(MediaAcquisitionManager::class)->acquireBytes(
        contents: $webp,
        originalFilename: 'plain.webp',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: $actor,
    );

    expect(Storage::disk('public')->get($pngMedia->path))->toBe($png)
        ->and(Storage::disk('public')->get($webpMedia->path))->toBe($webp);
});
