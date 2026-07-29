<?php

use App\Enums\MediaAttachmentRole;
use App\Enums\MediaNamingStrategy;
use App\Filament\Exports\ContentGroupExporter;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Filament\Forms\MediaPickerField;
use App\Filament\Pages\AdminUxSettings as AdminUxSettingsPage;
use App\Filament\Pages\DisplaySettings;
use App\Filament\Pages\MenuHeaderSettings;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\Media\ImageFileNamer;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeSettingsBackupSnapshotQueue();
});

function clearImgASettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(AdminUxSettings::class);
    app(SettingsContainer::class)->clearCache();
}

function saveImgAPublicSetting(string $name, array $payload): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => $name,
        ],
        [
            'locked' => false,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    clearImgASettingsCache();
}

function imgAMedia(string $path, string $type = 'image/jpeg', string $extension = 'jpg'): Media
{
    return Media::factory()->create([
        'disk' => 'public',
        'directory' => trim(dirname($path), '.'),
        'visibility' => 'public',
        'name' => pathinfo($path, PATHINFO_FILENAME),
        'path' => $path,
        'width' => 100,
        'height' => 100,
        'size' => 1024,
        'type' => $type,
        'ext' => $extension,
    ]);
}

function imgAHydratedPickerId(object $page, string $statePath): int
{
    $field = $page->form->getComponentByStatePath($statePath);

    expect($field)->toBeInstanceOf(PathCuratorPicker::class);

    $state = $field->getState();

    expect($state)->toBeInt();

    return $state;
}

it('always renders the app owned media picker despite the legacy driver setting', function (): void {
    config(['media.picker.driver' => 'curator']);

    expect(MediaPickerField::make('cover_path', ImageFileNamer::CONTENT_GROUP_COVER))
        ->toBeInstanceOf(PathCuratorPicker::class);

    config(['media.picker.driver' => 'file_upload']);

    expect(MediaPickerField::make('cover_path', ImageFileNamer::CONTENT_GROUP_COVER))
        ->toBeInstanceOf(PathCuratorPicker::class);
});

it('resolves a non empty curator glide token fallback', function (): void {
    expect(config('curator.glide_token'))->not->toBeEmpty();
});

it('persists curator picker selections as attachments with a legacy cover path', function (): void {
    config(['media.picker.driver' => 'curator']);
    $this->actingAs(User::factory()->admin()->create());
    Storage::fake('public');
    UploadedFile::fake()->image('library.jpg')->storeAs('content-groups/covers', 'library.jpg', 'public');
    $media = imgAMedia('content-groups/covers/library.jpg');
    $group = ContentGroup::factory()->create();

    Livewire::test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->set('data.cover_media_reference_key', $media->reference_key)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($group->coverMediaAttachment()->with('media')->first()?->media?->path)->toBe('content-groups/covers/library.jpg')
        ->and($group->coverMediaAttachment?->media_id)->toBe($media->getKey());
});

it('round trips public settings image paths through the curator picker without changing bytes', function (): void {
    config(['media.picker.driver' => 'curator']);
    Storage::fake('public');
    Storage::disk('public')->put('header/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>');
    $media = imgAMedia('header/logo.svg', 'image/svg+xml', 'svg');
    $this->actingAs(User::factory()->create());

    Livewire::test(MenuHeaderSettings::class)
        ->set('data.menu_config.logo.light_media_reference_key', $media->reference_key)
        ->call('save')
        ->assertHasNoFormErrors();

    clearImgASettingsCache();

    expect(app(PublicContentSettings::class)->menu_config['logo']['light_path'])->toBe('header/logo.svg')
        ->and(app(PublicContentSettings::class)->menu_config['logo']['light_media_reference_key'])->toBe($media->reference_key);
});

it('keeps a registered menu logo selected after saving and remounting the settings page', function (): void {
    config(['media.picker.driver' => 'curator']);
    Storage::fake('public');
    Storage::disk('public')->put('header/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>');
    $media = imgAMedia('header/logo.svg', 'image/svg+xml', 'svg');
    saveImgAPublicSetting('menu_config', [
        'logo' => [
            'light_path' => 'header/logo.svg',
            'dark_path' => null,
        ],
    ]);
    $this->actingAs(User::factory()->create());

    $page = Livewire::test(MenuHeaderSettings::class)
        ->assertSee((string) $media->title);
    expect(imgAHydratedPickerId($page->instance(), 'menu_config.logo.light_media_reference_key'))
        ->toBe($media->getKey());

    $page
        ->call('save')
        ->assertHasNoFormErrors();

    clearImgASettingsCache();

    expect(app(PublicContentSettings::class)->menu_config['logo']['light_path'])->toBe('header/logo.svg');

    $remounted = Livewire::test(MenuHeaderSettings::class)
        ->assertSee((string) $media->title);
    expect(imgAHydratedPickerId($remounted->instance(), 'menu_config.logo.light_media_reference_key'))
        ->toBe($media->getKey());
});

it('keeps a registered custom default image selected after remounting display settings', function (): void {
    config(['media.picker.driver' => 'curator']);
    Storage::fake('public');
    Storage::disk('public')->put('default-images/fallback.jpg', 'image');
    $media = imgAMedia('default-images/fallback.jpg');
    saveImgAPublicSetting('default_images', array_replace_recursive(
        PublicFrontConfigRegistry::defaults()['default_images'],
        [
            'content_item' => [
                'mode' => 'custom',
                'path' => 'default-images/fallback.jpg',
            ],
        ],
    ));
    $this->actingAs(User::factory()->create());

    $page = Livewire::test(DisplaySettings::class)
        ->assertSee((string) $media->title);
    expect(imgAHydratedPickerId($page->instance(), 'default_images.content_item.media_reference_key'))
        ->toBe($media->getKey());
});

it('saves the admin ux media naming strategy setting', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(AdminUxSettingsPage::class)
        ->set('data.media_naming_strategy', MediaNamingStrategy::SlugKey->value)
        ->call('save')
        ->assertHasNoFormErrors();

    clearImgASettingsCache();

    expect(app(AdminUxSettings::class)->media_naming_strategy)->toBe(MediaNamingStrategy::SlugKey->value);
});

it('blocks deleting curator media that is still referenced by app surfaces', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('content-groups/covers/referenced.jpg', 'image');
    $media = imgAMedia('content-groups/covers/referenced.jpg');
    $group = ContentGroup::factory()->create([
        'title' => 'Referenced Podcast',
    ]);
    MediaAttachment::query()->create([
        'media_id' => $media->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);
    $actor = User::factory()->admin()->create();
    $coordinator = app(MediaFilesystemMutationCoordinator::class);

    expect(fn () => $media->delete())->toThrow(LogicException::class)
        ->and(fn () => $coordinator->delete($media, $actor))->toThrow(AuthorizationException::class);
    expect(Media::query()->whereKey($media->getKey())->exists())->toBeTrue();

    $group->coverMediaAttachment()->delete();

    $coordinator->delete($media->refresh(), $actor);

    expect(Media::query()->whereKey($media->getKey())->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('content-groups/covers/referenced.jpg');
});

it('renders content group cover alt text on public images and badge thumbnails', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('content-groups/covers/alt.jpg', 'cover fixture');
    $media = imgAMedia('content-groups/covers/alt.jpg');
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Alt Podcast',
        'slug' => 'alt-podcast',
        'cover_alt_text' => 'Editorial cover alt',
    ]);
    MediaAttachment::query()->create([
        'media_id' => $media->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);
    ContentItem::factory()
        ->for($group)
        ->published()
        ->withTranscription()
        ->create(['title' => 'Alt Episode']);

    Filament::setCurrentPanel(Filament::getPanel('public'));

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('alt="Editorial cover alt"', false);

    $badge = Blade::render(
        '<x-public.content-group-badge :group="$group" mode="thumbnail_name" :cover-url="$coverUrl" :cover-alt="$coverAlt" />',
        [
            'group' => $group,
            'coverUrl' => Storage::disk('public')->url(
                (string) $group->coverMediaAttachment()->with('media')->first()?->media?->path,
            ),
            'coverAlt' => $group->cover_alt_text,
        ],
    );

    expect($badge)->toContain('alt="Editorial cover alt"');
});

it('exports portable cover identity without a mutable path column', function (): void {
    $columns = collect(ContentGroupExporter::getColumns());
    $column = $columns
        ->first(fn ($column): bool => $column->getName() === 'cover_media_reference_key');

    expect($column)->not->toBeNull()
        ->and($column->isEnabledByDefault())->toBeTrue()
        ->and($columns->contains(fn ($candidate): bool => $candidate->getName() === 'cover_path'))->toBeFalse();
});
