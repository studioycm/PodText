<?php

namespace App\Filament\Resources\SettingsBackups\Tables;

use App\Enums\SettingsBackupSource;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class SettingsBackupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable(),
                TextColumn::make('source')
                    ->label(__('admin.fields.source'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('label')
                    ->label(__('admin.fields.label'))
                    ->placeholder(__('admin.placeholders.empty'))
                    ->searchable(),
                TextColumn::make('payload_hash')
                    ->label(__('admin.fields.payload_hash'))
                    ->formatStateUsing(fn (string $state): string => str($state)->substr(0, 12)->toString())
                    ->copyable(),
                TextColumn::make('payload_size')
                    ->label(__('admin.fields.payload_size'))
                    ->state(fn (SettingsBackupVersion $record): string => Number::fileSize($record->packageSize())),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label(__('admin.fields.source'))
                    ->options(SettingsBackupSource::class),
            ])
            ->headerActions([
                Action::make('createBackup')
                    ->label(__('admin.actions.create_backup'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->schema([
                        TextInput::make('label')
                            ->label(__('admin.fields.label'))
                            ->maxLength(255),
                    ])
                    ->modalSubmitActionLabel(__('admin.actions.create_backup'))
                    ->action(function (array $data): void {
                        $user = auth()->user();
                        $backup = app(SettingsBackupManager::class)->createManual(
                            $data['label'] ?? null,
                            $user instanceof User ? $user : null,
                        );

                        Notification::make()
                            ->success()
                            ->title(__('admin.notifications.settings_backup_created'))
                            ->body(__('admin.notifications.settings_backup_created_body', ['id' => $backup->getKey()]))
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('download')
                        ->label(__('admin.actions.download'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('gray')
                        ->action(fn (SettingsBackupVersion $record) => response()->streamDownload(
                            function () use ($record): void {
                                echo $record->payload_json;
                            },
                            $record->downloadFilename(),
                            ['Content-Type' => 'application/json; charset=UTF-8'],
                        )),
                    Action::make('compare')
                        ->label(__('admin.actions.compare'))
                        ->icon(Heroicon::OutlinedScale)
                        ->color('info')
                        ->modalHeading(__('admin.actions.compare_backup'))
                        ->schema(fn (SettingsBackupVersion $record): array => self::diffSchema($record))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('admin.actions.close')),
                    Action::make('restore')
                        ->label(__('admin.actions.restore'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(__('admin.actions.restore_backup'))
                        ->modalDescription(fn (SettingsBackupVersion $record): string => app(SettingsBackupManager::class)->compare($record)->summaryText())
                        ->action(function (SettingsBackupVersion $record): void {
                            $user = auth()->user();

                            app(SettingsBackupManager::class)->restore(
                                $record,
                                $user instanceof User ? $user : null,
                            );

                            Notification::make()
                                ->success()
                                ->title(__('admin.notifications.settings_backup_restored'))
                                ->send();
                        }),
                    DeleteAction::make()
                        ->label(__('admin.actions.delete')),
                ]),
            ]);
    }

    /**
     * @return array<int, Section>
     */
    private static function diffSchema(SettingsBackupVersion $record): array
    {
        $diff = app(SettingsBackupManager::class)->compare($record);

        return [
            Section::make(__('admin.sections.settings_backup_diff_summary'))
                ->schema([
                    TextEntry::make('summary')
                        ->label(__('admin.fields.summary'))
                        ->state($diff->summaryText()),
                ]),
            Section::make(__('admin.sections.settings_backup_diff'))
                ->schema([
                    TextEntry::make('changes')
                        ->label(__('admin.fields.changes'))
                        ->state($diff->lines())
                        ->listWithLineBreaks()
                        ->columnSpanFull(),
                ]),
        ];
    }
}
