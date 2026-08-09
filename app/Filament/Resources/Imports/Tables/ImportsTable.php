<?php

namespace App\Filament\Resources\Imports\Tables;

use App\Enums\ImportConnectionProvider;
use App\Support\Dashboard\EditorialMetrics;
use App\Support\UiFormats;
use Filament\Actions\Action;
use Filament\Actions\Imports\Models\Import;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('failedRows'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    // The display rule everywhere: name ?: file_name.
                    ->label(__('admin.fields.title'))
                    ->state(fn (Import $record): string => (string) ($record->name ?: $record->file_name))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $inner) => $inner
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('file_name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('file_name')
                    ->label(__('admin.resources.imports.file_name'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider')
                    ->label(__('admin.importer.fields.provider'))
                    ->badge()
                    // One home for the null-means-manual rule.
                    ->state(fn (Import $record): string => ImportConnectionProvider::fromImportValue($record->provider)->getLabel()),
                TextColumn::make('failed_rows_count')
                    ->label(__('admin.resources.imports.failed_rows'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn (int $state): string => UiFormats::number($state)),
                TextColumn::make('successful_rows')
                    ->label(__('admin.resources.imports.successful_rows'))
                    ->formatStateUsing(fn (int $state): string => UiFormats::number($state)),
                TextColumn::make('total_rows')
                    ->label(__('admin.resources.imports.total_rows'))
                    ->formatStateUsing(fn (int $state): string => UiFormats::number($state)),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label(__('admin.resources.imports.completed_at'))
                    ->dateTime()
                    ->placeholder(__('admin.resources.imports.running'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label(__('admin.importer.fields.provider'))
                    ->options(ImportConnectionProvider::options())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->when(
                            $data['value'] === ImportConnectionProvider::Manual->value,
                            // Null provider = pre-column legacy row, a manual act.
                            fn (Builder $inner): Builder => $inner->where(
                                fn (Builder $nested) => $nested
                                    ->where('provider', ImportConnectionProvider::Manual->value)
                                    ->orWhereNull('provider'),
                            ),
                            fn (Builder $inner): Builder => $inner->where('provider', $data['value']),
                        ),
                    )),
                TernaryFilter::make('failed')
                    ->label(__('admin.resources.imports.failed_filter'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('failedRows'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('failedRows'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                Action::make('downloadFailedRows')
                    ->label(__('admin.resources.imports.download_failed_rows'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('danger')
                    // The aggregate is on the row (withCount) — no per-row
                    // service hop (service-hop-cost).
                    ->visible(fn (Import $record): bool => (int) $record->failed_rows_count > 0)
                    ->url(fn (Import $record): string => app(EditorialMetrics::class)->failedRowsDownloadUrl($record))
                    ->openUrlInNewTab(),
            ]);
    }
}
