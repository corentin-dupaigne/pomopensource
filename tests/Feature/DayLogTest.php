<?php

use App\Models\FocusedSession;
use App\Models\User;
use Carbon\Carbon;

function sessionAt(User $user, string $start, array $values = []): FocusedSession
{
    $start = Carbon::parse($start);

    return FocusedSession::create($values + [
        'user_id' => $user->id,
        'started_at' => $start,
        'ended_at' => $start->copy()->addMinutes(25),
        'minute_focused' => 25,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = $this->user->projects()->create(['name' => 'Maths']);
});

test('the day log lists the day\'s finished sessions, oldest first', function () {
    sessionAt($this->user, '2026-10-08 14:00', ['note' => 'two-sum']);
    sessionAt($this->user, '2026-10-08 09:10', ['project_id' => $this->project->id, 'note' => 'chapter 3']);
    sessionAt($this->user, '2026-10-07 09:00');
    sessionAt($this->user, '2026-10-08 16:00', ['ended_at' => null, 'minute_focused' => 0]);
    sessionAt(User::factory()->create(), '2026-10-08 10:00');

    $this->actingAs($this->user)
        ->getJson('/user-stats/day/2026-10-08')
        ->assertOk()
        ->assertJsonCount(2, 'sessions')
        ->assertJsonPath('sessions.0.project', ['id' => $this->project->id, 'name' => 'Maths'])
        ->assertJsonPath('sessions.0.minutes_focused', 25)
        ->assertJsonPath('sessions.1.project', null);
});

test('notes can no longer be changed or seen, but stay stored', function () {
    $session = sessionAt($this->user, '2026-10-08 09:00', ['note' => 'mine']);

    $this->actingAs($this->user)
        ->patchJson('/focused-sessions/notes', ['ids' => [$session->id], 'note' => 'changed'])
        ->assertNotFound();
    $this->actingAs($this->user)
        ->getJson('/user-stats/day/2026-10-08')
        ->assertJsonMissingPath('sessions.0.note');

    expect($session->fresh()->note)->toBe('mine');
});
