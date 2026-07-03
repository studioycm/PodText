<?php

namespace App\Filament\Resources\Support;

use App\Enums\PublicationStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

class RelationshipOptionForms
{
    public static function configureAuthorSelect(Select $select, bool $allowEdit = true): Select
    {
        $select = self::configureCreateOption(
            select: $select,
            schema: self::authorForm(),
            headingKey: 'admin.modals.create_author',
            labelKey: 'admin.actions.create_author',
        );

        if (! $allowEdit) {
            return $select;
        }

        return self::configureEditOption(
            select: $select,
            schema: self::authorForm(),
            headingKey: 'admin.modals.edit_author',
            labelKey: 'admin.actions.edit_author',
        );
    }

    public static function configureCategorySelect(Select $select, bool $allowEdit = true): Select
    {
        $select = self::configureCreateOption(
            select: $select,
            schema: self::categoryForm(),
            headingKey: 'admin.modals.create_category',
            labelKey: 'admin.actions.create_category',
        );

        if (! $allowEdit) {
            return $select;
        }

        return self::configureEditOption(
            select: $select,
            schema: self::categoryForm(),
            headingKey: 'admin.modals.edit_category',
            labelKey: 'admin.actions.edit_category',
        );
    }

    public static function configureContentGroupSelect(Select $select, bool $allowEdit = true): Select
    {
        $select = self::configureCreateOption(
            select: $select,
            schema: self::contentGroupForm(),
            headingKey: 'admin.modals.create_content_group',
            labelKey: 'admin.actions.create_content_group',
        );

        if (! $allowEdit) {
            return $select;
        }

        return self::configureEditOption(
            select: $select,
            schema: self::contentGroupForm(),
            headingKey: 'admin.modals.edit_content_group',
            labelKey: 'admin.actions.edit_content_group',
        );
    }

    public static function configureContentTagSelect(Select $select): Select
    {
        return self::configureCreateOption(
            select: $select,
            schema: self::contentTagForm(),
            headingKey: 'admin.modals.create_content_tag',
            labelKey: 'admin.actions.create_content_tag',
        );
    }

    /**
     * @param  array<int, mixed>  $schema
     */
    private static function configureCreateOption(Select $select, array $schema, string $headingKey, string $labelKey): Select
    {
        return $select
            ->createOptionForm($schema)
            ->createOptionModalHeading(__($headingKey))
            ->createOptionAction(fn (Action $action): Action => self::modalAction($action, $labelKey));
    }

    /**
     * @param  array<int, mixed>  $schema
     */
    private static function configureEditOption(Select $select, array $schema, string $headingKey, string $labelKey): Select
    {
        return $select
            ->editOptionForm($schema)
            ->editOptionModalHeading(__($headingKey))
            ->editOptionAction(fn (Action $action): Action => self::modalAction($action, $labelKey));
    }

    private static function modalAction(Action $action, string $labelKey): Action
    {
        return $action
            ->label(__($labelKey))
            ->modalWidth(Width::ThreeExtraLarge);
    }

    /**
     * @return array<int, mixed>
     */
    private static function authorForm(): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin.fields.author_name'))
                ->live(onBlur: true)
                ->afterStateUpdated(self::syncSlugFrom(...))
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label(__('admin.fields.slug'))
                ->helperText(__('admin.helpers.slug'))
                ->required()
                ->maxLength(255)
                ->unique(),
            MarkdownEditor::make('bio_markdown')
                ->label(__('admin.fields.bio_markdown'))
                ->disableToolbarButtons(['attachFiles'])
                ->fileAttachments(false)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function categoryForm(): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin.fields.name'))
                ->live(onBlur: true)
                ->afterStateUpdated(self::syncSlugFrom(...))
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label(__('admin.fields.slug'))
                ->helperText(__('admin.helpers.slug'))
                ->required()
                ->maxLength(255)
                ->unique(),
            Toggle::make('is_visible')
                ->label(__('admin.fields.is_visible'))
                ->default(true)
                ->required(),
            TextInput::make('sort_order')
                ->label(__('admin.fields.sort_order'))
                ->helperText(__('admin.helpers.sort_order'))
                ->required()
                ->numeric()
                ->integer()
                ->default(0),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function contentGroupForm(): array
    {
        return [
            TextInput::make('title')
                ->label(__('admin.fields.title'))
                ->live(onBlur: true)
                ->afterStateUpdated(self::syncSlugFrom(...))
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label(__('admin.fields.slug'))
                ->helperText(__('admin.helpers.slug'))
                ->required()
                ->maxLength(255)
                ->unique(),
            Select::make('original_language_code')
                ->label(__('admin.fields.original_language_code'))
                ->options(fn (): array => collect(config('localization.available_locales', ['he', 'en']))
                    ->mapWithKeys(fn (string $locale): array => [$locale => __("admin.locales.{$locale}")])
                    ->all())
                ->default('he')
                ->required(),
            Select::make('status')
                ->label(__('admin.fields.status'))
                ->options(PublicationStatus::class)
                ->default(PublicationStatus::Draft->value)
                ->required(),
            DateTimePicker::make('published_at')
                ->label(__('admin.fields.published_at'))
                ->displayFormat('d/m/Y H:i')
                ->timezone('Asia/Jerusalem'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function contentTagForm(): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin.fields.name'))
                ->helperText(__('admin.helpers.content_tag_name'))
                ->required()
                ->maxLength(255),
            TextInput::make('type')
                ->label(__('admin.fields.tag_type'))
                ->helperText(__('admin.helpers.tag_type'))
                ->default('content')
                ->disabled()
                ->dehydrated(),
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
        ];
    }

    private static function syncSlugFrom(Set $set, Get $get, ?string $old, ?string $state): void
    {
        if (filled($get('slug')) && $get('slug') !== Str::slug((string) $old)) {
            return;
        }

        $set('slug', Str::slug((string) $state));
    }
}
