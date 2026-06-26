<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentItem>
 */
class ContentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'reference_key' => (string) Str::ulid(),
            'content_group_id' => ContentGroup::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'type_label_singular_override' => null,
            'description_markdown' => fake()->paragraph(),
            'media_url' => 'https://example.com/media/'.fake()->uuid(),
            'embed_url' => null,
            'duration_seconds' => fake()->numberBetween(60, 7200),
            'transcript_markdown' => fake()->paragraphs(3, true),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'original_published_at' => null,
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
