<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use App\Models\Author;
use App\Support\PublicContent\PublicContributorDiscovery;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class ShowContributor extends Page
{
    use HidesPublicPageHeader;

    public Author $author;

    protected static ?string $slug = 'contributors/{authorSlug}';

    protected string $view = 'filament.public.pages.show-contributor';

    protected static bool $shouldRegisterNavigation = false;

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'contributors.show';
    }

    public function mount(string $authorSlug): void
    {
        $this->author = PublicContributorDiscovery::findContributorBySlug($authorSlug);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->author->name;
    }
}
