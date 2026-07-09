<?php

namespace App\Filament\Resources\ContentGroups\Pages;

use App\Filament\Resources\ContentGroups\ContentGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Enums\ContentTabPosition;

class EditContentGroup extends EditRecord
{
    protected static string $resource = ContentGroupResource::class;

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
        return __('admin.tabs.group_details');
    }
}
