<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = auth()->user()->suggestedSubjects()->get();

        return Inertia::render('App', ['projects' => $projects]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
        'name' => 'required|string|max:255',
        ]);

        // A subject of the same name is the same one.
        return response()->json(Project::named(auth()->user(), $validated['name']));
    }


    public function update(Request $request, Project $project)
    {
        $this->authorizeOwner($request, $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $project->update($validated);
    }

    /**
     * The notice that projects and notes became one field was read.
     */
    public function dismissNotice(Request $request)
    {
        $request->user()->forceFill(['show_subjects_notice' => false])->save();
    }

    public function destroy(Request $request, Project $project)
    {
        $this->authorizeOwner($request, $project);

        // No longer current: out of the suggestions, still named in the
        // history and stats. Typing it again brings it back.
        $project->forceFill(['removed_at' => now()])->save();
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
