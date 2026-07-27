<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Livewire\Admin\MediaPickerPanel;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Support\Media\MediaAttachmentManager;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\SvgUploadSanitizer;
use App\Support\PublicFront\PublicDefaultImageResolver;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
});

function p2Media(string $path, array $overrides = []): Media
{
    return Media::factory()->create(array_merge([
        'disk' => 'public',
        'directory' => dirname($path),
        'visibility' => 'public',
        'name' => pathinfo($path, PATHINFO_FILENAME),
        'path' => $path,
        'width' => 120,
        'height' => 120,
        'size' => 12,
        'type' => 'image/jpeg',
        'ext' => pathinfo($path, PATHINFO_EXTENSION),
    ], $overrides));
}

function p2StoredMedia(string $path, array $overrides = []): Media
{
    $media = p2Media($path, $overrides);
    Storage::disk((string) $media->disk)->put((string) $media->path, 'image-bytes');

    return $media;
}

it('shows every curator row in All Media and uses Needs Attention as a task instead of an exclusion', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $ready = p2StoredMedia('content-groups/covers/'.Str::ulid().'.jpg');
    $private = p2StoredMedia('private-covers/'.Str::ulid().'.jpg', [
        'disk' => 'local',
        'directory' => 'private-covers',
        'visibility' => 'private',
    ]);
    $oddMetadata = p2StoredMedia('legacy/nested/odd.bin', [
        'directory' => 'legacy',
        'name' => 'not-the-path-name',
        'type' => 'application/octet-stream',
        'ext' => 'bin',
        'size' => 0,
        'width' => null,
        'height' => null,
    ]);
    $missing = p2Media('content-items/images/'.Str::ulid().'.jpg');

    expect(app(MediaRecordScope::class)->inventoryQuery()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$ready, $private, $oddMetadata, $missing])->pluck('id')->sort()->values()->all());

    Livewire::test(ListMedia::class)
        ->assertCanSeeTableRecords([$ready, $private, $oddMetadata, $missing])
        ->set('activeTab', 'needs_attention')
        ->assertCanSeeTableRecords([$private, $oddMetadata, $missing])
        ->assertCanNotSeeTableRecords([$ready]);
});

it('runs one exact SVG diagnostic snapshot only after Needs Attention is selected', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $records = collect(range(1, 30))->map(function (int $index): Media {
        $name = (string) Str::ulid();
        $media = p2Media("header/{$name}.svg", [
            'name' => $name,
            'type' => 'image/svg+xml',
            'ext' => 'svg',
            'width' => null,
            'height' => null,
            'size' => 68,
        ]);
        Storage::disk('public')->put(
            $media->path,
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"></svg>',
        );

        return $media;
    });
    $sanitizeCalls = 0;
    $sanitizer = Mockery::mock(SvgUploadSanitizer::class);
    $sanitizer->shouldReceive('sanitize')
        ->andReturnUsing(function (string $contents) use (&$sanitizeCalls): string {
            $sanitizeCalls++;

            return $contents;
        });
    app()->instance(SvgUploadSanitizer::class, $sanitizer);

    $component = Livewire::test(ListMedia::class);

    expect($sanitizeCalls)->toBe(25);

    $component
        ->set('activeTab', 'needs_attention')
        ->assertCanNotSeeTableRecords($records);

    expect($sanitizeCalls)->toBe(30);
});

it('starts the picker in its logical context and All Media clears that filter without mutating existing media', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $context = p2StoredMedia('content-groups/covers/'.Str::ulid().'.gif', [
        'type' => 'image/gif',
        'ext' => 'gif',
        'width' => null,
        'height' => null,
    ]);
    $otherFolder = p2StoredMedia('header/'.Str::ulid().'.jpg');
    $private = p2StoredMedia('private/'.Str::ulid().'.jpg', [
        'disk' => 'local',
        'directory' => 'private',
        'visibility' => 'private',
    ]);
    $beforeRows = Media::query()->orderBy('id')->get()->map->getAttributes()->all();
    $beforePublicFiles = Storage::disk('public')->allFiles();
    $beforePrivateFiles = Storage::disk('local')->allFiles();

    $picker = Livewire::test(MediaPickerPanel::class, [
        'purpose' => ImageUploadPurpose::ContentGroupCover->value,
        'selectedIds' => [],
        'isMultiple' => false,
        'maxItems' => 1,
    ]);

    expect(array_column($picker->get('files'), 'id'))->toBe([$context->getKey()]);

    $picker->call('showAllMedia');
    $files = collect($picker->get('files'))->keyBy('id');

    expect($files->keys()->all())->toContain($context->getKey(), $otherFolder->getKey(), $private->getKey())
        ->and($files->get($otherFolder->getKey())['selectable'])->toBeTrue()
        ->and($files->get($private->getKey())['selectable'])->toBeFalse()
        ->and($files->get($private->getKey())['selection_blocked_reason'])->toBeString()->not->toBe('');

    $picker
        ->call('toggleSelection', $otherFolder->getKey())
        ->callAction(TestAction::make('insertMedia'))
        ->assertDispatched('insert-media', fn (string $event, array $parameters): bool => $parameters === [
            'mediaId' => $otherFolder->getKey(),
            'mediaIds' => [$otherFolder->getKey()],
        ]);

    expect(Media::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($beforeRows)
        ->and(MediaMutationOperation::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe($beforePublicFiles)
        ->and(Storage::disk('local')->allFiles())->toBe($beforePrivateFiles);
});

it('renders the authoritative attached row despite legacy path and metadata drift but falls back when its file is missing', function (): void {
    $media = p2StoredMedia('legacy/nested/current.bin', [
        'directory' => 'legacy',
        'name' => 'stale-name',
        'type' => 'application/octet-stream',
        'ext' => 'bin',
        'size' => 0,
        'width' => null,
        'height' => null,
    ]);
    $group = ContentGroup::factory()->create(['cover_path' => 'stale/legacy-mirror.jpg']);
    MediaAttachment::factory()->create([
        'media_id' => $media->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    app()->forgetInstance(PublicDefaultImageResolver::class);
    $image = app(PublicDefaultImageResolver::class)->contentGroupImage($group->refresh());

    expect($image['source'])->toBe('group')
        ->and($image['path'])->toBe($media->path)
        ->and($image['url'])->toBe(Storage::disk('public')->url($media->path));

    Storage::disk('public')->delete($media->path);
    app()->forgetInstance(PublicDefaultImageResolver::class);
    $missing = app(PublicDefaultImageResolver::class)->contentGroupImage($group->refresh());

    expect($missing['source'])->toBe('fallback')
        ->and($missing['path'])->toBeNull()
        ->and($missing['url'])->toBeNull();
});

it('falls back for rowless legacy owner paths regardless of old root or path-shape rules', function (string $legacyPath): void {
    $group = ContentGroup::factory()->create(['cover_path' => $legacyPath]);

    $image = app(PublicDefaultImageResolver::class)->contentGroupImage($group);

    expect($image['source'])->toBe('fallback')
        ->and($image['path'])->toBeNull()
        ->and($image['url'])->toBeNull();
})->with([
    'wrong root' => 'legacy/wrong-root-cover.gif',
    'malformed traversal' => '../outside-cover.jpg',
]);

it('replaces a rowless malformed legacy owner identity through the same-page action', function (): void {
    $admin = User::factory()->admin()->create();
    $replacement = p2StoredMedia('content-groups/covers/'.Str::ulid().'.jpg');
    $group = ContentGroup::factory()->create(['cover_path' => '../outside-cover.jpg']);

    Livewire::actingAs($admin)->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group))
        ->set('mountedActions.0.data.cover_media_reference_key', $replacement->reference_key)
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($replacement->getKey())
        ->and($group->refresh()->cover_path)->toBe($replacement->path);
});

it('keeps Add or Replace Image visible on podcast and episode list and edit pages and cancel preserves the attachment', function (): void {
    $admin = User::factory()->admin()->create();
    $cover = p2StoredMedia('content-groups/covers/'.Str::ulid().'.jpg', ['title' => 'Current podcast cover']);
    $episodeImage = p2StoredMedia('content-items/images/'.Str::ulid().'.jpg', ['title' => 'Current episode image']);
    $replacement = p2StoredMedia('header/'.Str::ulid().'.jpg', ['title' => 'Replacement from All Media']);
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $cover, MediaAttachmentRole::Cover, $admin);
    $manager->attach($item, $episodeImage, MediaAttachmentRole::PrimaryImage, $admin);
    $beforeRows = Media::query()->orderBy('id')->get()->map->getAttributes()->all();
    $beforeFiles = Storage::disk('public')->allFiles();

    Livewire::actingAs($admin)->test(ListContentGroups::class)
        ->assertActionVisible(TestAction::make('chooseContentGroupCover')->table($group))
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group))
        ->assertSet('mountedActions.0.data.cover_media_reference_key', $cover->getKey())
        ->assertMountedActionModalSee('Current podcast cover')
        ->assertMountedActionModalSee(__('admin.owner_image.actions.change_podcast_cover'))
        ->assertMountedActionModalSee(__('admin.media_library.gallery_source'))
        ->set('mountedActions.0.data.cover_media_reference_key', $replacement->reference_key)
        ->unmountAction();

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($cover->getKey())
        ->and($group->refresh()->cover_path)->toBe($cover->path)
        ->and(Media::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($beforeRows)
        ->and(Storage::disk('public')->allFiles())->toBe($beforeFiles);

    Livewire::actingAs($admin)->test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->assertActionVisible('chooseContentGroupCover');
    Livewire::actingAs($admin)->test(ListContentItems::class)
        ->assertActionVisible(TestAction::make('chooseContentItemImage')->table($item));
    Livewire::actingAs($admin)->test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertActionVisible('chooseContentItemImage');
    Livewire::actingAs($admin)->test(EditEpisodeWorkspace::class, ['record' => $item->getRouteKey()])
        ->assertActionVisible('chooseContentItemImage');
});

it('mounts and preserves authoritative attachments with nonportable keys on unrelated edit saves', function (?string $referenceKey): void {
    $admin = User::factory()->admin()->create();
    $media = p2StoredMedia('content-groups/covers/'.Str::ulid().'.jpg', [
        'title' => 'Existing nonportable cover',
    ]);
    $group = ContentGroup::factory()->create(['title' => 'Before unrelated save']);
    app(MediaAttachmentManager::class)->attach($group, $media, MediaAttachmentRole::Cover, $admin);
    DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => $referenceKey]);
    $media->refresh();

    Livewire::actingAs($admin)
        ->test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->assertOk()
        ->assertSet('data.cover_media_reference_key', $media->getKey())
        ->assertActionVisible('chooseContentGroupCover')
        ->set('data.title', 'After unrelated save')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($group->refresh()->title)->toBe('After unrelated save')
        ->and($group->cover_path)->toBe($media->path)
        ->and($group->coverMediaAttachment()->value('media_id'))->toBe($media->getKey());
})->with([
    'null key' => null,
    'malformed key' => 'BADKEY',
]);

it('replaces an owner attachment from All Media without copying moving renaming or normalizing either record', function (): void {
    $admin = User::factory()->admin()->create();
    $current = p2StoredMedia('content-groups/covers/'.Str::ulid().'.jpg');
    $replacement = p2StoredMedia('legacy/elsewhere/'.Str::ulid().'.bin', [
        'directory' => 'legacy',
        'type' => 'application/octet-stream',
        'ext' => 'bin',
        'size' => 0,
        'width' => null,
        'height' => null,
    ]);
    $group = ContentGroup::factory()->create();
    app(MediaAttachmentManager::class)->attach($group, $current, MediaAttachmentRole::Cover, $admin);
    $beforeRows = Media::query()->orderBy('id')->get()->map->getAttributes()->all();
    $beforeFiles = Storage::disk('public')->allFiles();

    Livewire::actingAs($admin)->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group))
        ->set('mountedActions.0.data.cover_media_reference_key', $replacement->reference_key)
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($replacement->getKey())
        ->and($group->refresh()->cover_path)->toBe($replacement->path)
        ->and(Media::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($beforeRows)
        ->and(Storage::disk('public')->allFiles())->toBe($beforeFiles)
        ->and(MediaMutationOperation::query()->count())->toBe(0);
});

it('rejects a forged nonselectable replacement at the action boundary', function (): void {
    $admin = User::factory()->admin()->create();
    $current = p2StoredMedia('content-groups/covers/'.Str::ulid().'.jpg');
    $private = p2StoredMedia('private/'.Str::ulid().'.jpg', [
        'disk' => 'local',
        'directory' => 'private',
        'visibility' => 'private',
    ]);
    $group = ContentGroup::factory()->create();
    app(MediaAttachmentManager::class)->attach($group, $current, MediaAttachmentRole::Cover, $admin);

    Livewire::actingAs($admin)->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group))
        ->set('mountedActions.0.data.cover_media_reference_key', $private->reference_key)
        ->assertHasErrors(['mountedActions.0.data.cover_media_reference_key'])
        ->assertSet('mountedActions.0.data.cover_media_reference_key', $current->getKey());

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($current->getKey())
        ->and($group->refresh()->cover_path)->toBe($current->path)
        ->and(MediaMutationOperation::query()->count())->toBe(0);
});

it('rechecks selection diagnostics under the attachment lock while preserving an unchanged current row', function (): void {
    $admin = User::factory()->admin()->create();
    $current = p2StoredMedia('content-groups/covers/'.Str::ulid().'.jpg');
    $private = p2StoredMedia('private/'.Str::ulid().'.jpg', [
        'disk' => 'local',
        'directory' => 'private',
        'visibility' => 'private',
    ]);
    $racing = p2StoredMedia('content-groups/covers/'.Str::ulid().'.jpg');
    $group = ContentGroup::factory()->create();
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $current, MediaAttachmentRole::Cover, $admin);

    Storage::disk('public')->delete($current->path);
    $manager->attach($group, $current, MediaAttachmentRole::Cover, $admin);

    expect(app(MediaInventoryDiagnostics::class)->selectionBlockedReason($racing))->toBeNull();
    Storage::disk('public')->delete($racing->path);
    expect(fn () => $manager->attach($group, $racing, MediaAttachmentRole::Cover, $admin))
        ->toThrow(InvalidArgumentException::class, __('admin.media_library.selection_missing_file'));

    expect(fn () => $manager->attach($group, $private, MediaAttachmentRole::Cover, $admin))
        ->toThrow(InvalidArgumentException::class, __('admin.media_library.selection_audience_denied'));

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($current->getKey())
        ->and($group->refresh()->cover_path)->toBe($current->path);
});

it('ships Package 2 picker and replacement labels in Hebrew and English', function (): void {
    $en = require lang_path('en/admin.php');
    $he = require lang_path('he/admin.php');

    foreach ([
        'actions.add_replace_image',
        'media_library.all_media',
        'media_library.context_media',
        'media_library.needs_repair',
        'media_library.selection_audience_denied',
    ] as $key) {
        expect(data_get($en, $key))->toBeString()->not->toBe('')
            ->and(data_get($he, $key))->toBeString()->not->toBe('');
    }
});
