<?php

namespace App\Livewire\Public;

use App\Filament\Public\Pages\ShowContributor;
use App\Models\Author;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicContent\PublicTranscriptionSelector;
use App\Support\PublicFront\ItemPage\PublicItemPageRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\Transcripts\TranscriptSegmentParser;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class ContentItemTranscriptViewer extends Component
{
    public ContentItem $contentItem;

    #[Url(as: 'transcription', except: '')]
    public string $selectedTranscription = '';

    public function mount(ContentItem $contentItem): void
    {
        $this->contentItem = $contentItem;
        $this->selectedTranscription = $this->normalizeSelectedTranscription($this->selectedTranscription);
    }

    public function selectTranscription(string $referenceKey): void
    {
        $this->selectedTranscription = $this->normalizeSelectedTranscription($referenceKey);
    }

    public function render(TranscriptSegmentParser $parser): View
    {
        $transcriptions = $this->publishedTranscriptions;
        $selectedTranscription = $this->resolveSelectedTranscription($transcriptions);
        $this->selectedTranscription = $selectedTranscription?->reference_key ?? '';
        $wordCount = $selectedTranscription ? $this->wordCount($selectedTranscription) : 0;
        $readingMinutes = $selectedTranscription ? max(1, (int) ceil($wordCount / 200)) : null;

        return view('livewire.public.content-item-transcript-viewer', [
            'activeTranscription' => $selectedTranscription,
            'details' => $selectedTranscription && $readingMinutes
                ? $this->transcriptDetails($selectedTranscription, $readingMinutes, $wordCount)
                : null,
            'readingMinutes' => $readingMinutes,
            'segments' => $selectedTranscription ? $parser->parse($selectedTranscription->transcript_markdown) : [],
            'showActionsMenu' => $this->showActionsMenu(),
            'transcriptions' => $transcriptions,
            'wordCount' => $wordCount,
        ]);
    }

    /**
     * @return Collection<int, Transcription>
     */
    #[Computed]
    public function publishedTranscriptions(): Collection
    {
        $policy = app(PublicTranscriptionPolicy::class);
        $mode = $policy->showMultipleTranscriptionsOnItemPage
            ? $policy->modeForPublicDisplay()
            : PublicTranscriptionPolicy::MODE_FEATURED_ONLY;

        return app(PublicTranscriptionSelector::class)
            ->publicTranscriptionsForItem($this->contentItem, $mode);
    }

    /**
     * @param  Collection<int, Transcription>  $transcriptions
     */
    protected function resolveSelectedTranscription(Collection $transcriptions): ?Transcription
    {
        if ($transcriptions->isEmpty()) {
            return null;
        }

        if (filled($this->selectedTranscription)) {
            $selected = $transcriptions->firstWhere('reference_key', $this->selectedTranscription);

            if ($selected instanceof Transcription) {
                return $selected;
            }
        }

        return $transcriptions->first();
    }

    protected function normalizeSelectedTranscription(string $referenceKey): string
    {
        $transcriptions = $this->publishedTranscriptions;

        if (filled($referenceKey)) {
            $selected = $transcriptions->firstWhere('reference_key', $referenceKey);

            if ($selected instanceof Transcription) {
                return $selected->reference_key;
            }
        }

        return $transcriptions->first()?->reference_key ?? '';
    }

    protected function wordCount(Transcription $transcription): int
    {
        if ($transcription->word_count !== null && $transcription->word_count > 0) {
            return $transcription->word_count;
        }

        $plainText = str((string) $transcription->transcript_markdown)
            ->stripTags()
            ->replaceMatches('/[\\[\\]()`*_#>:-]+/u', ' ')
            ->squish()
            ->toString();

        if ($plainText === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $plainText, flags: PREG_SPLIT_NO_EMPTY) ?: []);
    }

    protected function showActionsMenu(): bool
    {
        return (bool) (app(PublicFrontRenderContext::class)->itemPage()['show_transcript_actions_menu'] ?? false);
    }

    /**
     * @return array{
     *     title: ?string,
     *     reading_time: string,
     *     word_count: string,
     *     published_at: ?string,
     *     published_part: ?array<string, mixed>,
     *     published_class: string,
     *     transcribers: array<int, array{label: string, url: string}>
     * }
     */
    protected function transcriptDetails(Transcription $transcription, int $readingMinutes, int $wordCount): array
    {
        $dateConfig = app(PublicFrontRenderContext::class)->itemPage()['dates']['transcription_date'] ?? [];
        $publishedAt = $this->formatDate($transcription->published_at);
        $publishedLabel = $this->dateLabel((string) ($dateConfig['label_mode'] ?? 'short'), $dateConfig['label_override'] ?? null);

        return [
            'title' => filled($transcription->title) ? (string) $transcription->title : null,
            'reading_time' => trans_choice('public.labels.reading_minutes_count', $readingMinutes, ['count' => $readingMinutes]),
            'word_count' => trans_choice('public.labels.transcript_words_count', $wordCount, ['count' => $wordCount]),
            'published_at' => $publishedAt,
            'published_part' => $publishedAt ? [
                'type' => 'transcript_detail',
                'source' => 'transcription',
                'attribute' => 'published_at',
                'order' => 30,
                'label' => $publishedLabel,
                'label_position' => filled($publishedLabel) ? 'inline_before' : 'hidden',
                'label_alignment' => 'start',
                'icon' => $dateConfig['icon'] ?? 'document',
                'icon_position' => $dateConfig['icon_position'] ?? 'inline_before',
            ] : null,
            'published_class' => $this->detailBadgeClass(),
            'transcribers' => $this->transcriberLinks($transcription),
        ];
    }

    private function dateLabel(string $mode, mixed $override): ?string
    {
        if ($mode === 'hidden') {
            return null;
        }

        if (is_string($override) && filled($override)) {
            return $override;
        }

        return __('public.dates.transcription_date_'.$mode);
    }

    private function formatDate(mixed $date): ?string
    {
        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return $date->timezone('Asia/Jerusalem')->format('d/m/Y');
    }

    private function detailBadgeClass(): string
    {
        return trim('inline-flex max-w-full items-center rounded-md border font-medium '.
            PublicItemPageRegistry::infoBadgeSizeClass('sm').' '.
            PublicItemPageRegistry::infoBadgeColorClass('gray'));
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function transcriberLinks(Transcription $transcription): array
    {
        $authors = $transcription->relationLoaded('authors') ? $transcription->authors : collect();

        return $authors
            ->map(fn (Author $author): array => [
                'label' => (string) $author->name,
                'url' => ShowContributor::getUrl(['authorSlug' => $author->slug], panel: 'public'),
            ])
            ->values()
            ->all();
    }
}
