<?php

use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->startedAt = Carbon::parse('2026-10-17 12:40:13');
    $this->travelTo($this->startedAt);
    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => $this->startedAt->toISOString()])
        ->assertOk();
});

function sendProgress(User $user, Carbon $startedAt, int $seconds, array $values = [])
{
    return test()->actingAs($user)->patchJson('/focused-sessions/current', $values + [
        'started_at' => $startedAt->toISOString(),
        'time_focused' => $seconds,
        'in_progress' => true,
    ]);
}

test('time focused so far is kept when the session never ends', function () {
    // The pomodoro was paused, then everyone left the call.
    sendProgress($this->user, $this->startedAt, 88 * 60, ['note' => 'chapter 4'])->assertOk();

    $session = $this->user->focusedSessions()->sole();
    expect($session->minute_focused)->toBe(88)
        ->and($session->note)->toBe('chapter 4')
        ->and($session->ended_at)->toBeNull();

    $this->actingAs($this->user)
        ->getJson('/user-stats/day/2026-10-17')
        ->assertJsonCount(1, 'sessions')
        ->assertJsonPath('sessions.0.minutes_focused', 88);
});

test('the end replaces the progress', function () {
    sendProgress($this->user, $this->startedAt, 10 * 60)->assertOk();

    $this->actingAs($this->user)->patchJson('/focused-sessions/current', [
        'started_at' => $this->startedAt->toISOString(),
        'ended_at' => $this->startedAt->copy()->addMinutes(25)->toISOString(),
        'time_focused' => 25 * 60,
    ])->assertOk();

    $session = $this->user->focusedSessions()->sole();
    expect($session->minute_focused)->toBe(25)
        ->and($session->ended_at)->not->toBeNull();
});

test('progress arriving after the end changes nothing', function () {
    $this->actingAs($this->user)->patchJson('/focused-sessions/current', [
        'started_at' => $this->startedAt->toISOString(),
        'ended_at' => $this->startedAt->copy()->addMinutes(25)->toISOString(),
        'time_focused' => 25 * 60,
    ])->assertOk();

    sendProgress($this->user, $this->startedAt, 24 * 60)->assertOk();

    $session = $this->user->focusedSessions()->sole();
    expect($session->minute_focused)->toBe(25)
        ->and($session->ended_at)->not->toBeNull();
});

test('progress on a session whose start never reached the server is kept', function () {
    $later = $this->startedAt->copy()->addHour();

    sendProgress($this->user, $later, 5 * 60)->assertOk();

    $session = $this->user->focusedSessions()->where('started_at', $later)->sole();
    expect($session->minute_focused)->toBe(5)
        ->and($session->ended_at)->toBeNull();
});

test('a session nobody has focused on yet is not in the day log', function () {
    $this->actingAs($this->user)
        ->getJson('/user-stats/day/2026-10-17')
        ->assertJsonCount(0, 'sessions');
});

test('progress still needs its start time', function () {
    $this->actingAs($this->user)->patchJson('/focused-sessions/current', [
        'time_focused' => 60,
        'in_progress' => true,
    ])->assertUnprocessable();
});
