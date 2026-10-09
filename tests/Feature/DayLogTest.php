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
        ->assertJsonPath('sessions.0.note', 'chapter 3')
        ->assertJsonPath('sessions.0.minutes_focused', 25)
        ->assertJsonPath('sessions.1.project', null)
        ->assertJsonPath('sessions.1.note', 'two-sum');
});

test('notes can be set on past sessions', function () {
    $first = sessionAt($this->user, '2026-10-08 09:00');
    $second = sessionAt($this->user, '2026-10-08 09:30');

    $this->actingAs($this->user)
        ->patchJson('/focused-sessions/notes', ['ids' => [$first->id, $second->id], 'note' => 'integrals'])
        ->assertOk();

    expect($first->fresh()->note)->toBe('integrals')
        ->and($second->fresh()->note)->toBe('integrals');

    $this->actingAs($this->user)
        ->patchJson('/focused-sessions/notes', ['ids' => [$first->id], 'note' => null])
        ->assertOk();
    expect($first->fresh()->note)->toBeNull();
});

test('someone else\'s notes cannot be changed', function () {
    $session = sessionAt($this->user, '2026-10-08 09:00', ['note' => 'mine']);

    $this->actingAs(User::factory()->create())
        ->patchJson('/focused-sessions/notes', ['ids' => [$session->id], 'note' => 'yours'])
        ->assertOk();

    expect($session->fresh()->note)->toBe('mine');
});
