<?php

use App\Enums\ImageUploadPurpose;
use App\Support\Media\ImageUploadValidator;

function curatorG1Fixture(string $name): string
{
    $path = base_path("tests/Fixtures/media/{$name}");
    $contents = file_get_contents($path);

    if (! is_string($contents)) {
        throw new RuntimeException("Unable to read fixture [{$name}].");
    }

    return str_ends_with($name, '.base64')
        ? (base64_decode(trim($contents), true) ?: throw new RuntimeException("Invalid base64 fixture [{$name}]."))
        : $contents;
}

it('accepts each allowed raster format without changing its bytes', function (string $fixture, string $filename, string $mime, string $extension): void {
    $source = curatorG1Fixture($fixture);
    $validated = app(ImageUploadValidator::class)->validateAdmissionBytes(
        $source,
        $filename,
        ImageUploadPurpose::ContentGroupCover,
    );

    expect($validated->mimeType)->toBe($mime)
        ->and($validated->extension)->toBe($extension)
        ->and($validated->contents)->toBe($source)
        ->and($validated->displayFilename)->toBe($filename)
        ->and($validated->width)->toBe(2)
        ->and($validated->height)->toBe(2)
        ->and($validated->sha256)->toBe(hash('sha256', $validated->contents));
})->with([
    'jpeg' => ['valid.jpg.base64', 'photo.jpeg', 'image/jpeg', 'jpg'],
    'png' => ['valid.png.base64', 'photo.png', 'image/png', 'png'],
    'webp' => ['valid.webp.base64', 'photo.webp', 'image/webp', 'webp'],
]);

it('accepts sanitized svg for every image purpose', function (ImageUploadPurpose $purpose): void {
    $contents = curatorG1Fixture('clean.svg');
    $validated = app(ImageUploadValidator::class)->validateAdmissionBytes(
        $contents,
        'cover.svg',
        $purpose,
    );

    expect($validated->mimeType)->toBe('image/svg+xml')
        ->and($validated->extension)->toBe('svg')
        ->and($validated->isSvg())->toBeTrue()
        ->and($validated->contents)->toContain('<svg');
})->with(ImageUploadPurpose::cases());

it('rejects every excluded client file family', function (string $filename, string $contents): void {
    expect(fn () => app(ImageUploadValidator::class)->validateAdmissionBytes(
        $contents,
        $filename,
        ImageUploadPurpose::HeaderLogo,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'php' => ['shell.php', '<?php echo 1;'],
    'php alternate' => ['shell.phtml', '<?php echo 1;'],
    'script' => ['task.py', 'print(1)'],
    'shell' => ['task.sh', '#!/bin/sh'],
    'javascript' => ['app.js', 'alert(1)'],
    'json' => ['data.json', '{}'],
    'html' => ['page.html', '<html></html>'],
    'css' => ['style.css', 'body{}'],
    'xml' => ['data.xml', '<root/>'],
    'pdf' => ['doc.pdf', '%PDF-1.7'],
    'docx' => ['doc.docx', "PK\x03\x04"],
    'markdown' => ['readme.md', '# title'],
    'plain text' => ['notes.txt', 'notes'],
    'audio' => ['sound.mp3', 'ID3'],
    'video' => ['movie.mp4', "\x00\x00\x00\x18ftypmp42"],
    'archive' => ['files.zip', "PK\x03\x04"],
    'binary' => ['program.bin', "\x00\x01\x02"],
    'flash' => ['movie.swf', 'FWS'],
    'font' => ['font.woff2', 'wOF2'],
    'gif' => ['image.gif', 'GIF89a'],
    'bmp' => ['image.bmp', 'BM'],
    'tiff' => ['image.tiff', "II*\x00"],
    'avif' => ['image.avif', "\x00\x00\x00\x18ftypavif"],
    'ico' => ['image.ico', "\x00\x00\x01\x00"],
]);

it('rejects extension mime mismatches and renamed executables', function (): void {
    $validator = app(ImageUploadValidator::class);

    expect(fn () => $validator->validateAdmissionBytes(
        curatorG1Fixture('valid.png.base64'),
        'photo.jpg',
        ImageUploadPurpose::ContentGroupCover,
    ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $validator->validateAdmissionBytes(
            '<?php echo "owned"; ?>',
            'photo.jpg',
            ImageUploadPurpose::ContentGroupCover,
        ))->toThrow(InvalidArgumentException::class);
});

it('rejects raster polyglots and trailing executable data', function (): void {
    $polyglot = curatorG1Fixture('valid.jpg.base64').'<?php echo "owned"; ?>';

    expect(fn () => app(ImageUploadValidator::class)->validateAdmissionBytes(
        $polyglot,
        'photo.jpg',
        ImageUploadPurpose::ContentGroupCover,
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects malicious svg content', function (string $contents): void {
    expect(fn () => app(ImageUploadValidator::class)->validateAdmissionBytes(
        $contents,
        'logo.svg',
        ImageUploadPurpose::HeaderLogo,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'combined malicious fixture' => fn (): string => curatorG1Fixture('malicious.svg'),
    'script element' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    'event attribute' => '<svg xmlns="http://www.w3.org/2000/svg"><rect onload="alert(1)" /></svg>',
    'foreign object' => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject></svg>',
    'malformed xml' => '<svg xmlns="http://www.w3.org/2000/svg"><path></svg>',
    'doctype and entity' => '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>',
    'external href' => '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.test/a.png"/></svg>',
    'javascript href' => '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><text>x</text></a></svg>',
    'data href' => '<svg xmlns="http://www.w3.org/2000/svg"><image href="data:text/html;base64,WA=="/></svg>',
    'unsafe style' => '<svg xmlns="http://www.w3.org/2000/svg"><rect style="background:url(https://example.test/a)"/></svg>',
]);

it('rejects oversized bytes and excessive dimensions', function (): void {
    $validator = app(ImageUploadValidator::class);

    expect(fn () => $validator->validateAdmissionBytes(
        str_repeat('x', (2048 * 1024) + 1),
        'large.jpg',
        ImageUploadPurpose::ContentGroupCover,
    ))->toThrow(InvalidArgumentException::class);

    $image = imagecreatetruecolor(3001, 1);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    expect($contents)->toBeString()
        ->and(fn () => $validator->validateAdmissionBytes(
            $contents,
            'wide.png',
            ImageUploadPurpose::ContentGroupCover,
        ))->toThrow(InvalidArgumentException::class, 'dimensions are outside');

    $pngChunk = static function (string $type, string $data): string {
        $crc = crc32($type.$data);
        $crc = $crc < 0 ? $crc + 4294967296 : $crc;

        return pack('N', strlen($data)).$type.$data.pack('N', $crc);
    };
    $oversizedHeaderOnly = "\x89PNG\r\n\x1A\n"
        .$pngChunk('IHDR', pack('NNCCCCC', 3001, 3001, 8, 2, 0, 0, 0))
        .$pngChunk('IEND', '');

    expect(fn () => $validator->validateAdmissionBytes(
        $oversizedHeaderOnly,
        'header-only.png',
        ImageUploadPurpose::ContentGroupCover,
    ))->toThrow(InvalidArgumentException::class, 'dimensions are outside');
});
