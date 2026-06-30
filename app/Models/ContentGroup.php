<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Database\Factories\ContentGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'reference_key',
    'title',
    'slug',
    'group_type_label_singular',
    'group_type_label_plural',
    'default_item_type_label_singular',
    'default_item_type_label_plural',
    'description_markdown',
    'cover_path',
    'original_language_code',
    'status',
    'published_at',
    'homepage_order',
])]
class ContentGroup extends Model
{
    /** @use HasFactory<ContentGroupFactory> */
    use HasFactory;

    protected $attributes = [
        'group_type_label_singular' => 'Podcast',
        'group_type_label_plural' => 'Podcasts',
        'default_item_type_label_singular' => 'Episode',
        'default_item_type_label_plural' => 'Episodes',
        'original_language_code' => 'he',
        'status' => 'draft',
    ];

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PublicationStatus::Published)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeOrderedForHomepage(Builder $query): Builder
    {
        return $query
            ->orderByRaw('homepage_order is null')
            ->orderBy('homepage_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    protected static function booted(): void
    {
        static::creating(function (ContentGroup $contentGroup): void {
            $contentGroup->reference_key ??= (string) Str::ulid();
            $contentGroup->slug = static::uniqueSlug($contentGroup->slug ?: $contentGroup->title);
            $contentGroup->group_type_label_singular ??= 'Podcast';
            $contentGroup->group_type_label_plural ??= 'Podcasts';
            $contentGroup->default_item_type_label_singular ??= 'Episode';
            $contentGroup->default_item_type_label_plural ??= 'Episodes';
            $contentGroup->original_language_code ??= 'he';
            $contentGroup->status ??= PublicationStatus::Draft;
        });

        static::updating(function (ContentGroup $contentGroup): void {
            if ($contentGroup->isDirty('reference_key')) {
                $contentGroup->reference_key = $contentGroup->getOriginal('reference_key');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'homepage_order' => 'integer',
            'published_at' => 'datetime',
            'status' => PublicationStatus::class,
        ];
    }

    private static function uniqueSlug(string $source): string
    {
        $baseSlug = Str::slug($source) ?: Str::lower((string) Str::ulid());
        $slug = $baseSlug;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
