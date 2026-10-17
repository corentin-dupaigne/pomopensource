<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Models\Stats;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserStatsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $stats = $this->updateStats($user, $this->timezone($request));

        return response()->json([
            'stats' => [
                'hours_focused' => round($stats->minute_focused / 60, 1),
                'days_accessed' => $stats->days_accessed,
                'day_streak' => $stats->day_streak,
            ],
        ]);
    }

    /**
     * The user's time zone, which their browser sends: days are the user's
     * own, not the server's. Falls back to the app's.
     */
    private function timezone(Request $request): string
    {
        $timezone = $request->header('X-Timezone');
        try {
            return $timezone ? (new \DateTimeZone($timezone))->getName() : config('app.timezone');
        } catch (\Exception) {
            return config('app.timezone');
        }
    }

    /**
     * The dates of the user's days with a focus session, in their time zone.
     */
    private function sessionDays(User $user, string $timezone)
    {
        return $user->focusedSessions()
            ->pluck('started_at')
            ->map(fn ($startedAt) => $startedAt->setTimezone($timezone)->toDateString())
            ->unique();
    }

    public function updateStats(User $user, ?string $timezone = null)
    {
        $timezone ??= config('app.timezone');
        $stats = $user->stats ?? new Stats(['user_id' => $user->id]);

        // Update total focused time
        $this->updateTimeFocused($user, $stats);

        $days = $this->sessionDays($user, $timezone);
        $stats->days_accessed = $days->count();
        $this->updateDayStreak($days, $timezone, $stats);

        $stats->save();

        return $stats;
    }

    public function updateTimeFocused(User $user, Stats $stats): void
    {
        $totalMinutesFocused = $user->focusedSessions()->sum('minute_focused');
        $stats->minute_focused = $totalMinutesFocused;
    }


    /**
     * Consecutive days with a focus session, counted back from today. A day
     * with no session yet today keeps yesterday's streak alive.
     *
     * Computed from the sessions on every call: the stored value used to be
     * incremented on each request, so it grew every time stats were read.
     */
    private function updateDayStreak($days, string $timezone, Stats $stats): void
    {
        $days = $days->flip();

        $day = Carbon::today($timezone);
        if (!$days->has($day->toDateString())) {
            $day->subDay();
        }

        $streak = 0;
        while ($days->has($day->toDateString())) {
            $streak++;
            $day->subDay();
        }

        $stats->day_streak = $streak;
    }


    public function getCalendarData(Request $request, $year, $month = null, $day = null)
    {
        $user = $request->user();
        $timezone = $this->timezone($request);
        $stats = $this->updateStats($user, $timezone);
        $view = $request->query('view', 'month');

        switch ($view) {
            case 'week':
                return $this->getWeekCalendarData($user, $year, $month, $day, $stats, $timezone);
            case 'month':
                return $this->getMonthCalendarData($user, $year, $month, $stats, $timezone);
            case 'year':
                return $this->getYearCalendarData($user, $year, $stats, $timezone);
            default:
                return response()->json(['error' => 'Invalid view'], 400);
        }
    }

    private function getWeekCalendarData($user, $year, $month, $day, $stats, $timezone)
    {
        $startDate = Carbon::create($year, $month, $day, 0, 0, 0, $timezone)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();

        return $this->getCalendarDataForDateRange($user, $startDate, $endDate, $stats);
    }

    private function getMonthCalendarData($user, $year, $month, $stats, $timezone)
    {
        $startDate = Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return $this->getCalendarDataForDateRange($user, $startDate, $endDate, $stats);
    }

    /**
     * The sessions started between two moments of the user's time zone,
     * grouped by the user's date in the given format. Sessions are stored
     * in the app's time zone.
     */
    private function sessionsBetween($user, Carbon $startDate, Carbon $endDate, string $format)
    {
        $timezone = $startDate->getTimezone();

        return $user->focusedSessions()
            ->whereBetween('started_at', [
                $startDate->copy()->setTimezone(config('app.timezone')),
                $endDate->copy()->setTimezone(config('app.timezone')),
            ])
            ->get()
            ->groupBy(fn ($session) => $session->started_at->setTimezone($timezone)->format($format));
    }

    private function getYearCalendarData($user, $year, $stats, $timezone)
    {
        $startDate = Carbon::create($year, 1, 1, 0, 0, 0, $timezone)->startOfYear();
        $endDate = $startDate->copy()->endOfYear();

        $focusedSessions = $this->sessionsBetween($user, $startDate, $endDate, 'Y-m');

        $calendar = [];

        for ($month = 1; $month <= 12; $month++) {
            $date = Carbon::create($year, $month, 1);
            $monthKey = $date->format('Y-m');
            $sessions = $focusedSessions->get($monthKey, collect());

            $calendar[] = [
                'date' => $monthKey,
                'has_session' => $sessions->isNotEmpty(),
                'minutes_focused' => $sessions->sum('minute_focused'),
            ];
        }

        return response()->json([
            'calendar' => $calendar,
            'currentStreak' => $stats->day_streak,
            'initialYear' => (int)$year,
        ]);
    }

    private function getCalendarDataForDateRange($user, $startDate, $endDate, $stats)
    {
        $focusedSessions = $this->sessionsBetween($user, $startDate, $endDate, 'Y-m-d');

        $calendar = [];

        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $sessions = $focusedSessions->get($dateString, collect());

            $calendar[] = [
                'date' => $dateString,
                'has_session' => $sessions->isNotEmpty(),
                'minutes_focused' => $sessions->sum('minute_focused'),
            ];
        }

        return response()->json([
            'calendar' => $calendar,
            'currentStreak' => $stats->day_streak,
            'initialYear' => (int)$startDate->year,
            'initialMonth' => (int)$startDate->month,
            'initialDay' => (int)$startDate->day,
        ]);
    }

    /**
     * The sessions of one day, oldest first, for the day log. Days are
     * grouped as in the calendar, in the user's time zone.
     */
    public function getDayLog(Request $request, string $date)
    {
        $start = Carbon::parse($date, $this->timezone($request))->startOfDay();
        $sessions = $request->user()->focusedSessions()
            ->with('project:id,name')
            ->whereBetween('started_at', [
                $start->copy()->setTimezone(config('app.timezone')),
                $start->copy()->endOfDay()->setTimezone(config('app.timezone')),
            ])
            ->whereNotNull('ended_at')
            ->orderBy('started_at')
            ->get();

        return response()->json([
            'sessions' => $sessions->map(fn ($session) => [
                'id' => $session->id,
                'started_at' => $session->started_at,
                'minutes_focused' => $session->minute_focused,
                'project' => $session->project?->only(['id', 'name']),
                'note' => $session->note,
            ]),
        ]);
    }

    public function getProjectStats(Request $request)
    {
        $projects = $request->user()->projects()
            ->withSum('focusedSessions', 'minute_focused')
            ->get();

        return response()->json([
            'projects' => $projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                // In seconds, like the guest stats computed on the device.
                'total_time_focused' => (int) $project->focused_sessions_sum_minute_focused * 60,
            ]),
        ]);
    }






}
