<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAcquisitionDisposition;
use App\Enums\MediaAcquisitionFilenameStrategy;
use App\Filament\Pages\AdminUxSettings as AdminUxSettingsPage;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaProviderBinding;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Media\StorageImageCandidateBrowser;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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
    Storage::fake('public_assets');
});

function acquisitionFixture(string $name = 'valid.jpg.base64'): string
{
    $encoded = file_get_contents(base_path("tests/Fixtures/media/{$name}"));

    return base64_decode(trim((string) $encoded), true)
        ?: throw new RuntimeException("Invalid fixture [{$name}].");
}

it('admits pixel-preserved bytes with admission metadata stripped and creates the curator asset kernel atomically', function (): void {
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

    $stored = Storage::disk('public')->get($media->path);

    expect(substr($stored, (int) strpos($stored, "\xFF\xDA")))
        ->toBe(substr($source, (int) strpos($source, "\xFF\xDA")))
        ->and($stored)->not->toContain('CREATOR: gd-jpeg')
        ->and($media->size)->toBe(strlen($stored));
});

it('supports cleaned original collision safe naming without losing original metadata', function (): void {
    $settings = app(AdminUxSettings::class);
    $settings->media_acquisition_filename_strategy = MediaAcquisitionFilenameStrategy::CleanedOriginal;
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

    $result = app(MediaAcquisitionManager::class)->acquireUploads(
        [$valid, $invalid],
        ImageUploadPurpose::ContentGroupCover,
        User::factory()->admin()->create(),
    );

    expect($result->successful)->toHaveCount(0)
        ->and($result->admittedIndexes)->toBe([])
        ->and($result->nothingAdmittedForInvalidFiles())->toBeTrue()
        ->and($result->failed->pluck('index')->all())->toBe([1])
        ->and($result->failed->sole()['reason'])->toBe('validation')
        ->and($result->notAttemptedIndexes)->toBe([0])
        ->and(Media::query()->count())->toBe(0)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('returns permanent successes and stops after a later operational batch failure', function (): void {
    $bindingAttempts = 0;

    Event::listen('eloquent.creating: '.MediaProviderBinding::class, function () use (&$bindingAttempts): void {
        $bindingAttempts++;

        if ($bindingAttempts === 2) {
            throw new RuntimeException('second binding failed');
        }
    });

    $result = app(MediaAcquisitionManager::class)->acquireUploads(
        [
            UploadedFile::fake()->createWithContent('first.jpg', acquisitionFixture()),
            UploadedFile::fake()->createWithContent('second.png', acquisitionFixture('valid.png.base64')),
            UploadedFile::fake()->createWithContent('third.jpg', acquisitionFixture()),
        ],
        ImageUploadPurpose::ContentGroupCover,
        User::factory()->admin()->create(),
    );

    expect($result->successful)->toHaveCount(1)
        ->and($result->failedCount)->toBe(1)
        ->and($result->notAttemptedCount)->toBe(1)
        ->and($result->unsuccessfulCount())->toBe(2)
        ->and($result->successful->sole()->disposition)->toBe(MediaAcquisitionDisposition::Created)
        ->and(Media::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(1);
});

it('does not convert programming errors into a normal partial batch result', function (): void {
    $bindingAttempts = 0;

    Event::listen('eloquent.creating: '.MediaProviderBinding::class, function () use (&$bindingAttempts): void {
        $bindingAttempts++;

        if ($bindingAttempts === 2) {
            throw new TypeError('programming failure');
        }
    });

    expect(fn () => app(MediaAcquisitionManager::class)->acquireUploads(
        [
            UploadedFile::fake()->createWithContent('first.jpg', acquisitionFixture()),
            UploadedFile::fake()->createWithContent('second.png', acquisitionFixture('valid.png.base64')),
        ],
        ImageUploadPurpose::ContentGroupCover,
        User::factory()->admin()->create(),
    ))->toThrow(TypeError::class, 'programming failure');

    expect(Media::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(1);
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
        ->and($settings->media_acquisition_filename_strategy)->toBe(MediaAcquisitionFilenameStrategy::CleanedOriginal);
});

it('recursively browses only the bounded Laravel public disk and public images source', function (): void {
    config()->set('filesystems.disks.public_assets', [
        'driver' => 'local',
        'root' => public_path(),
        'throw' => false,
        'report' => false,
    ]);
    config()->set('media.acquisition.storage_sources', [
        'laravel_public' => [
            'disk' => 'public',
            'root' => '',
            'mode' => 'register',
            'label' => ['en' => 'Laravel public disk', 'he' => 'דיסק Laravel הציבורי'],
        ],
        'public_images' => [
            'disk' => 'public_assets',
            'root' => 'images',
            'mode' => 'copy',
            'label' => ['en' => 'Public images', 'he' => 'תמונות public'],
        ],
    ]);
    Storage::fake('public_assets');
    Storage::disk('public')->put('root-visible.jpg', acquisitionFixture());
    Storage::disk('public')->put('podcasts/nested/deep-visible.png', acquisitionFixture('valid.png.base64'));
    Storage::disk('public')->put('podcasts/nested/not-image.txt', 'no');
    Storage::disk('public_assets')->put('images/archive/deep-public-image.jpg', acquisitionFixture());
    Storage::disk('public_assets')->put('build/assets/excluded.png', acquisitionFixture('valid.png.base64'));

    $candidates = app(MediaAcquisitionManager::class)->storageCandidates();

    expect($candidates)->toHaveCount(3)
        ->and(array_keys($candidates[0]))->toBe(['token', 'filename', 'source', 'ext', 'size'])
        ->and($candidates[0]['ext'])->toBeIn(['JPG', 'PNG'])
        ->and($candidates[0]['size'])->toBeGreaterThan(0)
        ->and(collect($candidates)->pluck('filename')->all())->toEqualCanonicalizing([
            'root-visible.jpg',
            'deep-visible.png',
            'deep-public-image.jpg',
        ])
        ->and(collect($candidates)->pluck('token')->implode(' '))
        ->not->toContain('podcasts')
        ->not->toContain('images/archive')
        ->and($candidates[0])->not->toHaveKey('path')
        ->and(app(MediaAcquisitionManager::class)->storageCandidates('deep-public'))
        ->toHaveCount(1)
        ->and(app(MediaAcquisitionManager::class)->storageCandidates('deep-public')[0]['filename'])
        ->toBe('deep-public-image.jpg');

    $forgedOutsideRoot = Crypt::encryptString(json_encode([
        'source' => 'public_images',
        'path' => 'build/assets/excluded.png',
    ], JSON_THROW_ON_ERROR));

    expect(fn () => app(StorageImageCandidateBrowser::class)->resolve($forgedOutsideRoot))
        ->toThrow(InvalidArgumentException::class);
});

it('caps recursive Storage results and examined entries', function (): void {
    config()->set('media.acquisition.storage_candidate_limit', 2);
    config()->set('media.acquisition.storage_traversal_limit', 30);
    config()->set('media.acquisition.storage_directory_limit', 12);
    config()->set('media.acquisition.storage_sources', [
        'laravel_public' => [
            'disk' => 'public',
            'root' => '',
            'mode' => 'register',
            'label' => ['en' => 'Laravel public disk', 'he' => 'דיסק Laravel הציבורי'],
        ],
    ]);

    foreach (range(1, 8) as $index) {
        Storage::disk('public')->put("nested/{$index}/candidate-{$index}.jpg", acquisitionFixture());
    }

    expect(app(MediaAcquisitionManager::class)->storageCandidates())
        ->toHaveCount(2);
});

it('stops recursive Storage discovery at its entry and directory budgets', function (): void {
    config()->set('media.acquisition.storage_candidate_limit', 50);
    config()->set('media.acquisition.storage_traversal_limit', 1);
    config()->set('media.acquisition.storage_directory_limit', 12);
    config()->set('media.acquisition.storage_sources', [
        'laravel_public' => [
            'disk' => 'public',
            'root' => '',
            'mode' => 'register',
            'label' => ['en' => 'Laravel public disk', 'he' => 'דיסק Laravel הציבורי'],
        ],
    ]);
    Storage::disk('public')->put('a-not-image.txt', 'not an image');
    Storage::disk('public')->put('z-later-image.jpg', acquisitionFixture());

    expect(app(MediaAcquisitionManager::class)->storageCandidates())->toBe([]);

    Storage::fake('public');
    config()->set('media.acquisition.storage_traversal_limit', 30);
    config()->set('media.acquisition.storage_directory_limit', 1);
    Storage::disk('public')->put('nested/never-visited.jpg', acquisitionFixture());

    expect(app(MediaAcquisitionManager::class)->storageCandidates())->toBe([]);
});

it('registers a safe public Storage raster in place and reuses its Media row', function (): void {
    $actor = User::factory()->admin()->create();
    Storage::disk('public')->put('media-imports/in-place.jpg', acquisitionFixture());
    $token = app(MediaAcquisitionManager::class)->storageCandidates()[0]['token'];

    $registered = app(MediaAcquisitionManager::class)->acquireStorageCandidate(
        $token,
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $reused = app(MediaAcquisitionManager::class)->acquireStorageCandidate(
        $token,
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );

    expect($registered->media->path)->toBe('media-imports/in-place.jpg')
        ->and($registered->disposition)->toBe(MediaAcquisitionDisposition::Registered)
        ->and($reused->media->is($registered->media))->toBeTrue()
        ->and($reused->disposition)->toBe(MediaAcquisitionDisposition::Reused)
        ->and(Media::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1);
    Storage::disk('public')->assertExists('media-imports/in-place.jpg');
});

it('rechecks for a concurrently admitted Storage row inside the candidate lock', function (): void {
    expect(config('media.acquisition.storage_lock_seconds'))->toBe(60);

    $actor = User::factory()->admin()->create();
    Storage::disk('public')->put('media-imports/concurrent.jpg', acquisitionFixture());
    $realBrowser = app(StorageImageCandidateBrowser::class);
    $token = $realBrowser->browse()[0]['token'];
    $candidate = $realBrowser->resolve($token);
    $existing = null;
    $resolveCount = 0;
    $browser = Mockery::mock(StorageImageCandidateBrowser::class);
    $browser->shouldReceive('resolve')
        ->twice()
        ->with($token)
        ->andReturnUsing(function () use (&$existing, &$resolveCount, $candidate) {
            $resolveCount++;

            if ($resolveCount === 2) {
                $existing = Media::factory()->create([
                    'disk' => $candidate->disk,
                    'directory' => dirname($candidate->path),
                    'path' => $candidate->path,
                    'name' => pathinfo($candidate->path, PATHINFO_FILENAME),
                ]);
                $asset = MediaAsset::query()->create([
                    'reference_key' => $existing->reference_key,
                ]);
                MediaProviderBinding::query()->create([
                    'media_asset_id' => $asset->getKey(),
                    'provider' => 'curator',
                    'provider_record_key' => (string) $existing->getKey(),
                ]);
            }

            return $candidate;
        });
    app()->instance(StorageImageCandidateBrowser::class, $browser);
    app()->forgetInstance(MediaAcquisitionManager::class);

    $result = app(MediaAcquisitionManager::class)->acquireStorageCandidate(
        $token,
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );

    expect($result->disposition)->toBe(MediaAcquisitionDisposition::Reused)
        ->and($result->media->is($existing))->toBeTrue()
        ->and($resolveCount)->toBe(2)
        ->and(Media::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1);
    Storage::disk('public')->assertExists('media-imports/concurrent.jpg');
});

it('fails safely when the bounded Storage candidate lock is contended', function (): void {
    Storage::disk('public')->put('media-imports/contended.jpg', acquisitionFixture());
    $token = app(MediaAcquisitionManager::class)->storageCandidates()[0]['token'];
    $key = 'media-acquisition:storage:'.hash('sha256', "public\0media-imports/contended.jpg");
    $lock = Cache::lock($key, 30);

    expect($lock->get())->toBeTrue();
    config()->set('media.acquisition.storage_lock_wait_seconds', 0);

    try {
        expect(fn () => app(MediaAcquisitionManager::class)->acquireStorageCandidate(
            $token,
            ImageUploadPurpose::ContentGroupCover,
            User::factory()->admin()->create(),
        ))->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }

    expect(Media::query()->count())->toBe(0)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(MediaProviderBinding::query()->count())->toBe(0);
    Storage::disk('public')->assertExists('media-imports/contended.jpg');
});

it('copies public images input and sanitized public SVG without changing either source', function (): void {
    $actor = User::factory()->admin()->create();
    $raster = acquisitionFixture();
    $svg = (string) file_get_contents(base_path('tests/Fixtures/media/clean.svg'));
    Storage::disk('public_assets')->put('images/private.jpg', $raster);
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

    $copiedRaster = (string) Storage::disk('public')->get($privateMedia->media->path);

    expect($privateMedia->disposition)->toBe(MediaAcquisitionDisposition::Copied)
        ->and($svgMedia->disposition)->toBe(MediaAcquisitionDisposition::Copied)
        ->and($privateMedia->media->path)->toStartWith('content-groups/covers/')
        ->and($svgMedia->media->path)->toStartWith('content-groups/covers/')
        ->and($svgMedia->media->path)->not->toBe('media-imports/public.svg')
        ->and(substr($copiedRaster, (int) strpos($copiedRaster, "\xFF\xDA")))
        ->toBe(substr($raster, (int) strpos($raster, "\xFF\xDA")))
        ->and($copiedRaster)->not->toContain('CREATOR: gd-jpeg')
        ->and(Storage::disk('public')->get($svgMedia->media->path))->toContain('<svg');
    Storage::disk('public_assets')->assertExists('images/private.jpg');
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
