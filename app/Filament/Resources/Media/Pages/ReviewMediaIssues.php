<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Support\Media\MediaIssueReviewPresenter;
use App\Support\Media\MediaLibraryContext;
use App\Support\Media\MediaLibraryTaskQuery;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;

class ReviewMediaIssues extends Page
{
    use InteractsWithRecord;
    use RestrictsFileUploadsToSchemaComponents;

    protected static string $resource = MediaResource::class;

    protected string $view = 'filament.resources.media.pages.review-media-issues';

    /** @var array<string, int|string|null> */
    #[Locked]
    public array $mediaLibraryContext = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();

        $this->mediaLibraryContext = app(MediaLibraryContext::class)
            ->fromContinuationToken(
                request()->query('origin'),
                (int) $this->media()->getKey(),
            );
    }

    public function hydrate(): void
    {
        $this->authorizeAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.media_issue_review.heading');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.media_issue_review.heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('admin.media_issue_review.subheading');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.media_issue_review.breadcrumb');
    }

    public function detailsUrl(): string
    {
        $context = app(MediaLibraryContext::class);

        return MediaResource::getUrl('edit', [
            'record' => $this->media(),
            ...$context->continuationParameters($this->mediaLibraryContext),
        ]);
    }

    public function mediaLibraryReturnUrl(): string
    {
        $context = app(MediaLibraryContext::class);

        return MediaResource::getUrl(
            'index',
            $context->indexParameters($this->mediaLibraryContext),
        ).$context->fragment($this->mediaLibraryContext);
    }

    public function nextIssueUrl(): ?string
    {
        $next = app(MediaLibraryTaskQuery::class)->nextIssue(
            $this->media(),
            $this->mediaLibraryContext,
        );

        if (! $next instanceof Media) {
            return null;
        }

        return MediaResource::getUrl('review-issues', [
            'record' => $next,
            ...app(MediaLibraryContext::class)
                ->continuationParameters($this->mediaLibraryContext),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'review' => app(MediaIssueReviewPresenter::class)->review($this->media()),
            'detailsUrl' => $this->detailsUrl(),
            'returnUrl' => $this->mediaLibraryReturnUrl(),
            'nextUrl' => $this->nextIssueUrl(),
            'icons' => [
                'view' => Heroicon::OutlinedEye,
                'download' => Heroicon::OutlinedArrowDownTray,
                'next' => app()->getLocale() === 'he'
                    ? Heroicon::OutlinedArrowLeft
                    : Heroicon::OutlinedArrowRight,
                'return' => Heroicon::OutlinedPhoto,
            ],
        ];
    }

    private function authorizeAccess(): void
    {
        abort_unless(MediaResource::canView($this->media()), 403);
    }

    private function media(): Media
    {
        $record = $this->getRecord();
        abort_unless($record instanceof Media, 404);

        return $record;
    }
}
