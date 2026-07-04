<?php

namespace App\Support\PublicFront\Sections;

use App\Filament\Public\Pages\BrowseCategoryContentItems;
use App\Filament\Public\Pages\BrowseContentGroups;
use App\Filament\Public\Pages\BrowseContributors;
use App\Filament\Public\Pages\BrowseTagContentItems;
use App\Filament\Public\Pages\SearchContentItems;
use App\Filament\Public\Pages\ShowContentGroup;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use Illuminate\Support\Collection;

class PublicDisplaySectionResolver
{
    public function __construct(
        private readonly PublicDisplaySectionConfigValidator $validator,
        private readonly PublicDisplaySectionQueryResolver $queryResolver,
        private readonly PublicFrontCardTemplateResolver $templateResolver,
    ) {}

    public function resolve(HomepageSection $section): ?PublicDisplaySectionResult
    {
        $config = $this->validator->validate($section);

        if (! $config->isRenderable()) {
            return null;
        }

        $results = $this->queryResolver->resolve($config);
        $sourceType = (string) $config->sourceType();
        $displayConfig = $config->displayConfig;
        $templateFamily = $displayConfig['template_family'] ?? null;
        $cardTemplate = is_string($templateFamily)
            ? $this->templateResolver->resolve(
                family: $templateFamily,
                key: $displayConfig['template_key'] ?? null,
                overrides: $displayConfig['template_overrides'] ?? [],
            )
            : null;

        return new PublicDisplaySectionResult(
            key: "section-{$section->getKey()}",
            section: $section,
            sourceType: $sourceType,
            heading: ($displayConfig['show_heading'] ?? true) ? ($displayConfig['heading'] ?? $section->name) : null,
            showHeading: (bool) ($displayConfig['show_heading'] ?? true),
            targetLabel: $this->targetLabel($config, $section),
            viewMoreUrl: ($displayConfig['show_view_all_link'] ?? true) ? $this->viewMoreUrl($config, $section) : null,
            cardTemplate: $cardTemplate,
            items: $results['items'],
            contentGroups: $results['contentGroups'],
            categories: $results['categories'],
            contributors: $results['contributors'],
            sourceConfig: $config->sourceConfig,
            selectionConfig: $config->selectionConfig,
            displayConfig: $config->displayConfig,
            paginationConfig: $config->paginationConfig,
            invalidConfig: $config->invalidConfig,
        );
    }

    /**
     * @param  Collection<int, HomepageSection>  $sections
     * @return Collection<int, PublicDisplaySectionResult>
     */
    public function resolveMany(Collection $sections): Collection
    {
        return $sections
            ->map(fn (HomepageSection $section): ?PublicDisplaySectionResult => $this->resolve($section))
            ->filter()
            ->values();
    }

    public function defaultLatestSection(int $limit): PublicDisplaySectionResult
    {
        $section = new HomepageSection([
            'name' => __('public.sections.latest'),
            'type' => 'latest',
            'limit' => max(1, $limit),
            'is_visible' => true,
            'source_config' => [],
            'selection_config' => [],
            'display_config' => [],
            'pagination_config' => [],
        ]);

        $config = $this->validator->validate($section);
        $results = $this->queryResolver->resolve($config);
        $cardTemplate = $this->templateResolver->resolve('content_item');

        return new PublicDisplaySectionResult(
            key: 'section-latest-default',
            section: null,
            sourceType: PublicDisplaySectionRegistry::LATEST_CONTENT_ITEMS,
            heading: __('public.sections.latest'),
            showHeading: true,
            targetLabel: null,
            viewMoreUrl: SearchContentItems::getUrl(panel: 'public'),
            cardTemplate: $cardTemplate,
            items: $results['items'],
            contentGroups: collect(),
            categories: collect(),
            contributors: collect(),
            sourceConfig: $config->sourceConfig,
            selectionConfig: $config->selectionConfig,
            displayConfig: $config->displayConfig,
            paginationConfig: $config->paginationConfig,
            invalidConfig: $config->invalidConfig,
        );
    }

    private function targetLabel(PublicDisplaySectionConfigResult $config, HomepageSection $section): ?string
    {
        return match ($config->sourceType()) {
            PublicDisplaySectionRegistry::CATEGORY_CONTENT_ITEMS => $this->category($config, $section)?->name,
            PublicDisplaySectionRegistry::TAG_CONTENT_ITEMS => $this->tag($config, $section)?->name,
            PublicDisplaySectionRegistry::CONTENT_GROUP_ITEMS => $this->contentGroup($config, $section)?->title,
            PublicDisplaySectionRegistry::TOP_TRANSCRIBERS => __('public.sections.top_transcribers_target'),
            default => null,
        };
    }

    private function viewMoreUrl(PublicDisplaySectionConfigResult $config, HomepageSection $section): ?string
    {
        $routeKey = $config->displayConfig['view_all_route_key'] ?? null;

        if (is_string($routeKey)) {
            return $this->routeKeyUrl($routeKey);
        }

        return match ($config->sourceType()) {
            PublicDisplaySectionRegistry::LATEST_CONTENT_ITEMS,
            PublicDisplaySectionRegistry::MANUAL_CONTENT_ITEMS => SearchContentItems::getUrl(panel: 'public'),
            PublicDisplaySectionRegistry::CATEGORY_CONTENT_ITEMS => ($category = $this->category($config, $section))
                ? BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public')
                : null,
            PublicDisplaySectionRegistry::TAG_CONTENT_ITEMS => ($tag = $this->tag($config, $section))
                ? BrowseTagContentItems::getUrl(['tagSlug' => $tag->slug], panel: 'public')
                : null,
            PublicDisplaySectionRegistry::CONTENT_GROUP_ITEMS => ($group = $this->contentGroup($config, $section))
                ? ShowContentGroup::getUrl(['contentGroupSlug' => $group->slug], panel: 'public')
                : null,
            PublicDisplaySectionRegistry::CONTRIBUTORS,
            PublicDisplaySectionRegistry::TOP_TRANSCRIBERS => BrowseContributors::getUrl(panel: 'public'),
            default => null,
        };
    }

    private function routeKeyUrl(string $routeKey): ?string
    {
        return match ($routeKey) {
            'home', 'podcasts' => BrowseContentGroups::getUrl(panel: 'public'),
            'search' => SearchContentItems::getUrl(panel: 'public'),
            'contributors' => BrowseContributors::getUrl(panel: 'public'),
            default => null,
        };
    }

    private function category(PublicDisplaySectionConfigResult $config, HomepageSection $section): ?Category
    {
        $categoryId = $config->sourceConfig['category_id'] ?? $section->category_id;

        return Category::query()
            ->visible()
            ->find($categoryId);
    }

    private function tag(PublicDisplaySectionConfigResult $config, HomepageSection $section): ?ContentTag
    {
        $tagId = $config->sourceConfig['tag_id'] ?? $section->tag_id;

        return ContentTag::query()
            ->content()
            ->enabled()
            ->find($tagId);
    }

    private function contentGroup(PublicDisplaySectionConfigResult $config, HomepageSection $section): ?ContentGroup
    {
        $contentGroupId = $config->sourceConfig['content_group_id'] ?? $section->content_group_id;

        return ContentGroup::query()
            ->published()
            ->find($contentGroupId);
    }
}
