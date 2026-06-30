<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Support\Media\ContentItemMediaRules;
use Database\Factories\ContentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Tags\HasTags;

#[Fillable([
    'reference_key',
    'content_group_id',
    'title',
    'slug',
    'type_label_singular_override',
    'description_markdown',
    'media_url',
    'embed_url',
    'embed_provider',
    'duration_seconds',
    'media_duration_seconds',
    'external_id',
    'external_title',
    'external_description',
    'external_thumbnail_url',
    'external_published_at',
    'media_metadata',
    'direct_media_url',
    'featured_transcription_id',
    'is_pinned',
    'pinned_at',
    'pinned_until',
    'pin_order',
    'status',
    'published_at',
    'original_published_at',
])]
class ContentItem extends Model
{
    /** @use HasFactory<ContentItemFactory> */
    use HasFactory;

    use HasTags;

    protected $attributes = [
        'is_pinned' => false,
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

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): MorphToMany
    {
        return $this
            ->morphToMany(
                self::getTagClassName(),
                $this->getTaggableMorphName(),
                $this->getTaggableTableName(),
                'taggable_id',
                'tag_id',
            )
            ->using($this->getPivotModelClassName())
            ->ordered();
    }

    public function contentTags(): MorphToMany
    {
        return $this->tags()
            ->where('type', 'content');
    }

    public function enabledContentTags(): MorphToMany
    {
        return $this->contentTags()
            ->where('is_enabled', true);
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

    public function effectiveCategories(): Collection
    {
        $directCategories = $this->relationLoaded('categories')
            ? $this->categories
            : $this->categories()->get();

        $contentGroup = $this->relationLoaded('contentGroup')
            ? $this->contentGroup
            : $this->contentGroup()->first();

        $groupCategories = $contentGroup?->relationLoaded('categories')
            ? $contentGroup->categories
            : ($contentGroup?->categories()->get() ?? collect());

        return $directCategories
            ->merge($groupCategories)
            ->unique('id')
            ->values();
    }

    public function publicTags(): Collection
    {
        return $this->enabledContentTags()->get();
    }

    public function isCurrentlyPinned(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_pinned
            && ($this->pinned_at === null || $this->pinned_at->lte($at))
            && ($this->pinned_until === null || $this->pinned_until->gt($at));
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

    public function scopeInCategoryTree(Builder $query, Category|int $category): Builder
    {
        $category = $category instanceof Category
            ? $category
            : Category::query()->findOrFail($category);

        $categoryIds = $category->descendantIds()->all();

        return $query->where(function (Builder $query) use ($categoryIds): void {
            $query
                ->whereHas('categories', fn (Builder $query): Builder => $query->whereIn('categories.id', $categoryIds))
                ->orWhereHas('contentGroup.categories', fn (Builder $query): Builder => $query->whereIn('categories.id', $categoryIds));
        });
    }

    public function scopeWithEnabledContentTag(Builder $query, ContentTag|string $tag): Builder
    {
        $tag = is_string($tag)
            ? ContentTag::findFromString($tag, 'content')
            : $tag;

        if (! $tag instanceof ContentTag) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas('tags', function (Builder $query) use ($tag): void {
            $query
                ->where('tags.id', $tag->getKey())
                ->where('tags.type', 'content')
                ->where('tags.is_enabled', true);
        });
    }

    public function scopeCurrentlyPinned(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_pinned', true)
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull('pinned_at')
                    ->orWhere('pinned_at', '<=', $at);
            })
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull('pinned_until')
                    ->orWhere('pinned_until', '>', $at);
            });
    }

    public function scopeOrderedForPins(Builder $query): Builder
    {
        return $query
            ->orderByRaw('pin_order is null')
            ->orderBy('pin_order')
            ->orderByDesc('pinned_at')
            ->orderByDesc('id');
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
     * @return array<string, array<int, mixed>>
     */
    public static function mediaValidationRules(): array
    {
        return ContentItemMediaRules::rules();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'external_published_at' => 'datetime',
            'featured_transcription_id' => 'integer',
            'is_pinned' => 'boolean',
            'media_duration_seconds' => 'integer',
            'media_metadata' => 'array',
            'original_published_at' => 'datetime',
            'pin_order' => 'integer',
            'pinned_at' => 'datetime',
            'pinned_until' => 'datetime',
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
