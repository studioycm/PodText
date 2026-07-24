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

        $this->mediaLibraryContext = app(MediaLibraryContext::class)->fromInput(
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
        $state = $context->fromInput(
            $this->mediaLibraryContext,
            (int) $this->getRecord()->getKey(),
        );

        return MediaResource::getUrl('index', $context->indexParameters($state))
            .$context->fragment($state);
    }
}
