<?php

namespace App\Models;

use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use Database\Factories\MediaMutationOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_key',
    'media_id',
    'media_id_snapshot',
    'user_id',
    'media_reference_key',
    'operation',
    'status',
    'purpose',
    'idempotency_key',
    'source_disk',
    'source_path',
    'source_sha256',
    'destination_disk',
    'destination_path',
    'destination_sha256',
    'staging_disk',
    'staging_path',
    'staging_sha256',
    'quarantine_disk',
    'quarantine_path',
    'quarantine_sha256',
    'context',
    'attempts',
    'lease_token',
    'lease_expires_at',
    'last_error',
    'started_at',
    'committed_at',
    'cleanup_completed_at',
    'completed_at',
    'failed_at',
])]
class MediaMutationOperation extends Model
{
    use HasFactory;

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'cleanup_completed_at' => 'datetime',
            'committed_at' => 'datetime',
            'completed_at' => 'datetime',
            'context' => 'array',
            'failed_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'media_id_snapshot' => 'integer',
            'operation' => MediaMutationOperationType::class,
            'started_at' => 'datetime',
            'status' => MediaMutationStatus::class,
        ];
    }

    protected static function newFactory(): Factory
    {
        return MediaMutationOperationFactory::new();
    }
}
