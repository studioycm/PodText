<?php

namespace App\Support\SettingsLifecycle;

use Closure;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Throwable;

class PublicContentSettingsWriteCoordinator
{
    public const LOCK_KEY = 'settings:public_content:write';

    private ?Lock $activeLock = null;

    public function __construct(
        private readonly int $leaseSeconds = 300,
        private readonly int $waitSeconds = 5,
    ) {}

    public function withinLock(Closure $callback): mixed
    {
        if ($this->activeLock instanceof Lock) {
            throw new LogicException('This public content settings writer coordinator already owns the group lock.');
        }

        $lock = Cache::lock(self::LOCK_KEY, $this->leaseSeconds());

        try {
            $lock->block($this->waitSeconds());
        } catch (LockTimeoutException $exception) {
            throw new LockTimeoutException(
                'Timed out acquiring the public content settings write lock.',
                previous: $exception,
            );
        }

        $this->activeLock = $lock;

        try {
            return $callback();
        } finally {
            $this->activeLock = null;
            $lock->release();
        }
    }

    public function transaction(Closure $callback): mixed
    {
        return $this->withinLock(fn (): mixed => DB::transaction(function () use ($callback): mixed {
            $result = $callback();
            $this->refreshLeaseOrFail();

            return $result;
        }));
    }

    /**
     * Native transaction owners must call this immediately before commit.
     *
     * This supports Filament SettingsPage, whose parent lifecycle owns the
     * database transaction while the coordinator owns the outer group lock.
     * `withinLock()` deliberately does not check again after its callback,
     * because that callback may already have committed.
     */
    public function refreshLeaseOrFail(): void
    {
        $lock = $this->activeLock;

        if (! $lock instanceof Lock) {
            throw new LogicException('The public content settings write lock is not owned by this coordinator.');
        }

        try {
            $refreshed = $lock->refresh($this->leaseSeconds());
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'The public content settings write lock lease could not be refreshed before commit.',
                previous: $exception,
            );
        }

        if (! $refreshed) {
            throw new RuntimeException(
                'The public content settings writer lost ownership of its lock before commit.',
            );
        }
    }

    private function leaseSeconds(): int
    {
        return min(600, max(1, $this->leaseSeconds));
    }

    private function waitSeconds(): int
    {
        return min(10, max(0, $this->waitSeconds));
    }
}
