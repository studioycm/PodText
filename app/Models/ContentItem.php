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
use Illuminate\Support\Str;

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
    'transcript_markdown',
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

    public function effectiveTypeLabelSingular(): string
    {
        return $this->type_label_singular_override
            ?: $this->contentGroup?->default_item_type_label_singular
            ?: 'Episode';
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
            ->whereHas('contentGroup', fn (Builder $query): Builder => $query->published());
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
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
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
