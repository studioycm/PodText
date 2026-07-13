<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.user_role'))
                    ->description(__('admin.descriptions.user_role'))
                    ->schema([
                        Select::make('role')
                            ->label(__('admin.fields.role'))
                            ->helperText(__('admin.helpers.user_role'))
                            ->options(UserRole::options())
                            ->native(false)
                            ->required()
                            ->rules([Rule::enum(UserRole::class)])
                            ->superAdminOnly(),
                    ])
                    ->columns(1),
            ]);
    }
}
