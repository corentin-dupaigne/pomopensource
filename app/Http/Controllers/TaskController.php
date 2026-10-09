<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function store(Request $request, Project $project)
    {
        abort_unless((int) $project->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $task = $project->tasks()->create($validated);

        return response()->json($task);
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeOwner($request, $task);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $task->update($validated);
    }

    public function destroy(Request $request, Task $task)
    {
        $this->authorizeOwner($request, $task);

        $task->delete();
    }

    /**
     * A task belongs to whoever owns its project.
     */
    private function authorizeOwner(Request $request, Task $task): void
    {
        abort_unless((int) $task->project?->user_id === $request->user()->id, 404);
    }
}
