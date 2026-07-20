<?php

namespace Database\Factories;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MediaMutationOperation> */
class MediaMutationOperationFactory extends Factory
{
    protected $model = MediaMutationOperation::class;

    public function definition(): array
    {
        return [
            'operation_key' => (string) Str::ulid(),
            'media_id' => Media::factory(),
            'operation' => MediaMutationOperationType::Upload,
            'status' => MediaMutationStatus::Staged,
            'purpose' => ImageUploadPurpose::ContentGroupCover->value,
            'attempts' => 0,
            'started_at' => now(),
        ];
    }
}
