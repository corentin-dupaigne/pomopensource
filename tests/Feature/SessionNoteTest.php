<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => now()])
        ->assertOk();
});

test('ending a session saves its note', function () {
    $this->actingAs($this->user)
        ->patchJson('/focused-sessions/current', [
            'ended_at' => now(),
            'time_focused' => 25 * 60,
            'note' => 'chapter 3 integrals',
        ])
        ->assertOk();

    expect($this->user->focusedSessions()->first()->note)->toBe('chapter 3 integrals');
});

test('the note is optional', function () {
    $this->actingAs($this->user)
        ->patchJson('/focused-sessions/current', ['ended_at' => now(), 'time_focused' => 60])
        ->assertOk();

    expect($this->user->focusedSessions()->first()->note)->toBeNull();
});

test('a note is at most 255 characters', function () {
    $this->actingAs($this->user)
        ->patchJson('/focused-sessions/current', [
            'ended_at' => now(),
            'time_focused' => 60,
            'note' => str_repeat('a', 256),
        ])
        ->assertUnprocessable();
});
