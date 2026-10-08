<?php

namespace App\Http\Controllers;

use App\Models\ActivityRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The timer shared by everyone in a Discord Activity instance.
 */
class ActivityRoomController extends Controller
{
    public function show(Request $request, string $instance)
    {
        $this->authorizeDiscordUser($request);

        // Clients poll this often: lock only when a timer ran out and must end.
        $room = ActivityRoom::firstOrCreate(['instance_id' => $instance]);
        if ($room->isDue()) {
            $room = DB::transaction(function () use ($instance) {
                $room = $this->lockedRoom($instance);
                if ($room->completeIfDue()) {
                    $room->save();
                }

                return $room;
            });
        }

        return response()->json($room->state());
    }

    public function update(Request $request, string $instance)
    {
        $this->authorizeDiscordUser($request);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['start', 'pause', 'reset', 'switch', 'complete'])],
            'timer_type' => ['required_if:action,switch', Rule::in(ActivityRoom::TIMER_TYPES)],
            'durations' => ['sometimes', 'array:'.implode(',', ActivityRoom::TIMER_TYPES)],
            'durations.*' => ['integer', 'min:1', 'max:600'],
        ]);

        $room = DB::transaction(function () use ($instance, $validated) {
            $room = $this->lockedRoom($instance);
            // A timer that ran out since the last request ends first.
            $changed = $room->completeIfDue();
            $changed = $room->act($validated['action'], $validated['timer_type'] ?? null, $validated['durations'] ?? null) || $changed;
            if ($changed || $room->isDirty()) {
                $room->save();
            }

            return $room;
        });

        return response()->json($room->state());
    }

    /**
     * Rooms are for accounts signed in through the Activity.
     */
    private function authorizeDiscordUser(Request $request): void
    {
        abort_unless($request->user()->discord_id, 403);
    }

    private function lockedRoom(string $instance): ActivityRoom
    {
        ActivityRoom::firstOrCreate(['instance_id' => $instance]);

        return ActivityRoom::where('instance_id', $instance)->lockForUpdate()->firstOrFail();
    }
}
