<?php

namespace App\Filament\Resources\ContentTags\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContentTagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.fields.name'))
                            ->helperText(__('admin.helpers.content_tag_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('admin.fields.slug'))
                            ->helperText(__('admin.helpers.content_tag_slug'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        TextInput::make('type')
                            ->label(__('admin.fields.tag_type'))
                            ->helperText(__('admin.helpers.tag_type'))
                            ->default('content')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.visibility_order'))
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label(__('admin.fields.is_enabled'))
                            ->helperText(__('admin.helpers.is_enabled'))
                            ->default(false),
                        DateTimePicker::make('enabled_at')
                            ->label(__('admin.fields.enabled_at'))
                            ->helperText(__('admin.helpers.enabled_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem'),
                        TextInput::make('order_column')
                            ->label(__('admin.fields.sort_order'))
                            ->helperText(__('admin.helpers.sort_order'))
                            ->numeric()
                            ->integer()
                            ->default(0),
                        TextInput::make('moderation_state')
                            ->label(__('admin.fields.moderation_state'))
                            ->helperText(__('admin.helpers.moderation_state'))
                            ->maxLength(50),
                    ])
                    ->columns(2),
            ]);
    }
}
