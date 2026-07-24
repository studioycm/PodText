<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Support\Media\MediaReferenceFinder;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

class ListMedia extends ListRecords
{
    use RestrictsFileUploadsToSchemaComponents;

    protected static string $resource = MediaResource::class;

    private ?string $primedReferenceSignature = null;

    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getTableRecords();
        $items = ($records instanceof Paginator || $records instanceof CursorPaginator)
            ? collect($records->items())
            : $records;
        $media = $items->filter(fn (mixed $record): bool => $record instanceof Media);
        $signature = $media
            ->map(fn (Media $record): string => implode(':', [
                $record->getKey(),
                $record->disk,
                $record->path,
            ]))
            ->implode('|');

        if ($signature !== $this->primedReferenceSignature) {
            app(MediaReferenceFinder::class)->prime($media);
            $this->primedReferenceSignature = $signature;
        }

        return $records;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('admin.media_library.upload_multiple'))
                ->icon(Heroicon::ArrowUpTray),
        ];
    }
}
