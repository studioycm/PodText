<?php

namespace App\Support\Settings\CardTemplates;

use App\Enums\MediaAttachmentRole;
use App\Filament\Public\Pages\ShowContributor;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Support\Media\MediaAttachmentIdentityResolver;
use App\Support\Media\MediaIdentityResolver;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\PublicMediaDelivery;
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
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\ComponentAttributeBag;

class CardTemplatePreviewer
{
    public const SAMPLE_PRELOAD_LIMIT = 10;

    public const SAMPLE_LIMIT = 50;

    public function __construct(
        private readonly CardTemplateDraftNormalizer $normalizer,
        private readonly ContentItemDisplayTitle $displayTitle,
        private readonly PublicFrontRenderContext $context,
        private readonly MediaRecordScope $mediaRecordScope,
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
            $services['default_images'],
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
    public function initialSampleOptions(string $family): array
    {
        return $this->sampleOptionsQuery($family)
            ->limit(self::SAMPLE_PRELOAD_LIMIT)
            ->get()
            ->mapWithKeys(fn (Author|ContentGroup|ContentItem $sample): array => [
                (int) $sample->getKey() => $this->label($sample),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function sampleOptions(string $family, string $search): array
    {
        return $this->sampleOptionsQuery($family, $search)
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
        $sample = $this->sampleQuery(
            $family,
            $services['aggregates'],
            $services['selector'],
            $services['default_images'],
        )
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
        $queryServices = $this->queryServices();

        return [
            'renderer' => new PublicFrontCardTemplateRenderer(new PublicFrontCardTemplateResolver($this->context)),
            'context' => $this->context,
            ...$queryServices,
        ];
    }

    /**
     * @return array{
     *     selector: PublicTranscriptionSelector,
     *     policy: PublicTranscriptionPolicy,
     *     aggregates: PublicTranscriptionAggregates,
     *     default_images: PublicDefaultImageResolver
     * }
     */
    private function queryServices(): array
    {
        $policy = PublicTranscriptionPolicy::fromConfig(
            $this->context->transcriptionPolicy(),
            multiMode: false,
        );
        $selector = new PublicTranscriptionSelector($policy);

        return [
            'selector' => $selector,
            'policy' => $policy,
            'aggregates' => new PublicTranscriptionAggregates($policy, $selector),
            'default_images' => new PublicDefaultImageResolver(
                $this->context,
                app(MediaIdentityResolver::class),
                app(MediaAttachmentIdentityResolver::class),
                app(PublicMediaDelivery::class),
            ),
        ];
    }

    private function sample(
        string $family,
        ?int $sampleId,
        PublicTranscriptionAggregates $aggregates,
        PublicTranscriptionSelector $selector,
        PublicDefaultImageResolver $defaultImages,
    ): Author|ContentGroup|ContentItem|null {
        $query = $this->sampleQuery($family, $aggregates, $selector, $defaultImages);

        if ($sampleId !== null) {
            return $query->whereKey($sampleId)->first();
        }

        return $query->first();
    }

    private function sampleQuery(
        string $family,
        PublicTranscriptionAggregates $aggregates,
        PublicTranscriptionSelector $selector,
        PublicDefaultImageResolver $defaultImages,
        string $search = '',
    ): Builder {
        $search = trim($search);

        return match ($family) {
            PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY => $this->contentItemQuery(
                $aggregates,
                $selector,
                $defaultImages,
                $search,
            ),
            PublicFrontCardTemplateRegistry::CONTENT_GROUP_FAMILY => $this->contentGroupQuery(
                $aggregates,
                $defaultImages,
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
        PublicDefaultImageResolver $defaultImages,
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

        $contentItemsTable = (new ContentItem)->getTable();
        $contentGroupsTable = (new ContentGroup)->getTable();
        $itemAttachment = 'preview_item_attachment';
        $groupAttachment = 'preview_group_attachment';
        $previewGroup = 'preview_content_group';
        $query
            ->selectSub(
                DB::table('media_attachments')
                    ->selectRaw('1')
                    ->where('attachable_type', 'content_item')
                    ->where('role', MediaAttachmentRole::PrimaryImage->value)
                    ->whereColumn('attachable_id', "{$contentItemsTable}.id")
                    ->limit(1),
                'preview_item_attachment_exists',
            )
            ->selectSub(
                $this->mediaRecordScope
                    ->inventoryQuery()
                    ->selectRaw('1')
                    ->join("media_attachments as {$itemAttachment}", "{$itemAttachment}.media_id", '=', 'curator.id')
                    ->where("{$itemAttachment}.attachable_type", 'content_item')
                    ->where("{$itemAttachment}.role", MediaAttachmentRole::PrimaryImage->value)
                    ->whereColumn("{$itemAttachment}.attachable_id", "{$contentItemsTable}.id")
                    ->where('curator.disk', 'public')
                    ->where('curator.visibility', 'public')
                    ->limit(1),
                'preview_item_trusted_attachment_exists',
            )
            ->selectSub(
                $this->mediaRecordScope
                    ->inventoryQuery()
                    ->selectRaw('count(*)')
                    ->where('curator.disk', 'public')
                    ->where('curator.visibility', 'public')
                    ->whereColumn('curator.path', "{$contentItemsTable}.image_path"),
                'preview_item_legacy_count',
            )
            ->selectSub(
                DB::table('curator')
                    ->selectRaw('count(*)')
                    ->whereColumn('curator.path', "{$contentItemsTable}.image_path"),
                'preview_item_raw_legacy_count',
            )
            ->selectSub(
                DB::table('media_attachments')
                    ->selectRaw('1')
                    ->where('attachable_type', 'content_group')
                    ->where('role', MediaAttachmentRole::Cover->value)
                    ->whereColumn('attachable_id', "{$contentItemsTable}.content_group_id")
                    ->limit(1),
                'preview_group_attachment_exists',
            )
            ->selectSub(
                $this->mediaRecordScope
                    ->inventoryQuery()
                    ->selectRaw('1')
                    ->join("media_attachments as {$groupAttachment}", "{$groupAttachment}.media_id", '=', 'curator.id')
                    ->join("{$contentGroupsTable} as {$previewGroup}", "{$previewGroup}.id", '=', "{$groupAttachment}.attachable_id")
                    ->where("{$groupAttachment}.attachable_type", 'content_group')
                    ->where("{$groupAttachment}.role", MediaAttachmentRole::Cover->value)
                    ->whereColumn("{$previewGroup}.id", "{$contentItemsTable}.content_group_id")
                    ->where('curator.disk', 'public')
                    ->where('curator.visibility', 'public')
                    ->limit(1),
                'preview_group_trusted_attachment_exists',
            )
            ->selectSub(
                $this->mediaRecordScope
                    ->inventoryQuery()
                    ->selectRaw('count(*)')
                    ->join("{$contentGroupsTable} as {$previewGroup}", function ($join) use ($contentItemsTable, $previewGroup): void {
                        $join->whereColumn("{$previewGroup}.id", "{$contentItemsTable}.content_group_id");
                    })
                    ->where('curator.disk', 'public')
                    ->where('curator.visibility', 'public')
                    ->whereColumn('curator.path', "{$previewGroup}.cover_path"),
                'preview_group_legacy_count',
            )
            ->selectSub(
                DB::table('curator')
                    ->selectRaw('count(*)')
                    ->join("{$contentGroupsTable} as {$previewGroup}", function ($join) use ($contentItemsTable, $previewGroup): void {
                        $join->whereColumn("{$previewGroup}.id", "{$contentItemsTable}.content_group_id");
                    })
                    ->whereColumn('curator.path', "{$previewGroup}.cover_path"),
                'preview_group_raw_legacy_count',
            );
        $query->orderByRaw(
            "CASE
                WHEN preview_item_trusted_attachment_exists = 1
                    OR (
                        COALESCE(preview_item_attachment_exists, 0) = 0
                        AND preview_item_raw_legacy_count = 1
                        AND preview_item_legacy_count = 1
                    )
                    OR NULLIF(TRIM({$contentItemsTable}.external_thumbnail_url), '') IS NOT NULL THEN 0
                WHEN ? = 1 AND (
                    preview_group_trusted_attachment_exists = 1
                    OR (
                        COALESCE(preview_group_attachment_exists, 0) = 0
                        AND preview_group_raw_legacy_count = 1
                        AND preview_group_legacy_count = 1
                    )
                ) THEN 1
                WHEN ? = 1 THEN 2
                ELSE 3
            END",
            [
                (int) $defaultImages->allowsContentItemGroupCover(),
                (int) $defaultImages->hasConfiguredDefault(PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY),
            ],
        );

        return $query->orderByEffectiveTranscriptionPublishedAt();
    }

    private function contentGroupQuery(
        PublicTranscriptionAggregates $aggregates,
        PublicDefaultImageResolver $defaultImages,
        string $search,
    ): Builder {
        $query = PublicContentGroupQueries::base($aggregates);

        if ($search !== '') {
            PublicContentGroupQueries::applySearch($query, $search);
        }

        $contentGroupsTable = (new ContentGroup)->getTable();
        $attachment = 'preview_group_attachment';
        $query
            ->selectSub(
                DB::table('media_attachments')
                    ->selectRaw('1')
                    ->where('attachable_type', 'content_group')
                    ->where('role', MediaAttachmentRole::Cover->value)
                    ->whereColumn('attachable_id', "{$contentGroupsTable}.id")
                    ->limit(1),
                'preview_group_attachment_exists',
            )
            ->selectSub(
                $this->mediaRecordScope
                    ->inventoryQuery()
                    ->selectRaw('1')
                    ->join("media_attachments as {$attachment}", "{$attachment}.media_id", '=', 'curator.id')
                    ->where("{$attachment}.attachable_type", 'content_group')
                    ->where("{$attachment}.role", MediaAttachmentRole::Cover->value)
                    ->whereColumn("{$attachment}.attachable_id", "{$contentGroupsTable}.id")
                    ->where('curator.disk', 'public')
                    ->where('curator.visibility', 'public')
                    ->limit(1),
                'preview_group_trusted_attachment_exists',
            )
            ->selectSub(
                $this->mediaRecordScope
                    ->inventoryQuery()
                    ->selectRaw('count(*)')
                    ->where('curator.disk', 'public')
                    ->where('curator.visibility', 'public')
                    ->whereColumn('curator.path', "{$contentGroupsTable}.cover_path"),
                'preview_group_legacy_count',
            )
            ->selectSub(
                DB::table('curator')
                    ->selectRaw('count(*)')
                    ->whereColumn('curator.path', "{$contentGroupsTable}.cover_path"),
                'preview_group_raw_legacy_count',
            );
        $query->orderByRaw(
            'CASE
                WHEN preview_group_trusted_attachment_exists = 1
                    OR (
                        COALESCE(preview_group_attachment_exists, 0) = 0
                        AND preview_group_raw_legacy_count = 1
                        AND preview_group_legacy_count = 1
                    ) THEN 0
                WHEN ? = 1 THEN 2
                ELSE 3
            END',
            [(int) $defaultImages->hasConfiguredDefault(PublicFrontCardTemplateRegistry::CONTENT_GROUP_FAMILY)],
        );

        return $query->orderBy('title')->orderBy('id');
    }

    private function sampleOptionsQuery(string $family, string $search = ''): Builder
    {
        $services = $this->queryServices();

        $query = $this->sampleQuery(
            $family,
            $services['aggregates'],
            $services['selector'],
            $services['default_images'],
            $search,
        );

        if ($family === PublicFrontCardTemplateRegistry::CONTENT_GROUP_FAMILY) {
            $query->without(['coverMediaAttachment', 'coverMediaAttachment.media']);
        }

        return $query;
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
