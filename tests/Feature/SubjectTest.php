<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->startedAt = now()->subMinutes(25)->startOfSecond();
});

function endWith(array $values): array
{
    return $values + ['ended_at' => now(), 'time_focused' => 25 * 60];
}

test('a subject typed the first time is made, and the session is on it', function () {
    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => $this->startedAt, 'subject' => '  Biochem '])
        ->assertOk()
        ->assertJsonPath('project.name', 'Biochem');

    $session = $this->user->focusedSessions()->first();
    expect($session->project->name)->toBe('Biochem')
        ->and($this->user->projects()->count())->toBe(1);
});

test('the same text, whatever its case or spacing, is the same subject', function () {
    $project = $this->user->projects()->create(['name' => 'Bio chem']);

    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => $this->startedAt, 'subject' => 'bio   CHEM'])
        ->assertJsonPath('project.id', $project->id);

    expect($this->user->projects()->count())->toBe(1);
});

test('a blank subject is general focus', function () {
    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => $this->startedAt, 'subject' => '  '])
        ->assertOk();

    expect($this->user->focusedSessions()->first()->project_id)->toBeNull()
        ->and($this->user->projects()->count())->toBe(0);
});

test('the subject can change before the session ends', function () {
    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => $this->startedAt, 'subject' => 'Maths']);

    $this->actingAs($this->user)
        ->patchJson('/focused-sessions/current', endWith(['started_at' => $this->startedAt, 'subject' => 'Physics']))
        ->assertOk();

    expect($this->user->focusedSessions()->first()->project->name)->toBe('Physics');
});

test('a note written in the day log stays when the session ends', function () {
    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => $this->startedAt, 'subject' => 'Maths']);
    $this->user->focusedSessions()->update(['note' => 'chapter 3']);

    $this->actingAs($this->user)
        ->patchJson('/focused-sessions/current', endWith(['started_at' => $this->startedAt, 'subject' => 'Maths']));

    expect($this->user->focusedSessions()->first()->note)->toBe('chapter 3');
});

test('sessions kept by older pages still name their project by ID', function () {
    $project = $this->user->projects()->create(['name' => 'Thesis']);

    $this->actingAs($this->user)
        ->patchJson('/focused-sessions/current', endWith(['started_at' => $this->startedAt, 'project_id' => $project->id]))
        ->assertOk();

    expect($this->user->focusedSessions()->first()->project_id)->toEqual($project->id);
});

test('the most recently used subjects come first', function () {
    $old = $this->user->projects()->create(['name' => 'Old']);
    $this->user->projects()->create(['name' => 'Newer']);
    $this->travel(1)->minutes();

    $this->actingAs($this->user)
        ->postJson('/focused-sessions', ['started_at' => now(), 'subject' => 'old']);

    expect($this->user->projects()->pluck('name')->all())->toBe(['Old', 'Newer']);
});

test('adding a project of a name that exists gives that one', function () {
    $project = $this->user->projects()->create(['name' => 'Thesis']);

    $this->actingAs($this->user)
        ->postJson('/projects', ['name' => 'thesis'])
        ->assertJsonPath('id', $project->id);
});

test('the notice is dismissed for good', function () {
    $this->user->forceFill(['show_subjects_notice' => true])->save();

    $this->actingAs($this->user)->postJson('/subjects-notice/dismiss')->assertOk();

    expect($this->user->fresh()->show_subjects_notice)->toBeFalse();
});
