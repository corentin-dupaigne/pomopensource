<?php

use App\Models\User;
use Carbon\Carbon;

function endPomodoro(User $user, string $startedAt = '2026-11-23 09:00:00', int $seconds = 25 * 60)
{
    return test()->actingAs($user)->patchJson('/focused-sessions/current', [
        'started_at' => Carbon::parse($startedAt)->toISOString(),
        'ended_at' => Carbon::parse($startedAt)->addSeconds($seconds)->toISOString(),
        'time_focused' => $seconds,
    ]);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-23 12:00:00'));
    $this->user = User::factory()->create();
});

test('the first pomodoro of an account that never opened its stats points to them', function () {
    endPomodoro($this->user)->assertOk()->assertJson(['stats_hint' => 25]);

    expect($this->user->fresh()->stats_hint_at)->not->toBeNull();
});

test('it is pointed to once', function () {
    endPomodoro($this->user)->assertJson(['stats_hint' => 25]);
    endPomodoro($this->user, '2026-11-23 10:00:00')->assertOk()->assertJson(['stats_hint' => null]);
});

test('an account that opened its stats is not pointed to them', function () {
    $this->actingAs($this->user)->getJson('/user-stats')->assertOk();

    endPomodoro($this->user)->assertOk()->assertJson(['stats_hint' => null]);
});

test('progress and pomodoros of less than a minute do not point to the stats', function () {
    $this->actingAs($this->user)->patchJson('/focused-sessions/current', [
        'started_at' => Carbon::parse('2026-11-23 09:00:00')->toISOString(),
        'time_focused' => 10 * 60,
        'in_progress' => true,
    ])->assertOk()->assertJson(['stats_hint' => null]);
    endPomodoro($this->user, '2026-11-23 11:00:00', 20)->assertJson(['stats_hint' => null]);

    expect($this->user->fresh()->stats_hint_at)->toBeNull();
    endPomodoro($this->user, '2026-11-23 11:30:00')->assertJson(['stats_hint' => 25]);
});

test('a retry of an ended session does not point to the stats again', function () {
    endPomodoro($this->user)->assertJson(['stats_hint' => 25]);
    endPomodoro($this->user)->assertOk()->assertJsonMissing(['stats_hint' => 25]);
});
