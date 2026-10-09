<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->other = User::factory()->create();
    $this->project = $this->owner->projects()->create(['name' => 'Maths']);
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

test('a session cannot be filed under someone else\'s project', function () {
    $this->actingAs($this->other)
        ->postJson('/focused-sessions', ['project_id' => $this->project->id, 'started_at' => now()])
        ->assertUnprocessable();

    $this->actingAs($this->owner)
        ->postJson('/focused-sessions', ['project_id' => $this->project->id, 'started_at' => now()])
        ->assertOk();
});
