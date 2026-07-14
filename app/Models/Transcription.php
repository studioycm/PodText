<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Support\Transcriptions\SingleTranscriptionLens;
use Database\Factories\TranscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'reference_key',
    'content_item_id',
    'author_id',
    'title',
    'language_code',
    'transcript_markdown',
    'status',
    'published_at',
    'word_count',
    'speakers',
    'parsed_segments',
])]
class Transcription extends Model
{
    /** @use HasFactory<TranscriptionFactory> */
    use HasFactory;

    protected $attributes = [
        'language_code' => 'he',
        'status' => 'draft',
    ];

    private bool $isSanctionedWorkspaceReplacement = false;

    public function markAsSanctionedWorkspaceReplacement(): static
    {
        $this->isSanctionedWorkspaceReplacement = true;

        return $this;
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function authors(): BelongsToMany
    {
        return $this
            ->belongsToMany(Author::class, 'author_transcription')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('authors.name')
            ->orderBy('authors.id');
    }

    /**
     * @param  iterable<int, Author|int|string|null>  $authors
     */
    public function syncTranscribers(iterable $authors): void
    {
        $authorIds = collect($authors)
            ->map(fn (Author|int|string|null $author): ?int => $author instanceof Author ? $author->getKey() : (is_numeric($author) ? (int) $author : null))
            ->filter(fn (?int $authorId): bool => filled($authorId) && $authorId > 0)
            ->unique()
            ->values();

        $syncPayload = $authorIds
            ->mapWithKeys(fn (int $authorId, int $index): array => [
                $authorId => ['sort_order' => $index],
            ])
            ->all();

        $this->authors()->sync($syncPayload);

        $primaryAuthorId = $authorIds->first();

        if ($this->author_id !== $primaryAuthorId) {
            $this->forceFill(['author_id' => $primaryAuthorId])->saveQuietly();
        }

        $this->unsetRelation('author');
        $this->unsetRelation('authors');
    }

    public function primaryTranscriber(): ?Author
    {
        if ($this->relationLoaded('authors') && $this->authors->isNotEmpty()) {
            return $this->authors->first();
        }

        $author = $this->authors()->first();

        if ($author instanceof Author) {
            return $author;
        }

        return $this->relationLoaded('author')
            ? $this->author
            : $this->author()->first();
    }

    public function primaryAuthor(): ?Author
    {
        return $this->primaryTranscriber();
    }

    /**
     * @return array<int, string>
     */
    public function transcriberNames(): array
    {
        $authors = $this->relationLoaded('authors')
            ? $this->authors
            : $this->authors()->get();

        if ($authors->isEmpty()) {
            $primaryAuthor = $this->relationLoaded('author')
                ? $this->author
                : $this->author()->first();

            return $primaryAuthor instanceof Author ? [$primaryAuthor->name] : [];
        }

        return $authors
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PublicationStatus::Published)
            ->whereNotNull('transcript_markdown')
            ->where('transcript_markdown', '!=', '')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published
            && filled($this->transcript_markdown)
            && ($this->published_at === null || $this->published_at->lte(now()));
    }

    protected static function booted(): void
    {
        static::creating(function (Transcription $transcription): void {
            $transcription->reference_key ??= (string) Str::ulid();
            $transcription->language_code ??= 'he';
            $transcription->status ??= PublicationStatus::Draft;

            if (
                $transcription->isSanctionedWorkspaceReplacement
                || ! app(SingleTranscriptionLens::class)->isActive()
                || blank($transcription->content_item_id)
            ) {
                return;
            }

            if (Transcription::query()->where('content_item_id', $transcription->content_item_id)->exists()) {
                $message = __('admin.validation.transcription_already_exists');

                throw ValidationException::withMessages([
                    'data.content_item_id' => $message,
                ]);
            }
        });

        static::created(function (Transcription $transcription): void {
            $contentItem = $transcription->contentItem;

            if (! $contentItem || filled($contentItem->featured_transcription_id)) {
                return;
            }

            if ($contentItem->transcriptions()->whereKeyNot($transcription->getKey())->exists()) {
                return;
            }

            $contentItem->forceFill([
                'featured_transcription_id' => $transcription->getKey(),
            ])->save();
        });

        static::updating(function (Transcription $transcription): void {
            if ($transcription->isDirty('reference_key')) {
                $transcription->reference_key = $transcription->getOriginal('reference_key');
            }
        });

        static::saved(function (Transcription $transcription): void {
            if (! $transcription->wasRecentlyCreated && ! $transcription->wasChanged('author_id')) {
                return;
            }

            $transcription->syncCompatibilityAuthorToTranscriberPivot();
        });
    }

    private function syncCompatibilityAuthorToTranscriberPivot(): void
    {
        if (blank($this->author_id) || ! Schema::hasTable('author_transcription')) {
            return;
        }

        $authorIds = collect([(int) $this->author_id])
            ->merge($this->authors()->pluck('authors.id')->map(fn (int $authorId): int => $authorId))
            ->unique()
            ->values();

        $syncPayload = $authorIds
            ->mapWithKeys(fn (int $authorId, int $index): array => [
                $authorId => ['sort_order' => $index],
            ])
            ->all();

        $this->authors()->sync($syncPayload);
        $this->unsetRelation('authors');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parsed_segments' => 'array',
            'published_at' => 'datetime',
            'speakers' => 'array',
            'status' => PublicationStatus::class,
            'word_count' => 'integer',
        ];
    }
}
