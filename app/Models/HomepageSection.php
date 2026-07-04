<?php

namespace App\Models;

use App\Enums\HomepageSectionType;
use Database\Factories\HomepageSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'type',
    'category_id',
    'tag_id',
    'content_group_id',
    'limit',
    'sort_order',
    'is_visible',
    'source_config',
    'selection_config',
    'display_config',
    'pagination_config',
])]
class HomepageSection extends Model
{
    /** @use HasFactory<HomepageSectionFactory> */
    use HasFactory;

    protected $attributes = [
        'limit' => 6,
        'sort_order' => 0,
        'is_visible' => true,
        'source_config' => '[]',
        'selection_config' => '[]',
        'display_config' => '[]',
        'pagination_config' => '[]',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(ContentTag::class, 'tag_id');
    }

    public function contentGroup(): BelongsTo
    {
        return $this->belongsTo(ContentGroup::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceConfig(): array
    {
        return $this->configArray('source_config');
    }

    /**
     * @return array<string, mixed>
     */
    public function selectionConfig(): array
    {
        return $this->configArray('selection_config');
    }

    /**
     * @return array<string, mixed>
     */
    public function displayConfig(): array
    {
        return $this->configArray('display_config');
    }

    /**
     * @return array<string, mixed>
     */
    public function paginationConfig(): array
    {
        return $this->configArray('pagination_config');
    }

    protected static function booted(): void
    {
        static::creating(function (HomepageSection $homepageSection): void {
            $homepageSection->slug = static::uniqueSlug($homepageSection->slug ?: $homepageSection->name);
            $homepageSection->limit ??= 6;
            $homepageSection->sort_order ??= 0;
            $homepageSection->is_visible ??= true;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'content_group_id' => 'integer',
            'display_config' => 'array',
            'is_visible' => 'boolean',
            'limit' => 'integer',
            'pagination_config' => 'array',
            'selection_config' => 'array',
            'source_config' => 'array',
            'sort_order' => 'integer',
            'tag_id' => 'integer',
            'type' => HomepageSectionType::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function configArray(string $key): array
    {
        $value = $this->getAttribute($key);

        return is_array($value) ? $value : [];
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
