<?php

use App\Enums\MediaNamingStrategy;
use App\Support\Media\ImageFileNamer;

it('builds storage filenames for every media naming strategy', function (): void {
    expect(ImageFileNamer::storageFileName('שלום-עולם', '01HXYZ', 'image/jpeg', MediaNamingStrategy::Slug))
        ->toBe('שלום-עולם.jpg')
        ->and(ImageFileNamer::storageFileName('שלום-עולם', '01HXYZ', 'image/png', MediaNamingStrategy::ReferenceKey))
        ->toBe('01hxyz.png')
        ->and(ImageFileNamer::storageFileName('שלום-עולם', '01HXYZ', 'image/webp', MediaNamingStrategy::SlugKey))
        ->toBe('שלום-עולם--01hxyz.webp');
});

it('falls back to the reference key when the slug is empty', function (): void {
    expect(ImageFileNamer::storageFileName('', '01HXYZ', 'image/jpeg', MediaNamingStrategy::Slug))
        ->toBe('01hxyz.jpg')
        ->and(ImageFileNamer::storageFileName(null, '01HXYZ', 'image/webp', MediaNamingStrategy::SlugKey))
        ->toBe('01hxyz.webp');
});

it('adds deterministic collision suffixes before the extension', function (): void {
    $existing = ['show.jpg', 'show-2.jpg'];

    $name = ImageFileNamer::storageFileName(
        'show',
        '01HXYZ',
        'image/jpeg',
        MediaNamingStrategy::Slug,
        fn (string $candidate): bool => in_array($candidate, $existing, true),
    );

    expect($name)->toBe('show-3.jpg');
});

it('uses slug and reference key for export filenames regardless of storage strategy', function (): void {
    expect(ImageFileNamer::exportFileName('show', '01HXYZ', 'image/webp'))
        ->toBe('show--01hxyz.webp');
});
