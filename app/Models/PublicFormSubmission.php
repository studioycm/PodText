<?php

namespace App\Models;

use App\Enums\PublicFormSubmissionStatus;
use Database\Factories\PublicFormSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'form_key',
    'form_name_snapshot',
    'payload',
    'status',
    'submitted_at',
    'source_url',
    'submitter_ip_hash',
    'user_agent_hash',
    'metadata',
    'verification_channel',
    'verification_verified_at',
])]
class PublicFormSubmission extends Model
{
    /** @use HasFactory<PublicFormSubmissionFactory> */
    use HasFactory;

    public const NEW_SUBMISSIONS_NAVIGATION_BADGE_CACHE_KEY = 'public_form_submissions.new_navigation_badge';

    protected $attributes = [
        'status' => 'new',
    ];

    protected static function booted(): void
    {
        static::creating(function (PublicFormSubmission $submission): void {
            $submission->submitted_at ??= now();
        });

        // Void on purpose: Cache::forget() returns false for an absent key,
        // and a model-event listener returning exactly false HALTS the
        // dispatcher's listener loop (Dispatcher::dispatch, break-on-false) —
        // which silently skipped every later eloquent.saved listener, such
        // as the dashboard's EditorialMetricsCacheObserver.
        static::saved(function (): void {
            Cache::forget(self::NEW_SUBMISSIONS_NAVIGATION_BADGE_CACHE_KEY);
        });
        static::deleted(function (): void {
            Cache::forget(self::NEW_SUBMISSIONS_NAVIGATION_BADGE_CACHE_KEY);
        });
    }

    public function markReviewed(): void
    {
        $this->update(['status' => PublicFormSubmissionStatus::Reviewed]);
    }

    public function archive(): void
    {
        $this->update(['status' => PublicFormSubmissionStatus::Archived]);
    }

    public function reopen(): void
    {
        $this->update(['status' => PublicFormSubmissionStatus::New]);
    }

    public function scopeStatus(Builder $query, PublicFormSubmissionStatus|string $status): Builder
    {
        $status = $status instanceof PublicFormSubmissionStatus ? $status->value : $status;

        return $query->where('status', $status);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'status' => PublicFormSubmissionStatus::class,
            'submitted_at' => 'datetime',
            'verification_verified_at' => 'datetime',
        ];
    }
}
