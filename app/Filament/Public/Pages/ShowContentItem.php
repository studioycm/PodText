<?php

namespace App\Filament\Public\Pages;

use App\Models\ContentGroup;
use App\Models\ContentItem;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class ShowContentItem extends Page
{
    public ContentGroup $contentGroup;

    public ContentItem $contentItem;

    protected static ?string $slug = 'items/{contentGroupSlug}/{contentItemSlug}';

    protected string $view = 'filament.public.pages.show-content-item';

    protected static bool $shouldRegisterNavigation = false;

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

        $this->contentItem = ContentItem::query()
            ->published()
            ->whereBelongsTo($this->contentGroup)
            ->where('slug', $contentItemSlug)
            ->with(['authors', 'contentGroup'])
            ->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->contentItem->title;
    }
}
