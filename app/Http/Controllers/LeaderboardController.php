<?php

namespace App\Http\Controllers;

use App\Models\ActivityLaunch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Who focused the most in a Discord server's calls: a reason for a group
 * to open the Activity again in the same voice channel.
 */
class LeaderboardController extends Controller
{
    // Rolling windows, in days: no "today" to cut in one time zone for
    // members around the world. Null is all time.
    public const PERIODS = ['day' => 1, 'week' => 7, 'all' => null];

    public const SHOWN = 10;

    // Minutes come from the client, and a timer can run with nobody there:
    // an account counts at most this much a day. Some cheating is fine
    // among friends.
    public const DAILY_CAP = 12 * 60;

    public function show(Request $request, string $instance)
    {
        $viewer = $request->user();
        abort_unless($viewer->discord_id, 403);
        $period = $request->validate(['period' => 'required|in:'.implode(',', array_keys(self::PERIODS))])['period'];

        // Only a server's calls have a leaderboard, not private calls.
        $guild = ActivityLaunch::placeOf($instance)['guild_id'];
        abort_unless($guild, 404);

        $ranked = $this->ranking($guild, self::PERIODS[$period]);
        $users = User::whereIn('id', $ranked->pluck('user_id'))->get()->keyBy('id');
        $rows = $ranked->map(fn ($row) => [
            'rank' => $row->rank,
            'minutes' => (int) $row->minutes,
            'me' => $row->user_id === $viewer->id,
            'user' => $this->discordUser($users[$row->user_id]),
        ]);

        // The top, and the viewer's own row when they are further down.
        $me = $rows->firstWhere('me', true);

        return response()->json([
            'period' => $period,
            'entries' => $rows->take(self::SHOWN)->values(),
            'me' => $me && $me['rank'] > self::SHOWN ? $me : null,
            // What the server did together: everyone listed, not just the top.
            'together' => ['members' => $rows->count(), 'minutes' => $rows->sum('minutes')],
            'hidden' => $this->hiddenUserIds()->contains($viewer->id),
        ]);
    }

    /**
     * Accounts with focus time in the server's calls over the period, most
     * first, each day capped. Nobody is listed with nothing, nor those who
     * chose not to appear.
     */
    private function ranking(string $guild, ?int $days)
    {
        $daily = DB::table('focused_sessions')
            ->join('users', 'users.id', '=', 'focused_sessions.user_id')
            ->where('focused_sessions.guild_id', $guild)
            ->where('focused_sessions.minute_focused', '>', 0)
            ->whereNotNull('users.discord_id')
            ->whereNotIn('focused_sessions.user_id', $this->hiddenUserIds())
            ->when($days, fn ($query) => $query->where('focused_sessions.started_at', '>=', now()->subDays($days)))
            ->groupBy('focused_sessions.user_id', DB::raw('DATE(focused_sessions.started_at)'))
            ->selectRaw('focused_sessions.user_id, SUM(focused_sessions.minute_focused) as minutes');

        $totals = DB::query()->fromSub($daily, 'days')
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(CASE WHEN minutes > ? THEN ? ELSE minutes END) as minutes', [self::DAILY_CAP, self::DAILY_CAP])
            ->orderByDesc('minutes')
            ->orderBy('user_id')
            ->get();

        // Equal time, equal rank: 1, 2, 2, 4.
        $previous = null;
        foreach ($totals as $index => $row) {
            $row->user_id = (int) $row->user_id;
            $row->rank = $previous && (int) $previous->minutes === (int) $row->minutes ? $previous->rank : $index + 1;
            $previous = $row;
        }

        return $totals;
    }

    private function hiddenUserIds()
    {
        $setting = Setting::where('key', 'show_in_leaderboards')->value('id');

        return DB::table('user_settings')
            ->where('setting_id', $setting)
            ->whereIn('value', ['0', 'false'])
            ->pluck('user_id');
    }

    // Shaped like the Discord users the Activity already shows.
    private function discordUser(User $user): array
    {
        return [
            'id' => $user->discord_id,
            'username' => $user->name,
            'global_name' => $user->name,
            'avatar' => $user->discord_avatar,
        ];
    }
}
