<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Enums\EpisodeListScope;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Tables\ContentItemsTable;
use App\Support\ContentItems\EpisodeListScopeQuery;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListContentItems extends ListRecords
{
    protected static string $resource = ContentItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createEpisodeWorkspace')
                ->label(__('admin.actions.create_episode_workspace'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(ContentItemResource::getUrl('workspace-create')),
            CreateAction::make()
                ->label(__('admin.actions.classic_create')),
            ...ContentItemsTable::intakeActions(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = app(EpisodeListScopeQuery::class)->counts();

        return collect(EpisodeListScope::cases())
            ->mapWithKeys(fn (EpisodeListScope $scope): array => [
                $scope->value => Tab::make($scope->getLabel())
                    ->modifyQueryUsing(fn (Builder $query): Builder => app(EpisodeListScopeQuery::class)->apply($query, $scope))
                    ->badge($counts[$scope->value] ?? 0)
                    ->extraAttributes(['data-scope' => $scope->value]),
            ])
            ->all();
    }

    public function mount(): void
    {
        parent::mount();

        // The real door: `$activeTab` is `#[Url(as: 'tab')]`, and Livewire
        // does not fire `updated*` hooks for URL hydration — narrowing only
        // in the update hook would leave the query-string path unguarded.
        $this->narrowActiveTab();
    }

    public function updatedActiveTab(): void
    {
        $this->narrowActiveTab();

        parent::updatedActiveTab();
    }

    /** state-narrows-at-the-door: the tab key is URL/browser-writable. */
    private function narrowActiveTab(): void
    {
        $this->activeTab = EpisodeListScope::tryFrom((string) $this->activeTab)?->value
            ?? EpisodeListScope::All->value;
    }

    /**
     * The lens is the page: the scope tabs and their counts already say what
     * is showing, so the page keeps neither a subheading nor breadcrumbs
     * above them (operator ruling, 2026-08-05).
     */
    public function getSubheading(): ?string
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }
}
