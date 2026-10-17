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

    /**
     * End a focus session. The client names the session by its start time:
     * the latest open session may belong to another tab or device, or be
     * one left open long ago. When the start never reached the server, the
     * session is created here, so the time is still logged.
     */
    public function update(Request $request)
    {
        $userId = $request->user()->id;
        $validated = $request->validate([
            'started_at' => 'nullable|date',
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'ended_at' => 'required|date',
            'time_focused' => 'required|integer|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        // A session can span pauses, so count the time actually focused rather
        // than the wall-clock time between start and end.
        $end = [
            'ended_at' => $validated['ended_at'],
            'minute_focused' => intdiv($validated['time_focused'] + 30, 60),
            'note' => $validated['note'] ?? null,
        ];

        $sessions = $request->user()->focusedSessions();

        // Pages loaded before sessions were named by their start.
        if (empty($validated['started_at'])) {
            $sessions->whereNull('ended_at')->latest()->firstOrFail()->update($end);

            return;
        }

        // Stored to the second, like the start request saved it.
        $startedAt = (new FocusedSession)->fromDateTime($validated['started_at']);
        $focusedSession = $sessions->where('started_at', $startedAt)->first();

        if (!$focusedSession) {
            $request->user()->focusedSessions()->create($end + [
                'started_at' => $startedAt,
                'project_id' => $validated['project_id'] ?? null,
            ]);
        } elseif ($focusedSession->ended_at === null) {
            $focusedSession->update($end);
        }
        // Already ended: a retry of a request that went through.
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
