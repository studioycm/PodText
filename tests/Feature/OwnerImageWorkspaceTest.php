<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\Tb1PickerContainer;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Filament\Resources\ContentGroups\Pages\CreateContentGroup;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
use App\Filament\Resources\ContentItems\Pages\CreateContentItem;
use App\Filament\Resources\ContentItems\Pages\CreateEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\Media\MediaResource;
use App\Jobs\DownloadExternalContentItemImage;
use App\Livewire\Admin\MediaPickerPanel;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Policies\CuratorMediaPolicy;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\Media\LegacyOwnerMediaRepairer;
use App\Support\Media\MediaAttachmentFormState;
use App\Support\Media\MediaAttachmentManager;
use App\Support\Media\OwnerImageChangedException;
use App\Support\Media\OwnerImageChoicePresentation;
use App\Support\Media\OwnerImagePresenter;
use App\Support\PublicFront\PublicDefaultImageResolver;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Livewire as LivewireSchemaComponent;
use Filament\Schemas\Components\Tabs;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

final class OwnerImageDenyAttachPolicy extends CuratorMediaPolicy
{
    public function attach(User $user, Media $media): bool
    {
        return false;
    }
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
});

function ownerImageMedia(string $path, array $overrides = [], bool $store = true): Media
{
    $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
    /** @var Media $media */
    $media = Media::factory()->create(array_merge([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => dirname($path),
        'visibility' => 'public',
        'name' => pathinfo($path, PATHINFO_FILENAME),
        'path' => $path,
        'width' => 640,
        'height' => 360,
        'size' => 2048,
        'type' => $extension === 'png' ? 'image/png' : 'image/jpeg',
        'ext' => $extension,
        'title' => 'Owner image',
        'exif' => ['original_filename' => 'Original upload.jpg'],
    ], $overrides));

    if ($store) {
        Storage::disk((string) $media->disk)->put((string) $media->path, 'image-bytes');
    }

    return $media;
}

function ownerImageUploadFixture(string $filename): UploadedFile
{
    $encoded = file_get_contents(base_path("tests/Fixtures/media/{$filename}.base64"));

    return UploadedFile::fake()->createWithContent(
        $filename,
        base64_decode((string) $encoded, true),
    );
}

function saveOwnerImageDefaultSettings(array $overrides): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'default_images',
        ],
        [
            'locked' => false,
            'payload' => json_encode(array_replace_recursive(
                PublicFrontConfigRegistry::defaults()['default_images'],
                $overrides,
            )),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app()->forgetInstance(PublicDefaultImageResolver::class);
    app(SettingsContainer::class)->clearCache();
}

/**
 * @return array<int, string>
 */
function taskEightOwnerTranslationManifest(): array
{
    return [
        'owner_image.actions.add_podcast_cover',
        'owner_image.actions.change_podcast_cover',
        'owner_image.actions.save_podcast_cover',
        'owner_image.actions.add_episode_image',
        'owner_image.actions.change_episode_image',
        'owner_image.actions.save_episode_image',
        'owner_image.choice.direct_heading',
        'owner_image.choice.shown_now_heading',
        'owner_image.choice.pending_heading',
        'owner_image.choice.direct_states.present',
        'owner_image.choice.direct_states.absent',
        'owner_image.choice.direct_states.broken',
        'owner_image.choice.direct_states.unsafe_legacy',
        'owner_image.choice.direct_states.custom',
        'owner_image.choice.direct_states.inherit',
        'owner_image.choice.direct_states.none',
        'owner_image.choice.pending_states.unchanged',
        'owner_image.choice.pending_states.replacement',
        'owner_image.choice.pending_states.automatic_fallback',
        'owner_image.choice.commit_boundary.commit',
        'owner_image.choice.commit_boundary.cancel',
        'owner_image.sources.direct_media',
        'owner_image.sources.compatibility_media',
        'owner_image.sources.inherited_podcast_cover',
        'owner_image.sources.external_url',
        'owner_image.sources.configured_default',
        'owner_image.sources.global_default',
        'owner_image.sources.none',
        'owner_image.warnings.portable_identity',
        'owner_image.warnings.storage_disk',
        'owner_image.warnings.missing_file',
        'owner_image.warnings.audience_denied',
        'owner_image.warnings.unsanitized_svg',
        'owner_image.warnings.metadata',
        'owner_image.warnings.disallowed_legacy_path',
        'owner_image.warnings.disallowed_attachment',
        'owner_image.warnings.duplicate_legacy_rows',
        'owner_image.warnings.attachment_path_mismatch',
        'owner_image.warnings.missing_attachment_media',
        'media_library.gallery_source',
        'media_library.upload_source',
        'media_library.url_source',
        'media_library.storage_source',
        'media_library.source_navigation',
        'media_library.gallery_search_label',
        'media_library.search',
        'media_library.selected_count',
        'media_library.current_image',
        'media_library.select_image',
        'media_library.deselect_image',
        'media_library.open_details',
        'media_library.preview_unavailable',
        'media_library.empty_library',
        'media_library.clear_search',
        'media_library.all_media',
        'media_library.directory_filter_label',
        'media_library.root_directory',
        'media_library.empty_directory',
        'media_library.working',
        'media_library.offline',
        'media_library.batch_files',
        'media_library.batch_files_help',
        'media_library.inline_batch_help',
        'media_library.upload_heading',
        'media_library.upload_failed',
        'media_library.upload_invalid',
        'media_library.upload_in_progress',
        'media_library.upload_multiple',
        'media_library.upload_one_or_multiple',
        'media_library.upload_partial_body',
        'media_library.upload_partial_title',
        'media_library.uploading_file',
        'media_library.acquire_url',
        'media_library.add_and_choose',
        'media_library.url_field',
        'media_library.url_help',
        'media_library.url_failure_blocked',
        'media_library.url_failure_invalid_image',
        'media_library.url_failure_invalid_response',
        'media_library.url_failure_not_found',
        'media_library.url_failure_temporarily_unavailable',
        'media_library.url_failure_timed_out',
        'media_library.url_failure_unexpected',
        'media_library.url_invalid',
        'media_library.acquire_storage',
        'media_library.refresh_storage',
        'media_library.storage_empty',
        'media_library.storage_load_failed',
        'media_library.storage_loading',
        'media_library.storage_search',
        'media_library.storage_search_empty',
        'media_library.storage_search_label',
        'media_library.storage_unconfigured',
        'media_library.working_storage',
        'media_library.working_upload',
        'media_library.working_url',
        'media_library.acquisition_created_single',
        'media_library.acquisition_created_multiple',
        'media_library.acquisition_reused_single',
        'media_library.acquisition_reused_multiple',
        'media_library.acquisition_batch_created',
        'media_library.acquisition_batch_partial',
        'media_library.storage_copied',
        'media_library.storage_registered',
        'media_library.storage_reused',
        'media_library.acquisition_selection_limit_exceeded',
        'media_library.selection_limit_exceeded',
        'media_library.acquisition_permanence_inline',
    ];
}

/**
 * @return array<int, string>
 */
function taskEightTranslationPlaceholders(string $value): array
{
    preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);

    $placeholders = array_values(array_unique($matches[1]));
    sort($placeholders);

    return $placeholders;
}

function taskEightDomDocument(string $html): DOMDocument
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $previousErrors = libxml_use_internal_errors(true);

    try {
        $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
    }

    return $document;
}

function taskEightElementHtml(string $html, string $testId): string
{
    $document = taskEightDomDocument($html);
    $nodes = (new DOMXPath($document))->query("//*[@data-testid=\"{$testId}\"]");

    if ($nodes === false || $nodes->count() !== 1) {
        throw new RuntimeException("Expected exactly one [data-testid=\"{$testId}\"] element.");
    }

    return (string) $document->saveHTML($nodes->item(0));
}

function taskEightHasDirectedExactText(string $html, string $direction, string $value): bool
{
    $document = taskEightDomDocument($html);
    $nodes = (new DOMXPath($document))->query("//*[@dir=\"{$direction}\"]");

    if ($nodes === false) {
        return false;
    }

    foreach ($nodes as $node) {
        if (trim($node->textContent) === $value) {
            return true;
        }
    }

    return false;
}

it('presents a direct owner image with truthful canonical metadata and safe admin links', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $media = ownerImageMedia('content-items/images/direct-episode.jpg', [
        'title' => 'Direct episode art',
        'exif' => ['original_filename' => 'Interview final ORIGINAL.JPG'],
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    $this->actingAs($admin);
    $presentation = app(OwnerImagePresenter::class)->present(
        $item,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($presentation)
        ->effectiveSource->toBe('direct_media')
        ->brokenDirect->toBeFalse()
        ->expectedMediaId->toBe($media->getKey())
        ->expectedLegacyPath->toBe($media->path)
        ->and($presentation->media)->not->toBeNull()
        ->and($presentation->media['title'])->toBe('Direct episode art')
        ->and($presentation->media['original_filename'])->toBe('Interview final ORIGINAL.JPG')
        ->and($presentation->media['stored_filename'])->toBe('direct-episode.jpg')
        ->and($presentation->media['mime'])->toBe('image/jpeg')
        ->and($presentation->media['extension'])->toBe('jpg')
        ->and($presentation->media['dimensions'])->toBe('640 × 360')
        ->and($presentation->media['file_size'])->toBeString()->not->toBe('')
        ->and($presentation->media['directory'])->toBe('content-items/images')
        ->and($presentation->media['disk'])->toBe('public')
        ->and($presentation->media['reference_key'])->toBe($media->reference_key)
        ->and($presentation->media['preview_url'])->toBe(route('admin.media-files.view', ['media' => $media]))
        ->and($presentation->media['download_url'])->toBe(route('admin.media-files.download', ['media' => $media]))
        ->and($presentation->media['review_url'])->toBe(MediaResource::getUrl('edit', ['record' => $media]))
        ->and($presentation->media['updated_at'])->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/');
});

it('projects episode direct shown and pending owner image state independently', function (): void {
    app()->setLocale('en');
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create(['title' => 'User podcast title']);
    $item = ContentItem::factory()->for($group)->create(['title' => 'User episode title']);
    $direct = ownerImageMedia('content-items/images/direct-choice.jpg', [
        'title' => 'User direct image title',
    ]);
    $replacement = ownerImageMedia('content-items/images/pending-choice.jpg', [
        'title' => 'User pending image title',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $direct,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    $this->actingAs($admin);
    $choice = app(OwnerImagePresenter::class)->choice(
        $item,
        MediaAttachmentRole::PrimaryImage,
        $replacement->reference_key,
        [
            'commit' => 'Save episode image',
            'cancel' => 'Cancel keeps the episode unchanged',
            'admission' => 'New Media remains permanent',
        ],
    );

    expect($choice)
        ->ownerKind->toBe('episode')
        ->ownerKindLabel->toBe('Episode')
        ->ownerLabel->toBe('User episode title')
        ->slotKind->toBe('primary_image')
        ->slotLabel->toBe('Episode image')
        ->isProspective->toBeFalse()
        ->directState->toBe('present')
        ->savedMediaId->toBe($direct->getKey())
        ->savedReferenceKey->toBe($direct->reference_key)
        ->shownNowSource->toBe('direct_media')
        ->pendingKind->toBe('replacement')
        ->canClearPending->toBeTrue()
        ->canChooseAutomatic->toBeTrue()
        ->expectedMediaId->toBe($direct->getKey())
        ->expectedLegacyPath->toBe($direct->path)
        ->and($choice->directMedia['id'])->toBe($direct->getKey())
        ->and($choice->directMedia['label'])->toBe('User direct image title')
        ->and($choice->shownNowMedia['id'])->toBe($direct->getKey())
        ->and($choice->pendingMedia['id'])->toBe($replacement->getKey())
        ->and($choice->pendingMedia['label'])->toBe('User pending image title')
        ->and($choice->pendingMedia['details_url'])->toBe(MediaResource::getUrl('edit', ['record' => $replacement]))
        ->and(array_keys($choice->pendingMedia))->toBe([
            'id',
            'reference_key',
            'label',
            'preview_url',
            'details_url',
        ])
        ->and($choice->commitBoundary)->toBe([
            'commit' => 'Save episode image',
            'cancel' => 'Cancel keeps the episode unchanged',
            'admission' => 'New Media remains permanent',
        ]);
});

it('presents an admitted storage media row as a numeric pending episode image without mutating its identity', function (): void {
    app()->setLocale('en');
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create(['title' => 'Inventory pending podcast']);
    $item = ContentItem::factory()->for($group)->create(['title' => 'Inventory pending episode']);
    $direct = ownerImageMedia('content-items/images/inventory-pending-direct.jpg', [
        'title' => 'Saved episode image',
    ]);
    $pending = ownerImageMedia('media-imports/inventory-pending-episode.jpg', [
        'title' => 'Admitted storage episode image',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $direct,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );
    $attachment = $item->primaryImageMediaAttachment()->sole();
    $pending->refresh();
    $pendingAttributes = $pending->getAttributes();
    $attachmentAttributes = $attachment->getAttributes();
    $pendingContents = Storage::disk((string) $pending->disk)->get((string) $pending->path);

    $this->actingAs($admin);
    $choice = app(OwnerImagePresenter::class)->choice(
        $item,
        MediaAttachmentRole::PrimaryImage,
        $pending->getKey(),
        [
            'commit' => 'Save episode image',
            'cancel' => 'Cancel keeps the episode unchanged',
            'admission' => 'New Media remains permanent',
        ],
    );

    expect($choice)
        ->pendingKind->toBe('replacement')
        ->and($choice->pendingMedia)->toBe([
            'id' => $pending->getKey(),
            'reference_key' => $pending->reference_key,
            'label' => 'Admitted storage episode image',
            'preview_url' => route('admin.media-files.view', ['media' => $pending]),
            'details_url' => MediaResource::getUrl('edit', ['record' => $pending]),
        ])
        ->and($pending->refresh()->getAttributes())->toBe($pendingAttributes)
        ->and($pending->disk)->toBe('public')
        ->and($pending->path)->toBe('media-imports/inventory-pending-episode.jpg')
        ->and(Storage::disk((string) $pending->disk)->get((string) $pending->path))->toBe($pendingContents)
        ->and($attachment->refresh()->getAttributes())->toBe($attachmentAttributes)
        ->and($attachment->media_id)->toBe($direct->getKey());
});

it('preserves admitted storage metadata for a preserved numeric pending episode image identity', function (): void {
    app()->setLocale('en');
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create(['title' => 'Preserved identity podcast']);
    $item = ContentItem::factory()->for($group)->create(['title' => 'Preserved identity episode']);
    $media = ownerImageMedia('media-imports/preserved-identity-episode.jpg', [
        'title' => 'Preserved admitted episode image',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );
    $attachment = $item->primaryImageMediaAttachment()->sole();
    $media->refresh();
    $mediaAttributes = $media->getAttributes();
    $attachmentAttributes = $attachment->getAttributes();
    $mediaContents = Storage::disk((string) $media->disk)->get((string) $media->path);
    $expectedMetadata = [
        'id' => $media->getKey(),
        'reference_key' => $media->reference_key,
        'label' => 'Preserved admitted episode image',
        'preview_url' => route('admin.media-files.view', ['media' => $media]),
        'details_url' => MediaResource::getUrl('edit', ['record' => $media]),
    ];

    $this->actingAs($admin);
    $choice = app(OwnerImagePresenter::class)->choice(
        $item,
        MediaAttachmentRole::PrimaryImage,
        MediaAttachmentFormState::preservedMediaIdentity((int) $media->getKey()),
        [
            'commit' => 'Save episode image',
            'cancel' => 'Cancel keeps the episode unchanged',
            'admission' => 'New Media remains permanent',
        ],
    );

    expect($choice)
        ->directState->toBe('present')
        ->savedMediaId->toBe($media->getKey())
        ->savedReferenceKey->toBe($media->reference_key)
        ->shownNowSource->toBe('direct_media')
        ->pendingKind->toBe('unchanged')
        ->canClearPending->toBeFalse()
        ->expectedMediaId->toBe($media->getKey())
        ->expectedLegacyPath->toBe($media->path)
        ->and($choice->directMedia)->toBe($expectedMetadata)
        ->and($choice->shownNowMedia)->toBe($expectedMetadata)
        ->and($choice->pendingMedia)->toBe($expectedMetadata)
        ->and($media->refresh()->getAttributes())->toBe($mediaAttributes)
        ->and($media->disk)->toBe('public')
        ->and($media->path)->toBe('media-imports/preserved-identity-episode.jpg')
        ->and(Storage::disk((string) $media->disk)->get((string) $media->path))->toBe($mediaContents)
        ->and($attachment->refresh()->getAttributes())->toBe($attachmentAttributes)
        ->and($attachment->media_id)->toBe($media->getKey());
});

it('projects each persisted owner choice from one refreshed owner snapshot', function (string $ownerType): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $owner = $ownerType === 'podcast'
        ? $group
        : ContentItem::factory()->for($group)->create();
    $role = $ownerType === 'podcast'
        ? MediaAttachmentRole::Cover
        : MediaAttachmentRole::PrimaryImage;
    $path = $ownerType === 'podcast'
        ? 'content-groups/covers/single-snapshot.jpg'
        : 'content-items/images/single-snapshot.jpg';
    $freshTitle = "Fresh {$ownerType} title";
    $media = ownerImageMedia($path);
    app(MediaAttachmentManager::class)->attach($owner, $media, $role, $admin);
    $staleOwner = $owner->fresh();
    $owner->newQuery()
        ->whereKey($owner->getKey())
        ->update(['title' => $freshTitle]);
    $ownerSelects = [];
    $ownerTable = $owner->getTable();

    DB::listen(function ($query) use (&$ownerSelects, $ownerTable): void {
        $sql = mb_strtolower($query->sql);

        if (preg_match('/\bfrom\s+["`]?'.preg_quote($ownerTable, '/').'["`]?\b/', $sql) === 1) {
            $ownerSelects[] = $query->sql;
        }
    });

    $this->actingAs($admin);
    $choice = app(OwnerImagePresenter::class)->choice(
        $staleOwner,
        $role,
        $media->reference_key,
        [
            'commit' => 'Save',
            'cancel' => 'Cancel',
            'admission' => 'Permanent',
        ],
    );

    expect($ownerSelects)->toHaveCount(1)
        ->and($choice->ownerLabel)->toBe($freshTitle)
        ->and($choice->directMedia['id'])->toBe($media->getKey())
        ->and($choice->savedMediaId)->toBe($media->getKey())
        ->and($choice->savedReferenceKey)->toBe($media->reference_key)
        ->and($choice->expectedMediaId)->toBe($media->getKey())
        ->and($choice->expectedLegacyPath)->toBe($media->path)
        ->and($choice->shownNowMedia['id'])->toBe($media->getKey())
        ->and($choice->shownNowSource)->toBe('direct_media')
        ->and($choice->warningCodes)->toBe([]);
})->with([
    'podcast cover' => ['podcast'],
    'episode primary image' => ['episode'],
]);

it('reuses one prepared owner projection across modal consumers and refreshes it once after stale evidence', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $original = ownerImageMedia('content-groups/covers/prepared-snapshot-a.jpg');
    $concurrent = ownerImageMedia('content-groups/covers/prepared-snapshot-b.jpg');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $original, MediaAttachmentRole::Cover, $admin);
    $staleOwner = $group->fresh();
    $ownerSelects = [];

    DB::listen(function ($query) use (&$ownerSelects): void {
        $sql = mb_strtolower($query->sql);

        if (preg_match('/\bfrom\s+["`]?content_groups["`]?\b/', $sql) === 1) {
            $ownerSelects[] = $query->sql;
        }
    });

    $this->actingAs($admin);
    $presenter = app(OwnerImagePresenter::class);
    $initial = $presenter->present($staleOwner, MediaAttachmentRole::Cover);
    $initialChoice = $presenter->choice(
        $staleOwner,
        MediaAttachmentRole::Cover,
        $original->getKey(),
        ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
    );
    $initialPickerIdentity = $presenter->pickerIdentity($staleOwner, MediaAttachmentRole::Cover);

    expect($ownerSelects)->toHaveCount(1)
        ->and($initial->expectedMediaId)->toBe($original->getKey())
        ->and($initialChoice->savedMediaId)->toBe($original->getKey())
        ->and($initialPickerIdentity)->toBe($original->getKey());

    $manager->attach($group, $concurrent, MediaAttachmentRole::Cover, $admin);
    $ownerSelects = [];
    $presenter->refresh($staleOwner, MediaAttachmentRole::Cover);
    $refreshed = $presenter->present($staleOwner, MediaAttachmentRole::Cover);
    $refreshedChoice = $presenter->choice(
        $staleOwner,
        MediaAttachmentRole::Cover,
        $original->getKey(),
        ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
    );
    $refreshedPickerIdentity = $presenter->pickerIdentity($staleOwner, MediaAttachmentRole::Cover);

    expect($ownerSelects)->toHaveCount(1)
        ->and($refreshed->expectedMediaId)->toBe($concurrent->getKey())
        ->and($refreshedChoice->savedMediaId)->toBe($concurrent->getKey())
        ->and($refreshedPickerIdentity)->toBe($concurrent->getKey());
});

it('renders the owner image list surface without record scaled owner refresh queries', function (): void {
    $admin = User::factory()->admin()->create();
    $groups = ContentGroup::factory()->count(3)->create();

    foreach ($groups as $index => $group) {
        $media = ownerImageMedia("content-groups/covers/list-probe-{$index}.jpg");
        app(MediaAttachmentManager::class)->attach(
            $group,
            $media,
            MediaAttachmentRole::Cover,
            $admin,
        );
    }

    $recordRefreshQueries = [];

    DB::listen(function ($query) use (&$recordRefreshQueries): void {
        $sql = mb_strtolower($query->sql);

        if (
            str_contains($sql, 'from "content_groups"')
            && str_contains($sql, 'where "content_groups"."id" = ?')
            && str_contains($sql, 'limit 1')
        ) {
            $recordRefreshQueries[] = $query->sql;
        }
    });

    $component = Livewire::actingAs($admin)->test(ListContentGroups::class);

    foreach ($groups as $group) {
        $component->assertSee($group->title);
    }

    expect($recordRefreshQueries)->toBe([]);
});

it('projects podcast unchanged automatic and prospective owner choice states', function (): void {
    app()->setLocale('en');
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create(['title' => 'admin.owner_image.heading']);
    $direct = ownerImageMedia('content-groups/covers/direct-podcast-choice.jpg', [
        'title' => 'admin.owner_image.actions.delete',
    ]);
    $prospectiveMedia = ownerImageMedia('content-groups/covers/prospective-choice.jpg');
    app(MediaAttachmentManager::class)->attach(
        $group,
        $direct,
        MediaAttachmentRole::Cover,
        $admin,
    );
    $boundary = [
        'commit' => 'Create or save the podcast',
        'cancel' => 'Cancel keeps the podcast unchanged',
        'admission' => 'Admitted Media remains permanent',
    ];

    $this->actingAs($admin);
    $unchanged = app(OwnerImagePresenter::class)->choice(
        $group,
        MediaAttachmentRole::Cover,
        $direct->getKey(),
        $boundary,
    );
    $automatic = app(OwnerImagePresenter::class)->choice(
        $group,
        MediaAttachmentRole::Cover,
        null,
        $boundary,
    );
    $prospective = app(OwnerImagePresenter::class)->choice(
        new ContentGroup(['title' => 'Prospective podcast title']),
        MediaAttachmentRole::Cover,
        $prospectiveMedia->reference_key,
        $boundary,
    );

    expect($unchanged)
        ->ownerKind->toBe('podcast')
        ->ownerKindLabel->toBe('Podcast')
        ->ownerLabel->toBe('admin.owner_image.heading')
        ->slotKind->toBe('cover')
        ->slotLabel->toBe('Cover')
        ->pendingKind->toBe('unchanged')
        ->canClearPending->toBeFalse()
        ->and($unchanged->directMedia['label'])->toBe('admin.owner_image.actions.delete')
        ->and($automatic)
        ->pendingKind->toBe('automatic_fallback')
        ->pendingMedia->toBeNull()
        ->canClearPending->toBeTrue()
        ->canChooseAutomatic->toBeTrue()
        ->and($prospective)
        ->isProspective->toBeTrue()
        ->directState->toBe('absent')
        ->pendingKind->toBe('replacement')
        ->canChooseAutomatic->toBeFalse()
        ->and($prospective->pendingMedia['id'])->toBe($prospectiveMedia->getKey());
});

it('projects inherited external default global and empty shown-now states independently', function (): void {
    $admin = User::factory()->admin()->create();
    $cover = ownerImageMedia('content-groups/covers/choice-inherited.jpg');
    $configured = ownerImageMedia('default-images/choice-configured.jpg');
    $global = ownerImageMedia('default-images/choice-global.jpg');
    $group = ContentGroup::factory()->create();
    app(MediaAttachmentManager::class)->attach(
        $group,
        $cover,
        MediaAttachmentRole::Cover,
        $admin,
    );
    $inheritedItem = ContentItem::factory()->for($group)->create(['external_thumbnail_url' => null]);
    $externalItem = ContentItem::factory()->for(ContentGroup::factory())->create([
        'external_thumbnail_url' => 'https://cdn.example.test/choice-external.jpg',
    ]);
    $boundary = [
        'commit' => 'Save',
        'cancel' => 'Cancel',
        'admission' => 'Permanent',
    ];

    $this->actingAs($admin);
    $inherited = app(OwnerImagePresenter::class)->choice(
        $inheritedItem,
        MediaAttachmentRole::PrimaryImage,
        null,
        $boundary,
    );
    $external = app(OwnerImagePresenter::class)->choice(
        $externalItem,
        MediaAttachmentRole::PrimaryImage,
        null,
        $boundary,
    );

    saveOwnerImageDefaultSettings([
        'content_group' => [
            'mode' => 'custom',
            'path' => $configured->path,
            'media_reference_key' => $configured->reference_key,
        ],
    ]);
    $configuredGroup = app(OwnerImagePresenter::class)->choice(
        ContentGroup::factory()->create(),
        MediaAttachmentRole::Cover,
        null,
        $boundary,
    );

    saveOwnerImageDefaultSettings([
        'content_item' => [
            'mode' => 'inherit',
            'path' => null,
            'media_reference_key' => null,
        ],
        'global' => [
            'mode' => 'custom',
            'path' => $global->path,
            'media_reference_key' => $global->reference_key,
        ],
    ]);
    $globalItem = app(OwnerImagePresenter::class)->choice(
        ContentItem::factory()->for(ContentGroup::factory())->create(['external_thumbnail_url' => null]),
        MediaAttachmentRole::PrimaryImage,
        null,
        $boundary,
    );

    saveOwnerImageDefaultSettings([
        'global' => [
            'mode' => 'none',
            'path' => null,
            'media_reference_key' => null,
        ],
    ]);
    $emptyItem = app(OwnerImagePresenter::class)->choice(
        ContentItem::factory()->for(ContentGroup::factory())->create(['external_thumbnail_url' => null]),
        MediaAttachmentRole::PrimaryImage,
        null,
        $boundary,
    );

    expect($inherited)
        ->directState->toBe('absent')
        ->shownNowSource->toBe('inherited_podcast_cover')
        ->pendingKind->toBe('unchanged')
        ->canChooseAutomatic->toBeFalse()
        ->and($inherited->shownNowMedia['id'])->toBe($cover->getKey())
        ->and($external)
        ->shownNowSource->toBe('external_url')
        ->shownNowMedia->toBeNull()
        ->shownNowPreviewUrl->toBe('https://cdn.example.test/choice-external.jpg')
        ->and($configuredGroup)
        ->shownNowSource->toBe('configured_default')
        ->and($configuredGroup->shownNowMedia['id'])->toBe($configured->getKey())
        ->and($globalItem)
        ->shownNowSource->toBe('global_default')
        ->and($globalItem->shownNowMedia['id'])->toBe($global->getKey())
        ->and($emptyItem)
        ->shownNowSource->toBe('none')
        ->shownNowMedia->toBeNull()
        ->shownNowPreviewUrl->toBeNull();
});

it('projects broken denied and unsafe owner states without unsafe chooser actions', function (): void {
    $admin = User::factory()->admin()->create();
    $moderator = User::factory()->moderator()->create();
    $private = ownerImageMedia('private/choice-private.jpg', [
        'disk' => 'local',
        'directory' => 'private',
        'visibility' => 'private',
    ]);
    $privateItem = ContentItem::factory()->for(ContentGroup::factory())->create([
        'external_thumbnail_url' => 'https://cdn.example.test/private-fallback.jpg',
    ]);
    MediaAttachment::factory()->create([
        'media_id' => $private->getKey(),
        'attachable_type' => 'content_item',
        'attachable_id' => $privateItem->getKey(),
        'role' => MediaAttachmentRole::PrimaryImage,
        'position' => 0,
    ]);
    $privateItem->forceFill(['image_path' => $private->path])->saveQuietly();
    $unsafe = ownerImageMedia('content-groups/covers/unsafe-item-role.jpg');
    ownerImageMedia('content-groups/covers/unsafe-item-role.jpg', [
        'name' => 'duplicate-unsafe-item-role',
        'title' => 'Duplicate unsafe item role',
    ]);
    $unsafeItem = ContentItem::factory()->for(ContentGroup::factory())->create([
        'image_path' => $unsafe->path,
        'external_thumbnail_url' => 'https://cdn.example.test/unsafe-fallback.jpg',
    ]);
    $svg = ownerImageMedia('content-items/images/choice-unsafe.svg', [
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
    ], store: false);
    Storage::disk('public')->put(
        $svg->path,
        '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>',
    );
    $svgItem = ContentItem::factory()->for(ContentGroup::factory())->create([
        'image_path' => $svg->path,
        'external_thumbnail_url' => 'https://cdn.example.test/svg-fallback.jpg',
    ]);
    MediaAttachment::factory()->create([
        'media_id' => $svg->getKey(),
        'attachable_type' => 'content_item',
        'attachable_id' => $svgItem->getKey(),
        'role' => MediaAttachmentRole::PrimaryImage,
        'position' => 0,
    ]);
    $missing = ownerImageMedia('content-items/images/choice-missing.jpg');
    Storage::disk('public')->delete($missing->path);
    $missingItem = ContentItem::factory()->for(ContentGroup::factory())->create([
        'image_path' => $missing->path,
        'external_thumbnail_url' => 'https://cdn.example.test/missing-fallback.jpg',
    ]);
    MediaAttachment::factory()->create([
        'media_id' => $missing->getKey(),
        'attachable_type' => 'content_item',
        'attachable_id' => $missingItem->getKey(),
        'role' => MediaAttachmentRole::PrimaryImage,
        'position' => 0,
    ]);
    $missingRow = ownerImageMedia('content-items/images/choice-missing-row.jpg');
    $missingRowItem = ContentItem::factory()->for(ContentGroup::factory())->create([
        'image_path' => $missingRow->path,
        'external_thumbnail_url' => 'https://cdn.example.test/missing-row-fallback.jpg',
    ]);
    MediaAttachment::factory()->create([
        'media_id' => $missingRow->getKey(),
        'attachable_type' => 'content_item',
        'attachable_id' => $missingRowItem->getKey(),
        'role' => MediaAttachmentRole::PrimaryImage,
        'position' => 0,
    ]);
    $missingRowId = $missingRow->getKey();
    DB::statement('PRAGMA defer_foreign_keys = ON');
    DB::table('curator')->where('id', $missingRowId)->delete();
    $boundary = [
        'commit' => 'Save',
        'cancel' => 'Cancel',
        'admission' => 'Permanent',
    ];

    $this->actingAs($admin);
    $broken = app(OwnerImagePresenter::class)->choice(
        $privateItem,
        MediaAttachmentRole::PrimaryImage,
        null,
        $boundary,
    );
    $unsafeChoice = app(OwnerImagePresenter::class)->choice(
        $unsafeItem,
        MediaAttachmentRole::PrimaryImage,
        null,
        $boundary,
    );
    $unsafeSvg = app(OwnerImagePresenter::class)->choice(
        $svgItem,
        MediaAttachmentRole::PrimaryImage,
        null,
        $boundary,
    );
    $missingFile = app(OwnerImagePresenter::class)->choice(
        $missingItem,
        MediaAttachmentRole::PrimaryImage,
        null,
        $boundary,
    );
    $missingAttachmentMedia = app(OwnerImagePresenter::class)->choice(
        $missingRowItem,
        MediaAttachmentRole::PrimaryImage,
        null,
        $boundary,
    );

    $this->actingAs($moderator);
    $denied = app(OwnerImagePresenter::class)->choice(
        $privateItem,
        MediaAttachmentRole::PrimaryImage,
        $private->getKey(),
        $boundary,
    );

    expect($broken)
        ->directState->toBe('broken')
        ->shownNowSource->toBe('external_url')
        ->shownNowMedia->toBeNull()
        ->canChooseAutomatic->toBeTrue()
        ->and($broken->warningCodes)->toContain('audience_denied')
        ->and($broken->directMedia['preview_url'])->toBeNull()
        ->and($broken->directMedia['details_url'])->toBe(MediaResource::getUrl('edit', ['record' => $private]))
        ->and($unsafeChoice)
        ->directState->toBe('unsafe_legacy')
        ->unsafeFingerprint->not->toBeNull()
        ->canChooseAutomatic->toBeFalse()
        ->and($unsafeSvg)
        ->directState->toBe('broken')
        ->canChooseAutomatic->toBeTrue()
        ->and($unsafeSvg->warningCodes)->toContain('unsanitized_svg')
        ->and($unsafeSvg->directMedia['preview_url'])->toBeNull()
        ->and($missingFile)
        ->directState->toBe('broken')
        ->savedMediaId->toBe($missing->getKey())
        ->and($missingFile->warningCodes)->toContain('missing_file')
        ->and($missingFile->directMedia['preview_url'])->toBeNull()
        ->and($missingAttachmentMedia)
        ->directState->toBe('broken')
        ->savedMediaId->toBe($missingRowId)
        ->canChooseAutomatic->toBeTrue()
        ->and($missingAttachmentMedia->warningCodes)->toContain('missing_attachment_media')
        ->and($denied->directMedia['preview_url'])->toBeNull()
        ->and($denied->directMedia['details_url'])->toBeNull();
});

it('renders shared owner choice state in the canonical owner action', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create(['title' => 'כותרת Episode']);
    $media = ownerImageMedia('content-items/images/owner-aware-field.jpg', [
        'title' => 'תמונה Mixed title',
    ]);
    $pending = ownerImageMedia('content-items/images/owner-aware-pending.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item))
        ->assertMountedActionModalSee('data-testid="owner-image-choice-state"', false)
        ->assertMountedActionModalSee('dir="auto"', false)
        ->assertMountedActionModalSee('dir="ltr"', false)
        ->assertMountedActionModalSee('data-testid="owner-image-choose-automatic"', false)
        ->assertMountedActionModalDontSee('data-inline-owner-summary="true"', false);
});

it('memoizes owner choice presentations per field and refreshes only the changed field', function (): void {
    $admin = User::factory()->admin()->create();
    $firstMedia = ownerImageMedia('content-items/images/memoized-first.jpg');
    $secondMedia = ownerImageMedia('content-items/images/memoized-second.jpg');
    $firstItem = ContentItem::factory()->for(ContentGroup::factory())->create();
    $secondItem = ContentItem::factory()->for(ContentGroup::factory())->create();
    $firstReads = 0;
    $secondReads = 0;
    $presentation = fn (int $savedMediaId): OwnerImageChoicePresentation => new OwnerImageChoicePresentation(
        ownerKind: 'episode',
        ownerKindLabel: 'Episode',
        ownerLabel: 'Memoized owner',
        slotKind: 'primary_image',
        slotLabel: 'Episode image',
        isProspective: false,
        directState: 'present',
        directMedia: null,
        shownNowSource: 'direct_media',
        shownNowMedia: null,
        shownNowPreviewUrl: null,
        savedMediaId: $savedMediaId,
        savedReferenceKey: null,
        pendingKind: 'unchanged',
        pendingMedia: null,
        canClearPending: false,
        canChooseAutomatic: true,
        expectedMediaId: $savedMediaId,
        expectedLegacyPath: null,
        unsafeFingerprint: null,
        commitBoundary: ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
        warningCodes: [],
    );
    $firstComponent = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($firstItem));
    $secondComponent = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($secondItem));
    $first = collect($firstComponent->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);
    $second = collect($secondComponent->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($first instanceof PathCuratorPicker);
    assert($second instanceof PathCuratorPicker);

    $first->ownerChoice(function () use (&$firstReads, $firstMedia, $presentation): OwnerImageChoicePresentation {
        $firstReads++;

        return $presentation($firstMedia->getKey());
    });
    $second->ownerChoice(function () use (&$secondReads, $secondMedia, $presentation): OwnerImageChoicePresentation {
        $secondReads++;

        return $presentation($secondMedia->getKey());
    });

    $this->actingAs($admin);

    $first->getOwnerChoicePresentation();
    $first->getInlineWorkspaceComponent()->getData();
    $second->getOwnerChoicePresentation();

    expect($firstReads)->toBe(1)
        ->and($secondReads)->toBe(1);

    $first
        ->state($firstMedia->getKey())
        ->callAfterStateUpdated(false);

    $first->getOwnerChoicePresentation();
    $second->getOwnerChoicePresentation();

    expect($firstReads)->toBe(2)
        ->and($secondReads)->toBe(1);
});

it('keeps owner choice pending state server owned and restores the saved media identity', function (): void {
    $admin = User::factory()->admin()->create();
    $item = ContentItem::factory()->for(ContentGroup::factory())->create();
    $saved = ownerImageMedia('content-items/images/owner-saved.jpg');
    $replacement = ownerImageMedia('content-items/images/owner-pending.jpg');
    $forged = ownerImageMedia('header/owner-forged.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $saved,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    PathCuratorPicker::configureUsing(
        fn (PathCuratorPicker $picker): PathCuratorPicker => $picker->ownerChoice(
            fn (PathCuratorPicker $component) => app(OwnerImagePresenter::class)->choice(
                $item,
                MediaAttachmentRole::PrimaryImage,
                $component->getState(),
                [
                    'commit' => 'Save',
                    'cancel' => 'Cancel',
                    'admission' => 'Permanent',
                ],
            ),
        )->multiple()->maxItems(10),
        function () use ($admin, $forged, $item, $replacement, $saved): void {
            $component = Livewire::actingAs($admin)
                ->test(ListContentItems::class)
                ->mountAction(TestAction::make('chooseContentItemImage')->table($item));
            $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
                ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

            assert($picker instanceof PathCuratorPicker);

            $inlineData = $picker->getInlineWorkspaceComponent()->getData();

            expect($picker->isOwnerChoice())->toBeTrue()
                ->and($picker->isMultiple())->toBeFalse()
                ->and($inlineData['isOwnerChoice'])->toBeTrue()
                ->and($inlineData['savedMediaId'])->toBe($saved->getKey());

            $component
                ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
                    'mediaIds' => [$replacement->getKey(), $forged->getKey()],
                    'purpose' => ImageUploadPurpose::HeaderLogo->value,
                    'savedMediaId' => $forged->getKey(),
                    'isOwnerChoice' => false,
                ]])
                ->assertStatus(422);

            $component = Livewire::actingAs($admin)
                ->test(ListContentItems::class)
                ->mountAction(TestAction::make('chooseContentItemImage')->table($item));
            $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
                ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

            assert($picker instanceof PathCuratorPicker);

            $component
                ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
                    'mediaId' => $replacement->getKey(),
                    'purpose' => ImageUploadPurpose::HeaderLogo->value,
                    'savedMediaId' => $forged->getKey(),
                    'isOwnerChoice' => false,
                ]])
                ->assertSet('mountedActions.0.data.primary_image_media_reference_key', $replacement->getKey())
                ->assertDispatched('owner-media-selection-changed')
                ->call('callSchemaComponentMethod', $picker->getKey(), 'restoreSavedOwnerSelection', [[
                    'savedMediaId' => $forged->getKey(),
                ]])
                ->assertSet('mountedActions.0.data.primary_image_media_reference_key', $saved->getKey())
                ->assertDispatched('owner-media-selection-restored')
                ->call('callSchemaComponentMethod', $picker->getKey(), 'chooseAutomaticOwnerImage', [[
                    'savedMediaId' => $forged->getKey(),
                ]])
                ->assertSet('mountedActions.0.data.primary_image_media_reference_key', null)
                ->assertDispatched('owner-media-selection-changed');
        },
    );

    expect($item->primaryImageMediaAttachment()->value('media_id'))->toBe($saved->getKey())
        ->and($item->refresh()->image_path)->toBe($saved->path);
});

it('keeps the picker opener on a non inline owner choice field without generic selected actions', function (): void {
    $admin = User::factory()->admin()->create();
    $item = ContentItem::factory()->for(ContentGroup::factory())->create();
    $saved = ownerImageMedia('content-items/images/non-inline-owner.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $saved,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    PathCuratorPicker::configureUsing(
        fn (PathCuratorPicker $picker): PathCuratorPicker => $picker->ownerChoice(
            fn (PathCuratorPicker $component) => app(OwnerImagePresenter::class)->choice(
                $item,
                MediaAttachmentRole::PrimaryImage,
                $component->getState(),
                [
                    'commit' => 'Save',
                    'cancel' => 'Cancel',
                    'admission' => 'Permanent',
                ],
            ),
        ),
        fn () => Livewire::actingAs($admin)
            ->test(EditContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSee('data-testid="owner-image-choice-state"', false)
            ->assertSee('data-testid="media-picker-open"', false)
            ->assertDontSee('data-testid="media-picker-selected-item"', false)
            ->assertDontSee('removeAll', false),
    );
});

it('rejects hostile generic schema actions in owner mode and keeps owner transitions authoritative', function (): void {
    $admin = User::factory()->admin()->create();
    $item = ContentItem::factory()->for(ContentGroup::factory())->create();
    $saved = ownerImageMedia('content-items/images/owner-action-saved.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $saved,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    PathCuratorPicker::configureUsing(
        fn (PathCuratorPicker $picker): PathCuratorPicker => $picker->ownerChoice(
            fn (PathCuratorPicker $component) => app(OwnerImagePresenter::class)->choice(
                $item,
                MediaAttachmentRole::PrimaryImage,
                $component->getState(),
                [
                    'commit' => 'Save',
                    'cancel' => 'Cancel',
                    'admission' => 'Permanent',
                ],
            ),
        ),
        function () use ($admin, $item, $saved): void {
            $component = Livewire::actingAs($admin)
                ->test(EditContentItem::class, ['record' => $item->getRouteKey()]);
            $picker = collect($component->instance()->getSchema('form')?->getFlatComponents(withHidden: true))
                ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

            assert($picker instanceof PathCuratorPicker);

            foreach (['view', 'edit', 'download', 'remove', 'removeAll'] as $actionName) {
                $action = TestAction::make($actionName)->schemaComponent(
                    (string) str($picker->getKey())->after('.'),
                );

                $component
                    ->assertActionHidden($action, ['id' => $saved->getKey()])
                    ->mountAction($action, ['id' => $saved->getKey()])
                    ->assertSet('mountedActions', [])
                    ->assertSet('data.primary_image_media_reference_key', $saved->getKey());
            }

            foreach (['remove', 'removeAll'] as $actionName) {
                $action = $picker->getAction($actionName);

                expect($action)->toBeInstanceOf(Action::class);

                $action->hidden(false)->disabled(false);

                expect(fn () => $action->call([
                    'arguments' => ['id' => $saved->getKey()],
                    'component' => $picker,
                ]))->toThrow(NotFoundHttpException::class);
            }

            $component
                ->call('callSchemaComponentMethod', $picker->getKey(), 'chooseAutomaticOwnerImage', [[]])
                ->assertSet('data.primary_image_media_reference_key', null)
                ->assertSet('data.relation_owner_image_pending_identity', null)
                ->assertDispatched('owner-media-selection-changed')
                ->call('callSchemaComponentMethod', $picker->getKey(), 'restoreSavedOwnerSelection', [[]])
                ->assertSet('data.primary_image_media_reference_key', $saved->getKey())
                ->assertSet('data.relation_owner_image_pending_identity', $saved->getKey())
                ->assertDispatched('owner-media-selection-restored');
        },
    );

    expect($item->primaryImageMediaAttachment()->value('media_id'))->toBe($saved->getKey())
        ->and($item->refresh()->image_path)->toBe($saved->path);
});

it('opens the shared detail workspace from a table thumbnail without row navigation', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create(['title' => 'Keyboard episode']);
    $media = ownerImageMedia('content-items/images/keyboard-episode.jpg', [
        'title' => 'Keyboard artwork',
        'exif' => ['original_filename' => 'Keyboard original.jpg'],
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('contentItemImageDetails')->table($item))
        ->assertMountedActionModalSee('data-testid="owner-image-choice-state"', false)
        ->assertMountedActionModalSee(__('admin.owner_image.sources.direct_media'))
        ->assertMountedActionModalSee('Keyboard artwork');
});

it('binds the canonical owner baseline token to the action actor owner role and expected identity', function (): void {
    $admin = User::factory()->admin()->create();
    $item = ContentItem::factory()->for(ContentGroup::factory())->create();
    $media = ownerImageMedia('content-items/images/authenticated-baseline.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    $component = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item));
    $token = $component->get('mountedActions.0.data.owner_image_baseline_token');

    expect($token)->toBeString()->not->toBe('')
        ->and(json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'v' => 1,
            'action' => 'chooseContentItemImage',
            'field' => 'primary_image_media_reference_key',
            'owner_type' => 'content_item',
            'owner_id' => $item->getKey(),
            'role' => MediaAttachmentRole::PrimaryImage->value,
            'actor_id' => $admin->getKey(),
            'expected_media_id' => $media->getKey(),
            'expected_legacy_path' => $media->path,
            'unsafe_fingerprint' => null,
        ]);
});

it('ignores forged legacy expected fields and still requires a stale reconfirm', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $original = ownerImageMedia('content-groups/covers/forged-baseline-a.jpg');
    $concurrent = ownerImageMedia('content-groups/covers/forged-baseline-b.jpg');
    $pending = ownerImageMedia('content-groups/covers/forged-baseline-c.jpg');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $original, MediaAttachmentRole::Cover, $admin);

    $component = Livewire::actingAs($admin)
        ->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group));
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
        'mediaId' => $pending->getKey(),
    ]]);
    $manager->attach($group, $concurrent, MediaAttachmentRole::Cover, $admin);

    $component
        ->set('mountedActions.0.data.expected_media_id', $concurrent->getKey())
        ->set('mountedActions.0.data.expected_legacy_path', $concurrent->path)
        ->set('mountedActions.0.data.legacy_media_repair_fingerprint', 'forged-current-fingerprint')
        ->callMountedAction()
        ->assertActionMounted()
        ->assertHasErrors(['mountedActions.0.data.cover_media_reference_key']);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($concurrent->getKey())
        ->and($group->refresh()->cover_path)->toBe($concurrent->path);
});

it('rejects a modified owner baseline token without mutating the owner', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $current = ownerImageMedia('content-groups/covers/tampered-token-current.jpg');
    $pending = ownerImageMedia('content-groups/covers/tampered-token-pending.jpg');
    app(MediaAttachmentManager::class)->attach(
        $group,
        $current,
        MediaAttachmentRole::Cover,
        $admin,
    );

    $component = Livewire::actingAs($admin)
        ->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group));
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $pending->getKey(),
        ]])
        ->set('mountedActions.0.data.owner_image_baseline_token', 'tampered-token')
        ->callMountedAction()
        ->assertActionMounted()
        ->assertHasErrors(['mountedActions.0.data.cover_media_reference_key']);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($current->getKey())
        ->and($group->refresh()->cover_path)->toBe($current->path);
});

it('rejects a valid owner baseline token from a different context', function (string $mismatch): void {
    $firstAdmin = User::factory()->admin()->create();
    $secondAdmin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $foreignGroup = ContentGroup::factory()->create();
    $current = ownerImageMedia('content-groups/covers/cross-context-current.jpg');
    $pending = ownerImageMedia('content-groups/covers/cross-context-pending.jpg');
    app(MediaAttachmentManager::class)->attach(
        $group,
        $current,
        MediaAttachmentRole::Cover,
        $firstAdmin,
    );

    $foreignAction = $mismatch === 'action'
        ? 'contentGroupCoverDetails'
        : 'chooseContentGroupCover';
    $foreignOwner = $mismatch === 'owner' ? $foreignGroup : $group;
    $foreignComponent = Livewire::actingAs($firstAdmin)
        ->test(ListContentGroups::class)
        ->mountAction(TestAction::make($foreignAction)->table($foreignOwner));
    $foreignToken = $foreignComponent->get('mountedActions.0.data.owner_image_baseline_token');
    $targetActor = $mismatch === 'actor' ? $secondAdmin : $firstAdmin;

    $component = Livewire::actingAs($targetActor)
        ->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group));
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $pending->getKey(),
        ]])
        ->set('mountedActions.0.data.owner_image_baseline_token', $foreignToken)
        ->callMountedAction()
        ->assertActionMounted()
        ->assertHasErrors(['mountedActions.0.data.cover_media_reference_key']);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($current->getKey())
        ->and($group->refresh()->cover_path)->toBe($current->path);
})->with([
    'actor id' => ['actor'],
    'action name' => ['action'],
    'owner id' => ['owner'],
]);

it('shows owner state before the inline source picker without tabs', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $media = ownerImageMedia('content-items/images/inline-picker.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    $component = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('contentItemImageDetails')->table($item))
        ->assertMountedActionModalSee('data-testid="owner-image-choice-state"', false);

    $action = $component->instance()->getMountedAction();
    $schema = $component->instance()->getSchema('mountedActionSchema0');
    $components = collect($schema?->getFlatComponents(withHidden: true));
    $picker = $components->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);
    $livewire = $components->first(fn (mixed $component): bool => $component instanceof LivewireSchemaComponent);

    assert($picker instanceof PathCuratorPicker);
    assert($livewire instanceof LivewireSchemaComponent);

    expect($action?->hasModalContent())->toBeFalse()
        ->and($action?->getModalWidth())->toBe(Width::SevenExtraLarge)
        ->and($action?->isModalAutofocused())->toBeFalse()
        ->and($components->contains(fn (mixed $component): bool => $component instanceof Tabs))->toBeFalse()
        ->and($livewire->getComponent())->toBe(MediaPickerPanel::class)
        ->and($livewire->getData()['isInlineOwnerWorkspace'] ?? false)->toBeTrue();

    Livewire::actingAs($admin)
        ->test(MediaPickerPanel::class, [
            'purpose' => ImageUploadPurpose::ContentItemPrimaryImage->value,
            'selectedIds' => [$media->getKey()],
            'isMultiple' => false,
            'maxItems' => 1,
            'isInlineOwnerWorkspace' => true,
        ])
        ->assertDontSee('data-testid="media-picker-selected-preview"', false);
});

it('configures one owner specific canonical modal with one state then source schema', function (): void {
    app()->setLocale('en');
    $admin = User::factory()->admin()->create();
    $settings = app(AdminUxSettings::class);
    $settings->tb1_picker_container = Tb1PickerContainer::SlideOver->value;
    $settings->save();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $episodeImage = ownerImageMedia('content-items/images/canonical-owner-modal.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $episodeImage,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    foreach ([
        [
            ListContentGroups::class,
            $group,
            'chooseContentGroupCover',
            'Podcast · Cover — Add',
            'Save podcast cover',
            __('admin.helpers.cover_path'),
        ],
        [
            ListContentItems::class,
            $item,
            'chooseContentItemImage',
            'Episode · Primary image — Change',
            'Save episode image',
            __('admin.helpers.content_item_image_path'),
        ],
    ] as [$pageClass, $record, $actionName, $heading, $submitLabel, $description]) {
        $component = Livewire::actingAs($admin)
            ->test($pageClass)
            ->mountAction(TestAction::make($actionName)->table($record));
        $action = $component->instance()->getMountedAction();
        $schema = $component->instance()->getSchema('mountedActionSchema0');
        $visibleComponents = collect($schema?->getFlatComponents(withHidden: true))
            ->filter(fn (mixed $schemaComponent): bool => $schemaComponent instanceof PathCuratorPicker
                || $schemaComponent instanceof LivewireSchemaComponent
                || $schemaComponent instanceof Tabs)
            ->values();

        expect($action?->getLabel())->toBe($heading)
            ->and($action?->getModalHeading())->toBe($heading)
            ->and($action?->getModalDescription())->toBeNull()
            ->and($action?->getModalSubmitAction()?->getLabel())->toBe($submitLabel)
            ->and($action?->getModalWidth())->toBe(Width::SevenExtraLarge)
            ->and($action?->isModalSlideOver())->toBeFalse()
            ->and($action?->isModalHeaderSticky())->toBeTrue()
            ->and($action?->isModalFooterSticky())->toBeTrue()
            ->and($action?->getExtraModalWindowAttributes())->toMatchArray([
                'class' => 'podtext-owner-image-modal',
                'data-owner-image-modal' => 'true',
            ])
            ->and($action?->getExtraModalFooterActions())->toBe([])
            ->and($visibleComponents)->toHaveCount(2)
            ->and($visibleComponents[0])->toBeInstanceOf(PathCuratorPicker::class)
            ->and($visibleComponents[0]->isLabelHidden())->toBeTrue()
            ->and(substr_count($component->getMountedActionModalHtml(), e($description)))->toBe(1)
            ->and($visibleComponents[1])->toBeInstanceOf(LivewireSchemaComponent::class)
            ->and($visibleComponents)->each(
                fn ($component): mixed => $component->not->toBeInstanceOf(Tabs::class),
            );
    }
});

it('keeps an inline gallery choice pending when the owner action is cancelled', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $current = ownerImageMedia('content-items/images/current-inline-choice.jpg');
    $replacement = ownerImageMedia('content-items/images/pending-inline-choice.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $current,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    $component = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item));
    $schema = $component->instance()->getSchema('mountedActionSchema0');
    $picker = collect($schema?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [
            [
                'mediaId' => $replacement->getKey(),
                'mediaIds' => [$replacement->getKey()],
            ],
        ])
        ->assertSet('mountedActions.0.data.primary_image_media_reference_key', $replacement->getKey())
        ->unmountAction();

    expect($item->primaryImageMediaAttachment()->value('media_id'))->toBe($current->getKey())
        ->and($item->refresh()->image_path)->toBe($current->path)
        ->and(Media::query()->whereKey($replacement)->exists())->toBeTrue();
});

it('commits a relation manager episode image only when create is submitted', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $replacement = ownerImageMedia('content-items/images/relation-create-choice.jpg');

    $component = Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->mountAction(TestAction::make('create')->table())
        ->set('mountedActions.0.data.title', 'Relation image create')
        ->set('mountedActions.0.data.slug', 'relation-image-create')
        ->set('mountedActions.0.data.media_url', 'https://example.com/relation-image-create.mp3')
        ->set('mountedActions.0.data.primary_image_media_reference_key', $replacement->reference_key);

    expect(ContentItem::query()->where('slug', 'relation-image-create')->exists())->toBeFalse()
        ->and(Media::query()->whereKey($replacement)->exists())->toBeTrue();

    $component
        ->callMountedAction()
        ->assertHasNoErrors();

    $created = ContentItem::query()->where('slug', 'relation-image-create')->firstOrFail();

    expect($created->primaryImageMediaAttachment()->value('media_id'))->toBe($replacement->getKey())
        ->and($created->mediaAttachments()->firstOrFail()->role)->toBe(MediaAttachmentRole::PrimaryImage)
        ->and($created->mediaAttachments()->count())->toBe(1)
        ->and($created->image_path)->toBe($replacement->path);
});

it('rolls back a relation owner insert when post-create image persistence is denied', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $replacement = ownerImageMedia('content-items/images/relation-create-denied.jpg');
    $ownerCount = $group->contentItems()->count();
    $attachmentCount = MediaAttachment::query()->count();

    $component = Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->mountAction(TestAction::make('create')->table())
        ->set('mountedActions.0.data.title', 'Denied relation create')
        ->set('mountedActions.0.data.slug', 'denied-relation-create')
        ->set('mountedActions.0.data.media_url', 'https://example.com/denied-relation-create.mp3')
        ->set('mountedActions.0.data.primary_image_media_reference_key', $replacement->reference_key);

    Gate::policy(Media::class, OwnerImageDenyAttachPolicy::class);
    try {
        $component
            ->callMountedAction()
            ->assertForbidden();
    } finally {
        Gate::policy(Media::class, CuratorMediaPolicy::class);
    }

    expect($group->contentItems()->count())->toBe($ownerCount)
        ->and(ContentItem::query()->where('slug', 'denied-relation-create')->exists())->toBeFalse()
        ->and(ContentItem::query()->where('slug', 'denied-relation-create')->value('image_path'))->toBeNull()
        ->and(MediaAttachment::query()->count())->toBe($attachmentCount)
        ->and(Media::query()->whereKey($replacement)->exists())->toBeTrue();
    Storage::disk((string) $replacement->disk)->assertExists((string) $replacement->path);
});

it('commits a relation manager episode image and record edit in one submit', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create(['title' => 'Before relation edit']);
    $current = ownerImageMedia('content-items/images/relation-edit-current.jpg');
    $replacement = ownerImageMedia('content-items/images/relation-edit-replacement.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $current,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->mountAction(TestAction::make('edit')->table($item))
        ->set('mountedActions.0.data.title', 'After relation edit')
        ->set('mountedActions.0.data.primary_image_media_reference_key', $replacement->reference_key)
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($item->refresh()->title)->toBe('After relation edit')
        ->and($item->primaryImageMediaAttachment()->value('media_id'))->toBe($replacement->getKey())
        ->and($item->image_path)->toBe($replacement->path);
});

it('leaves a relation manager episode image unchanged when edit is cancelled', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $current = ownerImageMedia('content-items/images/relation-cancel-current.jpg');
    $replacement = ownerImageMedia('content-items/images/relation-cancel-replacement.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $current,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->mountAction(TestAction::make('edit')->table($item))
        ->set('mountedActions.0.data.primary_image_media_reference_key', $replacement->reference_key)
        ->unmountAction();

    expect($item->primaryImageMediaAttachment()->value('media_id'))->toBe($current->getKey())
        ->and($item->refresh()->image_path)->toBe($current->path)
        ->and(Media::query()->whereKey($replacement)->exists())->toBeTrue();
});

it('keeps a nested relation upload permanent when the outer create is cancelled', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $ownerCount = $group->contentItems()->count();

    $component = Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->mountAction(TestAction::make('create')->table());
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $schemaComponent): bool => $schemaComponent instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component->mountAction(
        TestAction::make('launchPanel')->schemaComponent(
            (string) str($picker->getKey())->after('.'),
        ),
    );

    $nestedPicker = collect($component->instance()->getSchema('mountedActionSchema1')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $schemaComponent): bool => $schemaComponent instanceof LivewireSchemaComponent);

    assert($nestedPicker instanceof LivewireSchemaComponent);
    expect($nestedPicker->getComponent())->toBe(MediaPickerPanel::class);

    Livewire::actingAs($admin)
        ->test(MediaPickerPanel::class, $nestedPicker->getData())
        ->call('activateSource', 'upload')
        ->fillForm(['uploads' => [ownerImageUploadFixture('valid.jpg')]])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertDispatched('insert-media');

    $admitted = Media::query()->sole();

    expect($group->contentItems()->count())->toBe($ownerCount)
        ->and(MediaAttachment::query()->count())->toBe(0);
    Storage::disk((string) $admitted->disk)->assertExists((string) $admitted->path);

    $component
        ->unmountAction(false)
        ->assertActionMounted()
        ->unmountAction();

    expect($group->contentItems()->count())->toBe($ownerCount)
        ->and(ContentItem::query()->count())->toBe($ownerCount)
        ->and(MediaAttachment::query()->count())->toBe(0)
        ->and(Media::query()->whereKey($admitted)->exists())->toBeTrue();
    Storage::disk((string) $admitted->disk)->assertExists((string) $admitted->path);
});

it('clears a direct relation manager episode image through the shared persistence seam', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $current = ownerImageMedia('content-items/images/relation-clear-current.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $current,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->mountAction(TestAction::make('edit')->table($item))
        ->set('mountedActions.0.data.primary_image_media_reference_key', null)
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($item->primaryImageMediaAttachment()->exists())->toBeFalse()
        ->and($item->refresh()->image_path)->toBeNull()
        ->and(Media::query()->whereKey($current)->exists())->toBeTrue();
});

it('presents and replaces an unsafe legacy relation manager image truthfully', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $unsafe = ownerImageMedia('content-items/images/relation-unsafe-legacy.jpg');
    ownerImageMedia('content-items/images/relation-unsafe-legacy.jpg', [
        'name' => 'relation-unsafe-legacy-duplicate',
        'title' => 'Relation unsafe legacy duplicate',
    ]);
    $replacement = ownerImageMedia('content-items/images/relation-unsafe-replacement.jpg');
    $item = ContentItem::factory()->for($group)->create([
        'image_path' => $unsafe->path,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->mountAction(TestAction::make('edit')->table($item));
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $schemaComponent): bool => $schemaComponent instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    expect($picker->getOwnerChoicePresentation())
        ->directState->toBe('unsafe_legacy')
        ->unsafeFingerprint->toBeString()->not->toBe('')
        ->and($component->get('ownerImageUnsafeFingerprint'))->toBeString()->not->toBe('')
        ->and($component->get('mountedActions.0.data.relation_owner_image_baseline_token'))->toBeString()->not->toBe('');

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $replacement->getKey(),
        ]])
        ->assertSet('mountedActions.0.data.primary_image_media_reference_key', $replacement->getKey())
        ->assertSet('mountedActions.0.data.relation_owner_image_pending_identity', $replacement->getKey());

    expect($component->instance()->getSchema('mountedActionSchema0')?->getState())
        ->primary_image_media_reference_key->toBe($replacement->reference_key)
        ->relation_owner_image_pending_identity->toBe($replacement->getKey());

    $component
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($item->refresh()->image_path)->toBe($replacement->path)
        ->and($item->primaryImageMediaAttachment()->value('media_id'))->toBe($replacement->getKey())
        ->and($item->mediaAttachments()->firstOrFail()->role)->toBe(MediaAttachmentRole::PrimaryImage);
});

it('rejects a tampered relation manager owner image baseline without changing the episode', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create(['title' => 'Tamper baseline title']);
    $current = ownerImageMedia('content-items/images/relation-tamper-current.jpg');
    $replacement = ownerImageMedia('content-items/images/relation-tamper-replacement.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $current,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->mountAction(TestAction::make('edit')->table($item))
        ->set('mountedActions.0.data.title', 'Tampered title')
        ->set('mountedActions.0.data.primary_image_media_reference_key', $replacement->reference_key)
        ->set('mountedActions.0.data.relation_owner_image_baseline_token', 'tampered')
        ->callMountedAction()
        ->assertActionMounted()
        ->assertHasErrors(['mountedActions.0.data.primary_image_media_reference_key']);

    expect($item->refresh()->title)->toBe('Tamper baseline title')
        ->and($item->image_path)->toBe($current->path)
        ->and($item->primaryImageMediaAttachment()->value('media_id'))->toBe($current->getKey());
});

it('rolls back a stale relation manager edit refreshes its baseline and retries unchanged', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create(['title' => 'Before stale relation edit']);
    $original = ownerImageMedia('content-items/images/relation-stale-original.jpg');
    $concurrent = ownerImageMedia('content-items/images/relation-stale-concurrent.jpg');
    $replacement = ownerImageMedia('content-items/images/relation-stale-replacement.jpg');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($item, $original, MediaAttachmentRole::PrimaryImage, $admin);

    $component = Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->mountAction(TestAction::make('edit')->table($item))
        ->set('mountedActions.0.data.title', 'After stale relation edit')
        ->set('mountedActions.0.data.primary_image_media_reference_key', $replacement->reference_key);

    $manager->attach($item, $concurrent, MediaAttachmentRole::PrimaryImage, $admin);

    $component
        ->callMountedAction()
        ->assertActionMounted()
        ->assertHasErrors(['mountedActions.0.data.primary_image_media_reference_key'])
        ->assertSet('mountedActions.0.data.primary_image_media_reference_key', $replacement->getKey());

    expect($item->refresh()->title)->toBe('Before stale relation edit')
        ->and($item->primaryImageMediaAttachment()->value('media_id'))->toBe($concurrent->getKey())
        ->and($item->image_path)->toBe($concurrent->path);

    $component
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($item->refresh()->title)->toBe('After stale relation edit')
        ->and($item->primaryImageMediaAttachment()->value('media_id'))->toBe($replacement->getKey())
        ->and($item->image_path)->toBe($replacement->path);
});

it('commits selected owner images from every classic create surface', function (): void {
    $admin = User::factory()->admin()->create();
    $groupCover = ownerImageMedia('content-groups/covers/classic-create-cover.jpg');
    $classicEpisodeImage = ownerImageMedia('content-items/images/classic-create-episode.jpg');
    $workspaceEpisodeImage = ownerImageMedia('content-items/images/workspace-create-episode.jpg');

    Livewire::actingAs($admin)
        ->test(CreateContentGroup::class)
        ->set('data.title', 'Classic create podcast')
        ->set('data.slug', 'classic-create-podcast')
        ->set('data.cover_media_reference_key', $groupCover->reference_key)
        ->set('data.status', 'draft')
        ->call('create')
        ->assertHasNoErrors();

    $group = ContentGroup::query()->where('slug', 'classic-create-podcast')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(CreateContentItem::class)
        ->set('data.content_group_id', $group->getKey())
        ->set('data.title', 'Classic create episode')
        ->set('data.slug', 'classic-create-episode')
        ->set('data.media_url', 'https://example.com/classic-create-episode.mp3')
        ->set('data.primary_image_media_reference_key', $classicEpisodeImage->reference_key)
        ->set('data.status', 'draft')
        ->call('create')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)
        ->test(CreateEpisodeWorkspace::class)
        ->set('data.content_group_id', $group->getKey())
        ->set('data.title', 'Workspace create episode')
        ->set('data.slug', 'workspace-create-episode')
        ->set('data.media_url', 'https://example.com/workspace-create-episode.mp3')
        ->set('data.primary_image_media_reference_key', $workspaceEpisodeImage->reference_key)
        ->set('data.status', 'draft')
        ->set('data.workspaceTranscription.title', 'Workspace transcript')
        ->set('data.workspaceTranscription.status', 'draft')
        ->set('data.workspaceTranscription.transcript_markdown', '')
        ->call('create')
        ->assertHasNoErrors();

    $classicItem = ContentItem::query()->where('slug', 'classic-create-episode')->firstOrFail();
    $workspaceItem = ContentItem::query()->where('slug', 'workspace-create-episode')->firstOrFail();

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($groupCover->getKey())
        ->and($group->cover_path)->toBe($groupCover->path)
        ->and($classicItem->primaryImageMediaAttachment()->value('media_id'))->toBe($classicEpisodeImage->getKey())
        ->and($classicItem->image_path)->toBe($classicEpisodeImage->path)
        ->and($workspaceItem->primaryImageMediaAttachment()->value('media_id'))->toBe($workspaceEpisodeImage->getKey())
        ->and($workspaceItem->image_path)->toBe($workspaceEpisodeImage->path);
});

it('keeps a full-page create owner choice pending until save', function (string $surface): void {
    $admin = User::factory()->admin()->create();
    $isPodcast = $surface === 'podcast';
    $media = ownerImageMedia(
        $isPodcast
            ? 'content-groups/covers/pending-create-cover.jpg'
            : "content-items/images/pending-create-{$surface}.jpg",
    );
    $ownerCount = $isPodcast ? ContentGroup::query()->count() : ContentItem::query()->count();
    $attachmentCount = MediaAttachment::query()->count();
    $page = match ($surface) {
        'podcast' => CreateContentGroup::class,
        'classic-episode' => CreateContentItem::class,
        'workspace' => CreateEpisodeWorkspace::class,
    };
    $field = $isPodcast ? 'cover_media_reference_key' : 'primary_image_media_reference_key';

    Livewire::actingAs($admin)
        ->test($page)
        ->set("data.{$field}", $media->reference_key)
        ->assertSet("data.{$field}", $media->getKey());

    expect($isPodcast ? ContentGroup::query()->count() : ContentItem::query()->count())->toBe($ownerCount)
        ->and(MediaAttachment::query()->count())->toBe($attachmentCount)
        ->and(Media::query()->whereKey($media)->exists())->toBeTrue();
})->with([
    'podcast',
    'classic-episode',
    'workspace',
]);

it('rolls back every full-page owner insert when post-create image persistence is denied', function (string $surface): void {
    $admin = User::factory()->admin()->create();
    $isPodcast = $surface === 'podcast';
    $group = $isPodcast ? null : ContentGroup::factory()->create();
    $media = ownerImageMedia(
        $isPodcast
            ? 'content-groups/covers/denied-create-cover.jpg'
            : "content-items/images/denied-create-{$surface}.jpg",
    );
    $slug = "denied-create-{$surface}";
    $page = match ($surface) {
        'podcast' => CreateContentGroup::class,
        'classic-episode' => CreateContentItem::class,
        'workspace' => CreateEpisodeWorkspace::class,
    };
    $ownerCount = $isPodcast ? ContentGroup::query()->count() : ContentItem::query()->count();
    $relationshipCount = $group?->contentItems()->count();
    $attachmentCount = MediaAttachment::query()->count();
    $transcriptionCount = DB::table('transcriptions')->count();
    $component = Livewire::actingAs($admin)->test($page);

    if ($isPodcast) {
        $component
            ->set('data.title', 'Denied podcast create')
            ->set('data.slug', $slug)
            ->set('data.cover_media_reference_key', $media->reference_key)
            ->set('data.status', 'draft');
    } else {
        $component
            ->set('data.content_group_id', $group?->getKey())
            ->set('data.title', "Denied {$surface} create")
            ->set('data.slug', $slug)
            ->set('data.media_url', "https://example.com/{$slug}.mp3")
            ->set('data.primary_image_media_reference_key', $media->reference_key)
            ->set('data.status', 'draft');

        if ($surface === 'workspace') {
            $component
                ->set('data.workspaceTranscription.title', 'Denied workspace transcript')
                ->set('data.workspaceTranscription.status', 'draft')
                ->set('data.workspaceTranscription.transcript_markdown', '');
        }
    }

    Gate::policy(Media::class, OwnerImageDenyAttachPolicy::class);
    try {
        $component
            ->call('create')
            ->assertForbidden();
    } finally {
        Gate::policy(Media::class, CuratorMediaPolicy::class);
    }

    expect($isPodcast ? ContentGroup::query()->count() : ContentItem::query()->count())->toBe($ownerCount)
        ->and(($isPodcast ? ContentGroup::query() : ContentItem::query())->where('slug', $slug)->exists())->toBeFalse()
        ->and(($isPodcast ? ContentGroup::query() : ContentItem::query())
            ->where('slug', $slug)
            ->value($isPodcast ? 'cover_path' : 'image_path'))->toBeNull()
        ->and($group?->contentItems()->count())->toBe($relationshipCount)
        ->and(MediaAttachment::query()->count())->toBe($attachmentCount)
        ->and(DB::table('transcriptions')->count())->toBe($transcriptionCount)
        ->and(Media::query()->whereKey($media)->exists())->toBeTrue();
    Storage::disk((string) $media->disk)->assertExists((string) $media->path);
})->with([
    'podcast',
    'classic-episode',
    'workspace',
]);

it('rolls back and refreshes a stale owner image save on every full edit surface', function (string $surface): void {
    $admin = User::factory()->admin()->create();
    $directory = $surface === 'podcast' ? 'content-groups/covers' : 'content-items/images';
    $original = ownerImageMedia("{$directory}/{$surface}-stale-original.jpg");
    $concurrent = ownerImageMedia("{$directory}/{$surface}-stale-concurrent.jpg");
    $replacement = ownerImageMedia("{$directory}/{$surface}-stale-replacement.jpg");
    $manager = app(MediaAttachmentManager::class);

    if ($surface === 'podcast') {
        $record = ContentGroup::factory()->create(['title' => 'Before stale podcast save']);
        $role = MediaAttachmentRole::Cover;
        $field = 'cover_media_reference_key';
        $page = EditContentGroup::class;
    } else {
        $record = ContentItem::factory()->for(ContentGroup::factory())->create(['title' => 'Before stale episode save']);
        $role = MediaAttachmentRole::PrimaryImage;
        $field = 'primary_image_media_reference_key';
        $page = $surface === 'workspace' ? EditEpisodeWorkspace::class : EditContentItem::class;
    }

    $manager->attach($record, $original, $role, $admin);
    $component = Livewire::actingAs($admin)
        ->test($page, ['record' => $record->getRouteKey()])
        ->set('data.title', "After stale {$surface} save")
        ->set("data.{$field}", $replacement->reference_key);

    $manager->attach($record, $concurrent, $role, $admin);

    $component
        ->call('save')
        ->assertHasErrors(["data.{$field}"])
        ->assertSet("data.{$field}", $replacement->getKey());

    expect($record->refresh()->title)->toStartWith('Before stale')
        ->and($record->mediaAttachments()->where('role', $role->value)->value('media_id'))->toBe($concurrent->getKey())
        ->and($record->getAttribute($role === MediaAttachmentRole::Cover ? 'cover_path' : 'image_path'))->toBe($concurrent->path);

    $component
        ->call('save')
        ->assertHasNoErrors();

    expect($record->refresh()->title)->toBe("After stale {$surface} save")
        ->and($record->mediaAttachments()->where('role', $role->value)->value('media_id'))->toBe($replacement->getKey())
        ->and($record->getAttribute($role === MediaAttachmentRole::Cover ? 'cover_path' : 'image_path'))->toBe($replacement->path);
})->with([
    'podcast',
    'classic-episode',
    'workspace',
]);

it('renders shared owner state in dedicated localized owner and player sections', function (string $locale): void {
    app()->setLocale($locale);
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();

    Livewire::actingAs($admin)
        ->test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->assertSee(__('admin.sections.podcast_cover'))
        ->assertSee('data-testid="owner-image-choice-state"', false);

    foreach ([EditContentItem::class, EditEpisodeWorkspace::class] as $page) {
        Livewire::actingAs($admin)
            ->test($page, ['record' => $item->getRouteKey()])
            ->assertSee(__('admin.sections.episode_image'))
            ->assertSee(__('admin.sections.player_embed'))
            ->assertSee('data-testid="owner-image-choice-state"', false);
    }
})->with(['he', 'en']);

it('uses localized Episodes table and Episode create edit relation modal headings', function (string $locale): void {
    app()->setLocale($locale);
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $component = Livewire::actingAs($admin)
        ->test(ContentItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditContentGroup::class,
        ])
        ->assertSee(__('admin.relations.episodes'))
        ->mountAction(TestAction::make('create')->table())
        ->assertMountedActionModalSee(__('admin.modals.create_episode'))
        ->assertMountedActionModalSee('data-testid="owner-image-choice-state"', false)
        ->unmountAction()
        ->mountAction(TestAction::make('edit')->table($item))
        ->assertMountedActionModalSee(__('admin.modals.edit_episode'))
        ->assertMountedActionModalSee('data-testid="owner-image-choice-state"', false);

    expect($component->instance()->getMountedAction()?->getModalHeading())
        ->toBe(__('admin.modals.edit_episode'));
})->with(['he', 'en']);

it('commits one gallery choice only from the primary owner submit', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $current = ownerImageMedia('content-items/images/gallery-current.jpg');
    $replacement = ownerImageMedia('content-items/images/gallery-replacement.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $current,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    $component = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item));
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $replacement->getKey(),
        ]])
        ->assertSet('mountedActions.0.data.primary_image_media_reference_key', $replacement->getKey());

    expect($item->primaryImageMediaAttachment()->value('media_id'))->toBe($current->getKey());

    $component
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($item->primaryImageMediaAttachment()->value('media_id'))->toBe($replacement->getKey())
        ->and($item->refresh()->image_path)->toBe($replacement->path)
        ->and($item->mediaAttachments()
            ->where('role', MediaAttachmentRole::PrimaryImage->value)
            ->count())->toBe(1);
});

it('rejects unauthorized and unselectable owner image submissions', function (): void {
    $admin = User::factory()->admin()->create();
    $moderator = User::factory()->moderator()->create();
    $group = ContentGroup::factory()->create();
    $current = ownerImageMedia('content-groups/covers/rejected-current.jpg');
    $replacement = ownerImageMedia('content-groups/covers/rejected-replacement.jpg');
    $blocked = ownerImageMedia('content-groups/covers/rejected-blocked.svg', [
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
    ], store: false);
    Storage::disk('public')->put($blocked->path, '<svg><script>alert(1)</script></svg>');
    app(MediaAttachmentManager::class)->attach(
        $group,
        $current,
        MediaAttachmentRole::Cover,
        $admin,
    );

    expect(fn () => app(MediaAttachmentFormState::class)->persist(
        $group,
        $replacement->reference_key,
        MediaAttachmentRole::Cover,
        $moderator,
        expectedMediaId: $current->getKey(),
        expectedLegacyPath: $current->path,
        enforceExpectedIdentity: true,
    ))->toThrow(AuthorizationException::class);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($current->getKey());

    $component = Livewire::actingAs($admin)
        ->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group));
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $blocked->getKey(),
        ]])
        ->assertStatus(422);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($current->getKey())
        ->and($group->refresh()->cover_path)->toBe($current->path);
});

it('rejects an unauthorized attach through the mounted canonical owner action wiring', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $current = ownerImageMedia('content-groups/covers/action-auth-current.jpg');
    $replacement = ownerImageMedia('content-groups/covers/action-auth-replacement.jpg');
    app(MediaAttachmentManager::class)->attach(
        $group,
        $current,
        MediaAttachmentRole::Cover,
        $admin,
    );

    $component = Livewire::actingAs($admin)
        ->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group))
        ->set('mountedActions.0.data.cover_media_reference_key', $replacement->reference_key);

    Gate::policy(Media::class, OwnerImageDenyAttachPolicy::class);
    try {
        $component
            ->call('callMountedAction')
            ->assertForbidden();
    } finally {
        Gate::policy(Media::class, CuratorMediaPolicy::class);
    }

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($current->getKey())
        ->and($group->refresh()->cover_path)->toBe($current->path);
});

it('reuses the same integrated workspace on podcast edit classic episode edit and episode workspace', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $cover = ownerImageMedia('content-groups/covers/edit-surface-cover.jpg');
    $episode = ownerImageMedia('content-items/images/edit-surface-episode.jpg');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $cover, MediaAttachmentRole::Cover, $admin);
    $manager->attach($item, $episode, MediaAttachmentRole::PrimaryImage, $admin);

    foreach ([
        [EditContentGroup::class, $group, 'chooseContentGroupCover'],
        [EditContentItem::class, $item, 'chooseContentItemImage'],
        [EditEpisodeWorkspace::class, $item, 'chooseContentItemImage'],
    ] as [$pageClass, $record, $actionName]) {
        Livewire::actingAs($admin)
            ->test($pageClass, ['record' => $record->getRouteKey()])
            ->mountAction($actionName)
            ->assertMountedActionModalSee('data-testid="owner-image-choice-state"', false)
            ->assertMountedActionModalSee('data-testid="media-picker"', false)
            ->assertHasNoErrors();
    }
});

it('removes only the direct image through the root workspace action', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $media = ownerImageMedia('content-groups/covers/remove-through-workspace.jpg');
    app(MediaAttachmentManager::class)->attach(
        $group,
        $media,
        MediaAttachmentRole::Cover,
        $admin,
    );

    $component = Livewire::actingAs($admin)
        ->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group));
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'chooseAutomaticOwnerImage', [[]])
        ->assertSet('mountedActions.0.data.cover_media_reference_key', null);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($media->getKey());

    $component
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($group->coverMediaAttachment()->exists())->toBeFalse()
        ->and($group->refresh()->cover_path)->toBeNull()
        ->and(Media::query()->whereKey($media)->exists())->toBeTrue()
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
});

it('keeps external import separate from the canonical owner modal', function (): void {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/import-me.jpg',
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('contentItemImageDetails')->table($item));

    expect($component->instance()->getMountedAction()?->getExtraModalFooterActions())->toBe([]);
    Queue::assertNothingPushed();

    $component
        ->unmountAction()
        ->mountAction(TestAction::make('downloadExternalImage')->table($item))
        ->callMountedAction()
        ->assertHasNoErrors();

    Queue::assertPushed(
        DownloadExternalContentItemImage::class,
        fn (DownloadExternalContentItemImage $job): bool => $job->contentItemId === $item->getKey()
            && $job->userId === $admin->getKey()
            && $job->overwrite === false
            && $job->expectedUrl === $item->external_thumbnail_url,
    );

    expect($item->primaryImageMediaAttachment()->exists())->toBeFalse()
        ->and($item->refresh()->image_path)->toBeNull();
});

it('identifies external and inherited effective sources without pretending an external URL is Media', function (): void {
    $admin = User::factory()->admin()->create();
    $cover = ownerImageMedia('content-groups/covers/inherited-cover.jpg', [
        'title' => 'Inherited podcast cover',
    ]);
    $group = ContentGroup::factory()->create();
    app(MediaAttachmentManager::class)->attach(
        $group,
        $cover,
        MediaAttachmentRole::Cover,
        $admin,
    );
    $external = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/episode.jpg',
    ]);
    $inherited = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => null,
    ]);

    $this->actingAs($admin);
    $externalPresentation = app(OwnerImagePresenter::class)->present(
        $external,
        MediaAttachmentRole::PrimaryImage,
    );
    $inheritedPresentation = app(OwnerImagePresenter::class)->present(
        $inherited,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($externalPresentation)
        ->effectiveSource->toBe('external_url')
        ->media->toBeNull()
        ->canImportExternal->toBeTrue()
        ->and($externalPresentation->effectivePreviewUrl)->toBe('https://cdn.example.test/episode.jpg')
        ->and($inheritedPresentation)
        ->effectiveSource->toBe('inherited_podcast_cover')
        ->canImportExternal->toBeFalse()
        ->and($inheritedPresentation->media['id'])->toBe($cover->getKey())
        ->and($inheritedPresentation->media['title'])->toBe('Inherited podcast cover');
});

it('identifies configured and global default Media sources', function (): void {
    $admin = User::factory()->admin()->create();
    $configured = ownerImageMedia('default-images/configured-group.jpg');
    $global = ownerImageMedia('default-images/global-fallback.jpg');
    $configuredGroup = ContentGroup::factory()->create();

    saveOwnerImageDefaultSettings([
        'content_group' => [
            'mode' => 'custom',
            'path' => $configured->path,
            'media_reference_key' => $configured->reference_key,
        ],
    ]);

    $this->actingAs($admin);
    $configuredPresentation = app(OwnerImagePresenter::class)->present(
        $configuredGroup,
        MediaAttachmentRole::Cover,
    );

    saveOwnerImageDefaultSettings([
        'content_item' => [
            'mode' => 'inherit',
            'path' => null,
            'media_reference_key' => null,
        ],
        'global' => [
            'mode' => 'custom',
            'path' => $global->path,
            'media_reference_key' => $global->reference_key,
        ],
    ]);
    $globalItem = ContentItem::factory()
        ->for(ContentGroup::factory())
        ->create(['external_thumbnail_url' => null]);
    $globalPresentation = app(OwnerImagePresenter::class)->present(
        $globalItem,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($configuredPresentation)
        ->effectiveSource->toBe('configured_default')
        ->and($configuredPresentation->media['id'])->toBe($configured->getKey())
        ->and($globalPresentation)
        ->effectiveSource->toBe('global_default')
        ->and($globalPresentation->media['id'])->toBe($global->getKey());
});

it('identifies an empty automatic fallback without inventing Media metadata', function (): void {
    $item = ContentItem::factory()
        ->for(ContentGroup::factory())
        ->create(['external_thumbnail_url' => null]);

    $presentation = app(OwnerImagePresenter::class)->present(
        $item,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($presentation)
        ->effectiveSource->toBe('none')
        ->effectivePreviewUrl->toBeNull()
        ->media->toBeNull()
        ->brokenDirect->toBeFalse()
        ->canRemoveDirect->toBeFalse();
});

it('shows a broken direct association separately from the effective fallback', function (): void {
    $admin = User::factory()->admin()->create();
    $missing = ownerImageMedia('content-items/images/missing-direct.jpg', [
        'title' => 'Missing direct row file',
    ]);
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/fallback.jpg',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $missing,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );
    Storage::disk('public')->delete($missing->path);

    $this->actingAs($admin);
    $presentation = app(OwnerImagePresenter::class)->present(
        $item,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($presentation)
        ->effectiveSource->toBe('external_url')
        ->effectivePreviewUrl->toBe('https://cdn.example.test/fallback.jpg')
        ->brokenDirect->toBeTrue()
        ->canRemoveDirect->toBeTrue()
        ->and($presentation->warningCodes)->toContain('missing_file')
        ->and($presentation->media['id'])->toBe($missing->getKey())
        ->and($presentation->media['preview_url'])->toBeNull()
        ->and($presentation->media['review_url'])->toBe(MediaResource::getUrl('edit', ['record' => $missing]));
});

it('keeps audience denial and unsafe SVG diagnostics separate from the effective fallback', function (): void {
    $private = ownerImageMedia('private/private-direct.jpg', [
        'disk' => 'local',
        'directory' => 'private',
        'visibility' => 'private',
    ]);
    $svg = ownerImageMedia('content-items/images/unsafe-direct.svg', [
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
    ], store: false);
    Storage::disk('public')->put(
        $svg->path,
        '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>',
    );

    foreach ([
        [$private, 'audience_denied'],
        [$svg, 'unsanitized_svg'],
    ] as [$media, $warning]) {
        $item = ContentItem::factory()
            ->for(ContentGroup::factory())
            ->create([
                'external_thumbnail_url' => 'https://cdn.example.test/d01-fallback.jpg',
                'image_path' => $media->path,
            ]);
        MediaAttachment::factory()->create([
            'media_id' => $media->getKey(),
            'attachable_type' => 'content_item',
            'attachable_id' => $item->getKey(),
            'role' => MediaAttachmentRole::PrimaryImage,
            'position' => 0,
        ]);

        $presentation = app(OwnerImagePresenter::class)->present(
            $item,
            MediaAttachmentRole::PrimaryImage,
        );

        expect($presentation)
            ->effectiveSource->toBe('external_url')
            ->effectivePreviewUrl->toBe('https://cdn.example.test/d01-fallback.jpg')
            ->brokenDirect->toBeTrue()
            ->and($presentation->warningCodes)->toContain($warning)
            ->and($presentation->media['id'])->toBe($media->getKey())
            ->and($presentation->media['preview_url'])->toBeNull();
    }
});

it('refreshes canonical Media metadata when the detail workspace opens', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $media = ownerImageMedia('content-groups/covers/before-refresh.jpg', [
        'title' => 'Before refresh',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $group,
        $media,
        MediaAttachmentRole::Cover,
        $admin,
    );

    Storage::disk('public')->put('content-groups/covers/after-refresh.jpg', 'updated-image-bytes');
    Storage::disk('public')->delete($media->path);
    $media->forceFill([
        'title' => 'After refresh',
        'name' => 'after-refresh',
        'path' => 'content-groups/covers/after-refresh.jpg',
    ])->saveQuietly();

    $this->actingAs($admin);
    $presentation = app(OwnerImagePresenter::class)->present(
        $group,
        MediaAttachmentRole::Cover,
    );

    expect($presentation)
        ->effectiveSource->toBe('direct_media')
        ->and($presentation->media['title'])->toBe('After refresh')
        ->and($presentation->media['stored_filename'])->toBe('after-refresh.jpg')
        ->and($presentation->media['preview_url'])->toBe(route('admin.media-files.view', ['media' => $media]));
});

it('rechecks direct file availability when the detail workspace reopens', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $media = ownerImageMedia('content-groups/covers/rechecked-file.jpg');
    app(MediaAttachmentManager::class)->attach(
        $group,
        $media,
        MediaAttachmentRole::Cover,
        $admin,
    );

    $this->actingAs($admin);
    $presenter = app(OwnerImagePresenter::class);
    $available = $presenter->present($group, MediaAttachmentRole::Cover);
    Storage::disk('public')->delete($media->path);
    $missing = app(OwnerImagePresenter::class)->present($group, MediaAttachmentRole::Cover);

    expect($available)
        ->effectiveSource->toBe('direct_media')
        ->brokenDirect->toBeFalse()
        ->and($available->warningCodes)->not->toContain('missing_file')
        ->and($available->media['preview_url'])->toBe(route('admin.media-files.view', ['media' => $media]))
        ->and($missing)
        ->brokenDirect->toBeTrue()
        ->and($missing->warningCodes)->toContain('missing_file')
        ->and($missing->media['preview_url'])->toBeNull();
});

it('mounts the shared workspace for a broken direct image while keeping fallback and warning separate', function (): void {
    $admin = User::factory()->admin()->create();
    $missing = ownerImageMedia('content-items/images/action-missing.jpg');
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/action-fallback.jpg',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $missing,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );
    Storage::disk('public')->delete($missing->path);

    Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item))
        ->assertMountedActionModalSee(__('admin.owner_image.sources.external_url'))
        ->assertMountedActionModalSee('data-testid="owner-image-choice-state"', false)
        ->assertHasNoErrors();
});

it('mounts and detaches an attachment whose Media row disappeared', function (): void {
    $admin = User::factory()->admin()->create();
    $missing = ownerImageMedia('content-items/images/missing-media-row.jpg');
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/missing-row-fallback.jpg',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $missing,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    DB::statement('PRAGMA defer_foreign_keys = ON');
    DB::table('curator')->where('id', $missing->getKey())->delete();

    $component = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item))
        ->assertMountedActionModalSee(__('admin.owner_image.sources.external_url'))
        ->assertMountedActionModalSee('data-testid="owner-image-choice-state"', false)
        ->assertHasNoErrors();

    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'chooseAutomaticOwnerImage', [[]])
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($item->primaryImageMediaAttachment()->exists())->toBeFalse()
        ->and($item->refresh()->image_path)->toBeNull();
});

it('halts a rowless stale automatic choice refreshes its authenticated baseline and detaches only on unchanged retry', function (): void {
    $admin = User::factory()->admin()->create();
    $missing = ownerImageMedia('content-items/images/rowless-stale-a.jpg', [
        'title' => 'Rowless original A',
    ]);
    $concurrent = ownerImageMedia('content-items/images/rowless-stale-b.jpg', [
        'title' => 'Rowless concurrent B',
    ]);
    $item = ContentItem::factory()->for(ContentGroup::factory())->create();
    app(MediaAttachmentManager::class)->attach(
        $item,
        $missing,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    DB::statement('PRAGMA defer_foreign_keys = ON');
    DB::table('curator')->where('id', $missing->getKey())->delete();

    $component = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item));
    $originalToken = $component->get('mountedActions.0.data.owner_image_baseline_token');
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'chooseAutomaticOwnerImage', [[]])
        ->assertSet('mountedActions.0.data.primary_image_media_reference_key', null);

    DB::table('media_attachments')
        ->where('attachable_type', 'content_item')
        ->where('attachable_id', $item->getKey())
        ->where('role', MediaAttachmentRole::PrimaryImage->value)
        ->update(['media_id' => $concurrent->getKey()]);
    DB::table('content_items')
        ->where('id', $item->getKey())
        ->update(['image_path' => $concurrent->path]);

    expect(DB::table('content_items')->where('id', $item->getKey())->value('image_path'))
        ->toBe($concurrent->path);

    $component
        ->callMountedAction()
        ->assertActionMounted()
        ->assertHasErrors(['mountedActions.0.data.primary_image_media_reference_key'])
        ->assertSet('mountedActions.0.data.primary_image_media_reference_key', null)
        ->assertMountedActionModalSee('Rowless concurrent B')
        ->assertMountedActionModalSee($concurrent->reference_key);

    $refreshedToken = $component->get('mountedActions.0.data.owner_image_baseline_token');
    $refreshedBaseline = json_decode(
        Crypt::decryptString($refreshedToken),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($refreshedToken)->toBeString()->not->toBe($originalToken)
        ->and($refreshedBaseline['expected_media_id'])->toBe($concurrent->getKey())
        ->and($refreshedBaseline['expected_legacy_path'])->toBe($concurrent->path)
        ->and($refreshedBaseline['unsafe_fingerprint'])->toBeNull()
        ->and($item->primaryImageMediaAttachment()->value('media_id'))->toBe($concurrent->getKey())
        ->and($item->refresh()->image_path)->toBe($concurrent->path);

    $component
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($item->primaryImageMediaAttachment()->exists())->toBeFalse()
        ->and($item->refresh()->image_path)->toBeNull();
});

it('keeps unrelated unsafe repair runtime failures distinct from owner image stale conflicts', function (): void {
    $admin = User::factory()->admin()->create();
    $missing = ownerImageMedia('content-items/images/rowless-runtime-a.jpg');
    $item = ContentItem::factory()->for(ContentGroup::factory())->create();
    app(MediaAttachmentManager::class)->attach(
        $item,
        $missing,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    DB::statement('PRAGMA defer_foreign_keys = ON');
    DB::table('curator')->where('id', $missing->getKey())->delete();
    $item = $item->fresh();
    $fingerprint = app(MediaAttachmentFormState::class)
        ->diagnostic($item, MediaAttachmentRole::PrimaryImage)?->fingerprint;

    expect($fingerprint)->toBeString()->not->toBe('');

    try {
        app(LegacyOwnerMediaRepairer::class)->replace(
            $item,
            MediaAttachmentRole::PrimaryImage,
            'MISSING-REPLACEMENT',
            $fingerprint,
            $admin,
        );
        $this->fail('The unavailable replacement should remain a distinct runtime failure.');
    } catch (RuntimeException $exception) {
        expect($exception)
            ->not->toBeInstanceOf(OwnerImageChangedException::class)
            ->and($exception->getMessage())->toBe('The requested replacement media is unavailable.');
    }
});

it('rejects a stale normal replacement and preserves the newer owner image', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $current = ownerImageMedia('content-groups/covers/current.jpg');
    $newer = ownerImageMedia('content-groups/covers/newer.jpg');
    $staleTarget = ownerImageMedia('content-groups/covers/stale-target.jpg');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $current, MediaAttachmentRole::Cover, $admin);

    $this->actingAs($admin);
    $snapshot = app(OwnerImagePresenter::class)->present($group, MediaAttachmentRole::Cover);
    $manager->attach($group, $newer, MediaAttachmentRole::Cover, $admin);

    expect(fn () => app(MediaAttachmentFormState::class)->persist(
        $group,
        $staleTarget->reference_key,
        MediaAttachmentRole::Cover,
        $admin,
        expectedMediaId: $snapshot->expectedMediaId,
        expectedLegacyPath: $snapshot->expectedLegacyPath,
        enforceExpectedIdentity: true,
    ))->toThrow(OwnerImageChangedException::class);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($newer->getKey())
        ->and($group->refresh()->cover_path)->toBe($newer->path);
});

it('halts a stale owner modal submit refreshes evidence and commits the pending choice once on retry', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $original = ownerImageMedia('content-groups/covers/stale-modal-a.jpg', ['title' => 'Original A']);
    $concurrent = ownerImageMedia('content-groups/covers/stale-modal-b.jpg', ['title' => 'Concurrent B']);
    $pending = ownerImageMedia('content-groups/covers/stale-modal-c.jpg', ['title' => 'Pending C']);
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $original, MediaAttachmentRole::Cover, $admin);

    $component = Livewire::actingAs($admin)
        ->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group));
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    $component
        ->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $pending->getKey(),
        ]])
        ->assertSet('mountedActions.0.data.cover_media_reference_key', $pending->getKey());

    $manager->attach($group, $concurrent, MediaAttachmentRole::Cover, $admin);

    $component
        ->callMountedAction()
        ->assertActionMounted()
        ->assertHasErrors(['mountedActions.0.data.cover_media_reference_key'])
        ->assertSet('mountedActions.0.data.cover_media_reference_key', $pending->getKey())
        ->assertMountedActionModalSee('Concurrent B');
    $refreshedBaseline = json_decode(
        Crypt::decryptString($component->get('mountedActions.0.data.owner_image_baseline_token')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($concurrent->getKey())
        ->and($group->refresh()->cover_path)->toBe($concurrent->path)
        ->and($refreshedBaseline['expected_media_id'])->toBe($concurrent->getKey())
        ->and($refreshedBaseline['expected_legacy_path'])->toBe($concurrent->path)
        ->and($refreshedBaseline['unsafe_fingerprint'])->toBeNull();

    $component
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($pending->getKey())
        ->and($group->refresh()->cover_path)->toBe($pending->path)
        ->and($group->mediaAttachments()
            ->where('role', MediaAttachmentRole::Cover->value)
            ->count())->toBe(1);
});

it('rejects a stale direct removal and preserves the newer owner image', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $current = ownerImageMedia('content-groups/covers/remove-current.jpg');
    $newer = ownerImageMedia('content-groups/covers/remove-newer.jpg');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $current, MediaAttachmentRole::Cover, $admin);

    $this->actingAs($admin);
    $snapshot = app(OwnerImagePresenter::class)->present($group, MediaAttachmentRole::Cover);
    $manager->attach($group, $newer, MediaAttachmentRole::Cover, $admin);

    expect(fn () => app(MediaAttachmentFormState::class)->detachDirectIfUnchanged(
        $group,
        MediaAttachmentRole::Cover,
        $admin,
        $snapshot->expectedMediaId,
        $snapshot->expectedLegacyPath,
    ))->toThrow(OwnerImageChangedException::class);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($newer->getKey())
        ->and($group->refresh()->cover_path)->toBe($newer->path);
});

it('ships the Package 4 workspace labels and feedback in Hebrew and English', function (): void {
    $en = require lang_path('en/admin.php');
    $he = require lang_path('he/admin.php');

    foreach ([
        'owner_image.heading',
        'owner_image.sources.direct_media',
        'owner_image.sources.inherited_podcast_cover',
        'owner_image.sources.external_url',
        'owner_image.sources.configured_default',
        'owner_image.broken_direct_body',
        'owner_image.metadata.original_filename',
        'owner_image.metadata.stored_filename',
        'owner_image.actions.change_image',
        'owner_image.actions.use_automatic_image',
        'owner_image.actions.copy_filename',
        'owner_image.copy_success',
        'validation.owner_image_changed',
    ] as $key) {
        expect(data_get($en, $key))->toBeString()->not->toBe('')
            ->and(data_get($he, $key))->toBeString()->not->toBe('');
    }
});

it('loads the bounded owner image translation contract directly with exact locale parity', function (): void {
    $manifest = taskEightOwnerTranslationManifest();
    $expectedKeys = collect($manifest)->sort()->values()->all();
    $localeValues = [];

    expect($manifest)->toContain(
        'media_library.inline_batch_help',
        'media_library.add_and_choose',
    );

    foreach (['en', 'he'] as $locale) {
        $directFile = Arr::dot(require lang_path("{$locale}/admin.php"));
        $actualKeys = collect($directFile)
            ->only($manifest)
            ->keys()
            ->sort()
            ->values()
            ->all();

        expect($actualKeys)->toBe($expectedKeys);

        foreach ($manifest as $key) {
            $value = $directFile[$key] ?? null;

            expect($value)
                ->toBeString()
                ->not->toBe('')
                ->not->toBe("admin.{$key}")
                ->not->toMatch('/<[^>]+>/')
                ->and(app('translator')->hasForLocale("admin.{$key}", $locale))->toBeTrue()
                ->and(app('translator')->get("admin.{$key}", [], $locale, false))->toBe($value);

            $localeValues[$locale][$key] = $value;
        }
    }

    foreach ($manifest as $key) {
        expect(taskEightTranslationPlaceholders($localeValues['he'][$key]))
            ->toBe(taskEightTranslationPlaceholders($localeValues['en'][$key]));
    }

    expect($localeValues['en'])->toMatchArray([
        'owner_image.actions.add_podcast_cover' => 'Podcast · Cover — Add',
        'owner_image.actions.change_podcast_cover' => 'Podcast · Cover — Change',
        'owner_image.actions.save_podcast_cover' => 'Save podcast cover',
        'owner_image.actions.add_episode_image' => 'Episode · Primary image — Add',
        'owner_image.actions.change_episode_image' => 'Episode · Primary image — Change',
        'owner_image.actions.save_episode_image' => 'Save episode image',
        'owner_image.choice.direct_heading' => 'Direct image',
        'owner_image.choice.shown_now_heading' => 'Shown now',
        'owner_image.choice.pending_heading' => 'Pending choice',
        'media_library.acquisition_permanence_inline' => 'Uploaded or imported images enter the library immediately, and Cancel does not remove them. To remove them, go to :link. A multi-upload does not choose the owner image — choose one image and save.',
    ])->and($localeValues['he'])->toMatchArray([
        'owner_image.actions.add_podcast_cover' => 'פודקאסט · עטיפה — הוספה',
        'owner_image.actions.change_podcast_cover' => 'פודקאסט · עטיפה — החלפה',
        'owner_image.actions.save_podcast_cover' => 'שמירת עטיפת הפודקאסט',
        'owner_image.actions.add_episode_image' => 'פרק · תמונה ראשית — הוספה',
        'owner_image.actions.change_episode_image' => 'פרק · תמונה ראשית — החלפה',
        'owner_image.actions.save_episode_image' => 'שמירת תמונת הפרק',
        'owner_image.choice.direct_heading' => 'תמונה ישירה',
        'owner_image.choice.shown_now_heading' => 'מוצגת עכשיו',
        'owner_image.choice.pending_heading' => 'בחירה ממתינה',
        'media_library.acquisition_permanence_inline' => 'תמונות שמועלות או מיובאות נכנסות לספרייה מיד, וביטול לא מסיר אותן. להסרה יש לעבור אל :link. העלאה מרובה לא בוחרת תמונת בעלים — יש לבחור תמונה אחת ולשמור.',
    ]);
});

it('mounts exact localized podcast and episode add and change owner actions', function (
    string $locale,
    string $ownerType,
    bool $hasDirectAttachment,
    string $expectedHeading,
    string $expectedSubmitLabel,
    string $expectedCancelLabel,
): void {
    app()->setLocale($locale);
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $record = $ownerType === 'podcast'
        ? $group
        : ContentItem::factory()->for($group)->create();
    $role = $ownerType === 'podcast'
        ? MediaAttachmentRole::Cover
        : MediaAttachmentRole::PrimaryImage;
    $actionName = $ownerType === 'podcast'
        ? 'chooseContentGroupCover'
        : 'chooseContentItemImage';
    $page = $ownerType === 'podcast'
        ? ListContentGroups::class
        : ListContentItems::class;

    if ($hasDirectAttachment) {
        $path = $ownerType === 'podcast'
            ? 'content-groups/covers/task-eight-action.jpg'
            : 'content-items/images/task-eight-action.jpg';
        app(MediaAttachmentManager::class)->attach(
            $record,
            ownerImageMedia($path),
            $role,
            $admin,
        );
    }

    $component = Livewire::actingAs($admin)
        ->test($page)
        ->mountAction(TestAction::make($actionName)->table($record))
        ->assertMountedActionModalSee($expectedHeading)
        ->assertMountedActionModalSee($expectedSubmitLabel)
        ->assertMountedActionModalSee($expectedCancelLabel)
        ->assertMountedActionModalDontSee('admin.owner_image.sources.')
        ->assertMountedActionModalDontSee('admin.owner_image.warnings.')
        ->assertMountedActionModalDontSee('admin.media_library.');
    $action = $component->instance()->getMountedAction();

    expect($action?->getLabel())->toBe($expectedHeading)
        ->and($action?->getModalHeading())->toBe($expectedHeading)
        ->and($action?->getModalSubmitAction()?->getLabel())->toBe($expectedSubmitLabel)
        ->and($action?->getModalCancelAction()?->getLabel())->toBe($expectedCancelLabel);
})->with([
    'English podcast add' => ['en', 'podcast', false, 'Podcast · Cover — Add', 'Save podcast cover', 'Cancel'],
    'English podcast change' => ['en', 'podcast', true, 'Podcast · Cover — Change', 'Save podcast cover', 'Cancel'],
    'English episode add' => ['en', 'episode', false, 'Episode · Primary image — Add', 'Save episode image', 'Cancel'],
    'English episode change' => ['en', 'episode', true, 'Episode · Primary image — Change', 'Save episode image', 'Cancel'],
    'Hebrew podcast add' => ['he', 'podcast', false, 'פודקאסט · עטיפה — הוספה', 'שמירת עטיפת הפודקאסט', 'ביטול'],
    'Hebrew podcast change' => ['he', 'podcast', true, 'פודקאסט · עטיפה — החלפה', 'שמירת עטיפת הפודקאסט', 'ביטול'],
    'Hebrew episode add' => ['he', 'episode', false, 'פרק · תמונה ראשית — הוספה', 'שמירת תמונת הפרק', 'ביטול'],
    'Hebrew episode change' => ['he', 'episode', true, 'פרק · תמונה ראשית — החלפה', 'שמירת תמונת הפרק', 'ביטול'],
]);

it('renders direct shown pending and commit boundary state from real owner evidence', function (
    string $locale,
    string $state,
    string $expectedDirectState,
    string $expectedShownSource,
    string $expectedPendingState,
): void {
    app()->setLocale($locale);
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/task-eight-fallback.jpg',
    ]);
    $direct = ownerImageMedia('content-items/images/task-eight-direct.jpg', [
        'title' => 'Direct mixed תמונה',
    ]);
    $replacement = ownerImageMedia('content-items/images/task-eight-replacement.jpg', [
        'title' => 'Replacement mixed תמונה',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $direct,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    if ($state === 'broken') {
        Storage::disk('public')->delete($direct->path);
    }

    $component = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item));
    $picker = collect($component->instance()->getSchema('mountedActionSchema0')?->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof PathCuratorPicker);

    assert($picker instanceof PathCuratorPicker);

    if ($state === 'replacement') {
        $component->call('callSchemaComponentMethod', $picker->getKey(), 'updateState', [[
            'mediaId' => $replacement->getKey(),
        ]])->call('$refresh');
    }

    if ($state === 'automatic_fallback') {
        $component
            ->call('callSchemaComponentMethod', $picker->getKey(), 'chooseAutomaticOwnerImage', [[]])
            ->call('$refresh');
    }

    $submitLabel = __('admin.owner_image.actions.save_episode_image');
    $cancelLabel = __('admin.actions.cancel');

    $component
        ->assertMountedActionModalSee('data-testid="owner-image-direct-state"', false)
        ->assertMountedActionModalSee('data-testid="owner-image-shown-now-state"', false)
        ->assertMountedActionModalSee('data-testid="owner-image-pending-state"', false)
        ->assertMountedActionModalSee(rtrim(__("admin.owner_image.choice.direct_states.{$expectedDirectState}"), '.'))
        ->assertMountedActionModalSee(rtrim(__("admin.owner_image.sources.{$expectedShownSource}"), '.'))
        ->assertMountedActionModalSee(rtrim(__("admin.owner_image.choice.pending_states.{$expectedPendingState}"), '.'));
    $modalHtml = $component->getMountedActionModalHtml();
    $boundaryHtml = taskEightElementHtml($modalHtml, 'owner-image-commit-boundary');

    expect($boundaryHtml)
        ->toContain(e(__('admin.owner_image.choice.commit_boundary.commit', ['action' => $submitLabel])))
        ->toContain(e(__('admin.owner_image.choice.commit_boundary.cancel', ['action' => $cancelLabel])))
        ->not->toContain(e(__('admin.media_library.acquisition_permanence_inline')));

    if ($state === 'replacement') {
        $component->assertMountedActionModalSee('Replacement mixed תמונה');
    }

    if ($state === 'broken') {
        $brokenHtml = taskEightElementHtml($modalHtml, 'owner-image-broken-state');

        expect(taskEightHasDirectedExactText($brokenHtml, 'ltr', (string) $direct->reference_key))->toBeTrue()
            ->and(taskEightHasDirectedExactText($brokenHtml, 'ltr', $direct->path))->toBeTrue();
    }
})->with([
    'English unchanged' => ['en', 'unchanged', 'present', 'direct_media', 'unchanged'],
    'English replacement' => ['en', 'replacement', 'present', 'direct_media', 'replacement'],
    'English automatic fallback' => ['en', 'automatic_fallback', 'present', 'direct_media', 'automatic_fallback'],
    'English broken' => ['en', 'broken', 'broken', 'external_url', 'unchanged'],
    'Hebrew unchanged' => ['he', 'unchanged', 'present', 'direct_media', 'unchanged'],
    'Hebrew replacement' => ['he', 'replacement', 'present', 'direct_media', 'replacement'],
    'Hebrew automatic fallback' => ['he', 'automatic_fallback', 'present', 'direct_media', 'automatic_fallback'],
    'Hebrew broken' => ['he', 'broken', 'broken', 'external_url', 'unchanged'],
]);

it('escapes mixed direction owner and media content while isolating technical identities', function (): void {
    app()->setLocale('he');
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $ownerTitle = 'פרק <em>Episode</em> & owner';
    $mediaTitle = 'תמונה <strong>Mixed</strong> & media';
    $directPath = 'content-items/images/mixed-direction.jpg';
    $item = ContentItem::factory()->for($group)->create(['title' => $ownerTitle]);
    $media = ownerImageMedia($directPath, ['title' => $mediaTitle]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    $component = Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item));
    $modalHtml = $component->getMountedActionModalHtml();
    $choiceHtml = taskEightElementHtml($modalHtml, 'owner-image-choice-state');

    expect(taskEightHasDirectedExactText($modalHtml, 'auto', $ownerTitle))->toBeTrue()
        ->and(str_contains($choiceHtml, e($mediaTitle)))->toBeTrue()
        ->and(taskEightHasDirectedExactText($choiceHtml, 'auto', $mediaTitle))->toBeFalse()
        ->and(str_contains($choiceHtml, (string) $media->reference_key))->toBeFalse();

    $component
        ->assertMountedActionModalDontSee('<em>Episode</em>', false)
        ->assertMountedActionModalDontSee('<strong>Mixed</strong>', false);
});

it('declares only the scoped owner modal responsive stylesheet contract', function (): void {
    $css = file_get_contents(resource_path('css/filament/admin/theme.css'));

    expect($css)
        ->toContain('.fi-modal-window.podtext-owner-image-modal')
        ->toContain(".podtext-owner-image-modal [data-testid='media-picker']")
        ->toContain(".podtext-owner-image-modal [data-testid='media-picker-gallery'] + aside")
        ->toContain('@media (max-width: 40rem)')
        ->toContain('inline-size: 100%')
        ->toContain('block-size: 100dvh')
        ->toContain('max-block-size: 100dvh')
        ->toContain('border-start-start-radius: 0')
        ->toContain('border-end-end-radius: 0')
        ->toContain('env(safe-area-inset-top)')
        ->toContain('env(safe-area-inset-bottom)')
        ->toContain('overscroll-behavior-y: contain')
        ->toContain('min-block-size: 2.75rem')
        ->toContain('min-inline-size: 2.75rem')
        ->not->toContain('!important');
});
