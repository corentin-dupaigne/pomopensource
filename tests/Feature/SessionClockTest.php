<?php

use App\Models\User;
use Carbon\Carbon;

// A device whose clock is a year ahead of the server's.
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->now = Carbon::parse('2026-10-18 12:00:00');
    $this->travelTo($this->now);
    $this->deviceStart = $this->now->copy()->addYear();
});

test('a session from a clock set in the future starts now', function () {
    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => $this->deviceStart->toISOString()])
        ->assertOk();

    expect($this->user->focusedSessions()->sole()->started_at->equalTo($this->now))->toBeTrue();
});

test('its progress and end still reach it, and it ends now', function () {
    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => $this->deviceStart->toISOString()])
        ->assertOk();
    $this->travel(25)->minutes();

    $this->actingAs($this->user)->patchJson('/focused-sessions/current', [
        'started_at' => $this->deviceStart->toISOString(),
        'time_focused' => 10 * 60,
        'in_progress' => true,
    ])->assertOk();
    expect($this->user->focusedSessions()->sole()->minute_focused)->toBe(10);

    $this->actingAs($this->user)->patchJson('/focused-sessions/current', [
        'started_at' => $this->deviceStart->toISOString(),
        'ended_at' => $this->deviceStart->copy()->addMinutes(25)->toISOString(),
        'time_focused' => 25 * 60,
    ])->assertOk();

    $session = $this->user->focusedSessions()->sole();
    expect($session->minute_focused)->toBe(25)
        ->and($session->started_at->equalTo($this->now))->toBeTrue()
        ->and($session->ended_at->equalTo($this->now->copy()->addMinutes(25)))->toBeTrue();
});

test('a clock a little ahead keeps its own times', function () {
    $start = $this->now->copy()->addMinutes(2);

    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => $start->toISOString()])
        ->assertOk();

    expect($this->user->focusedSessions()->sole()->started_at->equalTo($start))->toBeTrue();
});
