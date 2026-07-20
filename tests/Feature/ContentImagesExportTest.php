<?php

use App\Enums\MediaAttachmentRole;
use App\Enums\MediaNamingStrategy;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Jobs\DownloadExternalContentItemImage;
use App\Jobs\ExportContentImagesZip;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Support\Media\ContentImagesExportManager;
use App\Support\Media\ExternalImageDnsResolver;
use App\Support\Media\SafeExternalImageFetcher;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    $this->mock(ExternalImageDnsResolver::class)
        ->shouldReceive('addresses')
        ->andReturn(['93.184.216.34']);
});

function imgbStoreImage(string $path): void
{
    UploadedFile::fake()
        ->image(basename($path), 40, 40)
        ->storeAs(trim(dirname($path), '.'), basename($path), 'public');
}

function imgbPngBytes(): string
{
    return base64_decode(
        trim(file_get_contents(base_path('tests/Fixtures/media/valid.png.base64')) ?: ''),
        true,
    ) ?: '';
}

function imgbFixture(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/content-images/{$name}")) ?: '';
}

it('builds content image zip files with egress naming and skipped-file reporting', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    imgbStoreImage('content-groups/covers/cover.jpg');
    imgbStoreImage('content-items/images/episode.jpg');

    $user = User::factory()->create();
    $group = ContentGroup::factory()->create([
        'reference_key' => '01J0000000000000000000001',
        'title' => 'Alpha Podcast',
        'slug' => 'alpha-podcast',
        'cover_path' => 'content-groups/covers/cover.jpg',
    ]);
    $item = ContentItem::factory()->for($group)->create([
        'reference_key' => '01J0000000000000000000002',
        'title' => 'Episode One',
        'slug' => 'episode-one',
        'image_path' => 'content-items/images/episode.jpg',
    ]);
    ContentItem::factory()->for($group)->create([
        'reference_key' => '01J0000000000000000000003',
        'title' => 'Missing Episode',
        'slug' => 'missing-episode',
        'image_path' => 'content-items/images/missing.jpg',
    ]);
    $coverMedia = Media::factory()->create([
        'disk' => 'public',
        'directory' => 'content-groups/covers',
        'visibility' => 'public',
        'name' => 'cover',
        'path' => 'content-groups/covers/cover.jpg',
        'width' => 40,
        'height' => 40,
        'size' => Storage::disk('public')->size('content-groups/covers/cover.jpg'),
        'type' => 'image/jpeg',
        'ext' => 'jpg',
    ]);
    $itemMedia = Media::factory()->create([
        'disk' => 'public',
        'directory' => 'content-items/images',
        'visibility' => 'public',
        'name' => 'episode',
        'path' => 'content-items/images/episode.jpg',
        'width' => 40,
        'height' => 40,
        'size' => Storage::disk('public')->size('content-items/images/episode.jpg'),
        'type' => 'image/jpeg',
        'ext' => 'jpg',
    ]);
    MediaAttachment::query()->create([
        'media_id' => $coverMedia->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);
    MediaAttachment::query()->create([
        'media_id' => $itemMedia->getKey(),
        'attachable_type' => 'content_item',
        'attachable_id' => $item->getKey(),
        'role' => MediaAttachmentRole::PrimaryImage,
        'position' => 0,
    ]);

    $result = app(ContentImagesExportManager::class)->build(
        (int) $user->getKey(),
        null,
        MediaNamingStrategy::SlugKey,
    );

    Storage::disk('local')->assertExists($result['path']);

    $zip = new ZipArchive;
    $coverEntry = 'podcasts/alpha-podcast--01j0000000000000000000001/cover.jpg';
    $itemEntry = 'podcasts/alpha-podcast--01j0000000000000000000001/episodes/episode-one--01j0000000000000000000002.jpg';
    expect($zip->open(Storage::disk('local')->path($result['path'])))->toBeTrue()
        ->and($zip->getFromName($coverEntry))->toBeString()
        ->and($zip->getFromName($itemEntry))->toBeString()
        ->and($result['included'])->toBe(2)
        ->and($result['skipped'])->toContain('content_item:01J0000000000000000000003:primary_image:legacy-only');
    $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $coverManifest = collect($manifest['media'])->firstWhere('role', MediaAttachmentRole::Cover->value);
    $itemManifest = collect($manifest['media'])->firstWhere('role', MediaAttachmentRole::PrimaryImage->value);

    expect($manifest['schema_version'])->toBe(1)
        ->and($manifest['media'])->toHaveCount(2)
        ->and($coverManifest)->toMatchArray([
            'media_reference_key' => $coverMedia->reference_key,
            'owner_reference_key' => $group->reference_key,
            'role' => MediaAttachmentRole::Cover->value,
            'archive_filename' => $coverEntry,
            'validated_type' => 'image/jpeg',
            'extension' => 'jpg',
            'sha256' => hash('sha256', (string) $zip->getFromName($coverEntry)),
        ])
        ->and($itemManifest)->toMatchArray([
            'media_reference_key' => $itemMedia->reference_key,
            'owner_reference_key' => $item->reference_key,
            'role' => MediaAttachmentRole::PrimaryImage->value,
            'archive_filename' => $itemEntry,
            'validated_type' => 'image/jpeg',
            'extension' => 'jpg',
            'sha256' => hash('sha256', (string) $zip->getFromName($itemEntry)),
        ]);

    $zip->close();
});

it('queues content image zip exports from header and record actions with selected naming strategy', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $group = ContentGroup::factory()->create();

    $this->actingAs($user);

    Livewire::test(ListContentGroups::class)
        ->callAction(TestAction::make('downloadContentImages')->table(), [
            'media_naming_strategy' => MediaNamingStrategy::ReferenceKey->value,
        ])
        ->assertActionVisible(TestAction::make('downloadPodcastImages')->table($group))
        ->callAction(TestAction::make('downloadPodcastImages')->table($group), [
            'media_naming_strategy' => MediaNamingStrategy::Slug->value,
        ]);

    Queue::assertPushed(
        ExportContentImagesZip::class,
        fn (ExportContentImagesZip $job): bool => $job->userId === $user->id
            && $job->contentGroupId === null
            && $job->strategy === MediaNamingStrategy::ReferenceKey->value
            && $job->queue === 'imports-exports',
    );
    Queue::assertPushed(
        ExportContentImagesZip::class,
        fn (ExportContentImagesZip $job): bool => $job->userId === $user->id
            && $job->contentGroupId === $group->id
            && $job->strategy === MediaNamingStrategy::Slug->value
            && $job->queue === 'imports-exports',
    );
});

it('sends a database notification when the queued content image export is ready', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    imgbStoreImage('content-groups/covers/ready.jpg');

    $user = User::factory()->create();
    $group = ContentGroup::factory()->create([
        'cover_path' => 'content-groups/covers/ready.jpg',
    ]);

    (new ExportContentImagesZip(
        userId: (int) $user->getKey(),
        contentGroupId: (int) $group->getKey(),
        strategy: MediaNamingStrategy::Slug->value,
    ))->handle(app(ContentImagesExportManager::class));

    $files = Storage::disk('local')->allFiles('content-images-exports/user-'.$user->id);

    expect($user->notifications()->count())->toBe(1);
    expect($files)->toHaveCount(1);
    Storage::disk('local')->assertExists($files[0]);
});

it('blocks guests and non owners from content image export downloads', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $token = '01JEXPORTTOKEN000000000001';
    $manager = app(ContentImagesExportManager::class);

    Storage::disk('local')->put($manager->pathFor((int) $owner->getKey(), $token), 'zip-bytes');

    $this->get(route('admin.content-images-exports.download', ['token' => $token]))
        ->assertRedirect('/admin/login');

    $this->actingAs($other)
        ->get(route('admin.content-images-exports.download', ['token' => $token]))
        ->assertNotFound();

    Storage::disk('local')->assertExists($manager->pathFor((int) $owner->getKey(), $token));
    Storage::disk('local')->assertMissing($manager->pathFor((int) $other->getKey(), $token));
});

it('downloads valid HTTPS external item images into local episode images', function (): void {
    Storage::fake('public');
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->with('https://cdn.example.test/episode.png')
        ->andReturn(imgbPngBytes());
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    $user = User::factory()->create();
    $item = ContentItem::factory()->create([
        'title' => 'Remote Episode',
        'slug' => 'remote-episode',
        'external_thumbnail_url' => 'https://cdn.example.test/episode.png',
    ]);

    $job = new DownloadExternalContentItemImage(
        contentItemId: (int) $item->getKey(),
        userId: (int) $user->getKey(),
        expectedUrl: (string) $item->external_thumbnail_url,
    );
    app()->call([$job, 'handle']);

    $path = $item->refresh()->image_path;

    expect($path)->toStartWith('content-items/images/')
        ->and(Media::query()->where('path', $path)->exists())->toBeTrue()
        ->and($item->primaryImageMediaAttachment?->media?->path)->toBe($path)
        ->and($user->notifications()->count())->toBe(1);
    Storage::disk('public')->assertExists($path);
});

it('rejects non-HTTPS oversized and non-raster external image downloads', function (): void {
    Storage::fake('public');
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')->once()->with('http://cdn.example.test/episode.png')
        ->andThrow(new InvalidArgumentException('HTTPS is required.'));
    $fetcher->shouldReceive('fetch')->once()->with('https://cdn.example.test/not-image.txt')
        ->andReturn(imgbFixture('not-image.txt'));
    $fetcher->shouldReceive('fetch')->once()->with('https://cdn.example.test/too-large.png')
        ->andReturn(str_repeat(imgbFixture('too-large-seed.txt'), 2048 * 1024 + 1));
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    $user = User::factory()->create();
    $httpItem = ContentItem::factory()->create([
        'external_thumbnail_url' => 'http://cdn.example.test/episode.png',
    ]);
    $textItem = ContentItem::factory()->create([
        'external_thumbnail_url' => 'https://cdn.example.test/not-image.txt',
    ]);
    $oversizedItem = ContentItem::factory()->create([
        'external_thumbnail_url' => 'https://cdn.example.test/too-large.png',
    ]);

    foreach ([$httpItem, $textItem, $oversizedItem] as $item) {
        app()->call([(new DownloadExternalContentItemImage(
            contentItemId: (int) $item->getKey(),
            userId: (int) $user->getKey(),
            expectedUrl: (string) $item->external_thumbnail_url,
        )), 'handle']);
    }

    expect($httpItem->refresh()->image_path)->toBeNull()
        ->and($textItem->refresh()->image_path)->toBeNull()
        ->and($oversizedItem->refresh()->image_path)->toBeNull()
        ->and($user->notifications()->count())->toBe(3);
});
