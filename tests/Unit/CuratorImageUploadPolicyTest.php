<?php

use App\Enums\ImageUploadPurpose;
use App\Support\Media\CuratorImageUploadPolicy;

it('owns the exact positive image union and canonical extensions', function (): void {
    $policy = app(CuratorImageUploadPolicy::class);

    expect($policy->globalMimeTypes())->toBe([
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/svg+xml',
    ])->and($policy->clientExtensions())->toBe([
        'jpg',
        'jpeg',
        'png',
        'webp',
        'svg',
    ])->and($policy->canonicalExtension('image/jpeg'))->toBe('jpg')
        ->and($policy->canonicalExtension('image/png'))->toBe('png')
        ->and($policy->canonicalExtension('image/webp'))->toBe('webp')
        ->and($policy->canonicalExtension('image/svg+xml'))->toBe('svg');
});

it('keeps svg limited to the header purpose', function (): void {
    $policy = app(CuratorImageUploadPolicy::class);

    expect($policy->allowsMime(ImageUploadPurpose::HeaderLogo, 'image/svg+xml'))->toBeTrue()
        ->and($policy->allowsMime(ImageUploadPurpose::ContentGroupCover, 'image/svg+xml'))->toBeFalse()
        ->and($policy->allowsMime(ImageUploadPurpose::ContentItemPrimaryImage, 'image/svg+xml'))->toBeFalse();
});

it('normalizes only exact app owned roots and paths', function (string $value): void {
    expect(fn () => app(CuratorImageUploadPolicy::class)->normalizePath($value))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'traversal' => 'header/../shell.svg',
    'backslash' => 'header\\shell.svg',
    'encoded traversal' => 'header/%2e%2e/shell.svg',
    'absolute' => '/header/logo.svg',
    'double slash' => 'header//logo.svg',
    'sibling prefix' => 'header-backup/logo.svg',
    'nested directory' => 'header/nested/logo.svg',
    'arbitrary root' => 'uploads/logo.svg',
]);

it('generates a ulid filename in the server owned purpose root', function (): void {
    $path = app(CuratorImageUploadPolicy::class)->generatedPath(
        ImageUploadPurpose::ContentGroupCover,
        'image/jpeg',
    );

    expect($path)->toMatch('/^content-groups\/covers\/[0-9A-HJKMNP-TV-Z]{26}\.jpg$/');
});
