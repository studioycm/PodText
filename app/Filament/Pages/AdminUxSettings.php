<?php

namespace App\Filament\Pages;

use App\Enums\MediaNamingStrategy;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Settings\AdminUxSettings as AdminUxSettingsData;
use BackedEnum;
use Filament\Forms\Components\Select;
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

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.content');
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
            ]);
    }
}
