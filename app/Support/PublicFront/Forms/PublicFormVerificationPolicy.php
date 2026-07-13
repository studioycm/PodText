<?php

namespace App\Support\PublicFront\Forms;

use App\Enums\PublicFormEmailVerificationMode;
use App\Support\PublicFront\PublicFrontConfigReader;
use Illuminate\Support\Str;

class PublicFormVerificationPolicy
{
    public function __construct(
        private readonly PublicFrontConfigReader $configReader,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public function requiresEmailVerification(array $definition): bool
    {
        if ($this->submitterEmailField($definition) === null) {
            return false;
        }

        if ((bool) ($this->configReader->group('public_forms')['require_email_verification'] ?? false)) {
            return true;
        }

        return ($definition['settings']['submitter_email_verification'] ?? PublicFormEmailVerificationMode::Off->value)
            === PublicFormEmailVerificationMode::EmailOtp->value;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>|null
     */
    public function submitterEmailField(array $definition): ?array
    {
        foreach ($definition['fields'] ?? [] as $field) {
            if (! is_array($field) || blank($field['key'] ?? null)) {
                continue;
            }

            if (($field['type'] ?? null) === 'email' || ($field['validation_semantics'] ?? null) === 'email') {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $payload
     */
    public function submitterEmail(array $definition, array $payload): ?string
    {
        $field = $this->submitterEmailField($definition);

        if ($field === null) {
            return null;
        }

        $value = $payload[(string) $field['key']] ?? null;

        if (! is_scalar($value) || blank($value)) {
            return null;
        }

        return Str::of((string) $value)->trim()->lower()->toString();
    }
}
