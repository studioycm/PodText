<?php

namespace App\Auth\LegacyRoleBackfill;

final class PrivacyHasher
{
    private const HKDF_INFO = 'podtext:authz1-c:report:v1';

    private readonly string $key;

    public function __construct(?string $appKey = null)
    {
        $material = $appKey ?? config('app.key');

        if (! is_string($material) || $material === '') {
            throw new PrivacyKeyException('The authorization reporting key is unavailable.');
        }

        if (str_starts_with($material, 'base64:')) {
            $decoded = base64_decode(substr($material, 7), true);

            if (! is_string($decoded) || $decoded === '') {
                throw new PrivacyKeyException('The authorization reporting key is malformed.');
            }

            $material = $decoded;
        }

        $key = hash_hkdf('sha256', $material, 32, self::HKDF_INFO);

        if (! is_string($key) || strlen($key) !== 32) {
            throw new PrivacyKeyException('The authorization reporting key could not be derived.');
        }

        $this->key = $key;
    }

    public function keyId(): string
    {
        return hash('sha256', $this->key);
    }

    public function userHash(int|string $id): string
    {
        return $this->hmac('user', CanonicalJson::encode([
            'type' => is_int($id) ? 'integer' : 'string',
            'value' => (string) $id,
        ]));
    }

    public function rawRoleHash(mixed $role): string
    {
        $type = get_debug_type($role);
        $bytes = is_string($role) ? $role : CanonicalJson::encode($role);

        return $this->hmac('raw-role', CanonicalJson::encode([
            'type' => $type,
            'length' => strlen($bytes),
            'bytes' => base64_encode($bytes),
        ]));
    }

    public function fingerprint(string $domain, mixed $value): string
    {
        return $this->hmac($domain, CanonicalJson::encode($value));
    }

    private function hmac(string $domain, string $payload): string
    {
        return hash_hmac('sha256', $domain."\0".$payload, $this->key);
    }
}
