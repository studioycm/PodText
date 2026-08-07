<?php

namespace App\Models;

use App\Enums\MediaAttachmentRole;
use Database\Factories\MediaAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['media_id', 'media_asset_id', 'attachable_type', 'attachable_id', 'role', 'position'])]
class MediaAttachment extends Model
{
    use HasFactory;

    protected $attributes = [
        'position' => 0,
    ];

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * The morph map admits only ContentGroup and ContentItem, but `morphTo()`
     * with no argument returns `MorphTo<Model, $this>` and PHPStan checks the
     * body against the tag. Narrowing the generic to that union is therefore a
     * claim it rejects, not an improvement — keep `Model` here.
     *
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'role' => MediaAttachmentRole::class,
        ];
    }

    protected static function newFactory(): Factory
    {
        return MediaAttachmentFactory::new();
    }
}
