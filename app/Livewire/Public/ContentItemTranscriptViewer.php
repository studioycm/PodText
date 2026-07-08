<?php

namespace App\Livewire\Public;

use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicContent\PublicTranscriptionSelector;
use App\Support\Transcripts\TranscriptSegmentParser;
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

        return view('livewire.public.content-item-transcript-viewer', [
            'activeTranscription' => $selectedTranscription,
            'readingMinutes' => $selectedTranscription ? $this->readingMinutes($selectedTranscription) : null,
            'segments' => $selectedTranscription ? $parser->parse($selectedTranscription->transcript_markdown) : [],
            'transcriptions' => $transcriptions,
            'wordCount' => $selectedTranscription ? $this->wordCount($selectedTranscription) : 0,
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

    protected function readingMinutes(Transcription $transcription): int
    {
        return max(1, (int) ceil($this->wordCount($transcription) / 200));
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
}
