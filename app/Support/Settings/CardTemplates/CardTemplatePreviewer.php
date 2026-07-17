<?php

namespace App\Support\Settings\CardTemplates;

use App\Filament\Public\Pages\ShowContributor;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Support\PublicContent\PublicContentItemQueries;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicContent\PublicTranscriptionAggregates;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicContent\PublicTranscriptionSelector;
use App\Support\PublicFront\Cards\PublicContentGroupCardPresenter;
use App\Support\PublicFront\Cards\PublicContentItemCardPresenter;
use App\Support\PublicFront\Cards\PublicContributorCardPresenter;
use App\Support\PublicFront\Cards\PublicFrontCardTemplate;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\ContentItemDisplayTitle;
use App\Support\PublicFront\Groups\PublicContentGroupQueries;
use App\Support\PublicFront\PublicDefaultImageResolver;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigResult;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\ComponentAttributeBag;

class CardTemplatePreviewer
{
    public const SAMPLE_LIMIT = 50;

    public function __construct(
        private readonly CardTemplateDraftNormalizer $normalizer,
        private readonly ContentItemDisplayTitle $displayTitle,
    ) {}

    /**
     * @param  array<string, mixed>  $draft
     * @return array{family: string, sample_id: int, sample_label: string, html: string}
     */
    public function preview(array $draft, ?int $sampleId = null): array
    {
        $normalized = $this->normalizer->normalizeCandidate($this->normalizer->candidate($draft));
        $template = PublicFrontCardTemplate::fromArray($normalized);
        $services = $this->services();
        $sample = $this->sample(
            $template->family,
            $sampleId,
            $services['aggregates'],
            $services['selector'],
        );

        if (! $sample) {
            throw CardTemplateWriteException::named('preview_sample_missing');
        }

        return [
            'family' => $template->family,
            'sample_id' => (int) $sample->getKey(),
            'sample_label' => $this->label($sample),
            'html' => $this->render($sample, $template, $services),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function sampleOptions(string $family, string $search): array
    {
        $services = $this->queryServices();

        return $this->sampleQuery($family, $services['aggregates'], $services['selector'], $search)
            ->limit(self::SAMPLE_LIMIT)
            ->get()
            ->mapWithKeys(fn (Author|ContentGroup|ContentItem $sample): array => [
                (int) $sample->getKey() => $this->label($sample),
            ])
            ->all();
    }

    public function sampleLabel(string $family, int $sampleId): ?string
    {
        $services = $this->queryServices();
        $sample = $this->sampleQuery($family, $services['aggregates'], $services['selector'])
            ->whereKey($sampleId)
            ->first();

        return $sample ? $this->label($sample) : null;
    }

    /**
     * @return array{
     *     renderer: PublicFrontCardTemplateRenderer,
     *     context: PublicFrontRenderContext,
     *     selector: PublicTranscriptionSelector,
     *     policy: PublicTranscriptionPolicy,
     *     aggregates: PublicTranscriptionAggregates,
     *     default_images: PublicDefaultImageResolver
     * }
     */
    private function services(): array
    {
        $context = new PublicFrontRenderContext(
            new PublicFrontConfigResult(PublicFrontConfigRegistry::defaults()),
        );
        $queryServices = $this->queryServices();

        return [
            'renderer' => new PublicFrontCardTemplateRenderer(new PublicFrontCardTemplateResolver($context)),
            'context' => $context,
            ...$queryServices,
            'default_images' => new PublicDefaultImageResolver($context),
        ];
    }

    /**
     * @return array{
     *     selector: PublicTranscriptionSelector,
     *     policy: PublicTranscriptionPolicy,
     *     aggregates: PublicTranscriptionAggregates
     * }
     */
    private function queryServices(): array
    {
        $policy = PublicTranscriptionPolicy::fromConfig(
            PublicFrontConfigRegistry::defaults()['transcription_policy'],
            multiMode: false,
        );
        $selector = new PublicTranscriptionSelector($policy);

        return [
            'selector' => $selector,
            'policy' => $policy,
            'aggregates' => new PublicTranscriptionAggregates($policy, $selector),
        ];
    }

    private function sample(
        string $family,
        ?int $sampleId,
        PublicTranscriptionAggregates $aggregates,
        PublicTranscriptionSelector $selector,
    ): Author|ContentGroup|ContentItem|null {
        $query = $this->sampleQuery($family, $aggregates, $selector);

        if ($sampleId !== null) {
            return $query->whereKey($sampleId)->first();
        }

        return $query->first();
    }

    private function sampleQuery(
        string $family,
        PublicTranscriptionAggregates $aggregates,
        PublicTranscriptionSelector $selector,
        string $search = '',
    ): Builder {
        $search = trim($search);

        return match ($family) {
            PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY => $this->contentItemQuery(
                $aggregates,
                $selector,
                $search,
            ),
            PublicFrontCardTemplateRegistry::CONTENT_GROUP_FAMILY => $this->contentGroupQuery(
                $aggregates,
                $search,
            ),
            PublicFrontCardTemplateRegistry::CONTRIBUTOR_FAMILY => PublicContributorDiscovery::contributors(
                search: $search,
                aggregates: $aggregates,
            ),
            default => throw CardTemplateWriteException::named('validation'),
        };
    }

    private function contentItemQuery(
        PublicTranscriptionAggregates $aggregates,
        PublicTranscriptionSelector $selector,
        string $search,
    ): Builder {
        $query = PublicContentItemQueries::base($aggregates, $selector);

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function (Builder $query) use ($like): void {
                $query
                    ->where('title', 'like', $like)
                    ->orWhereHas('contentGroup', fn (Builder $query): Builder => $query->where('title', 'like', $like));
            });
        }

        return $query->orderByEffectiveTranscriptionPublishedAt();
    }

    private function contentGroupQuery(PublicTranscriptionAggregates $aggregates, string $search): Builder
    {
        $query = PublicContentGroupQueries::base($aggregates);

        if ($search !== '') {
            PublicContentGroupQueries::applySearch($query, $search);
        }

        return $query->orderBy('title')->orderBy('id');
    }

    private function label(Author|ContentGroup|ContentItem $sample): string
    {
        if ($sample instanceof ContentItem) {
            return __('admin.settings_sp3c.preview.sample_item_label', [
                'title' => $sample->title,
                'group' => $sample->contentGroup->title,
            ]);
        }

        return $sample instanceof ContentGroup
            ? (string) $sample->title
            : (string) $sample->name;
    }

    /**
     * @param  array{
     *     renderer: PublicFrontCardTemplateRenderer,
     *     context: PublicFrontRenderContext,
     *     selector: PublicTranscriptionSelector,
     *     policy: PublicTranscriptionPolicy,
     *     aggregates: PublicTranscriptionAggregates,
     *     default_images: PublicDefaultImageResolver
     * }  $services
     */
    private function render(
        Author|ContentGroup|ContentItem $sample,
        PublicFrontCardTemplate $template,
        array $services,
    ): string {
        return match (true) {
            $sample instanceof ContentItem => $this->renderContentItem($sample, $template, $services),
            $sample instanceof ContentGroup => $this->renderContentGroup($sample, $template, $services),
            default => $this->renderContributor($sample, $template, $services),
        };
    }

    /**
     * @param  array<string, mixed>  $services
     */
    private function renderContentItem(ContentItem $item, PublicFrontCardTemplate $template, array $services): string
    {
        $options = $services['context']->cardOptions();
        $presenter = new PublicContentItemCardPresenter(
            $services['renderer'],
            $services['selector'],
            $services['policy'],
            $services['default_images'],
            $this->displayTitle,
        );
        $card = $presenter->present($item, $options, $template);

        return view('components.public.content-item-card', [
            'card' => $card,
            'options' => $options,
            'cardTemplate' => $template,
            'previewMode' => true,
            'attributes' => new ComponentAttributeBag,
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $services
     */
    private function renderContentGroup(ContentGroup $group, PublicFrontCardTemplate $template, array $services): string
    {
        $presenter = new PublicContentGroupCardPresenter(
            $services['renderer'],
            $services['default_images'],
        );
        $card = $presenter->present(
            $group,
            $template,
            PublicFrontConfigRegistry::defaults()['podcasts_page'],
        );

        return view('components.public.content-group-card', [
            'card' => $card,
            'cardTemplate' => $template,
            'previewMode' => true,
            'attributes' => new ComponentAttributeBag,
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $services
     */
    private function renderContributor(Author $author, PublicFrontCardTemplate $template, array $services): string
    {
        $url = ShowContributor::getUrl(['authorSlug' => $author->slug], panel: 'public');
        $presenter = new PublicContributorCardPresenter(
            $services['renderer'],
            $services['default_images'],
        );
        $card = $presenter->present($author, $url, $template);

        return view('components.public.contributor-card', [
            'author' => $author,
            'fullPageUrl' => $url,
            'cardTemplate' => $template,
            'card' => $card,
            'previewMode' => true,
            'attributes' => new ComponentAttributeBag,
        ])->render();
    }
}
