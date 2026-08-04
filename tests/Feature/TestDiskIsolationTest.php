<?php

declare(strict_types=1);

use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;

/*
 * Pins the cure for the browser-suite timeout contention defect: faked disks
 * must be rooted per process, so a concurrent pest run's `Storage::fake()`
 * (which purges its root) cannot delete fixtures out from under an in-flight
 * test. Mechanism, measurements and the fix verification:
 * docs/research/browser-timeout-contention-investigation.md.
 */

it('roots faked disks per process so a concurrent run cannot purge them', function (string $disk): void {
    Storage::fake($disk);
    Storage::disk($disk)->put('isolation-probe.txt', 'probe');

    $token = (string) ParallelTesting::token();
    $root = dirname(Storage::disk($disk)->path('isolation-probe.txt'));
    $sharedRoot = storage_path('framework/testing/disks/'.$disk);

    expect($token)->not->toBe('')
        ->and($root)->toBe($sharedRoot.'_test_'.$token)
        ->and($root)->not->toBe($sharedRoot)
        ->and(Storage::disk($disk)->exists('isolation-probe.txt'))->toBeTrue();
})->with(['public', 'local']);

it('derives the process token from the running pid when the runner supplies none', function (): void {
    expect((string) ParallelTesting::token())->toBe('p'.getmypid());
});

it('leaves the shared unsuffixed root out of the write path entirely', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('isolation-probe.txt', 'probe');

    expect(file_exists(storage_path('framework/testing/disks/public/isolation-probe.txt')))->toBeFalse();
});
