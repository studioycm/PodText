<?php

namespace App\Filament\Forms;

use App\Enums\MediaNamingStrategy;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Support\Media\ImageFileNamer;
use App\Support\Media\ImageUploadRules;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaPickerField
{
    public static function make(string $name, string $family, bool $allowSvg = false): Field
    {
        if (config('media.picker.driver', 'curator') === 'file_upload') {
            return self::fileUpload($name, $family, $allowSvg);
        }

        return self::curatorPicker($name, $family, $allowSvg);
    }

    private static function curatorPicker(string $name, string $family, bool $allowSvg): PathCuratorPicker
    {
        $directory = ImageFileNamer::directoryFor($family);

        return PathCuratorPicker::make($name)
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->acceptedFileTypes($allowSvg ? ImageUploadRules::logoMimeTypes() : ImageUploadRules::rasterMimeTypes())
            ->maxSize(ImageUploadRules::MAX_KILOBYTES)
            ->limitToDirectory()
            ->buttonLabel(__('admin.actions.pick_media'));
    }

    private static function fileUpload(string $name, string $family, bool $allowSvg): FileUpload
    {
        $directory = ImageFileNamer::directoryFor($family);

        return FileUpload::make($name)
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->image()
            ->acceptedFileTypes($allowSvg ? ImageUploadRules::logoMimeTypes() : ImageUploadRules::rasterMimeTypes())
            ->rule($allowSvg ? ImageUploadRules::logoImage() : ImageUploadRules::rasterImage())
            ->maxSize(ImageUploadRules::MAX_KILOBYTES)
            ->preventFilePathTampering(allowFilePathUsing: self::allowFilePathFor($directory))
            ->getUploadedFileNameForStorageUsing(self::uploadNameFor($family, $directory));
    }

    private static function uploadNameFor(string $family, string $directory): Closure
    {
        return function (BaseFileUpload $component, TemporaryUploadedFile $file, ?Model $record = null, ?Get $get = null) use ($family, $directory): string {
            if ($family !== ImageFileNamer::CONTENT_GROUP_COVER) {
                return $component->shouldPreserveFilenames()
                    ? $file->getClientOriginalName()
                    : ((string) str()->ulid()).'.'.mb_strtolower($file->getClientOriginalExtension());
            }

            $record ??= $component->getRecord();
            $slug = $get instanceof Get ? $get('slug') : null;
            $referenceKey = $get instanceof Get ? $get('reference_key') : null;

            if (blank($slug) && $record instanceof Model) {
                $slug = $record->getAttribute('slug');
            }

            if (blank($referenceKey) && $record instanceof Model) {
                $referenceKey = $record->getAttribute('reference_key');
            }

            $referenceKey = filled($referenceKey) ? (string) $referenceKey : (string) str()->ulid();

            return ImageFileNamer::storageFileName(
                is_string($slug) ? $slug : null,
                $referenceKey,
                $file->getMimeType() ?: 'image/'.$file->getClientOriginalExtension(),
                MediaNamingStrategy::Slug,
                fn (string $fileName): bool => Storage::disk('public')->exists("{$directory}/{$fileName}"),
            );
        };
    }

    private static function allowFilePathFor(string $directory): Closure
    {
        return static fn (string $file): bool => str_starts_with(
            str_replace('\\', '/', trim($file)),
            "{$directory}/",
        );
    }
}
