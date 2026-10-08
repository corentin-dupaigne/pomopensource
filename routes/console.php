<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Shared Activity timers of calls that ended.
Schedule::command('model:prune', ['--model' => [\App\Models\ActivityRoom::class]])->daily();
