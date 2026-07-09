<?php

namespace App\Models;

use App\Enums\SettingsBackupSource;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected function casts(): array
    {
        return [
            'source' => SettingsBackupSource::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
