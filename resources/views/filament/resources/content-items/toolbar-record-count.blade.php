{{-- The number of episodes the current view is showing, at the end of the
     toolbar row beside the other table controls.

     Filament renders its result count only in the pagination overview at the
     foot of the table, plus an sr-only aria-live element — so on a long list
     the operator had to scroll to learn how many rows their filter matched.

     `getAllTableRecordsCount()` is filter-, search- and tab-aware, and free
     here: this hook fires after the records have resolved, so the paginator
     already knows its total and no extra query is issued. The hook is
     unscoped, so this view guards itself to the episodes list. --}}
@php
    $livewire = \Livewire\Livewire::current();
@endphp

@if ($livewire instanceof \App\Filament\Resources\ContentItems\Pages\ListContentItems)
    <span class="podtext-toolbar-record-count" data-testid="episodes-toolbar-record-count">
        {{ trans_choice('admin.episodes.toolbar_record_count', $count = $livewire->getAllTableRecordsCount(), ['count' => \App\Support\UiFormats::number($count)]) }}
    </span>
@endif
