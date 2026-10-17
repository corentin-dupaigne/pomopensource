<?php

namespace App\Http\Controllers;

use App\Models\ActivityLaunch;
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
        $this->countParticipant($room, $request->user()->id);
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
            'action' => ['required', Rule::in(['start', 'pause', 'reset', 'switch', 'complete', 'durations'])],
            'timer_type' => ['required_if:action,switch', Rule::in(ActivityRoom::TIMER_TYPES)],
            'durations' => ['required_if:action,durations', 'array:'.implode(',', ActivityRoom::TIMER_TYPES)],
            'durations.*' => ['integer', 'min:1', 'max:600'],
        ]);

        $actor = $request->user()->name;
        $room = DB::transaction(function () use ($instance, $validated, $actor) {
            $room = $this->lockedRoom($instance);
            // A timer that ran out since the last request ends first.
            $changed = $room->completeIfDue();
            $changed = $validated['action'] === 'durations'
                ? $room->setDurations($validated['durations'], $actor) || $changed
                : $room->act($validated['action'], $validated['timer_type'] ?? null) || $changed;
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

    /**
     * Count each account that uses the call's timer once, for the usage
     * statistics. Only a newcomer's first poll writes anything.
     */
    private function countParticipant(ActivityRoom $room, int $userId): void
    {
        if (in_array($userId, $room->participant_ids ?? [], true)) {
            return;
        }

        DB::transaction(function () use ($room, $userId) {
            $locked = $this->lockedRoom($room->instance_id);
            $ids = $locked->participant_ids ?? [];
            if (in_array($userId, $ids, true)) {
                return;
            }
            $ids[] = $userId;
            $locked->participant_ids = $ids;
            $locked->save();
            ActivityLaunch::where('instance_id', $room->instance_id)->update(['participants' => count($ids)]);
        });
    }

    private function lockedRoom(string $instance): ActivityRoom
    {
        ActivityRoom::firstOrCreate(['instance_id' => $instance]);

        return ActivityRoom::where('instance_id', $instance)->lockForUpdate()->firstOrFail();
    }
}
