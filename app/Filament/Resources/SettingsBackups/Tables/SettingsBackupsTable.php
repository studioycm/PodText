<?php

namespace App\Filament\Resources\SettingsBackups\Tables;

use App\Enums\SettingsBackupSource;
use App\Filament\Actions\ExportPublicSettingsAction;
use App\Filament\Pages\ImportPublicSettings;
use App\Models\SettingsBackupSnapshot;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use App\Support\SettingsLifecycle\SettingsBackupSnapshotManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class SettingsBackupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'snapshots' => fn ($query) => $query
                    ->where('screen_key', 'home')
                    ->where('kind', SettingsBackupSnapshot::KIND_THUMBNAIL)
                    ->where('format', SettingsBackupSnapshot::FORMAT_PNG)
                    ->where('status', SettingsBackupSnapshot::STATUS_DONE)
                    ->latest('id'),
            ]))
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('home_thumbnail')
                    ->label(__('admin.fields.home_thumbnail'))
                    ->state(fn (SettingsBackupVersion $record): ?string => $record->homeThumbnailSnapshot()?->fileUrl())
                    ->imageWidth(96)
                    ->imageHeight(54)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),
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
                ExportPublicSettingsAction::make(),
                Action::make('importSettings')
                    ->label(__('admin.actions.import_public_settings'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('gray')
                    ->url(fn (): string => ImportPublicSettings::getUrl()),
                Action::make('createBackup')
                    ->label(__('admin.actions.create_backup'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->schema([
                        TextInput::make('label')
                            ->label(__('admin.fields.label'))
                            ->maxLength(255),
                        CheckboxList::make('snapshot_formats')
                            ->label(__('admin.fields.snapshot_formats'))
                            ->options(self::snapshotFormatOptions())
                            ->default(fn (): array => self::defaultSettingsBackupConfig()['snapshot_formats'])
                            ->columns(3)
                            ->required(),
                        CheckboxList::make('snapshot_themes')
                            ->label(__('admin.fields.snapshot_themes'))
                            ->options(self::snapshotThemeOptions())
                            ->default(fn (): array => self::defaultSettingsBackupConfig()['snapshot_themes'])
                            ->columns(2)
                            ->required(),
                    ])
                    ->modalSubmitActionLabel(__('admin.actions.create_backup'))
                    ->action(function (array $data): void {
                        $user = auth()->user();
                        $backup = app(SettingsBackupManager::class)->createManual(
                            $data['label'] ?? null,
                            $user instanceof User ? $user : null,
                            $data['snapshot_formats'] ?? null,
                            $data['snapshot_themes'] ?? null,
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
                    Action::make('snapshots')
                        ->label(__('admin.actions.snapshots'))
                        ->icon(Heroicon::OutlinedPhoto)
                        ->color('gray')
                        ->slideOver()
                        ->modalHeading(__('admin.actions.snapshots'))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('admin.actions.close'))
                        ->modalContent(fn (SettingsBackupVersion $record): View => view('filament.settings-backups.snapshots-gallery', [
                            'backup' => $record,
                        ])),
                    DeleteAction::make()
                        ->label(__('admin.actions.delete'))
                        ->using(fn (SettingsBackupVersion $record) => app(SettingsBackupSnapshotManager::class)->deleteBackup($record)),
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

    /**
     * @return array<string, string>
     */
    private static function snapshotFormatOptions(): array
    {
        return collect(PublicFrontConfigRegistry::settingsBackupSnapshotFormats())
            ->mapWithKeys(fn (string $format): array => [$format => __("admin.settings_backup_snapshot_formats.{$format}")])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function snapshotThemeOptions(): array
    {
        return collect(PublicFrontConfigRegistry::settingsBackupSnapshotThemes())
            ->mapWithKeys(fn (string $theme): array => [$theme => __("admin.settings_backup_snapshot_themes.{$theme}")])
            ->all();
    }

    /**
     * @return array{snapshot_formats: array<int, string>, snapshot_themes: array<int, string>}
     */
    private static function defaultSettingsBackupConfig(): array
    {
        return app(PublicContentSettings::class)->settings_backups
            ?? PublicFrontConfigRegistry::defaults()['settings_backups'];
    }
}
