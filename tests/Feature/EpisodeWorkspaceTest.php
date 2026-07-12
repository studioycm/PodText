<?php

use App\Enums\PublicationStatus;
use App\Enums\Tb1PickerContainer;
use App\Enums\TranscriptionMode;
use App\Enums\TranscriptionPresentationMode;
use App\Filament\Pages\AdminUxSettings as AdminUxSettingsPage;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Pages\CreateEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
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
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

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

function clearEpisodeWorkspaceSettingsCache(): void
{
    app()->forgetInstance(AdminUxSettings::class);
    app(SettingsContainer::class)->clearCache();
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
    app()->instance(EpisodeSpotifyLookup::class, new class extends EpisodeSpotifyLookup
    {
        public function __construct() {}

        public function lookup(string $episodeInput, ?ImportConnection $connection = null): array
        {
            expect($episodeInput)->toBe('spotify:episode:abc123');

            return [
                'title' => 'Spotify title',
                'title_prefix' => 'Spotify show',
                'media_url' => 'https://open.spotify.com/episode/abc123',
                'embed_url' => 'https://open.spotify.com/embed/episode/abc123',
                'embed_provider' => 'spotify',
                'external_id' => 'abc123',
            ];
        }
    });

    Livewire::test(CreateEpisodeWorkspace::class)
        ->set('data.title', 'Manual title')
        ->set('data.title_prefix', 'Manual prefix')
        ->set('data.spotify_episode', 'spotify:episode:abc123')
        ->callAction(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'))
        ->assertSet('data.title', 'Manual title')
        ->assertSet('data.title_prefix', 'Manual prefix')
        ->assertSet('data.media_url', 'https://open.spotify.com/episode/abc123')
        ->assertSet('data.embed_url', 'https://open.spotify.com/embed/episode/abc123')
        ->assertSet('data.embed_provider', 'spotify')
        ->set('data.embed_html', '<iframe src="https://open.spotify.com/embed/episode/from-html"></iframe>')
        ->callAction(TestAction::make('extractEmbedSrc')->schemaComponent('embed_html', 'form'))
        ->assertSet('data.embed_url', 'https://open.spotify.com/embed/episode/from-html');
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
