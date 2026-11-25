<?php

use App\Jobs\SyncEeaE1aJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncEeaE1aJob)
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping();
