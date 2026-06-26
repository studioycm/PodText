<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ApprovedEmbedUrl implements ValidationRule
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
            $fail(__('admin.validation.embed_url_no_html'));

            return;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);

        if ($scheme !== 'https') {
            $fail(__('admin.validation.embed_url_https'));

            return;
        }

        $allowedHosts = array_map('strtolower', config('media.embeds.allowed_hosts', []));

        if (! in_array(strtolower((string) $host), $allowedHosts, true)) {
            $fail(__('admin.validation.embed_url_host'));
        }
    }
}
