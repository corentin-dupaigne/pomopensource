<?php

use Illuminate\Support\Facades\Schedule;

// Shared Activity timers of calls that ended.
Schedule::command('model:prune', ['--model' => [\App\Models\ActivityRoom::class]])->daily();
