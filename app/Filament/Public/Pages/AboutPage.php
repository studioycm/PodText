<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use App\Support\PublicFront\About\PublicAboutPageRenderer;
use App\Support\PublicFront\PublicFrontConfigReader;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class AboutPage extends Page
{
    use HidesPublicPageHeader;

    protected string $view = 'filament.public.pages.about-page';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $aboutPage = [];

    /** @var array<int, array<string, mixed>> */
    public array $blocks = [];

    /** @var array<int, array<string, mixed>> */
    public array $teamProfiles = [];

    /** @var array<int, array{form_key: string, display_mode: string}> */
    public array $formCtas = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'about';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'about';
    }

    public function mount(): void
    {
        $aboutPage = app(PublicFrontConfigReader::class)
            ->read()
            ->group('about_page');

        abort_unless(($aboutPage['enabled'] ?? false) === true, 404);

        $renderer = app(PublicAboutPageRenderer::class);

        $visibleBlocks = collect($aboutPage['blocks'] ?? [])
            ->filter(fn (mixed $block): bool => is_array($block) && ($block['visible'] ?? true) === true)
            ->filter(fn (array $block): bool => ($block['type'] ?? null) !== 'form_cta' || $renderer->hasEnabledForm($block['form_key'] ?? null))
            ->values()
            ->all();

        $this->blocks = collect($visibleBlocks)
            ->map(fn (array $block): array => $this->viewReadyBlock($block, $renderer))
            ->values()
            ->all();

        $this->teamProfiles = collect($aboutPage['team_profiles'] ?? [])
            ->filter(fn (mixed $profile): bool => is_array($profile) && ($profile['visible'] ?? true) === true)
            ->values()
            ->all();

        $this->formCtas = $renderer->formCtas($visibleBlocks);
        $this->aboutPage = [
            'enabled' => true,
            'title' => $aboutPage['title'] ?? __('public.pages.about.title'),
            'kicker' => $aboutPage['kicker'] ?? null,
            'description' => $aboutPage['description'] ?? null,
            'settings' => $aboutPage['settings'] ?? [],
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->aboutPage['title'] ?? __('public.pages.about.title');
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function viewReadyBlock(array $block, PublicAboutPageRenderer $renderer): array
    {
        if (($block['type'] ?? null) === 'markdown') {
            $block['html'] = $renderer->renderMarkdown($block['content'] ?? $block['body'] ?? '');
            unset($block['content'], $block['body']);

            return $block;
        }

        if (($block['type'] ?? null) === 'rich_content') {
            $block['html'] = $renderer->renderRichContent($block['rich_content'] ?? null);
            unset($block['rich_content'], $block['content']);

            return $block;
        }

        if (($block['type'] ?? null) === 'callout') {
            $block['html'] = $renderer->renderMarkdown($block['body'] ?? '');
            unset($block['body'], $block['content']);

            return $block;
        }

        return $block;
    }
}
