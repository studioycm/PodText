<?php

namespace App\Auth\LegacyRoleBackfill;

use Illuminate\Cache\CacheManager;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final readonly class PermissionCacheInvalidator
{
    public function __construct(
        private CacheManager $cacheManager,
        private PermissionRegistrar $registrar,
    ) {}

    public function invalidate(): PermissionCacheInvalidationOutcome
    {
        $storeName = config('permission.cache.store', 'default');
        $key = config('permission.cache.key');

        if (! is_string($storeName) || ! is_string($key) || $key === '') {
            throw new BackfillException('The permission cache contract is unavailable.');
        }

        try {
            $store = match (true) {
                $storeName === 'default' => $this->cacheManager->store(),
                array_key_exists($storeName, config('cache.stores')) => $this->cacheManager->store($storeName),
                default => $this->cacheManager->store('array'),
            };
            $wasPresent = $store->has($key);
            $this->registrar->forgetCachedPermissions();
            $isPresent = $store->has($key);
        } catch (Throwable $exception) {
            throw new BackfillException('The permission cache could not be invalidated.', previous: $exception);
        }

        if ($isPresent) {
            throw new BackfillException('The permission cache key remains after invalidation.');
        }

        return $wasPresent
            ? PermissionCacheInvalidationOutcome::Deleted
            : PermissionCacheInvalidationOutcome::AlreadyAbsent;
    }
}
