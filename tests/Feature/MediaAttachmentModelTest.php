<?php

use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Support\Media\MediaAttachmentManager;
use App\Support\Media\MediaRecordScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

it('assigns an immutable portable reference key to new media', function (): void {
    $untrusted = new Media;
    $untrusted->forceFill([
        'disk' => 'public',
        'directory' => 'content-groups/covers',
        'visibility' => 'public',
        'name' => 'untrusted',
        'path' => 'content-groups/covers/untrusted.jpg',
        'width' => 1,
        'height' => 1,
        'size' => 1,
        'type' => 'image/jpeg',
        'ext' => 'jpg',
    ]);

    expect(fn () => $untrusted->save())
        ->toThrow(LogicException::class, 'validated mutation coordinator');

    $media = Media::factory()->create(['reference_key' => null]);

    expect($media->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');

    $original = $media->reference_key;

    expect(fn () => $media->forceFill(['reference_key' => (string) Str::ulid()])->save())
        ->toThrow(LogicException::class);

    expect($media->refresh()->reference_key)->toBe($original);
});

it('shares one media record between owners while enforcing singleton roles', function (): void {
    $actor = User::factory()->admin()->create();
    $firstOwner = ContentGroup::factory()->create();
    $secondOwner = ContentGroup::factory()->create();
    $firstMedia = Media::factory()->create();
    $secondMedia = Media::factory()->create();
    $manager = app(MediaAttachmentManager::class);

    $manager->attach($firstOwner, $firstMedia, MediaAttachmentRole::Cover, $actor);
    $manager->attach($secondOwner, $firstMedia, MediaAttachmentRole::Cover, $actor);

    expect($firstMedia->attachments()->count())->toBe(2)
        ->and($firstOwner->refresh()->cover_path)->toBe($firstMedia->path)
        ->and($secondOwner->refresh()->cover_path)->toBe($firstMedia->path);

    $manager->attach($firstOwner, $secondMedia, MediaAttachmentRole::Cover, $actor);

    expect($firstOwner->mediaAttachments()->count())->toBe(1)
        ->and($firstOwner->coverMediaAttachment()->firstOrFail()->media_id)->toBe($secondMedia->getKey())
        ->and($firstOwner->refresh()->cover_path)->toBe($secondMedia->path)
        ->and($secondOwner->coverMediaAttachment()->firstOrFail()->media_id)->toBe($firstMedia->getKey());
});

it('enforces role and owner compatibility and resolves stable morph aliases', function (): void {
    $actor = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $cover = Media::factory()->create();
    $primaryName = (string) Str::ulid();
    $primary = Media::factory()->create([
        'directory' => 'content-items/images',
        'name' => $primaryName,
        'path' => "content-items/images/{$primaryName}.jpg",
    ]);
    $manager = app(MediaAttachmentManager::class);

    expect(fn () => $manager->attach($item, $cover, MediaAttachmentRole::Cover, $actor))
        ->toThrow(InvalidArgumentException::class);

    $attachment = $manager->attach($item, $primary, MediaAttachmentRole::PrimaryImage, $actor);

    expect($attachment->attachable_type)->toBe('content_item')
        ->and($attachment->attachable)->toBeInstanceOf(ContentItem::class)
        ->and($item->refresh()->image_path)->toBe($primary->path);
});

it('enforces the owner role uniqueness constraint at the database boundary', function (): void {
    $group = ContentGroup::factory()->create();
    $first = Media::factory()->create();
    $second = Media::factory()->create();

    MediaAttachment::query()->create([
        'media_id' => $first->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    expect(fn () => MediaAttachment::query()->create([
        'media_id' => $second->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 1,
    ]))->toThrow(QueryException::class);
});

it('removes owner attachment rows without deleting shared media bytes', function (): void {
    $actor = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $cover = Media::factory()->create();
    $primaryName = (string) Str::ulid();
    $primary = Media::factory()->create([
        'directory' => 'content-items/images',
        'name' => $primaryName,
        'path' => "content-items/images/{$primaryName}.jpg",
    ]);
    Storage::disk('public')->put($cover->path, 'cover');
    Storage::disk('public')->put($primary->path, 'primary');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $cover, MediaAttachmentRole::Cover, $actor);
    $manager->attach($item, $primary, MediaAttachmentRole::PrimaryImage, $actor);

    $group->delete();

    expect(MediaAttachment::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(2);
    Storage::disk('public')->assertExists($cover->path);
    Storage::disk('public')->assertExists($primary->path);
});

it('shares and replaces singleton primary images for content items', function (): void {
    $actor = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $firstItem = ContentItem::factory()->for($group)->create();
    $secondItem = ContentItem::factory()->for($group)->create();
    $firstName = (string) Str::ulid();
    $secondName = (string) Str::ulid();
    $shared = Media::factory()->create([
        'directory' => 'content-items/images',
        'name' => $firstName,
        'path' => "content-items/images/{$firstName}.jpg",
    ]);
    $replacement = Media::factory()->create([
        'directory' => 'content-items/images',
        'name' => $secondName,
        'path' => "content-items/images/{$secondName}.jpg",
    ]);
    $manager = app(MediaAttachmentManager::class);

    $manager->attach($firstItem, $shared, MediaAttachmentRole::PrimaryImage, $actor);
    $manager->attach($secondItem, $shared, MediaAttachmentRole::PrimaryImage, $actor);
    $manager->attach($firstItem, $replacement, MediaAttachmentRole::PrimaryImage, $actor);

    expect($firstItem->primaryImageMediaAttachment()->firstOrFail()->media_id)->toBe($replacement->getKey())
        ->and($secondItem->primaryImageMediaAttachment()->firstOrFail()->media_id)->toBe($shared->getKey())
        ->and($firstItem->mediaAttachments()->count())->toBe(1)
        ->and($secondItem->mediaAttachments()->count())->toBe(1)
        ->and($firstItem->refresh()->image_path)->toBe($replacement->path)
        ->and($secondItem->refresh()->image_path)->toBe($shared->path);
});

it('casts attachment positions and journal history while preserving stable morph aliases', function (): void {
    $group = ContentGroup::factory()->create();
    $media = Media::factory()->create();
    $attachment = MediaAttachment::factory()->create([
        'media_id' => $media->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 7,
    ]);
    $operation = MediaMutationOperation::factory()->create([
        'media_id' => $media->getKey(),
        'media_id_snapshot' => $media->getKey(),
        'media_reference_key' => $media->reference_key,
        'context' => ['reason' => 'test'],
    ]);
    $relationshipOrder = collect($group->mediaAttachments()->getQuery()->getQuery()->orders ?? [])
        ->pluck('column')
        ->all();

    expect($attachment->position)->toBe(7)
        ->and($attachment->role)->toBe(MediaAttachmentRole::Cover)
        ->and($attachment->attachable)->toBeInstanceOf(ContentGroup::class)
        ->and($media->attachments()->firstOrFail()->is($attachment))->toBeTrue()
        ->and($media->mutationOperations()->firstOrFail()->is($operation))->toBeTrue()
        ->and($operation->context)->toBe(['reason' => 'test'])
        ->and($relationshipOrder)->toBe(['role', 'position', 'id'])
        ->and(Relation::getMorphedModel('content_group'))->toBe(ContentGroup::class)
        ->and(Relation::getMorphedModel('content_item'))->toBe(ContentItem::class)
        ->and(Relation::getMorphedModel(ContentGroup::class))->toBe(ContentGroup::class);
});

it('enforces the restrictive media foreign key and cleans direct item attachments', function (): void {
    $actor = User::factory()->admin()->create();
    $item = ContentItem::factory()->create();
    $name = (string) Str::ulid();
    $media = Media::factory()->create([
        'directory' => 'content-items/images',
        'name' => $name,
        'path' => "content-items/images/{$name}.jpg",
    ]);
    app(MediaAttachmentManager::class)->attach($item, $media, MediaAttachmentRole::PrimaryImage, $actor);

    expect(fn () => DB::table('curator')->where('id', $media->getKey())->delete())
        ->toThrow(QueryException::class);

    $item->delete();

    expect(MediaAttachment::query()->count())->toBe(0)
        ->and(Media::query()->whereKey($media->getKey())->exists())->toBeTrue();
});

it('authorizes detach for both attached and legacy only owner state', function (): void {
    $admin = User::factory()->admin()->create();
    $moderator = User::factory()->moderator()->create();
    $attachedGroup = ContentGroup::factory()->create();
    $legacyGroup = ContentGroup::factory()->create(['cover_path' => 'content-groups/covers/legacy.jpg']);
    $media = Media::factory()->create();
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($attachedGroup, $media, MediaAttachmentRole::Cover, $admin);

    expect(fn () => $manager->detach($attachedGroup, MediaAttachmentRole::Cover, $moderator))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $manager->detach($legacyGroup, MediaAttachmentRole::Cover, $moderator))
        ->toThrow(AuthorizationException::class);

    expect($attachedGroup->refresh()->cover_path)->toBe($media->path)
        ->and($legacyGroup->refresh()->cover_path)->toBe('content-groups/covers/legacy.jpg');

    $manager->detach($attachedGroup, MediaAttachmentRole::Cover, $admin);
    $manager->detach($legacyGroup, MediaAttachmentRole::Cover, $admin);

    expect($attachedGroup->refresh()->cover_path)->toBeNull()
        ->and($legacyGroup->refresh()->cover_path)->toBeNull()
        ->and(MediaAttachment::query()->count())->toBe(0);
});

it('keeps nullable legacy media out of trusted selection until its key is backfilled', function (): void {
    $media = Media::factory()->create();
    DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => null]);

    expect(app(MediaRecordScope::class)->find($media->getKey()))->toBeNull();
});

it('locks and reloads media identity for attachment writes and rejects active mutations', function (): void {
    $actor = User::factory()->admin()->create();
    $owner = ContentGroup::factory()->create();
    $media = Media::factory()->create();
    $newName = (string) Str::ulid();
    $newPath = "content-groups/covers/{$newName}.jpg";
    DB::table('curator')->where('id', $media->getKey())->update([
        'name' => $newName,
        'path' => $newPath,
    ]);

    app(MediaAttachmentManager::class)->attach($owner, $media, MediaAttachmentRole::Cover, $actor);
    expect($owner->refresh()->cover_path)->toBe($newPath);

    $operation = MediaMutationOperation::factory()->create([
        'media_id' => $media->getKey(),
        'media_id_snapshot' => $media->getKey(),
        'media_reference_key' => $media->reference_key,
        'operation' => MediaMutationOperationType::Rename,
        'status' => MediaMutationStatus::Copied,
        'lease_token' => (string) Str::ulid(),
        'lease_expires_at' => now()->addMinute(),
    ]);

    expect(fn () => app(MediaAttachmentManager::class)->detach($owner, MediaAttachmentRole::Cover, $actor))
        ->toThrow(RuntimeException::class, 'active filesystem mutation');
    expect($owner->refresh()->cover_path)->toBe($newPath)
        ->and($owner->coverMediaAttachment()->exists())->toBeTrue();

    $operation->update(['status' => MediaMutationStatus::Failed]);
    app(MediaAttachmentManager::class)->detach($owner, MediaAttachmentRole::Cover, $actor);
    expect($owner->refresh()->cover_path)->toBeNull();
});
