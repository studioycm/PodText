<?php

namespace App\Support\Settings;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

class SettingsPageProfiler
{
    public const REQUEST_INITIAL_LOAD = 'initial load';

    public const REQUEST_LIVEWIRE_UPDATE = 'livewire update';

    public const REQUEST_SAVE = 'save';

    private ?string $requestKind = null;

    private ?string $subject = null;

    public function isEnabled(): bool
    {
        return (bool) config('settings.profiling.enabled', false);
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function withRequestKind(string $requestKind, Closure $callback): mixed
    {
        $previousRequestKind = $this->requestKind;
        $this->requestKind = $requestKind;

        try {
            return $callback();
        } finally {
            $this->requestKind = $previousRequestKind;
        }
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function withSubject(string $subject, Closure $callback): mixed
    {
        $previousSubject = $this->subject;
        $this->subject = $subject;

        try {
            return $callback();
        } finally {
            $this->subject = $previousSubject;
        }
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function measure(string $phase, Closure $callback, ?string $requestKind = null, ?int $payloadBytes = null): mixed
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $startedAt = hrtime(true);

        try {
            return $callback();
        } finally {
            $this->record(
                phase: $phase,
                milliseconds: $this->millisecondsSince($startedAt),
                requestKind: $requestKind,
                payloadBytes: $payloadBytes,
            );
        }
    }

    /**
     * @return array{phase: string, request_kind: string, subject: string|null, started_at: int, payload_bytes: int|null}|null
     */
    public function start(string $phase, ?string $requestKind = null, ?int $payloadBytes = null): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return [
            'phase' => $phase,
            'request_kind' => $requestKind ?? $this->currentRequestKind(),
            'subject' => $this->subject,
            'started_at' => hrtime(true),
            'payload_bytes' => $payloadBytes,
        ];
    }

    /**
     * @param  array{phase: string, request_kind: string, subject: string|null, started_at: int, payload_bytes: int|null}|null  $timer
     */
    public function stop(?array $timer): void
    {
        if ($timer === null) {
            return;
        }

        $this->record(
            phase: $timer['phase'],
            milliseconds: $this->millisecondsSince($timer['started_at']),
            requestKind: $timer['request_kind'],
            payloadBytes: $timer['payload_bytes'],
            subject: $timer['subject'],
            resolveCurrentSubject: false,
        );
    }

    public function record(
        string $phase,
        float $milliseconds,
        ?string $requestKind = null,
        ?int $payloadBytes = null,
        ?string $subject = null,
        bool $resolveCurrentSubject = true,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $context = [
            'phase' => $phase,
            'milliseconds' => round($milliseconds, 3),
            'request_kind' => $requestKind ?? $this->currentRequestKind(),
            'subject' => $resolveCurrentSubject ? ($subject ?? $this->subject) : $subject,
        ];

        if ($payloadBytes !== null) {
            $context['payload_bytes'] = $payloadBytes;
        }

        Log::channel('settings_profiling')->info('Settings page profile', $context);
    }

    public function payloadBytes(mixed $payload): int
    {
        try {
            return strlen(json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } catch (Throwable) {
            return 0;
        }
    }

    public function currentRequestKind(): string
    {
        if ($this->requestKind !== null) {
            return $this->requestKind;
        }

        if (request()->headers->has('X-Livewire')) {
            return self::REQUEST_LIVEWIRE_UPDATE;
        }

        if (request()->isMethod('POST')) {
            return self::REQUEST_SAVE;
        }

        return self::REQUEST_INITIAL_LOAD;
    }

    private function millisecondsSince(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }
}
