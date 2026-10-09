<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = auth()->user()->projects()->with('tasks')->get();

        return Inertia::render('App', ['projects' => $projects]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
        'name' => 'required|string|max:255',
        ]);

        $project = auth()->user()->projects()->create($validated);

        return response()->json($project);
    }


    public function update(Request $request, Project $project)
    {
        $this->authorizeOwner($request, $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $project->update($validated);
    }

    public function destroy(Request $request, Project $project)
    {
        $this->authorizeOwner($request, $project);

        $project->tasks()->delete();

        $project->delete();
    }

    /**
     * Route model binding finds any user's project: answer as if someone
     * else's did not exist.
     */
    private function authorizeOwner(Request $request, Project $project): void
    {
        abort_unless((int) $project->user_id === $request->user()->id, 404);
    }
}
