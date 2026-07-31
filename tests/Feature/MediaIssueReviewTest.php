<?php

use App\Enums\MediaAttachmentRole;
use App\Enums\MediaDiagnosticReason;
use App\Enums\MediaLibraryTask;
use App\Enums\UserRole;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Pages\ReviewMediaIssues;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaIssueReviewPresenter;
use App\Support\Media\MediaLibraryContext;
use App\Support\Media\MediaLibraryTaskQuery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
    app()->setLocale('en');
});

function mediaIssueFixture(
    string $identity,
    array $overrides = [],
    string|false $contents = 'valid raster fixture',
): Media {
    $disk = (string) ($overrides['disk'] ?? 'public');
    $directory = (string) ($overrides['directory'] ?? 'content-groups/covers');
    $extension = (string) ($overrides['ext'] ?? 'jpg');
    $path = (string) ($overrides['path'] ?? "{$directory}/{$identity}.{$extension}");
    $media = Media::factory()->create(array_merge([
        'reference_key' => (string) Str::ulid(),
        'disk' => $disk,
        'directory' => $directory,
        'visibility' => 'public',
        'name' => $identity,
        'path' => $path,
        'width' => 100,
        'height' => 100,
        'size' => is_string($contents) ? max(strlen($contents), 1) : 1024,
        'type' => 'image/jpeg',
        'ext' => $extension,
        'title' => Str::headline($identity),
        'exif' => ['original_filename' => "{$identity}-original.{$extension}"],
    ], $overrides));
    assert($media instanceof Media);

    if (
        is_string($contents)
        && array_key_exists($disk, config('filesystems.disks', []))
    ) {
        Storage::disk($disk)->put($media->path, $contents);
    }

    return $media;
}

/** @return array<string, int|string|null> */
function mediaIssueContext(Media $media, array $overrides = []): array
{
    return array_merge([
        'v' => 1,
        'task' => MediaLibraryTask::NeedsAttention->value,
        'mime' => 'image/jpeg',
        'reason' => MediaDiagnosticReason::MissingFile->value,
        'search' => null,
        'sort' => 'created_at:desc',
        'page' => 2,
        'focus' => (int) $media->getKey(),
    ], $overrides);
}

function mediaIssueSaveSetting(string $name, array $payload): void
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

it('registers a view-authorized inventory-wide Review issues page', function (): void {
    $media = mediaIssueFixture('review-route', ['disk' => 'local'], false);
    $origin = app(MediaLibraryContext::class)->continuationToken(mediaIssueContext($media));

    $this->actingAs(User::factory()->role(UserRole::Moderator)->create());

    Livewire::withQueryParams(['origin' => $origin])
        ->test(ReviewMediaIssues::class, ['record' => $media->getRouteKey()])
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::withQueryParams(['origin' => $origin])
        ->test(ReviewMediaIssues::class, ['record' => $media->getRouteKey()])
        ->assertOk()
        ->assertSee(__('admin.media_issue_review.heading'));

    $this->get(MediaResource::getUrl('review-issues', ['record' => 999_999]))
        ->assertNotFound();
});

it('revises Media details around stable identity and keeps descriptive Save separate from repair', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = mediaIssueFixture('descriptive-is-not-repair', [
        'title' => 'Stable Media identity',
        'width' => 0,
    ], false);
    $before = $media->only([
        'disk',
        'directory',
        'visibility',
        'name',
        'path',
        'width',
        'height',
        'size',
        'type',
        'ext',
        'reference_key',
    ]);
    $from = mediaIssueContext($media, [
        'reason' => MediaDiagnosticReason::Metadata->value,
        'search' => 'Stable Media',
    ]);
    $component = Livewire::withQueryParams(['from' => $from])
        ->test(EditMedia::class, ['record' => $media->getRouteKey()])
        ->assertOk()
        ->assertSee('Stable Media identity')
        ->assertSee('descriptive-is-not-repair-original.jpg')
        ->assertSee(__('admin.media_issue_review.details.describe_heading'))
        ->assertSee(__('admin.media_issue_review.details.describe_not_repair'))
        ->assertSee(__('admin.media_issue_review.review_issues'))
        ->assertSee(__('admin.media_library.repair_missing_file'))
        ->assertSee(trans_choice(
            'admin.media_library.additional_issue_count',
            1,
            ['count' => 1],
        ));
    $reviewUrl = $component->instance()->issueReviewUrl();
    parse_str((string) parse_url($reviewUrl, PHP_URL_QUERY), $reviewQuery);
    $restored = app(MediaLibraryContext::class)->fromContinuationToken(
        $reviewQuery['origin'] ?? null,
        999,
    );

    expect(parse_url($reviewUrl, PHP_URL_PATH))->toBe(parse_url(
        MediaResource::getUrl('review-issues', ['record' => $media]),
        PHP_URL_PATH,
    ))->and($restored)->toBe($from);

    $component
        ->fillForm([
            'title' => 'Updated descriptive title',
            'alt' => 'Updated descriptive alternative text',
            'caption' => 'Updated descriptive caption',
            'description' => 'Updated descriptive description',
            'width' => 640,
            'path' => 'forged/repaired.jpg',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNoRedirect();

    $media->refresh();

    expect($media->title)->toBe('Updated descriptive title')
        ->and($media->alt)->toBe('Updated descriptive alternative text')
        ->and($media->only(array_keys($before)))->toBe($before)
        ->and(app(MediaInventoryDiagnostics::class)->reasons($media))
        ->toContain(MediaDiagnosticReason::Metadata->value);
});

it('shows a healthy details state without inventing an issue route', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = mediaIssueFixture('healthy-details');

    Livewire::test(EditMedia::class, ['record' => $media->getRouteKey()])
        ->assertOk()
        ->assertSee(__('admin.media_issue_review.details.ready'))
        ->assertDontSee(__('admin.media_issue_review.review_issues'));
});

it('explains every current diagnostic reason with cause consequence and evidence limits', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $portable = mediaIssueFixture('reason-portable');
    DB::table($portable->getTable())
        ->where($portable->getKeyName(), $portable->getKey())
        ->update(['reference_key' => null]);
    $portable->refresh();
    $storageDisk = mediaIssueFixture('reason-storage', [
        'disk' => 'missing-media-disk',
    ], false);
    $missingFile = mediaIssueFixture('reason-missing', [], false);
    $audience = mediaIssueFixture('reason-audience', [
        'disk' => 'local',
        'visibility' => 'private',
    ]);
    $maliciousSvg = (string) file_get_contents(base_path('tests/Fixtures/media/malicious.svg'));
    $unsafeSvg = mediaIssueFixture('reason-unsafe-svg', [
        'path' => 'content-groups/covers/reason-unsafe-svg.svg',
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
        'size' => strlen($maliciousSvg),
    ], $maliciousSvg);
    $metadata = mediaIssueFixture('reason-metadata', ['height' => 0]);
    $records = [
        MediaDiagnosticReason::PortableIdentity->value => $portable,
        MediaDiagnosticReason::StorageDisk->value => $storageDisk,
        MediaDiagnosticReason::MissingFile->value => $missingFile,
        MediaDiagnosticReason::AudienceDenied->value => $audience,
        MediaDiagnosticReason::UnsanitizedSvg->value => $unsafeSvg,
        MediaDiagnosticReason::Metadata->value => $metadata,
    ];
    $presenter = app(MediaIssueReviewPresenter::class);

    foreach ($records as $reason => $media) {
        $issue = collect($presenter->review($media)['issues'])
            ->firstWhere('value', $reason);

        expect($issue)->not->toBeNull()
            ->and($issue['label'])->toBe(__("admin.media_library.repair_{$reason}"))
            ->and($issue['cause'])->toBe(__("admin.media_issue_review.reasons.{$reason}.cause"))
            ->and($issue['consequence'])->toBe(__("admin.media_issue_review.reasons.{$reason}.consequence"))
            ->and($issue['evidence_limit'])->toBe(__("admin.media_issue_review.reasons.{$reason}.evidence_limit"))
            ->and($issue['facts'])->not->toBeEmpty();
    }
});

it('separates canonical owner routes from bounded settings evidence', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = mediaIssueFixture('owner-impact', [], false);
    $group = ContentGroup::factory()->create(['title' => 'Canonical podcast owner']);
    $item = ContentItem::factory()->create(['title' => 'Canonical episode owner']);
    MediaAttachment::factory()->create([
        'media_id' => $media,
        'attachable_type' => 'content_group',
        'attachable_id' => $group,
        'role' => MediaAttachmentRole::Cover,
    ]);
    MediaAttachment::factory()->create([
        'media_id' => $media,
        'attachable_type' => 'content_item',
        'attachable_id' => $item,
        'role' => MediaAttachmentRole::PrimaryImage,
    ]);
    DB::table('media_attachments')->insert([
        [
            'media_id' => $media->getKey(),
            'attachable_type' => 'content_group',
            'attachable_id' => 999_991,
            'role' => MediaAttachmentRole::Cover->value,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'media_id' => $media->getKey(),
            'attachable_type' => 'unsupported_owner',
            'attachable_id' => 999_992,
            'role' => 'unsupported_role',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    mediaIssueSaveSetting('default_images', [
        'content_group' => [
            'path' => $media->path,
            'media_reference_key' => $media->reference_key,
        ],
    ]);

    $review = app(MediaIssueReviewPresenter::class)->review($media);
    $owners = collect($review['owners'])->keyBy('type');

    expect($owners)->toHaveCount(2)
        ->and($owners['content_group']['title'])->toBe('Canonical podcast owner')
        ->and($owners['content_group']['action_url'])->toBe(
            ContentGroupResource::getUrl('edit', ['record' => $group]),
        )
        ->and($owners['content_item']['title'])->toBe('Canonical episode owner')
        ->and($owners['content_item']['action_url'])->toBe(
            ContentItemResource::getUrl('workspace', ['record' => $item]),
        )
        ->and($review['unresolved_attachment_count'])->toBe(2)
        ->and($review['non_attachment_references'])->toContain(
            __('admin.media_references.default_image', [
                'family' => __('admin.default_image_families.content_group'),
            ]),
        )
        ->and($review['has_current_media_repair_authority'])->toBeFalse();
});

it('keeps canonical owner evidence queries bounded as attachment counts grow', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = mediaIssueFixture('bounded-owner-queries', [], false);
    $groups = ContentGroup::factory()->count(12)->create();
    $items = ContentItem::factory()->count(12)->create();

    $groups->each(fn (ContentGroup $group): MediaAttachment => MediaAttachment::factory()->create([
        'media_id' => $media,
        'attachable_type' => 'content_group',
        'attachable_id' => $group,
        'role' => MediaAttachmentRole::Cover,
    ]));
    $items->each(fn (ContentItem $item): MediaAttachment => MediaAttachment::factory()->create([
        'media_id' => $media,
        'attachable_type' => 'content_item',
        'attachable_id' => $item,
        'role' => MediaAttachmentRole::PrimaryImage,
    ]));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $review = app(MediaIssueReviewPresenter::class)->review($media);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($review['owners'])->toHaveCount(24)
        ->and($queryCount)->toBeLessThanOrEqual(12);
});

it('emits only currently authorized and available file routes', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $available = mediaIssueFixture('file-route-available');
    $missing = mediaIssueFixture('file-route-missing', [], false);
    $maliciousSvg = (string) file_get_contents(base_path('tests/Fixtures/media/malicious.svg'));
    $unsafeSvg = mediaIssueFixture('file-route-unsafe', [
        'path' => 'content-groups/covers/file-route-unsafe.svg',
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
        'size' => strlen($maliciousSvg),
    ], $maliciousSvg);
    $presenter = app(MediaIssueReviewPresenter::class);
    $availableDetails = $presenter->details($available);
    $missingDetails = $presenter->details($missing);
    $unsafeDetails = $presenter->details($unsafeSvg);

    expect($availableDetails['view_url'])->toBe(route('admin.media-files.view', [
        'media' => $available,
    ]))->and($availableDetails['download_url'])->toBe(route('admin.media-files.download', [
        'media' => $available,
    ]))->and($missingDetails['view_url'])->toBeNull()
        ->and($missingDetails['download_url'])->toBeNull()
        ->and($unsafeDetails['view_url'])->toBeNull()
        ->and($unsafeDetails['download_url'])->toBe(route('admin.media-files.download', [
            'media' => $unsafeSvg,
        ]));
});

it('keeps the originating context authenticated through next close and exact return', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $at = Carbon::parse('2026-07-25 12:00:00', config('app.timezone'));
    $next = mediaIssueFixture('queue-target-next', [
        'created_at' => $at,
    ], false);
    $current = mediaIssueFixture('queue-target-current', [
        'created_at' => $at,
    ], false);
    mediaIssueFixture('queue-other-title', [
        'created_at' => $at->copy()->subMinute(),
    ], false);
    $state = mediaIssueContext($current, [
        'task' => MediaLibraryTask::NeedsAttention->value,
        'reason' => MediaDiagnosticReason::MissingFile->value,
        'search' => 'queue target',
        'sort' => 'created_at:desc',
        'page' => 7,
    ]);
    $context = app(MediaLibraryContext::class);
    $token = $context->continuationToken($state);

    expect($context->fromContinuationToken($token, 999))->toBe($state)
        ->and($context->fromContinuationToken("forged-{$token}", 999))->toBe([
            'v' => 1,
            'task' => MediaLibraryTask::All->value,
            'mime' => null,
            'reason' => null,
            'search' => null,
            'sort' => null,
            'page' => 1,
            'focus' => 999,
        ])
        ->and(app(MediaLibraryTaskQuery::class)->nextIssue($current, $state)?->is($next))
        ->toBeTrue();

    $component = Livewire::withQueryParams(['origin' => $token])
        ->test(ReviewMediaIssues::class, ['record' => $current->getRouteKey()])
        ->assertOk();
    $instance = $component->instance();
    $nextUrl = $instance->nextIssueUrl();
    $closeUrl = $instance->detailsUrl();
    $returnUrl = $instance->mediaLibraryReturnUrl();
    parse_str((string) parse_url((string) $nextUrl, PHP_URL_QUERY), $nextQuery);
    parse_str((string) parse_url($closeUrl, PHP_URL_QUERY), $closeQuery);

    expect(parse_url((string) $nextUrl, PHP_URL_PATH))->toBe(parse_url(
        MediaResource::getUrl('review-issues', ['record' => $next]),
        PHP_URL_PATH,
    ))->and($context->fromContinuationToken($nextQuery['origin'] ?? null, 999))->toBe($state)
        ->and(parse_url($closeUrl, PHP_URL_PATH))->toBe(parse_url(
            MediaResource::getUrl('edit', ['record' => $current]),
            PHP_URL_PATH,
        ))
        ->and($context->fromContinuationToken($closeQuery['origin'] ?? null, 999))->toBe($state)
        ->and($returnUrl)->toBe(MediaResource::getUrl('index', [
            'tab' => MediaLibraryTask::NeedsAttention->value,
            'filters' => [
                'type' => ['value' => 'image/jpeg'],
                'reason' => ['value' => MediaDiagnosticReason::MissingFile->value],
            ],
            'search' => 'queue target',
            'sort' => 'created_at:desc',
            'page' => 7,
            'focus' => $current->getKey(),
        ])."#media-record-{$current->getKey()}");

    Livewire::withQueryParams(['origin' => $nextQuery['origin']])
        ->test(EditMedia::class, ['record' => $next->getRouteKey()])
        ->assertOk()
        ->tap(function ($component) use ($returnUrl): void {
            expect($component->instance()->mediaLibraryReturnUrl())->toBe($returnUrl);
        });
});

it('keeps next issue in parity with split and quoted Media Library search terms', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $at = Carbon::parse('2026-07-25 12:00:00', config('app.timezone'));
    $current = mediaIssueFixture('alpha-beta-current', [
        'created_at' => $at,
    ], false);
    $splitMatch = mediaIssueFixture('alpha-middle-beta', [
        'created_at' => $at->copy()->subMinute(),
    ], false);
    $quotedMatch = mediaIssueFixture('alpha-beta-later', [
        'created_at' => $at->copy()->subMinutes(2),
    ], false);
    $query = app(MediaLibraryTaskQuery::class);
    $context = mediaIssueContext($current, [
        'mime' => null,
        'reason' => MediaDiagnosticReason::MissingFile->value,
        'search' => 'alpha beta',
        'sort' => 'created_at:desc',
    ]);

    Livewire::test(ListMedia::class)
        ->set('tableSearch', 'alpha beta')
        ->assertCanSeeTableRecords([$current, $splitMatch, $quotedMatch])
        ->set('tableSearch', '"alpha beta"')
        ->assertCanSeeTableRecords([$current, $quotedMatch])
        ->assertCanNotSeeTableRecords([$splitMatch]);

    expect($query->nextIssue($current, $context)?->is($splitMatch))->toBeTrue();

    $context['search'] = '"alpha beta"';

    expect($query->nextIssue($current, $context)?->is($quotedMatch))->toBeTrue();
});

it('does not wrap the issue queue and handles null timestamp ordering deterministically', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $nonNull = mediaIssueFixture('null-non-null', [
        'created_at' => Carbon::parse('2026-07-25 12:00:00', config('app.timezone')),
    ], false);
    $nullFirst = mediaIssueFixture('null-first', [], false);
    $nullLast = mediaIssueFixture('null-last', [], false);
    DB::table($nullFirst->getTable())
        ->where($nullFirst->getKeyName(), $nullFirst->getKey())
        ->update(['created_at' => null]);
    DB::table($nullLast->getTable())
        ->where($nullLast->getKeyName(), $nullLast->getKey())
        ->update(['created_at' => null]);
    $nullFirst->refresh();
    $nullLast->refresh();
    $descendingContext = mediaIssueContext($nullLast, [
        'mime' => null,
        'reason' => MediaDiagnosticReason::MissingFile->value,
        'search' => 'null',
        'sort' => 'created_at:desc',
    ]);
    $ascendingContext = [
        ...$descendingContext,
        'sort' => 'created_at:asc',
    ];
    $query = app(MediaLibraryTaskQuery::class);
    expect($query->nextIssue($nonNull, $descendingContext)?->is($nullLast))->toBeTrue()
        ->and($query->nextIssue($nullLast, $descendingContext)?->is($nullFirst))->toBeTrue()
        ->and($query->nextIssue($nullFirst, $descendingContext))->toBeNull()
        ->and($query->nextIssue($nullFirst, $ascendingContext)?->is($nullLast))->toBeTrue()
        ->and($query->nextIssue($nullLast, $ascendingContext)?->is($nonNull))->toBeTrue()
        ->and($query->nextIssue($nonNull, $ascendingContext))->toBeNull();
});

it('renders an honest blocker with no Fix Recheck Retry or Package 5 controls and performs no mutation', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    // A metadata-only defect (extension disagreeing with the stored type) is a
    // reason that still has no repair action, so the honest blocker renders.
    // The missing-file reason gained real resolutions in RECON2 R5, so it no
    // longer represents the no-authority state this test pins.
    $media = mediaIssueFixture('mutation-free-review', ['ext' => 'png']);
    MediaAttachment::factory()->create(['media_id' => $media]);
    $beforeMedia = $media->fresh()->getAttributes();
    $beforeAttachments = MediaAttachment::query()->where('media_id', $media->getKey())->get()->toArray();
    $beforeOperations = MediaMutationOperation::query()->count();
    $origin = app(MediaLibraryContext::class)->continuationToken(mediaIssueContext($media));

    Livewire::withQueryParams(['origin' => $origin])
        ->test(ReviewMediaIssues::class, ['record' => $media->getRouteKey()])
        ->assertOk()
        ->assertSee(__('admin.media_issue_review.blocker.heading'))
        ->assertSee(__('admin.media_issue_review.blocker.body'))
        ->assertDontSee('data-testid="media-issue-fix"', false)
        ->assertDontSee('data-testid="media-issue-recheck"', false)
        ->assertDontSee('data-testid="media-issue-retry"', false)
        ->assertDontSee('data-testid="media-files-discovery"', false)
        ->assertDontSee('data-testid="media-trash"', false);

    expect($media->fresh()->getAttributes())->toBe($beforeMedia)
        ->and(MediaAttachment::query()->where('media_id', $media->getKey())->get()->toArray())
        ->toBe($beforeAttachments)
        ->and(MediaMutationOperation::query()->count())->toBe($beforeOperations);
});
