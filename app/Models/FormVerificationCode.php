<?php

namespace App\Models;

use App\Enums\FormVerificationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'channel',
    'address',
    'code_hash',
    'form_key',
    'guest_token_hash',
    'expires_at',
    'attempts',
    'verified_at',
    'consumed_at',
])]
class FormVerificationCode extends Model
{
    use MassPrunable;

    /**
     * Codes 30 days past expiry (operator decision 2026-08-08, D4). No
     * retention or audit expectation exists for used codes — verified against
     * docs and tests before choosing the window. MassPrunable on purpose:
     * deleting a dead code has no side effects, so no per-row events needed.
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subDays(30));
    }

    protected $attributes = [
        'attempts' => 0,
    ];

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    #[Scope]
    protected function forChallenge(
        Builder $query,
        FormVerificationChannel $channel,
        string $address,
        string $formKey,
        string $guestTokenHash,
    ): Builder {
        return $query
            ->where('channel', $channel)
            ->where('address', $address)
            ->where('form_key', $formKey)
            ->where('guest_token_hash', $guestTokenHash);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => FormVerificationChannel::class,
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
