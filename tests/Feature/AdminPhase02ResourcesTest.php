<?php

use App\Enums\HomepageSectionType;
use App\Enums\PublicationStatus;
use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Pages\CreateContentItem;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\ContentItems\RelationManagers\TranscriptionsRelationManager;
use App\Filament\Resources\ContentTags\ContentTagResource;
use App\Filament\Resources\ContentTags\Pages\CreateContentTag;
use App\Filament\Resources\ContentTags\Pages\EditContentTag;
use App\Filament\Resources\ContentTags\Pages\ListContentTags;
use App\Filament\Resources\HomepageSections\HomepageSectionResource;
use App\Filament\Resources\HomepageSections\Pages\CreateHomepageSection;
use App\Filament\Resources\HomepageSections\Pages\EditHomepageSection;
use App\Filament\Resources\HomepageSections\Pages\ListHomepageSections;
use App\Filament\Resources\Transcriptions\Pages\CreateTranscription;
use App\Filament\Resources\Transcriptions\Pages\EditTranscription;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Filament\Resources\Transcriptions\TranscriptionResource;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

it('renders prompt nine resource pages and protects tag routes from guests', function (): void {
    $category = Category::factory()->create();
    $tag = ContentTag::findOrCreateFromString('Admin Tag', 'content')->enable();
    $section = HomepageSection::factory()->create(['category_id' => $category->id]);
    $transcription = Transcription::factory()->forAuthor()->create();

    Livewire::test(ListCategories::class)->assertOk()->assertCanSeeTableRecords([$category]);
    Livewire::test(CreateCategory::class)->assertOk();
    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])->assertOk();

    Livewire::test(ListContentTags::class)->assertOk()->assertCanSeeTableRecords([$tag]);
    Livewire::test(CreateContentTag::class)->assertOk();
    Livewire::test(EditContentTag::class, ['record' => $tag->getRouteKey()])->assertOk();

    Livewire::test(ListHomepageSections::class)->assertOk()->assertCanSeeTableRecords([$section]);
    Livewire::test(CreateHomepageSection::class)->assertOk();
    Livewire::test(EditHomepageSection::class, ['record' => $section->getRouteKey()])->assertOk();

    Livewire::test(ListTranscriptions::class)->assertOk()->assertCanSeeTableRecords([$transcription]);
    Livewire::test(CreateTranscription::class)->assertOk();
    Livewire::test(EditTranscription::class, ['record' => $transcription->getRouteKey()])->assertOk();

    Livewire::test(PublicContentSettingsPage::class)->assertOk();

    auth()->logout();

    $this->get(ContentTagResource::getUrl('index'))
        ->assertRedirect('/admin/login');
});

it('auto-generates slugs and manages categories, tags, and homepage sections', function (): void {
    Livewire::test(CreateCategory::class)
        ->set('data.name', 'Phase Category')
        ->assertSet('data.slug', 'phase-category')
        ->fillForm([
            'name' => 'Phase Category',
            'slug' => 'phase-category',
            'is_visible' => true,
            'sort_order' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(CategoryResource::getUrl('index'));

    $category = Category::query()->where('slug', 'phase-category')->firstOrFail();

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Category',
            'slug' => 'updated-category',
            'is_visible' => false,
            'sort_order' => 20,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(CategoryResource::getUrl('index'));

    $category->refresh();

    expect($category->name)->toBe('Updated Category')
        ->and($category->is_visible)->toBeFalse();

    Livewire::test(CreateContentTag::class)
        ->fillForm([
            'name' => 'Editorial Tag',
            'type' => 'content',
            'is_enabled' => true,
            'enabled_at' => now()->subMinute(),
            'order_column' => 3,
            'moderation_state' => 'approved',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(ContentTagResource::getUrl('index'));

    $tag = ContentTag::findFromString('Editorial Tag', 'content');

    expect($tag)->not->toBeNull()
        ->and($tag->is_enabled)->toBeTrue();

    Livewire::test(CreateHomepageSection::class)
        ->set('data.name', 'Latest Editorial')
        ->assertSet('data.slug', 'latest-editorial')
        ->fillForm([
            'name' => 'Latest Editorial',
            'slug' => 'latest-editorial',
            'type' => HomepageSectionType::Category->value,
            'category_id' => $category->id,
            'limit' => 8,
            'sort_order' => 1,
            'is_visible' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(HomepageSectionResource::getUrl('index'));

    $section = HomepageSection::query()->where('slug', 'latest-editorial')->firstOrFail();

    expect($section->category_id)->toBe($category->id)
        ->and($section->tag_id)->toBeNull();
});

it('drives homepage section target fields and validation from section type', function (): void {
    $category = Category::factory()->create();
    $tag = ContentTag::findOrCreateFromString('Homepage Typed Tag', 'content')->enable();
    $group = ContentGroup::factory()->create();

    Livewire::test(CreateHomepageSection::class)
        ->set('data.type', HomepageSectionType::Latest->value)
        ->assertSchemaComponentHidden('category_id', 'form')
        ->assertSchemaComponentHidden('tag_id', 'form')
        ->assertSchemaComponentHidden('content_group_id', 'form')
        ->fillForm([
            'name' => 'Latest Section',
            'slug' => 'latest-section',
            'type' => HomepageSectionType::Latest->value,
            'limit' => 6,
            'sort_order' => 1,
            'is_visible' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateHomepageSection::class)
        ->set('data.type', HomepageSectionType::Category->value)
        ->assertSchemaComponentVisible('category_id', 'form')
        ->assertSchemaComponentHidden('tag_id', 'form')
        ->assertSchemaComponentHidden('content_group_id', 'form')
        ->fillForm([
            'name' => 'Missing Category Section',
            'slug' => 'missing-category-section',
            'type' => HomepageSectionType::Category->value,
            'limit' => 6,
            'sort_order' => 2,
            'is_visible' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['category_id' => 'required']);

    Livewire::test(CreateHomepageSection::class)
        ->set('data.type', HomepageSectionType::Tag->value)
        ->assertSchemaComponentHidden('category_id', 'form')
        ->assertSchemaComponentVisible('tag_id', 'form')
        ->assertSchemaComponentHidden('content_group_id', 'form')
        ->fillForm([
            'name' => 'Tag Section',
            'slug' => 'tag-section',
            'type' => HomepageSectionType::Tag->value,
            'tag_id' => $tag->id,
            'limit' => 6,
            'sort_order' => 3,
            'is_visible' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateHomepageSection::class)
        ->set('data.type', HomepageSectionType::ContentGroup->value)
        ->assertSchemaComponentHidden('category_id', 'form')
        ->assertSchemaComponentHidden('tag_id', 'form')
        ->assertSchemaComponentVisible('content_group_id', 'form')
        ->fillForm([
            'name' => 'Group Section',
            'slug' => 'group-section',
            'type' => HomepageSectionType::ContentGroup->value,
            'content_group_id' => $group->id,
            'limit' => 6,
            'sort_order' => 4,
            'is_visible' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HomepageSection::query()->where('slug', 'latest-section')->firstOrFail()->category_id)->toBeNull()
        ->and(HomepageSection::query()->where('slug', 'tag-section')->firstOrFail()->tag_id)->toBe($tag->id)
        ->and(HomepageSection::query()->where('slug', 'group-section')->firstOrFail()->content_group_id)->toBe($group->id);
});

it('assigns prompt eight taxonomy, tags, pinning, media metadata, and featured transcription on content items', function (): void {
    $group = ContentGroup::factory()->create();
    $author = Author::factory()->create();
    $category = Category::factory()->create();
    $tag = ContentTag::findOrCreateFromString('Assigned Tag', 'content')->enable();

    $component = Livewire::test(CreateContentItem::class)
        ->fillForm([
            'content_group_id' => $group->id,
            'title' => 'Prompt Nine Item',
            'slug' => 'prompt-nine-item',
            'description_markdown' => 'Description',
            'media_url' => 'https://example.com/audio.mp3',
            'embed_url' => 'https://www.youtube.com/embed/demo',
            'duration_seconds' => 360,
            'authors' => [$author->id],
            'categories' => [$category->id],
            'tags' => ['Assigned Tag'],
            'is_pinned' => true,
            'pinned_at' => now()->subHour(),
            'pinned_until' => now()->addHour(),
            'pin_order' => 2,
            'embed_provider' => 'youtube',
            'external_id' => 'yt-123',
            'external_title' => 'External title',
            'external_description' => 'External description',
            'external_thumbnail_url' => 'https://example.com/thumb.jpg',
            'direct_media_url' => 'https://example.com/direct.mp3',
            'media_duration_seconds' => 360,
            'media_metadata' => ['source' => 'manual'],
            'status' => PublicationStatus::Draft,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified(Notification::make()
            ->success()
            ->title(__('admin.notifications.content_item_created'))
            ->body(__('admin.notifications.content_item_created_add_transcription')));

    $item = ContentItem::query()->where('slug', 'prompt-nine-item')->firstOrFail();

    $component->assertRedirect(ContentItemResource::getUrl('edit', ['record' => $item]));

    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published()
        ->create(['title' => 'Primary transcript']);

    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->fillForm([
            'content_group_id' => $group->id,
            'title' => 'Prompt Nine Item',
            'slug' => 'prompt-nine-item',
            'media_url' => 'https://example.com/audio.mp3',
            'authors' => [$author->id],
            'categories' => [$category->id],
            'tags' => ['Assigned Tag'],
            'featured_transcription_id' => $transcription->id,
            'is_pinned' => true,
            'pin_order' => 1,
            'status' => PublicationStatus::Draft,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $item->refresh()->load('categories', 'tags');

    expect($item->categories)->toHaveCount(1)
        ->and($item->tags->first()->is($tag))->toBeTrue()
        ->and($item->is_pinned)->toBeTrue()
        ->and($item->pin_order)->toBe(1)
        ->and($item->embed_provider)->toBe('youtube')
        ->and($item->media_metadata)->toBe(['source' => 'manual'])
        ->and($item->featured_transcription_id)->toBe($transcription->id)
        ->and($item->transcript_markdown)->toBeNull();
});

it('renders content item edit details and transcription tabs with real form fields', function (): void {
    $item = ContentItem::factory()->create([
        'title' => 'Details Tab Item',
        'slug' => 'details-tab-item',
        'media_url' => 'https://example.com/details-tab.mp3',
    ]);

    $component = Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertOk()
        ->assertSee(__('admin.tabs.item_details'))
        ->assertSee(__('admin.tabs.transcriptions'))
        ->assertSee(__('admin.fields.title'))
        ->assertSee(__('admin.fields.slug'))
        ->assertSee(__('admin.fields.content_group'))
        ->assertSee(__('admin.fields.status'))
        ->assertSee(__('admin.fields.media_url'))
        ->assertSchemaComponentVisible('title', 'form')
        ->assertSchemaComponentVisible('slug', 'form')
        ->assertSchemaComponentVisible('content_group_id', 'form')
        ->assertSchemaComponentVisible('status', 'form')
        ->assertSchemaComponentVisible('media_url', 'form');

    expect((new ReflectionMethod(EditContentItem::class, 'getContentTabLabel'))->getDeclaringClass()->getName())
        ->toBe(EditContentItem::class)
        ->and((new ReflectionMethod(EditContentItem::class, 'getContentTabComponent'))->getDeclaringClass()->getName())
        ->not->toBe(EditContentItem::class);

    expect($component->instance()->form->getComponents())->not->toBeEmpty();
});

it('saves public content settings through the settings page', function (): void {
    Livewire::test(PublicContentSettingsPage::class)
        ->fillForm([
            'homepage_item_limit' => 9,
            'pinned_item_limit' => 4,
            'default_public_sort' => 'title_desc',
            'default_result_layout' => 'rows',
            'show_latest_section' => false,
            'item_page_layout' => 'media_first',
            'homepage_card_image_size' => 'large',
            'homepage_card_density' => 'compact',
            'homepage_card_title_size' => 'lg',
            'homepage_show_group_badge' => false,
            'homepage_show_authors' => false,
            'homepage_show_categories' => false,
            'homepage_show_tags' => false,
            'homepage_show_duration' => false,
            'homepage_show_effective_date' => false,
            'homepage_show_description' => false,
            'homepage_description_lines' => 1,
            'homepage_cards_per_page' => 7,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(PublicContentSettings::class);

    $settings = app(PublicContentSettings::class);

    expect($settings->homepage_item_limit)->toBe(9)
        ->and($settings->pinned_item_limit)->toBe(4)
        ->and($settings->default_public_sort)->toBe('title_desc')
        ->and($settings->default_result_layout)->toBe('rows')
        ->and($settings->show_latest_section)->toBeFalse()
        ->and($settings->item_page_layout)->toBe('media_first')
        ->and($settings->homepage_card_image_size)->toBe('large')
        ->and($settings->homepage_card_density)->toBe('compact')
        ->and($settings->homepage_card_title_size)->toBe('lg')
        ->and($settings->homepage_show_group_badge)->toBeFalse()
        ->and($settings->homepage_show_authors)->toBeFalse()
        ->and($settings->homepage_show_categories)->toBeFalse()
        ->and($settings->homepage_show_tags)->toBeFalse()
        ->and($settings->homepage_show_duration)->toBeFalse()
        ->and($settings->homepage_show_effective_date)->toBeFalse()
        ->and($settings->homepage_show_description)->toBeFalse()
        ->and($settings->homepage_description_lines)->toBe(1)
        ->and($settings->homepage_cards_per_page)->toBe(7);
});

it('manages item transcriptions through the content item relation manager', function (): void {
    $item = ContentItem::factory()->create();
    $otherItem = ContentItem::factory()->create();
    $author = Author::factory()->create();
    $ownerTranscription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published()
        ->create(['title' => 'Owner transcript']);
    $otherTranscription = Transcription::factory()
        ->for($otherItem)
        ->forAuthor($author)
        ->published()
        ->create(['title' => 'Other transcript']);

    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertSee(__('admin.tabs.item_details'))
        ->assertSee(__('admin.tabs.transcriptions'));

    Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $item,
        'pageClass' => EditContentItem::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$ownerTranscription])
        ->assertCanNotSeeTableRecords([$otherTranscription])
        ->mountAction(TestAction::make('create')->table())
        ->set('mountedActions.0.data.author_id', $author->id)
        ->set('mountedActions.0.data.title', 'Relation transcript')
        ->set('mountedActions.0.data.language_code', 'he')
        ->set('mountedActions.0.data.transcript_markdown', 'Relation body')
        ->set('mountedActions.0.data.status', PublicationStatus::Draft->value)
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $created = Transcription::query()->where('title', 'Relation transcript')->firstOrFail();

    expect($created->content_item_id)->toBe($item->id);

    Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $item,
        'pageClass' => EditContentItem::class,
    ])
        ->mountAction(TestAction::make('edit')->table($created))
        ->set('mountedActions.0.data.author_id', $author->id)
        ->set('mountedActions.0.data.title', 'Edited relation transcript')
        ->set('mountedActions.0.data.language_code', 'en')
        ->set('mountedActions.0.data.transcript_markdown', 'Edited body')
        ->set('mountedActions.0.data.status', PublicationStatus::Published->value)
        ->set('mountedActions.0.data.published_at', now()->subMinute())
        ->callMountedAction()
        ->assertHasNoFormErrors();

    Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $item,
        'pageClass' => EditContentItem::class,
    ])
        ->callAction(TestAction::make('setFeatured')->table($ownerTranscription))
        ->assertHasNoFormErrors();

    $item->refresh();

    expect($created->refresh()->title)->toBe('Edited relation transcript')
        ->and($item->featured_transcription_id)->toBe($ownerTranscription->id);
});

it('auto-features the first relation-manager transcription and only offers manual featured changes when useful', function (): void {
    $item = ContentItem::factory()->create();
    $author = Author::factory()->create();

    Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $item,
        'pageClass' => EditContentItem::class,
    ])
        ->assertActionVisible(TestAction::make('create')->table())
        ->mountAction(TestAction::make('create')->table())
        ->set('mountedActions.0.data.author_id', $author->id)
        ->set('mountedActions.0.data.title', 'First item transcript')
        ->set('mountedActions.0.data.language_code', 'he')
        ->set('mountedActions.0.data.transcript_markdown', 'First body')
        ->set('mountedActions.0.data.status', PublicationStatus::Published->value)
        ->set('mountedActions.0.data.published_at', now()->subMinutes(5))
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $first = Transcription::query()->where('title', 'First item transcript')->firstOrFail();

    expect($first->content_item_id)->toBe($item->id)
        ->and($item->refresh()->featured_transcription_id)->toBe($first->id);

    Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $item->refresh(),
        'pageClass' => EditContentItem::class,
    ])
        ->assertActionHidden(TestAction::make('setFeatured')->table($first));

    $second = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published()
        ->create(['title' => 'Second item transcript']);

    Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $item->refresh(),
        'pageClass' => EditContentItem::class,
    ])
        ->assertActionVisible(TestAction::make('setFeatured')->table($second))
        ->callAction(TestAction::make('setFeatured')->table($second))
        ->assertHasNoFormErrors();

    expect($item->refresh()->featured_transcription_id)->toBe($second->id);
});

it('keeps draft featured transcriptions from becoming publicly effective', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()->for($group)->published()->create();
    $draft = Transcription::factory()->for($item)->forAuthor()->create([
        'title' => 'Draft featured transcript',
        'status' => PublicationStatus::Draft,
    ]);
    $published = Transcription::factory()->for($item)->forAuthor()->published()->create([
        'title' => 'Published fallback transcript',
    ]);

    $item->update(['featured_transcription_id' => $draft->id]);

    expect($item->refresh()->effectiveTranscription()?->is($published))->toBeTrue()
        ->and(ContentItem::query()->published()->whereKey($item)->exists())->toBeTrue();
});

it('creates transcriptions from the content item table row action and defers moving existing records', function (): void {
    $item = ContentItem::factory()->create();
    $author = Author::factory()->create();

    Livewire::test(ListContentItems::class)
        ->assertActionVisible(TestAction::make('addTranscription')->table($item))
        ->assertActionDoesNotExist(TestAction::make('associateTranscription')->table($item))
        ->mountAction(TestAction::make('addTranscription')->table($item))
        ->set('mountedActions.0.data.author_id', $author->id)
        ->set('mountedActions.0.data.title', 'Table action transcript')
        ->set('mountedActions.0.data.language_code', 'he')
        ->set('mountedActions.0.data.status', PublicationStatus::Draft->value)
        ->set('mountedActions.0.data.transcript_markdown', 'Created from table action')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $transcription = Transcription::query()->where('title', 'Table action transcript')->firstOrFail();

    expect($transcription->content_item_id)->toBe($item->id)
        ->and($item->refresh()->featured_transcription_id)->toBe($transcription->id);
});

it('creates standalone transcriptions and validates same item featured selection', function (): void {
    $item = ContentItem::factory()->create();
    $otherItem = ContentItem::factory()->create();
    $author = Author::factory()->create();
    $otherTranscription = Transcription::factory()->for($otherItem)->published()->create();

    Livewire::test(CreateTranscription::class)
        ->fillForm([
            'content_item_id' => $item->id,
            'author_id' => $author->id,
            'title' => 'Standalone transcript',
            'language_code' => 'he',
            'transcript_markdown' => 'Standalone body',
            'status' => PublicationStatus::Published,
            'published_at' => now()->subMinute(),
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(TranscriptionResource::getUrl('index'));

    $this->expectException(ValidationException::class);

    $item->update(['featured_transcription_id' => $otherTranscription->id]);
});
