<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaIssueReviewPresenter;
use App\Support\Media\MediaLibraryContext;
use App\Support\Media\MediaLibraryTaskQuery;
use App\Support\Media\MediaOperationReceipts;
use App\Support\Media\MediaRecordProjector;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class ReviewMediaIssues extends Page
{
    use InteractsWithRecord;
    use RestrictsFileUploadsToSchemaComponents;

    protected static string $resource = MediaResource::class;

    protected string $view = 'filament.resources.media.pages.review-media-issues';

    /** @var array<string, int|string|null> */
    #[Locked]
    public array $mediaLibraryContext = [];

    /** @var array{closed: array<int, string>, remaining: array<int, string>}|null */
    public ?array $sanitizeResult = null;

    public bool $sanitizeRefused = false;

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
        $media = $this->media();
        $trustedStrip = null;

        if ($media->trusted_at !== null) {
            $trustedStrip = __('admin.media_issue_review.trust.strip', [
                'date' => $media->trusted_at->timezone('Asia/Jerusalem')->format('d/m/Y H:i'),
                'user' => (string) (User::query()->find($media->trusted_by_user_id)?->name
                    ?? __('admin.media_issue_review.facts.unavailable')),
            ]);
        }

        return [
            'review' => app(MediaIssueReviewPresenter::class)->review($this->media()),
            'trustedStrip' => $trustedStrip,
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

    public function sanitizeFileAction(): Action
    {
        return Action::make('sanitizeFile')
            ->label(__('admin.media_issue_review.sanitize.action'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('admin.media_issue_review.sanitize.action'))
            ->modalDescription(__('admin.media_issue_review.sanitize.not_swap_hint'))
            ->modalContent(fn (): View => $this->operationConsequence('sanitize'))
            ->modalSubmitActionLabel(__('admin.media_issue_review.sanitize.action'))
            ->action(function (): void {
                $user = $this->actor();
                $media = $this->media();
                Gate::forUser($user)->authorize('repair', $media);
                $diagnostics = app(MediaInventoryDiagnostics::class);
                $before = $diagnostics->reasons($media);
                $name = (string) ($media->title ?: $media->name);

                try {
                    $updated = app(MediaFilesystemMutationCoordinator::class)->sanitize($media, $user);
                } catch (InvalidArgumentException) {
                    $this->sanitizeRefused = true;
                    Notification::make()
                        ->warning()
                        ->title(__('admin.media_issue_review.sanitize.refusal_title'))
                        ->body(__('admin.media_issue_review.sanitize.refusal_body'))
                        ->send();

                    return;
                } catch (ValidationException $exception) {
                    app(MediaOperationReceipts::class)->operationFailed($exception->getMessage());

                    return;
                } catch (RuntimeException) {
                    app(MediaOperationReceipts::class)->operationFailed();

                    return;
                }

                $diagnostics->forget($media);
                $diagnostics->forget($updated);
                $this->record = $updated;
                $after = $diagnostics->reasons($updated);
                $closed = array_values(array_diff($before, $after));
                $this->sanitizeResult = [
                    'closed' => array_map(fn (string $reason): string => __("admin.media_library.repair_{$reason}"), $closed),
                    'remaining' => array_map(fn (string $reason): string => __("admin.media_library.repair_{$reason}"), $after),
                ];
                $this->sanitizeRefused = false;
                app(MediaOperationReceipts::class)->sanitizeSucceeded($user, $name, count($closed), count($after));
            });
    }

    public function swapFileAction(): Action
    {
        return Action::make('swapFile')
            ->label(__('admin.media_library.swap'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('warning')
            ->modalContent(fn (): View => $this->operationConsequence('swap'))
            ->modalSubmitActionLabel(__('admin.media_library.swap_submit'))
            ->schema([
                FileUpload::make('replacement')
                    ->label(__('admin.media_library.replacement'))
                    ->acceptedFileTypes(function (): array {
                        $policy = app(CuratorImageUploadPolicy::class);

                        try {
                            return $policy->mimeTypesFor($policy->purposeForPath((string) $this->media()->path));
                        } catch (InvalidArgumentException) {
                            return $policy->globalMimeTypes();
                        }
                    })
                    ->maxSize(CuratorImageUploadPolicy::MAX_KILOBYTES)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $user = $this->actor();
                $media = $this->media();
                Gate::forUser($user)->authorize('swap', $media);
                $replacement = $data['replacement'] ?? null;
                abort_unless($replacement instanceof TemporaryUploadedFile, 422);
                $oldSize = (int) $media->size;

                try {
                    $updated = app(MediaFilesystemMutationCoordinator::class)->swap($media, $replacement, $user);
                } catch (InvalidArgumentException) {
                    app(MediaOperationReceipts::class)->operationFailed();

                    return;
                } catch (ValidationException $exception) {
                    app(MediaOperationReceipts::class)->operationFailed($exception->getMessage());

                    return;
                } catch (RuntimeException) {
                    app(MediaOperationReceipts::class)->operationFailed();

                    return;
                }

                app(MediaInventoryDiagnostics::class)->forget($media);
                app(MediaInventoryDiagnostics::class)->forget($updated);
                $this->record = $updated;
                $this->sanitizeRefused = false;
                $this->sanitizeResult = null;
                app(MediaOperationReceipts::class)->swapSucceeded($user, $oldSize, (int) $updated->size);
            });
    }

    public function deleteFileAction(): Action
    {
        return Action::make('deleteFile')
            ->label(__('admin.media_library.delete_permanently'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalContent(fn (): View => $this->operationConsequence('delete'))
            ->modalSubmitActionLabel(__('admin.media_library.delete_permanently'))
            ->action(function (): void {
                $user = $this->actor();
                $media = $this->media();
                Gate::forUser($user)->authorize('delete', $media);
                $name = (string) ($media->title ?: $media->name);

                try {
                    app(MediaFilesystemMutationCoordinator::class)->delete($media, $user);
                } catch (ValidationException $exception) {
                    app(MediaOperationReceipts::class)->operationFailed($exception->getMessage());

                    return;
                } catch (RuntimeException) {
                    app(MediaOperationReceipts::class)->operationFailed();

                    return;
                }

                app(MediaInventoryDiagnostics::class)->forget($media);
                app(MediaOperationReceipts::class)->deleteSucceeded($user, $name);
                $this->redirect($this->mediaLibraryReturnUrl());
            });
    }

    public function trustFileAction(): Action
    {
        return Action::make('trustFile')
            ->label(__('admin.media_issue_review.trust.action'))
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('admin.media_issue_review.trust.action'))
            ->modalDescription(__('admin.media_issue_review.trust.consequence'))
            ->modalSubmitActionLabel(__('admin.media_issue_review.trust.submit'))
            ->action(function (): void {
                $user = $this->actor();
                $media = $this->media();
                Gate::forUser($user)->authorize('trust', $media);

                $media->forceFill([
                    'trusted_at' => now(),
                    'trusted_by_user_id' => $user->getKey(),
                ])->save();

                app(MediaInventoryDiagnostics::class)->forget($media);
                $this->record = $media->refresh();
                $this->sanitizeRefused = false;
                $this->sanitizeResult = null;
                app(MediaOperationReceipts::class)->trustGranted($user, (string) ($media->title ?: $media->name));
            });
    }

    public function untrustFileAction(): Action
    {
        return Action::make('untrustFile')
            ->label(__('admin.media_issue_review.trust.untrust_action'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('admin.media_issue_review.trust.untrust_action'))
            ->modalDescription(__('admin.media_issue_review.trust.untrust_consequence'))
            ->action(function (): void {
                $user = $this->actor();
                $media = $this->media();
                Gate::forUser($user)->authorize('trust', $media);

                $media->forceFill([
                    'trusted_at' => null,
                    'trusted_by_user_id' => null,
                ])->save();

                app(MediaInventoryDiagnostics::class)->forget($media);
                $this->record = $media->refresh();
                app(MediaOperationReceipts::class)->trustRevoked($user, (string) ($media->title ?: $media->name));
            });
    }

    private function operationConsequence(string $operation): View
    {
        $media = $this->media();

        return view('filament.resources.media.operation-consequence', [
            'projection' => app(MediaRecordProjector::class)->project($media),
            'storedFilename' => basename((string) $media->path),
            'operation' => $operation,
        ]);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
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
