<?php

use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\RelationManagers\TranscriptionsRelationManager;
use App\Filament\Resources\Transcriptions\Pages\CreateTranscription;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Livewire\Public\ContentItemTranscriptViewer;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContentItemQueries;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicContent\PublicTranscriptionAggregates;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicContent\PublicTranscriptionSelector;
use App\Support\PublicFront\Cards\PublicContentGroupCardPresenter;
use App\Support\PublicFront\Cards\PublicContentItemCardPresenter;
use App\Support\PublicFront\Cards\PublicContributorCardPresenter;
use App\Support\PublicFront\Cards\PublicFrontCardTemplate;
use App\Support\PublicFront\Groups\PublicContentGroupQueries;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\Transcriptions\TranscriptionModeLabel;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Mail::fake();
    setTestTranscriptionMode(TranscriptionMode::Single);
});

function saveLens1PublicTranscriptionPolicy(): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'transcription_policy',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'public_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
                'count_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
                'show_multiple_transcriptions_on_item_page' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app()->forgetInstance(PublicTranscriptionPolicy::class);
    app()->forgetInstance(PublicTranscriptionSelector::class);
    app()->forgetInstance(PublicTranscriptionAggregates::class);
    app(SettingsContainer::class)->clearCache();
}

function lens1Template(string $family, array $parts): PublicFrontCardTemplate
{
    return PublicFrontCardTemplate::fromArray([
        'key' => "lens1_{$family}",
        'label' => 'LENS1 test template',
        'family' => $family,
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'hidden',
        'title_size' => 'base',
        'parts' => $parts,
    ]);
}

it('auto-features the first transcription across direct resource and relation-manager creation paths', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $directItem = ContentItem::factory()->create();
    $direct = Transcription::factory()->for($directItem)->create(['title' => 'Direct transcript']);

    expect($directItem->refresh()->featured_transcription_id)->toBe($direct->id);

    $resourceItem = ContentItem::factory()->create();
    $resourceAuthor = Author::factory()->create();

    Livewire::test(CreateTranscription::class)
        ->fillForm([
            'content_item_id' => $resourceItem->id,
            'transcriber_ids' => [$resourceAuthor->id],
            'title' => 'Resource episode transcript',
            'language_code' => 'he',
            'transcript_markdown' => 'Resource body',
            'status' => PublicationStatus::Published->value,
            'published_at' => now()->subMinute(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $resourceTranscription = Transcription::query()
        ->where('title', 'Resource episode transcript')
        ->firstOrFail();

    expect($resourceItem->refresh()->featured_transcription_id)->toBe($resourceTranscription->id);

    $relationItem = ContentItem::factory()->create();
    $relationAuthor = Author::factory()->create();

    Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $relationItem,
        'pageClass' => EditContentItem::class,
    ])
        ->mountAction(TestAction::make('create')->table())
        ->set('mountedActions.0.data.transcriber_ids', [$relationAuthor->id])
        ->set('mountedActions.0.data.title', 'Relation episode transcript')
        ->set('mountedActions.0.data.language_code', 'he')
        ->set('mountedActions.0.data.transcript_markdown', 'Relation body')
        ->set('mountedActions.0.data.status', PublicationStatus::Draft->value)
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $relationTranscription = Transcription::query()
        ->where('title', 'Relation episode transcript')
        ->firstOrFail();

    expect($relationItem->refresh()->featured_transcription_id)->toBe($relationTranscription->id);

    setTestTranscriptionMode(TranscriptionMode::Multi);

    $second = Transcription::factory()->for($directItem)->create(['title' => 'Second direct transcript']);

    expect($directItem->refresh()->featured_transcription_id)->toBe($direct->id)
        ->and($second->id)->not->toBe($direct->id);
});

it('blocks a second single-mode creation with localized messages while allowing workspace replacement', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $item = ContentItem::factory()->create();
    $first = Transcription::factory()->for($item)->create(['title' => 'Original transcript']);
    $author = Author::factory()->create();

    app()->setLocale('en');

    expect(fn () => Transcription::factory()->for($item)->create())
        ->toThrow(ValidationException::class, 'This episode already has its transcript');

    Livewire::test(CreateTranscription::class)
        ->fillForm([
            'content_item_id' => $item->id,
            'transcriber_ids' => [$author->id],
            'title' => 'Blocked resource transcript',
            'language_code' => 'en',
            'transcript_markdown' => 'Blocked body',
            'status' => PublicationStatus::Draft->value,
        ])
        ->call('create')
        ->assertSee('This episode already has its transcript');

    app()->setLocale('he');

    expect(fn () => Transcription::factory()->for($item)->create())
        ->toThrow(ValidationException::class, 'לפרק כבר יש תמלול');

    $replacement = $item->startFreshWorkspaceTranscription();

    expect($item->refresh()->transcriptions()->count())->toBe(2)
        ->and($item->featured_transcription_id)->toBe($replacement->id)
        ->and($replacement->id)->not->toBe($first->id);
});

it('counts effective episodes in single mode and keeps record counts in multi mode', function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);
    saveLens1PublicTranscriptionPolicy();

    $group = ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()->for($group)->published(now()->subHour())->create();
    $featuredAuthor = Author::factory()->create(['name' => 'Featured Person']);
    $historicalAuthor = Author::factory()->create(['name' => 'Historical Person']);

    $featured = Transcription::factory()
        ->for($item)
        ->forAuthor($featuredAuthor)
        ->published(now()->subMinutes(20))
        ->create(['title' => 'Effective transcript', 'word_count' => 200]);
    $historical = Transcription::factory()
        ->for($item)
        ->forAuthor($historicalAuthor)
        ->published(now()->subMinutes(10))
        ->create(['title' => 'Historical transcript', 'word_count' => 500]);

    $item->update(['featured_transcription_id' => $featured->id]);
    setTestTranscriptionMode(TranscriptionMode::Single);

    $singleGroup = PublicContentGroupQueries::base()->whereKey($group)->firstOrFail();
    $singleItem = PublicContentItemQueries::base()->whereKey($item)->firstOrFail();
    $singleContributors = PublicContributorDiscovery::contributors()->get();

    expect((int) $singleGroup->public_transcriptions_count)->toBe(1)
        ->and((int) $singleItem->public_transcriptions_count)->toBe(1)
        ->and($singleContributors->pluck('id')->all())->toContain($featuredAuthor->id)
        ->and($singleContributors->pluck('id')->all())->not->toContain($historicalAuthor->id)
        ->and((int) $singleContributors->firstWhere('id', $featuredAuthor->id)->public_transcriptions_count)->toBe(1);

    setTestTranscriptionMode(TranscriptionMode::Multi);

    $multiGroup = PublicContentGroupQueries::base()->whereKey($group)->firstOrFail();
    $multiItem = PublicContentItemQueries::base()->whereKey($item)->firstOrFail();
    $multiContributors = PublicContributorDiscovery::contributors()->get();

    expect((int) $multiGroup->public_transcriptions_count)->toBe(2)
        ->and((int) $multiItem->public_transcriptions_count)->toBe(2)
        ->and($multiContributors->pluck('id')->all())->toContain($featuredAuthor->id, $historicalAuthor->id)
        ->and($historical->content_item_id)->toBe($item->id);
});

it('selects single label variants and suppresses an episode count template part and viewer switcher', function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);
    saveLens1PublicTranscriptionPolicy();

    $group = ContentGroup::factory()->published()->create(['title' => 'LENS Podcast']);
    $item = ContentItem::factory()->for($group)->published(now()->subHour())->create(['title' => 'LENS Episode']);
    $author = Author::factory()->create(['name' => 'LENS Contributor']);
    $featured = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published(now()->subMinutes(20))
        ->create(['title' => 'Featured lens transcript']);
    $historical = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published(now()->subMinutes(10))
        ->create(['title' => 'Historical lens transcript']);
    $item->update(['featured_transcription_id' => $featured->id]);

    setTestTranscriptionMode(TranscriptionMode::Single);
    app()->setLocale('en');

    expect(TranscriptionModeLabel::choice('public.labels.public_transcriptions_count', 2, ['count' => 2]))
        ->toBe('2 episodes')
        ->and(TranscriptionModeLabel::choice(
            'public.labels.public_transcriptions_count',
            2,
            ['count' => 2],
            'public.labels.single.public_transcriptions_count_full',
        ))->toBe('2 transcribed episodes')
        ->and(TranscriptionModeLabel::text('public.labels.public_group_latest_transcription_date', ['date' => '13/07/2026']))
        ->toBe('latest episode 13/07/2026');

    app()->setLocale('he');

    expect(TranscriptionModeLabel::choice('public.labels.public_transcriptions_count', 2, ['count' => 2]))
        ->toBe('2 פרקים')
        ->and(TranscriptionModeLabel::text('public.pages.contributor.items_heading'))
        ->toBe('פרקים שתומללו');

    $publicItem = PublicContentItemQueries::base()->whereKey($item)->firstOrFail();
    $itemTemplate = lens1Template('content_item', [[
        'type' => 'metadata_row',
        'source' => 'content_item',
        'attribute' => 'transcription_count',
        'visible' => true,
        'order' => 10,
    ]]);
    $itemCard = app(PublicContentItemCardPresenter::class)->present(
        $publicItem,
        (new PublicContentCardOptions)->withTranscriptionDisplay('effective_plus_count'),
        $itemTemplate,
    );

    expect(collect($itemCard['parts'])->pluck('attribute')->all())->not->toContain('transcription_count');

    app()->setLocale('en');

    $publicGroup = PublicContentGroupQueries::base()->whereKey($group)->firstOrFail();
    $groupCard = app(PublicContentGroupCardPresenter::class)->present(
        $publicGroup,
        lens1Template('content_group', [
            ['type' => 'metadata_row', 'source' => 'content_group', 'attribute' => 'transcription_count', 'visible' => true, 'order' => 10],
            ['type' => 'metadata_row', 'source' => 'content_group', 'attribute' => 'latest_transcription_date', 'visible' => true, 'order' => 20],
        ]),
    );
    $publicAuthor = PublicContributorDiscovery::contributors()->whereKey($author)->firstOrFail();
    $contributorCard = app(PublicContributorCardPresenter::class)->present(
        $publicAuthor,
        '/contributors/lens-contributor',
        lens1Template('contributor', [[
            'type' => 'metadata_row',
            'source' => 'contributor',
            'attribute' => 'transcription_count',
            'visible' => true,
            'order' => 10,
        ]]),
    );

    expect($groupCard['public_transcriptions_count_label'])->toBe('1 episode')
        ->and($groupCard['latest_transcription_date_label'])->toStartWith('latest episode ')
        ->and($contributorCard['counts']['transcriptions_label'])->toBe('1 episode');

    Livewire::test(ContentItemTranscriptViewer::class, ['contentItem' => $item->refresh()])
        ->call('selectTranscription', $historical->reference_key)
        ->assertSet('selectedTranscription', $featured->reference_key)
        ->assertDontSee('Historical lens transcript');

    setTestTranscriptionMode(TranscriptionMode::Multi);

    expect(TranscriptionModeLabel::choice('public.labels.public_transcriptions_count', 2, ['count' => 2]))
        ->toBe('2 public transcriptions');
});

it('scopes the standalone resource to the current episode row and exposes history only to super-admins', function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);

    $item = ContentItem::factory()->create();
    $older = Transcription::factory()->for($item)->published(now()->subDay())->create(['title' => 'Older row']);
    $current = Transcription::factory()->for($item)->create(['title' => 'Current draft row']);
    $item->update(['featured_transcription_id' => $current->id]);

    setTestTranscriptionMode(TranscriptionMode::Single);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ListTranscriptions::class)
        ->assertTableFilterVisible('history')
        ->assertCanSeeTableRecords([$current])
        ->assertCanNotSeeTableRecords([$older])
        ->filterTable('history')
        ->assertCanSeeTableRecords([$current, $older])
        ->assertActionHidden(TestAction::make('setFeatured')->table($older));

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListTranscriptions::class)
        ->assertTableFilterHidden('history')
        ->assertCanSeeTableRecords([$current])
        ->assertCanNotSeeTableRecords([$older]);

    setTestTranscriptionMode(TranscriptionMode::Multi);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ListTranscriptions::class)
        ->assertTableFilterVisible('history')
        ->assertCanSeeTableRecords([$current, $older])
        ->assertActionVisible(TestAction::make('setFeatured')->table($older));

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListTranscriptions::class)
        ->assertTableFilterHidden('history')
        ->assertCanSeeTableRecords([$current, $older]);
});
