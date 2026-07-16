<?php

namespace App\Auth\LegacyRoleBackfill;

use Illuminate\Encryption\Encrypter;

final class PrivacyHasher
{
    private const HKDF_INFO = 'podtext:authz1-c:report:v2';

    private readonly string $key;

    public function __construct(?string $appKey = null, ?string $cipher = null)
    {
        $material = $appKey ?? config('app.key');
        $cipher ??= config('app.cipher');

        if (! is_string($material) || $material === '' || ! is_string($cipher) || $cipher === '') {
            throw new PrivacyKeyException('The authorization reporting key is unavailable.');
        }

        if (str_starts_with($material, 'base64:')) {
            $material = base64_decode(substr($material, 7));
        }

        if (! is_string($material) || ! Encrypter::supported($material, $cipher)) {
            throw new PrivacyKeyException('The authorization reporting key is unavailable.');
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

    /** @param array<string, mixed> $payload */
    public function artifactMac(string $domain, array $payload): string
    {
        unset($payload['artifact_mac']);

        return $this->hmac('artifact:'.$domain, CanonicalJson::encode($payload));
    }

    public function physicalTupleHash(
        int $roleId,
        int|string $modelId,
        string $modelType,
    ): string {
        return $this->fingerprint('physical-role-tuple', [
            'role_id' => $roleId,
            'model_id' => [
                'type' => is_int($modelId) ? 'integer' : 'string',
                'value' => (string) $modelId,
            ],
            'model_type' => $modelType,
        ]);
    }

    private function hmac(string $domain, string $payload): string
    {
        return hash_hmac('sha256', $domain."\0".$payload, $this->key);
    }
}
