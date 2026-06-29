<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Author;
use App\Models\ContentItem;
use App\Models\Transcription;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transcription>
 */
class TranscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_key' => (string) Str::ulid(),
            'content_item_id' => ContentItem::factory(),
            'author_id' => null,
            'title' => fake()->sentence(4),
            'language_code' => 'he',
            'transcript_markdown' => fake()->paragraphs(3, true),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'word_count' => fake()->numberBetween(50, 5000),
            'speakers' => null,
            'parsed_segments' => null,
        ];
    }

    public function published(DateTimeInterface|string|null $publishedAt = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PublicationStatus::Published,
            'published_at' => $publishedAt ?? now()->subMinute(),
        ]);
    }

    public function forAuthor(?Author $author = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'author_id' => $author?->getKey() ?? Author::factory(),
        ]);
    }
}
