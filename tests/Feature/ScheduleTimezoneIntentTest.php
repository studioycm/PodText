<?php

use App\Support\UiTimezone;
use Illuminate\Console\Scheduling\Schedule;

it('gives every schedule entry an explicit timezone', function (): void {
    $events = app(Schedule::class)->events();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event->timezone)
            ->not->toBeNull()
            ->toBe(UiTimezone::name(), "Unpinned schedule entry: {$event->command}");
    }
});
