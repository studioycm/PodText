<?php

namespace App\Filament\Resources\PublicFormSubmissions\Tables;

use App\Enums\PublicFormSubmissionStatus;
use App\Models\PublicFormSubmission;
use App\Support\PublicFront\Forms\PublicFormSubmissionPresenter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PublicFormSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('form_key')
                    ->label(__('admin.fields.public_form_key'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('form_name_snapshot')
                    ->label(__('admin.fields.public_form_name_snapshot'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('verification_verified_at')
                    ->label(__('admin.fields.public_form_verification'))
                    ->badge()
                    ->getStateUsing(fn (PublicFormSubmission $record): string => $record->verification_verified_at
                        ? __('admin.labels.verified')
                        : __('admin.labels.not_verified'))
                    ->color(fn (PublicFormSubmission $record): string => $record->verification_verified_at ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label(__('admin.fields.submitted_at'))
                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                    ->sortable(),
                TextColumn::make('source_url')
                    ->label(__('admin.fields.source_url'))
                    ->limit(48)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payload_summary')
                    ->label(__('admin.fields.payload'))
                    ->getStateUsing(fn (PublicFormSubmission $record): string => app(PublicFormSubmissionPresenter::class)->summary($record))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('submitter_ip_hash')
                    ->label(__('admin.fields.submitter_ip_hash'))
                    ->limit(16)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_agent_hash')
                    ->label(__('admin.fields.user_agent_hash'))
                    ->limit(16)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(PublicFormSubmissionStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('markReviewed')
                    ->label(__('admin.actions.mark_reviewed'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (PublicFormSubmission $record): bool => $record->status !== PublicFormSubmissionStatus::Reviewed)
                    ->action(function (PublicFormSubmission $record): void {
                        $record->markReviewed();

                        Notification::make()
                            ->success()
                            ->title(__('admin.notifications.public_form_submission_reviewed'))
                            ->send();
                    }),
                Action::make('archive')
                    ->label(__('admin.actions.archive'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->visible(fn (PublicFormSubmission $record): bool => $record->status !== PublicFormSubmissionStatus::Archived)
                    ->action(function (PublicFormSubmission $record): void {
                        $record->archive();

                        Notification::make()
                            ->success()
                            ->title(__('admin.notifications.public_form_submission_archived'))
                            ->send();
                    }),
                Action::make('reopen')
                    ->label(__('admin.actions.reopen'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (PublicFormSubmission $record): bool => $record->status !== PublicFormSubmissionStatus::New)
                    ->action(function (PublicFormSubmission $record): void {
                        $record->reopen();

                        Notification::make()
                            ->success()
                            ->title(__('admin.notifications.public_form_submission_reopened'))
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('archive')
                        ->label(__('admin.actions.archive'))
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each->archive();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
