<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

function quarantinePruneFixture(): string
{
    $encoded = file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'));

    return base64_decode(trim((string) $encoded), true)
        ?: throw new RuntimeException('Invalid media fixture.');
}

/** @param array<string, mixed> $attributes */
function quarantinePruneCompletedOperation(int $ageDays, array $attributes = []): MediaMutationOperation
{
    $operationKey = (string) Str::ulid();
    $contents = quarantinePruneFixture();
    $quarantinePath = "media-quarantine/{$operationKey}/original.jpg";
    Storage::disk('local')->put($quarantinePath, $contents);

    return MediaMutationOperation::factory()->create([
        'operation_key' => $operationKey,
        'media_id' => null,
        'operation' => MediaMutationOperationType::Delete,
        'status' => MediaMutationStatus::Completed,
        'purpose' => ImageUploadPurpose::ContentGroupCover->value,
        'source_disk' => 'public',
        'source_path' => 'content-groups/covers/source.jpg',
        'source_sha256' => hash('sha256', $contents),
        'quarantine_disk' => 'local',
        'quarantine_path' => $quarantinePath,
        'quarantine_sha256' => hash('sha256', $contents),
        'lease_token' => null,
        'lease_expires_at' => null,
        'completed_at' => now()->subDays($ageDays),
        ...$attributes,
    ]);
}

it('reports prunable quarantine directories without deleting anything by default', function (): void {
    $operation = quarantinePruneCompletedOperation(ageDays: 120);

    $this->artisan('media:prune-quarantine')->assertSuccessful();

    Storage::disk('local')->assertExists($operation->quarantine_path);
});

it('prunes aged completed quarantine directories with --apply while keeping the journal truth', function (): void {
    $aged = quarantinePruneCompletedOperation(ageDays: 120);
    $fresh = quarantinePruneCompletedOperation(ageDays: 5);

    $this->artisan('media:prune-quarantine --apply')->assertSuccessful();

    Storage::disk('local')->assertMissing($aged->quarantine_path);
    Storage::disk('local')->assertMissing("media-quarantine/{$aged->operation_key}");
    Storage::disk('local')->assertExists($fresh->quarantine_path);

    $aged->refresh();

    expect($aged->status)->toBe(MediaMutationStatus::Completed)
        ->and($aged->quarantine_path)->not->toBeNull()
        ->and($aged->quarantine_sha256)->not->toBeNull()
        ->and($aged->context['quarantine_pruned_at'] ?? null)->not->toBeNull()
        ->and($fresh->refresh()->context['quarantine_pruned_at'] ?? null)->toBeNull();
});

it('never prunes when the retention window is zero', function (): void {
    config()->set('media.quarantine.retention_days', 0);
    $ancient = quarantinePruneCompletedOperation(ageDays: 3650);

    $this->artisan('media:prune-quarantine --apply')->assertSuccessful();

    Storage::disk('local')->assertExists($ancient->quarantine_path);
});

it('honors a --days override', function (): void {
    $operation = quarantinePruneCompletedOperation(ageDays: 10);

    $this->artisan('media:prune-quarantine --apply --days=30')->assertSuccessful();
    Storage::disk('local')->assertExists($operation->quarantine_path);

    $this->artisan('media:prune-quarantine --apply --days=7')->assertSuccessful();
    Storage::disk('local')->assertMissing($operation->quarantine_path);
});

it('skips journal rows whose key or quarantine path is not shaped like the machinery wrote it', function (): void {
    $malformedKey = quarantinePruneCompletedOperation(ageDays: 120, attributes: [
        'operation_key' => 'evil-key',
        'quarantine_path' => 'media-quarantine/evil-key/original.jpg',
    ]);
    Storage::disk('local')->put('media-quarantine/evil-key/original.jpg', quarantinePruneFixture());

    $mismatchedPath = quarantinePruneCompletedOperation(ageDays: 120, attributes: [
        'quarantine_path' => 'media-quarantine/01J00000000000000000000009/original.jpg',
    ]);
    Storage::disk('local')->put('media-quarantine/01J00000000000000000000009/original.jpg', quarantinePruneFixture());

    $this->artisan('media:prune-quarantine --apply')->assertFailed();

    Storage::disk('local')->assertExists('media-quarantine/evil-key/original.jpg');
    Storage::disk('local')->assertExists('media-quarantine/01J00000000000000000000009/original.jpg');
    Storage::disk('local')->assertExists("media-quarantine/{$mismatchedPath->operation_key}/original.jpg");
});

it('keeps aged incomplete operations intact so repair can still finish their cleanup', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);
    $sourcePath = 'content-groups/covers/source.jpg';
    $destinationPath = 'content-groups/covers/destination.jpg';
    $contents = quarantinePruneFixture();
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
        'lease_expires_at' => now()->subDays(120),
        'committed_at' => now()->subDays(120),
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
        'completed_at' => null,
    ]);

    $this->artisan('media:prune-quarantine --apply')->assertSuccessful();

    Storage::disk('local')->assertExists($operation->quarantine_path);

    expect($coordinator->repair($operation->refresh()))->toBe('completed_cleanup')
        ->and($operation->refresh()->status)->toBe(MediaMutationStatus::Completed);
    Storage::disk('local')->assertExists($operation->quarantine_path);
});

it('snapshots human captions into the delete journal context', function (): void {
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);

    $media = $coordinator->createFromUpload(
        UploadedFile::fake()->createWithContent('captioned.jpg', quarantinePruneFixture()),
        ImageUploadPurpose::ContentGroupCover,
        $actor,
        ['title' => 'כותרת שמורה'],
    );
    $media->forceFill([
        'alt' => 'טקסט חלופי',
        'caption' => 'כיתוב מלא',
        'description' => 'תיאור ארוך לשחזור עתידי',
    ])->save();

    $coordinator->delete($media->refresh(), $actor);

    $context = MediaMutationOperation::query()
        ->where('operation', MediaMutationOperationType::Delete->value)
        ->latest('id')
        ->firstOrFail()
        ->context;

    expect($context['alt'] ?? null)->toBe('טקסט חלופי')
        ->and($context['title'] ?? null)->toBe('כותרת שמורה')
        ->and($context['caption'] ?? null)->toBe('כיתוב מלא')
        ->and($context['description'] ?? null)->toBe('תיאור ארוך לשחזור עתידי');
});
