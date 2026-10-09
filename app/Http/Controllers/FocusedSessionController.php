<?php

namespace App\Http\Controllers;

use App\Models\FocusedSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FocusedSessionController extends Controller
{
    public function index(Request $request)
    {
        $focusedSessions = $request->user()->focusedSessions()
            ->with('project')
            ->latest()
            ->paginate(10);

        return Inertia::render('FocusedSessions/Index', [
            'focusedSessions' => $focusedSessions,
        ]);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;
        $validated = $request->validate([
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('user_id', $userId)],
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
            'note' => 'nullable|string|max:255',
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
            'note' => $validated['note'] ?? null,
        ]);
    }

    /**
     * Set the note of past sessions, from the day log. The log shows
     * back-to-back sessions on the same thing as one line, so this takes
     * every session of the line.
     */
    public function updateNotes(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|max:100',
            'ids.*' => 'integer',
            'note' => 'nullable|string|max:255',
        ]);

        $request->user()->focusedSessions()
            ->whereIn('id', $validated['ids'])
            ->update(['note' => $validated['note'] ?? null]);
    }
}
