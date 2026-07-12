<?php

namespace App\Filament\Pages;

use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Filament\Imports\ContentGroupImporter;
use App\Filament\Imports\ContentItemImporter;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\ContentGroup;
use App\Models\ImportConnection;
use App\Support\Importer\ImporterThrottle;
use App\Support\Importer\SpotifyLinks\ImporterCsvBuilder;
use App\Support\Importer\SpotifyLinks\SpotifyEntityMode;
use App\Support\Importer\SpotifyLinks\SpotifyHtmlToMarkdown;
use App\Support\Importer\SpotifyLinks\SpotifyLinkParser;
use App\Support\Importer\SpotifyLinks\SpotifyOEmbedClient;
use App\Support\Media\EpisodeSpotifyLookup;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SpotifyLinksFetcher extends Page
{
    use UsesAdminNavigationOrder;
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMusicalNote;

    protected static ?string $slug = 'spotify-links-fetcher';

    protected string $view = 'filament.pages.spotify-links-fetcher';

    public string $linksInput = '';

    public string $entityMode = 'episodes';

    public int $batchCap = 25;

    public ?int $connectionId = null;

    public ?TemporaryUploadedFile $csvUpload = null;

    /**
     * @var array<int, array{type: string, id: string, input: string, url: string}>
     */
    public array $parsedLinks = [];

    /**
     * @var array<int, string>
     */
    public array $warnings = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $podcastRows = [];

    public bool $usedReducedMode = false;

    public static function getNavigationLabel(): string
    {
        return __('admin.spotify_fetcher.pages.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.spotify_fetcher.pages.title');
    }

    public function mount(): void
    {
        $this->connectionId = array_key_first($this->spotifyConnections());
    }

    public function parseLinks(): void
    {
        $this->batchCap = $this->normalizedBatchCap();
        $this->validate([
            'batchCap' => ['integer', 'min:1', 'max:100'],
            'csvUpload' => ['nullable', 'file', 'mimes:csv,txt', 'max:512'],
            'entityMode' => ['required', 'in:episodes,shows'],
        ]);

        $parsed = app(SpotifyLinkParser::class)->parse(
            $this->linksInput,
            SpotifyEntityMode::fromInput($this->entityMode),
            $this->batchCap,
            $this->csvUpload,
        );

        $this->parsedLinks = $parsed['items'];
        $this->warnings = $parsed['warnings'];

        if ($this->parsedLinks === []) {
            $this->warnings[] = __('admin.spotify_fetcher.warnings.no_valid_links');
        }
    }

    public function fetch(): void
    {
        $this->parseLinks();

        $this->rows = [];
        $this->podcastRows = [];
        $this->usedReducedMode = false;

        if ($this->parsedLinks === []) {
            return;
        }

        $connection = $this->selectedConnection();

        if (! $connection instanceof ImportConnection) {
            $this->usedReducedMode = true;
            $this->warnings[] = __('admin.spotify_fetcher.warnings.reduced_without_connection');
        }

        foreach ($this->parsedLinks as $index => $item) {
            app(ImporterThrottle::class)->wait('spotify.links_fetcher', $index + 1);

            if ($item['type'] === 'show') {
                $this->fetchShow($item, $connection);

                continue;
            }

            $this->fetchEpisode($item, $connection);
        }

        Notification::make()
            ->success()
            ->title(__('admin.spotify_fetcher.notifications.fetch_complete'))
            ->send();
    }

    public function downloadEpisodesCsv(): StreamedResponse
    {
        return $this->downloadCsv(
            __('admin.spotify_fetcher.files.episodes'),
            app(ImporterCsvBuilder::class)->headersFor(ContentItemImporter::class),
            $this->episodeCsvRows(),
        );
    }

    public function downloadPodcastsCsv(): StreamedResponse
    {
        return $this->downloadCsv(
            __('admin.spotify_fetcher.files.podcasts'),
            app(ImporterCsvBuilder::class)->headersFor(ContentGroupImporter::class),
            $this->podcastCsvRows(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function spotifyConnections(): array
    {
        return ImportConnection::query()
            ->where('provider', ImportConnectionProvider::Spotify)
            ->where('status', ImportConnectionStatus::Connected)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function episodeCsvRows(): array
    {
        return collect($this->rows)
            ->filter(fn (array $row): bool => ($row['type'] ?? null) === 'episode' && ($row['status'] ?? null) !== 'error')
            ->map(fn (array $row): array => $this->contentItemImportRow($row))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function podcastCsvRows(): array
    {
        return collect($this->podcastRows)
            ->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'error')
            ->map(fn (array $row): array => $this->contentGroupImportRow($row))
            ->values()
            ->all();
    }

    private function fetchEpisode(array $item, ?ImportConnection $connection): void
    {
        try {
            if (! $connection instanceof ImportConnection) {
                $this->rows[] = $this->reducedRow($item);

                return;
            }

            $lookup = app(EpisodeSpotifyLookup::class)->lookup($item['url'], $connection);
            $showId = (string) data_get($lookup, 'media_metadata.show_id');
            $showName = (string) ($lookup['title_prefix'] ?? '');
            $show = $this->showData($showId, $connection, $showName);
            $group = $this->resolveContentGroup($showId, $showName);

            $this->rows[] = $this->episodeRow($item, $lookup, $show, $group, 'fetched');

            if (! $group instanceof ContentGroup && filled($show['external_id'] ?? null)) {
                $this->addPodcastRow($show, 'fetched');
            }
        } catch (Throwable $exception) {
            $this->rows[] = $this->fallbackOrErrorRow($item, $exception);
        }
    }

    private function fetchShow(array $item, ?ImportConnection $connection): void
    {
        try {
            if (! $connection instanceof ImportConnection) {
                $row = $this->reducedRow($item);
                $this->rows[] = $row;
                $this->addPodcastRow($row, 'reduced');

                return;
            }

            $show = app(EpisodeSpotifyLookup::class)->lookupShow($item['url'], $connection);
            $row = $this->showRow($item, $show, 'fetched');

            $this->rows[] = $row;
            $this->addPodcastRow($row, 'fetched');
        } catch (Throwable $exception) {
            $this->rows[] = $this->fallbackOrErrorRow($item, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function showData(string $showId, ?ImportConnection $connection, string $fallbackName): array
    {
        if ($showId === '' || ! $connection instanceof ImportConnection) {
            return [
                'external_id' => $showId,
                'title' => $fallbackName,
            ];
        }

        try {
            return app(EpisodeSpotifyLookup::class)->lookupShow($showId, $connection);
        } catch (Throwable) {
            return [
                'external_id' => $showId,
                'title' => $fallbackName,
            ];
        }
    }

    private function selectedConnection(): ?ImportConnection
    {
        if (! $this->connectionId) {
            return null;
        }

        return ImportConnection::query()
            ->whereKey($this->connectionId)
            ->where('provider', ImportConnectionProvider::Spotify)
            ->where('status', ImportConnectionStatus::Connected)
            ->first();
    }

    private function resolveContentGroup(string $showId, string $showName): ?ContentGroup
    {
        if ($showId !== '') {
            $group = ContentGroup::query()
                ->whereHas('contentItems', fn ($query): mixed => $query->where('media_metadata->show_id', $showId))
                ->first();

            if ($group instanceof ContentGroup) {
                return $group;
            }
        }

        if ($showName === '') {
            return null;
        }

        return ContentGroup::query()
            ->where('title', $showName)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function episodeRow(array $item, array $lookup, array $show, ?ContentGroup $group, string $status): array
    {
        $releaseDate = $this->dateString($lookup['original_published_at'] ?? null);
        $description = app(SpotifyHtmlToMarkdown::class)->convert((string) data_get($lookup, 'media_metadata.html_description'));

        if ($description === '') {
            $description = (string) ($lookup['external_description'] ?? '');
        }

        return [
            'content_group_reference_key' => $group?->reference_key ?? '',
            'description_markdown' => $description,
            'duration_seconds' => $lookup['duration_seconds'] ?? '',
            'embed_url' => $lookup['embed_url'] ?? '',
            'external_description' => $lookup['external_description'] ?? '',
            'external_id' => $lookup['external_id'] ?? $item['id'],
            'external_thumbnail_url' => $lookup['external_thumbnail_url'] ?? '',
            'input' => $item['input'],
            'media_url' => $lookup['media_url'] ?? $item['url'],
            'reason' => '',
            'reduced' => false,
            'release_date' => $releaseDate,
            'show_id' => data_get($lookup, 'media_metadata.show_id') ?: ($show['external_id'] ?? ''),
            'show_name' => $show['title'] ?? $lookup['title_prefix'] ?? '',
            'status' => $status,
            'status_label' => __("admin.spotify_fetcher.statuses.{$status}"),
            'title' => $lookup['title'] ?? $lookup['external_title'] ?? $item['id'],
            'title_prefix' => $lookup['title_prefix'] ?? $show['title'] ?? '',
            'type' => 'episode',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function showRow(array $item, array $show, string $status): array
    {
        return [
            'content_group_reference_key' => '',
            'description_markdown' => app(SpotifyHtmlToMarkdown::class)->convert((string) ($show['html_description'] ?? ''))
                ?: (string) ($show['description'] ?? ''),
            'duration_seconds' => '',
            'embed_url' => '',
            'external_description' => $show['description'] ?? '',
            'external_id' => $show['external_id'] ?? $item['id'],
            'external_thumbnail_url' => $show['thumbnail'] ?? '',
            'input' => $item['input'],
            'media_url' => $show['external_url'] ?? $item['url'],
            'reason' => '',
            'reduced' => $status === 'reduced',
            'release_date' => '',
            'show_id' => $show['external_id'] ?? $item['id'],
            'show_name' => $show['title'] ?? $item['id'],
            'status' => $status,
            'status_label' => __("admin.spotify_fetcher.statuses.{$status}"),
            'title' => $show['title'] ?? $item['id'],
            'title_prefix' => '',
            'type' => 'show',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reducedRow(array $item): array
    {
        $this->usedReducedMode = true;
        $embed = app(SpotifyOEmbedClient::class)->fetch($item['url']);

        return [
            'content_group_reference_key' => '',
            'description_markdown' => '',
            'duration_seconds' => '',
            'embed_url' => '',
            'external_description' => '',
            'external_id' => $item['id'],
            'external_thumbnail_url' => $embed['thumbnail'] ?? '',
            'input' => $item['input'],
            'media_url' => $item['url'],
            'reason' => __('admin.spotify_fetcher.reduced_reason'),
            'reduced' => true,
            'release_date' => '',
            'show_id' => $item['type'] === 'show' ? $item['id'] : '',
            'show_name' => $item['type'] === 'show' ? ($embed['title'] ?? $item['id']) : '',
            'status' => 'reduced',
            'status_label' => __('admin.spotify_fetcher.statuses.reduced'),
            'title' => $embed['title'] ?? $item['id'],
            'title_prefix' => '',
            'type' => $item['type'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackOrErrorRow(array $item, Throwable $exception): array
    {
        try {
            return $this->reducedRow($item);
        } catch (Throwable) {
            return [
                'content_group_reference_key' => '',
                'description_markdown' => '',
                'duration_seconds' => '',
                'embed_url' => '',
                'external_description' => '',
                'external_id' => $item['id'],
                'external_thumbnail_url' => '',
                'input' => $item['input'],
                'media_url' => $item['url'],
                'reason' => $exception->getMessage(),
                'reduced' => false,
                'release_date' => '',
                'show_id' => $item['type'] === 'show' ? $item['id'] : '',
                'show_name' => '',
                'status' => 'error',
                'status_label' => __('admin.spotify_fetcher.statuses.error'),
                'title' => $item['id'],
                'title_prefix' => '',
                'type' => $item['type'],
            ];
        }
    }

    private function addPodcastRow(array $show, string $status): void
    {
        $key = (string) ($show['show_id'] ?? $show['external_id'] ?? $show['title'] ?? $show['show_name'] ?? '');

        if ($key === '') {
            return;
        }

        $exists = collect($this->podcastRows)
            ->contains(fn (array $row): bool => (string) ($row['external_id'] ?? $row['show_id'] ?? $row['title'] ?? '') === $key);

        if ($exists) {
            return;
        }

        $this->podcastRows[] = [
            'description_markdown' => app(SpotifyHtmlToMarkdown::class)->convert((string) ($show['html_description'] ?? ''))
                ?: (string) ($show['description_markdown'] ?? $show['description'] ?? ''),
            'external_id' => $show['external_id'] ?? $show['show_id'] ?? '',
            'external_thumbnail_url' => $show['thumbnail'] ?? $show['external_thumbnail_url'] ?? '',
            'media_url' => $show['external_url'] ?? $show['media_url'] ?? '',
            'reason' => $status === 'reduced' ? __('admin.spotify_fetcher.reduced_reason') : '',
            'show_id' => $show['external_id'] ?? $show['show_id'] ?? '',
            'status' => $status,
            'status_label' => __("admin.spotify_fetcher.statuses.{$status}"),
            'title' => $show['title'] ?? $show['show_name'] ?? $key,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contentItemImportRow(array $row): array
    {
        $metadata = array_filter([
            'episode_id' => $row['external_id'] ?? null,
            'provider' => 'spotify',
            'reduced' => (bool) ($row['reduced'] ?? false),
            'show' => $row['show_name'] ?? null,
            'show_id' => $row['show_id'] ?? null,
            'source_input' => $row['input'] ?? null,
        ], fn (mixed $value): bool => filled($value) || is_bool($value));

        return [
            'category_paths' => '',
            'content_group_reference_key' => $row['content_group_reference_key'] ?? '',
            'content_tag_slugs' => '',
            'description_markdown' => $row['description_markdown'] ?? '',
            'direct_media_url' => '',
            'duration_seconds' => $row['duration_seconds'] ?? '',
            'embed_provider' => 'spotify',
            'embed_url' => $row['embed_url'] ?? '',
            'external_description' => $row['external_description'] ?? '',
            'external_id' => $row['external_id'] ?? '',
            'external_published_at' => $row['release_date'] ?? '',
            'external_thumbnail_url' => $row['external_thumbnail_url'] ?? '',
            'external_title' => $row['title'] ?? '',
            'featured_transcription_reference_key' => '',
            'is_pinned' => '',
            'media_duration_seconds' => $row['duration_seconds'] ?? '',
            'media_metadata' => $metadata,
            'media_url' => $row['media_url'] ?? '',
            'original_published_at' => $row['release_date'] ?? '',
            'pin_order' => '',
            'pinned_at' => '',
            'pinned_until' => '',
            'published_at' => '',
            'reference_key' => '',
            'slug' => '',
            'status' => 'draft',
            'title' => $row['title'] ?? '',
            'type_label_singular_override' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contentGroupImportRow(array $row): array
    {
        return [
            'category_paths' => '',
            'default_item_type_label_plural' => 'Episodes',
            'default_item_type_label_singular' => 'Episode',
            'description_markdown' => $row['description_markdown'] ?? '',
            'group_type_label_plural' => 'Podcasts',
            'group_type_label_singular' => 'Podcast',
            'homepage_order' => '',
            'original_language_code' => 'he',
            'published_at' => '',
            'reference_key' => '',
            'slug' => '',
            'status' => 'draft',
            'title' => $row['title'] ?? '',
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function downloadCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(
            fn (): int|false => print "\xEF\xBB\xBF".app(ImporterCsvBuilder::class)->csv($headers, $rows),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function normalizedBatchCap(): int
    {
        return max(1, min(100, (int) $this->batchCap));
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return app(ImporterCsvBuilder::class)->importDate($value);
        }

        if (blank($value)) {
            return '';
        }

        return app(ImporterCsvBuilder::class)->importDate(Carbon::parse((string) $value, 'Asia/Jerusalem'));
    }
}
