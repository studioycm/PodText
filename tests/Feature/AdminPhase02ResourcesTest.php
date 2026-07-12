<?php

use App\Enums\HomepageSectionType;
use App\Enums\PublicationStatus;
use App\Enums\PublicFormSubmissionStatus;
use App\Filament\Pages\AdminUxSettings as AdminUxSettingsPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ImporterSettings;
use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Filament\Public\Pages\ShowContentGroup;
use App\Filament\Public\Pages\ShowContentItem;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentGroups\Pages\CreateContentGroup;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
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
use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Filament\Resources\SettingsBackups\SettingsBackupResource;
use App\Filament\Resources\Transcriptions\Pages\CreateTranscription;
use App\Filament\Resources\Transcriptions\Pages\EditTranscription;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Filament\Resources\Transcriptions\TranscriptionResource;
use App\Filament\Support\AdminNavigationOrder;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Models\PublicFormSubmission;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use Awcodes\Curator\Resources\Media\MediaResource;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Enums\ContentTabPosition;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\RecordActionsPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

function mountUx2EffectiveTranscriptionAction(ContentItem $item): Testable
{
    return Livewire::test(ListContentItems::class)
        ->mountAction(TestAction::make('editEffectiveTranscription')->table($item));
}

/**
 * @param  array<int, int>  $authorIds
 */
function assertUx2TranscriberOrder(Transcription $transcription, array $authorIds): void
{
    $pivotRows = DB::table('author_transcription')
        ->where('transcription_id', $transcription->id)
        ->orderBy('sort_order')
        ->get(['author_id', 'sort_order']);

    expect($pivotRows->pluck('author_id')->map(fn (int $authorId): int => $authorId)->all())->toBe($authorIds)
        ->and($pivotRows->pluck('sort_order')->map(fn (int $sortOrder): int => $sortOrder)->all())->toBe(array_keys($authorIds));
}

it('orders every registered admin navigation resource and page through the central map', function (): void {
    $expected = [
        ContentGroupResource::class => [
            'sort' => 100,
            'group' => AdminNavigationOrder::CONTENT_MANAGEMENT,
        ],
        ContentItemResource::class => [
            'sort' => 110,
            'group' => AdminNavigationOrder::CONTENT_MANAGEMENT,
        ],
        TranscriptionResource::class => [
            'sort' => 120,
            'group' => AdminNavigationOrder::CONTENT_MANAGEMENT,
        ],
        AuthorResource::class => [
            'sort' => 200,
            'group' => AdminNavigationOrder::TAXONOMY_MANAGEMENT,
        ],
        CategoryResource::class => [
            'sort' => 210,
            'group' => AdminNavigationOrder::TAXONOMY_MANAGEMENT,
        ],
        ContentTagResource::class => [
            'sort' => 220,
            'group' => AdminNavigationOrder::TAXONOMY_MANAGEMENT,
        ],
        HomepageSectionResource::class => [
            'sort' => 300,
            'group' => AdminNavigationOrder::SITE_MANAGEMENT,
        ],
        PublicContentSettingsPage::class => [
            'sort' => 310,
            'group' => AdminNavigationOrder::SITE_MANAGEMENT,
        ],
        AdminUxSettingsPage::class => [
            'sort' => 320,
            'group' => AdminNavigationOrder::SITE_MANAGEMENT,
        ],
        SettingsBackupResource::class => [
            'sort' => 330,
            'group' => AdminNavigationOrder::SITE_MANAGEMENT,
        ],
        ImporterSettings::class => [
            'sort' => 340,
            'group' => AdminNavigationOrder::SITE_MANAGEMENT,
        ],
        PublicFormSubmissionResource::class => [
            'sort' => 10,
            'group' => null,
            'badge_deferred' => true,
        ],
        MediaResource::class => [
            'sort' => 20,
            'group' => null,
        ],
    ];

    expect(AdminNavigationOrder::all())->toBe($expected);

    foreach ($expected as $class => $config) {
        expect($class::getNavigationSort())->toBe($config['sort'])
            ->and($class::getNavigationGroup())->toBe(
                $config['group'] ? AdminNavigationOrder::groupLabel($config['group']) : null,
            );
    }

    expect(Dashboard::shouldRegisterNavigation())->toBeFalse()
        ->and(PublicFormSubmissionResource::isNavigationBadgeDeferred())->toBeTrue();

    $panel = Filament::getPanel('admin');
    $registeredNavigationClasses = [
        ...$panel->getResources(),
        ...$panel->getPages(),
    ];

    $missing = collect($registeredNavigationClasses)
        ->filter(fn (string $class): bool => method_exists($class, 'shouldRegisterNavigation')
            ? $class::shouldRegisterNavigation()
            : true)
        ->reject(fn (string $class): bool => AdminNavigationOrder::has($class))
        ->values()
        ->all();

    expect($missing)->toBeEmpty();

    $navigation = collect($panel->getNavigation());
    $navigationLabels = $navigation
        ->map(fn ($group): ?string => $group->getLabel())
        ->values()
        ->all();

    expect($navigationLabels)->toBe([
        null,
        AdminNavigationOrder::groupLabel(AdminNavigationOrder::CONTENT_MANAGEMENT),
        AdminNavigationOrder::groupLabel(AdminNavigationOrder::TAXONOMY_MANAGEMENT),
        AdminNavigationOrder::groupLabel(AdminNavigationOrder::SITE_MANAGEMENT),
    ]);

    $itemLabelsFor = fn (?string $groupLabel): array => collect($navigation
        ->first(fn ($group): bool => $group->getLabel() === $groupLabel)
        ->getItems())
        ->map(fn ($item): string => $item->getLabel())
        ->values()
        ->all();

    expect($itemLabelsFor(null))->toBe([
        __('admin.resources.content_item.workspace_navigation'),
        __('admin.resources.public_form_submission.navigation'),
        __('admin.curator.plural_label'),
    ])
        ->and($itemLabelsFor(AdminNavigationOrder::groupLabel(AdminNavigationOrder::CONTENT_MANAGEMENT)))->toBe([
            __('admin.resources.content_group.navigation'),
            __('admin.resources.content_item.navigation'),
            __('admin.resources.transcription.navigation'),
        ])
        ->and($itemLabelsFor(AdminNavigationOrder::groupLabel(AdminNavigationOrder::TAXONOMY_MANAGEMENT)))->toBe([
            __('admin.resources.author.navigation'),
            __('admin.resources.category.navigation'),
            __('admin.resources.content_tag.navigation'),
        ])
        ->and($itemLabelsFor(AdminNavigationOrder::groupLabel(AdminNavigationOrder::SITE_MANAGEMENT)))->toBe([
            __('admin.resources.homepage_section.navigation'),
            __('admin.pages.public_content_settings.navigation'),
            __('admin.pages.admin_ux_settings.navigation'),
            __('admin.resources.settings_backup.navigation'),
            __('admin.importer.pages.settings.navigation'),
        ]);
});

it('defers the public form submission navigation badge query until badge evaluation', function (): void {
    PublicFormSubmission::factory()
        ->count(2)
        ->create(['status' => PublicFormSubmissionStatus::New]);

    PublicFormSubmission::factory()->reviewed()->create();

    $navigationItem = PublicFormSubmissionResource::getNavigationItems()[0];
    $badgeProperty = new ReflectionProperty($navigationItem, 'badge');
    $badgeProperty->setAccessible(true);

    expect($badgeProperty->getValue($navigationItem))->toBeInstanceOf(Closure::class)
        ->and($navigationItem->getBadge())->toBe('2')
        ->and($navigationItem->getBadgeColor($navigationItem->getBadge()))->toBe('warning')
        ->and($navigationItem->getBadgeTooltip())->toBe(__('admin.resources.public_form_submission.navigation_badge_tooltip'));

    PublicFormSubmission::factory()->create(['status' => PublicFormSubmissionStatus::New]);

    expect($navigationItem->getBadge())->toBe('3');
});

it('labels episode workspace actions as the defaults and classic actions as system actions', function (): void {
    $group = ContentGroup::factory()->create();

    $listPage = Livewire::test(ListContentItems::class)
        ->assertOk()
        ->instance();

    $headerActions = collect($listPage->getCachedHeaderActions())
        ->keyBy(fn (Action $action): string => $action->getName());

    $tableActions = collect($listPage->getTable()->getRecordActions())
        ->keyBy(fn (Action $action): string => $action->getName());

    expect($headerActions->get('createEpisodeWorkspace')->getLabel())->toBe(__('admin.actions.create_episode_workspace'))
        ->and($headerActions->get('create')->getLabel())->toBe(__('admin.actions.classic_create'))
        ->and($tableActions->get('openEpisodeWorkspace')->getLabel())->toBe(__('admin.actions.open_episode_workspace'))
        ->and($tableActions->get('edit')->getLabel())->toBe(__('admin.actions.classic_edit'));

    $relationManagerTable = Livewire::test(ContentItemsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditContentGroup::class,
    ])
        ->assertOk()
        ->instance()
        ->getTable();

    $relationHeaderActions = collect($relationManagerTable->getHeaderActions())
        ->keyBy(fn (Action $action): string => $action->getName());
    $relationRecordActions = collect($relationManagerTable->getRecordActions())
        ->keyBy(fn (Action $action): string => $action->getName());

    expect($relationHeaderActions->get('create')->getLabel())->toBe(__('admin.actions.classic_create'))
        ->and($relationRecordActions->get('openEpisodeWorkspace')->getLabel())->toBe(__('admin.actions.open_episode_workspace'))
        ->and($relationRecordActions->get('edit')->getLabel())->toBe(__('admin.actions.classic_edit'));
});

it('applies admin table action modal and section defaults', function (): void {
    $group = ContentGroup::factory()->create();

    $itemsTable = Livewire::test(ListContentItems::class)
        ->assertOk()
        ->instance()
        ->getTable();

    $relationManagerTable = Livewire::test(ContentItemsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditContentGroup::class,
    ])
        ->assertOk()
        ->instance()
        ->getTable();

    expect($itemsTable->getRecordActionsPosition())->toBe(RecordActionsPosition::BeforeColumns)
        ->and($relationManagerTable->getRecordActionsPosition())->toBe(RecordActionsPosition::BeforeColumns)
        ->and(Action::make('wide-admin-modal')->getModalWidth())->toBe(Width::SevenExtraLarge)
        ->and(Action::make('compact-confirmation')->requiresConfirmation()->getModalWidth())->toBe(Width::Medium)
        ->and(Section::make('Admin section')->getColumnSpan())->toHaveKey('default', 'full');
});

it('combines relation manager tabs with content first on edit pages', function (): void {
    $group = ContentGroup::factory()->create([
        'title' => 'Tabbed Group',
        'slug' => 'tabbed-group',
    ]);
    $item = ContentItem::factory()->for($group)->create([
        'title' => 'Tabbed Item',
        'slug' => 'tabbed-item',
    ]);

    $itemEditor = Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertOk()
        ->assertSee(__('admin.tabs.item_details'))
        ->assertSee(__('admin.tabs.transcriptions'))
        ->instance();

    $groupEditor = Livewire::test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->assertOk()
        ->assertSee(__('admin.tabs.group_details'))
        ->assertSee(__('admin.tabs.content_items'))
        ->instance();

    expect($itemEditor->hasCombinedRelationManagerTabsWithContent())->toBeTrue()
        ->and($itemEditor->getContentTabPosition())->toBe(ContentTabPosition::Before)
        ->and($groupEditor->hasCombinedRelationManagerTabsWithContent())->toBeTrue()
        ->and($groupEditor->getContentTabPosition())->toBe(ContentTabPosition::Before)
        ->and(file_get_contents(resource_path('css/filament/admin/theme.css')))
        ->toContain('relationManagerTabs.container');
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

it('generates hebrew slugs from blank admin fields and preserves manual overrides', function (): void {
    Author::factory()->create([
        'name' => 'שלום עולם',
        'slug' => 'שלום-עולם',
    ]);

    Livewire::test(CreateAuthor::class)
        ->set('data.name', 'שלום עולם')
        ->assertSet('data.slug', 'שלום-עולם-2')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Author::query()->where('slug', 'שלום-עולם-2')->exists())->toBeTrue();

    Livewire::test(CreateCategory::class)
        ->set('data.slug', 'manual-category')
        ->set('data.name', 'קטגוריה חדשה')
        ->assertSet('data.slug', 'manual-category')
        ->callAction(TestAction::make('regenerateSlug')->schemaComponent('slug', 'form'))
        ->assertSet('data.slug', 'קטגוריה-חדשה')
        ->set('data.is_visible', true)
        ->set('data.sort_order', 0)
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::query()->where('slug', 'קטגוריה-חדשה')->exists())->toBeTrue();

    Livewire::test(CreateContentGroup::class)
        ->set('data.title', 'פודקאסט ציבורי')
        ->assertSet('data.slug', 'פודקאסט-ציבורי')
        ->set('data.status', PublicationStatus::Published->value)
        ->set('data.published_at', now()->subMinute())
        ->call('create')
        ->assertHasNoFormErrors();

    $group = ContentGroup::query()->where('slug', 'פודקאסט-ציבורי')->firstOrFail();

    ContentItem::factory()
        ->for($group)
        ->published()
        ->withTranscription()
        ->create();

    Filament::setCurrentPanel(Filament::getPanel('public'));

    $this->get(ShowContentGroup::getUrl(['contentGroupSlug' => $group->slug], panel: 'public'))
        ->assertSuccessful()
        ->assertSee($group->title);
});

it('scopes content item hebrew slugs to the group and enforces media field lengths', function (): void {
    $firstGroup = ContentGroup::factory()->published()->create();
    $secondGroup = ContentGroup::factory()->published()->create();

    ContentItem::factory()->for($firstGroup)->create([
        'title' => 'פרק מיוחד',
        'slug' => 'פרק-מיוחד',
    ]);

    Livewire::test(CreateContentItem::class)
        ->set('data.content_group_id', $firstGroup->id)
        ->set('data.title', 'פרק מיוחד')
        ->assertSet('data.slug', 'פרק-מיוחד-2')
        ->set('data.media_url', 'https://example.com/media/scoped-a.mp3')
        ->set('data.status', PublicationStatus::Published->value)
        ->set('data.published_at', now()->subMinute())
        ->call('create')
        ->assertHasNoFormErrors();

    $scopedItem = ContentItem::query()->where('slug', 'פרק-מיוחד-2')->firstOrFail();

    Transcription::factory()
        ->for($scopedItem)
        ->forAuthor()
        ->published()
        ->create();

    Filament::setCurrentPanel(Filament::getPanel('public'));

    $this->get(ShowContentItem::getUrl([
        'contentGroupSlug' => $firstGroup->slug,
        'contentItemSlug' => $scopedItem->slug,
    ], panel: 'public'))
        ->assertSuccessful()
        ->assertSee($scopedItem->title);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateContentItem::class)
        ->set('data.content_group_id', $secondGroup->id)
        ->set('data.title', 'פרק מיוחד')
        ->assertSet('data.slug', 'פרק-מיוחד');

    $longMediaUrl = 'https://example.com/'.str_repeat('a', 2030);
    $longEmbedUrl = 'https://www.youtube.com/embed/'.str_repeat('a', 2030);

    Livewire::test(CreateContentItem::class)
        ->fillForm([
            'content_group_id' => $firstGroup->id,
            'title' => 'Length Contract Item',
            'media_url' => $longMediaUrl,
            'embed_url' => $longEmbedUrl,
            'embed_provider' => str_repeat('a', 51),
            'status' => PublicationStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'media_url' => 'max',
            'embed_url' => 'max',
            'embed_provider' => 'max',
        ]);
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

    Livewire::test(CreateHomepageSection::class)
        ->set('data.type', HomepageSectionType::TopTranscribers->value)
        ->assertSchemaComponentHidden('category_id', 'form')
        ->assertSchemaComponentHidden('tag_id', 'form')
        ->assertSchemaComponentHidden('content_group_id', 'form')
        ->fillForm([
            'name' => 'Top Transcribers Section',
            'slug' => 'top-transcribers-section',
            'type' => HomepageSectionType::TopTranscribers->value,
            'limit' => 6,
            'sort_order' => 5,
            'is_visible' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HomepageSection::query()->where('slug', 'latest-section')->firstOrFail()->category_id)->toBeNull()
        ->and(HomepageSection::query()->where('slug', 'tag-section')->firstOrFail()->tag_id)->toBe($tag->id)
        ->and(HomepageSection::query()->where('slug', 'group-section')->firstOrFail()->content_group_id)->toBe($group->id)
        ->and(HomepageSection::query()->where('slug', 'top-transcribers-section')->firstOrFail()->category_id)->toBeNull();
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

it('creates and selects simple related records from content item relationship selectors', function (): void {
    $component = Livewire::test(CreateContentItem::class)
        ->assertActionExists(TestAction::make('createOption')->schemaComponent('content_group_id', 'form'))
        ->assertActionExists(TestAction::make('editOption')->schemaComponent('content_group_id', 'form'))
        ->assertActionExists(TestAction::make('createOption')->schemaComponent('categories', 'form'))
        ->assertActionDoesNotExist(TestAction::make('editOption')->schemaComponent('categories', 'form'))
        ->mountAction(TestAction::make('createOption')->schemaComponent('content_group_id', 'form'))
        ->set('mountedActions.0.data.title', 'פודקאסט מוטמע')
        ->assertSet('mountedActions.0.data.slug', 'פודקאסט-מוטמע')
        ->set('mountedActions.0.data.original_language_code', 'he')
        ->set('mountedActions.0.data.status', PublicationStatus::Draft->value)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->mountAction(TestAction::make('createOption')->schemaComponent('categories', 'form'))
        ->set('mountedActions.0.data.name', 'קטגוריה מוטמעת')
        ->assertSet('mountedActions.0.data.slug', 'קטגוריה-מוטמעת')
        ->set('mountedActions.0.data.is_visible', true)
        ->set('mountedActions.0.data.sort_order', 0)
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $group = ContentGroup::query()->where('slug', 'פודקאסט-מוטמע')->firstOrFail();
    $category = Category::query()->where('slug', 'קטגוריה-מוטמעת')->firstOrFail();

    $component
        ->assertSet('data.content_group_id', $group->id)
        ->assertSet('data.categories', [$category->id])
        ->fillForm([
            'title' => 'Inline Selector Item',
            'slug' => 'inline-selector-item',
            'media_url' => 'https://example.com/inline-selector.mp3',
            'status' => PublicationStatus::Draft,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = ContentItem::query()->where('slug', 'inline-selector-item')->firstOrFail();

    expect($item->content_group_id)->toBe($group->id)
        ->and($item->categories()->whereKey($category)->exists())->toBeTrue();
});

it('edits selected simple related records and leaves complex selectors create-disabled', function (): void {
    $author = Author::factory()->create([
        'name' => 'Original Modal Author',
        'slug' => 'original-modal-author',
    ]);
    $item = ContentItem::factory()->create();
    Transcription::factory()->for($item)->forAuthor($author)->published()->create();
    Transcription::factory()->for($item)->forAuthor($author)->published()->create();

    Livewire::test(CreateTranscription::class)
        ->set('data.transcriber_ids', [$author->id])
        ->assertActionExists(TestAction::make('createOption')->schemaComponent('transcriber_ids', 'form'))
        ->assertActionDoesNotExist(TestAction::make('editOption')->schemaComponent('transcriber_ids', 'form'))
        ->mountAction(TestAction::make('createOption')->schemaComponent('transcriber_ids', 'form'))
        ->set('mountedActions.0.data.name', 'Created Modal Transcriber')
        ->set('mountedActions.0.data.slug', 'created-modal-transcriber')
        ->set('mountedActions.0.data.bio_markdown', 'Created through a selector modal.')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertActionDoesNotExist(TestAction::make('createOption')->schemaComponent('featured_transcription_id', 'form'));

    Livewire::test(CreateTranscription::class)
        ->assertActionDoesNotExist(TestAction::make('createOption')->schemaComponent('content_item_id', 'form'));

    expect(Author::query()->where('slug', 'created-modal-transcriber')->exists())->toBeTrue();
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

it('manages content items from the content group relation manager', function (): void {
    $group = ContentGroup::factory()->create();
    $otherGroup = ContentGroup::factory()->create();
    $ownerItem = ContentItem::factory()->for($group)->create(['title' => 'Owner item']);
    $otherItem = ContentItem::factory()->for($otherGroup)->create(['title' => 'Other item']);
    $author = Author::factory()->create();

    Livewire::test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->assertOk();

    expect(ContentGroupResource::getRelations())->toHaveKey('contentItems');

    Livewire::test(ContentItemsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditContentGroup::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$ownerItem])
        ->assertCanNotSeeTableRecords([$otherItem])
        ->assertActionVisible(TestAction::make('create')->table())
        ->assertActionVisible(TestAction::make('addTranscription')->table($ownerItem))
        ->mountAction(TestAction::make('create')->table())
        ->set('mountedActions.0.data.title', 'Relation manager item')
        ->set('mountedActions.0.data.slug', 'relation-manager-item')
        ->set('mountedActions.0.data.media_url', 'https://example.com/relation-manager.mp3')
        ->set('mountedActions.0.data.status', PublicationStatus::Draft->value)
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $created = ContentItem::query()->where('slug', 'relation-manager-item')->firstOrFail();

    expect($created->content_group_id)->toBe($group->id);

    Livewire::test(ContentItemsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditContentGroup::class,
    ])
        ->assertCanSeeTableRecords([$created])
        ->mountAction(TestAction::make('edit')->table($created))
        ->set('mountedActions.0.data.title', 'Edited relation manager item')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->mountAction(TestAction::make('addTranscription')->table($created))
        ->set('mountedActions.0.data.transcriber_ids', [$author->id])
        ->set('mountedActions.0.data.title', 'Group relation transcript')
        ->set('mountedActions.0.data.language_code', 'he')
        ->set('mountedActions.0.data.status', PublicationStatus::Draft->value)
        ->set('mountedActions.0.data.transcript_markdown', 'Created from group relation manager')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($created->refresh()->title)->toBe('Edited relation manager item')
        ->and($created->content_group_id)->toBe($group->id)
        ->and($created->transcriptions()->where('title', 'Group relation transcript')->exists())->toBeTrue();
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
        ->set('mountedActions.0.data.transcriber_ids', [$author->id])
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
        ->set('mountedActions.0.data.transcriber_ids', [$author->id])
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
        ->set('mountedActions.0.data.transcriber_ids', [$author->id])
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
        ->set('mountedActions.0.data.transcriber_ids', [$author->id])
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

it('shows the effective transcription edit action on both episode list surfaces and hides it without transcriptions', function (): void {
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create(['title' => 'Action visible item']);
    $emptyItem = ContentItem::factory()->for($group)->create(['title' => 'Action hidden item']);
    $author = Author::factory()->create(['name' => 'Visible Transcriber']);
    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published()
        ->create(['title' => 'Visible effective transcript']);

    $item->update(['featured_transcription_id' => $transcription->id]);

    $expectedContext = __('admin.labels.transcription_context', [
        'title' => 'Visible effective transcript',
        'status' => __('admin.publication_status.published'),
    ]);

    Livewire::test(ListContentItems::class)
        ->assertActionVisible(TestAction::make('editEffectiveTranscription')->table($item))
        ->assertActionHidden(TestAction::make('editEffectiveTranscription')->table($emptyItem))
        ->assertTableColumnStateSet('effective_transcription_context', $expectedContext, $item);

    Livewire::test(ContentItemsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditContentGroup::class,
    ])
        ->assertActionVisible(TestAction::make('editEffectiveTranscription')->table($item))
        ->assertActionHidden(TestAction::make('editEffectiveTranscription')->table($emptyItem))
        ->assertTableColumnStateSet('effective_transcription_context', $expectedContext, $item);
});

it('resolves and saves effective transcription edit action fallback tiers', function (): void {
    $primary = Author::factory()->create(['name' => 'Primary UX2']);
    $secondary = Author::factory()->create(['name' => 'Secondary UX2']);
    $tertiary = Author::factory()->create(['name' => 'Tertiary UX2']);

    $effectiveItem = ContentItem::factory()->create(['title' => 'Effective published item']);
    $effectiveTarget = Transcription::factory()
        ->for($effectiveItem)
        ->forAuthor($primary)
        ->published(now()->subDays(3))
        ->create([
            'title' => 'Effective published transcript',
            'transcript_markdown' => 'Original effective body',
        ]);
    $effectiveItem->update(['featured_transcription_id' => $effectiveTarget->id]);
    $untouchedEffectiveSibling = Transcription::factory()
        ->for($effectiveItem)
        ->forAuthor($tertiary)
        ->create(['title' => 'Untouched effective sibling']);

    mountUx2EffectiveTranscriptionAction($effectiveItem)
        ->assertMountedActionModalSee('Effective published transcript')
        ->assertMountedActionModalSee(__('admin.publication_status.published'))
        ->assertSchemaStateSet([
            'title' => 'Effective published transcript',
            'transcriber_ids' => [$primary->id],
            'transcript_markdown' => 'Original effective body',
            'status' => PublicationStatus::Published,
        ])
        ->set('mountedActions.0.data.transcriber_ids', [$secondary->id, $primary->id])
        ->set('mountedActions.0.data.title', 'Edited effective transcript')
        ->set('mountedActions.0.data.status', PublicationStatus::Draft->value)
        ->set('mountedActions.0.data.transcript_markdown', 'Edited effective body')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $effectiveTarget->refresh();

    expect($effectiveTarget->title)->toBe('Edited effective transcript')
        ->and($effectiveTarget->status)->toBe(PublicationStatus::Draft)
        ->and($effectiveTarget->transcript_markdown)->toBe('Edited effective body')
        ->and($effectiveTarget->author_id)->toBe($secondary->id)
        ->and($untouchedEffectiveSibling->refresh()->title)->toBe('Untouched effective sibling');

    assertUx2TranscriberOrder($effectiveTarget, [$secondary->id, $primary->id]);

    $featuredDraftItem = ContentItem::factory()->create(['title' => 'Featured draft item']);
    $featuredDraftTarget = Transcription::factory()
        ->for($featuredDraftItem)
        ->forAuthor($primary)
        ->create([
            'title' => 'Featured draft transcript',
            'status' => PublicationStatus::Draft,
            'transcript_markdown' => 'Original featured draft body',
        ]);

    $featuredDraftItem->update(['featured_transcription_id' => $featuredDraftTarget->id]);

    mountUx2EffectiveTranscriptionAction($featuredDraftItem)
        ->assertMountedActionModalSee('Featured draft transcript')
        ->assertMountedActionModalSee(__('admin.publication_status.draft'))
        ->set('mountedActions.0.data.transcriber_ids', [$tertiary->id])
        ->set('mountedActions.0.data.title', 'Edited featured draft transcript')
        ->set('mountedActions.0.data.status', PublicationStatus::Published->value)
        ->set('mountedActions.0.data.transcript_markdown', 'Edited featured draft body')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($featuredDraftTarget->refresh()->title)->toBe('Edited featured draft transcript')
        ->and($featuredDraftTarget->status)->toBe(PublicationStatus::Published)
        ->and($featuredDraftTarget->transcript_markdown)->toBe('Edited featured draft body')
        ->and($featuredDraftTarget->author_id)->toBe($tertiary->id);

    assertUx2TranscriberOrder($featuredDraftTarget, [$tertiary->id]);

    $latestDraftItem = ContentItem::factory()->create(['title' => 'Latest draft item']);
    $olderDraft = Transcription::factory()
        ->for($latestDraftItem)
        ->forAuthor($primary)
        ->create(['title' => 'Older latest-only draft']);
    $latestDraftTarget = Transcription::factory()
        ->for($latestDraftItem)
        ->forAuthor($secondary)
        ->create([
            'title' => 'Newest latest-only draft',
            'transcript_markdown' => 'Original latest-only body',
        ]);
    $latestDraftItem->refresh()->forceFill(['featured_transcription_id' => null])->save();

    mountUx2EffectiveTranscriptionAction($latestDraftItem)
        ->assertMountedActionModalSee('Newest latest-only draft')
        ->assertMountedActionModalSee(__('admin.publication_status.draft'))
        ->set('mountedActions.0.data.transcriber_ids', [$primary->id])
        ->set('mountedActions.0.data.title', 'Edited newest latest-only draft')
        ->set('mountedActions.0.data.status', PublicationStatus::Published->value)
        ->set('mountedActions.0.data.transcript_markdown', 'Edited latest-only body')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($latestDraftTarget->refresh()->title)->toBe('Edited newest latest-only draft')
        ->and($latestDraftTarget->status)->toBe(PublicationStatus::Published)
        ->and($latestDraftTarget->transcript_markdown)->toBe('Edited latest-only body')
        ->and($latestDraftTarget->author_id)->toBe($primary->id)
        ->and($olderDraft->refresh()->title)->toBe('Older latest-only draft');

    assertUx2TranscriberOrder($latestDraftTarget, [$primary->id]);
});

it('creates standalone transcriptions and validates same item featured selection', function (): void {
    $item = ContentItem::factory()->create();
    $otherItem = ContentItem::factory()->create();
    $author = Author::factory()->create();
    $otherTranscription = Transcription::factory()->for($otherItem)->published()->create();

    Livewire::test(CreateTranscription::class)
        ->fillForm([
            'content_item_id' => $item->id,
            'transcriber_ids' => [$author->id],
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
