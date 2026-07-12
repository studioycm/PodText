<?php

namespace App\Filament\Resources\ContentItems\Schemas;

use App\Enums\PublicationStatus;
use App\Filament\Forms\Components\SlugInput;
use App\Filament\Forms\MediaPickerField;
use App\Filament\Public\Pages\ShowContentItem;
use App\Filament\Resources\Support\RelationshipOptionForms;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Rules\ApprovedEmbedUrl;
use App\Settings\AdminUxSettings;
use App\Support\Media\ContentItemMediaRules;
use App\Support\Media\EpisodeEmbedInputNormalizer;
use App\Support\Media\EpisodeSpotifyLookup;
use App\Support\Media\ImageFileNamer;
use App\Support\PublicFront\ContentItemDisplayTitle;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component as Livewire;
use Throwable;

class EpisodeWorkspaceForm
{
    public static function configure(Schema $schema): Schema
    {
        $settings = app(AdminUxSettings::class);

        return $schema
            ->components([
                Section::make(__('admin.sections.episode_workspace_identity'))
                    ->description(__('admin.descriptions.episode_workspace_identity'))
                    ->schema([
                        RelationshipOptionForms::configureContentGroupSelect(
                            Select::make('content_group_id')
                                ->label(__('admin.fields.content_group'))
                                ->helperText(__('admin.helpers.content_item_content_group'))
                                ->relationship('contentGroup', 'title')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                    if (filled($get('title_prefix')) || blank($state)) {
                                        return;
                                    }

                                    $set('title_prefix', ContentGroup::query()->whereKey($state)->value('title'));
                                })
                                ->required()
                        ),
                        TextInput::make('title_prefix')
                            ->label(__('admin.fields.title_prefix'))
                            ->helperText(__('admin.helpers.title_prefix'))
                            ->maxLength(255),
                        SlugInput::source(
                            'title',
                            table: 'content_items',
                            scopeUsing: self::scopeSlugToContentGroup(...),
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
                        Html::make(fn (Get $get, ?ContentItem $record): Htmlable => self::combinedTitlePreview($get, $record))
                            ->columnSpanFull(),
                        Html::make(fn (Get $get, ?ContentItem $record): Htmlable => self::publicUrlPreview($get, $record))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.episode_workspace_media'))
                    ->description(__('admin.descriptions.episode_workspace_media'))
                    ->schema([
                        MarkdownEditor::make('description_markdown')
                            ->label(__('admin.fields.description_markdown'))
                            ->helperText(__('admin.helpers.content_item_description'))
                            ->disableToolbarButtons(['attachFiles'])
                            ->fileAttachments(false)
                            ->columnSpanFull(),
                        MediaPickerField::make('image_path', ImageFileNamer::CONTENT_ITEM_IMAGE)
                            ->label(__('admin.fields.image_path'))
                            ->helperText(__('admin.helpers.content_item_image_path'))
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
                        TextInput::make('spotify_episode')
                            ->label(__('admin.fields.spotify_episode'))
                            ->helperText(__('admin.helpers.spotify_episode'))
                            ->dehydrated(false)
                            ->suffixAction(
                                Action::make('fetchSpotifyEpisode')
                                    ->label(__('admin.actions.fetch_spotify_episode'))
                                    ->icon(Heroicon::OutlinedArrowDownTray)
                                    ->action(fn (Set $set, Get $get): null => self::fetchSpotifyEpisode($set, $get)),
                            ),
                        Textarea::make('embed_html')
                            ->label(__('admin.fields.embed_html'))
                            ->helperText(__('admin.helpers.embed_html'))
                            ->rows(4)
                            ->maxLength(65535)
                            ->hintAction(
                                Action::make('extractEmbedSrc')
                                    ->label(__('admin.actions.extract_embed_src'))
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->action(fn (Set $set, Get $get): null => self::extractEmbedSrc($set, $get)),
                            )
                            ->columnSpanFull(),
                        TextInput::make('embed_url')
                            ->label(__('admin.fields.embed_url'))
                            ->helperText(__('admin.helpers.embed_url'))
                            ->url()
                            ->maxLength(2048)
                            ->rules([new ApprovedEmbedUrl])
                            ->validationMessages([
                                'embed_url.url' => __('admin.validation.embed_url_url'),
                            ]),
                        TextInput::make('direct_media_url')
                            ->label(__('admin.fields.direct_media_url'))
                            ->helperText(__('admin.helpers.direct_media_url'))
                            ->url()
                            ->maxLength(2048)
                            ->rules(ContentItemMediaRules::rules()['direct_media_url']),
                        Html::make(fn (Get $get): Htmlable => self::audioPreview($get))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.episode_workspace_taxonomy'))
                    ->description(__('admin.descriptions.episode_workspace_taxonomy'))
                    ->schema([
                        RelationshipOptionForms::configureCategorySelect(
                            Select::make('categories')
                                ->label(__('admin.fields.categories'))
                                ->relationship('categories', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->helperText(__('admin.helpers.item_categories')),
                            allowEdit: false,
                        ),
                        SpatieTagsInput::make('tags')
                            ->label(__('admin.fields.tags'))
                            ->type('content')
                            ->helperText(__('admin.helpers.content_tags')),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columns(2),
                self::transcriptionSection($settings),
                Section::make(__('admin.sections.episode_workspace_advanced'))
                    ->description(__('admin.descriptions.episode_workspace_advanced'))
                    ->schema([
                        TextInput::make('type_label_singular_override')
                            ->label(__('admin.fields.type_label_singular_override'))
                            ->helperText(__('admin.helpers.type_label_singular_override'))
                            ->maxLength(255),
                        TextInput::make('duration_seconds')
                            ->label(__('admin.fields.duration_seconds'))
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('media_duration_seconds')
                            ->label(__('admin.fields.media_duration_seconds'))
                            ->numeric()
                            ->integer()
                            ->minValue(0),
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
                        DateTimePicker::make('original_published_at')
                            ->label(__('admin.fields.original_published_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem'),
                        KeyValue::make('media_metadata')
                            ->label(__('admin.fields.media_metadata'))
                            ->helperText(__('admin.helpers.media_metadata'))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columns(2),
                Section::make(__('admin.sections.episode_workspace_visibility'))
                    ->description(__('admin.descriptions.episode_workspace_visibility'))
                    ->schema([
                        Select::make('status')
                            ->label(__('admin.fields.status'))
                            ->helperText(__('admin.helpers.content_item_status'))
                            ->options(PublicationStatus::class)
                            ->default(PublicationStatus::Draft->value)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label(__('admin.fields.published_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem'),
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
                        Html::make(fn (Get $get, ?ContentItem $record): Htmlable => self::visibilityChecklist($get, $record))
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    private static function transcriptionSection(AdminUxSettings $settings): Section
    {
        $section = Section::make(__('admin.sections.episode_workspace_transcription'))
            ->description(__('admin.descriptions.episode_workspace_transcription'))
            ->relationship('workspaceTranscription')
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::normalizeTranscriptionData($data))
            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::normalizeTranscriptionData($data))
            ->schema([
                Html::make(fn (?Model $record = null, ?Livewire $livewire = null): Htmlable => self::hiddenTranscriptionsHint(
                    self::parentContentItemFromContext($record, $livewire),
                ))
                    ->visible((bool) $settings->show_episode_workspace_hint_line)
                    ->columnSpanFull(),
                RelationshipOptionForms::configureTranscriberRelationshipSelect(
                    Select::make('transcriber_ids'),
                )
                    ->required(false),
                TextInput::make('title')
                    ->label(__('admin.fields.title'))
                    ->helperText(__('admin.helpers.transcription_title'))
                    ->maxLength(255),
                TextInput::make('language_code')
                    ->label(__('admin.fields.language_code'))
                    ->helperText(__('admin.helpers.language_code'))
                    ->default('he')
                    ->required()
                    ->maxLength(10)
                    ->visible((bool) $settings->show_episode_workspace_language_code),
                Select::make('status')
                    ->label(__('admin.fields.status'))
                    ->helperText(__('admin.helpers.transcription_status'))
                    ->options(PublicationStatus::class)
                    ->default(PublicationStatus::Draft->value)
                    ->required(),
                DateTimePicker::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->helperText(__('admin.helpers.transcription_published_at'))
                    ->displayFormat('d/m/Y H:i')
                    ->timezone('Asia/Jerusalem'),
                MarkdownEditor::make('transcript_markdown')
                    ->label(__('admin.fields.transcript_markdown'))
                    ->helperText(__('admin.helpers.transcript_markdown'))
                    ->disableToolbarButtons(['attachFiles'])
                    ->fileAttachments(false)
                    ->columnSpanFull(),
            ])
            ->extraAttributes([
                'data-test' => 'episode-workspace-transcription',
                'data-transcription-mode' => $settings->transcription_mode,
                'data-transcription-presentation-mode' => $settings->transcription_presentation_mode,
            ])
            ->columns(2);

        return $settings->transcription_presentation_mode === 'collapsible'
            ? $section->collapsible()
            : $section;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeTranscriptionData(array $data): array
    {
        unset($data['transcriber_ids']);

        $data['language_code'] = filled($data['language_code'] ?? null) ? $data['language_code'] : 'he';
        $data['status'] = filled($data['status'] ?? null) ? $data['status'] : PublicationStatus::Draft->value;
        $data['transcript_markdown'] = filled($data['transcript_markdown'] ?? null)
            ? (string) $data['transcript_markdown']
            : '';

        return $data;
    }

    private static function combinedTitlePreview(Get $get, ?ContentItem $record): Htmlable
    {
        $item = $record ?? new ContentItem;
        $item->title = (string) ($get('title') ?? $item->title);
        $item->title_prefix = $get('title_prefix') ?? $item->title_prefix;

        if (filled($get('content_group_id'))) {
            $group = ContentGroup::query()->whereKey($get('content_group_id'))->first();

            if ($group) {
                $item->setRelation('contentGroup', $group);
            }
        }

        $title = app(ContentItemDisplayTitle::class)->combined($item);

        return new HtmlString('<div data-test="combined-title-preview" class="text-sm text-gray-600 dark:text-gray-300">'.e(__('admin.labels.combined_title_preview', ['title' => $title])).'</div>');
    }

    private static function publicUrlPreview(Get $get, ?ContentItem $record): Htmlable
    {
        $contentGroup = $record?->exists
            ? ($record->relationLoaded('contentGroup') ? $record->contentGroup : $record->contentGroup()->first())
            : null;

        if (! $record?->exists || blank($record->slug) || blank($contentGroup?->slug)) {
            return new HtmlString('<div data-test="public-url-preview" class="text-sm text-gray-600 dark:text-gray-300">'.e(__('admin.placeholders.public_url_unavailable')).'</div>');
        }

        $url = ShowContentItem::getUrl([
            'contentGroupSlug' => $contentGroup->slug,
            'contentItemSlug' => (string) ($get('slug') ?: $record->slug),
        ], panel: 'public');

        return new HtmlString('<div data-test="public-url-preview" class="text-sm text-gray-600 dark:text-gray-300">'.e($url).'</div>');
    }

    private static function audioPreview(Get $get): Htmlable
    {
        $directMediaUrl = $get('direct_media_url');

        if (blank($directMediaUrl) || ! is_string($directMediaUrl) || ! str_starts_with($directMediaUrl, 'https://')) {
            return new HtmlString('');
        }

        return new HtmlString('<div data-test="direct-audio-preview"><audio controls src="'.e($directMediaUrl).'" class="w-full"></audio></div>');
    }

    private static function visibilityChecklist(Get $get, ?ContentItem $record): Htmlable
    {
        $group = filled($get('content_group_id'))
            ? ContentGroup::query()->whereKey($get('content_group_id'))->first()
            : ($record?->relationLoaded('contentGroup') ? $record->contentGroup : $record?->contentGroup()->first());

        $transcription = $record?->resolveWorkspaceTranscription();

        $groupVisible = $group?->status === PublicationStatus::Published
            && ($group->published_at === null || $group->published_at->lte(now()));
        $itemVisible = $get('status') === PublicationStatus::Published->value
            || $get('status') === PublicationStatus::Published;

        $items = [
            __('admin.labels.visibility_group', ['state' => $groupVisible ? __('admin.labels.active') : __('admin.labels.inactive')]),
            __('admin.labels.visibility_item', ['state' => $itemVisible ? __('admin.labels.active') : __('admin.labels.inactive')]),
            __('admin.labels.visibility_transcription', ['state' => $transcription?->isPublished() ? __('admin.labels.active') : __('admin.labels.inactive')]),
        ];

        $html = collect($items)
            ->map(fn (string $item): string => '<li>'.e($item).'</li>')
            ->implode('');

        return new HtmlString('<ul data-test="workspace-visibility-checklist" class="list-disc space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">'.$html.'</ul>');
    }

    private static function hiddenTranscriptionsHint(?ContentItem $record): Htmlable
    {
        $count = $record?->exists ? $record->transcriptions()->count() : 0;

        if ($count <= 1) {
            return new HtmlString('');
        }

        return new HtmlString('<div data-test="workspace-hidden-transcriptions-hint" class="text-sm text-gray-600 dark:text-gray-300">'.e(__('admin.labels.workspace_hidden_transcriptions_hint', ['count' => $count - 1])).'</div>');
    }

    private static function parentContentItemFromContext(?Model $record, ?Livewire $livewire = null): ?ContentItem
    {
        if ($record instanceof ContentItem) {
            return $record;
        }

        if ($record instanceof Transcription && filled($record->content_item_id)) {
            return ContentItem::query()->whereKey($record->content_item_id)->first();
        }

        if ($livewire !== null && method_exists($livewire, 'getRecord')) {
            $pageRecord = $livewire->getRecord();

            return $pageRecord instanceof ContentItem ? $pageRecord : null;
        }

        return null;
    }

    private static function fetchSpotifyEpisode(Set $set, Get $get): null
    {
        try {
            $data = app(EpisodeSpotifyLookup::class)->lookup((string) $get('spotify_episode'));
        } catch (Throwable $throwable) {
            Notification::make()
                ->danger()
                ->title(__('admin.notifications.spotify_episode_lookup_failed'))
                ->body($throwable->getMessage())
                ->send();

            return null;
        }

        foreach ($data as $field => $value) {
            if ($value === null || filled($get($field))) {
                continue;
            }

            $set($field, $value);
        }

        Notification::make()
            ->success()
            ->title(__('admin.notifications.spotify_episode_lookup_filled'))
            ->send();

        return null;
    }

    private static function extractEmbedSrc(Set $set, Get $get): null
    {
        $embedUrl = app(EpisodeEmbedInputNormalizer::class)->iframeSrc($get('embed_html'));

        if (blank($embedUrl)) {
            return null;
        }

        $set('embed_url', $embedUrl);

        return null;
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
