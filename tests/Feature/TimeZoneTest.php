<?php

use App\Models\FocusedSession;
use App\Models\User;
use Carbon\Carbon;

// Auckland is UTC+13 in October: a morning there is the evening before in UTC.
const AUCKLAND = ['X-Timezone' => 'Pacific/Auckland'];

function sessionAtUtc(User $user, string $start): FocusedSession
{
    $start = Carbon::parse($start, 'UTC');

    return FocusedSession::create([
        'user_id' => $user->id,
        'started_at' => $start,
        'ended_at' => $start->copy()->addMinutes(25),
        'minute_focused' => 25,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    sessionAtUtc($this->user, '2026-10-15 20:00'); // the 16th, 09:00 in Auckland
    sessionAtUtc($this->user, '2026-10-16 02:00'); // the 16th, 15:00 in Auckland
});

test('the day log holds the sessions of the user\'s own day', function () {
    $this->actingAs($this->user)
        ->getJson('/user-stats/day/2026-10-16', AUCKLAND)
        ->assertJsonCount(2, 'sessions');

    $this->actingAs($this->user)
        ->getJson('/user-stats/day/2026-10-15', AUCKLAND)
        ->assertJsonCount(0, 'sessions');
});

test('the calendar counts the time on the user\'s own day', function () {
    $days = collect($this->actingAs($this->user)
        ->getJson('/user-stats/calendar/2026/10?view=month', AUCKLAND)
        ->json('calendar'))->keyBy('date');

    expect($days['2026-10-16']['minutes_focused'])->toBe(50)
        ->and($days['2026-10-15']['minutes_focused'])->toBe(0);
});

test('the week starts on the user\'s own Monday', function () {
    // Monday the 12th, 08:00 in Auckland: still Sunday in UTC.
    sessionAtUtc($this->user, '2026-10-11 19:00');

    $days = collect($this->actingAs($this->user)
        ->getJson('/user-stats/calendar/2026/10/12?view=week', AUCKLAND)
        ->json('calendar'))->keyBy('date');

    expect($days['2026-10-12']['minutes_focused'])->toBe(25);
});

test('the year counts the time in the user\'s own month', function () {
    // November 1st, 10:00 in Auckland: still October in UTC.
    sessionAtUtc($this->user, '2026-10-31 21:00');

    $months = collect($this->actingAs($this->user)
        ->getJson('/user-stats/calendar/2026?view=year', AUCKLAND)
        ->json('calendar'))->keyBy('date');

    expect($months['2026-11']['minutes_focused'])->toBe(25)
        ->and($months['2026-10']['minutes_focused'])->toBe(50);
});

test('days and streak follow the user\'s own days', function () {
    // The 15th, 23:00 and the 16th, 01:00 in Auckland: one day in UTC.
    $user = User::factory()->create();
    sessionAtUtc($user, '2026-10-15 10:00');
    sessionAtUtc($user, '2026-10-15 12:00');
    $this->travelTo(Carbon::parse('2026-10-15 13:00', 'UTC'));

    $this->actingAs($user)
        ->getJson('/user-stats', AUCKLAND)
        ->assertJsonPath('stats.days_accessed', 2)
        ->assertJsonPath('stats.day_streak', 2);
});

test('without a valid time zone, days are cut in the app\'s', function () {
    $this->actingAs($this->user)
        ->getJson('/user-stats/day/2026-10-16', ['X-Timezone' => 'Not/AZone'])
        ->assertJsonCount(1, 'sessions');
});
