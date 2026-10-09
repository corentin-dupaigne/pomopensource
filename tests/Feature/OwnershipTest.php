<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->other = User::factory()->create();
    $this->project = $this->owner->projects()->create(['name' => 'Maths']);
    $this->task = $this->project->tasks()->create(['name' => 'Integrals']);
});

test('someone else cannot rename or delete a project', function () {
    $this->actingAs($this->other)
        ->patchJson("/projects/{$this->project->id}", ['name' => 'Mine now'])
        ->assertNotFound();
    $this->actingAs($this->other)
        ->deleteJson("/projects/{$this->project->id}")
        ->assertNotFound();

    expect(Project::find($this->project->id)->name)->toBe('Maths');
});

test('the owner can rename and delete their project', function () {
    $this->actingAs($this->owner)
        ->patchJson("/projects/{$this->project->id}", ['name' => 'Algebra'])
        ->assertOk();
    expect($this->project->fresh()->name)->toBe('Algebra');

    $this->actingAs($this->owner)
        ->deleteJson("/projects/{$this->project->id}")
        ->assertOk();
    expect(Project::find($this->project->id))->toBeNull();
});

test('someone else cannot add, rename or delete tasks', function () {
    $this->actingAs($this->other)
        ->postJson("/projects/{$this->project->id}/tasks", ['name' => 'Sneaky'])
        ->assertNotFound();
    $this->actingAs($this->other)
        ->patchJson("/tasks/{$this->task->id}", ['name' => 'Sneaky'])
        ->assertNotFound();
    $this->actingAs($this->other)
        ->deleteJson("/tasks/{$this->task->id}")
        ->assertNotFound();

    expect($this->project->tasks()->pluck('name')->all())->toBe(['Integrals']);
});

test('a session cannot be filed under someone else\'s project or task', function () {
    $this->actingAs($this->other)
        ->postJson('/focused-sessions', ['project_id' => $this->project->id, 'started_at' => now()])
        ->assertUnprocessable();
    $this->actingAs($this->other)
        ->postJson('/focused-sessions', ['task_id' => $this->task->id, 'started_at' => now()])
        ->assertUnprocessable();

    $this->actingAs($this->owner)
        ->postJson('/focused-sessions', ['task_id' => $this->task->id, 'started_at' => now()])
        ->assertOk();
});
