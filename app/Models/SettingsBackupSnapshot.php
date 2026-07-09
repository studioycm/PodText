<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingsBackupSnapshot extends Model
{
    use HasFactory;

    public const FORMAT_HTML = 'html';

    public const FORMAT_PDF = 'pdf';

    public const FORMAT_PNG = 'png';

    public const KIND_FULL = 'full';

    public const KIND_THUMBNAIL = 'thumbnail';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    public const VIEWPORT_DESKTOP = 'desktop-1440';

    protected $fillable = [
        'backup_id',
        'screen_key',
        'theme',
        'viewport',
        'kind',
        'format',
        'resolved_url',
        'path',
        'status',
        'error',
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(SettingsBackupVersion::class, 'backup_id');
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isImage(): bool
    {
        return $this->format === self::FORMAT_PNG;
    }

    public function contentType(): string
    {
        return match ($this->format) {
            self::FORMAT_HTML => 'text/html; charset=UTF-8',
            self::FORMAT_PDF => 'application/pdf',
            default => 'image/png',
        };
    }

    public function fileUrl(bool $download = false): ?string
    {
        if (blank($this->path)) {
            return null;
        }

        return route('admin.settings-backup-snapshots.file', [
            'settingsBackupSnapshot' => $this,
            'download' => $download ? 1 : null,
        ]);
    }

    public function downloadFilename(): string
    {
        $timestamp = $this->created_at?->copy()->timezone('Asia/Jerusalem')->format('Ymd-His') ?? (string) $this->getKey();

        return "settings-backup-{$this->backup_id}-{$this->screen_key}-{$this->theme}-{$this->kind}-{$timestamp}.{$this->format}";
    }
}
