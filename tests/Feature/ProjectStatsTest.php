<?php

use App\Models\FocusedSession;
use App\Models\User;

function focusedFor(User $user, int $minutes, ?int $projectId = null): void
{
    FocusedSession::create([
        'user_id' => $user->id,
        'project_id' => $projectId,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'minute_focused' => $minutes,
    ]);
}

test('time spent on no project shows as general focus', function () {
    $user = User::factory()->create();
    $project = $user->projects()->create(['name' => 'Thesis']);
    focusedFor($user, 25, $project->id);
    focusedFor($user, 50);
    focusedFor($user, 25);
    focusedFor(User::factory()->create(), 25);

    $this->actingAs($user)
        ->getJson('/projects-stats')
        ->assertOk()
        ->assertJsonCount(2, 'projects')
        ->assertJsonPath('projects.0', ['id' => null, 'name' => null, 'total_time_focused' => 75 * 60])
        ->assertJsonPath('projects.1', ['id' => $project->id, 'name' => 'Thesis', 'total_time_focused' => 25 * 60]);
});

test('general focus is left out until there is some', function () {
    $user = User::factory()->create();
    $user->projects()->create(['name' => 'Thesis']);

    $this->actingAs($user)
        ->getJson('/projects-stats')
        ->assertJsonCount(1, 'projects')
        ->assertJsonPath('projects.0.name', 'Thesis');
});
