<?php

namespace App\Filament\Resources\Media\Pages;

use App\Enums\MediaLibraryTask;
use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Support\Media\MediaLibraryContext;
use App\Support\Media\MediaLibraryTaskQuery;
use App\Support\Media\MediaReferenceFinder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class ListMedia extends ListRecords
{
    use RestrictsFileUploadsToSchemaComponents;

    protected static string $resource = MediaResource::class;

    #[Url(as: 'focus')]
    public mixed $focus = null;

    private ?string $primedReferenceSignature = null;

    public function mount(): void
    {
        parent::mount();

        $context = app(MediaLibraryContext::class);
        $state = $context->capture(
            task: $this->activeTab,
            filters: is_array($this->tableFilters) ? $this->tableFilters : [],
            search: $this->tableSearch,
            sort: $this->tableSort,
            page: request()->query('page', 1),
            focus: 1,
        );
        $parameters = $context->indexParameters($state);

        $this->activeTab = (string) $state['task'];
        $this->tableFilters = $parameters['filters'] ?? null;
        $this->tableSearch = $state['search'] ?? '';
        $this->tableSort = is_string($state['sort']) ? $state['sort'] : null;
        $this->focus = $context->normalizeFocus($this->focus);
        $this->paginators['page'] = (int) $state['page'];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $counts = app(MediaLibraryTaskQuery::class)->counts();

        return collect(MediaLibraryTask::cases())
            ->mapWithKeys(function (MediaLibraryTask $task) use ($counts): array {
                $tab = Tab::make($task->getLabel())
                    ->modifyQueryUsing(
                        fn (Builder $query): Builder => app(MediaLibraryTaskQuery::class)
                            ->apply($query, $task),
                    );

                if (array_key_exists($task->value, $counts)) {
                    $tab->badge($counts[$task->value]);
                }

                return [$task->value => $tab];
            })
            ->all();
    }

    public function activeTaskDescription(): string
    {
        return (MediaLibraryTask::tryFrom((string) $this->activeTab) ?? MediaLibraryTask::All)
            ->description();
    }

    public function updatedActiveTab(): void
    {
        $this->activeTab = MediaLibraryTask::tryFrom((string) $this->activeTab)?->value
            ?? MediaLibraryTask::All->value;

        parent::updatedActiveTab();
    }

    public function forgetMediaTaskCaches(): void
    {
        app(MediaLibraryTaskQuery::class)->forgetCounts();
        unset($this->cachedTabs);
    }

    public function hasMediaViewConstraints(): bool
    {
        return $this->activeTab !== MediaLibraryTask::All->value
            || collect($this->tableFilters ?? [])->contains(
                fn (mixed $filter): bool => filled(data_get($filter, 'value')),
            )
            || filled($this->tableSearch)
            || filled($this->tableSort)
            || $this->getTablePage() !== 1;
    }

    public function shouldFocusMedia(Media $media): bool
    {
        return is_int($this->focus) && $this->focus === (int) $media->getKey();
    }

    public function editUrlForMedia(Media $media): string
    {
        $context = app(MediaLibraryContext::class);
        $state = $context->capture(
            task: $this->activeTab,
            filters: $this->tableFilters ?? [],
            search: $this->tableSearch,
            sort: $this->tableSort,
            page: $this->getTablePage(),
            focus: (int) $media->getKey(),
        );

        return MediaResource::getUrl('edit', [
            'record' => $media,
            ...$context->editParameters($state),
        ]);
    }

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
            Action::make('resetMediaView')
                ->label(__('admin.media_library.reset_view'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->url(MediaResource::getUrl('index'))
                ->visible(fn (): bool => $this->hasMediaViewConstraints()),
            CreateAction::make()
                ->label(__('admin.media_library.upload_multiple'))
                ->icon(Heroicon::ArrowUpTray),
        ];
    }
}
