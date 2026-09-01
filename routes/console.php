<?php

declare(strict_types=1);

use App\Console\Commands\NotifyOverdueProblems;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * One digest a day, early enough to be waiting when the shift starts.
 * withoutOverlapping keeps a slow queue from stacking two runs.
 */
Schedule::command(NotifyOverdueProblems::class)
    ->dailyAt('07:00')
    ->withoutOverlapping();
