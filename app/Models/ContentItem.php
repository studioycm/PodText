<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Database\Factories\ContentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'reference_key',
    'content_group_id',
    'title',
    'slug',
    'type_label_singular_override',
    'description_markdown',
    'media_url',
    'embed_url',
    'duration_seconds',
    'featured_transcription_id',
    'status',
    'published_at',
    'original_published_at',
])]
class ContentItem extends Model
{
    /** @use HasFactory<ContentItemFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
    ];

    public function contentGroup(): BelongsTo
    {
        return $this->belongsTo(ContentGroup::class);
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class);
    }

    public function transcriptions(): HasMany
    {
        return $this->hasMany(Transcription::class);
    }

    public function featuredTranscription(): BelongsTo
    {
        return $this->belongsTo(Transcription::class, 'featured_transcription_id');
    }

    public function latestPublishedTranscription(): HasOne
    {
        return $this
            ->hasOne(Transcription::class)
            ->published()
            ->latest('published_at')
            ->latest('id');
    }

    public function effectiveTypeLabelSingular(): string
    {
        return $this->type_label_singular_override
            ?: $this->contentGroup?->default_item_type_label_singular
            ?: 'Episode';
    }

    public function effectiveTranscription(): ?Transcription
    {
        $featuredTranscription = $this->relationLoaded('featuredTranscription')
            ? $this->featuredTranscription
            : $this->featuredTranscription()->first();

        if ($featuredTranscription?->content_item_id === $this->getKey() && $featuredTranscription->isPublished()) {
            return $featuredTranscription;
        }

        if ($this->relationLoaded('latestPublishedTranscription')) {
            return $this->latestPublishedTranscription;
        }

        return $this->latestPublishedTranscription()->first();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PublicationStatus::Published)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->whereHas('contentGroup', fn (Builder $query): Builder => $query->published())
            ->whereHas('transcriptions', fn (Builder $query): Builder => $query->published());
    }

    public function scopeWithEffectiveTranscriptionPublishedAt(Builder $query): Builder
    {
        return $query->addSelect([
            'featured_transcription_published_at' => Transcription::query()
                ->select('published_at')
                ->whereColumn('id', $this->getTable().'.featured_transcription_id')
                ->whereColumn('content_item_id', $this->getTable().'.id')
                ->published()
                ->limit(1),
            'latest_transcription_published_at' => Transcription::query()
                ->select('published_at')
                ->whereColumn('content_item_id', $this->getTable().'.id')
                ->published()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(1),
        ]);
    }

    public function scopeOrderByEffectiveTranscriptionPublishedAt(Builder $query, string $direction = 'desc'): Builder
    {
        $this->scopeWithEffectiveTranscriptionPublishedAt($query);
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderByRaw("coalesce(featured_transcription_published_at, latest_transcription_published_at) {$direction}")
            ->orderBy('id', $direction);
    }

    protected static function booted(): void
    {
        static::creating(function (ContentItem $contentItem): void {
            $contentItem->reference_key ??= (string) Str::ulid();
            $contentItem->slug = static::uniqueSlug($contentItem->slug ?: $contentItem->title, (int) $contentItem->content_group_id);
            $contentItem->status ??= PublicationStatus::Draft;
        });

        static::updating(function (ContentItem $contentItem): void {
            if ($contentItem->isDirty('reference_key')) {
                $contentItem->reference_key = $contentItem->getOriginal('reference_key');
            }
        });

        static::saving(function (ContentItem $contentItem): void {
            if (! $contentItem->isDirty('featured_transcription_id') || blank($contentItem->featured_transcription_id)) {
                return;
            }

            $belongsToItem = Transcription::query()
                ->whereKey($contentItem->featured_transcription_id)
                ->where('content_item_id', $contentItem->getKey())
                ->exists();

            if (! $belongsToItem) {
                throw ValidationException::withMessages([
                    'featured_transcription_id' => __('validation.exists', [
                        'attribute' => 'featured transcription',
                    ]),
                ]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'featured_transcription_id' => 'integer',
            'original_published_at' => 'datetime',
            'published_at' => 'datetime',
            'status' => PublicationStatus::class,
        ];
    }

    private static function uniqueSlug(string $source, int $contentGroupId): string
    {
        $baseSlug = Str::slug($source) ?: Str::lower((string) Str::ulid());
        $slug = $baseSlug;
        $suffix = 2;

        while (static::query()
            ->where('content_group_id', $contentGroupId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
