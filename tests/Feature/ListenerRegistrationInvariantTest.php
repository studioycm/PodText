<?php

use Illuminate\Support\Facades\Event;

/**
 * Guard for the `double-registration` pattern: framework auto-discovery plus
 * an explicit Event::listen registers a listener twice, and the handler
 * fires twice per event with no error (found live on StampImportSource,
 * fixed pre-land in 6e6a03a). This invariant pins the whole listener map:
 * no event may carry the same listener registration twice.
 *
 * Closures are keyed by their definition site (file:line) rather than
 * object identity, so the same closure source registered twice is caught
 * while distinct closures that merely look alike are not.
 *
 * @return array<int, string>
 */
function duplicateListenerRegistrations(): array
{
    return collect(Event::getRawListeners())
        ->flatMap(function (array $listeners, string $event): array {
            return collect($listeners)
                ->map(function (mixed $listener) use ($event): string {
                    if ($listener instanceof Closure) {
                        $ref = new ReflectionFunction($listener);

                        return $event.'|closure@'.$ref->getFileName().':'.$ref->getStartLine();
                    }

                    if (is_array($listener)) {
                        $class = is_object($listener[0]) ? $listener[0]::class : (string) $listener[0];

                        return $event.'|'.$class.'@'.($listener[1] ?? 'handle');
                    }

                    return $event.'|'.(string) $listener;
                })
                ->all();
        })
        ->countBy()
        ->filter(fn (int $count): bool => $count > 1)
        ->keys()
        ->all();
}

it('registers every listener exactly once per event', function (): void {
    expect(duplicateListenerRegistrations())->toBe([], sprintf(
        'Duplicate listener registrations found (double-registration pattern — one home per listener: rely on discovery OR wire explicitly, never both): %s',
        implode('; ', duplicateListenerRegistrations()),
    ));
});

it('detects a deliberate duplicate, so an empty result is not detector failure', function (): void {
    Event::listen('synthetic.double-registration.probe', [self::class, 'toString']);
    Event::listen('synthetic.double-registration.probe', [self::class, 'toString']);

    expect(duplicateListenerRegistrations())
        ->toContain('synthetic.double-registration.probe|'.self::class.'@toString');
});
