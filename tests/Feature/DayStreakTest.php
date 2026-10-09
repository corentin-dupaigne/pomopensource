<?php

use App\Models\FocusedSession;
use App\Models\User;
use Carbon\Carbon;

function focusedOn(User $user, string $day): void
{
    $start = Carbon::parse($day)->setTime(10, 0);
    FocusedSession::create([
        'user_id' => $user->id,
        'started_at' => $start,
        'ended_at' => $start->copy()->addMinutes(25),
        'minute_focused' => 25,
    ]);
}

function streakOf(User $user): int
{
    return test()->actingAs($user)->getJson('/user-stats')->assertOk()->json('stats.day_streak');
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-09 15:00'));
    $this->user = User::create(['name' => 'Focus Fan', 'email' => 'fan@example.com', 'password' => 'secret']);
});

test('no sessions means no streak', function () {
    expect(streakOf($this->user))->toBe(0);
});

test('reading the stats again does not grow the streak', function () {
    focusedOn($this->user, '2026-10-07');
    focusedOn($this->user, '2026-10-08');

    foreach (range(1, 5) as $_) {
        expect(streakOf($this->user))->toBe(2);
    }
    $this->actingAs($this->user)->getJson('/user-stats/calendar/2026/10?view=month')
        ->assertJson(['currentStreak' => 2]);
});

test('consecutive days up to today count', function () {
    foreach (['2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09'] as $day) {
        focusedOn($this->user, $day);
    }
    focusedOn($this->user, '2026-10-09');

    expect(streakOf($this->user))->toBe(4);
});

test('a streak ending yesterday is still alive today', function () {
    focusedOn($this->user, '2026-10-07');
    focusedOn($this->user, '2026-10-08');

    expect(streakOf($this->user))->toBe(2);
});

test('a missed day ends the streak', function () {
    focusedOn($this->user, '2026-10-01');
    focusedOn($this->user, '2026-10-02');
    focusedOn($this->user, '2026-10-09');

    expect(streakOf($this->user))->toBe(1);
});

test('a streak that ended before yesterday is zero', function () {
    focusedOn($this->user, '2026-10-06');
    focusedOn($this->user, '2026-10-07');

    expect(streakOf($this->user))->toBe(0);
});

test('a wrong stored streak is corrected', function () {
    focusedOn($this->user, '2026-10-08');
    $this->user->stats()->create(['day_streak' => 13]);

    expect(streakOf($this->user))->toBe(1);
});
