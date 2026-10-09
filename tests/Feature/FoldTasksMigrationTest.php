<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('sessions on a task move to its project, with the task name as note', function () {
    $migration = require database_path('migrations/2026_10_09_110000_fold_tasks_into_session_notes.php');
    $user = User::factory()->create();
    $project = $user->projects()->create(['name' => 'LeetCode']);
    $taskId = DB::table('tasks')->insertGetId(['project_id' => $project->id, 'name' => 'Two-sum']);

    $session = fn (array $values) => DB::table('focused_sessions')->insertGetId(
        $values + ['user_id' => $user->id, 'started_at' => now(), 'minute_focused' => 25]
    );
    $onTask = $session(['task_id' => $taskId]);
    $onTaskWithNote = $session(['task_id' => $taskId, 'note' => 'kept']);
    $onProject = $session(['project_id' => $project->id]);
    $onNothing = $session([]);

    $migration->up();

    $row = fn ($id) => DB::table('focused_sessions')->find($id);
    expect($row($onTask))->project_id->toEqual($project->id)->note->toBe('Two-sum')
        ->and($row($onTaskWithNote))->project_id->toEqual($project->id)->note->toBe('kept')
        ->and($row($onProject))->project_id->toEqual($project->id)->note->toBeNull()
        ->and($row($onNothing))->project_id->toBeNull()->note->toBeNull();
});
