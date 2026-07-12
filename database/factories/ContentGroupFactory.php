<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\ContentGroup;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentGroup>
 */
class ContentGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'reference_key' => (string) Str::ulid(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'group_type_label_singular' => 'Podcast',
            'group_type_label_plural' => 'Podcasts',
            'default_item_type_label_singular' => 'Episode',
            'default_item_type_label_plural' => 'Episodes',
            'description_markdown' => fake()->paragraph(),
            'cover_path' => null,
            'cover_alt_text' => null,
            'original_language_code' => 'he',
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'homepage_order' => null,
        ];
    }

    public function published(DateTimeInterface|string|null $publishedAt = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PublicationStatus::Published,
            'published_at' => $publishedAt ?? now()->subMinute(),
        ]);
    }
}
