<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use App\Models\ContentTag;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class BrowseTagContentItems extends Page
{
    use HidesPublicPageHeader;

    public ContentTag $contentTag;

    protected string $view = 'filament.public.pages.browse-tag-content-items';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'tags/{tagSlug}';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'tags.show';
    }

    public function mount(string $tagSlug): void
    {
        $this->contentTag = ContentTag::query()
            ->content()
            ->enabled()
            ->where('slug->'.app()->getLocale(), $tagSlug)
            ->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->contentTag->name;
    }
}
