<?php

namespace App\Filament\Resources\Support;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use LogicException;

final class ResourceTableActions
{
    public static function iconOnly(Table $table): Table
    {
        return $table->modifyUngroupedRecordActionsUsing(function (Action $action): void {
            if ($action->getIcon() === null) {
                $action->icon(match (true) {
                    $action instanceof EditAction => Heroicon::OutlinedPencilSquare,
                    $action instanceof DeleteAction => Heroicon::OutlinedTrash,
                    default => null,
                });
            }

            if (! $action->getIcon() instanceof Heroicon) {
                throw new LogicException("Resource table action [{$action->getName()}] requires a semantic icon.");
            }

            $action
                ->iconButton()
                ->hiddenLabel()
                ->tooltip(fn () => $action->getLabel());
        });
    }
}
