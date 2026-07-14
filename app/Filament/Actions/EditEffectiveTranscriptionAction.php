<?php

namespace App\Filament\Actions;

use App\Enums\PublicationStatus;
use App\Filament\Resources\Transcriptions\Schemas\TranscriptionForm;
use App\Filament\Resources\Transcriptions\TranscriptionResource;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\Transcriptions\TranscriptionModeLabel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class EditEffectiveTranscriptionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editEffectiveTranscription';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('admin.actions.edit_effective_transcription'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->modalSubmitActionLabel(TranscriptionModeLabel::text('admin.actions.save_transcription'))
            ->modalHeading(fn (ContentItem $record): string => static::modalHeadingFor($record))
            ->modalDescription(__('admin.helpers.edit_effective_transcription_action'))
            ->hidden(fn (ContentItem $record): bool => ! static::recordHasTranscriptions($record))
            ->schema(TranscriptionForm::components(
                includeContentItem: false,
                useRelationshipTranscriberSelect: false,
            ))
            ->fillForm(fn (ContentItem $record): array => static::formStateFor($record))
            ->extraModalFooterActions(fn (Action $action): array => static::footerActionsFor($action))
            ->action(function (ContentItem $record, array $data, Action $action): void {
                static::saveResolvedTranscription($record, $data, $action);
            });
    }

    public static function contextStateFor(ContentItem $record): ?string
    {
        $transcription = static::loadedContextTranscriptionFor($record);

        if (! $transcription instanceof Transcription) {
            return null;
        }

        return __('admin.labels.transcription_context', [
            'title' => static::titleFor($transcription),
            'status' => static::statusLabelFor($transcription),
        ]);
    }

    public static function contextColorFor(ContentItem $record): string
    {
        $transcription = static::loadedContextTranscriptionFor($record);

        return $transcription instanceof Transcription
            ? static::statusColorFor($transcription)
            : 'gray';
    }

    public static function resolveTranscriptionFor(ContentItem $record): ?Transcription
    {
        $record->loadMissing([
            'featuredTranscription.authors',
            'latestPublishedTranscription.authors',
        ]);

        $effectiveTranscription = $record->effectiveTranscription();

        if ($effectiveTranscription instanceof Transcription) {
            return $effectiveTranscription->loadMissing('authors');
        }

        $featuredTranscription = $record->featuredTranscription;

        if (static::isCurrentFeaturedTranscription($record, $featuredTranscription)) {
            return $featuredTranscription->loadMissing('authors');
        }

        return $record
            ->transcriptions()
            ->with('authors')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private static function formStateFor(ContentItem $record): array
    {
        $transcription = static::resolveTranscriptionFor($record);

        if (! $transcription instanceof Transcription) {
            return [];
        }

        $transcription->loadMissing('authors');

        return [
            'reference_key' => $transcription->reference_key,
            'transcriber_ids' => $transcription->authors
                ->pluck('id')
                ->map(fn (int $authorId): int => $authorId)
                ->values()
                ->all(),
            'title' => $transcription->title,
            'language_code' => $transcription->language_code,
            'transcript_markdown' => $transcription->transcript_markdown,
            'status' => static::statusValueFor($transcription),
            'published_at' => $transcription->published_at,
        ];
    }

    /**
     * @return array<int, Action>
     */
    private static function footerActionsFor(Action $action): array
    {
        $record = $action->getRecord();

        if (! $record instanceof ContentItem) {
            return [];
        }

        $transcription = static::resolveTranscriptionFor($record);

        if (! $transcription instanceof Transcription) {
            return [];
        }

        return [
            Action::make('openEffectiveTranscriptionResource')
                ->label(TranscriptionModeLabel::text('admin.actions.open_transcription_resource'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(TranscriptionResource::getUrl('edit', ['record' => $transcription]))
                ->openUrlInNewTab(false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function saveResolvedTranscription(ContentItem $record, array $data, Action $action): void
    {
        $transcription = static::resolveTranscriptionFor($record);

        if (! $transcription instanceof Transcription) {
            Notification::make()
                ->warning()
                ->title(__('admin.notifications.transcription_not_found'))
                ->send();

            $action->halt();

            return;
        }

        $transcriberIds = $data['transcriber_ids'] ?? [];

        $transcription->fill([
            'title' => $data['title'] ?? null,
            'language_code' => $data['language_code'] ?? 'he',
            'transcript_markdown' => $data['transcript_markdown'] ?? '',
            'status' => $data['status'] ?? $transcription->status,
            'published_at' => $data['published_at'] ?? null,
        ]);

        $transcription->save();
        $transcription->syncTranscribers($transcriberIds);

        Notification::make()
            ->success()
            ->title(TranscriptionModeLabel::text('admin.notifications.effective_transcription_saved'))
            ->send();
    }

    private static function modalHeadingFor(ContentItem $record): string
    {
        $transcription = static::resolveTranscriptionFor($record);

        if (! $transcription instanceof Transcription) {
            return __('admin.modals.edit_effective_transcription_missing');
        }

        return __('admin.modals.edit_effective_transcription', [
            'title' => static::titleFor($transcription),
            'status' => static::statusLabelFor($transcription),
        ]);
    }

    private static function recordHasTranscriptions(ContentItem $record): bool
    {
        $count = $record->getAttribute('transcriptions_count');

        if ($count !== null) {
            return (int) $count > 0;
        }

        if ($record->relationLoaded('transcriptions')) {
            return $record->transcriptions->isNotEmpty();
        }

        return $record->transcriptions()->exists();
    }

    private static function loadedContextTranscriptionFor(ContentItem $record): ?Transcription
    {
        $featuredTranscription = $record->relationLoaded('featuredTranscription')
            ? $record->featuredTranscription
            : null;

        if (static::isCurrentFeaturedTranscription($record, $featuredTranscription) && $featuredTranscription->isPublished()) {
            return $featuredTranscription;
        }

        $latestPublishedTranscription = $record->relationLoaded('latestPublishedTranscription')
            ? $record->latestPublishedTranscription
            : null;

        if ($latestPublishedTranscription instanceof Transcription) {
            return $latestPublishedTranscription;
        }

        if (static::isCurrentFeaturedTranscription($record, $featuredTranscription)) {
            return $featuredTranscription;
        }

        return null;
    }

    private static function isCurrentFeaturedTranscription(ContentItem $record, ?Transcription $transcription): bool
    {
        return $transcription instanceof Transcription
            && filled($record->featured_transcription_id)
            && $transcription->content_item_id === $record->getKey()
            && (int) $transcription->getKey() === (int) $record->featured_transcription_id;
    }

    private static function titleFor(Transcription $transcription): string
    {
        return filled($transcription->title)
            ? $transcription->title
            : __('admin.labels.untitled_transcription', ['id' => $transcription->getKey()]);
    }

    private static function statusLabelFor(Transcription $transcription): string
    {
        $status = $transcription->status;

        if ($status instanceof PublicationStatus) {
            return $status->getLabel();
        }

        return PublicationStatus::tryFrom((string) $status)?->getLabel() ?? (string) $status;
    }

    private static function statusValueFor(Transcription $transcription): string
    {
        $status = $transcription->status;

        if ($status instanceof PublicationStatus) {
            return $status->value;
        }

        return (string) $status;
    }

    private static function statusColorFor(Transcription $transcription): string
    {
        $status = $transcription->status;

        if ($status instanceof PublicationStatus) {
            return $status->getColor();
        }

        return PublicationStatus::tryFrom((string) $status)?->getColor() ?? 'gray';
    }
}
