<?php

use App\Enums\PublicationStatus;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentGroups\Pages\CreateContentGroup;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Pages\CreateContentItem;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Testable::macro('fillForm', function (array|Closure $state = [], ?string $form = null): Testable {
        if ($state instanceof Closure) {
            $state = $state([]);
        }

        $schemaStatePath = 'data';

        if (method_exists($this->instance(), 'getDefaultTestingSchemaName')) {
            $form ??= $this->instance()->getDefaultTestingSchemaName();
            $schemaStatePath = $this->instance()->{$form}->getStatePath();
        }

        foreach ($state as $key => $value) {
            $this->set(filled($schemaStatePath) ? "{$schemaStatePath}.{$key}" : $key, $value);
        }

        return $this;
    });

    $this->actingAs(User::factory()->create());
});

it('denies guest access to admin resource routes', function (): void {
    auth()->logout();

    $this->get(AuthorResource::getUrl('index'))
        ->assertRedirect('/admin/login');

    $this->get(ContentGroupResource::getUrl('index'))
        ->assertRedirect('/admin/login');

    $this->get(ContentItemResource::getUrl('index'))
        ->assertRedirect('/admin/login');
});

it('renders author resource pages', function (): void {
    $author = Author::factory()->create();

    Livewire::test(ListAuthors::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$author]);

    Livewire::test(CreateAuthor::class)
        ->assertOk();

    Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet([
            'reference_key' => $author->reference_key,
            'name' => $author->name,
            'slug' => $author->slug,
        ]);
});

it('creates and edits authors with validation', function (): void {
    Livewire::test(CreateAuthor::class)
        ->fillForm([
            'name' => 'Leah Goldberg',
            'slug' => 'leah-goldberg',
            'bio_markdown' => '**Hebrew-first** biography',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $author = Author::query()->where('slug', 'leah-goldberg')->firstOrFail();

    expect($author->name)->toBe('Leah Goldberg')
        ->and($author->bio_markdown)->toBe('**Hebrew-first** biography');

    Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Author',
            'slug' => 'updated-author',
            'bio_markdown' => 'Updated bio',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $author->refresh();

    expect($author->name)->toBe('Updated Author')
        ->and($author->slug)->toBe('updated-author');

    Livewire::test(CreateAuthor::class)
        ->fillForm([
            'name' => null,
            'slug' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'slug' => 'required',
        ]);

    Livewire::test(CreateAuthor::class)
        ->fillForm([
            'name' => 'Duplicate Author',
            'slug' => 'updated-author',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'slug' => 'unique',
        ]);
});

it('searches author tables', function (): void {
    $visible = Author::factory()->create(['name' => 'Searchable Author']);
    $hidden = Author::factory()->create(['name' => 'Different Name']);

    Livewire::test(ListAuthors::class)
        ->searchTable('Searchable')
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);
});

it('renders content group resource pages', function (): void {
    $group = ContentGroup::factory()->create();

    Livewire::test(ListContentGroups::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$group]);

    Livewire::test(CreateContentGroup::class)
        ->assertOk();

    Livewire::test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet([
            'reference_key' => $group->reference_key,
            'title' => $group->title,
            'slug' => $group->slug,
        ]);
});

it('creates and edits content groups with defaults, cover upload, and publication enum', function (): void {
    Storage::fake('public');

    Livewire::test(CreateContentGroup::class)
        ->fillForm([
            'title' => 'Hebrew Podcast',
            'slug' => 'hebrew-podcast',
            'description_markdown' => 'תיאור **בעברית**',
            'cover_path' => UploadedFile::fake()->image('cover.jpg'),
            'original_language_code' => 'he',
            'status' => PublicationStatus::Published,
            'published_at' => now()->subMinute(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $group = ContentGroup::query()->where('slug', 'hebrew-podcast')->firstOrFail();

    expect($group->group_type_label_singular)->toBe(__('public.labels.podcast'))
        ->and($group->group_type_label_plural)->toBe(__('public.labels.podcasts'))
        ->and($group->default_item_type_label_singular)->toBe(__('public.labels.item'))
        ->and($group->default_item_type_label_plural)->toBe(__('public.labels.items'))
        ->and($group->status)->toBe(PublicationStatus::Published)
        ->and($group->cover_path)->toStartWith('content-groups/covers/');

    Storage::disk('public')->assertExists($group->cover_path);

    Livewire::test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->fillForm([
            'title' => 'Changed Group',
            'slug' => 'changed-group',
            'group_type_label_singular' => 'Course',
            'group_type_label_plural' => 'Courses',
            'default_item_type_label_singular' => 'Lesson',
            'default_item_type_label_plural' => 'Lessons',
            'description_markdown' => 'Updated',
            'original_language_code' => 'en',
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $group->refresh();

    expect($group->title)->toBe('Changed Group')
        ->and($group->group_type_label_singular)->toBe('Course')
        ->and($group->default_item_type_label_singular)->toBe('Lesson')
        ->and($group->status)->toBe(PublicationStatus::Draft);
});

it('validates content group required and unique fields', function (): void {
    ContentGroup::factory()->create(['slug' => 'existing-group']);

    Livewire::test(CreateContentGroup::class)
        ->fillForm([
            'title' => null,
            'slug' => null,
            'group_type_label_singular' => null,
            'group_type_label_plural' => null,
            'default_item_type_label_singular' => null,
            'default_item_type_label_plural' => null,
            'original_language_code' => null,
            'status' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'title' => 'required',
            'slug' => 'required',
            'group_type_label_singular' => 'required',
            'group_type_label_plural' => 'required',
            'default_item_type_label_singular' => 'required',
            'default_item_type_label_plural' => 'required',
            'original_language_code' => 'required',
            'status' => 'required',
        ]);

    Livewire::test(CreateContentGroup::class)
        ->fillForm([
            'title' => 'Duplicate Group',
            'slug' => 'existing-group',
            'original_language_code' => 'he',
            'status' => PublicationStatus::Draft,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'slug' => 'unique',
        ]);
});

it('searches and filters content group tables', function (): void {
    $visible = ContentGroup::factory()->published()->create([
        'title' => 'Visible Group',
        'original_language_code' => 'he',
    ]);
    $hidden = ContentGroup::factory()->create([
        'title' => 'Hidden Group',
        'original_language_code' => 'en',
        'status' => PublicationStatus::Draft,
    ]);

    Livewire::test(ListContentGroups::class)
        ->searchTable('Visible')
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);

    Livewire::test(ListContentGroups::class)
        ->filterTable('status', PublicationStatus::Published->value)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);

    Livewire::test(ListContentGroups::class)
        ->filterTable('original_language_code', 'en')
        ->assertCanSeeTableRecords([$hidden])
        ->assertCanNotSeeTableRecords([$visible]);
});

it('renders content item resource pages', function (): void {
    $item = ContentItem::factory()->create();

    Livewire::test(ListContentItems::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$item]);

    Livewire::test(CreateContentItem::class)
        ->assertOk();

    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet([
            'reference_key' => $item->reference_key,
            'title' => $item->title,
            'slug' => $item->slug,
        ]);
});

it('creates and edits content items with labels embed validation and publication enum', function (): void {
    $group = ContentGroup::factory()->create([
        'default_item_type_label_singular' => 'Episode',
    ]);

    Livewire::test(CreateContentItem::class)
        ->fillForm([
            'content_group_id' => $group->id,
            'title' => 'Hebrew Transcript',
            'slug' => 'hebrew-transcript',
            'type_label_singular_override' => 'Talk',
            'description_markdown' => 'Item description',
            'media_url' => 'https://example.com/audio.mp3',
            'embed_url' => 'https://www.youtube.com/embed/demo',
            'duration_seconds' => 123,
            'status' => PublicationStatus::Published,
            'published_at' => now()->subMinute(),
            'original_published_at' => now()->subDay(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = ContentItem::query()->where('slug', 'hebrew-transcript')->firstOrFail();

    expect($item->content_group_id)->toBe($group->id)
        ->and($item->effectiveTypeLabelSingular())->toBe('Talk')
        ->and($item->embed_url)->toBe('https://www.youtube.com/embed/demo')
        ->and($item->status)->toBe(PublicationStatus::Published);

    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->fillForm([
            'content_group_id' => $group->id,
            'title' => 'Updated Transcript',
            'slug' => 'updated-transcript',
            'type_label_singular_override' => null,
            'description_markdown' => 'Updated description',
            'media_url' => 'https://example.com/updated.mp3',
            'embed_url' => 'https://player.vimeo.com/video/123',
            'duration_seconds' => 456,
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'original_published_at' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $item->refresh()->load('contentGroup');

    expect($item->title)->toBe('Updated Transcript')
        ->and($item->effectiveTypeLabelSingular())->toBe('Episode')
        ->and($item->status)->toBe(PublicationStatus::Draft);
});

it('validates content item required fields, scoped slugs, and embed URLs', function (): void {
    $group = ContentGroup::factory()->create();
    $otherGroup = ContentGroup::factory()->create();

    ContentItem::factory()->for($group)->create(['slug' => 'existing-item']);

    Livewire::test(CreateContentItem::class)
        ->fillForm([
            'content_group_id' => null,
            'title' => null,
            'slug' => null,
            'media_url' => null,
            'status' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'content_group_id' => 'required',
            'title' => 'required',
            'slug' => 'required',
            'media_url' => 'required',
            'status' => 'required',
        ]);

    Livewire::test(CreateContentItem::class)
        ->fillForm([
            'content_group_id' => $group->id,
            'title' => 'Duplicate Item',
            'slug' => 'existing-item',
            'media_url' => 'https://example.com/audio.mp3',
            'status' => PublicationStatus::Draft,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'slug' => 'unique',
        ]);

    Livewire::test(CreateContentItem::class)
        ->fillForm([
            'content_group_id' => $otherGroup->id,
            'title' => 'Same Slug Other Group',
            'slug' => 'existing-item',
            'media_url' => 'https://example.com/audio.mp3',
            'status' => PublicationStatus::Draft,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateContentItem::class)
        ->fillForm([
            'content_group_id' => $group->id,
            'title' => 'Invalid Media',
            'slug' => 'invalid-media',
            'media_url' => 'http://example.com/audio.mp3',
            'status' => PublicationStatus::Draft,
        ])
        ->call('create')
        ->assertHasFormErrors(['media_url']);

    foreach ([
        'http://www.youtube.com/embed/demo',
        'https://unapproved.example/embed/demo',
        '<iframe src="https://www.youtube.com/embed/demo"></iframe>',
    ] as $embedUrl) {
        Livewire::test(CreateContentItem::class)
            ->fillForm([
                'content_group_id' => $group->id,
                'title' => 'Invalid Embed',
                'slug' => 'invalid-'.md5($embedUrl),
                'media_url' => 'https://example.com/audio.mp3',
                'embed_url' => $embedUrl,
                'status' => PublicationStatus::Draft,
            ])
            ->call('create')
            ->assertHasFormErrors(['embed_url']);
    }
});

it('searches and filters content item tables', function (): void {
    $firstGroup = ContentGroup::factory()->create(['title' => 'First Group']);
    $secondGroup = ContentGroup::factory()->create(['title' => 'Second Group']);
    $author = Author::factory()->create(['name' => 'Visible Author']);
    $otherAuthor = Author::factory()->create(['name' => 'Other Author']);

    $visible = ContentItem::factory()
        ->for($firstGroup)
        ->published()
        ->create(['title' => 'Visible Item']);
    $hidden = ContentItem::factory()
        ->for($secondGroup)
        ->create([
            'title' => 'Hidden Item',
            'status' => PublicationStatus::Draft,
        ]);
    Transcription::factory()->for($visible)->forAuthor($author)->create();
    Transcription::factory()->for($hidden)->forAuthor($otherAuthor)->create();

    Livewire::test(ListContentItems::class)
        ->searchTable('Visible')
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);

    Livewire::test(ListContentItems::class)
        ->filterTable('content_group_id', $firstGroup->id)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);

    Livewire::test(ListContentItems::class)
        ->filterTable('status', PublicationStatus::Published->value)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);

    Livewire::test(ListContentItems::class)
        ->filterTable('transcriber_id', $author->id)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);
});
