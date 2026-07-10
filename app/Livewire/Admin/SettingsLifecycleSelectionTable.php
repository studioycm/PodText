<?php

namespace App\Livewire\Admin;

use App\Support\SettingsLifecycle\SettingsLifecycleSelectionState;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SettingsLifecycleSelectionTable extends Component
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * @var array<int, string>
     */
    public array $selectedPaths = [];

    public string $filter = 'changed';

    public string $search = '';

    public string $tableMode = 'import';

    public function render(): View
    {
        return view('livewire.admin.settings-lifecycle-selection-table', [
            'groupedRows' => $this->groupedRows(),
        ]);
    }

    public function toggleGroup(string $group): void
    {
        $this->selectedPaths = app(SettingsLifecycleSelectionState::class)
            ->toggleGroup($this->rows, $this->selectedPaths, $group);
    }

    public function toggleUnit(string $path): void
    {
        $this->selectedPaths = app(SettingsLifecycleSelectionState::class)
            ->togglePath($this->rows, $this->selectedPaths, $path);
    }

    public function groupState(string $group): string
    {
        return app(SettingsLifecycleSelectionState::class)
            ->groupState($this->rows, $this->selectedPaths, $group);
    }

    /**
     * @return array<string, array{label: string, rows: array<int, array<string, mixed>>}>
     */
    public function groupedRows(): array
    {
        return collect($this->filteredRows())
            ->groupBy(fn (array $row): string => (string) $row['group'])
            ->map(fn ($rows): array => [
                'label' => (string) ($rows->first()['group_label'] ?? ''),
                'rows' => $rows->values()->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filteredRows(): array
    {
        return collect($this->rows)
            ->filter(fn (array $row): bool => $this->matchesFilter($row))
            ->filter(fn (array $row): bool => $this->matchesSearch($row))
            ->values()
            ->all();
    }

    private function matchesFilter(array $row): bool
    {
        if ($this->tableMode === 'locks') {
            return match ($this->filter) {
                'locked' => in_array($row['path'] ?? null, $this->selectedPaths, true),
                'unlocked' => ! in_array($row['path'] ?? null, $this->selectedPaths, true),
                default => true,
            };
        }

        return match ($this->filter) {
            'added' => ($row['state'] ?? null) === 'added',
            'removed' => ($row['state'] ?? null) === 'removed',
            'all' => true,
            default => ($row['state'] ?? null) !== 'unchanged' || ($row['error'] ?? null) !== null,
        };
    }

    private function matchesSearch(array $row): bool
    {
        if (blank($this->search)) {
            return true;
        }

        $needle = str($this->search)->lower()->toString();
        $haystack = str(implode(' ', [
            $row['path'] ?? '',
            $row['label'] ?? '',
            $row['current_preview'] ?? '',
            $row['imported_preview'] ?? '',
        ]))->lower()->toString();

        return str_contains($haystack, $needle);
    }
}
