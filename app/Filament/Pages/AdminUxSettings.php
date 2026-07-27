<?php

namespace App\Filament\Pages;

use App\Enums\MediaAcquisitionFilenameStrategy;
use App\Enums\MediaNamingStrategy;
use App\Enums\TranscriptionMode;
use App\Enums\TranscriptionPresentationMode;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Settings\AdminUxSettings as AdminUxSettingsData;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AdminUxSettings extends SettingsPage
{
    use UsesAdminNavigationOrder;

    protected static string $settings = AdminUxSettingsData::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.admin_ux_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.admin_ux_settings.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.admin_ux'))
                    ->description(__('admin.descriptions.admin_ux'))
                    ->schema([
                        Select::make('media_naming_strategy')
                            ->label(__('admin.fields.media_naming_strategy'))
                            ->helperText(__('admin.helpers.media_naming_strategy'))
                            ->options(fn (): array => collect(MediaNamingStrategy::cases())
                                ->mapWithKeys(fn (MediaNamingStrategy $strategy): array => [
                                    $strategy->value => __("admin.media_naming_strategies.{$strategy->value}"),
                                ])
                                ->all())
                            ->default(MediaNamingStrategy::Slug->value)
                            ->native(false)
                            ->required(),
                    ]),
                Section::make(__('admin.sections.media_acquisition'))
                    ->description(__('admin.descriptions.media_acquisition'))
                    ->schema([
                        TextInput::make('media_acquisition_max_kilobytes')
                            ->label(__('admin.fields.media_acquisition_max_kilobytes'))
                            ->helperText(__('admin.helpers.media_acquisition_max_kilobytes'))
                            ->integer()
                            ->minValue(64)
                            ->maxValue(10240)
                            ->default(2048)
                            ->required(),
                        TextInput::make('media_acquisition_max_dimension')
                            ->label(__('admin.fields.media_acquisition_max_dimension'))
                            ->helperText(__('admin.helpers.media_acquisition_max_dimension'))
                            ->integer()
                            ->minValue(64)
                            ->maxValue(10000)
                            ->default(3000)
                            ->required(),
                        TextInput::make('media_acquisition_upload_batch_limit')
                            ->label(__('admin.fields.media_acquisition_upload_batch_limit'))
                            ->helperText(__('admin.helpers.media_acquisition_upload_batch_limit'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(10)
                            ->required(),
                        TextInput::make('media_picker_browse_limit')
                            ->label(__('admin.fields.media_picker_browse_limit'))
                            ->helperText(__('admin.helpers.media_picker_browse_limit'))
                            ->integer()
                            ->minValue(10)
                            ->maxValue(100)
                            ->default(25)
                            ->required(),
                        TextInput::make('media_picker_search_limit')
                            ->label(__('admin.fields.media_picker_search_limit'))
                            ->helperText(__('admin.helpers.media_picker_search_limit'))
                            ->integer()
                            ->minValue(10)
                            ->maxValue(100)
                            ->default(50)
                            ->required(),
                        Select::make('media_acquisition_filename_strategy')
                            ->label(__('admin.fields.media_acquisition_filename_strategy'))
                            ->helperText(__('admin.helpers.media_acquisition_filename_strategy'))
                            ->options(fn (): array => collect(MediaAcquisitionFilenameStrategy::cases())
                                ->mapWithKeys(fn (MediaAcquisitionFilenameStrategy $strategy): array => [
                                    $strategy->value => __("admin.media_acquisition_filename_strategies.{$strategy->value}"),
                                ])
                                ->all())
                            ->default(MediaAcquisitionFilenameStrategy::AppGenerated->value)
                            ->native(false)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.episode_workspace'))
                    ->description(__('admin.descriptions.episode_workspace'))
                    ->schema([
                        Select::make('transcription_presentation_mode')
                            ->label(__('admin.fields.transcription_presentation_mode'))
                            ->helperText(__('admin.helpers.transcription_presentation_mode'))
                            ->options(fn (): array => collect(TranscriptionPresentationMode::cases())
                                ->mapWithKeys(fn (TranscriptionPresentationMode $mode): array => [$mode->value => $mode->getLabel()])
                                ->all())
                            ->default(TranscriptionPresentationMode::Collapsible->value)
                            ->native(false)
                            ->required(),
                        Select::make('transcription_mode')
                            ->label(__('admin.fields.transcription_mode'))
                            ->helperText(__('admin.helpers.transcription_mode'))
                            ->options(fn (): array => collect(TranscriptionMode::cases())
                                ->mapWithKeys(fn (TranscriptionMode $mode): array => [$mode->value => $mode->getLabel()])
                                ->all())
                            ->default(TranscriptionMode::Single->value)
                            ->live()
                            ->native(false)
                            ->required()
                            ->superAdminOnly(),
                        Toggle::make('show_episode_workspace_hint_line')
                            ->label(__('admin.fields.show_episode_workspace_hint_line'))
                            ->helperText(__('admin.helpers.show_episode_workspace_hint_line'))
                            ->default(true)
                            ->visible(fn (Get $get): bool => $get('transcription_mode') === TranscriptionMode::Multi->value),
                        Toggle::make('show_episode_workspace_language_code')
                            ->label(__('admin.fields.show_episode_workspace_language_code'))
                            ->helperText(__('admin.helpers.show_episode_workspace_language_code'))
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MultiTranscriptionSurfaces::overlayUnauthorizedSettings($data, AdminUxSettingsData::class);
    }
}
