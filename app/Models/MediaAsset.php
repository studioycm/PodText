<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['reference_key'])]
class MediaAsset extends Model
{
    public function providerBindings(): HasMany
    {
        return $this->hasMany(MediaProviderBinding::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class);
    }

    protected static function booted(): void
    {
        static::creating(function (MediaAsset $asset): void {
            $asset->reference_key ??= (string) Str::ulid();
        });

        static::updating(function (MediaAsset $asset): void {
            if ($asset->isDirty('reference_key')) {
                throw new LogicException('A media asset reference key is immutable after creation.');
            }
        });
    }
}
