<?php

namespace App\Filament\Pages;

use App\Filament\Actions\ExportPublicSettingsAction;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Filament\Support\PublicFormsSettingsForm;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManagePublicForms extends PublicContentSettingsSubjectPage
{
    use UsesAdminNavigationOrder;

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.manage_public_forms.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_public_forms.title');
    }

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::PUBLIC_FORMS;
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportPublicSettingsAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $schema = $schema->components([
            $this->withImportLockSection(
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
                'public_forms',
                'public-front-forms',
            ),
        ]);

        $this->applyInlineImportLockHints($schema->getComponents());

        return $schema;
    }
}
