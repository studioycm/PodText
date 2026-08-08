<?php

namespace App\Models;

use App\Enums\NavigationBadge;
use App\Enums\PublicFormSubmissionStatus;
use App\Models\Concerns\HasFoldedSearchColumns;
use App\Models\Contracts\FoldsSearchColumns;
use App\Support\NavigationBadgeCount;
use Database\Factories\PublicFormSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
class PublicFormSubmission extends Model implements FoldsSearchColumns
{
    /** @use HasFactory<PublicFormSubmissionFactory> */
    use HasFactory;

    use HasFoldedSearchColumns;

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
        // as the dashboard's EditorialMetricsCacheObserver. Both the void
        // return and NavigationBadgeCount::forget()'s own void return matter.
        static::saved(function (): void {
            NavigationBadgeCount::forget(NavigationBadge::FormSubmissions);
        });
        static::deleted(function (): void {
            NavigationBadgeCount::forget(NavigationBadge::FormSubmissions);
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

    #[Scope]
    protected function status(Builder $query, PublicFormSubmissionStatus|string $status): Builder
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

    /**
     * @return array<string, string>
     */
    public static function foldedSearchColumns(): array
    {
        return [
            'form_name_snapshot' => 'form_name_snapshot_search',
        ];
    }
}
