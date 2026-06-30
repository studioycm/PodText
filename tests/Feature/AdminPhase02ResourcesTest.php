<?php

use App\Enums\HomepageSectionType;
use App\Enums\PublicationStatus;
use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ContentItems\Pages\CreateContentItem;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
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
            'type' => HomepageSectionType::Category,
            'category_id' => $category->id,
            'tag_id' => $tag->id,
            'limit' => 8,
            'sort_order' => 1,
            'is_visible' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(HomepageSectionResource::getUrl('index'));

    $section = HomepageSection::query()->where('slug', 'latest-editorial')->firstOrFail();

    expect($section->category_id)->toBe($category->id)
        ->and($section->tag_id)->toBe($tag->id);
});

it('assigns prompt eight taxonomy, tags, pinning, media metadata, and featured transcription on content items', function (): void {
    $group = ContentGroup::factory()->create();
    $author = Author::factory()->create();
    $category = Category::factory()->create();
    $tag = ContentTag::findOrCreateFromString('Assigned Tag', 'content')->enable();

    Livewire::test(CreateContentItem::class)
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
        ->assertHasNoFormErrors();

    $item = ContentItem::query()->where('slug', 'prompt-nine-item')->firstOrFail();
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

it('saves public content settings through the settings page', function (): void {
    Livewire::test(PublicContentSettingsPage::class)
        ->fillForm([
            'homepage_item_limit' => 9,
            'pinned_item_limit' => 4,
            'default_public_sort' => 'pinned',
            'default_result_layout' => 'rows',
            'show_latest_section' => false,
            'item_page_layout' => 'media_first',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(PublicContentSettings::class);

    $settings = app(PublicContentSettings::class);

    expect($settings->homepage_item_limit)->toBe(9)
        ->and($settings->pinned_item_limit)->toBe(4)
        ->and($settings->default_public_sort)->toBe('pinned')
        ->and($settings->default_result_layout)->toBe('rows')
        ->and($settings->show_latest_section)->toBeFalse()
        ->and($settings->item_page_layout)->toBe('media_first');
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
