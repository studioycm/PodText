<?php

use App\Enums\ExternalImageFailureReason;
use App\Enums\PublicationStatus;
use App\Filament\Forms\SpotifyShowInput;
use App\Filament\Resources\ContentGroups\Pages\CreateContentGroup;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentItems\Pages\CreateContentItem;
use App\Filament\Resources\ContentItems\Pages\CreateEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Schemas\EpisodeWorkspaceForm;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Media\EpisodeSpotifyLookup;
use App\Support\Media\ExternalImageUnavailableException;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Media\SafeExternalImageFetcher;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

function spotifyAcquisitionPng(): string
{
    return base64_decode(
        trim((string) file_get_contents(base_path('tests/Fixtures/media/valid.png.base64'))),
        true,
    ) ?: throw new RuntimeException('Invalid Spotify acquisition fixture.');
}

function fakeSpotifyEpisodeLookup(string $imageUrl): void
{
    $lookup = Mockery::mock(EpisodeSpotifyLookup::class);
    $lookup->shouldReceive('lookup')->atLeast()->once()->andReturn([
        'title' => 'Spotify episode',
        'description_markdown' => 'Spotify description',
        'media_url' => 'https://open.spotify.com/episode/episode12345',
        'embed_url' => 'https://open.spotify.com/embed/episode/episode12345',
        'external_thumbnail_url' => $imageUrl,
        'media_metadata' => ['show_id' => 'show12345'],
    ]);
    app()->instance(EpisodeSpotifyLookup::class, $lookup);
}

it('feeds Spotify episode artwork through shared URL acquisition on workspace and classic create/edit surfaces', function (): void {
    $imageUrl = 'https://cdn.example.test/spotify-episode.png';
    fakeSpotifyEpisodeLookup($imageUrl);
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')->times(2)->with($imageUrl)->andReturn(spotifyAcquisitionPng());
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    $workspace = Livewire::test(CreateEpisodeWorkspace::class)
        ->set('data.spotify_episode', 'spotify:episode:episode12345')
        ->callAction(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'), data: [
            'fill_slug_when_empty' => true,
            'fill_title_prefix_when_empty' => true,
            'link_matched_podcast' => false,
            'overwrite_non_empty_fields' => false,
        ]);
    $classic = Livewire::test(CreateContentItem::class)
        ->set('data.spotify_episode', 'spotify:episode:episode12345')
        ->callAction(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'), data: [
            'fill_slug_when_empty' => true,
            'fill_title_prefix_when_empty' => true,
            'link_matched_podcast' => false,
            'overwrite_non_empty_fields' => false,
        ]);
    $classicReferenceKey = $classic->get('data.primary_image_media_reference_key');

    expect($workspace->get('data.primary_image_media_reference_key'))->toBeString()
        ->and($classicReferenceKey)->toBeString()
        ->and(Media::query()->count())->toBe(2)
        ->and(MediaAsset::query()->count())->toBe(2);

    $item = ContentItem::factory()->create();
    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertActionExists(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'));
    Livewire::test(EditEpisodeWorkspace::class, ['record' => $item->getRouteKey()])
        ->assertActionExists(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'));
});

it('feeds Spotify show artwork through shared URL acquisition on podcast create and edit surfaces', function (): void {
    $imageUrl = 'https://cdn.example.test/spotify-show.png';
    $lookup = Mockery::mock(EpisodeSpotifyLookup::class);
    $lookup->shouldReceive('lookupShow')->atLeast()->once()->andReturn([
        'title' => 'Spotify podcast',
        'description_markdown' => 'Podcast description',
        'thumbnail' => $imageUrl,
    ]);
    app()->instance(EpisodeSpotifyLookup::class, $lookup);
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')->once()->with($imageUrl)->andReturn(spotifyAcquisitionPng());
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    $create = Livewire::test(CreateContentGroup::class)
        ->set('data.spotify_show', 'spotify:show:show12345')
        ->callAction(TestAction::make('fetchSpotifyShow')->schemaComponent('spotify_show', 'form'), data: [
            'overwrite_non_empty_fields' => false,
        ])
        ->assertSet('data.title', 'Spotify podcast')
        ->assertSet('data.description_markdown', 'Podcast description');

    expect($create->get('data.cover_media_reference_key'))->toBeString()
        ->and(Media::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1);

    $group = ContentGroup::factory()->create(['status' => PublicationStatus::Draft]);
    Livewire::test(EditContentGroup::class, ['record' => $group->getRouteKey()])
        ->assertActionExists(TestAction::make('fetchSpotifyShow')->schemaComponent('spotify_show', 'form'));
});

it('keeps Spotify metadata while presenting a safe categorized image failure', function (): void {
    $imageUrl = 'https://cdn.example.test/spotify-episode.png';
    $unsafeDetails = 'connection failed at 10.0.0.1 with token=secret';
    fakeSpotifyEpisodeLookup($imageUrl);
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')->once()->with($imageUrl)->andThrow(
        new ExternalImageUnavailableException(
            ExternalImageFailureReason::TemporarilyUnavailable,
            $unsafeDetails,
        ),
    );
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    Livewire::test(CreateEpisodeWorkspace::class)
        ->set('data.spotify_episode', 'spotify:episode:episode12345')
        ->callAction(TestAction::make('fetchSpotifyEpisode')->schemaComponent('spotify_episode', 'form'), data: [
            'fill_slug_when_empty' => true,
            'fill_title_prefix_when_empty' => true,
            'link_matched_podcast' => false,
            'overwrite_non_empty_fields' => false,
        ])
        ->assertSet('data.title', 'Spotify episode')
        ->assertSet('data.primary_image_media_reference_key', null)
        ->assertNotified(
            Notification::make()
                ->warning()
                ->title(__('admin.notifications.spotify_image_acquisition_failed'))
                ->body(__('admin.media_library.url_failure_temporarily_unavailable')),
        )
        ->assertDontSee($unsafeDetails);
});

it('rethrows authorization failures from Spotify show image acquisition', function (): void {
    $lookup = Mockery::mock(EpisodeSpotifyLookup::class);
    $lookup->shouldReceive('lookupShow')->once()->andReturn([
        'title' => 'Authorized podcast title',
        'description_markdown' => 'Description',
        'thumbnail' => 'https://cdn.example.test/spotify-show.png',
    ]);
    app()->instance(EpisodeSpotifyLookup::class, $lookup);
    $acquisitions = Mockery::mock(MediaAcquisitionManager::class);
    $acquisitions->shouldReceive('acquireExternalUrl')
        ->once()
        ->andThrow(new AuthorizationException('show artwork denied'));
    app()->instance(MediaAcquisitionManager::class, $acquisitions);
    $get = Mockery::mock(Get::class);
    $get->shouldReceive('__invoke')->zeroOrMoreTimes()->andReturnUsing(
        fn (string $field): mixed => $field === 'spotify_show'
            ? 'spotify:show:show12345'
            : null,
    );
    $set = Mockery::mock(Set::class);
    $set->shouldReceive('__invoke')->zeroOrMoreTimes()->andReturnNull();
    $method = new ReflectionMethod(SpotifyShowInput::class, 'fetch');

    expect(fn () => $method->invoke(null, $set, $get, [
        'overwrite_non_empty_fields' => false,
    ]))->toThrow(AuthorizationException::class, 'show artwork denied');
});

it('rethrows authorization failures from Spotify episode image acquisition', function (): void {
    $lookup = Mockery::mock(EpisodeSpotifyLookup::class);
    $lookup->shouldReceive('lookup')->once()->andReturn([
        'title' => 'Authorized episode title',
        'external_thumbnail_url' => 'https://cdn.example.test/spotify-episode.png',
    ]);
    app()->instance(EpisodeSpotifyLookup::class, $lookup);
    $acquisitions = Mockery::mock(MediaAcquisitionManager::class);
    $acquisitions->shouldReceive('acquireExternalUrl')
        ->once()
        ->andThrow(new AuthorizationException('episode artwork denied'));
    app()->instance(MediaAcquisitionManager::class, $acquisitions);
    $get = Mockery::mock(Get::class);
    $get->shouldReceive('__invoke')->zeroOrMoreTimes()->andReturnUsing(
        fn (string $field): mixed => $field === 'spotify_episode'
            ? 'spotify:episode:episode12345'
            : null,
    );
    $set = Mockery::mock(Set::class);
    $set->shouldReceive('__invoke')->zeroOrMoreTimes()->andReturnNull();
    $method = new ReflectionMethod(EpisodeWorkspaceForm::class, 'fetchSpotifyEpisode');

    expect(fn () => $method->invoke(null, $set, $get, [
        'fill_slug_when_empty' => true,
        'fill_title_prefix_when_empty' => true,
        'link_matched_podcast' => false,
        'overwrite_non_empty_fields' => false,
    ]))->toThrow(AuthorizationException::class, 'episode artwork denied');
});
