<?php

use App\Enums\MediaDiagnosticReason;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\MediaRelocationBatch;
use App\Support\Media\PublicMediaDelivery;
use App\Support\Media\SvgUploadSanitizer;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
});

function relocationRootRow(string $identity, string $contents, array $overrides = []): Media
{
    $extension = (string) ($overrides['ext'] ?? 'jpg');
    $path = "{$identity}.{$extension}";
    Storage::disk('public')->put($path, $contents);
    /** @var Media $media */
    $media = Media::factory()->create(array_merge([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => '',
        'visibility' => 'public',
        'name' => $identity,
        'path' => $path,
        'width' => 100,
        'height' => 100,
        'size' => strlen($contents),
        'type' => 'image/jpeg',
        'ext' => $extension,
        'title' => Str::headline($identity),
    ], $overrides));

    return $media;
}

function relocationJpegBytes(): string
{
    return base64_decode(
        trim((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'))),
        true,
    ) ?: '';
}

it('grants the relocate ability only to unmanaged rows with unique identity', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $gate = Gate::forUser($admin);

    $rootRow = relocationRootRow('podcast_legacy_cover', relocationJpegBytes());

    expect($gate->inspect('relocate', $rootRow)->allowed())->toBeTrue();

    $managed = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'name' => 'already-managed',
        'path' => 'content-groups/covers/already-managed.jpg',
    ]);
    Storage::disk('public')->put($managed->path, relocationJpegBytes());
    $managedResponse = $gate->inspect('relocate', $managed);

    expect($managedResponse->allowed())->toBeFalse()
        ->and($managedResponse->message())->toBe(__('admin.media_library.op_blocked_already_managed'));
});

it('relocates a referenced root cover into the managed root rewriting its references in one commit', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $bytes = relocationJpegBytes();
    $media = relocationRootRow('podcast_2', $bytes, [
        'size' => strlen($bytes),
    ]);
    $referenceKey = (string) $media->reference_key;
    $group = ContentGroup::factory()->create([
        'title' => 'הפודקאסט הוותיק',
        'cover_path' => $media->path,
    ]);

    expect(app(MediaRecordScope::class)->allows($media))->toBeFalse();

    $updated = app(MediaFilesystemMutationCoordinator::class)->relocate($media, $admin);

    expect((string) $updated->directory)->toBe('content-groups/covers')
        ->and((string) $updated->reference_key)->toBe($referenceKey)
        ->and((int) $updated->getKey())->toBe((int) $media->getKey())
        ->and(app(MediaRecordScope::class)->allows($updated))->toBeTrue()
        ->and((string) Storage::disk('public')->get($updated->path))->toBe($bytes)
        ->and(Storage::disk('public')->exists('podcast_2.jpg'))->toBeFalse()
        ->and((string) $group->refresh()->cover_path)->toBe((string) $updated->path)
        ->and(MediaAttachment::query()
            ->where('media_id', $media->getKey())
            ->where('attachable_type', 'content_group')
            ->where('attachable_id', $group->getKey())
            ->count())->toBe(1)
        ->and(MediaMutationOperation::query()->where('operation', 'relocation')->first()?->getRawOriginal('status'))->toBe('completed')
        ->and(app(MediaInventoryDiagnostics::class)->reasons($updated->refresh()))->toBe([]);

    $again = Gate::forUser($admin)->inspect('relocate', $updated);

    expect($again->allowed())->toBeFalse()
        ->and($again->message())->toBe(__('admin.media_library.op_blocked_already_managed'));
});

it('relocates a trusted unsafe svg verbatim keeping the trust mark', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $malicious = (string) file_get_contents(base_path('tests/Fixtures/media/malicious.svg'));
    $media = relocationRootRow('trusted_legacy_logo', $malicious, [
        'ext' => 'svg',
        'type' => 'image/svg+xml',
        'width' => null,
        'height' => null,
        'trusted_at' => now(),
        'trusted_by_user_id' => $admin->getKey(),
    ]);

    $updated = app(MediaFilesystemMutationCoordinator::class)->relocate($media, $admin);

    expect((string) Storage::disk('public')->get($updated->path))->toBe($malicious)
        ->and((string) $updated->directory)->toBe('content-groups/covers')
        ->and($updated->trusted_at)->not->toBeNull()
        ->and(app(PublicMediaDelivery::class)->canRenderInline($updated))->toBeTrue();
});

it('normalizes an unsafe but cleanable root svg during relocation', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $prologSvg = "<?xml version=\"1.0\"?>\n<!-- legacy prolog -->\n"
        .(string) file_get_contents(base_path('tests/Fixtures/media/clean.svg'));
    $media = relocationRootRow('normalizable_logo', $prologSvg, [
        'ext' => 'svg',
        'type' => 'image/svg+xml',
        'width' => null,
        'height' => null,
    ]);

    $updated = app(MediaFilesystemMutationCoordinator::class)->relocate($media, $admin);
    $sanitized = app(SvgUploadSanitizer::class)->sanitize($prologSvg);

    expect((string) Storage::disk('public')->get($updated->path))->toBe($sanitized)
        ->and(app(MediaInventoryDiagnostics::class)->reasons($updated->refresh()))->toBe([]);
});

it('refuses relocation for unsanitizable untrusted svg and oversized sources without changes', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $malicious = (string) file_get_contents(base_path('tests/Fixtures/media/malicious.svg'));
    $refusal = relocationRootRow('refusal_logo', $malicious, [
        'ext' => 'svg',
        'type' => 'image/svg+xml',
        'width' => null,
        'height' => null,
    ]);

    expect(fn () => app(MediaFilesystemMutationCoordinator::class)->relocate($refusal, $admin))
        ->toThrow(InvalidArgumentException::class);

    expect(Storage::disk('public')->exists($refusal->path))->toBeTrue()
        ->and(MediaMutationOperation::query()->count())->toBe(0)
        ->and($refusal->refresh()->directory)->toBe('');

    expect(in_array(
        MediaDiagnosticReason::UnsanitizedSvg->value,
        app(MediaInventoryDiagnostics::class)->reasons($refusal),
        true,
    ))->toBeTrue();
});

it('runs idempotently as a resume: an already-relocated row simply denies again', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $bytes = relocationJpegBytes();
    $media = relocationRootRow('resume_case', $bytes, ['size' => strlen($bytes)]);

    app(MediaFilesystemMutationCoordinator::class)->relocate($media, $admin);

    expect(fn () => app(MediaFilesystemMutationCoordinator::class)->relocate($media->refresh(), $admin))
        ->toThrow(AuthorizationException::class);

    expect(MediaMutationOperation::query()->where('operation', 'relocation')->count())->toBe(1);
});

it('censuses and executes the batch with truthful fates and no failure spinning', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $bytes = relocationJpegBytes();
    $movable = relocationRootRow('movable_cover', $bytes, ['size' => strlen($bytes)]);
    $oversized = relocationRootRow('oversized_cover', $bytes, ['size' => strlen($bytes), 'width' => 7000, 'height' => 7000]);
    $refusal = relocationRootRow('refusal_batch_logo', (string) file_get_contents(base_path('tests/Fixtures/media/malicious.svg')), [
        'ext' => 'svg',
        'type' => 'image/svg+xml',
        'width' => null,
        'height' => null,
    ]);
    Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'name' => 'managed-bystander',
        'path' => 'content-groups/covers/managed-bystander.jpg',
    ]);

    $batch = app(MediaRelocationBatch::class);
    $census = $batch->census($admin);

    expect($census['relocatable']->count())->toBe(2)
        ->and(collect($census['skipped'])->pluck('reason')->all())
        ->toContain(__('admin.media_library.relocation_skip_oversized'));

    $first = $batch->executeChunk($admin, 10);

    expect($first['moved'])->toBe(1)
        ->and(count($first['failed']))->toBe(1)
        ->and($first['failed'][0]['reason'])->toBe(__('admin.media_library.relocation_fail_unsafe'))
        ->and($first['remaining'])->toBe(0)
        ->and((string) $movable->refresh()->directory)->toBe('content-groups/covers');

    $second = $batch->executeChunk($admin, 10, array_column($first['failed'], 'id'));

    expect($second['moved'])->toBe(0)
        ->and($second['failed'])->toBe([])
        ->and((string) $refusal->refresh()->directory)->toBe('');
});

it('runs the admin relocation action end to end with census modal and durable receipt', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $bytes = relocationJpegBytes();
    relocationRootRow('admin_run_one', $bytes, ['size' => strlen($bytes)]);
    relocationRootRow('admin_run_two', $bytes, ['size' => strlen($bytes)]);

    $component = Livewire::test(ListMedia::class)
        ->assertActionVisible(TestAction::make('relocateRootFiles')->table());

    $modalHtml = $component
        ->mountAction(TestAction::make('relocateRootFiles')->table())
        ->getMountedActionModalHtml();

    expect($modalHtml)
        ->toContain('data-testid="media-relocation-census"')
        ->toContain(__('admin.media_library.relocation_census_move', ['count' => 2]))
        ->toContain(__('admin.media_library.relocation_consequence'));

    $component
        ->callMountedAction()
        ->assertNotified(__('admin.media_library.relocation_done_title'));

    expect($component->get('relocationRun'))->toBeNull()
        ->and(Media::query()->where('path', 'not like', '%/%')->count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(1);
});

it('reports and applies through the artisan wrapper', function (): void {
    $admin = User::factory()->admin()->create();
    $bytes = relocationJpegBytes();
    $row = relocationRootRow('cli_cover', $bytes, ['size' => strlen($bytes)]);

    $this->artisan('media:relocate-root', ['--user' => $admin->getKey()])
        ->expectsOutputToContain('Census: 1 relocatable')
        ->expectsOutputToContain('Report only.')
        ->assertExitCode(0);

    expect((string) $row->refresh()->directory)->toBe('');

    $this->artisan('media:relocate-root', ['--apply' => true, '--user' => $admin->getKey()])
        ->expectsOutputToContain('Finished: 1 moved, 0 failed')
        ->assertExitCode(0);

    expect((string) $row->refresh()->directory)->toBe('content-groups/covers');
});
