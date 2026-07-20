<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

function curatorG1LegacyPng(): string
{
    $encoded = file_get_contents(base_path('tests/Fixtures/media/valid.png.base64'));

    return is_string($encoded)
        ? (base64_decode(trim($encoded), true) ?: throw new RuntimeException('Invalid legacy PNG fixture.'))
        : throw new RuntimeException('Missing legacy PNG fixture.');
}

function saveCuratorG1RegistrationSetting(string $name, array $payload, bool $locked = false): void
{
    DB::table('settings')->updateOrInsert(
        ['group' => PublicContentSettings::group(), 'name' => $name],
        [
            'locked' => $locked,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

it('dry-runs then atomically registers one reviewed legacy asset for shared owners', function (): void {
    $actor = User::factory()->admin()->create();
    $sourcePath = 'content-groups/covers/editorial-cover.png';
    $source = curatorG1LegacyPng();
    Storage::disk('public')->put($sourcePath, $source, 'public');
    $first = ContentGroup::factory()->create(['cover_path' => $sourcePath]);
    $second = ContentGroup::factory()->create(['cover_path' => $sourcePath]);

    $this->artisan('media:register-existing-curator-assets', ['--path' => [$sourcePath]])
        ->expectsOutputToContain('1 eligible')
        ->assertSuccessful();

    expect(Media::query()->count())->toBe(0)
        ->and(MediaAttachment::query()->count())->toBe(0);
    Storage::disk('public')->assertExists($sourcePath);

    $this->artisan('media:register-existing-curator-assets', [
        '--apply' => true,
        '--actor' => (string) $actor->getKey(),
        '--path' => [$sourcePath],
    ])->expectsOutputToContain('Registered media')->assertSuccessful();

    $media = Media::query()->sole();
    $operation = MediaMutationOperation::query()->sole();
    expect($media->path)->not->toBe($sourcePath)
        ->and($media->path)->toStartWith(ImageUploadPurpose::ContentGroupCover->root().'/')
        ->and($media->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($first->refresh()->cover_path)->toBe($media->path)
        ->and($second->refresh()->cover_path)->toBe($media->path)
        ->and(MediaAttachment::query()->where('media_id', $media->getKey())->count())->toBe(2)
        ->and(MediaAttachment::query()->pluck('role')->unique()->all())->toBe([MediaAttachmentRole::Cover])
        ->and($operation->operation)->toBe(MediaMutationOperationType::Registration)
        ->and($operation->status)->toBe(MediaMutationStatus::Completed)
        ->and($operation->source_sha256)->toBe(hash('sha256', $source))
        ->and($operation->destination_sha256)->toBe(hash('sha256', Storage::disk('public')->get($media->path)));
    Storage::disk('public')->assertMissing($sourcePath);
    Storage::disk('public')->assertExists($media->path);
    Storage::disk('local')->assertExists((string) $operation->quarantine_path);

    $this->artisan('media:register-existing-curator-assets', [
        '--apply' => true,
        '--actor' => (string) $actor->getKey(),
        '--path' => [$sourcePath],
    ])->expectsOutputToContain('Already registered')->assertSuccessful();
    expect(Media::query()->count())->toBe(1)
        ->and(MediaAttachment::query()->count())->toBe(2)
        ->and(MediaMutationOperation::query()->count())->toBe(1);

    Storage::disk('public')->put($sourcePath, $source, 'public');
    $laterOwner = ContentGroup::factory()->create(['cover_path' => $sourcePath]);
    $this->artisan('media:register-existing-curator-assets', ['--path' => [$sourcePath]])
        ->expectsOutputToContain('1 eligible')
        ->assertSuccessful();
    $this->artisan('media:register-existing-curator-assets', [
        '--apply' => true,
        '--actor' => (string) $actor->getKey(),
        '--path' => [$sourcePath],
    ])->expectsOutputToContain('Registered media')->assertSuccessful();

    expect(Media::query()->count())->toBe(2)
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::Registration)->count())->toBe(2)
        ->and($laterOwner->refresh()->cover_path)->not->toBe($sourcePath)
        ->and($laterOwner->coverMediaAttachment()->exists())->toBeTrue();
});

it('switches settings identity to the immutable key and generated path in the same registration', function (): void {
    config()->set('settings.cache.enabled', true);
    config()->set('settings.cache.store', 'array');
    $actor = User::factory()->admin()->create();
    $sourcePath = 'default-images/editorial-default.png';
    Storage::disk('public')->put($sourcePath, curatorG1LegacyPng(), 'public');
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global'] = [
        'mode' => 'custom',
        'path' => $sourcePath,
        'media_reference_key' => null,
    ];
    saveCuratorG1RegistrationSetting('default_images', $defaults);
    expect(app(PublicContentSettings::class)->default_images['global']['path'])->toBe($sourcePath);

    $this->artisan('media:register-existing-curator-assets', [
        '--apply' => true,
        '--actor' => (string) $actor->getKey(),
        '--path' => [$sourcePath],
    ])->assertSuccessful();

    $media = Media::query()->sole();
    $stored = json_decode((string) DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'default_images')
        ->value('payload'), true, flags: JSON_THROW_ON_ERROR);

    expect($stored['global']['path'])->toBe($media->path)
        ->and($stored['global']['media_reference_key'])->toBe($media->reference_key)
        ->and(app(PublicContentSettings::class)->default_images['global']['path'])->toBe($media->path)
        ->and(app(PublicContentSettings::class)->default_images['global']['media_reference_key'])->toBe($media->reference_key);
    Storage::disk('public')->assertMissing($sourcePath);
});

it('requires one exact path and an authorized actor and compensates locked settings', function (): void {
    $moderator = User::factory()->moderator()->create();
    $admin = User::factory()->admin()->create();
    $sourcePath = 'default-images/locked-default.png';
    Storage::disk('public')->put($sourcePath, curatorG1LegacyPng(), 'public');
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global'] = [
        'mode' => 'custom',
        'path' => $sourcePath,
        'media_reference_key' => null,
    ];
    saveCuratorG1RegistrationSetting('default_images', $defaults, locked: true);

    $this->artisan('media:register-existing-curator-assets', ['--apply' => true])
        ->expectsOutputToContain('exactly one --path')
        ->assertFailed();
    $this->artisan('media:register-existing-curator-assets', [
        '--apply' => true,
        '--path' => [$sourcePath],
    ])->expectsOutputToContain('--actor')->assertFailed();
    $this->artisan('media:register-existing-curator-assets', [
        '--apply' => true,
        '--actor' => (string) $moderator->getKey(),
        '--path' => [$sourcePath],
    ])->assertFailed();
    $this->artisan('media:register-existing-curator-assets', [
        '--apply' => true,
        '--actor' => (string) $admin->getKey(),
        '--path' => [$sourcePath],
    ])->expectsOutputToContain('locked')->assertFailed();

    expect(Media::query()->count())->toBe(0)
        ->and(MediaAttachment::query()->count())->toBe(0)
        ->and(MediaMutationOperation::query()->count())->toBe(0);
    Storage::disk('public')->assertExists($sourcePath);
});
