<?php

namespace App\Filament\Pages;

use App\Enums\MediaNamingStrategy;
use App\Enums\Tb1PickerContainer;
use App\Enums\TranscriptionMode;
use App\Enums\TranscriptionPresentationMode;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Settings\AdminUxSettings as AdminUxSettingsData;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
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
                            ->native(false)
                            ->required(),
                        Toggle::make('show_episode_workspace_hint_line')
                            ->label(__('admin.fields.show_episode_workspace_hint_line'))
                            ->helperText(__('admin.helpers.show_episode_workspace_hint_line'))
                            ->default(true),
                        Toggle::make('show_episode_workspace_language_code')
                            ->label(__('admin.fields.show_episode_workspace_language_code'))
                            ->helperText(__('admin.helpers.show_episode_workspace_language_code'))
                            ->default(false),
                        Select::make('tb1_picker_container')
                            ->label(__('admin.fields.tb1_picker_container'))
                            ->helperText(__('admin.helpers.tb1_picker_container'))
                            ->options(fn (): array => collect(Tb1PickerContainer::cases())
                                ->mapWithKeys(fn (Tb1PickerContainer $container): array => [$container->value => $container->getLabel()])
                                ->all())
                            ->default(Tb1PickerContainer::Modal->value)
                            ->native(false)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
