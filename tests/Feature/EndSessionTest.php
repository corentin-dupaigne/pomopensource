<?php

use App\Models\FocusedSession;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function startSession(User $user, string $startedAt): void
{
    test()->travelTo(Carbon::parse($startedAt));
    test()->actingAs($user)
        ->postJson('/focused-sessions', ['started_at' => Carbon::parse($startedAt)->toISOString()])
        ->assertOk();
}

function endSession(User $user, string $startedAt, array $values = [])
{
    return test()->actingAs($user)->patchJson('/focused-sessions/current', $values + [
        'started_at' => Carbon::parse($startedAt)->toISOString(),
        'ended_at' => Carbon::parse($startedAt)->addMinutes(25)->toISOString(),
        'time_focused' => 25 * 60,
    ]);
}

test('ending a session closes the session it started, not a newer one', function () {
    // Two tabs or devices: the second started while the first still ran.
    startSession($this->user, '2026-10-16 09:00:00.250');
    startSession($this->user, '2026-10-16 09:10:00');

    endSession($this->user, '2026-10-16 09:00:00.250')->assertOk();

    $first = $this->user->focusedSessions()->orderBy('started_at')->first();
    expect($first->minute_focused)->toBe(25)
        ->and($first->ended_at)->not->toBeNull()
        ->and($this->user->focusedSessions()->whereNull('ended_at')->count())->toBe(1);
});

test('time whose start never reached the server goes on the day it was worked', function () {
    // A session left open days ago, e.g. by a tab that was closed.
    startSession($this->user, '2026-10-12 18:00:00');

    // Today's start request failed: the server only hears of the end.
    $this->travelTo(Carbon::parse('2026-10-16 14:25:00'));
    endSession($this->user, '2026-10-16 14:00:00', ['note' => 'chapter 4'])->assertOk();

    $this->actingAs($this->user)
        ->getJson('/user-stats/day/2026-10-16')
        ->assertJsonCount(1, 'sessions')
        ->assertJsonPath('sessions.0.minutes_focused', 25)
        ->assertJsonPath('sessions.0.note', 'chapter 4');
    $this->actingAs($this->user)
        ->getJson('/user-stats/day/2026-10-12')
        ->assertJsonCount(0, 'sessions');
});

test('time whose start never reached the server is logged even with no open session', function () {
    $project = $this->user->projects()->create(['name' => 'Maths']);

    endSession($this->user, '2026-10-16 14:00:00', ['project_id' => $project->id])->assertOk();

    $session = $this->user->focusedSessions()->sole();
    expect($session->minute_focused)->toBe(25)
        ->and($session->project_id)->toBe($project->id)
        ->and($session->started_at->toDateTimeString())->toBe('2026-10-16 14:00:00');
});

test('a session sent twice is counted once', function () {
    startSession($this->user, '2026-10-16 09:00:00');

    endSession($this->user, '2026-10-16 09:00:00')->assertOk();
    endSession($this->user, '2026-10-16 09:00:00')->assertOk();

    expect($this->user->focusedSessions()->count())->toBe(1)
        ->and($this->user->focusedSessions()->sum('minute_focused'))->toBe(25);
});

test('a session cannot be logged on someone else\'s project', function () {
    $project = User::factory()->create()->projects()->create(['name' => 'Theirs']);

    endSession($this->user, '2026-10-16 14:00:00', ['project_id' => $project->id])
        ->assertUnprocessable();
});

test('someone else\'s session is never closed', function () {
    $other = User::factory()->create();
    startSession($other, '2026-10-16 09:00:00');

    endSession($this->user, '2026-10-16 09:00:00')->assertOk();

    expect(FocusedSession::where('user_id', $other->id)->sole()->ended_at)->toBeNull();
});
