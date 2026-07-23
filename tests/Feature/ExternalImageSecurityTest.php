<?php

use App\Enums\ExternalImageFailureReason;
use App\Enums\ImageUploadPurpose;
use App\Enums\UserRole;
use App\Jobs\DownloadExternalContentGroupImage;
use App\Jobs\DownloadExternalContentItemImage;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaProviderBinding;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\ExternalImageDnsResolver;
use App\Support\Media\ExternalImageRejectedException;
use App\Support\Media\ExternalImageUnavailableException;
use App\Support\Media\MediaAttachmentManager;
use App\Support\Media\PinnedExternalImageTransport;
use App\Support\Media\SafeExternalImageFetcher;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

function curatorG1ExternalResponse(string $body, int $status = 200, array $headers = []): Response
{
    return new Response(new Psr7Response($status, $headers, $body));
}

function curatorG1ExternalPng(): string
{
    $encoded = file_get_contents(base_path('tests/Fixtures/media/valid.png.base64'));

    if (! is_string($encoded)) {
        throw new RuntimeException('Unable to load the external-image PNG fixture.');
    }

    return base64_decode(trim($encoded), true)
        ?: throw new RuntimeException('The external-image PNG fixture is invalid.');
}

function curatorG1SafeFetcher(
    array $addressesBefore,
    Response $response,
    ?array $addressesAfter = null,
): SafeExternalImageFetcher {
    $dns = Mockery::mock(ExternalImageDnsResolver::class);
    $dns->shouldReceive('addresses')
        ->between(1, 2)
        ->andReturn($addressesBefore, $addressesAfter ?? $addressesBefore);
    $transport = Mockery::mock(PinnedExternalImageTransport::class);
    $transport->shouldReceive('get')
        ->once()
        ->with(
            'https://cdn.example.test/image.png',
            'cdn.example.test',
            $addressesBefore[0],
            Mockery::type('float'),
        )
        ->andReturn($response);

    return new SafeExternalImageFetcher($dns, $transport, app(CuratorImageUploadPolicy::class));
}

it('fetches only a stable public pinned endpoint without encoded or oversized bytes', function (): void {
    $contents = curatorG1ExternalPng();
    $fetcher = curatorG1SafeFetcher(
        ['93.184.216.34'],
        curatorG1ExternalResponse($contents, headers: [
            'Content-Encoding' => 'identity',
            'Content-Length' => (string) strlen($contents),
        ]),
    );

    expect($fetcher->fetch('https://cdn.example.test/image.png'))->toBe($contents);

    foreach (['1.1.1.1', '2606:4700:4700::1111', '2001:4860:4860::8888'] as $address) {
        expect(curatorG1SafeFetcher(
            [$address],
            curatorG1ExternalResponse($contents),
        )->fetch('https://cdn.example.test/image.png'))->toBe($contents);
    }
});

it('rejects noncanonical schemes credentials fragments ports whitespace and backslashes before transport', function (string $url): void {
    $dns = Mockery::mock(ExternalImageDnsResolver::class);
    $dns->shouldNotReceive('addresses');
    $transport = Mockery::mock(PinnedExternalImageTransport::class);
    $transport->shouldNotReceive('get');

    expect(fn () => (new SafeExternalImageFetcher($dns, $transport, app(CuratorImageUploadPolicy::class)))->fetch($url))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'http' => ['http://cdn.example.test/image.png'],
    'credentials' => ['https://user:pass@cdn.example.test/image.png'],
    'fragment' => ['https://cdn.example.test/image.png#fragment'],
    'custom port' => ['https://cdn.example.test:444/image.png'],
    'whitespace' => ["https://cdn.example.test/image.png\n"],
    'backslash' => ['https://cdn.example.test\\image.png'],
]);

it('rejects private special-use mixed and rebinding DNS answers', function (): void {
    foreach ([
        ['0.0.0.0'],
        ['127.0.0.1'],
        ['10.0.0.1'],
        ['100.64.0.1'],
        ['100.127.255.254'],
        ['169.254.169.254'],
        ['172.16.0.1'],
        ['192.0.0.9'],
        ['192.0.2.1'],
        ['192.31.196.1'],
        ['192.52.193.1'],
        ['192.88.99.1'],
        ['192.175.48.1'],
        ['198.18.0.1'],
        ['198.19.255.254'],
        ['198.51.100.1'],
        ['203.0.113.1'],
        ['224.0.0.1'],
        ['240.0.0.1'],
        ['255.255.255.255'],
        ['::'],
        ['::1'],
        ['::ffff:127.0.0.1'],
        ['64:ff9b::c000:201'],
        ['64:ff9b:1::1'],
        ['100::1'],
        ['100:0:0:1::1'],
        ['2001::1'],
        ['2001:2::1'],
        ['2001:3::1'],
        ['2001:db8::1'],
        ['2002:5db8:d822::1'],
        ['2620:4f:8000::1'],
        ['3fff::1'],
        ['5f00::1'],
        ['fc00::1'],
        ['fe80::1'],
        ['ff02::1'],
        ['4000::1'],
        ['999.999.999.999'],
        ['2001:db8::g'],
        ['93.184.216.34', '192.168.1.10'],
        ['93.184.216.34', '100.64.0.1'],
    ] as $addresses) {
        $dns = Mockery::mock(ExternalImageDnsResolver::class);
        $dns->shouldReceive('addresses')->once()->andReturn($addresses);
        $transport = Mockery::mock(PinnedExternalImageTransport::class);
        $transport->shouldNotReceive('get');

        expect(fn () => (new SafeExternalImageFetcher($dns, $transport, app(CuratorImageUploadPolicy::class)))->fetch('https://cdn.example.test/image.png'))
            ->toThrow(InvalidArgumentException::class);
    }

    $rebound = curatorG1SafeFetcher(
        ['93.184.216.34'],
        curatorG1ExternalResponse(curatorG1ExternalPng()),
        ['93.184.216.35'],
    );
    expect(fn () => $rebound->fetch('https://cdn.example.test/image.png'))
        ->toThrow(InvalidArgumentException::class, 'changed DNS addresses');
});

it('revalidates each redirect endpoint and enforces the redirect limit', function (): void {
    $dns = Mockery::mock(ExternalImageDnsResolver::class);
    $dns->shouldReceive('addresses')->with('cdn.example.test')->twice()->andReturn(['93.184.216.34']);
    $dns->shouldReceive('addresses')->with('images.example.test')->twice()->andReturn(['93.184.216.35']);
    $transport = Mockery::mock(PinnedExternalImageTransport::class);
    $transport->shouldReceive('get')
        ->once()
        ->with(
            'https://cdn.example.test/image.png',
            'cdn.example.test',
            '93.184.216.34',
            Mockery::type('float'),
        )
        ->andReturn(curatorG1ExternalResponse('', 302, ['Location' => 'https://images.example.test/final.png']));
    $transport->shouldReceive('get')
        ->once()
        ->with(
            'https://images.example.test/final.png',
            'images.example.test',
            '93.184.216.35',
            Mockery::type('float'),
        )
        ->andReturn(curatorG1ExternalResponse(curatorG1ExternalPng()));

    expect((new SafeExternalImageFetcher($dns, $transport, app(CuratorImageUploadPolicy::class)))->fetch('https://cdn.example.test/image.png'))
        ->toBe(curatorG1ExternalPng());

    $unsafeDns = Mockery::mock(ExternalImageDnsResolver::class);
    $unsafeDns->shouldReceive('addresses')->with('cdn.example.test')->twice()->andReturn(['93.184.216.34']);
    $unsafeTransport = Mockery::mock(PinnedExternalImageTransport::class);
    $unsafeTransport->shouldReceive('get')->once()->andReturn(
        curatorG1ExternalResponse('', 302, ['Location' => 'http://127.0.0.1/private.png']),
    );
    expect(fn () => (new SafeExternalImageFetcher($unsafeDns, $unsafeTransport, app(CuratorImageUploadPolicy::class)))->fetch('https://cdn.example.test/image.png'))
        ->toThrow(InvalidArgumentException::class);
});

it('shares one decreasing transport deadline across redirect hops', function (): void {
    config()->set('media.acquisition.external_image_timeout_seconds', 2.0);
    $dns = Mockery::mock(ExternalImageDnsResolver::class);
    $dns->shouldReceive('addresses')->with('one.example.test')->twice()->andReturn(['93.184.216.31']);
    $dns->shouldReceive('addresses')->with('two.example.test')->twice()->andReturn(['93.184.216.32']);
    $dns->shouldReceive('addresses')->with('three.example.test')->twice()->andReturn(['93.184.216.33']);
    $allowances = [];
    $transport = Mockery::mock(PinnedExternalImageTransport::class);
    $transport->shouldReceive('get')
        ->times(3)
        ->withArgs(function (string $url, string $host, string $address, float $remaining) use (&$allowances): bool {
            $allowances[] = $remaining;
            usleep(1_000);

            return true;
        })
        ->andReturn(
            curatorG1ExternalResponse('', 302, ['Location' => 'https://two.example.test/image.png']),
            curatorG1ExternalResponse('', 302, ['Location' => 'https://three.example.test/image.png']),
            curatorG1ExternalResponse(curatorG1ExternalPng()),
        );

    $fetcher = new SafeExternalImageFetcher($dns, $transport, app(CuratorImageUploadPolicy::class));

    expect($fetcher->fetch('https://one.example.test/image.png'))->toBe(curatorG1ExternalPng())
        ->and($allowances)->toHaveCount(3)
        ->and($allowances[0])->toBeLessThanOrEqual(2.0)
        ->and($allowances[1])->toBeLessThan($allowances[0])
        ->and($allowances[2])->toBeLessThan($allowances[1]);
});

it('stops redirect processing when the shared deadline is exhausted', function (): void {
    config()->set('media.acquisition.external_image_timeout_seconds', 0.02);
    $dns = Mockery::mock(ExternalImageDnsResolver::class);
    $dns->shouldReceive('addresses')->with('cdn.example.test')->once()->andReturn(['93.184.216.34']);
    $dns->shouldNotReceive('addresses')->with('images.example.test');
    $transport = Mockery::mock(PinnedExternalImageTransport::class);
    $transport->shouldReceive('get')
        ->once()
        ->with(
            'https://cdn.example.test/image.png',
            'cdn.example.test',
            '93.184.216.34',
            Mockery::type('float'),
        )
        ->andReturnUsing(function (): Response {
            usleep(30_000);

            return curatorG1ExternalResponse('', 302, ['Location' => 'https://images.example.test/final.png']);
        });

    try {
        (new SafeExternalImageFetcher($dns, $transport, app(CuratorImageUploadPolicy::class)))
            ->fetch('https://cdn.example.test/image.png');
        $this->fail('Expected the shared external-image deadline to be exhausted.');
    } catch (ExternalImageUnavailableException $exception) {
        expect($exception->reason)->toBe(ExternalImageFailureReason::TimedOut);
    }
});

it('stops before transport when synchronous DNS overruns the shared budget', function (): void {
    config()->set('media.acquisition.external_image_timeout_seconds', 0.02);
    $dns = Mockery::mock(ExternalImageDnsResolver::class);
    $dns->shouldReceive('addresses')
        ->once()
        ->with('cdn.example.test')
        ->andReturnUsing(function (): array {
            usleep(30_000);

            return ['93.184.216.34'];
        });
    $transport = Mockery::mock(PinnedExternalImageTransport::class);
    $transport->shouldNotReceive('get');

    try {
        (new SafeExternalImageFetcher($dns, $transport, app(CuratorImageUploadPolicy::class)))
            ->fetch('https://cdn.example.test/image.png');
        $this->fail('Expected the external-image budget to be exhausted after DNS.');
    } catch (ExternalImageUnavailableException $exception) {
        expect($exception->reason)->toBe(ExternalImageFailureReason::TimedOut);
    }
});

it('categorizes a transport failure after the total deadline as timed out', function (): void {
    config()->set('media.acquisition.external_image_timeout_seconds', 0.02);
    $dns = Mockery::mock(ExternalImageDnsResolver::class);
    $dns->shouldReceive('addresses')
        ->once()
        ->with('cdn.example.test')
        ->andReturn(['93.184.216.34']);
    $transport = Mockery::mock(PinnedExternalImageTransport::class);
    $transport->shouldReceive('get')
        ->once()
        ->andReturnUsing(function (): never {
            usleep(30_000);

            throw new RuntimeException('transport timeout with unsafe details');
        });

    try {
        (new SafeExternalImageFetcher($dns, $transport, app(CuratorImageUploadPolicy::class)))
            ->fetch('https://cdn.example.test/image.png');
        $this->fail('Expected the external-image transport deadline to be exhausted.');
    } catch (ExternalImageUnavailableException $exception) {
        expect($exception->reason)->toBe(ExternalImageFailureReason::TimedOut);
    }
});

it('categorizes safe external-image failures without relying on internal messages', function (): void {
    $invalidDns = Mockery::mock(ExternalImageDnsResolver::class);
    $invalidDns->shouldNotReceive('addresses');
    $invalidTransport = Mockery::mock(PinnedExternalImageTransport::class);
    $invalidTransport->shouldNotReceive('get');

    try {
        (new SafeExternalImageFetcher($invalidDns, $invalidTransport, app(CuratorImageUploadPolicy::class)))
            ->fetch('http://127.0.0.1/private.png');
        $this->fail('Expected a blocked external-image URL.');
    } catch (ExternalImageRejectedException $exception) {
        expect($exception->reason)->toBe(ExternalImageFailureReason::Blocked);
    }

    try {
        curatorG1SafeFetcher(
            ['93.184.216.34'],
            curatorG1ExternalResponse('not found', 404),
        )->fetch('https://cdn.example.test/image.png');
        $this->fail('Expected a rejected external-image response.');
    } catch (ExternalImageRejectedException $exception) {
        expect($exception->reason)->toBe(ExternalImageFailureReason::NotFound);
    }

    try {
        curatorG1SafeFetcher(
            ['93.184.216.34'],
            curatorG1ExternalResponse('retry', 503),
        )->fetch('https://cdn.example.test/image.png');
        $this->fail('Expected a temporarily unavailable external image.');
    } catch (ExternalImageUnavailableException $exception) {
        expect($exception->reason)->toBe(ExternalImageFailureReason::TemporarilyUnavailable);
    }
});

it('rejects encoded invalid-length oversized empty and terminal HTTP responses distinctly', function (): void {
    $cases = [
        [curatorG1ExternalResponse('bytes', headers: ['Content-Encoding' => 'gzip']), InvalidArgumentException::class],
        [curatorG1ExternalResponse('bytes', headers: ['Content-Length' => 'invalid']), InvalidArgumentException::class],
        [curatorG1ExternalResponse('bytes', headers: ['Content-Length' => (string) ((2048 * 1024) + 1)]), InvalidArgumentException::class],
        [curatorG1ExternalResponse(''), InvalidArgumentException::class],
        [curatorG1ExternalResponse(str_repeat('x', (2048 * 1024) + 1)), InvalidArgumentException::class],
        [curatorG1ExternalResponse('not found', 404), InvalidArgumentException::class],
        [curatorG1ExternalResponse('retry', 429), RuntimeException::class],
        [curatorG1ExternalResponse('retry', 503), RuntimeException::class],
    ];

    foreach ($cases as [$response, $exception]) {
        expect(fn () => curatorG1SafeFetcher(['93.184.216.34'], $response)->fetch('https://cdn.example.test/image.png'))
            ->toThrow($exception);
    }
});

it('fails closed when an HTTP fake cannot prove the pinned connected address', function (): void {
    Http::fake([
        'https://cdn.example.test/image.png' => Http::response(curatorG1ExternalPng()),
    ]);

    expect(fn () => app(PinnedExternalImageTransport::class)->get(
        'https://cdn.example.test/image.png',
        'cdn.example.test',
        '93.184.216.34',
        20.0,
    ))->toThrow(RuntimeException::class, 'did not prove its connected address');
});

it('downloads validates admits and attaches an external image idempotently', function (): void {
    $user = User::factory()->admin()->create();
    $item = ContentItem::factory()->create([
        'external_thumbnail_url' => 'https://cdn.example.test/image.png',
    ]);
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')->once()->andReturn(curatorG1ExternalPng());
    app()->instance(SafeExternalImageFetcher::class, $fetcher);
    $job = new DownloadExternalContentItemImage(
        contentItemId: (int) $item->getKey(),
        userId: (int) $user->getKey(),
        expectedUrl: (string) $item->external_thumbnail_url,
    );

    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    $item->refresh()->load('primaryImageMediaAttachment.media');
    expect($item->primaryImageMediaAttachment?->media)->toBeInstanceOf(Media::class)
        ->and($item->image_path)->toBe($item->primaryImageMediaAttachment?->media?->path)
        ->and($item->primaryImageMediaAttachment?->media?->directory)->toBe(ImageUploadPurpose::ContentItemPrimaryImage->root())
        ->and(Media::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1)
        ->and($user->notifications()->count())->toBe(1);
    Storage::disk('public')->assertExists((string) $item->image_path);
});

it('downloads a Spotify-produced podcast URL through the same queued admission path', function (): void {
    $user = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $url = 'https://cdn.example.test/show.png';
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')->once()->with($url)->andReturn(curatorG1ExternalPng());
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    app()->call([(new DownloadExternalContentGroupImage(
        contentGroupId: (int) $group->getKey(),
        userId: (int) $user->getKey(),
        url: $url,
    )), 'handle']);

    $group->refresh()->load('coverMediaAttachment.media');

    expect($group->coverMediaAttachment?->media)->toBeInstanceOf(Media::class)
        ->and($group->cover_path)->toBe($group->coverMediaAttachment?->media?->path)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1);
});

it('checks authorization and source freshness before any external request', function (): void {
    $moderator = User::factory()->role(UserRole::Moderator)->create();
    $item = ContentItem::factory()->create([
        'external_thumbnail_url' => 'https://cdn.example.test/image.png',
    ]);
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldNotReceive('fetch');
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    expect(fn () => app()->call([(new DownloadExternalContentItemImage(
        (int) $item->getKey(),
        (int) $moderator->getKey(),
        expectedUrl: (string) $item->external_thumbnail_url,
    )), 'handle']))->toThrow(AuthorizationException::class);

    $admin = User::factory()->admin()->create();
    app()->call([(new DownloadExternalContentItemImage(
        (int) $item->getKey(),
        (int) $admin->getKey(),
        expectedUrl: 'https://cdn.example.test/old.png',
    )), 'handle']);

    expect(Media::query()->count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(1);
});

it('keeps a newly admitted library item when owner identity changes during the fetch', function (): void {
    $user = User::factory()->admin()->create();
    $item = ContentItem::factory()->create([
        'external_thumbnail_url' => 'https://cdn.example.test/image.png',
    ]);
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')->once()->andReturnUsing(function () use ($item): string {
        ContentItem::query()->whereKey($item->getKey())->update([
            'image_path' => 'content-items/images/concurrent-editor.jpg',
        ]);

        return curatorG1ExternalPng();
    });
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    app()->call([(new DownloadExternalContentItemImage(
        (int) $item->getKey(),
        (int) $user->getKey(),
        expectedUrl: (string) $item->external_thumbnail_url,
    )), 'handle']);
    $failureBody = (string) data_get($user->notifications()->sole()->data, 'body');

    expect($item->refresh()->image_path)->toBe('content-items/images/concurrent-editor.jpg')
        ->and(Media::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(1)
        ->and($user->notifications()->count())->toBe(1)
        ->and($failureBody)->toContain(__('admin.notifications.external_image_attachment_failed'))
        ->and($failureBody)->not->toContain(__('admin.media_library.url_failure_invalid_image'));
});

it('rethrows authorization failures from the post-download attachment boundary', function (): void {
    $user = User::factory()->admin()->create();
    $item = ContentItem::factory()->create([
        'external_thumbnail_url' => 'https://cdn.example.test/image.png',
    ]);
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')->once()->andReturn(curatorG1ExternalPng());
    app()->instance(SafeExternalImageFetcher::class, $fetcher);
    $attachments = Mockery::mock(MediaAttachmentManager::class);
    $attachments->shouldReceive('attachIfUnchanged')
        ->once()
        ->andThrow(new AuthorizationException('attachment denied'));
    app()->instance(MediaAttachmentManager::class, $attachments);

    expect(fn () => app()->call([(new DownloadExternalContentItemImage(
        (int) $item->getKey(),
        (int) $user->getKey(),
        expectedUrl: (string) $item->external_thumbnail_url,
    )), 'handle']))->toThrow(AuthorizationException::class, 'attachment denied');

    expect(Media::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1)
        ->and($item->refresh()->primaryImageMediaAttachment()->exists())->toBeFalse()
        ->and($user->notifications()->count())->toBe(0);
});

it('turns validation failures into safe notifications while transient failures remain retryable', function (): void {
    $user = User::factory()->admin()->create();
    $item = ContentItem::factory()->create([
        'external_thumbnail_url' => 'https://cdn.example.test/image.png',
    ]);
    $invalidFetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $unsafeDetails = 'blocked https://token@private.example.test/image.png at 10.0.0.1';
    $invalidFetcher->shouldReceive('fetch')->once()->andThrow(new ExternalImageRejectedException(
        ExternalImageFailureReason::Blocked,
        $unsafeDetails,
    ));
    app()->instance(SafeExternalImageFetcher::class, $invalidFetcher);
    app()->call([(new DownloadExternalContentItemImage(
        (int) $item->getKey(),
        (int) $user->getKey(),
        expectedUrl: (string) $item->external_thumbnail_url,
    )), 'handle']);

    $failureBody = (string) data_get($user->notifications()->sole()->data, 'body');

    expect($user->notifications()->count())->toBe(1)
        ->and(Media::query()->count())->toBe(0)
        ->and($failureBody)->toContain(__('admin.media_library.url_failure_blocked'))
        ->and($failureBody)->not->toContain($unsafeDetails)
        ->and($failureBody)->not->toContain('10.0.0.1');

    $transientFetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $transientFetcher->shouldReceive('fetch')->once()->andThrow(new RuntimeException('transient transport failure'));
    app()->instance(SafeExternalImageFetcher::class, $transientFetcher);

    expect(fn () => app()->call([(new DownloadExternalContentItemImage(
        (int) $item->getKey(),
        (int) $user->getKey(),
        expectedUrl: (string) $item->external_thumbnail_url,
    )), 'handle']))->toThrow(RuntimeException::class, 'transient transport failure');
});
