<?php

namespace App\Filament\Pages;

use App\Filament\Actions\ExportPublicSettingsAction;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Filament\Support\PublicFormsSettingsForm;
use App\Settings\PublicContentSettings as PublicContentSettingsData;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigValidator;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManagePublicForms extends SettingsPage
{
    use UsesAdminNavigationOrder;

    protected static string $settings = PublicContentSettingsData::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.manage_public_forms.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_public_forms.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportPublicSettingsAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.public_front_forms'))
                    ->description(__('admin.descriptions.public_front_forms'))
                    ->schema([
                        Toggle::make('public_forms.require_email_verification')
                            ->label(__('admin.fields.public_forms_require_email_verification'))
                            ->helperText(__('admin.helpers.public_forms_require_email_verification'))
                            ->default(false),
                        PublicFormsSettingsForm::definitionsRepeater(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $publicForms = app(PublicFrontConfigReader::class)
            ->fromArray($data)
            ->group('public_forms');

        return [
            'public_forms' => PublicFormsSettingsForm::publicFormsForBuilder($publicForms),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $publicForms = app(PublicFrontConfigValidator::class)
            ->validate([
                'public_forms' => $data['public_forms'] ?? [],
            ])
            ->group('public_forms');

        return [
            'public_forms' => $publicForms,
        ];
    }
}
