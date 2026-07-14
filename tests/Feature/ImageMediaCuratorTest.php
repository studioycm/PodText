<?php

use App\Enums\MediaNamingStrategy;
use App\Filament\Exports\ContentGroupExporter;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Filament\Forms\MediaPickerField;
use App\Filament\Pages\AdminUxSettings as AdminUxSettingsPage;
use App\Filament\Pages\MenuHeaderSettings;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\Media\ImageFileNamer;
use Awcodes\Curator\Models\Media;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
    return Media::query()->create([
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

it('renders the shared media field in curator and file upload modes', function (): void {
    config(['media.picker.driver' => 'curator']);

    expect(MediaPickerField::make('cover_path', ImageFileNamer::CONTENT_GROUP_COVER))
        ->toBeInstanceOf(PathCuratorPicker::class);

    config(['media.picker.driver' => 'file_upload']);

    expect(MediaPickerField::make('cover_path', ImageFileNamer::CONTENT_GROUP_COVER))
        ->toBeInstanceOf(FileUpload::class);
});

it('resolves a non empty curator glide token fallback', function (): void {
    expect(config('curator.glide_token'))->not->toBeEmpty();
});

it('persists curator picker selections as plain cover path strings', function (): void {
    config(['media.picker.driver' => 'curator']);
    Storage::fake('public');
    UploadedFile::fake()->image('library.jpg')->storeAs('content-groups/covers', 'library.jpg', 'public');
    imgAMedia('content-groups/covers/library.jpg');
    $group = ContentGroup::factory()->create();

    Livewire::test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->set('data.cover_path', 'content-groups/covers/library.jpg')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($group->refresh()->cover_path)->toBe('content-groups/covers/library.jpg');
});

it('round trips public settings image paths through the curator picker without changing bytes', function (): void {
    config(['media.picker.driver' => 'curator']);
    Storage::fake('public');
    Storage::disk('public')->put('header/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>');
    imgAMedia('header/logo.svg', 'image/svg+xml', 'svg');
    $this->actingAs(User::factory()->create());

    Livewire::test(MenuHeaderSettings::class)
        ->set('data.menu_config.logo.light_path', 'header/logo.svg')
        ->call('save')
        ->assertHasNoFormErrors();

    clearImgASettingsCache();

    expect(app(PublicContentSettings::class)->menu_config['logo']['light_path'])->toBe('header/logo.svg');
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

it('deletes only unused app-owned cover files on replace and record delete', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('content-groups/covers/shared.jpg', 'old');
    Storage::disk('public')->put('content-groups/covers/delete-me.jpg', 'old');
    Storage::disk('public')->put('content-groups/covers/new.jpg', 'new');
    Storage::disk('public')->put('legacy/outside.jpg', 'legacy');

    $sharedA = ContentGroup::factory()->create(['cover_path' => 'content-groups/covers/shared.jpg']);
    ContentGroup::factory()->create(['cover_path' => 'content-groups/covers/shared.jpg']);
    $deleteMe = ContentGroup::factory()->create(['cover_path' => 'content-groups/covers/delete-me.jpg']);
    $legacy = ContentGroup::factory()->create(['cover_path' => 'legacy/outside.jpg']);

    $sharedA->update(['cover_path' => 'content-groups/covers/new.jpg']);
    $deleteMe->delete();
    $legacy->delete();

    Storage::disk('public')->assertExists('content-groups/covers/shared.jpg');
    Storage::disk('public')->assertMissing('content-groups/covers/delete-me.jpg');
    Storage::disk('public')->assertExists('legacy/outside.jpg');
});

it('keeps library registered cover files while deleting only no-row strays', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('content-groups/covers/library.jpg', 'library');
    Storage::disk('public')->put('content-groups/covers/stray.jpg', 'stray');
    imgAMedia('content-groups/covers/library.jpg');

    $library = ContentGroup::factory()->create(['cover_path' => 'content-groups/covers/library.jpg']);
    $stray = ContentGroup::factory()->create(['cover_path' => 'content-groups/covers/stray.jpg']);

    $library->update(['cover_path' => null]);
    $stray->update(['cover_path' => null]);

    Storage::disk('public')->assertExists('content-groups/covers/library.jpg');
    Storage::disk('public')->assertMissing('content-groups/covers/stray.jpg');
    expect(Media::query()->where('path', 'content-groups/covers/library.jpg')->exists())->toBeTrue();
});

it('blocks deleting curator media that is still referenced by app surfaces', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('content-groups/covers/referenced.jpg', 'image');
    $media = imgAMedia('content-groups/covers/referenced.jpg');
    $group = ContentGroup::factory()->create([
        'title' => 'Referenced Podcast',
        'cover_path' => 'content-groups/covers/referenced.jpg',
    ]);

    expect(fn () => $media->delete())->toThrow(ValidationException::class);
    expect(Media::query()->whereKey($media->getKey())->exists())->toBeTrue();

    $group->update(['cover_path' => null]);

    expect($media->refresh()->delete())->toBeTrue();
});

it('preserves legacy paths without curator rows when the admin form is saved untouched', function (): void {
    config(['media.picker.driver' => 'curator']);
    $this->actingAs(User::factory()->create());
    $group = ContentGroup::factory()->create([
        'cover_path' => 'legacy/outside.jpg',
    ]);

    Livewire::test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($group->refresh()->cover_path)->toBe('legacy/outside.jpg');
});

it('cleans only unregistered local episode image strays when item image paths change', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('content-items/images/library.jpg', 'library');
    Storage::disk('public')->put('content-items/images/stray.jpg', 'stray');
    Storage::disk('public')->put('content-items/images/shared.jpg', 'shared');
    imgAMedia('content-items/images/library.jpg');

    $library = ContentItem::factory()->create(['image_path' => 'content-items/images/library.jpg']);
    $stray = ContentItem::factory()->create(['image_path' => 'content-items/images/stray.jpg']);
    $shared = ContentItem::factory()->create(['image_path' => 'content-items/images/shared.jpg']);
    ContentItem::factory()->create(['image_path' => 'content-items/images/shared.jpg']);

    $library->update(['image_path' => null]);
    $stray->update(['image_path' => null]);
    $shared->update(['image_path' => null]);

    Storage::disk('public')->assertExists('content-items/images/library.jpg');
    Storage::disk('public')->assertMissing('content-items/images/stray.jpg');
    Storage::disk('public')->assertExists('content-items/images/shared.jpg');
});

it('registers existing cover and settings asset files as curator media idempotently', function (): void {
    Storage::fake('public');
    UploadedFile::fake()->image('legacy.jpg', 120, 80)->storeAs('content-groups/covers', 'legacy.jpg', 'public');
    UploadedFile::fake()->image('fallback.jpg', 80, 80)->storeAs('default-images', 'fallback.jpg', 'public');
    Storage::disk('public')->put('header/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>');
    ContentGroup::factory()->create(['cover_path' => 'content-groups/covers/legacy.jpg']);
    saveImgAPublicSetting('menu_config', [
        'logo' => [
            'light_path' => 'header/logo.svg',
            'dark_path' => null,
        ],
    ]);
    saveImgAPublicSetting('default_images', [
        'content_group' => [
            'mode' => 'custom',
            'path' => 'default-images/fallback.jpg',
        ],
    ]);

    $this->artisan('media:register-existing-curator-assets')->assertExitCode(0);
    $this->artisan('media:register-existing-curator-assets')->assertExitCode(0);

    expect(Media::query()->where('path', 'content-groups/covers/legacy.jpg')->count())->toBe(1)
        ->and(Media::query()->where('path', 'header/logo.svg')->count())->toBe(1)
        ->and(Media::query()->where('path', 'default-images/fallback.jpg')->count())->toBe(1);
});

it('renders content group cover alt text on public images and badge thumbnails', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Alt Podcast',
        'slug' => 'alt-podcast',
        'cover_path' => 'content-groups/covers/alt.jpg',
        'cover_alt_text' => 'Editorial cover alt',
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
        '<x-public.content-group-badge :group="$group" mode="thumbnail_name" />',
        ['group' => $group],
    );

    expect($badge)->toContain('alt="Editorial cover alt"');
});

it('keeps the cover export column path output disabled by default', function (): void {
    $column = collect(ContentGroupExporter::getColumns())
        ->first(fn ($column): bool => $column->getName() === 'cover_path');

    expect($column)->not->toBeNull()
        ->and($column->isEnabledByDefault())->toBeFalse();
});
