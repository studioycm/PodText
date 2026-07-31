{{-- The media gallery's top pagination panel, rendered as the toolbar
     row's last child so the toolbar becomes one two-column panel:
     controls at the start, pagination at the end. The hook is
     unscoped, so this view guards itself to the gallery page. --}}
@php
    $livewire = \Livewire\Livewire::current();
@endphp

@if ($livewire instanceof \App\Filament\Resources\Media\Pages\ListMedia)
    @php
        $records = $livewire->getTableRecords();
        $table = $livewire->getTable();
    @endphp

    @if ($records instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="podtext-gallery-top-pagination" data-testid="media-top-pagination">
            <x-filament::pagination
                :extreme-links="$table->hasExtremePaginationLinks()"
                :page-options="$table->getPaginationPageOptions()"
                :paginator="$records"
            />
        </div>
    @endif
@endif
