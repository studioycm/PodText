<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use App\Models\Category;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class BrowseCategoryContentItems extends Page
{
    use HidesPublicPageHeader;

    public Category $category;

    protected string $view = 'filament.public.pages.browse-category-content-items';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'categories/{categorySlug}';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'categories.show';
    }

    public function mount(string $categorySlug): void
    {
        $this->category = Category::query()
            ->visible()
            ->where('slug', $categorySlug)
            ->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->category->name;
    }
}
