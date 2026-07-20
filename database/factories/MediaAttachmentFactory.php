<?php

namespace Database\Factories;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaAttachment> */
class MediaAttachmentFactory extends Factory
{
    protected $model = MediaAttachment::class;

    public function definition(): array
    {
        return [
            'media_id' => Media::factory(),
            'attachable_type' => 'content_group',
            'attachable_id' => ContentGroup::factory(),
            'role' => MediaAttachmentRole::Cover,
            'position' => 0,
        ];
    }
}
