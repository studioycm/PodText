<?php

namespace App\Filament\Resources\Media\Pages;

use App\Enums\ImageUploadPurpose;
use App\Filament\Resources\Media\MediaResource;
use App\Models\User;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Media\MediaUploadBatchResult;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnexpectedValueException;

class CreateMedia extends CreateRecord
{
    use RestrictsFileUploadsToSchemaComponents;

    protected static string $resource = MediaResource::class;

    protected static bool $canCreateAnother = false;

    private ?MediaUploadBatchResult $uploadResult = null;

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $purpose = ImageUploadPurpose::tryFrom((string) ($data['purpose'] ?? ''))
            ?? throw new UnexpectedValueException('A valid image upload purpose is required.');
        $files = array_values(Arr::wrap($data['uploads'] ?? []));

        if (count($files) > 1) {
            Gate::forUser($user)->authorize('bulkUpload', static::getModel());
        }

        $uploads = collect($files)->map(function (mixed $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                throw new UnexpectedValueException('The upload state is invalid.');
            }

            return $file;
        })->all();

        try {
            $this->uploadResult = app(MediaAcquisitionManager::class)->acquireUploads(
                $uploads,
                $purpose,
                $user,
                Arr::only($data, ['alt', 'title', 'caption', 'description']),
            );
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'data.uploads' => __('admin.media_library.upload_invalid'),
            ]);
        }

        if ($this->uploadResult->successful->isEmpty()) {
            throw ValidationException::withMessages([
                'data.uploads' => __('admin.media_library.upload_failed'),
            ]);
        }

        return $this->uploadResult->media()->firstOrFail();
    }

    protected function getCreatedNotification(): ?Notification
    {
        if (! $this->uploadResult?->isPartial()) {
            return parent::getCreatedNotification();
        }

        return Notification::make()
            ->warning()
            ->title(__('admin.media_library.upload_partial_title'))
            ->body(__('admin.media_library.upload_partial_body', [
                'added' => $this->uploadResult->successful->count(),
                'not_added' => $this->uploadResult->unsuccessfulCount(),
            ]));
    }
}
