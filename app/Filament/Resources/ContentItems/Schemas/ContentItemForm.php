<?php

namespace App\Filament\Resources\ContentItems\Schemas;

use App\Enums\UserRole;
use App\Filament\Forms\Components\PublicationStatusSelect;
use App\Filament\Forms\Components\SlugInput;
use App\Filament\Forms\Components\TrustedHtmlCodeEditor;
use App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager;
use App\Filament\Resources\Support\RelationshipOptionForms;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Rules\ApprovedEmbedUrl;
use App\Support\Media\ContentItemMediaRules;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component as Livewire;

class ContentItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->description(__('admin.descriptions.content_item_identity'))
                    ->schema([
                        TextInput::make('reference_key')
                            ->label(__('admin.fields.reference_key'))
                            ->helperText(__('admin.helpers.reference_key'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        RelationshipOptionForms::configureContentGroupSelect(
                            Select::make('content_group_id')
                                ->label(__('admin.fields.content_group'))
                                ->helperText(__('admin.helpers.content_item_content_group'))
                                ->relationship('contentGroup', 'title')
                                ->searchable()
                                ->preload(false)
                                ->optionsLimit(50)
                                ->hiddenOn(ContentItemsRelationManager::class)
                                ->required()
                        ),
                        SlugInput::source(
                            'title',
                            table: 'content_items',
                            scopeUsing: self::scopeSlugToContentGroup(...)
                        )
                            ->label(__('admin.fields.title'))
                            ->helperText(__('admin.helpers.content_item_title'))
                            ->required()
                            ->maxLength(255),
                        SlugInput::slug(
                            source: 'title',
                            table: 'content_items',
                            scopeUsing: self::scopeSlugToContentGroup(...),
                            modifyRuleUsing: fn (Unique $rule, Get $get, ?Livewire $livewire = null): Unique => $rule
                                ->where('content_group_id', self::contentGroupIdForSlug($get, $livewire)),
                        )
                            ->label(__('admin.fields.slug'))
                            ->helperText(__('admin.helpers.content_item_slug')),
                        TextInput::make('type_label_singular_override')
                            ->label(__('admin.fields.type_label_singular_override'))
                            ->helperText(__('admin.helpers.type_label_singular_override'))
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.content'))
                    ->description(__('admin.descriptions.content_item_content'))
                    ->schema([
                        MarkdownEditor::make('description_markdown')
                            ->label(__('admin.fields.description_markdown'))
                            ->helperText(__('admin.helpers.content_item_description'))
                            ->disableToolbarButtons(['attachFiles'])
                            ->fileAttachments(false)
                            ->columnSpanFull(),
                        TextInput::make('media_url')
                            ->label(__('admin.fields.media_url'))
                            ->helperText(__('admin.helpers.media_url'))
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->rules(['starts_with:https://'])
                            ->validationMessages([
                                'media_url.starts_with' => __('admin.validation.media_url_https'),
                            ]),
                        TextInput::make('embed_url')
                            ->label(__('admin.fields.embed_url'))
                            ->helperText(__('admin.helpers.embed_url'))
                            ->url()
                            ->maxLength(2048)
                            ->rules([new ApprovedEmbedUrl])
                            ->validationMessages([
                                'embed_url.url' => __('admin.validation.embed_url_url'),
                            ]),
                        TrustedHtmlCodeEditor::make('embed_html')
                            ->label(__('admin.fields.embed_html'))
                            ->helperText(__('admin.helpers.embed_html'))
                            ->columnSpanFull(),
                        TextInput::make('duration_seconds')
                            ->label(__('admin.fields.duration_seconds'))
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        RelationshipOptionForms::configureCategorySelect(
                            Select::make('categories')
                                ->label(__('admin.fields.categories'))
                                ->relationship('categories', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload(false)
                                ->optionsLimit(50)
                                ->helperText(__('admin.helpers.item_categories')),
                            allowEdit: false,
                        ),
                        SpatieTagsInput::make('tags')
                            ->label(__('admin.fields.tags'))
                            ->type('content')
                            ->helperText(__('admin.helpers.content_tags')),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.featured_transcription'))
                    ->description(__('admin.descriptions.featured_transcription'))
                    ->schema([
                        Select::make('featured_transcription_id')
                            ->label(__('admin.fields.featured_transcription'))
                            ->helperText(__('admin.helpers.featured_transcription'))
                            ->options(fn (?ContentItem $record): array => $record
                                ? $record->transcriptions()
                                    ->latest('published_at')
                                    ->latest('id')
                                    ->get()
                                    ->mapWithKeys(fn ($transcription): array => [
                                        $transcription->getKey() => $transcription->title ?: __('admin.labels.untitled_transcription', ['id' => $transcription->getKey()]),
                                    ])
                                    ->all()
                                : [])
                            ->searchable(),
                    ])
                    ->visible(fn (?ContentItem $record): bool => $record
                        && $record->transcriptions()->count() > 1)
                    ->multiTranscription(UserRole::Admin),
                Section::make(__('admin.sections.pinning'))
                    ->description(__('admin.descriptions.pinning'))
                    ->schema([
                        Toggle::make('is_pinned')
                            ->label(__('admin.fields.is_pinned'))
                            ->helperText(__('admin.helpers.is_pinned')),
                        DateTimePicker::make('pinned_at')
                            ->label(__('admin.fields.pinned_at'))
                            ->helperText(__('admin.helpers.pinned_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem'),
                        DateTimePicker::make('pinned_until')
                            ->label(__('admin.fields.pinned_until'))
                            ->helperText(__('admin.helpers.pinned_until'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem'),
                        TextInput::make('pin_order')
                            ->label(__('admin.fields.pin_order'))
                            ->helperText(__('admin.helpers.pin_order'))
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                    ])
                    ->columns(4),
                Section::make(__('admin.sections.media_metadata'))
                    ->description(__('admin.descriptions.media_metadata'))
                    ->schema([
                        TextInput::make('embed_provider')
                            ->label(__('admin.fields.embed_provider'))
                            ->helperText(__('admin.helpers.embed_provider'))
                            ->maxLength(50),
                        TextInput::make('external_id')
                            ->label(__('admin.fields.external_id'))
                            ->helperText(__('admin.helpers.external_id'))
                            ->maxLength(255),
                        TextInput::make('external_title')
                            ->label(__('admin.fields.external_title'))
                            ->helperText(__('admin.helpers.external_title'))
                            ->maxLength(255),
                        TextInput::make('external_description')
                            ->label(__('admin.fields.external_description'))
                            ->helperText(__('admin.helpers.external_description'))
                            ->maxLength(2048),
                        TextInput::make('external_thumbnail_url')
                            ->label(__('admin.fields.external_thumbnail_url'))
                            ->helperText(__('admin.helpers.external_thumbnail_url'))
                            ->url()
                            ->maxLength(2048)
                            ->rules(ContentItemMediaRules::rules()['external_thumbnail_url']),
                        DateTimePicker::make('external_published_at')
                            ->label(__('admin.fields.external_published_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem'),
                        TextInput::make('direct_media_url')
                            ->label(__('admin.fields.direct_media_url'))
                            ->helperText(__('admin.helpers.direct_media_url'))
                            ->url()
                            ->maxLength(2048)
                            ->rules(ContentItemMediaRules::rules()['direct_media_url']),
                        TextInput::make('media_duration_seconds')
                            ->label(__('admin.fields.media_duration_seconds'))
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        KeyValue::make('media_metadata')
                            ->label(__('admin.fields.media_metadata'))
                            ->helperText(__('admin.helpers.media_metadata'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                Section::make(__('admin.sections.publication'))
                    ->description(__('admin.descriptions.content_item_publication'))
                    ->schema([
                        PublicationStatusSelect::make('status')
                            ->label(__('admin.fields.status'))
                            ->helperText(__('admin.helpers.content_item_status'))
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label(__('admin.fields.published_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem'),
                        DateTimePicker::make('original_published_at')
                            ->label(__('admin.fields.original_published_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem'),
                    ])
                    ->columns(3),
            ]);
    }

    private static function scopeSlugToContentGroup(QueryBuilder $query, Get $get, ?Model $record = null, ?Livewire $livewire = null): QueryBuilder
    {
        return $query->where('content_group_id', self::contentGroupIdForSlug($get, $livewire));
    }

    private static function contentGroupIdForSlug(Get $get, ?Livewire $livewire = null): mixed
    {
        $contentGroupId = $get('content_group_id');

        if (filled($contentGroupId)) {
            return $contentGroupId;
        }

        if ($livewire === null || ! method_exists($livewire, 'getOwnerRecord')) {
            return $contentGroupId;
        }

        $ownerRecord = $livewire->getOwnerRecord();

        return $ownerRecord instanceof ContentGroup
            ? $ownerRecord->getKey()
            : $contentGroupId;
    }
}
