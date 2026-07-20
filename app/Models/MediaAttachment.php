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

#[Fillable(['media_id', 'attachable_type', 'attachable_id', 'role', 'position'])]
class MediaAttachment extends Model
{
    use HasFactory;

    protected $attributes = [
        'position' => 0,
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

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
