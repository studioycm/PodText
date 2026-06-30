<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class HttpsUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $value = (string) $value;

        if (str_contains($value, '<') || str_contains($value, '>')) {
            $fail(__('admin.validation.url_no_html'));

            return;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail(__('validation.url', ['attribute' => $attribute]));

            return;
        }

        if (parse_url($value, PHP_URL_SCHEME) !== 'https') {
            $fail(__('admin.validation.url_https'));
        }
    }
}
