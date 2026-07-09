<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Filament\Resources\ContentItems\ContentItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Enums\ContentTabPosition;

class EditContentItem extends EditRecord
{
    protected static string $resource = ContentItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabPosition(): ?ContentTabPosition
    {
        return ContentTabPosition::Before;
    }

    public function getContentTabLabel(): ?string
    {
        return __('admin.tabs.item_details');
    }
}
