<?php

namespace App\Filament\Resources\ContentItems\RelationManagers;

use App\Enums\PublicationStatus;
use App\Filament\Resources\Transcriptions\TranscriptionResource;
use App\Models\Transcription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TranscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transcriptions';

    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->description(__('admin.descriptions.transcription_identity'))
                    ->schema([
                        Select::make('author_id')
                            ->label(__('admin.fields.author'))
                            ->helperText(__('admin.helpers.transcription_author'))
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('title')
                            ->label(__('admin.fields.title'))
                            ->helperText(__('admin.helpers.transcription_title'))
                            ->maxLength(255),
                        TextInput::make('language_code')
                            ->label(__('admin.fields.language_code'))
                            ->helperText(__('admin.helpers.language_code'))
                            ->default('he')
                            ->required()
                            ->maxLength(10),
                    ])
                    ->columns(3),
                Section::make(__('admin.sections.publication'))
                    ->description(__('admin.descriptions.transcription_publication'))
                    ->schema([
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
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.transcript'))
                    ->description(__('admin.descriptions.transcript_markdown'))
                    ->schema([
                        MarkdownEditor::make('transcript_markdown')
                            ->label(__('admin.fields.transcript_markdown'))
                            ->helperText(__('admin.helpers.transcript_markdown'))
                            ->disableToolbarButtons(['attachFiles'])
                            ->fileAttachments(false)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('author')
                ->latest('published_at')
                ->latest('id'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->placeholder(__('admin.labels.untitled'))
                    ->searchable(),
                TextColumn::make('author.name')
                    ->label(__('admin.fields.author'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable(),
                TextColumn::make('language_code')
                    ->label(__('admin.fields.language_code'))
                    ->badge(),
                TextColumn::make('word_count')
                    ->label(__('admin.fields.word_count'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('featured_state')
                    ->label(__('admin.fields.featured_transcription'))
                    ->state(fn (Transcription $record): string => $record->getKey() === $this->getOwnerRecord()->featured_transcription_id
                        ? __('admin.labels.featured')
                        : __('admin.labels.not_featured'))
                    ->badge()
                    ->color(fn (Transcription $record): string => $record->getKey() === $this->getOwnerRecord()->featured_transcription_id ? 'warning' : 'gray'),
                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(PublicationStatus::class),
                SelectFilter::make('author_id')
                    ->label(__('admin.fields.author'))
                    ->relationship('author', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('language_code')
                    ->label(__('admin.fields.language_code'))
                    ->options(fn (): array => Transcription::query()
                        ->whereNotNull('language_code')
                        ->distinct()
                        ->orderBy('language_code')
                        ->pluck('language_code', 'language_code')
                        ->all()),
                SelectFilter::make('featured_state')
                    ->label(__('admin.fields.featured_transcription'))
                    ->options([
                        'featured' => __('admin.labels.featured'),
                        'not_featured' => __('admin.labels.not_featured'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? null) === 'featured') {
                            return $query->whereKey($this->getOwnerRecord()->featured_transcription_id);
                        }

                        if (($data['value'] ?? null) === 'not_featured') {
                            return $query->whereKeyNot($this->getOwnerRecord()->featured_transcription_id);
                        }

                        return $query;
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->createAnother(false),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::FiveExtraLarge),
                Action::make('setFeatured')
                    ->label(__('admin.actions.set_featured_transcription'))
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->modalDescription(__('admin.helpers.set_featured_transcription_action'))
                    ->visible(fn (Transcription $record): bool => $record->isPublished()
                        && $this->getOwnerRecord()->transcriptions()->count() > 1
                        && $record->getKey() !== $this->getOwnerRecord()->featured_transcription_id)
                    ->action(function (Transcription $record): void {
                        abort_unless($record->content_item_id === $this->getOwnerRecord()->getKey(), 403);

                        $this->getOwnerRecord()->forceFill([
                            'featured_transcription_id' => $record->getKey(),
                        ])->save();

                        Notification::make()
                            ->success()
                            ->title(__('admin.notifications.featured_transcription_saved'))
                            ->send();
                    }),
                Action::make('openResource')
                    ->label(__('admin.actions.open_transcription_resource'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Transcription $record): string => TranscriptionResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make(__('admin.tabs.transcriptions'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->badge((string) $ownerRecord->transcriptions()->count())
            ->badgeColor('info')
            ->badgeTooltip(__('admin.tabs.transcriptions_badge_tooltip'));
    }
}
