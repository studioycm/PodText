<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\UserRole;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\ImageUploadValidator;
use App\Support\Media\MediaAttachmentManager;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\SvgUploadSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

function curatorG1ScopedMedia(array $attributes = []): Media
{
    $name = (string) Str::ulid();

    return Media::factory()->create([
        'name' => $name,
        'path' => "content-groups/covers/{$name}.jpg",
        ...$attributes,
    ]);
}

it('applies the same fixed disk visibility root type extension name size and dimension scope in SQL and PHP', function (): void {
    $scope = app(MediaRecordScope::class);
    $allowed = curatorG1ScopedMedia();
    $records = [
        curatorG1ScopedMedia(['disk' => 'local']),
        curatorG1ScopedMedia(['visibility' => 'private']),
        curatorG1ScopedMedia(['directory' => 'content-items/images']),
        curatorG1ScopedMedia(['type' => 'image/gif', 'ext' => 'gif', 'path' => 'content-groups/covers/forged.gif', 'name' => 'forged']),
        curatorG1ScopedMedia(['type' => 'image/png', 'ext' => 'jpg']),
        curatorG1ScopedMedia(['name' => 'different-name']),
        curatorG1ScopedMedia(['path' => 'content-groups/covers/nested/image.jpg', 'name' => 'image']),
        curatorG1ScopedMedia(['path' => 'content-groups/covers/../image.jpg', 'name' => 'image']),
        curatorG1ScopedMedia(['path' => 'content-groups/covers\\image.jpg', 'name' => 'image']),
        curatorG1ScopedMedia(['path' => 'content-groups/covers/%2e%2e.jpg', 'name' => '%2e%2e']),
        curatorG1ScopedMedia(['directory' => 'content-groups/covers-sibling', 'path' => 'content-groups/covers-sibling/image.jpg', 'name' => 'image']),
        curatorG1ScopedMedia(['size' => 0]),
        curatorG1ScopedMedia(['size' => (4096 * 1024) + 1]),
        curatorG1ScopedMedia(['width' => 0]),
        curatorG1ScopedMedia(['height' => 6001]),
    ];

    expect($scope->allows($allowed, ImageUploadPurpose::ContentGroupCover))->toBeTrue()
        ->and($scope->query(ImageUploadPurpose::ContentGroupCover)->pluck('id')->all())->toBe([$allowed->getKey()]);

    foreach ($records as $record) {
        expect($scope->allows($record, ImageUploadPurpose::ContentGroupCover), (string) $record->getKey())->toBeFalse()
            ->and($scope->find($record->getKey(), ImageUploadPurpose::ContentGroupCover))->toBeNull();
    }
});

it('allows dimensionless sanitized SVG only for the header purpose and resolves trusted keys and paths', function (): void {
    $scope = app(MediaRecordScope::class);
    $svg = Media::factory()->create([
        'directory' => ImageUploadPurpose::HeaderLogo->root(),
        'name' => 'logo',
        'path' => ImageUploadPurpose::HeaderLogo->root().'/logo.svg',
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
        'size' => 120,
    ]);

    expect($scope->allows($svg, ImageUploadPurpose::HeaderLogo))->toBeTrue()
        ->and($scope->allows($svg, ImageUploadPurpose::ContentGroupCover))->toBeFalse()
        ->and($scope->findByReferenceKey(strtolower((string) $svg->reference_key), ImageUploadPurpose::HeaderLogo)?->is($svg))->toBeTrue()
        ->and($scope->findByPath($svg->path, ImageUploadPurpose::HeaderLogo)?->is($svg))->toBeTrue()
        ->and($scope->findByPath('../logo.svg', ImageUploadPurpose::HeaderLogo))->toBeNull();

    $lowercase = curatorG1ScopedMedia();
    $lowercaseKey = mb_strtolower((string) $lowercase->reference_key);
    DB::table('curator')->where('id', $lowercase->getKey())->update(['reference_key' => $lowercaseKey]);
    $lowercase->refresh();

    expect($scope->findByReferenceKey(mb_strtoupper($lowercaseKey))?->is($lowercase))->toBeTrue()
        ->and($scope->allows($lowercase))->toBeTrue();
});

it('enforces every app media ability for admin or higher and denies record mutations when referenced', function (): void {
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $moderator = User::factory()->role(UserRole::Moderator)->create();
    $media = curatorG1ScopedMedia();
    $recordAbilities = ['view', 'update', 'delete', 'download', 'rename', 'swap', 'select', 'attach', 'detach'];
    $classAbilities = ['viewAny', 'create', 'bulkUpload', 'deleteAny'];

    foreach ([$admin, $superAdmin] as $actor) {
        foreach ($classAbilities as $ability) {
            expect(Gate::forUser($actor)->allows($ability, Media::class), "{$actor->role->value}:{$ability}")->toBeTrue();
        }

        foreach ($recordAbilities as $ability) {
            expect(Gate::forUser($actor)->allows($ability, $media), "{$actor->role->value}:{$ability}")->toBeTrue();
        }
    }

    foreach ($classAbilities as $ability) {
        expect(Gate::forUser($moderator)->allows($ability, Media::class), $ability)->toBeFalse();
    }

    foreach ($recordAbilities as $ability) {
        expect(Gate::forUser($moderator)->allows($ability, $media), $ability)->toBeFalse();
    }

    $group = ContentGroup::factory()->create();
    Storage::disk('public')->put($media->path, 'referenced media fixture');
    app(MediaAttachmentManager::class)->attach($group, $media, MediaAttachmentRole::Cover, $admin);

    expect(Gate::forUser($admin)->allows('view', $media))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('download', $media))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('select', $media))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', $media))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('rename', $media))->toBeFalse()
        // The RECON2 R5 swap lift: byte replacement keeps media identity, so
        // attachment-referenced rows are swappable (settings refs still block).
        ->and(Gate::forUser($admin)->allows('swap', $media))->toBeTrue();
});

it('streams configured inventory files for admins with inline or attachment disposition and hardened headers', function (): void {
    $encoded = file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'));
    expect($encoded)->toBeString();
    $validated = app(ImageUploadValidator::class)->validateBytes(
        base64_decode(trim($encoded), true) ?: throw new RuntimeException('Invalid JPEG fixture.'),
        'trusted.jpg',
        ImageUploadPurpose::ContentGroupCover,
    );
    $media = curatorG1ScopedMedia([
        'width' => $validated->width,
        'height' => $validated->height,
        'size' => $validated->size,
    ]);
    Storage::disk('public')->put($media->path, $validated->contents, 'public');
    $moderator = User::factory()->role(UserRole::Moderator)->create();
    $admin = User::factory()->admin()->create();

    $this->get(route('admin.media-files.view', ['media' => $media->getKey()]))
        ->assertRedirect('/admin/login');
    $this->actingAs($moderator)
        ->get(route('admin.media-files.view', ['media' => $media->getKey()]))
        ->assertForbidden();

    $disallowed = curatorG1ScopedMedia(['visibility' => 'private']);
    Storage::disk('public')->put($disallowed->path, 'private bytes');
    $this->actingAs($admin)
        ->get(route('admin.media-files.view', ['media' => $disallowed->getKey()]))
        ->assertSuccessful();

    $view = $this->actingAs($admin)->get(route('admin.media-files.view', ['media' => $media->getKey()]));
    $view->assertSuccessful()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    expect($view->headers->get('Content-Disposition'))->toContain('inline', basename($media->path));

    $download = $this->actingAs($admin)->get(route('admin.media-files.download', ['media' => $media->getKey()]));
    $download->assertSuccessful();
    expect($download->headers->get('Content-Disposition'))->toContain('attachment', basename($media->path));

    Storage::disk('public')->delete($media->path);
    $this->actingAs($admin)
        ->get(route('admin.media-files.download', ['media' => $media->getKey()]))
        ->assertNotFound();
});

it('serves allowed header SVG through the same hardened controller without raster curation', function (): void {
    $admin = User::factory()->admin()->create();
    $svg = Media::factory()->create([
        'directory' => ImageUploadPurpose::HeaderLogo->root(),
        'name' => 'safe-logo',
        'path' => ImageUploadPurpose::HeaderLogo->root().'/safe-logo.svg',
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
        'size' => 76,
    ]);
    Storage::disk('public')->put($svg->path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"></svg>');

    $this->actingAs($admin)
        ->get(route('admin.media-files.view', ['media' => $svg->getKey()]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $unsafe = Media::factory()->create([
        'directory' => ImageUploadPurpose::HeaderLogo->root(),
        'name' => 'unsafe-logo',
        'path' => ImageUploadPurpose::HeaderLogo->root().'/unsafe-logo.svg',
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
        'size' => 80,
    ]);
    Storage::disk('public')->put($unsafe->path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    $this->actingAs($admin)
        ->get(route('admin.media-files.view', ['media' => $unsafe->getKey()]))
        ->assertNotFound();
    $download = $this->actingAs($admin)
        ->get(route('admin.media-files.download', ['media' => $unsafe->getKey()]));
    $download->assertSuccessful();
    expect($download->headers->get('Content-Disposition'))->toContain('attachment');
});

it('refuses inline SVG when legacy MIME metadata needs normalization or sanitization would change the bytes', function (): void {
    $admin = User::factory()->admin()->create();
    $unsafe = Media::factory()->create([
        'directory' => 'legacy',
        'name' => 'parameterized-svg',
        'path' => 'legacy/parameterized-svg.bin',
        'type' => 'Image/SVG+XML; charset=UTF-8',
        'ext' => 'bin',
        'width' => null,
        'height' => null,
        'size' => 160,
    ]);
    Storage::disk('public')->put(
        $unsafe->path,
        '<svg xmlns="http://www.w3.org/2000/svg"><a><set attributeName="href" to="javascript:alert(1)"/><text>x</text></a></svg>',
    );

    $this->actingAs($admin)
        ->get(route('admin.media-files.view', ['media' => $unsafe->getKey()]))
        ->assertNotFound();
    $download = $this->actingAs($admin)
        ->get(route('admin.media-files.download', ['media' => $unsafe->getKey()]));

    $download->assertSuccessful();
    expect($download->headers->get('Content-Disposition'))->toContain('attachment');
});

it('renders already sanitized legacy SVG above the new-upload size limit', function (): void {
    $admin = User::factory()->admin()->create();
    $contents = app(SvgUploadSanitizer::class)->sanitize(
        '<svg xmlns="http://www.w3.org/2000/svg"><text>'.
        str_repeat('a', (CuratorImageUploadPolicy::MAX_KILOBYTES * 1024) + 1).
        '</text></svg>',
    );
    expect(strlen($contents))->toBeGreaterThan(CuratorImageUploadPolicy::MAX_KILOBYTES * 1024);
    $media = Media::factory()->create([
        'directory' => ImageUploadPurpose::HeaderLogo->root(),
        'name' => 'large-safe-logo',
        'path' => ImageUploadPurpose::HeaderLogo->root().'/large-safe-logo.svg',
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
        'size' => strlen($contents),
    ]);
    Storage::disk('public')->put($media->path, $contents);

    $this->actingAs($admin)
        ->get(route('admin.media-files.view', ['media' => $media->getKey()]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/svg+xml');
});
