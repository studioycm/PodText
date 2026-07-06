<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use App\Models\ContentGroup;
use App\Support\PublicFront\Groups\PublicContentGroupQueries;
use App\Support\PublicFront\PublicFrontConfigReader;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class ShowContentGroup extends Page
{
    use HidesPublicPageHeader;

    public ContentGroup $contentGroup;

    protected static ?string $slug = 'podcasts/{contentGroupSlug}';

    protected string $view = 'filament.public.pages.show-content-group';

    protected static bool $shouldRegisterNavigation = false;

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'podcasts.show';
    }

    public function mount(string $contentGroupSlug): void
    {
        abort_unless((bool) ($this->pageConfig()['enabled'] ?? true), 404);

        $this->contentGroup = PublicContentGroupQueries::base()
            ->where('slug', $contentGroupSlug)
            ->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->contentGroup->title;
    }

    /**
     * @return array<string, mixed>
     */
    public function pageConfig(): array
    {
        return app(PublicFrontConfigReader::class)
            ->read()
            ->group('podcasts_page');
    }
}
