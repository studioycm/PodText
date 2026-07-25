<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Support\Media\MediaLibraryContext;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    /** @var array<string, int|string|null> */
    #[Locked]
    public array $mediaLibraryContext = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $context = app(MediaLibraryContext::class);
        $this->mediaLibraryContext = request()->has('origin')
            ? $context->fromContinuationToken(
                request()->query('origin'),
                (int) $this->getRecord()->getKey(),
            )
            : $context->fromInput(
                request()->query('from'),
                (int) $this->getRecord()->getKey(),
            );
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_intersect_key($data, array_flip(['alt', 'title', 'caption', 'description']));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return array_intersect_key($data, array_flip(['alt', 'title', 'caption', 'description']));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToMediaLibrary')
                ->label(__('admin.media_library.back_to_media_library'))
                ->icon(Heroicon::OutlinedPhoto)
                ->color('gray')
                ->url(fn (): string => $this->mediaLibraryReturnUrl()),
        ];
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->url(fn (): string => $this->mediaLibraryReturnUrl())
            ->color('gray');
    }

    public function mediaLibraryReturnUrl(): string
    {
        $context = app(MediaLibraryContext::class);

        return MediaResource::getUrl(
            'index',
            $context->indexParameters($this->mediaLibraryContext),
        ).$context->fragment($this->mediaLibraryContext);
    }

    public function issueReviewUrl(): string
    {
        return MediaResource::getUrl('review-issues', [
            'record' => $this->getRecord(),
            ...app(MediaLibraryContext::class)
                ->continuationParameters($this->mediaLibraryContext),
        ]);
    }
}
