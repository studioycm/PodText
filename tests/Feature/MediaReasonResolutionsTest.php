<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Filament\Resources\Media\Pages\ReviewMediaIssues;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\MediaProviderBinding;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\ImageUploadValidator;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordCorrections;
use App\Support\Media\MediaRecordScope;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
    app()->setLocale('en');
});

function resolutionUpload(string $fixture, string $filename): UploadedFile
{
    $encoded = file_get_contents(base_path("tests/Fixtures/media/{$fixture}"));
    $contents = base64_decode(trim((string) $encoded), true)
        ?: throw new RuntimeException("Invalid fixture [{$fixture}].");

    return UploadedFile::fake()->createWithContent($filename, $contents);
}

/** Store normalization-stable bytes for a key-less row so minting can validate them. */
function resolutionMintableMedia(): Media
{
    $encoded = file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'));
    $source = base64_decode(trim((string) $encoded), true)
        ?: throw new RuntimeException('Invalid fixture.');
    $bytes = app(ImageUploadValidator::class)
        ->validateBytes($source, 'seed.jpg', ImageUploadPurpose::ContentGroupCover)
        ->contents;
    [$width, $height] = getimagesizefromstring($bytes);

    $media = Media::factory()->create([
        'size' => strlen($bytes),
        'width' => $width,
        'height' => $height,
    ]);
    // Key-less rows predate the model guard, so only the database can express
    // them: the creating hook would auto-mint a key through Eloquent.
    DB::table($media->getTable())->where('id', $media->getKey())->update(['reference_key' => null]);
    Storage::disk('public')->put($media->path, $bytes, ['visibility' => 'public']);

    return $media->refresh();
}

/** @param array<string, mixed> $payload */
function resolutionSaveSetting(string $name, array $payload): void
{
    DB::table('settings')->updateOrInsert(
        ['group' => PublicContentSettings::group(), 'name' => $name],
        [
            'locked' => false,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

function resolutionAttachToGroup(Media $media): MediaAttachment
{
    return MediaAttachment::query()->create([
        'media_id' => $media->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => ContentGroup::factory()->create()->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);
}

it('mirrors allowsForBackfill exactly through the structured verdict', function (array $attributes): void {
    $media = Media::factory()->create($attributes);
    $scope = app(MediaRecordScope::class);

    expect($scope->backfillVerdict($media)->passes())->toBe($scope->allowsForBackfill($media));
})->with([
    'clean managed row' => [[]],
    'missing reference key' => [['reference_key' => null]],
    'malformed reference key' => [['reference_key' => 'not-a-ulid']],
    'private visibility' => [['visibility' => 'private']],
    'foreign disk' => [['disk' => 'missing-disk']],
    'wrong directory' => [['directory' => 'content-items/images']],
    'wrong extension' => [['ext' => 'png']],
    'oversized' => [['size' => 999999999]],
    'zero dimensions' => [['width' => 0, 'height' => 0]],
    'unmanaged path' => [['path' => 'random/loose-file.jpg']],
]);

it('names the exact failing facts behind a backfill refusal', function (array $attributes, array $expectedCodes): void {
    $media = Media::factory()->create($attributes);

    expect(app(MediaRecordScope::class)->backfillVerdict($media)->failureCodes)->toBe($expectedCodes);
})->with([
    'clean row' => [[], []],
    'missing key is not a shape defect' => [['reference_key' => null], []],
    'malformed key' => [['reference_key' => 'not-a-ulid'], ['reference_key_invalid']],
    'private visibility' => [['visibility' => 'private'], ['visibility_not_public']],
    'foreign disk' => [['disk' => 'weird'], ['disk_not_public']],
    'wrong directory' => [['directory' => 'content-items/images'], ['directory_mismatch']],
    'extension mismatch' => [['ext' => 'png'], ['extension_mismatch']],
    'unmanaged path' => [['path' => 'random/loose.jpg'], ['path_outside_managed_roots']],
    'oversized with zero width' => [
        ['size' => CuratorImageUploadPolicy::MAX_KILOBYTES * 1024 + 1, 'width' => 0],
        ['size_out_of_bounds', 'dimensions_out_of_bounds'],
    ],
]);

it('lifts swap authorization to attachment-referenced rows while keeping the settings carve-out', function (): void {
    $actor = User::factory()->admin()->create();

    $referenced = Media::factory()->create();
    resolutionAttachToGroup($referenced);

    $settingsReferenced = Media::factory()->create();
    resolutionSaveSetting('default_images', [
        'content_group' => [
            'path' => $settingsReferenced->path,
            'media_reference_key' => $settingsReferenced->reference_key,
        ],
    ]);

    expect(Gate::forUser($actor)->inspect('swap', $referenced)->allowed())->toBeTrue()
        ->and(Gate::forUser($actor)->inspect('swap', $settingsReferenced)->allowed())->toBeFalse()
        ->and(Gate::forUser($actor)->inspect('rename', $referenced)->allowed())->toBeFalse();
});

it('restores a missing file through swap and journals the absent source truthfully', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $media = $coordinator->createFromUpload(
        resolutionUpload('valid.jpg.base64', 'original.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    resolutionAttachToGroup($media);
    Storage::disk('public')->delete($media->path);

    expect(app(MediaInventoryDiagnostics::class)->reasons($media->refresh()))->toContain('missing_file');

    $updated = $coordinator->swap(
        $media,
        resolutionUpload('valid.png.base64', 'replacement.png'),
        $actor,
    );
    $operation = MediaMutationOperation::query()
        ->where('operation', MediaMutationOperationType::Swap->value)
        ->latest('id')
        ->firstOrFail();

    expect($updated->getKey())->toBe($media->getKey())
        ->and($updated->type)->toBe('image/png')
        ->and(Storage::disk('public')->exists($updated->path))->toBeTrue()
        ->and($operation->status)->toBe(MediaMutationStatus::Completed)
        ->and($operation->context['source_missing'] ?? null)->toBeTrue()
        ->and($operation->quarantine_path)->toBeNull()
        ->and(MediaAttachment::query()->where('media_id', $media->getKey())->count())->toBe(1)
        ->and(app(MediaInventoryDiagnostics::class)->reasons($updated->refresh()))->not->toContain('missing_file');
});

it('still refuses rename when the source file is missing', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $media = $coordinator->createFromUpload(
        resolutionUpload('valid.jpg.base64', 'renameable.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    Storage::disk('public')->delete($media->path);

    expect(fn () => $coordinator->rename($media, $actor))
        ->toThrow(RuntimeException::class, 'missing');
});

it('deletes a row whose file is missing and journals the absent source', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $media = $coordinator->createFromUpload(
        resolutionUpload('valid.jpg.base64', 'vanished.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
        ['title' => 'כותרת אבודה'],
    );
    Storage::disk('public')->delete($media->path);

    $coordinator->delete($media->refresh(), $actor);

    $operation = MediaMutationOperation::query()
        ->where('operation', MediaMutationOperationType::Delete->value)
        ->latest('id')
        ->firstOrFail();

    expect(Media::query()->whereKey($media->getKey())->exists())->toBeFalse()
        ->and($operation->status)->toBe(MediaMutationStatus::Completed)
        ->and($operation->context['source_missing'] ?? null)->toBeTrue()
        ->and($operation->context['title'] ?? null)->toBe('כותרת אבודה')
        ->and($operation->quarantine_path)->toBeNull();
});

it('offers restore and detach-and-delete on the missing file card and detaches before deleting', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $media = $coordinator->createFromUpload(
        resolutionUpload('valid.jpg.base64', 'shared-lost.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $attachment = resolutionAttachToGroup($media);
    Storage::disk('public')->delete($media->path);

    Livewire::test(ReviewMediaIssues::class, ['record' => $media->getRouteKey()])
        ->assertSeeHtml('data-testid="media-issue-resolution-action-missing_file"')
        ->assertSeeHtml('data-testid="media-swapFile-trigger"')
        ->assertSeeHtml('data-testid="media-detachAndDeleteFile-trigger"')
        ->callAction('detachAndDeleteFile')
        ->assertHasNoFormErrors();

    $operation = MediaMutationOperation::query()
        ->where('operation', MediaMutationOperationType::Delete->value)
        ->latest('id')
        ->firstOrFail();

    expect(Media::query()->whereKey($media->getKey())->exists())->toBeFalse()
        ->and(MediaAttachment::query()->whereKey($attachment->getKey())->exists())->toBeFalse()
        ->and($operation->status)->toBe(MediaMutationStatus::Completed)
        ->and($operation->context['source_missing'] ?? null)->toBeTrue();
});

it('mints a missing portable reference key through a journaled lease window', function (): void {
    $actor = User::factory()->admin()->create();
    $media = resolutionMintableMedia();
    $bytes = (string) Storage::disk('public')->get($media->path);

    expect(app(MediaInventoryDiagnostics::class)->reasons($media))->toContain('portable_identity');

    $updated = app(MediaFilesystemMutationCoordinator::class)->mintReferenceKey($media, $actor);
    $operation = MediaMutationOperation::query()
        ->where('operation', MediaMutationOperationType::ReferenceKeyBackfill->value)
        ->latest('id')
        ->firstOrFail();

    expect($updated->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($operation->status)->toBe(MediaMutationStatus::Completed)
        ->and($operation->destination_path)->toBe($media->path)
        ->and($operation->destination_sha256)->toBe(hash('sha256', $bytes))
        ->and(MediaAsset::query()->where('reference_key', $updated->reference_key)->exists())->toBeTrue()
        ->and(MediaProviderBinding::query()
            ->where('provider', 'curator')
            ->where('provider_record_key', (string) $media->getKey())
            ->exists())->toBeTrue()
        ->and(app(MediaInventoryDiagnostics::class)->reasons($updated->refresh()))->not->toContain('portable_identity');

    expect(fn () => $updated->forceFill(['reference_key' => (string) Str::ulid()])->save())
        ->toThrow(LogicException::class);
});

it('refuses to mint over an existing or malformed reference key', function (): void {
    $actor = User::factory()->admin()->create();
    $valid = Media::factory()->create();
    $malformed = Media::factory()->create(['reference_key' => 'not-a-ulid']);

    expect(Gate::forUser($actor)->inspect('mintReferenceKey', $valid)->allowed())->toBeFalse()
        ->and(Gate::forUser($actor)->inspect('mintReferenceKey', $malformed)->allowed())->toBeFalse();
});

it('offers the mint action on the portable identity card and executes it', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);
    $media = resolutionMintableMedia();

    Livewire::test(ReviewMediaIssues::class, ['record' => $media->getRouteKey()])
        ->assertSeeHtml('data-testid="media-issue-resolution-action-portable_identity"')
        ->assertSeeHtml('data-testid="media-mintReferenceKey-trigger"')
        ->callAction('mintReferenceKey')
        ->assertHasNoFormErrors();

    expect($media->refresh()->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

it('lets only a super-admin flip a private file to public audience with trust-mark weight', function (): void {
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $media = Media::factory()->create(['visibility' => 'private']);
    Storage::disk('public')->put($media->path, 'bytes');

    expect(app(MediaInventoryDiagnostics::class)->reasons($media))->toContain('audience_denied')
        ->and(Gate::forUser($admin)->inspect('makePublic', $media)->allowed())->toBeFalse()
        ->and(Gate::forUser($superAdmin)->inspect('makePublic', $media)->allowed())->toBeTrue();

    $updated = app(MediaRecordCorrections::class)->makePublic($media, $superAdmin);

    expect($updated->visibility)->toBe('public')
        ->and($updated->audience_made_public_at)->not->toBeNull()
        ->and((int) $updated->audience_made_public_by_user_id)->toBe($superAdmin->getKey())
        ->and(app(MediaInventoryDiagnostics::class)->reasons($updated))->not->toContain('audience_denied');

    $reverted = app(MediaRecordCorrections::class)->revokePublicAudience($updated, $superAdmin);

    expect($reverted->visibility)->toBe('private')
        ->and($reverted->audience_made_public_at)->toBeNull()
        ->and(app(MediaInventoryDiagnostics::class)->reasons($reverted->refresh()))->toContain('audience_denied');
});

it('refuses the audience flip when the disk itself is not public', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $media = Media::factory()->create(['disk' => 'nonexistent-disk']);

    expect(Gate::forUser($superAdmin)->inspect('makePublic', $media)->allowed())->toBeFalse();
});

it('lets only a super-admin correct a nonexistent storage disk to a configured one', function (): void {
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $media = Media::factory()->create(['disk' => 'vanished-disk']);

    expect(app(MediaInventoryDiagnostics::class)->reasons($media))->toContain('storage_disk')
        ->and(Gate::forUser($admin)->inspect('correctDisk', $media)->allowed())->toBeFalse()
        ->and(Gate::forUser($superAdmin)->inspect('correctDisk', $media)->allowed())->toBeTrue()
        ->and(Gate::forUser($superAdmin)->inspect('correctDisk', Media::factory()->create())->allowed())->toBeFalse();

    expect(fn () => app(MediaRecordCorrections::class)->correctDisk($media, $superAdmin, 'not-configured'))
        ->toThrow(InvalidArgumentException::class);

    $updated = app(MediaRecordCorrections::class)->correctDisk($media, $superAdmin, 'public');

    expect($updated->disk)->toBe('public')
        ->and(app(MediaInventoryDiagnostics::class)->reasons($updated->refresh()))->not->toContain('storage_disk');
});

it('offers the super-admin resolutions on the reason cards and executes them', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());
    $privateMedia = Media::factory()->create(['visibility' => 'private']);
    Storage::disk('public')->put($privateMedia->path, 'bytes');

    Livewire::test(ReviewMediaIssues::class, ['record' => $privateMedia->getRouteKey()])
        ->assertSeeHtml('data-testid="media-issue-resolution-action-audience_denied"')
        ->assertSeeHtml('data-testid="media-makePublicFile-trigger"')
        ->callAction('makePublicFile')
        ->assertHasNoFormErrors();

    expect($privateMedia->refresh()->visibility)->toBe('public');

    $diskMedia = Media::factory()->create(['disk' => 'vanished-disk']);

    Livewire::test(ReviewMediaIssues::class, ['record' => $diskMedia->getRouteKey()])
        ->assertSeeHtml('data-testid="media-issue-resolution-action-storage_disk"')
        ->callAction('correctDiskFile', ['disk' => 'public'])
        ->assertHasNoFormErrors();

    expect($diskMedia->refresh()->disk)->toBe('public');
});

it('denies detach-and-delete when the file exists or settings still reference the row', function (): void {
    $actor = User::factory()->admin()->create();

    $present = Media::factory()->create();
    Storage::disk('public')->put($present->path, 'present bytes');

    $settingsReferenced = Media::factory()->create();
    resolutionSaveSetting('default_images', [
        'content_group' => [
            'path' => $settingsReferenced->path,
            'media_reference_key' => $settingsReferenced->reference_key,
        ],
    ]);

    expect(Gate::forUser($actor)->inspect('detachAndDelete', $present)->allowed())->toBeFalse()
        ->and(Gate::forUser($actor)->inspect('detachAndDelete', $settingsReferenced)->allowed())->toBeFalse();
});
