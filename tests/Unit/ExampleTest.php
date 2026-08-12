<?php

/*
 * Kept deliberately and converted rather than deleted (operator decision,
 * 2026-08-12). It asserts nothing about this application, which is exactly why
 * it keeps getting proposed for deletion — but it is Pest-native now, and that
 * is load-bearing for one concrete reason: Pest 5's
 * EnsureTiaIsRunningPestTestsOnly hard-panics MID-RUN on any PHPUnit-CLASS test
 * (:39-43), and this file was the last one in the suite.
 */

test('that true is true', function (): void {
    expect(true)->toBeTrue();
});
