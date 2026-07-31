<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\ImageUploadValidator;
use App\Support\Media\MediaAttachmentManager;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaMutationFence;
use App\Support\Media\StoredMediaValidator;
use App\Support\PublicFront\PublicFrontConfigCache;
use Awcodes\Curator\Facades\Glide;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Glide\Server;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

function curatorG1MutationFixture(string $name): string
{
    $contents = file_get_contents(base_path("tests/Fixtures/media/{$name}"));

    if (! is_string($contents)) {
        throw new RuntimeException("Unable to load media fixture [{$name}].");
    }

    return str_ends_with($name, '.base64')
        ? (base64_decode(trim($contents), true) ?: throw new RuntimeException("Invalid media fixture [{$name}]."))
        : $contents;
}

function curatorG1MutationUpload(string $fixture, string $filename): UploadedFile
{
    return UploadedFile::fake()->createWithContent($filename, curatorG1MutationFixture($fixture));
}

it('journals generated uploads rename swap and delete while preserving identity and invalidating old artifacts', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $media = $coordinator->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'client-name.jpeg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
        ['title' => 'Display filename only'],
    );

    expect($media->path)->toStartWith('content-groups/covers/')
        ->and($media->path)->not->toContain('client-name')
        ->and($media->ext)->toBe('jpg')
        ->and($media->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and(MediaMutationOperation::query()->latest('id')->firstOrFail()->status)->toBe(MediaMutationStatus::Completed);
    Storage::disk('public')->assertExists($media->path);
    expect(Storage::disk('local')->allFiles('media-staging'))->toBe([]);
    expect(app(StoredMediaValidator::class)->validate($media)->sha256)
        ->toBe(hash('sha256', Storage::disk('public')->get($media->path)));

    $referenceKey = $media->reference_key;
    $firstPath = $media->path;
    $oldName = $media->name;
    $oldModified = Storage::disk('public')->lastModified($firstPath);
    Cache::put('placeholder:'.$oldName.$oldModified, 'stale');
    $paletteKey = app(PublicFrontConfigCache::class)->podcastPaletteKey($firstPath, $oldModified);
    Cache::put($paletteKey, 'stale');
    Storage::disk('public')->put(dirname($firstPath).'/'.$oldName.'/card.webp', 'stale curation');

    expect(fn () => $media->forceFill(['path' => 'content-groups/covers/forged.jpg'])->save())
        ->toThrow(LogicException::class);

    $renamed = $coordinator->rename($media->refresh(), $actor);
    expect($renamed->reference_key)->toBe($referenceKey)
        ->and($renamed->path)->not->toBe($firstPath)
        ->and(Cache::has('placeholder:'.$oldName.$oldModified))->toBeFalse()
        ->and(Cache::has($paletteKey))->toBeFalse();
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertMissing(dirname($firstPath).'/'.$oldName.'/card.webp');

    $renamedPath = $renamed->path;
    $swapped = $coordinator->swap(
        $renamed,
        curatorG1MutationUpload('valid.png.base64', 'replacement.png'),
        $actor,
    );
    expect($swapped->reference_key)->toBe($referenceKey)
        ->and($swapped->path)->toEndWith('.png')
        ->and($swapped->type)->toBe('image/png')
        ->and($swapped->curations)->toBeNull();
    Storage::disk('public')->assertMissing($renamedPath);
    Storage::disk('public')->assertExists($swapped->path);

    $swappedPath = $swapped->path;
    $coordinator->delete($swapped, $actor);

    expect(Media::query()->whereKey($media->getKey())->exists())->toBeFalse()
        ->and(MediaMutationOperation::query()->where('status', MediaMutationStatus::Completed)->count())->toBe(4)
        ->and(Storage::disk('local')->allFiles('media-quarantine'))->not->toBeEmpty();
    Storage::disk('public')->assertMissing($swappedPath);
});

it('invalidates Glide cache identities for both sides of a rename', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $media = $coordinator->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'source.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $sourcePath = $media->path;
    $invalidatedPaths = [];
    $server = Mockery::mock(Server::class);

    $server->shouldReceive('deleteCache')
        ->twice()
        ->andReturnUsing(function (string $path) use (&$invalidatedPaths): bool {
            $invalidatedPaths[] = $path;

            return true;
        });
    Glide::shouldReceive('getServer')->twice()->andReturn($server);

    $renamed = $coordinator->rename($media, $actor);

    expect($invalidatedPaths)->toBe([$sourcePath, $renamed->path]);
});

it('promotes sanitized svg through private staging without raster metadata or curations', function (): void {
    $actor = User::factory()->admin()->create();
    $media = app(MediaFilesystemMutationCoordinator::class)->createFromUpload(
        curatorG1MutationUpload('clean.svg', 'brand-logo.svg'),
        ImageUploadPurpose::HeaderLogo,
        $actor,
    );
    $operation = MediaMutationOperation::query()->sole();

    expect($media->type)->toBe('image/svg+xml')
        ->and($media->ext)->toBe('svg')
        ->and($media->width)->toBeNull()
        ->and($media->height)->toBeNull()
        ->and($media->curations)->toBeNull()
        ->and($operation->status)->toBe(MediaMutationStatus::Completed)
        ->and($operation->staging_disk)->toBe('local')
        ->and($operation->destination_sha256)->toBe(hash('sha256', Storage::disk('public')->get($media->path)));
    Storage::disk('public')->assertExists($media->path);
    expect(Storage::disk('local')->allFiles('media-staging'))->toBe([]);
});

it('revalidates swap bytes before journaling and preserves the original on rejection', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $media = $coordinator->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'original.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $before = [
        'reference_key' => $media->reference_key,
        'path' => $media->path,
        'bytes' => Storage::disk('public')->get($media->path),
        'operations' => MediaMutationOperation::query()->count(),
    ];

    expect(fn () => $coordinator->swap(
        $media,
        UploadedFile::fake()->createWithContent('replacement.jpg', '<?php echo "owned"; ?>'),
        $actor,
    ))->toThrow(InvalidArgumentException::class);

    $fresh = $media->fresh();
    expect($fresh->reference_key)->toBe($before['reference_key'])
        ->and($fresh->path)->toBe($before['path'])
        ->and(Storage::disk('public')->get($fresh->path))->toBe($before['bytes'])
        ->and(MediaMutationOperation::query()->count())->toBe($before['operations'])
        ->and($fresh->attachments()->count())->toBe(0);
});

it('retries colliding destinations and fails closed after bounded exhaustion', function (): void {
    $actor = User::factory()->admin()->create();
    $collision = 'content-groups/covers/01J00000000000000000000010.jpg';
    $unique = 'content-groups/covers/01J00000000000000000000011.jpg';
    Storage::disk('public')->put($collision, 'existing destination', 'public');
    $policy = Mockery::mock(CuratorImageUploadPolicy::class)->makePartial();
    $policy->shouldReceive('generatedPath')->twice()->andReturn($collision, $unique);
    app()->instance(CuratorImageUploadPolicy::class, $policy);

    $media = app(MediaFilesystemMutationCoordinator::class)->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'collision.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );

    expect($media->path)->toBe($unique)
        ->and(Storage::disk('public')->get($collision))->toBe('existing destination');

    app()->forgetInstance(MediaFilesystemMutationCoordinator::class);
    app()->forgetInstance(ImageUploadValidator::class);
    $exhaustedPolicy = Mockery::mock(CuratorImageUploadPolicy::class)->makePartial();
    $exhaustedPolicy->shouldReceive('generatedPath')->times(5)->andReturn($collision);
    app()->instance(CuratorImageUploadPolicy::class, $exhaustedPolicy);
    $beforeOperations = MediaMutationOperation::query()->count();

    expect(fn () => app(MediaFilesystemMutationCoordinator::class)->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'exhausted.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    ))->toThrow(RuntimeException::class, 'unique media destination');

    expect(Media::query()->count())->toBe(1)
        ->and(MediaMutationOperation::query()->count())->toBe($beforeOperations + 1)
        ->and(MediaMutationOperation::query()->latest('id')->firstOrFail()->status)->toBe(MediaMutationStatus::Failed)
        ->and(Storage::disk('public')->get($collision))->toBe('existing destination')
        ->and(Storage::disk('local')->allFiles('media-staging'))->toBe([]);
});

it('authorizes every record before a mixed bulk delete and performs no partial mutation', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $referenced = $coordinator->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'referenced.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $unreferenced = $coordinator->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'unreferenced.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $group = ContentGroup::factory()->create();
    app(MediaAttachmentManager::class)->attach($group, $referenced, MediaAttachmentRole::Cover, $actor);

    expect(fn () => $coordinator->deleteMany([$unreferenced, $referenced], $actor))
        ->toThrow(AuthorizationException::class);

    expect(Media::query()->whereKey([$referenced->getKey(), $unreferenced->getKey()])->count())->toBe(2)
        ->and(MediaMutationOperation::query()->where('operation', MediaMutationOperationType::Delete)->count())->toBe(0);
    Storage::disk('public')->assertExists($referenced->path);
    Storage::disk('public')->assertExists($unreferenced->path);
});

it('compensates copied bytes when the database transaction rolls back and preserves the causal failure', function (): void {
    $actor = User::factory()->admin()->create();
    Media::creating(function (): void {
        throw new RuntimeException('forced media database failure');
    });

    expect(fn () => app(MediaFilesystemMutationCoordinator::class)->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'rollback.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    ))->toThrow(RuntimeException::class, 'forced media database failure');

    $operation = MediaMutationOperation::query()->sole();
    expect($operation->status)->toBe(MediaMutationStatus::Failed)
        ->and($operation->last_error)->toContain('forced media database failure')
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk('local')->allFiles('media-staging'))->toBe([]);
});

it('records a copy failure before any media row is committed and removes verified staging bytes', function (): void {
    $actor = User::factory()->admin()->create();
    $public = Storage::disk('public');
    $failingPublic = Mockery::mock($public)->makePartial();
    $failingPublic->shouldReceive('put')->once()->andReturnFalse();
    Storage::set('public', $failingPublic);

    expect(fn () => app(MediaFilesystemMutationCoordinator::class)->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'copy-failure.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    ))->toThrow(RuntimeException::class, 'could not be copied');

    expect(Media::query()->count())->toBe(0)
        ->and(MediaMutationOperation::query()->sole()->status)->toBe(MediaMutationStatus::Failed)
        ->and($public->allFiles())->toBe([])
        ->and(Storage::disk('local')->allFiles('media-staging'))->toBe([]);
});

it('repairs committed cleanup idempotently and refuses active or corrupt journals without touching unowned bytes', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $sourcePath = 'content-groups/covers/source.jpg';
    $destinationPath = 'content-groups/covers/destination.jpg';
    $contents = curatorG1MutationFixture('valid.jpg.base64');
    $sha = hash('sha256', $contents);
    Storage::disk('public')->put($sourcePath, $contents);
    Storage::disk('public')->put($destinationPath, $contents);
    Storage::disk('local')->put('media-staging/01J00000000000000000000000/normalized.jpg', $contents);
    Storage::disk('local')->put('media-quarantine/01J00000000000000000000000/original.jpg', $contents);
    $media = Media::factory()->create([
        'name' => 'destination',
        'path' => $destinationPath,
        'size' => strlen($contents),
    ]);
    $operation = MediaMutationOperation::factory()->create([
        'operation_key' => '01J00000000000000000000000',
        'media_id' => $media->getKey(),
        'media_id_snapshot' => $media->getKey(),
        'user_id' => $actor->getKey(),
        'media_reference_key' => $media->reference_key,
        'operation' => MediaMutationOperationType::Rename,
        'status' => MediaMutationStatus::CleanupPending,
        'purpose' => ImageUploadPurpose::ContentGroupCover->value,
        'source_disk' => 'public',
        'source_path' => $sourcePath,
        'source_sha256' => $sha,
        'destination_disk' => 'public',
        'destination_path' => $destinationPath,
        'destination_sha256' => $sha,
        'staging_disk' => 'local',
        'staging_path' => 'media-staging/01J00000000000000000000000/normalized.jpg',
        'staging_sha256' => $sha,
        'quarantine_disk' => 'local',
        'quarantine_path' => 'media-quarantine/01J00000000000000000000000/original.jpg',
        'quarantine_sha256' => $sha,
        'context' => ['source_name' => 'source'],
        'lease_token' => null,
        'lease_expires_at' => now()->subMinute(),
        'committed_at' => now()->subMinute(),
    ]);

    expect($coordinator->repair($operation))->toBe('completed_cleanup')
        ->and($coordinator->repair($operation->refresh()))->toBe('already_complete')
        ->and($operation->refresh()->status)->toBe(MediaMutationStatus::Completed);
    Storage::disk('public')->assertMissing($sourcePath);
    Storage::disk('public')->assertExists($destinationPath);
    Storage::disk('local')->assertMissing($operation->staging_path);
    Storage::disk('local')->assertExists($operation->quarantine_path);

    $active = MediaMutationOperation::factory()->create([
        'media_id' => null,
        'operation' => MediaMutationOperationType::Upload,
        'status' => MediaMutationStatus::Staged,
        'purpose' => ImageUploadPurpose::ContentGroupCover->value,
        'lease_token' => '01J00000000000000000000001',
        'lease_expires_at' => now()->addMinute(),
    ]);
    expect($coordinator->repair($active))->toBe('lease_active');

    Storage::disk('public')->put('outside-owned.txt', 'do not touch');
    $corrupt = MediaMutationOperation::factory()->create([
        'media_id' => null,
        'operation' => MediaMutationOperationType::Upload,
        'status' => MediaMutationStatus::Staged,
        'purpose' => ImageUploadPurpose::ContentGroupCover->value,
        'destination_disk' => 'public',
        'destination_path' => '../outside-owned.txt',
        'destination_sha256' => hash('sha256', 'do not touch'),
        'lease_token' => null,
        'lease_expires_at' => now()->subMinute(),
    ]);

    expect($coordinator->repair($corrupt))->toBe('manual_review_required')
        ->and($corrupt->refresh()->status)->toBe(MediaMutationStatus::Failed);
    Storage::disk('public')->assertExists('outside-owned.txt');
});

it('reports invalid journal enums without deleting artifacts or stealing a repair lease', function (): void {
    $contents = curatorG1MutationFixture('valid.jpg.base64');
    $sha = hash('sha256', $contents);
    $operation = MediaMutationOperation::factory()->create([
        'media_id' => null,
        'status' => MediaMutationStatus::Staged,
        'purpose' => ImageUploadPurpose::ContentGroupCover->value,
        'destination_disk' => 'public',
        'destination_path' => 'content-groups/covers/invalid-enum.jpg',
        'destination_sha256' => $sha,
        'staging_disk' => 'local',
        'staging_path' => null,
        'staging_sha256' => $sha,
        'quarantine_disk' => 'local',
        'quarantine_path' => null,
        'quarantine_sha256' => $sha,
        'lease_token' => null,
        'lease_expires_at' => now()->subMinute(),
    ]);
    $stagingPath = "media-staging/{$operation->operation_key}/normalized.jpg";
    $quarantinePath = "media-quarantine/{$operation->operation_key}/original.jpg";
    DB::table('media_mutation_operations')->where('id', $operation->getKey())->update([
        'operation' => 'forged_operation',
        'staging_path' => $stagingPath,
        'quarantine_path' => $quarantinePath,
    ]);
    Storage::disk('public')->put('content-groups/covers/invalid-enum.jpg', $contents, 'public');
    Storage::disk('local')->put($stagingPath, $contents);
    Storage::disk('local')->put($quarantinePath, $contents);

    $this->artisan('media:repair-mutations')
        ->expectsOutputToContain('manual review required: invalid journal enum')
        ->assertSuccessful();

    expect(app(MediaFilesystemMutationCoordinator::class)->repair($operation->refresh()))
        ->toBe('manual_review_required')
        ->and($operation->refresh()->getRawOriginal('status'))->toBe(MediaMutationStatus::Failed->value)
        ->and($operation->lease_token)->toBeNull();
    Storage::disk('public')->assertExists('content-groups/covers/invalid-enum.jpg');
    Storage::disk('local')->assertExists($stagingPath);
    Storage::disk('local')->assertExists($quarantinePath);
});

it('does not overwrite a replacement repair lease after filesystem work', function (): void {
    $contents = curatorG1MutationFixture('valid.jpg.base64');
    $path = 'content-groups/covers/repair-race.jpg';
    $operation = MediaMutationOperation::factory()->create([
        'media_id' => null,
        'operation' => MediaMutationOperationType::Upload,
        'status' => MediaMutationStatus::Staged,
        'purpose' => ImageUploadPurpose::ContentGroupCover->value,
        'destination_disk' => 'public',
        'destination_path' => $path,
        'destination_sha256' => hash('sha256', $contents),
        'lease_token' => null,
        'lease_expires_at' => now()->subMinute(),
    ]);
    $public = Storage::disk('public');
    $public->put($path, $contents, 'public');
    $replacementToken = (string) Str::ulid();
    $racingPublic = Mockery::mock($public)->makePartial();
    $racingPublic->shouldReceive('delete')->once()->with($path)->andReturnUsing(function (string $ownedPath) use ($public, $operation, $replacementToken): bool {
        $deleted = $public->delete($ownedPath);
        DB::table('media_mutation_operations')->where('id', $operation->getKey())->update([
            'lease_token' => $replacementToken,
            'lease_expires_at' => now()->addMinute(),
        ]);

        return $deleted;
    });
    Storage::set('public', $racingPublic);

    expect(app(MediaFilesystemMutationCoordinator::class)->repair($operation))
        ->toBe('lease_lost')
        ->and($operation->refresh()->getRawOriginal('status'))->toBe(MediaMutationStatus::Staged->value)
        ->and($operation->getRawOriginal('lease_token'))->toBe($replacementToken);
    expect($public->exists($path))->toBeFalse();
});

it('reports cleanup pending when recovered commit cleanup remains incomplete', function (): void {
    $actor = User::factory()->admin()->create();
    $media = app(MediaFilesystemMutationCoordinator::class)->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'recovered.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $operation = MediaMutationOperation::query()->sole();
    Storage::disk('local')->put((string) $operation->staging_path, 'foreign staging bytes');
    $operation->forceFill([
        'status' => MediaMutationStatus::Staged,
        'cleanup_completed_at' => null,
        'completed_at' => null,
        'lease_token' => null,
        'lease_expires_at' => now()->subMinute(),
    ])->save();

    expect(app(MediaFilesystemMutationCoordinator::class)->repair($operation))
        ->toBe('cleanup_pending')
        ->and($operation->refresh()->status)->toBe(MediaMutationStatus::CleanupPending)
        ->and($media->fresh()->path)->toBe($operation->destination_path);
    Storage::disk('public')->assertExists($media->path);
    Storage::disk('local')->assertExists((string) $operation->staging_path);
});

it('rejects non-creation journal types at the validated image API boundary', function (): void {
    $actor = User::factory()->admin()->create();
    $validated = app(ImageUploadValidator::class)->validateBytes(
        curatorG1MutationFixture('valid.jpg.base64'),
        'validated.jpg',
        ImageUploadPurpose::ContentGroupCover,
    );

    expect(fn () => app(MediaFilesystemMutationCoordinator::class)->createFromValidatedImage(
        $validated,
        $actor,
        operationType: MediaMutationOperationType::Rename,
    ))->toThrow(InvalidArgumentException::class, 'upload and external import');

    expect(MediaMutationOperation::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('deletes a row whose source bytes are missing and journals the absence instead of failing', function (): void {
    // RECON2 R5 (operator ruling MUX3-F032): the row is the lie when the file
    // is gone, so delete proceeds and the journal records source_missing.
    $actor = User::factory()->admin()->create();
    $media = Media::factory()->create();

    app(MediaFilesystemMutationCoordinator::class)->delete($media, $actor);

    $operation = MediaMutationOperation::query()->sole();

    expect(Media::query()->whereKey($media->getKey())->exists())->toBeFalse()
        ->and($operation->status)->toBe(MediaMutationStatus::Completed)
        ->and($operation->context['source_missing'] ?? null)->toBeTrue()
        ->and($operation->quarantine_path)->toBeNull();
});

it('fences concurrent existing-media mutations and stale journal leases', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $media = $coordinator->createFromUpload(
        curatorG1MutationUpload('valid.jpg.base64', 'fenced.jpg'),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
    );
    $open = MediaMutationOperation::factory()->create([
        'media_id' => $media->getKey(),
        'media_id_snapshot' => $media->getKey(),
        'media_reference_key' => $media->reference_key,
        'operation' => MediaMutationOperationType::Swap,
        'status' => MediaMutationStatus::Staged,
        'lease_token' => (string) Str::ulid(),
        'lease_expires_at' => now()->addMinute(),
    ]);

    expect(fn () => $coordinator->rename($media, $actor))
        ->toThrow(RuntimeException::class, 'incomplete mutation operation');

    $fence = app(MediaMutationFence::class);
    expect(fn () => DB::transaction(fn () => $fence->lockForCommit($open)))
        ->toThrow(RuntimeException::class, 'no longer authorizes');

    $open->update([
        'status' => MediaMutationStatus::Copied,
        'lease_expires_at' => now()->subSecond(),
    ]);
    expect(fn () => DB::transaction(fn () => $fence->lockForCommit($open->refresh())))
        ->toThrow(RuntimeException::class, 'expired or owned');

    $open->update([
        'lease_token' => (string) Str::ulid(),
        'lease_expires_at' => now()->addMinute(),
    ]);
    $stale = $open->refresh();
    DB::table('media_mutation_operations')->where('id', $open->getKey())->update([
        'lease_token' => (string) Str::ulid(),
    ]);
    expect(fn () => DB::transaction(fn () => $fence->lockForCommit($stale)))
        ->toThrow(RuntimeException::class, 'expired or owned');
});
