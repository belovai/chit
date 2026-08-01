<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pipeline:expire-stale-runs')->dailyAt('03:10');
Schedule::command('pipeline:prune-artifacts')->dailyAt('03:20');
