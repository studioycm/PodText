<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Enums\EpisodeListScope;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Models\ContentGroup;
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

    public function updatedActiveTab(): void
    {
        // state-narrows-at-the-door: the tab key is URL/browser-writable.
        $this->activeTab = EpisodeListScope::tryFrom((string) $this->activeTab)?->value
            ?? EpisodeListScope::All->value;

        parent::updatedActiveTab();
    }

    public function getSubheading(): ?string
    {
        $scope = EpisodeListScope::tryFrom((string) $this->activeTab) ?? EpisodeListScope::All;

        $podcastTitle = $this->scopedPodcastTitle();

        if ($podcastTitle !== null) {
            return __('admin.episodes.subheading_scoped_to_podcast', [
                'scope' => $scope->description(),
                'podcast' => $podcastTitle,
            ]);
        }

        return $scope->description();
    }

    private function scopedPodcastTitle(): ?string
    {
        $podcastId = data_get($this->tableFilters, 'content_group_id.value');

        if (! is_numeric($podcastId)) {
            return null;
        }

        return ContentGroup::query()->whereKey((int) $podcastId)->value('title');
    }
}
