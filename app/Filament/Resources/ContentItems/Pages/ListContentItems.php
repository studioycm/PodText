<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Enums\EpisodeListScope;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Tables\ContentItemsTable;
use App\Filament\Support\Concerns\UndoesPublicationToggle;
use App\Models\Author;
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
    use UndoesPublicationToggle;

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
        // One shared service instance memoizes the counts, so six deferred
        // badges still cost a single aggregate — and only after the page has
        // painted, because the badge is a closure (see Tab::configureUsing).
        $scopes = app(EpisodeListScopeQuery::class);

        return collect(EpisodeListScope::cases())
            ->filter(fn (EpisodeListScope $scope): bool => $scope->showsAsTab())
            ->mapWithKeys(fn (EpisodeListScope $scope): array => [
                $scope->value => Tab::make($scope->getLabel())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $scopes->apply($query, $scope))
                    ->badge(fn (): int => $scopes->counts()[$scope->value] ?? 0)
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
        // `showsAsTab()` and not merely `tryFrom()`: `pinned` is still a valid
        // scope, so it survives the enum narrowing and would select a tab that
        // no longer exists — an empty table under no visible tab. A stale
        // bookmark has to land somewhere real.
        $scope = EpisodeListScope::tryFrom((string) $this->activeTab);

        $this->activeTab = $scope?->showsAsTab() === true
            ? $scope->value
            : EpisodeListScope::All->value;
    }

    /**
     * A scoped arrival is announced in the heading itself rather than on a
     * second line: «פרקים · קול הבוקר». It is page identity, not commentary,
     * and it costs no vertical space. The plain list keeps the bare heading,
     * because the scope tabs already answer "what is showing".
     */
    public function getHeading(): string
    {
        $heading = parent::getHeading();
        $scope = $this->scopedArrivalName();

        return $scope === null
            ? $heading
            : __('admin.episodes.heading_scoped', ['heading' => $heading, 'scope' => $scope]);
    }

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

    /**
     * The entrances that scope this list to something worth naming. New ones
     * register here; the order is the announcement priority when more than
     * one is active.
     */
    private function scopedArrivalName(): ?string
    {
        $entrances = [
            'content_group_id' => [ContentGroup::class, 'title'],
            'transcriber_id' => [Author::class, 'name'],
        ];

        foreach ($entrances as $filter => [$model, $column]) {
            $key = data_get($this->tableFilters, "{$filter}.value");

            if (! is_numeric($key)) {
                continue;
            }

            $name = $model::query()->whereKey((int) $key)->value($column);

            if (filled($name)) {
                return $name;
            }
        }

        return null;
    }
}
