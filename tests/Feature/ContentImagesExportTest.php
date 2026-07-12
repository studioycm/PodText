<?php

use App\Enums\MediaNamingStrategy;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Jobs\DownloadExternalContentItemImage;
use App\Jobs\ExportContentImagesZip;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\Media\ContentImagesExportManager;
use Awcodes\Curator\Models\Media;
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
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lpO2ygAAAABJRU5ErkJggg==',
        true,
    ) ?: '';
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
    ContentItem::factory()->for($group)->create([
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

    $result = app(ContentImagesExportManager::class)->build(
        (int) $user->getKey(),
        null,
        MediaNamingStrategy::SlugKey,
    );

    Storage::disk('local')->assertExists($result['path']);

    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($result['path'])))->toBeTrue()
        ->and($zip->getFromName('podcasts/alpha-podcast--01j0000000000000000000001/cover.jpg'))->toBeString()
        ->and($zip->getFromName('podcasts/alpha-podcast--01j0000000000000000000001/episodes/episode-one--01j0000000000000000000002.jpg'))->toBeString()
        ->and($result['included'])->toBe(2)
        ->and($result['skipped'])->toContain('content-items/images/missing.jpg');

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
    Http::fake([
        'https://cdn.example.test/episode.png' => Http::response(imgbPngBytes(), 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $user = User::factory()->create();
    $item = ContentItem::factory()->create([
        'title' => 'Remote Episode',
        'slug' => 'remote-episode',
        'external_thumbnail_url' => 'https://cdn.example.test/episode.png',
    ]);

    (new DownloadExternalContentItemImage(
        contentItemId: (int) $item->getKey(),
        userId: (int) $user->getKey(),
    ))->handle();

    expect($item->refresh()->image_path)->toBe('content-items/images/remote-episode.png')
        ->and(Media::query()->where('path', 'content-items/images/remote-episode.png')->exists())->toBeTrue()
        ->and($user->notifications()->count())->toBe(1);
    Storage::disk('public')->assertExists('content-items/images/remote-episode.png');
});

it('rejects non-HTTPS oversized and non-raster external image downloads', function (): void {
    Storage::fake('public');
    Http::fake([
        'https://cdn.example.test/not-image.txt' => Http::response('not an image', 200, [
            'Content-Type' => 'text/plain',
        ]),
        'https://cdn.example.test/too-large.png' => Http::response(str_repeat('x', 2048 * 1024 + 1), 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

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

    (new DownloadExternalContentItemImage(
        contentItemId: (int) $httpItem->getKey(),
        userId: (int) $user->getKey(),
    ))->handle();
    (new DownloadExternalContentItemImage(
        contentItemId: (int) $textItem->getKey(),
        userId: (int) $user->getKey(),
    ))->handle();
    (new DownloadExternalContentItemImage(
        contentItemId: (int) $oversizedItem->getKey(),
        userId: (int) $user->getKey(),
    ))->handle();

    expect($httpItem->refresh()->image_path)->toBeNull()
        ->and($textItem->refresh()->image_path)->toBeNull()
        ->and($oversizedItem->refresh()->image_path)->toBeNull()
        ->and($user->notifications()->count())->toBe(3);
});
