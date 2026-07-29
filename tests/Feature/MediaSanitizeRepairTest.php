<?php

use App\Enums\MediaDiagnosticReason;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Pages\ReviewMediaIssues;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaIssueReviewPresenter;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\PublicMediaDelivery;
use App\Support\Media\SvgUploadSanitizer;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
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

function sanitizeRepairSvgRow(string $identity, string $contents, array $overrides = []): Media
{
    $path = "content-groups/covers/{$identity}.svg";
    Storage::disk('public')->put($path, $contents);
    /** @var Media $media */
    $media = Media::factory()->create(array_merge([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => 'content-groups/covers',
        'visibility' => 'public',
        'name' => $identity,
        'path' => $path,
        'width' => null,
        'height' => null,
        'size' => strlen($contents),
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'title' => Str::headline($identity),
    ], $overrides));

    return $media;
}

function sanitizeRepairNormalizableSvg(): string
{
    return "<?xml version=\"1.0\"?>\n<!-- legacy export prolog -->\n"
        .(string) file_get_contents(base_path('tests/Fixtures/media/clean.svg'));
}

it('grants the repair ability only to managed unreferenced rows with the svg reason', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $gate = Gate::forUser($admin);

    $repairable = sanitizeRepairSvgRow('repairable', sanitizeRepairNormalizableSvg());

    expect($gate->inspect('repair', $repairable)->allowed())->toBeTrue();

    $inUse = sanitizeRepairSvgRow('in-use', sanitizeRepairNormalizableSvg());
    $group = ContentGroup::factory()->create(['title' => 'פודקאסט מחובר', 'cover_path' => $inUse->path]);
    $inUseResponse = $gate->inspect('repair', $inUse);

    expect($inUseResponse->allowed())->toBeFalse()
        ->and($inUseResponse->message())->toContain($group->title);

    $safeBytes = app(SvgUploadSanitizer::class)->sanitize(
        (string) file_get_contents(base_path('tests/Fixtures/media/clean.svg')),
    );
    $healthy = sanitizeRepairSvgRow('healthy', $safeBytes);
    $healthyResponse = $gate->inspect('repair', $healthy);

    expect($healthyResponse->allowed())->toBeFalse()
        ->and($healthyResponse->message())->toBe(__('admin.media_issue_review.sanitize.not_applicable'));

    $rootLevel = sanitizeRepairSvgRow('root-level', sanitizeRepairNormalizableSvg(), [
        'directory' => '',
        'path' => 'root-level.svg',
    ]);
    Storage::disk('public')->put('root-level.svg', sanitizeRepairNormalizableSvg());

    expect($gate->inspect('repair', $rootLevel)->allowed())->toBeTrue();
});

it('projects truthful per-reason resolution states in the review presenter', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $presenter = app(MediaIssueReviewPresenter::class);

    $repairable = sanitizeRepairSvgRow('resolution-action', sanitizeRepairNormalizableSvg());
    $review = $presenter->review($repairable);
    $svgIssue = collect($review['issues'])->firstWhere('value', MediaDiagnosticReason::UnsanitizedSvg->value);

    expect($svgIssue['resolution']['kind'])->toBe('action')
        ->and($svgIssue['resolution']['label'])->toBe(__('admin.media_issue_review.sanitize.action'))
        ->and($review['has_current_media_repair_authority'])->toBeTrue()
        ->and($review['issues_have_resolution_content'])->toBeTrue();

    $inUse = sanitizeRepairSvgRow('resolution-blocked', sanitizeRepairNormalizableSvg());
    ContentGroup::factory()->create(['title' => 'בעלים חוסם', 'cover_path' => $inUse->path]);
    $blockedReview = $presenter->review($inUse);
    $blockedIssue = collect($blockedReview['issues'])->firstWhere('value', MediaDiagnosticReason::UnsanitizedSvg->value);

    expect($blockedIssue['resolution']['kind'])->toBe('blocked')
        ->and($blockedIssue['resolution']['reason'])->toContain('בעלים חוסם')
        ->and($blockedReview['has_current_media_repair_authority'])->toBeFalse()
        ->and($blockedReview['issues_have_resolution_content'])->toBeTrue();

    $rootMetadata = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => '',
        'visibility' => 'public',
        'name' => 'podcast_legacy',
        'path' => 'podcast_legacy.jpg',
        'width' => 100,
        'height' => 100,
        'size' => 1024,
        'type' => 'image/jpeg',
        'ext' => 'jpg',
    ]);
    Storage::disk('public')->put('podcast_legacy.jpg', 'legacy raster');
    $rootReview = $presenter->review($rootMetadata);
    $metadataIssue = collect($rootReview['issues'])->firstWhere('value', MediaDiagnosticReason::Metadata->value);

    expect($metadataIssue)->not->toBeNull()
        ->and($metadataIssue['resolution']['kind'])->toBe('separate_phase')
        ->and($metadataIssue['resolution']['description'])->toBe(__('admin.media_issue_review.resolution.metadata_root'))
        ->and($rootReview['has_current_media_repair_authority'])->toBeFalse();
});

it('sanitizes a managed unsafe svg end to end with journal receipt and fate chips', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);
    $media = sanitizeRepairSvgRow('sanitize-me', sanitizeRepairNormalizableSvg());
    $originalPath = (string) $media->path;

    $component = Livewire::test(ReviewMediaIssues::class, ['record' => $media->getKey()])
        ->assertSee(__('admin.media_issue_review.sanitize.action'));

    $modalHtml = $component->mountAction('sanitizeFile')->getMountedActionModalHtml();

    expect($modalHtml)
        ->toContain('data-testid="media-operation-consequence"')
        ->toContain(__('admin.media_library.sanitize_consequence'))
        ->toContain(__('admin.media_issue_review.sanitize.not_swap_hint'));

    $component
        ->callMountedAction()
        ->assertNotified();

    $media->refresh();

    expect($media->path)->not->toBe($originalPath)
        ->and((string) $media->directory)->toBe('content-groups/covers')
        ->and(app(MediaRecordScope::class)->allows($media))->toBeTrue()
        ->and(app(MediaInventoryDiagnostics::class)->reasons($media))->toBe([])
        ->and(MediaMutationOperation::query()->where('operation', 'sanitize')->count())->toBe(1)
        ->and($user->notifications()->count())->toBe(1);

    $component
        ->assertSee('data-testid="media-sanitize-result"', false)
        ->assertSee(__('admin.media_library.repair_unsanitized_svg'))
        ->assertSee(__('admin.media_issue_review.resolution.closed_status'));
});

it('refuses an unsanitizable svg without changes and offers the swap and delete routes', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);
    $malicious = (string) file_get_contents(base_path('tests/Fixtures/media/malicious.svg'));
    $media = sanitizeRepairSvgRow('refuse-me', $malicious);

    $component = Livewire::test(ReviewMediaIssues::class, ['record' => $media->getKey()])
        ->callAction('sanitizeFile')
        ->assertNotified(__('admin.media_issue_review.sanitize.refusal_title'));

    expect($component->get('sanitizeRefused'))->toBeTrue()
        ->and(MediaMutationOperation::query()->count())->toBe(0)
        ->and((string) Storage::disk('public')->get($media->path))->toBe($malicious);

    $component
        ->assertSee('data-testid="media-sanitize-refusal"', false)
        ->assertSee(__('admin.media_issue_review.sanitize.refusal_routes'));

    $component->callAction('swapFile', [
        'replacement' => UploadedFile::fake()->createWithContent(
            'clean.svg',
            (string) file_get_contents(base_path('tests/Fixtures/media/clean.svg')),
        ),
    ])->assertNotified(__('admin.media_library.swap_done_title'));

    $media->refresh();

    expect(app(MediaInventoryDiagnostics::class)->reasons($media))->toBe([])
        ->and($component->get('sanitizeRefused'))->toBeFalse();

    $deletable = sanitizeRepairSvgRow('delete-route', $malicious);

    Livewire::test(ReviewMediaIssues::class, ['record' => $deletable->getKey()])
        ->callAction('deleteFile')
        ->assertNotified()
        ->assertRedirect();

    expect(Media::query()->whereKey($deletable->getKey())->exists())->toBeFalse();
});

it('exposes the issue review card action only for rows needing attention', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $broken = sanitizeRepairSvgRow('card-broken', sanitizeRepairNormalizableSvg());
    $healthy = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'name' => 'card-healthy',
        'path' => 'content-groups/covers/card-healthy.jpg',
    ]);
    Storage::disk('public')->put($healthy->path, 'healthy raster');

    Livewire::test(ListMedia::class)
        ->assertActionVisible(TestAction::make('reviewIssues')->table($broken))
        ->assertActionHidden(TestAction::make('reviewIssues')->table($healthy));
});

it('settles an unsanitizable svg with an accountable trust mark and revokes it cleanly', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);
    $malicious = (string) file_get_contents(base_path('tests/Fixtures/media/malicious.svg'));
    $media = sanitizeRepairSvgRow('trust-me', $malicious);
    $gate = Gate::forUser($user);

    expect($gate->inspect('trust', $media)->allowed())->toBeTrue();

    $healthyJpeg = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'name' => 'trust-na',
        'path' => 'content-groups/covers/trust-na.jpg',
    ]);
    Storage::disk('public')->put($healthyJpeg->path, 'raster');
    $notApplicable = $gate->inspect('trust', $healthyJpeg);

    expect($notApplicable->allowed())->toBeFalse()
        ->and($notApplicable->message())->toBe(__('admin.media_issue_review.trust.not_applicable'));

    $component = Livewire::test(ReviewMediaIssues::class, ['record' => $media->getKey()])
        ->callAction('sanitizeFile');

    $component->assertSee('data-testid="media-sanitize-refusal-trust"', false);

    $modalHtml = $component->mountAction('trustFile')->getMountedActionModalHtml();

    expect($modalHtml)->toContain(__('admin.media_issue_review.trust.consequence'));

    $component
        ->callMountedAction()
        ->assertNotified(__('admin.media_issue_review.trust.done_title', ['name' => (string) $media->title]));

    $media->refresh();

    expect($media->trusted_at)->not->toBeNull()
        ->and((int) $media->trusted_by_user_id)->toBe((int) $user->getKey())
        ->and(app(PublicMediaDelivery::class)->canRenderInline($media))->toBeTrue()
        ->and(app(MediaInventoryDiagnostics::class)->reasons($media))->toBe([])
        ->and((string) Storage::disk('public')->get($media->path))->toBe($malicious)
        ->and(MediaMutationOperation::query()->count())->toBe(0);

    $strip = Livewire::test(ReviewMediaIssues::class, ['record' => $media->getKey()])
        ->assertSee('data-testid="media-trusted-strip"', false)
        ->assertSee(__('admin.media_issue_review.no_current_issues'));

    $strip
        ->callAction('untrustFile')
        ->assertNotified(__('admin.media_issue_review.trust.untrust_done_title', ['name' => (string) $media->title]));

    $media->refresh();

    expect($media->trusted_at)->toBeNull()
        ->and(app(MediaInventoryDiagnostics::class)->reasons($media))
        ->toContain(MediaDiagnosticReason::UnsanitizedSvg->value);
});

it('sanitizes a root-level svg and relocates it into the managed covers root in one step', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);
    $contents = sanitizeRepairNormalizableSvg();
    Storage::disk('public')->put('podcast_legacy_logo.svg', $contents);
    $media = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => '',
        'visibility' => 'public',
        'name' => 'podcast_legacy_logo',
        'path' => 'podcast_legacy_logo.svg',
        'width' => null,
        'height' => null,
        'size' => strlen($contents),
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'title' => 'לוגו מדור קודם',
    ]);
    $referenceKey = (string) $media->reference_key;
    $reasonsBefore = app(MediaInventoryDiagnostics::class)->reasons($media);

    expect($reasonsBefore)->toContain(MediaDiagnosticReason::UnsanitizedSvg->value)
        ->and(app(MediaRecordScope::class)->allows($media))->toBeFalse();

    Livewire::test(ReviewMediaIssues::class, ['record' => $media->getKey()])
        ->assertSee(__('admin.media_issue_review.sanitize.action'))
        ->callAction('sanitizeFile')
        ->assertNotified(__('admin.media_issue_review.sanitize.done_title', ['name' => 'לוגו מדור קודם']));

    $media->refresh();

    expect((string) $media->directory)->toBe('content-groups/covers')
        ->and((string) $media->reference_key)->toBe($referenceKey)
        ->and(app(MediaRecordScope::class)->allows($media))->toBeTrue()
        ->and(app(MediaInventoryDiagnostics::class)->reasons($media))->toBe([])
        ->and(MediaMutationOperation::query()->where('operation', 'sanitize')->count())->toBe(1)
        ->and(MediaMutationOperation::query()->where('operation', 'sanitize')->first()?->getRawOriginal('status'))->toBe('completed')
        ->and(Storage::disk('public')->exists('podcast_legacy_logo.svg'))->toBeFalse();
});
