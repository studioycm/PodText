<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\Transcription;
use App\Support\PublicContent\PublicContentItemQueries;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicFront\ItemPage\PublicItemPageRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

class ShowContentItem extends Page
{
    use HidesPublicPageHeader;

    public ContentGroup $contentGroup;

    public ContentItem $contentItem;

    protected static ?string $slug = 'items/{contentGroupSlug}/{contentItemSlug}';

    protected string $view = 'filament.public.pages.show-content-item';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $itemPageConfig = null;

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'items.show';
    }

    public function mount(string $contentGroupSlug, string $contentItemSlug): void
    {
        $this->contentGroup = ContentGroup::query()
            ->published()
            ->where('slug', $contentGroupSlug)
            ->firstOrFail();

        $this->contentItem = PublicContentItemQueries::base()
            ->whereBelongsTo($this->contentGroup)
            ->where('slug', $contentItemSlug)
            ->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->contentItem->title;
    }

    /**
     * @return array<string, mixed>
     */
    public function itemPageConfig(): array
    {
        return $this->itemPageConfig ??= app(PublicFrontRenderContext::class)->itemPage();
    }

    public function showBreadcrumbs(): bool
    {
        return (bool) ($this->itemPageConfig()['show_breadcrumbs'] ?? true);
    }

    /**
     * @return array{url: string, source: string}|null
     */
    public function pageImage(): ?array
    {
        if (filled($this->contentItem->external_thumbnail_url)) {
            return [
                'url' => (string) $this->contentItem->external_thumbnail_url,
                'source' => 'item',
            ];
        }

        if (filled($this->contentGroup->cover_path)) {
            return [
                'url' => Storage::disk('public')->url($this->contentGroup->cover_path),
                'source' => 'group',
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function podcastIdentity(): ?array
    {
        $identity = $this->itemPageConfig()['podcast_identity'] ?? [];
        $mode = $identity['mode'] ?? 'badge';

        if ($mode === 'hidden') {
            return null;
        }

        return [
            'mode' => $mode,
            'label' => $this->contentGroup->title,
            'url' => ShowContentGroup::getUrl(['contentGroupSlug' => $this->contentGroup->slug], panel: 'public'),
            'icon' => $identity['icon'] ?? 'podcast',
            'icon_position' => $identity['icon_position'] ?? 'inline_before',
            'class' => $mode === 'badge'
                ? $this->badgeClass($identity['size'] ?? 'sm', $identity['color'] ?? 'primary')
                : 'inline-flex items-center gap-1.5 text-sm font-medium text-primary-700 hover:text-primary-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-primary-300 dark:hover:text-primary-100',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function infoParts(): array
    {
        $config = $this->itemPageConfig();
        $infoFields = $config['info_fields'] ?? PublicItemPageRegistry::defaultInfoFields();
        $dates = $config['dates'] ?? [];

        return collect($infoFields)
            ->filter(fn (mixed $field): bool => is_array($field))
            ->values()
            ->map(function (array $field, int $index) use ($dates): ?array {
                $key = (string) ($field['field'] ?? '');

                if (! in_array($key, PublicItemPageRegistry::infoFields(), true) || ! $this->shouldShowInfoField($key, $dates)) {
                    return null;
                }

                $value = $this->infoFieldValue($key);

                if ($value === null || $value === [] || $value === '') {
                    return null;
                }

                $presentation = $this->infoFieldPresentation($key, $field, $dates);

                return [
                    'key' => $key,
                    'value' => $value,
                    'part' => [
                        'type' => 'item_page_info',
                        'source' => 'content_item',
                        'attribute' => $key,
                        'order' => ($index + 1) * 10,
                        'label' => $presentation['label'],
                        'label_position' => filled($presentation['label']) ? 'inline_before' : 'hidden',
                        'label_alignment' => 'start',
                        'icon' => $presentation['icon'],
                        'icon_position' => $presentation['icon_position'],
                    ],
                    'class' => $this->badgeClass($field['size'] ?? 'sm', $field['color'] ?? 'gray'),
                    'data_test' => "item-info-{$key}",
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function mediaDurationSeconds(): ?int
    {
        $seconds = $this->contentItem->media_duration_seconds ?: $this->contentItem->duration_seconds;

        return $seconds ? (int) $seconds : null;
    }

    private function effectiveTranscription(): ?Transcription
    {
        $featuredTranscription = $this->contentItem->relationLoaded('featuredTranscription')
            ? $this->contentItem->featuredTranscription
            : null;

        if ($featuredTranscription?->content_item_id === $this->contentItem->getKey() && $featuredTranscription->isPublished()) {
            return $featuredTranscription;
        }

        if ($this->contentItem->relationLoaded('latestPublishedTranscription')) {
            return $this->contentItem->latestPublishedTranscription;
        }

        return $this->contentItem->effectiveTranscription();
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function categoryLinks(): array
    {
        return $this->contentItem
            ->effectiveCategories()
            ->where('is_visible', true)
            ->values()
            ->map(fn (Category $category): array => [
                'label' => (string) $category->name,
                'url' => BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public'),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function tagLinks(): array
    {
        $tags = $this->contentItem->relationLoaded('enabledContentTags')
            ? $this->contentItem->enabledContentTags
            : collect();

        return $tags
            ->map(fn (ContentTag $tag): array => [
                'label' => (string) $tag->name,
                'url' => BrowseTagContentItems::getUrl(['tagSlug' => $tag->slug], panel: 'public'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function transcriberLinks(): array
    {
        $transcription = $this->effectiveTranscription();
        $authors = $transcription?->relationLoaded('authors') ? $transcription->authors : collect();

        return $authors
            ->map(fn (Author $author): array => [
                'label' => (string) $author->name,
                'url' => ShowContributor::getUrl(['authorSlug' => $author->slug], panel: 'public'),
            ])
            ->values()
            ->all();
    }

    private function shouldShowInfoField(string $key, array $dates): bool
    {
        $display = $dates['display'] ?? 'both';

        return match ($key) {
            'site_published_date' => in_array($display, ['site', 'both'], true),
            'original_published_date' => in_array($display, ['original', 'both'], true),
            'transcription_date' => (bool) ($dates['transcription_date']['enabled'] ?? true),
            default => true,
        };
    }

    private function infoFieldValue(string $key): mixed
    {
        $transcription = $this->effectiveTranscription();

        return match ($key) {
            'site_published_date' => $this->formatDate($this->contentItem->published_at),
            'original_published_date' => $this->formatDate($this->contentItem->original_published_at),
            'transcription_date' => $this->formatDate($transcription?->published_at),
            'duration' => $this->duration($this->contentItem->duration_seconds),
            'transcribers' => $this->transcriberLinks(),
            'reading_time' => $this->readingTime($transcription?->word_count),
            'word_count' => $transcription?->word_count
                ? trans_choice('public.labels.transcript_words_count', (int) $transcription->word_count, ['count' => (int) $transcription->word_count])
                : null,
            'transcription_count' => $this->transcriptionCountText(),
            'categories' => $this->categoryLinks(),
            'tags' => $this->tagLinks(),
            default => null,
        };
    }

    /**
     * @return array{label: ?string, icon: ?string, icon_position: string}
     */
    private function infoFieldPresentation(string $key, array $field, array $dates): array
    {
        $dateKey = match ($key) {
            'site_published_date' => 'site_published',
            'original_published_date' => 'original_published',
            'transcription_date' => 'transcription_date',
            default => null,
        };

        if ($dateKey !== null) {
            $dateConfig = $dates[$dateKey] ?? [];

            return [
                'label' => $this->labelFor($key, (string) ($dateConfig['label_mode'] ?? 'short'), $dateConfig['label_override'] ?? null),
                'icon' => $dateConfig['icon'] ?? $field['icon'] ?? 'calendar',
                'icon_position' => $dateConfig['icon_position'] ?? $field['icon_position'] ?? 'inline_before',
            ];
        }

        return [
            'label' => $this->labelFor($key, (string) ($field['label_mode'] ?? 'hidden'), $field['label_override'] ?? null),
            'icon' => $field['icon'] ?? 'document',
            'icon_position' => $field['icon_position'] ?? 'inline_before',
        ];
    }

    private function labelFor(string $key, string $mode, mixed $override): ?string
    {
        if ($mode === 'hidden') {
            return null;
        }

        if (is_string($override) && filled($override)) {
            return $override;
        }

        return match ($key) {
            'site_published_date' => __('public.dates.site_published_'.$mode),
            'original_published_date' => __('public.dates.original_published_'.$mode),
            'transcription_date' => __('public.dates.transcription_date_'.$mode),
            default => __("public.item_page.info_fields.{$key}_{$mode}"),
        };
    }

    private function transcriptionCountText(): ?string
    {
        $count = (int) ($this->contentItem->public_transcriptions_count ?? 0);

        if (! app(PublicTranscriptionPolicy::class)->countModeCountsAllPublished() || $count <= 1) {
            return null;
        }

        return trans_choice('public.labels.public_transcriptions_count', $count, ['count' => $count]);
    }

    private function duration(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        return gmdate($seconds >= 3600 ? 'H:i:s' : 'i:s', $seconds);
    }

    private function readingTime(?int $wordCount): ?string
    {
        if ($wordCount === null || $wordCount <= 0) {
            return null;
        }

        $minutes = max(1, (int) ceil($wordCount / 200));

        return trans_choice('public.labels.reading_minutes_count', $minutes, ['count' => $minutes]);
    }

    private function formatDate(mixed $date): ?string
    {
        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return $date->timezone('Asia/Jerusalem')->format('d/m/Y');
    }

    private function badgeClass(?string $size, ?string $color): string
    {
        return trim('inline-flex max-w-full items-center rounded-md border font-medium '.
            PublicItemPageRegistry::infoBadgeSizeClass($size).' '.
            PublicItemPageRegistry::infoBadgeColorClass($color));
    }
}
