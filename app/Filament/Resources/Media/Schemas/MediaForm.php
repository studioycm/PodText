<?php

namespace App\Filament\Resources\Media\Schemas;

use App\Enums\ImageUploadPurpose;
use App\Support\Media\CuratorImageUploadPolicy;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.media_upload'))
                ->visibleOn('create')
                ->schema([
                    Select::make('purpose')
                        ->label(__('admin.fields.image_upload_purpose'))
                        ->options(ImageUploadPurpose::options())
                        ->live()
                        ->required(),
                    FileUpload::make('uploads')
                        ->label(__('admin.media_library.batch_files'))
                        ->helperText(__('admin.media_library.batch_files_help'))
                        ->acceptedFileTypes(function (Get $get): array {
                            $purpose = ImageUploadPurpose::tryFrom((string) $get('purpose'));

                            return $purpose instanceof ImageUploadPurpose
                                ? app(CuratorImageUploadPolicy::class)->mimeTypesFor($purpose)
                                : app(CuratorImageUploadPolicy::class)->globalMimeTypes();
                        })
                        ->maxSize(app(CuratorImageUploadPolicy::class)->maxKilobytes())
                        ->multiple()
                        ->maxFiles(app(CuratorImageUploadPolicy::class)->uploadBatchLimit())
                        ->maxParallelUploads(2)
                        ->storeFiles(false)
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('admin.sections.media_metadata'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('admin.fields.title'))
                        ->maxLength(255),
                    TextInput::make('alt')
                        ->label(__('admin.fields.alt_text'))
                        ->maxLength(255),
                    Textarea::make('caption')
                        ->label(__('admin.fields.caption'))
                        ->maxLength(65000)
                        ->rows(3)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label(__('admin.fields.description'))
                        ->maxLength(65000)
                        ->rows(5)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}
