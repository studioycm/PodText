<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAcquisitionFilenameStrategy;
use App\Filament\Pages\AdminUxSettings as AdminUxSettingsPage;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaProviderBinding;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Support\Media\MediaAcquisitionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
});

function acquisitionFixture(string $name = 'valid.jpg.base64'): string
{
    $encoded = file_get_contents(base_path("tests/Fixtures/media/{$name}"));

    return base64_decode(trim((string) $encoded), true)
        ?: throw new RuntimeException("Invalid fixture [{$name}].");
}

it('admits preserved bytes and creates the curator asset kernel atomically', function (): void {
    $actor = User::factory()->admin()->create();
    $source = acquisitionFixture();

    $media = app(MediaAcquisitionManager::class)->acquireBytes(
        contents: $source,
        originalFilename: 'Original Photo.jpeg',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: $actor,
    );

    expect($media->reference_key)->toBeString()->toHaveLength(26)
        ->and($media->title)->toBe('Original Photo.jpeg')
        ->and(data_get(json_decode((string) $media->exif, true), 'original_filename'))->toBe('Original Photo.jpeg')
        ->and($media->mediaAsset)->toBeInstanceOf(MediaAsset::class)
        ->and($media->mediaAsset?->reference_key)->toBe($media->reference_key)
        ->and($media->providerBinding)->toBeInstanceOf(MediaProviderBinding::class)
        ->and($media->providerBinding?->provider)->toBe('curator')
        ->and($media->providerBinding?->provider_record_key)->toBe((string) $media->getKey())
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1);

    Storage::disk('public')->assertExists($media->path);
    expect(Storage::disk('public')->get($media->path))->toBe($source);
});

it('supports cleaned original collision safe naming without losing original metadata', function (): void {
    $settings = app(AdminUxSettings::class);
    $settings->media_acquisition_filename_strategy = MediaAcquisitionFilenameStrategy::CleanedOriginal->value;
    $settings->save();

    $media = app(MediaAcquisitionManager::class)->acquireBytes(
        contents: acquisitionFixture(),
        originalFilename: 'My Original Cover.jpeg',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: User::factory()->admin()->create(),
    );

    expect($media->path)->toMatch('#^content-groups/covers/my-original-cover-[0-9A-HJKMNP-TV-Z]{26}\\.jpg$#')
        ->and($media->title)->toBe('My Original Cover.jpeg');
});

it('removes only a newly written destination when atomic database admission fails', function (): void {
    $actor = User::factory()->admin()->create();
    Event::listen('eloquent.creating: '.MediaProviderBinding::class, function (): void {
        throw new RuntimeException('binding failed');
    });

    expect(fn () => app(MediaAcquisitionManager::class)->acquireBytes(
        contents: acquisitionFixture(),
        originalFilename: 'rollback.jpg',
        purpose: ImageUploadPurpose::ContentGroupCover,
        actor: $actor,
    ))->toThrow(RuntimeException::class, 'binding failed');

    expect(Media::query()->count())->toBe(0)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(MediaProviderBinding::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('validates an Upload batch completely before making any item permanent', function (): void {
    $valid = UploadedFile::fake()->createWithContent('valid.jpg', acquisitionFixture());
    $invalid = UploadedFile::fake()->createWithContent('invalid.jpg', '<?php echo 1;');

    expect(fn () => app(MediaAcquisitionManager::class)->acquireUploads(
        [$valid, $invalid],
        ImageUploadPurpose::ContentGroupCover,
        User::factory()->admin()->create(),
    ))->toThrow(InvalidArgumentException::class);

    expect(Media::query()->count())->toBe(0)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('saves the bounded Package 3 acquisition settings', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(AdminUxSettingsPage::class)
        ->fillForm([
            'media_acquisition_max_kilobytes' => 4096,
            'media_acquisition_max_dimension' => 5000,
            'media_acquisition_upload_batch_limit' => 12,
            'media_picker_browse_limit' => 40,
            'media_picker_search_limit' => 80,
            'media_acquisition_filename_strategy' => MediaAcquisitionFilenameStrategy::CleanedOriginal->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(AdminUxSettings::class);
    app(SettingsContainer::class)->clearCache();
    $settings = app(AdminUxSettings::class);

    expect($settings->media_acquisition_max_kilobytes)->toBe(4096)
        ->and($settings->media_acquisition_max_dimension)->toBe(5000)
        ->and($settings->media_acquisition_upload_batch_limit)->toBe(12)
        ->and($settings->media_picker_browse_limit)->toBe(40)
        ->and($settings->media_picker_search_limit)->toBe(80)
        ->and($settings->media_acquisition_filename_strategy)->toBe(MediaAcquisitionFilenameStrategy::CleanedOriginal->value);
});

it('browses configured Storage roots through opaque direct-child identities', function (): void {
    Storage::disk('public')->put('media-imports/visible.jpg', acquisitionFixture());
    Storage::disk('public')->put('media-imports/nested/hidden.jpg', acquisitionFixture());
    Storage::disk('public')->put('outside/hidden.jpg', acquisitionFixture());
    Storage::disk('public')->put('media-imports/not-image.txt', 'no');

    $candidates = app(MediaAcquisitionManager::class)->storageCandidates();

    expect($candidates)->toHaveCount(1)
        ->and(array_keys($candidates[0]))->toBe(['token', 'filename', 'source'])
        ->and($candidates[0]['filename'])->toBe('visible.jpg')
        ->and($candidates[0]['token'])->not->toContain('media-imports')
        ->and($candidates[0])->not->toHaveKey('path');
});

it('registers a safe public Storage raster in place and reuses its Media row', function (): void {
    $actor = User::factory()->admin()->create();
    Storage::disk('public')->put('media-imports/in-place.jpg', acquisitionFixture());
    $token = app(MediaAcquisitionManager::class)->storageCandidates()[0]['token'];

    $media = app(MediaAcquisitionManager::class)->acquireStorageCandidate(
        $token,
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $again = app(MediaAcquisitionManager::class)->acquireStorageCandidate(
        $token,
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );

    expect($media->path)->toBe('media-imports/in-place.jpg')
        ->and($again->is($media))->toBeTrue()
        ->and(Media::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1);
    Storage::disk('public')->assertExists('media-imports/in-place.jpg');
});

it('copies private Storage input and sanitized public SVG without changing either source', function (): void {
    $actor = User::factory()->admin()->create();
    $raster = acquisitionFixture();
    $svg = (string) file_get_contents(base_path('tests/Fixtures/media/clean.svg'));
    Storage::disk('local')->put('media-imports/private.jpg', $raster);
    Storage::disk('public')->put('media-imports/public.svg', $svg);

    $candidates = app(MediaAcquisitionManager::class)->storageCandidates();
    $private = collect($candidates)->firstWhere('filename', 'private.jpg');
    $publicSvg = collect($candidates)->firstWhere('filename', 'public.svg');

    $privateMedia = app(MediaAcquisitionManager::class)->acquireStorageCandidate(
        $private['token'],
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $svgMedia = app(MediaAcquisitionManager::class)->acquireStorageCandidate(
        $publicSvg['token'],
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );

    expect($privateMedia->path)->toStartWith('content-groups/covers/')
        ->and($svgMedia->path)->toStartWith('content-groups/covers/')
        ->and($svgMedia->path)->not->toBe('media-imports/public.svg')
        ->and(Storage::disk('public')->get($privateMedia->path))->toBe($raster)
        ->and(Storage::disk('public')->get($svgMedia->path))->toContain('<svg');
    Storage::disk('local')->assertExists('media-imports/private.jpg');
    Storage::disk('public')->assertExists('media-imports/public.svg');
    expect(Storage::disk('public')->get('media-imports/public.svg'))->toBe($svg);
});

it('rejects a forged Storage candidate identity', function (): void {
    expect(fn () => app(MediaAcquisitionManager::class)->acquireStorageCandidate(
        'client-supplied/path.jpg',
        ImageUploadPurpose::ContentGroupCover,
        User::factory()->admin()->create(),
    ))->toThrow(InvalidArgumentException::class);
});
