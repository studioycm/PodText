<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Rejects wall-clock times that do not exist in the given timezone — the
 * spring-forward gap, where PHP silently normalizes 02:30 to 03:30 (measured,
 * alignment spec §10.3). Detection is round-trip: parse, re-format, compare —
 * PHP has no dedicated gap API; the normalize-forward mismatch IS the
 * detector. The autumn fold hour exists twice and is ACCEPTED (accept-later
 * policy, spec §10.3) — it round-trips cleanly, so it passes here.
 */
class ExistsInTimezone implements ValidationRule
{
    public function __construct(
        private readonly string $timezone,
        private readonly string $format,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return; // presence is someone else's rule
        }

        try {
            $parsed = Carbon::createFromFormat($this->format, $value, $this->timezone);
        } catch (Throwable) {
            return; // format errors are someone else's rule too
        }

        if ($parsed !== null && $parsed->format($this->format) !== $value) {
            $fail(__('admin.validation.nonexistent_wall_time', ['timezone' => $this->timezone]));
        }
    }
}
