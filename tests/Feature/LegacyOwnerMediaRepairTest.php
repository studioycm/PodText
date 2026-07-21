<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\ImageUploadValidator;
use App\Support\Media\LegacyOwnerMediaRepairer;
use App\Support\Media\MediaAttachmentFormState;
use App\Support\Media\MediaAttachmentIdentityResolver;
use App\Support\Media\UnsafeLegacyOwnerMediaException;
use App\Support\PublicFront\PublicDefaultImageResolver;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

function legacyOwnerRepairMedia(?string $key): Media
{
    $bytes = base64_decode(trim((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'))), true);
    $validated = app(ImageUploadValidator::class)->validateBytes($bytes, 'cover.jpg', ImageUploadPurpose::ContentGroupCover);
    $name = (string) Str::ulid();
    $path = "content-groups/covers/{$name}.jpg";
    Storage::disk('public')->put($path, $bytes, 'public');
    $media = Media::factory()->create(['reference_key' => $key, 'disk' => 'public', 'directory' => 'content-groups/covers', 'name' => $name, 'path' => $path, 'visibility' => 'public', 'type' => $validated->mimeType, 'ext' => $validated->extension, 'size' => strlen($bytes), 'width' => $validated->width, 'height' => $validated->height]);
    DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => $key]);

    return $media->fresh();
}

it('turns only an unsafe owner identity into a safe diagnostic and atomically replaces it', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $target = legacyOwnerRepairMedia((string) Str::ulid());
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    $state = app(MediaAttachmentFormState::class);
    $diagnostic = $state->diagnostic($group, MediaAttachmentRole::Cover);

    expect($state->referenceKey($group, MediaAttachmentRole::Cover))->toBeNull()
        ->and($diagnostic)->not->toBeNull()
        ->and($diagnostic->ownerId)->toBe($group->getKey());
    expect(fn () => app(MediaAttachmentIdentityResolver::class)->resolve($group, MediaAttachmentRole::Cover))
        ->toThrow(UnsafeLegacyOwnerMediaException::class);

    app(LegacyOwnerMediaRepairer::class)->replace($group, MediaAttachmentRole::Cover, $target->reference_key, $diagnostic->fingerprint, User::factory()->admin()->create());

    expect($group->refresh()->cover_path)->toBe($target->path)
        ->and($group->coverMediaAttachment()->value('media_id'))->toBe($target->getKey())
        ->and($old->fresh()->reference_key)->toBeNull()
        ->and(Storage::disk('public')->exists($old->path))->toBeTrue()
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->count())->toBe(1);
});

it('fails closed for moderator, forged target, stale fingerprint, and explicit detach preserves old evidence', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    $state = app(MediaAttachmentFormState::class);
    $diagnostic = $state->diagnostic($group, MediaAttachmentRole::Cover);
    $repairer = app(LegacyOwnerMediaRepairer::class);

    expect(fn () => $repairer->detach($group, MediaAttachmentRole::Cover, $diagnostic->fingerprint, User::factory()->moderator()->create()))->toThrow(AuthorizationException::class);
    expect(fn () => $repairer->replace($group, MediaAttachmentRole::Cover, str_repeat('A', 26), $diagnostic->fingerprint, User::factory()->admin()->create()))->toThrow(RuntimeException::class);
    expect(fn () => $repairer->detach($group, MediaAttachmentRole::Cover, str_repeat('b', 64), User::factory()->admin()->create()))->toThrow(RuntimeException::class);

    $repairer->detach($group, MediaAttachmentRole::Cover, $diagnostic->fingerprint, User::factory()->admin()->create());
    expect($group->refresh()->cover_path)->toBeNull()
        ->and($old->fresh()->reference_key)->toBeNull()
        ->and(Storage::disk('public')->exists($old->path))->toBeTrue();
});

it('mounts both edit surfaces with an unsafe owner row without changing it', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    $item = ContentItem::factory()->for($group)->create(['image_path' => $old->path]);
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->assertOk()
        ->mountAction(TestAction::make('detachUnsafeCoverToDefault'))
        ->assertSet('mountedActions.0.data.legacy_media_repair_fingerprint', fn ($value): bool => is_string($value) && strlen($value) === 64);
    Livewire::actingAs($admin)->test(EditEpisodeWorkspace::class, ['record' => $item->getRouteKey()])
        ->assertOk()
        ->mountAction(TestAction::make('detachUnsafePrimaryImageToDefault'))
        ->assertSet('mountedActions.0.data.legacy_media_repair_fingerprint', fn ($value): bool => is_string($value) && strlen($value) === 64);
    expect($group->refresh()->cover_path)->toBe($old->path)->and($item->refresh()->image_path)->toBe($old->path);
});

it('uses a stable database-only diagnostic for duplicate raw rows', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $duplicate = Media::factory()->create([
        'reference_key' => null, 'disk' => $old->disk, 'directory' => $old->directory, 'name' => $old->name,
        'path' => $old->path, 'visibility' => $old->visibility, 'type' => $old->type, 'ext' => $old->ext,
        'size' => $old->size, 'width' => $old->width, 'height' => $old->height,
    ]);
    DB::table('curator')->where('id', $duplicate->getKey())->update(['reference_key' => null]);
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    $diagnostic = app(MediaAttachmentFormState::class)->diagnostic($group, MediaAttachmentRole::Cover);

    expect($diagnostic?->code->value)->toBe('duplicate_legacy_rows');
});

it('keeps the unsafe row unavailable to ordinary gallery abilities while limiting repair to admins', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $admin = User::factory()->admin()->create();
    $moderator = User::factory()->moderator()->create();

    expect(Gate::forUser($admin)->allows('view', $old))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('select', $old))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('download', $old))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('repairLegacyOwner', $old))->toBeTrue()
        ->and(Gate::forUser($moderator)->allows('repairLegacyOwner', $old))->toBeFalse();
});

it('rejects forged owner role pairs and unfinished evidence without writing a repair journal', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $target = legacyOwnerRepairMedia((string) Str::ulid());
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    $item = ContentItem::factory()->for($group)->create(['image_path' => null]);
    $repairer = app(LegacyOwnerMediaRepairer::class);
    $admin = User::factory()->admin()->create();
    $fingerprint = app(MediaAttachmentFormState::class)->diagnostic($group, MediaAttachmentRole::Cover)->fingerprint;

    expect(fn () => $repairer->detach($group, MediaAttachmentRole::PrimaryImage, $fingerprint, $admin))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $repairer->detach($item, MediaAttachmentRole::Cover, 'x', $admin))->toThrow(InvalidArgumentException::class);
    MediaMutationOperation::factory()->create(['media_id' => $old->getKey(), 'operation' => MediaMutationOperationType::LegacyTransition, 'status' => MediaMutationStatus::Committed]);
    expect(fn () => $repairer->replace($group, MediaAttachmentRole::Cover, $target->reference_key, $fingerprint, $admin))->toThrow(RuntimeException::class)
        ->and($group->refresh()->cover_path)->toBe($old->path)
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->count())->toBe(0);
});

it('falls back to the configured trusted default and never returns an unsafe owner path', function (): void {
    $bytes = base64_decode(trim((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'))), true);
    $validated = app(ImageUploadValidator::class)->validateBytes($bytes, 'default.jpg', ImageUploadPurpose::DefaultImage);
    $name = (string) Str::ulid();
    $defaultPath = "default-images/{$name}.jpg";
    Storage::disk('public')->put($defaultPath, $bytes, 'public');
    $default = Media::factory()->create(['reference_key' => (string) Str::ulid(), 'disk' => 'public', 'directory' => 'default-images', 'name' => $name, 'path' => $defaultPath, 'visibility' => 'public', 'type' => $validated->mimeType, 'ext' => $validated->extension, 'size' => strlen($bytes), 'width' => $validated->width, 'height' => $validated->height]);
    $unsafe = legacyOwnerRepairMedia(null);
    $group = ContentGroup::factory()->create(['cover_path' => $unsafe->path]);
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['content_group'] = ['mode' => 'custom', 'path' => $default->path, 'media_reference_key' => $default->reference_key];
    DB::table('settings')->updateOrInsert(['group' => PublicContentSettings::group(), 'name' => 'default_images'], ['locked' => false, 'payload' => json_encode($defaults, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    app()->forgetInstance(PublicDefaultImageResolver::class);
    $image = app(PublicDefaultImageResolver::class)->contentGroupImage($group);

    expect($image['path'])->toBe($default->path)
        ->and($image['url'])->toBe(Storage::disk('public')->url($default->path))
        ->and($image['url'])->not->toContain($unsafe->path);
});

it('uses the configured content-item default instead of a correctly rooted unsafe item image', function (): void {
    $bytes = base64_decode(trim((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'))), true);
    $validated = app(ImageUploadValidator::class)->validateBytes($bytes, 'item.jpg', ImageUploadPurpose::ContentItemPrimaryImage);
    $unsafeName = (string) Str::ulid();
    $unsafePath = "content-items/images/{$unsafeName}.jpg";
    Storage::disk('public')->put($unsafePath, $bytes, 'public');
    $unsafe = Media::factory()->create(['reference_key' => null, 'disk' => 'public', 'directory' => 'content-items/images', 'name' => $unsafeName, 'path' => $unsafePath, 'visibility' => 'public', 'type' => $validated->mimeType, 'ext' => $validated->extension, 'size' => strlen($bytes), 'width' => $validated->width, 'height' => $validated->height]);
    DB::table('curator')->where('id', $unsafe->getKey())->update(['reference_key' => null]);
    $defaultName = (string) Str::ulid();
    $defaultPath = "default-images/{$defaultName}.jpg";
    Storage::disk('public')->put($defaultPath, $bytes, 'public');
    $default = Media::factory()->create(['reference_key' => (string) Str::ulid(), 'disk' => 'public', 'directory' => 'default-images', 'name' => $defaultName, 'path' => $defaultPath, 'visibility' => 'public', 'type' => 'image/jpeg', 'ext' => 'jpg', 'size' => strlen($bytes), 'width' => $validated->width, 'height' => $validated->height]);
    $item = ContentItem::factory()->for(ContentGroup::factory()->create(['cover_path' => null]))->create(['image_path' => $unsafePath, 'external_thumbnail_url' => null]);
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['content_item'] = ['mode' => 'custom', 'path' => $default->path, 'media_reference_key' => $default->reference_key];
    DB::table('settings')->updateOrInsert(['group' => PublicContentSettings::group(), 'name' => 'default_images'], ['locked' => false, 'payload' => json_encode($defaults, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    app()->forgetInstance(PublicDefaultImageResolver::class);
    $image = app(PublicDefaultImageResolver::class)->contentItemImage($item, inheritGroupCover: false);
    expect($image['path'])->toBe($defaultPath)->and($image['url'])->not->toContain($unsafePath);
});

it('refuses an unfinished allowed target before any owner repair write', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $target = legacyOwnerRepairMedia((string) Str::ulid());
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    $fingerprint = app(MediaAttachmentFormState::class)->diagnostic($group, MediaAttachmentRole::Cover)->fingerprint;
    MediaMutationOperation::factory()->create(['media_id' => $target->getKey(), 'operation' => MediaMutationOperationType::LegacyTransition, 'status' => MediaMutationStatus::Committed]);
    expect(fn () => app(LegacyOwnerMediaRepairer::class)->replace($group, MediaAttachmentRole::Cover, $target->reference_key, $fingerprint, User::factory()->admin()->create()))->toThrow(RuntimeException::class)
        ->and($group->refresh()->cover_path)->toBe($old->path)
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->count())->toBe(0);
});

it('does not swallow an unrelated owner identity exception in public resolution', function (): void {
    $group = ContentGroup::factory()->create(['cover_path' => null]);
    $mock = Mockery::mock(MediaAttachmentIdentityResolver::class);
    $mock->shouldReceive('resolve')->once()->andThrow(new InvalidArgumentException('unrelated identity failure'));
    app()->instance(MediaAttachmentIdentityResolver::class, $mock);
    app()->forgetInstance(PublicDefaultImageResolver::class);

    expect(fn () => app(PublicDefaultImageResolver::class)->contentGroupImage($group))
        ->toThrow(InvalidArgumentException::class, 'unrelated identity failure');
});

it('diagnoses and detaches distinct attachment-path evidence without deleting either row', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $attached = legacyOwnerRepairMedia((string) Str::ulid());
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    MediaAttachment::factory()->create(['media_id' => $attached->getKey(), 'attachable_type' => 'content_group', 'attachable_id' => $group->getKey(), 'role' => MediaAttachmentRole::Cover, 'position' => 0]);
    $diagnostic = app(MediaAttachmentFormState::class)->diagnostic($group, MediaAttachmentRole::Cover);
    expect($diagnostic?->code->value)->toBe('attachment_path_mismatch');

    app(LegacyOwnerMediaRepairer::class)->detach($group, MediaAttachmentRole::Cover, $diagnostic->fingerprint, User::factory()->admin()->create());
    $operation = MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->sole();
    expect($group->refresh()->cover_path)->toBeNull()
        ->and($group->coverMediaAttachment()->exists())->toBeFalse()
        ->and(Media::query()->whereKey([$old->getKey(), $attached->getKey()])->count())->toBe(2)
        ->and(data_get($operation->context, 'evidence_media_ids'))->toBe([$old->getKey(), $attached->getKey()]);
});

it('mounts unsafe media list and relation-manager actions without an exception', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    $item = ContentItem::factory()->for($group)->create(['image_path' => $old->path]);
    $admin = User::factory()->admin()->create();
    $warning = __('admin.labels.unsafe_legacy_media');

    Livewire::actingAs($admin)->test(ListContentGroups::class)
        ->assertSee($group->title)
        ->assertSee($warning)
        ->assertActionVisible(TestAction::make('detachUnsafeCoverToDefault')->table($group))
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group))
        ->assertSet('mountedActions.0.data.legacy_media_repair_fingerprint', fn ($value): bool => is_string($value) && strlen($value) === 64)
        ->assertSet('mountedActions.0.data.cover_media_reference_key', null)
        ->unmountAction()
        ->mountAction(TestAction::make('detachUnsafeCoverToDefault')->table($group))
        ->assertSet('mountedActions.0.data.legacy_media_repair_fingerprint', fn ($value): bool => is_string($value) && strlen($value) === 64);
    Livewire::actingAs($admin)->test(ListContentItems::class)
        ->assertSee($item->title)
        ->assertSee($warning)
        ->assertActionVisible(TestAction::make('detachUnsafePrimaryImageToDefault')->table($item))
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item))
        ->unmountAction()
        ->mountAction(TestAction::make('detachUnsafePrimaryImageToDefault')->table($item));
    Livewire::actingAs($admin)->test(ContentItemsRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditContentGroup::class])
        ->assertSee($item->title)
        ->assertSee($warning)
        ->assertActionVisible(TestAction::make('detachUnsafePrimaryImageToDefault')->table($item))
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item));
});

it('ships every legacy repair label in both admin locales', function (): void {
    $en = require lang_path('en/admin.php');
    $he = require lang_path('he/admin.php');
    foreach ([
        'actions.detach_unsafe_media_to_default', 'helpers.unsafe_legacy_media_repair', 'labels.unsafe_legacy_media',
        'notifications.unsafe_media_detached_to_default', 'media_mutation_operations.legacy_transition', 'media_mutation_operations.legacy_owner_repair',
    ] as $key) {
        expect(data_get($en, $key))->toBeString()->not->toBe('')
            ->and(data_get($he, $key))->toBeString()->not->toBe('');
    }
});

it('repairs an unsafe cover through the real edit save rather than the ordinary attachment path', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $target = legacyOwnerRepairMedia((string) Str::ulid());
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    Livewire::actingAs(User::factory()->admin()->create())->test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->set('data.cover_media_reference_key', $target->reference_key)
        ->call('save')
        ->assertHasNoErrors();
    expect($group->refresh()->cover_path)->toBe($target->path)
        ->and($group->coverMediaAttachment()->value('media_id'))->toBe($target->getKey())
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->count())->toBe(1)
        ->and(Storage::disk('public')->exists($old->path))->toBeTrue();
});

it('preserves an unsafe cover on an ordinary real edit save with no picker selection', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    Livewire::actingAs(User::factory()->admin()->create())->test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->set('data.cover_media_reference_key', null)
        ->call('save')
        ->assertHasNoErrors();
    expect($group->refresh()->cover_path)->toBe($old->path)
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->count())->toBe(0);
});

it('repairs and detaches unsafe covers through mounted table actions', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $target = legacyOwnerRepairMedia((string) Str::ulid());
    $replace = ContentGroup::factory()->create(['cover_path' => $old->path]);
    $detachOld = legacyOwnerRepairMedia(null);
    $detach = ContentGroup::factory()->create(['cover_path' => $detachOld->path]);
    $admin = User::factory()->admin()->create();
    Livewire::actingAs($admin)->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($replace))
        ->set('mountedActions.0.data.cover_media_reference_key', $target->reference_key)
        ->callMountedAction();
    expect($replace->refresh()->cover_path)->toBe($target->path)
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->count())->toBe(1)
        ->and(Storage::disk('public')->exists($old->path))->toBeTrue();
    Livewire::actingAs($admin)->test(ListContentGroups::class)
        ->mountAction(TestAction::make('detachUnsafeCoverToDefault')->table($detach))
        ->callMountedAction();
    expect($detach->refresh()->cover_path)->toBeNull()
        ->and(Storage::disk('public')->exists($detachOld->path))->toBeTrue();
});

it('rejects a forged mounted repair fingerprint without changing the owner', function (): void {
    $old = legacyOwnerRepairMedia(null);
    $group = ContentGroup::factory()->create(['cover_path' => $old->path]);
    $component = Livewire::actingAs(User::factory()->admin()->create())->test(ListContentGroups::class)
        ->mountAction(TestAction::make('detachUnsafeCoverToDefault')->table($group))
        ->set('mountedActions.0.data.legacy_media_repair_fingerprint', str_repeat('f', 64));
    expect(fn () => $component->callMountedAction())->toThrow(RuntimeException::class);
    expect($group->refresh()->cover_path)->toBe($old->path)
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->count())->toBe(0);
});
