<?php

namespace App\Filament\Pages;

use App\Enums\ImportConnectionAuthType;
use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\ImportConnection;
use App\Support\Importer\ConnectionTester;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use JsonException;
use Throwable;

class ImporterSettings extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use UsesAdminNavigationOrder;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $slug = 'importer-settings';

    protected string $view = 'filament.pages.importer-settings';

    public static function getNavigationLabel(): string
    {
        return __('admin.importer.pages.settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.importer.pages.settings.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ImportConnection::query())
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider')
                    ->label(__('admin.importer.fields.provider'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('auth_type')
                    ->label(__('admin.importer.fields.auth_type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.importer.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('last_tested_at')
                    ->label(__('admin.importer.fields.last_tested_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->placeholder(__('admin.placeholders.empty'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label(__('admin.importer.fields.provider'))
                    ->options(ImportConnectionProvider::class),
                SelectFilter::make('auth_type')
                    ->label(__('admin.importer.fields.auth_type'))
                    ->options(ImportConnectionAuthType::class),
                SelectFilter::make('status')
                    ->label(__('admin.importer.fields.status'))
                    ->options(ImportConnectionStatus::class),
            ])
            ->headerActions([
                Action::make('createConnection')
                    ->label(__('admin.importer.actions.create_connection'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema(fn (): array => $this->connectionActionSchema())
                    ->modalSubmitActionLabel(__('admin.actions.save'))
                    ->action(function (array $data): void {
                        ImportConnection::query()->create($this->connectionData($data));

                        Notification::make()
                            ->success()
                            ->title(__('admin.importer.notifications.connection_created'))
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('testConnection')
                    ->label(__('admin.importer.actions.test_connection'))
                    ->icon(Heroicon::OutlinedBolt)
                    ->color('info')
                    ->action(function (ImportConnection $record): void {
                        $this->testConnection($record);
                    }),
                Action::make('connectGoogleOAuth')
                    ->label(__('admin.importer.actions.connect_google_oauth'))
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->visible(fn (ImportConnection $record): bool => $record->provider === ImportConnectionProvider::GoogleDrive
                        && $record->auth_type === ImportConnectionAuthType::OAuth)
                    ->url(fn (ImportConnection $record): string => route('admin.importer.google.redirect', $record)),
                Action::make('editConnection')
                    ->label(__('admin.actions.edit'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->schema(fn (ImportConnection $record): array => $this->connectionActionSchema($record))
                    ->fillForm(fn (ImportConnection $record): array => $this->connectionFormData($record))
                    ->modalSubmitActionLabel(__('admin.actions.save'))
                    ->action(function (ImportConnection $record, array $data): void {
                        $record->update($this->connectionData($data, $record));

                        Notification::make()
                            ->success()
                            ->title(__('admin.importer.notifications.connection_updated'))
                            ->send();
                    }),
                DeleteAction::make('deleteConnection')
                    ->label(__('admin.actions.delete')),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function connectionActionSchema(?ImportConnection $record = null): array
    {
        return [
            Section::make(__('admin.sections.identity'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin.fields.name'))
                        ->helperText(__('admin.importer.helpers.name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('provider')
                        ->label(__('admin.importer.fields.provider'))
                        ->helperText(__('admin.importer.helpers.provider'))
                        ->options(ImportConnectionProvider::class)
                        ->live()
                        ->required()
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $provider = self::providerFromState($state);
                            $set('auth_type', $provider?->defaultAuthType()->value);
                        }),
                    Select::make('auth_type')
                        ->label(__('admin.importer.fields.auth_type'))
                        ->helperText(__('admin.importer.helpers.auth_type'))
                        ->options(fn (Get $get): array => self::providerFromState($get('provider'))?->authTypeOptions() ?? [])
                        ->live()
                        ->required()
                        ->visible(fn (Get $get): bool => filled($get('provider'))),
                ])
                ->columns(3),
            Section::make(__('admin.importer.sections.credentials'))
                ->description(__('admin.importer.descriptions.credentials'))
                ->schema([
                    Textarea::make('service_account_json')
                        ->label(__('admin.importer.fields.service_account_json'))
                        ->helperText(__('admin.importer.helpers.service_account_json'))
                        ->rows(8)
                        ->rules(['nullable', 'json'])
                        ->required(fn (Get $get): bool => $record === null
                            && self::providerFromState($get('provider')) === ImportConnectionProvider::GoogleDrive
                            && self::authTypeFromState($get('auth_type')) === ImportConnectionAuthType::ServiceAccount)
                        ->visible(fn (Get $get): bool => self::providerFromState($get('provider')) === ImportConnectionProvider::GoogleDrive
                            && self::authTypeFromState($get('auth_type')) === ImportConnectionAuthType::ServiceAccount)
                        ->columnSpanFull(),
                    TextEntry::make('oauth_connect_hint')
                        ->label(__('admin.importer.fields.oauth_connection'))
                        ->state(__('admin.importer.helpers.oauth_connection'))
                        ->visible(fn (Get $get): bool => self::providerFromState($get('provider')) === ImportConnectionProvider::GoogleDrive
                            && self::authTypeFromState($get('auth_type')) === ImportConnectionAuthType::OAuth)
                        ->columnSpanFull(),
                    TextInput::make('spotify_client_id')
                        ->label(__('admin.importer.fields.spotify_client_id'))
                        ->helperText(__('admin.importer.helpers.spotify_client_id'))
                        ->required(fn (Get $get): bool => $record === null
                            && self::providerFromState($get('provider')) === ImportConnectionProvider::Spotify
                            && self::authTypeFromState($get('auth_type')) === ImportConnectionAuthType::ClientCredentials)
                        ->visible(fn (Get $get): bool => self::providerFromState($get('provider')) === ImportConnectionProvider::Spotify
                            && self::authTypeFromState($get('auth_type')) === ImportConnectionAuthType::ClientCredentials)
                        ->maxLength(255),
                    TextInput::make('spotify_client_secret')
                        ->label(__('admin.importer.fields.spotify_client_secret'))
                        ->helperText(__('admin.importer.helpers.spotify_client_secret'))
                        ->password()
                        ->revealable()
                        ->required(fn (Get $get): bool => $record === null
                            && self::providerFromState($get('provider')) === ImportConnectionProvider::Spotify
                            && self::authTypeFromState($get('auth_type')) === ImportConnectionAuthType::ClientCredentials)
                        ->visible(fn (Get $get): bool => self::providerFromState($get('provider')) === ImportConnectionProvider::Spotify
                            && self::authTypeFromState($get('auth_type')) === ImportConnectionAuthType::ClientCredentials)
                        ->maxLength(255),
                ])
                ->columns(2)
                ->visible(fn (Get $get): bool => filled($get('provider')) && filled($get('auth_type'))),
            Section::make(__('admin.importer.sections.defaults'))
                ->description(__('admin.importer.descriptions.defaults'))
                ->schema([
                    TextInput::make('settings.spreadsheet_id')
                        ->label(__('admin.importer.fields.default_spreadsheet_id'))
                        ->helperText(__('admin.importer.helpers.default_spreadsheet_id'))
                        ->maxLength(255),
                    TextInput::make('settings.folder_id')
                        ->label(__('admin.importer.fields.default_folder_id'))
                        ->helperText(__('admin.importer.helpers.default_folder_id'))
                        ->maxLength(255),
                ])
                ->columns(2)
                ->collapsed()
                ->visible(fn (Get $get): bool => self::providerFromState($get('provider')) === ImportConnectionProvider::GoogleDrive),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionFormData(ImportConnection $record): array
    {
        return [
            'auth_type' => $record->auth_type->value,
            'name' => $record->name,
            'provider' => $record->provider->value,
            'settings' => $record->settings ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function connectionData(array $data, ?ImportConnection $record = null): array
    {
        $provider = self::providerFromState($data['provider']) ?? ImportConnectionProvider::Manual;
        $authType = self::authTypeFromState($data['auth_type']) ?? $provider->defaultAuthType();

        return [
            'auth_type' => $authType,
            'credentials' => $this->credentialsData($provider, $authType, $data, $record),
            'name' => $data['name'],
            'provider' => $provider,
            'settings' => ImportConnection::normalizeArray($data['settings'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function credentialsData(
        ImportConnectionProvider $provider,
        ImportConnectionAuthType $authType,
        array $data,
        ?ImportConnection $record,
    ): array {
        $existing = $record?->credentials ?? [];

        if ($provider === ImportConnectionProvider::GoogleDrive && $authType === ImportConnectionAuthType::ServiceAccount) {
            $json = trim((string) ($data['service_account_json'] ?? ''));

            if ($json === '') {
                return $existing;
            }

            return [
                'service_account' => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            ];
        }

        if ($provider === ImportConnectionProvider::GoogleDrive && $authType === ImportConnectionAuthType::OAuth) {
            return $existing;
        }

        if ($provider === ImportConnectionProvider::Spotify && $authType === ImportConnectionAuthType::ClientCredentials) {
            return ImportConnection::normalizeArray([
                'client_id' => $data['spotify_client_id'] ?? data_get($existing, 'client_id'),
                'client_secret' => $data['spotify_client_secret'] ?? data_get($existing, 'client_secret'),
            ]);
        }

        return [];
    }

    private function testConnection(ImportConnection $record): void
    {
        try {
            $result = app(ConnectionTester::class)->test($record);
            $record->markTested($result->successful ? ImportConnectionStatus::Connected : ImportConnectionStatus::Failed);

            Notification::make()
                ->title($result->title)
                ->body($result->details === [] ? null : implode(PHP_EOL, $result->details))
                ->{$result->successful ? 'success' : 'danger'}()
                ->send();
        } catch (Throwable) {
            $record->markTested(ImportConnectionStatus::Failed);

            Notification::make()
                ->danger()
                ->title(__('admin.importer.notifications.connection_test_failed'))
                ->body(__('admin.importer.notifications.connection_test_failed_body'))
                ->send();
        }
    }

    private static function providerFromState(mixed $state): ?ImportConnectionProvider
    {
        if ($state instanceof ImportConnectionProvider) {
            return $state;
        }

        return is_string($state) ? ImportConnectionProvider::tryFrom($state) : null;
    }

    private static function authTypeFromState(mixed $state): ?ImportConnectionAuthType
    {
        if ($state instanceof ImportConnectionAuthType) {
            return $state;
        }

        return is_string($state) ? ImportConnectionAuthType::tryFrom($state) : null;
    }
}
