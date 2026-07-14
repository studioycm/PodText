<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Enums\UserRole;
use App\Filament\Actions\ContentImageActions;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Schemas\EpisodeWorkspaceForm;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EditEpisodeWorkspace extends EditRecord
{
    protected static string $resource = ContentItemResource::class;

    public function form(Schema $schema): Schema
    {
        return EpisodeWorkspaceForm::configure($schema);
    }

    protected function afterSave(): void
    {
        $this->getRecord()->refresh()->adoptWorkspaceTranscription();
    }

    protected function getHeaderActions(): array
    {
        return [
            ContentImageActions::downloadExternalImage(),
            ContentImageActions::downloadExternalImage(overwrite: true),
            $this->replaceWorkspaceTranscriptionAction(),
            Action::make('classicEdit')
                ->label(__('admin.actions.classic_edit'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(fn (ContentItem $record): string => $this->getResource()::getUrl('edit', ['record' => $record])),
            DeleteAction::make(),
        ];
    }

    private function replaceWorkspaceTranscriptionAction(): Action
    {
        return Action::make('replaceWorkspaceTranscription')
            ->label(__('admin.actions.replace_transcription'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->requiresConfirmation()
            ->modalHeading(__('admin.modals.replace_workspace_transcription'))
            ->modalSubmitActionLabel(__('admin.actions.replace_transcription'))
            ->fillForm(fn (): array => [
                'replacement_mode' => $this->canPickExistingTranscription() && $this->otherTranscriptionsExist()
                    ? 'existing'
                    : 'fresh',
            ])
            ->beforeFormValidated(function (): void {
                $mountedActionIndex = array_key_last($this->mountedActions ?? []);
                $rawData = is_int($mountedActionIndex)
                    ? ($this->mountedActions[$mountedActionIndex]['data'] ?? [])
                    : [];

                abort_if(
                    ($rawData['replacement_mode'] ?? null) === 'existing'
                    && ! $this->canPickExistingTranscription(),
                    403,
                );
            })
            ->schema([
                Radio::make('replacement_mode')
                    ->label(__('admin.fields.replacement_mode'))
                    ->options(fn (Get $get): array => [
                        ...($this->canPickExistingTranscription() || $get('replacement_mode') === 'existing'
                            ? ['existing' => __('admin.actions.select_existing_transcription')]
                            : []),
                        'fresh' => __('admin.actions.start_fresh_transcription'),
                    ])
                    ->default('fresh')
                    ->live()
                    ->required(),
                Select::make('existing_transcription_id')
                    ->label(__('admin.fields.existing_transcription'))
                    ->options(fn (): array => $this->existingTranscriptionOptions())
                    ->searchable()
                    ->preload(false)
                    ->optionsLimit(50)
                    ->visible(fn (Get $get): bool => $this->canPickExistingTranscription()
                        && $get('replacement_mode') === 'existing')
                    ->required(fn (Get $get): bool => $get('replacement_mode') === 'existing'),
            ])
            ->action(function (array $data): void {
                $record = $this->getRecord();

                if (($data['replacement_mode'] ?? null) === 'existing') {
                    abort_unless($this->canPickExistingTranscription(), 403);

                    $transcription = $record->transcriptions()
                        ->whereKey($data['existing_transcription_id'] ?? null)
                        ->first();

                    if (! $transcription instanceof Transcription) {
                        throw ValidationException::withMessages([
                            'existing_transcription_id' => __('validation.exists', [
                                'attribute' => __('admin.fields.existing_transcription'),
                            ]),
                        ]);
                    }

                    $record->replaceWorkspaceTranscriptionWith($transcription);
                } else {
                    $record->startFreshWorkspaceTranscription();
                }

                $this->record = $record->refresh();
                $this->fillForm();

                Notification::make()
                    ->success()
                    ->title(__('admin.notifications.workspace_transcription_replaced'))
                    ->send();
            });
    }

    private function otherTranscriptionsExist(): bool
    {
        $record = $this->getRecord();
        $workspaceTranscription = $record->resolveWorkspaceTranscription();

        return $record
            ->transcriptions()
            ->when($workspaceTranscription, fn (Builder $query): Builder => $query->whereKeyNot($workspaceTranscription->getKey()))
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function existingTranscriptionOptions(): array
    {
        $record = $this->getRecord();
        $workspaceTranscription = $record->resolveWorkspaceTranscription();

        return $record
            ->transcriptions()
            ->when($workspaceTranscription, fn (Builder $query): Builder => $query->whereKeyNot($workspaceTranscription->getKey()))
            ->latest('published_at')
            ->latest('id')
            ->get()
            ->mapWithKeys(fn (Transcription $transcription): array => [
                $transcription->getKey() => $transcription->title ?: __('admin.labels.untitled_transcription', ['id' => $transcription->getKey()]),
            ])
            ->all();
    }

    private function canPickExistingTranscription(): bool
    {
        return MultiTranscriptionSurfaces::currentUserCan(UserRole::Admin);
    }
}
