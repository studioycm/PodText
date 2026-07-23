<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\ImageUploadValidator;
use App\Support\Media\LegacyOwnerMediaDiagnostics;
use App\Support\Media\LegacyOwnerMediaRepairer;
use App\Support\Media\MediaAttachmentFormState;
use App\Support\Media\MediaAttachmentIdentityResolver;
use App\Support\Media\MediaInventoryDiagnostics;
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

function legacyOwnerRepairMedia(?string $key, array $overrides = []): Media
{
    $bytes = base64_decode(trim((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'))), true);
    $validated = app(ImageUploadValidator::class)->validateBytes($bytes, 'cover.jpg', ImageUploadPurpose::ContentGroupCover);
    $name = (string) Str::ulid();
    $attributes = array_merge([
        'reference_key' => $key,
        'disk' => 'public',
        'directory' => 'content-groups/covers',
        'name' => $name,
        'path' => "content-groups/covers/{$name}.jpg",
        'visibility' => 'public',
        'type' => $validated->mimeType,
        'ext' => $validated->extension,
        'size' => strlen($bytes),
        'width' => $validated->width,
        'height' => $validated->height,
    ], $overrides);

    Storage::disk((string) $attributes['disk'])->put((string) $attributes['path'], $bytes);
    $media = Media::factory()->create($attributes);
    DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => $key]);

    return $media->fresh();
}

/** @return array{Media, Media, ContentGroup} */
function duplicateLegacyOwnerState(): array
{
    $first = legacyOwnerRepairMedia(null);
    $duplicate = Media::factory()->create([
        'reference_key' => null,
        'disk' => $first->disk,
        'directory' => $first->directory,
        'name' => $first->name,
        'path' => $first->path,
        'visibility' => $first->visibility,
        'type' => $first->type,
        'ext' => $first->ext,
        'size' => $first->size,
        'width' => $first->width,
        'height' => $first->height,
    ]);
    DB::table('curator')->where('id', $duplicate->getKey())->update(['reference_key' => null]);

    return [$first, $duplicate->fresh(), ContentGroup::factory()->create(['cover_path' => $first->path])];
}

function configureContentGroupDefault(Media $default): void
{
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['content_group'] = [
        'mode' => 'custom',
        'path' => $default->path,
        'media_reference_key' => $default->reference_key,
    ];
    DB::table('settings')->updateOrInsert(
        ['group' => PublicContentSettings::group(), 'name' => 'default_images'],
        [
            'locked' => false,
            'payload' => json_encode($defaults, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

it('keeps key and metadata repair issues visible without treating them as public fallback reasons', function (): void {
    $media = legacyOwnerRepairMedia(null, [
        'directory' => 'legacy-folder',
        'name' => 'stale-name',
        'type' => 'application/octet-stream',
        'ext' => 'bin',
        'size' => 0,
        'width' => null,
        'height' => null,
    ]);
    $group = ContentGroup::factory()->create(['cover_path' => $media->path]);
    $admin = User::factory()->admin()->create();
    $identity = app(MediaAttachmentIdentityResolver::class)->resolve($group, MediaAttachmentRole::Cover);
    $image = app(PublicDefaultImageResolver::class)->contentGroupImage($group);

    expect($identity['media']?->is($media))->toBeTrue()
        ->and($identity['path'])->toBe($media->path)
        ->and(app(MediaAttachmentFormState::class)->diagnostic($group, MediaAttachmentRole::Cover))->toBeNull()
        ->and(app(MediaInventoryDiagnostics::class)->needsRepair($media))->toBeTrue()
        ->and($image['source'])->toBe('group')
        ->and($image['path'])->toBe($media->path)
        ->and(Gate::forUser($admin)->allows('view', $media))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('download', $media))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('select', $media))->toBeFalse();
});

it('keeps duplicate legacy paths as repairable association ambiguity', function (): void {
    [$first, $duplicate, $group] = duplicateLegacyOwnerState();
    $target = legacyOwnerRepairMedia((string) Str::ulid());
    $state = app(MediaAttachmentFormState::class);
    $diagnostic = $state->diagnostic($group, MediaAttachmentRole::Cover);

    expect($diagnostic?->code->value)->toBe('duplicate_legacy_rows');
    expect(fn () => app(MediaAttachmentIdentityResolver::class)->resolve($group, MediaAttachmentRole::Cover))
        ->toThrow(UnsafeLegacyOwnerMediaException::class);

    app(LegacyOwnerMediaRepairer::class)->replace(
        $group,
        MediaAttachmentRole::Cover,
        (string) $target->reference_key,
        (string) $diagnostic?->fingerprint,
        User::factory()->admin()->create(),
    );

    expect($group->refresh()->cover_path)->toBe($target->path)
        ->and($group->coverMediaAttachment()->value('media_id'))->toBe($target->getKey())
        ->and(Media::query()->whereKey([$first->getKey(), $duplicate->getKey()])->count())->toBe(2)
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->count())->toBe(1);
    Storage::disk('public')->assertExists($first->path);
});

it('fails duplicate-association repair closed for authorization stale evidence and unfinished mutations', function (): void {
    [$first, , $group] = duplicateLegacyOwnerState();
    $target = legacyOwnerRepairMedia((string) Str::ulid());
    $diagnostic = app(MediaAttachmentFormState::class)->diagnostic($group, MediaAttachmentRole::Cover);
    $repairer = app(LegacyOwnerMediaRepairer::class);

    expect(fn () => $repairer->detach(
        $group,
        MediaAttachmentRole::Cover,
        (string) $diagnostic?->fingerprint,
        User::factory()->moderator()->create(),
    ))->toThrow(AuthorizationException::class);
    expect(fn () => $repairer->detach(
        $group,
        MediaAttachmentRole::Cover,
        str_repeat('f', 64),
        User::factory()->admin()->create(),
    ))->toThrow(RuntimeException::class);

    MediaMutationOperation::factory()->create([
        'media_id' => $first->getKey(),
        'operation' => MediaMutationOperationType::LegacyTransition,
        'status' => MediaMutationStatus::Committed,
    ]);

    expect(fn () => $repairer->replace(
        $group,
        MediaAttachmentRole::Cover,
        (string) $target->reference_key,
        (string) $diagnostic?->fingerprint,
        User::factory()->admin()->create(),
    ))->toThrow(RuntimeException::class)
        ->and($group->refresh()->cover_path)->toBe($first->path)
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyOwnerRepair)->count())->toBe(0);
});

it('keeps an attachment authoritative when its compatibility path points at another row', function (): void {
    $legacy = legacyOwnerRepairMedia(null);
    $attached = legacyOwnerRepairMedia((string) Str::ulid(), [
        'directory' => 'legacy',
        'name' => 'stale-metadata',
        'type' => 'application/octet-stream',
        'ext' => 'bin',
        'size' => 0,
        'width' => null,
        'height' => null,
    ]);
    $group = ContentGroup::factory()->create(['cover_path' => $legacy->path]);
    MediaAttachment::factory()->create([
        'media_id' => $attached->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    $identity = app(MediaAttachmentIdentityResolver::class)->resolve($group, MediaAttachmentRole::Cover);
    app()->forgetInstance(PublicDefaultImageResolver::class);
    $image = app(PublicDefaultImageResolver::class)->contentGroupImage($group->refresh());

    expect($identity['has_attachment'])->toBeTrue()
        ->and($identity['media']?->is($attached))->toBeTrue()
        ->and($identity['path'])->toBe($attached->path)
        ->and(app(MediaAttachmentFormState::class)->diagnostic($group, MediaAttachmentRole::Cover))->toBeNull()
        ->and($image['source'])->toBe('group')
        ->and($image['path'])->toBe($attached->path)
        ->and(MediaMutationOperation::query()->count())->toBe(0);
});

it('uses a configured default for exactly the four D01 fallback conditions', function (): void {
    $default = legacyOwnerRepairMedia((string) Str::ulid(), [
        'directory' => 'default-images',
        'name' => $defaultName = (string) Str::ulid(),
        'path' => "default-images/{$defaultName}.jpg",
    ]);
    configureContentGroupDefault($default);

    $rowlessPath = 'content-groups/covers/'.Str::ulid().'.jpg';
    Storage::disk('public')->put($rowlessPath, 'rowless fixture');
    $rowless = ContentGroup::factory()->create(['cover_path' => $rowlessPath]);

    $missing = legacyOwnerRepairMedia((string) Str::ulid());
    Storage::disk('public')->delete($missing->path);
    $missingOwner = ContentGroup::factory()->create(['cover_path' => $missing->path]);

    $private = legacyOwnerRepairMedia((string) Str::ulid(), [
        'disk' => 'local',
        'visibility' => 'private',
    ]);
    $privateOwner = ContentGroup::factory()->create(['cover_path' => $private->path]);

    $svgName = (string) Str::ulid();
    $svgPath = "header/{$svgName}.svg";
    Storage::disk('public')->put($svgPath, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');
    $unsafeSvg = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => 'header',
        'name' => $svgName,
        'path' => $svgPath,
        'visibility' => 'public',
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'size' => 70,
        'width' => null,
        'height' => null,
    ]);
    $svgOwner = ContentGroup::factory()->create();
    MediaAttachment::factory()->create([
        'media_id' => $unsafeSvg->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $svgOwner->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    foreach ([$rowless, $missingOwner, $privateOwner, $svgOwner] as $owner) {
        app()->forgetInstance(PublicDefaultImageResolver::class);
        $image = app(PublicDefaultImageResolver::class)->contentGroupImage($owner->refresh());

        expect($image['source'])->toBe('content_group_default')
            ->and($image['path'])->toBe($default->path)
            ->and($image['url'])->toBe(Storage::disk('public')->url($default->path));
    }
});

it('creates a database-only diagnostic when an attachment row has lost its media row', function (): void {
    $group = ContentGroup::factory()->create();
    $attachment = MediaAttachment::factory()->make([
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);
    $attachment->setAttribute('id', 999);
    $attachment->setRelation('media', null);

    $diagnostic = app(LegacyOwnerMediaDiagnostics::class)->make(
        $group,
        MediaAttachmentRole::Cover,
        $attachment,
        collect(),
    );

    expect($diagnostic?->code->value)->toBe('missing_attachment_media')
        ->and($diagnostic?->fingerprint)->toBeString()->toHaveLength(64);
});

it('shows duplicate-association repair actions without hiding Add or Replace Image', function (): void {
    [, , $group] = duplicateLegacyOwnerState();
    $admin = User::factory()->admin()->create();
    $warning = __('admin.labels.unsafe_legacy_media');

    Livewire::actingAs($admin)->test(ListContentGroups::class)
        ->assertSee($group->title)
        ->assertSee($warning)
        ->assertActionVisible(TestAction::make('chooseContentGroupCover')->table($group))
        ->assertActionVisible(TestAction::make('detachUnsafeCoverToDefault')->table($group));
    Livewire::actingAs($admin)->test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->assertActionVisible('chooseContentGroupCover')
        ->assertActionVisible('detachUnsafeCoverToDefault');
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

it('ships every legacy repair label in both admin locales', function (): void {
    $en = require lang_path('en/admin.php');
    $he = require lang_path('he/admin.php');

    foreach ([
        'actions.detach_unsafe_media_to_default',
        'helpers.unsafe_legacy_media_repair',
        'labels.unsafe_legacy_media',
        'notifications.unsafe_media_detached_to_default',
        'media_mutation_operations.legacy_transition',
        'media_mutation_operations.legacy_owner_repair',
    ] as $key) {
        expect(data_get($en, $key))->toBeString()->not->toBe('')
            ->and(data_get($he, $key))->toBeString()->not->toBe('');
    }
});
