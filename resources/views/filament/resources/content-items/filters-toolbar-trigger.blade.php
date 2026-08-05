{{-- The episodes list keeps its filters in a collapsible panel above the
     table, but Filament renders that panel's trigger inside the panel's own
     container — a row of its own. This hook re-homes the trigger into the
     toolbar row, beside the search box, the grouping select and the column
     manager, so every table control lives on one line.

     `areFiltersOpen` is Alpine state on the table root, and the toolbar is a
     descendant of it, so the button toggles the same panel the native
     trigger would. The native one is hidden by the admin theme. --}}
@php
    $livewire = \Livewire\Livewire::current();
@endphp

@if ($livewire instanceof \App\Filament\Resources\ContentItems\Pages\ListContentItems)
    @php
        $table = $livewire->getTable();
    @endphp

    <span
        class="fi-ta-filters-trigger-action-ctn podtext-toolbar-filters-trigger"
        data-testid="episodes-toolbar-filters-trigger"
        x-on:click="areFiltersOpen = ! areFiltersOpen"
    >
        {{ $table->getFiltersTriggerAction()->badge($table->getActiveFiltersCount()) }}
    </span>
@endif
