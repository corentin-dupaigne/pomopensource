<?php

use App\Http\Controllers\ActivityRoomController;
use App\Http\Controllers\DiscordActivityController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\UserStatsController;
use App\Http\Controllers\FocusedSessionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Linked from the Discord Developer Portal, which needs both for a public Activity.
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');

// A page left open past the session lifetime holds a stale CSRF token, and
// every write is refused until a reload. The client calls this for a fresh
// one (any GET sets it as the XSRF-TOKEN cookie), and to learn whether the
// remember-me cookie signed the user back in.
Route::get('/session/refresh', fn () => response()->json(['authenticated' => Auth::check()]))
    ->name('session.refresh');

Route::post('/discord/token', [DiscordActivityController::class, 'token'])
    ->middleware('throttle:20,1')
    ->name('discord.token');
Route::get('/discord/session', [DiscordActivityController::class, 'session'])->name('discord.session');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Routes for Focused Session
    Route::post('/focused-sessions', [FocusedSessionController::class, 'store'])->name('focused-sessions.store');
    Route::patch('/focused-sessions/current', [FocusedSessionController::class, 'update'])->name('focused-sessions.update');
    Route::get('/focused-sessions', [FocusedSessionController::class, 'index'])->name('focused-sessions.index');
    Route::patch('/focused-sessions/notes', [FocusedSessionController::class, 'updateNotes'])->name('focused-sessions.notes');

    // Routes for User Stats
    Route::get('/user-stats', [UserStatsController::class, 'index'])->name('user-stats.index');
    Route::get('/user-stats/calendar/{year}', [UserStatsController::class, 'getCalendarData']);
    Route::get('/user-stats/calendar/{year}/{month}', [UserStatsController::class, 'getCalendarData']);
    Route::get('/user-stats/calendar/{year}/{month}/{day}', [UserStatsController::class, 'getCalendarData']);
    Route::get('/user-stats/day/{date}', [UserStatsController::class, 'getDayLog'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('user-stats.day');
    Route::get('/projects-stats', [UserStatsController::class, 'getProjectStats'])->name('projects-stats');

    // The timer shared by everyone in a Discord Activity instance
    Route::get('/activity-rooms/{instance}', [ActivityRoomController::class, 'show'])
        ->where('instance', '[A-Za-z0-9_-]{1,100}')
        ->name('activity-rooms.show');
    Route::post('/activity-rooms/{instance}', [ActivityRoomController::class, 'update'])
        ->where('instance', '[A-Za-z0-9_-]{1,100}')
        ->middleware('throttle:120,1')
        ->name('activity-rooms.update');

});

Route::get('/user-settings', [UserSettingsController::class, 'index'])->name('user-settings.index');
Route::patch('/user-settings', [UserSettingsController::class, 'update'])->name('user-settings.update');
Route::get('/background', [UserSettingsController::class, 'getBackground'])->name('user-settings.getBackground');

require __DIR__.'/auth.php';
