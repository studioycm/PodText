<?php

use App\Enums\PublicationStatus;
use App\Enums\Tb1PickerContainer;
use App\Enums\TranscriptionMode;
use App\Enums\TranscriptionPresentationMode;
use App\Filament\Pages\AdminUxSettings as AdminUxSettingsPage;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Pages\CreateContentItem;
use App\Filament\Resources\ContentItems\Pages\CreateEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\Transcriptions\Pages\CreateTranscription;
use App\Jobs\DownloadExternalContentItemImage;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ImportConnection;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Support\Media\EpisodeSpotifyLookup;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Mail::fake();

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

function clearEpisodeWorkspaceSettingsCache(): void
{
    app()->forgetInstance(AdminUxSettings::class);
    app(SettingsContainer::class)->clearCache();
}

function setEpisodeWorkspaceTranscriptionMode(TranscriptionMode $mode): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => AdminUxSettings::group(),
            'name' => 'transcription_mode',
        ],
        [
            'locked' => false,
            'payload' => json_encode($mode->value),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    clearEpisodeWorkspaceSettingsCache();
}

it('resolves the workspace transcription as featured then latest published then newest draft', function (): void {
    $item = ContentItem::factory()->create();
    $olderPublished = Transcription::factory()
        ->for($item)
        ->published(now()->subDays(2))
        ->create(['title' => 'Older published']);
    $newerPublished = Transcription::factory()
        ->for($item)
        ->published(now()->subDay())
        ->create(['title' => 'Newer published']);
    $draft = Transcription::factory()
        ->for($item)
        ->create(['title' => 'Featured draft']);

    $item->refresh()->forceFill(['featured_transcription_id' => null])->save();

    expect($item->refresh()->resolveWorkspaceTranscription()?->is($newerPublished))->toBeTrue();

    $item->update(['featured_transcription_id' => $draft->id]);

    expect($item->refresh()->resolveWorkspaceTranscription()?->is($draft))->toBeTrue();

    $draftOnlyItem = ContentItem::factory()->create();
    $olderDraft = Transcription::factory()->for($draftOnlyItem)->create(['title' => 'Older draft']);
    $newerDraft = Transcription::factory()->for($draftOnlyItem)->create(['title' => 'Newer draft']);

    $draftOnlyItem->refresh()->forceFill(['featured_transcription_id' => null])->save();

    expect($draftOnlyItem->refresh()->resolveWorkspaceTranscription()?->is($newerDraft))->toBeTrue()
        ->and(ContentItem::factory()->create()->resolveWorkspaceTranscription())->toBeNull()
        ->and($olderPublished->is($newerPublished))->toBeFalse()
        ->and($olderDraft->is($newerDraft))->toBeFalse()
        ->and(file_get_contents(app_path('Filament/Resources/ContentItems/Tables/ContentItemsTable.php')))->not->toContain('workspaceTranscription')
        ->and(file_get_contents(app_path('Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php')))->not->toContain('workspaceTranscription');
});

it('creates an episode workspace with one empty transcription and pins it idempotently', function (): void {
    $group = ContentGroup::factory()->create([
        'title' => 'Workspace Podcast',
        'slug' => 'workspace-podcast',
    ]);

    Livewire::test(CreateEpisodeWorkspace::class)
        ->fillForm([
            'content_group_id' => $group->id,
            'title_prefix' => 'Manual Prefix',
            'title' => 'Workspace Episode',
            'slug' => 'workspace-episode',
            'media_url' => 'https://example.com/workspace.mp3',
            'status' => PublicationStatus::Draft->value,
        ])
        ->set('data.workspaceTranscription.title', 'Workspace transcript')
        ->set('data.workspaceTranscription.status', PublicationStatus::Draft->value)
        ->set('data.workspaceTranscription.transcript_markdown', '')
        ->call('create')
        ->assertHasNoFormErrors();

    $item = ContentItem::query()->where('slug', 'workspace-episode')->firstOrFail();
    $transcription = $item->transcriptions()->firstOrFail();

    expect($item->featured_transcription_id)->toBe($transcription->id)
        ->and($item->title_prefix)->toBe('Manual Prefix')
        ->and($transcription->title)->toBe('Workspace transcript')
        ->and($transcription->transcript_markdown)->toBe('');

    Livewire::test(EditEpisodeWorkspace::class, ['record' => $item->getRouteKey()])
        ->set('data.workspaceTranscription.title', 'Workspace transcript edited')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->refresh()->transcriptions()->count())->toBe(1)
        ->and($item->featured_transcription_id)->toBe($transcription->id)
        ->and($transcription->refresh()->title)->toBe('Workspace transcript edited');
});

it('replaces the workspace transcription by selecting an existing record or starting fresh', function (): void {
    setEpisodeWorkspaceTranscriptionMode(TranscriptionMode::Multi);

    $item = ContentItem::factory()->create();
    $first = Transcription::factory()->for($item)->create(['title' => 'First workspace']);
    $second = Transcription::factory()->for($item)->create(['title' => 'Second workspace']);

    $item->update(['featured_transcription_id' => $first->id]);

    Livewire::test(EditEpisodeWorkspace::class, ['record' => $item->getRouteKey()])
        ->mountAction(TestAction::make('replaceWorkspaceTranscription'))
        ->set('mountedActions.0.data.replacement_mode', 'existing')
        ->set('mountedActions.0.data.existing_transcription_id', $second->id)
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($item->refresh()->featured_transcription_id)->toBe($second->id)
        ->and($first->refresh()->content_item_id)->toBe($item->id);

    Livewire::test(EditEpisodeWorkspace::class, ['record' => $item->getRouteKey()])
        ->mountAction(TestAction::make('replaceWorkspaceTranscription'))
        ->set('mountedActions.0.data.replacement_mode', 'fresh')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $fresh = $item->refresh()->featuredTranscription()->firstOrFail();

    expect($fresh->id)->not->toBe($second->id)
        ->and($fresh->transcript_markdown)->toBe('')
        ->and($item->transcriptions()->count())->toBe(3)
        ->and($second->refresh()->content_item_id)->toBe($item->id);
});

it('rejects forged existing workspace replacement while single-transcription mode is active', function (): void {
    setEpisodeWorkspaceTranscriptionMode(TranscriptionMode::Single);

    $item = ContentItem::factory()->create();
    $first = Transcription::factory()->for($item)->create(['title' => 'First workspace']);
    $second = Transcription::factory()->for($item)->create(['title' => 'Second workspace']);

    $item->update(['featured_transcription_id' => $first->id]);

    Livewire::test(EditEpisodeWorkspace::class, ['record' => $item->getRouteKey()])
        ->mountAction(TestAction::make('replaceWorkspaceTranscription'))
        ->set('mountedActions.0.data.replacement_mode', 'existing')
        ->set('mountedActions.0.data.existing_transcription_id', $second->id)
        ->callMountedAction()
        ->assertForbidden();

    expect($item->refresh()->featured_transcription_id)->toBe($first->id);
});

it('saves workspace admin ux settings and renders modal and slideover transcript modes', function (): void {
    Livewire::test(AdminUxSettingsPage::class)
        ->set('data.transcription_presentation_mode', TranscriptionPresentationMode::SlideOver->value)
        ->set('data.transcription_mode', TranscriptionMode::Single->value)
        ->set('data.show_episode_workspace_hint_line', true)
        ->set('data.show_episode_workspace_language_code', true)
        ->set('data.tb1_picker_container', Tb1PickerContainer::SlideOver->value)
        ->call('save')
        ->assertHasNoFormErrors();

    clearEpisodeWorkspaceSettingsCache();

    $settings = app(AdminUxSettings::class);

    expect($settings->transcription_presentation_mode)->toBe(TranscriptionPresentationMode::SlideOver->value)
        ->and($settings->transcription_mode)->toBe(TranscriptionMode::Single->value)
        ->and($settings->show_episode_workspace_hint_line)->toBeTrue()
        ->and($settings->show_episode_workspace_language_code)->toBeTrue()
        ->and($settings->tb1_picker_container)->toBe(Tb1PickerContainer::SlideOver->value);

    $item = ContentItem::factory()->create();
    Transcription::factory()->for($item)->create();

    Livewire::test(EditEpisodeWorkspace::class, ['record' => $item->getRouteKey()])
        ->assertSee('data-transcription-presentation-mode="slideover"', false)
        ->assertSchemaComponentVisible('workspaceTranscription.language_code', 'form');

    Livewire::test(AdminUxSettingsPage::class)
        ->set('data.transcription_presentation_mode', TranscriptionPresentationMode::Modal->value)
        ->set('data.transcription_mode', TranscriptionMode::Single->value)
        ->set('data.show_episode_workspace_hint_line', true)
        ->set('data.show_episode_workspace_language_code', true)
        ->set('data.tb1_picker_container', Tb1PickerContainer::Modal->value)
        ->call('save')
        ->assertHasNoFormErrors();

    clearEpisodeWorkspaceSettingsCache();

    Livewire::test(EditEpisodeWorkspace::class, ['record' => $item->getRouteKey()])
        ->assertSee('data-transcription-presentation-mode="modal"', false)
        ->assertSee(__('admin.sections.episode_workspace_transcription'));
});

it('fills blank fields from spotify lookup and extracts iframe src values', function (): void {
    $matchedGroup = ContentGroup::factory()->create(['title' => 'Spotify Show']);

    app()->instance(EpisodeSpotifyLookup::class, new class extends EpisodeSpotifyLookup
    {
        public function __construct() {}

        public function lookup(string $episodeInput, ?ImportConnection $connection = null): array
        {
            expect($episodeInput)->toBe('spotify:episode:abc123');

            return [
                'description_markdown' => "Spotify paragraph\n\nSecond paragraph",
                'title' => 'Spotify title',
                'title_prefix' => '  spotify   show  ',
                'media_url' => 'https://open.spotify.com/episode/abc123',
                'embed_url' => 'https://open.spotify.com/embed/episode/abc123',
                'embed_provider' => 'spotify',
                'external_id' => 'abc123',
                'media_metadata' => [
                    'show_id' => 'spotify-show',
                ],
            ];
        }
    });

    Livewire::test(CreateEpisodeWorkspace::class)
        ->set('data.title', 'Manual title')
        ->set('data.title_prefix', 'Manual prefix')
        ->set('data.spotify_episode', 'spotify:episode:abc123')
        ->mountAction(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'))
        ->assertSet('mountedActions.0.data.fill_slug_when_empty', true)
        ->assertSet('mountedActions.0.data.fill_title_prefix_when_empty', true)
        ->assertSet('mountedActions.0.data.link_matched_podcast', true)
        ->assertSet('mountedActions.0.data.overwrite_non_empty_fields', false)
        ->assertSet('mountedActions.0.data.matched_podcast_name', 'Spotify Show')
        ->assertSet('mountedActions.0.data.matched_podcast_tier', 'exact_title')
        ->callMountedAction()
        ->assertSet('data.title', 'Manual title')
        ->assertSet('data.title_prefix', 'Manual prefix')
        ->assertSet('data.content_group_id', $matchedGroup->id)
        ->assertSet('data.slug', 'manual-title')
        ->assertSet('data.description_markdown', "Spotify paragraph\n\nSecond paragraph")
        ->assertSet('data.media_url', 'https://open.spotify.com/episode/abc123')
        ->assertSet('data.embed_url', 'https://open.spotify.com/embed/episode/abc123')
        ->assertSet('data.embed_provider', 'spotify')
        ->set('data.embed_html', '<iframe src="https://open.spotify.com/embed/episode/from-html"></iframe>')
        ->callAction(TestAction::make('extractEmbedSrc')->schemaComponent('embed_html', 'form'))
        ->assertSet('data.embed_url', 'https://open.spotify.com/embed/episode/from-html');
});

it('prechecks spotify workspace podcast linking for show id matches', function (): void {
    $matchedGroup = ContentGroup::factory()->create(['title' => 'Existing ID Show']);
    ContentItem::factory()
        ->for($matchedGroup)
        ->create([
            'media_metadata' => [
                'show_id' => 'id-show',
            ],
        ]);

    app()->instance(EpisodeSpotifyLookup::class, new class extends EpisodeSpotifyLookup
    {
        public function __construct() {}

        public function lookup(string $episodeInput, ?ImportConnection $connection = null): array
        {
            return [
                'title' => 'ID Matched Episode',
                'title_prefix' => 'Different API Show Name',
                'media_url' => 'https://open.spotify.com/episode/idmatch12345',
                'external_id' => 'idmatch12345',
                'media_metadata' => [
                    'show_id' => 'id-show',
                ],
            ];
        }
    });

    Livewire::test(CreateEpisodeWorkspace::class)
        ->set('data.spotify_episode', 'spotify:episode:idmatch12345')
        ->mountAction(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'))
        ->assertSet('mountedActions.0.data.matched_podcast_name', 'Existing ID Show')
        ->assertSet('mountedActions.0.data.matched_podcast_tier', 'show_id')
        ->assertSet('mountedActions.0.data.link_matched_podcast', true)
        ->callMountedAction()
        ->assertSet('data.content_group_id', $matchedGroup->id);
});

it('shows close spotify workspace podcast matches unchecked as suggestions', function (): void {
    ContentGroup::factory()->create(['title' => 'Daily Podcast']);

    app()->instance(EpisodeSpotifyLookup::class, new class extends EpisodeSpotifyLookup
    {
        public function __construct() {}

        public function lookup(string $episodeInput, ?ImportConnection $connection = null): array
        {
            return [
                'title' => 'Close Match Episode',
                'title_prefix' => 'Daily Podcast with Yoni',
                'media_url' => 'https://open.spotify.com/episode/close12345',
                'external_id' => 'close12345',
                'media_metadata' => [
                    'show_id' => 'unknown-show',
                ],
            ];
        }
    });

    Livewire::test(CreateEpisodeWorkspace::class)
        ->set('data.spotify_episode', 'spotify:episode:close12345')
        ->mountAction(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'))
        ->assertSet('mountedActions.0.data.matched_podcast_name', 'Daily Podcast')
        ->assertSet('mountedActions.0.data.matched_podcast_tier', 'close_title')
        ->assertSet('mountedActions.0.data.link_matched_podcast', false)
        ->callMountedAction()
        ->assertSet('data.content_group_id', null);
});

it('honors spotify workspace modal options and clears the title prefix', function (): void {
    app()->instance(EpisodeSpotifyLookup::class, new class extends EpisodeSpotifyLookup
    {
        public function __construct() {}

        public function lookup(string $episodeInput, ?ImportConnection $connection = null): array
        {
            return [
                'description_markdown' => 'Spotify description',
                'title' => 'Spotify title',
                'title_prefix' => 'Spotify show',
                'media_url' => 'https://open.spotify.com/episode/offpath12345',
                'embed_url' => 'https://open.spotify.com/embed/episode/offpath12345',
                'embed_provider' => 'spotify',
                'external_id' => 'offpath12345',
                'media_metadata' => [
                    'show_id' => 'missing-show',
                ],
            ];
        }
    });

    Livewire::test(CreateEpisodeWorkspace::class)
        ->set('data.title', '')
        ->set('data.slug', '')
        ->set('data.title_prefix', '')
        ->set('data.spotify_episode', 'spotify:episode:offpath12345')
        ->mountAction(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'))
        ->set('mountedActions.0.data.fill_slug_when_empty', false)
        ->set('mountedActions.0.data.fill_title_prefix_when_empty', false)
        ->set('mountedActions.0.data.link_matched_podcast', true)
        ->set('mountedActions.0.data.overwrite_non_empty_fields', false)
        ->callMountedAction()
        ->assertSet('data.title', 'Spotify title')
        ->assertSet('data.slug', '')
        ->assertSet('data.title_prefix', '')
        ->assertSet('data.content_group_id', null)
        ->set('data.title_prefix', 'Temporary prefix')
        ->callAction(TestAction::make('clearTitlePrefix')->schemaComponent('title_prefix', 'form'))
        ->assertSet('data.title_prefix', null);
});

it('autofills published dates for workspace items and transcriptions only when blank', function (): void {
    $group = ContentGroup::factory()->create();
    $contentItem = ContentItem::factory()->for($group)->create();
    $existingPublishedAt = now('Asia/Jerusalem')->subDay();

    Livewire::test(EditEpisodeWorkspace::class, ['record' => $contentItem->getRouteKey()])
        ->set('data.published_at', null)
        ->set('data.status', PublicationStatus::Published->value)
        ->assertSet('data.status', PublicationStatus::Published->value)
        ->assertSet('data.published_at', fn (mixed $value): bool => filled($value))
        ->set('data.published_at', $existingPublishedAt)
        ->set('data.status', PublicationStatus::Published->value)
        ->assertSet('data.published_at', $existingPublishedAt);

    Livewire::test(CreateTranscription::class)
        ->set('data.content_item_id', $contentItem->id)
        ->set('data.transcript_markdown', 'Published transcript')
        ->set('data.published_at', null)
        ->set('data.status', PublicationStatus::Published->value)
        ->assertSet('data.published_at', fn (mixed $value): bool => filled($value));
});

it('saves trusted embed html verbatim from the system item form and renders the ltr editor', function (): void {
    $group = ContentGroup::factory()->create();
    $rawHtml = "<section data-fix1-embed=\"raw\">\n<script>window.fix1Embed = true;</script><iframe src=\"https://example.com/embed\"></iframe>\n</section>";

    Livewire::test(CreateContentItem::class)
        ->assertSeeHtml('data-trusted-html-code-editor="true"')
        ->assertSeeHtml('dir="ltr"')
        ->fillForm([
            'content_group_id' => $group->id,
            'title' => 'Raw HTML Episode',
            'slug' => 'raw-html-episode',
            'media_url' => 'https://example.com/raw-html-episode.mp3',
            'embed_html' => $rawHtml,
            'status' => PublicationStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ContentItem::query()->where('slug', 'raw-html-episode')->firstOrFail()->embed_html)
        ->toBe($rawHtml);
});

it('defaults item list rows and relation manager rows to the episode workspace while preserving classic edit', function (): void {
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();

    expect(ContentItemResource::getUrl('workspace', ['record' => $item]))
        ->toContain("/{$item->getRouteKey()}/workspace");

    Livewire::test(ListContentItems::class)
        ->assertActionVisible(TestAction::make('openEpisodeWorkspace')->table($item))
        ->assertActionVisible(TestAction::make('edit')->table($item));

    Livewire::test(ContentItemsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditContentGroup::class,
    ])
        ->assertActionVisible(TestAction::make('openEpisodeWorkspace')->table($item))
        ->assertActionVisible(TestAction::make('edit')->table($item));
});

it('shows TB1 image actions and queues external image downloads from episode tables', function (): void {
    Queue::fake();

    $withoutLocal = ContentItem::factory()->create([
        'external_thumbnail_url' => 'https://cdn.example.test/without-local.jpg',
        'image_path' => null,
    ]);
    $withLocal = ContentItem::factory()->create([
        'external_thumbnail_url' => 'https://cdn.example.test/with-local.jpg',
        'image_path' => 'content-items/images/local.jpg',
    ]);
    $withoutExternal = ContentItem::factory()->create([
        'external_thumbnail_url' => null,
        'image_path' => null,
    ]);

    Livewire::test(ListContentItems::class)
        ->assertActionVisible(TestAction::make('chooseContentItemImage')->table($withoutLocal))
        ->assertActionVisible(TestAction::make('downloadExternalImage')->table($withoutLocal))
        ->assertActionHidden(TestAction::make('downloadExternalImageOverwrite')->table($withoutLocal))
        ->assertActionHidden(TestAction::make('downloadExternalImage')->table($withLocal))
        ->assertActionVisible(TestAction::make('downloadExternalImageOverwrite')->table($withLocal))
        ->assertActionHidden(TestAction::make('downloadExternalImage')->table($withoutExternal))
        ->callAction(TestAction::make('downloadExternalImage')->table($withoutLocal));

    Queue::assertPushed(
        DownloadExternalContentItemImage::class,
        fn (DownloadExternalContentItemImage $job): bool => $job->contentItemId === $withoutLocal->id
            && $job->userId === auth()->id()
            && $job->overwrite === false
            && $job->queue === 'imports-exports',
    );
});
