<?php

namespace App\Models;

use App\Enums\SettingsBackupSource;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SettingsBackupVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope',
        'label',
        'payload_json',
        'checksum',
        'payload_hash',
        'source',
        'created_by_user_id',
        'import_report',
    ];

    protected function casts(): array
    {
        return [
            'import_report' => 'array',
            'source' => SettingsBackupSource::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SettingsBackupSnapshot::class, 'backup_id');
    }

    public function homeThumbnailSnapshot(): ?SettingsBackupSnapshot
    {
        $matchesHomeThumbnail = fn (SettingsBackupSnapshot $snapshot): bool => $snapshot->screen_key === 'home'
            && $snapshot->kind === SettingsBackupSnapshot::KIND_THUMBNAIL
            && $snapshot->format === SettingsBackupSnapshot::FORMAT_PNG
            && $snapshot->status === SettingsBackupSnapshot::STATUS_DONE;

        if ($this->relationLoaded('snapshots')) {
            return $this->snapshots
                ->first($matchesHomeThumbnail);
        }

        return $this->snapshots()
            ->where('screen_key', 'home')
            ->where('kind', SettingsBackupSnapshot::KIND_THUMBNAIL)
            ->where('format', SettingsBackupSnapshot::FORMAT_PNG)
            ->where('status', SettingsBackupSnapshot::STATUS_DONE)
            ->latest('id')
            ->first();
    }

    public function package(): PublicSettingsPackage
    {
        return PublicSettingsPackage::fromArray(json_decode($this->payload_json, true, flags: JSON_THROW_ON_ERROR));
    }

    public function shortPayloadHash(): string
    {
        return Str::substr($this->payload_hash, 0, 12);
    }

    public function packageSize(): int
    {
        return strlen($this->payload_json);
    }

    public function downloadFilename(): string
    {
        $timestamp = $this->created_at?->copy()->timezone('Asia/Jerusalem')->format('Ymd-His') ?? (string) $this->getKey();

        return "public-content-settings-backup-{$timestamp}.json";
    }
}
