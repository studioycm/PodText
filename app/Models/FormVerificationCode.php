<?php

namespace App\Models;

use App\Enums\FormVerificationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
    protected $attributes = [
        'attempts' => 0,
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    public function scopeForChallenge(
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
