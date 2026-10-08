<?php

namespace App\Http\Controllers;

use App\Models\FocusedSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class FocusedSessionController extends Controller
{
    public function index(Request $request)
    {
        $focusedSessions = $request->user()->focusedSessions()
            ->with(['task', 'project'])
            ->latest()
            ->paginate(10);

        return Inertia::render('FocusedSessions/Index', [
            'focusedSessions' => $focusedSessions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'task_id' => 'nullable|exists:tasks,id',
            'project_id' => 'nullable|exists:projects,id',
            'started_at' => 'required|date',
        ]);

        $focusedSession = $request->user()->focusedSessions()->create($validated);

        return response()->json(['message' => 'Correctly add session']);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'ended_at' => 'required|date',
            'time_focused' => 'required|integer|min:0',
        ]);

        $focusedSession = $request->user()->focusedSessions()
            ->whereNull('ended_at')
            ->latest()
            ->firstOrFail();

        // A session can span pauses, so count the time actually focused rather
        // than the wall-clock time between start and end.
        $focusedSession->update([
            'ended_at' => $validated['ended_at'],
            'minute_focused' => intdiv($validated['time_focused'] + 30, 60),
        ]);
    }
}
