<?php

namespace App\Filament\Public\Pages;

use App\Models\ContentGroup;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class ShowContentGroup extends Page
{
    public ContentGroup $contentGroup;

    protected static ?string $slug = 'groups/{contentGroupSlug}';

    protected string $view = 'filament.public.pages.show-content-group';

    protected static bool $shouldRegisterNavigation = false;

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'groups.show';
    }

    public function mount(string $contentGroupSlug): void
    {
        $this->contentGroup = ContentGroup::query()
            ->published()
            ->where('slug', $contentGroupSlug)
            ->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->contentGroup->title;
    }
}
