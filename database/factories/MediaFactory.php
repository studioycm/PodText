<?php

namespace Database\Factories;

use App\Enums\ImageUploadPurpose;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Media> */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $name = (string) Str::ulid();
        $root = ImageUploadPurpose::ContentGroupCover->root();

        return [
            'reference_key' => (string) Str::ulid(),
            'disk' => 'public',
            'directory' => $root,
            'visibility' => 'public',
            'name' => $name,
            'path' => "{$root}/{$name}.jpg",
            'width' => 100,
            'height' => 100,
            'size' => 1024,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
            'title' => fake()->words(3, true),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(
            fn (Media $media): Media => $media->permitTestFixtureCreation(),
        );
    }
}
