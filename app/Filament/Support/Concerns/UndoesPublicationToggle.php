<?php

namespace App\Filament\Support\Concerns;

use App\Enums\PublicationStatus;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Tables\ContentItemsTable;
use App\Models\ContentItem;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * The undo button on a publication notification lands here.
 *
 * It is a trait because the status cell appears on two tables — the episodes
 * list and the podcast's episodes relation manager — and a notification
 * dispatches to whichever component is listening. Without this on both, undo
 * would silently do nothing on one of them.
 *
 * Everything in the payload came from the browser, so nothing in it is
 * trusted: the record is resolved through the resource's own query, the
 * status has to be a real enum case, and the policy is asked again. A forged
 * event can therefore do no more than a click already could.
 *
 * The enum narrowing is pinned by a test. The Gate call is not, and saying so
 * is the point: ContentItemPolicy answers viewAny and update with the same
 * predicate, so nobody can reach either table without also passing it, and a
 * mutation deleting the line changes no observable behaviour today. It stays
 * because the day those two diverge is exactly the day an unauthorised undo
 * would otherwise land — and that day should also bring the missing test.
 */
trait UndoesPublicationToggle
{
    #[On('undoPublicationToggle')]
    public function undoPublicationToggle(mixed $recordKey, mixed $status): void
    {
        $status = PublicationStatus::tryFrom(is_string($status) ? $status : '');
        $record = ContentItemResource::getEloquentQuery()->find($recordKey);

        if ($status === null || ! $record instanceof ContentItem || ! Gate::allows('update', $record)) {
            return;
        }

        $record->update(['status' => $status]);

        ContentItemsTable::notifyPublicationOutcome($record);
    }
}
