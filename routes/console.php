<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs only when the host invokes `php artisan schedule:run` (no scheduler is
// provisioned by the app itself).
Schedule::command('media:prune-quarantine --apply')->dailyAt('03:30');
Schedule::command('model:prune')->dailyAt('03:50');
