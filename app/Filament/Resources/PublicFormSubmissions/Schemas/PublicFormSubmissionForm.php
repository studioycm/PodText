<?php

namespace App\Filament\Resources\PublicFormSubmissions\Schemas;

use App\Enums\PublicFormSubmissionStatus;
use App\Models\PublicFormSubmission;
use App\Support\PublicFront\Forms\PublicFormSubmissionPresenter;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PublicFormSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.public_form_submission_review'))
                    ->description(__('admin.descriptions.public_form_submission_review'))
                    ->schema([
                        Select::make('status')
                            ->label(__('admin.fields.status'))
                            ->options(PublicFormSubmissionStatus::class)
                            ->required(),
                    ]),
                Section::make(__('admin.sections.public_form_submission_details'))
                    ->schema([
                        TextInput::make('form_key')
                            ->label(__('admin.fields.public_form_key'))
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('form_name_snapshot')
                            ->label(__('admin.fields.public_form_name_snapshot'))
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('submitted_at')
                            ->label(__('admin.fields.submitted_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->timezone('Asia/Jerusalem')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('source_url')
                            ->label(__('admin.fields.source_url'))
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        TextInput::make('submitter_ip_hash')
                            ->label(__('admin.fields.submitter_ip_hash'))
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('user_agent_hash')
                            ->label(__('admin.fields.user_agent_hash'))
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('payload_preview')
                            ->label(__('admin.fields.payload'))
                            ->formatStateUsing(fn (?PublicFormSubmission $record): string => $record
                                ? app(PublicFormSubmissionPresenter::class)->plainTextPayload($record)
                                : '')
                            ->rows(10)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
