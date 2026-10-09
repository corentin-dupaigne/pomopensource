<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Models\Stats;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserStatsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $stats = $this->updateStats($user);

        return response()->json([
            'stats' => [
                'hours_focused' => round($stats->minute_focused / 60, 1),
                'days_accessed' => $stats->days_accessed,
                'day_streak' => $stats->day_streak,
            ],
        ]);
    }

    public function updateStats(User $user)
    {
        $stats = $user->stats ?? new Stats(['user_id' => $user->id]);

        // Update total focused time
        $this->updateTimeFocused($user, $stats);

        // Update days accessed
        $daysAccessed = $user->focusedSessions()
            ->select(DB::raw('DATE(started_at) as date'))
            ->distinct()
            ->get()
            ->count();
        $stats->days_accessed = $daysAccessed;

        // Update day streak
        $this->updateDayStreak($user, $stats);

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
    private function updateDayStreak(User $user, Stats $stats): void
    {
        $days = $user->focusedSessions()
            ->selectRaw('DATE(started_at) as day')
            ->distinct()
            ->pluck('day')
            ->flip();

        $day = Carbon::today();
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
        $stats = $this->updateStats($user);
        $view = $request->query('view', 'month');

        switch ($view) {
            case 'week':
                return $this->getWeekCalendarData($user, $year, $month, $day, $stats);
            case 'month':
                return $this->getMonthCalendarData($user, $year, $month, $stats);
            case 'year':
                return $this->getYearCalendarData($user, $year, $stats);
            default:
                return response()->json(['error' => 'Invalid view'], 400);
        }
    }

    private function getWeekCalendarData($user, $year, $month, $day, $stats)
    {
        $startDate = Carbon::create($year, $month, $day)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();

        return $this->getCalendarDataForDateRange($user, $startDate, $endDate, $stats);
    }

    private function getMonthCalendarData($user, $year, $month, $stats)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return $this->getCalendarDataForDateRange($user, $startDate, $endDate, $stats);
    }

    private function getYearCalendarData($user, $year, $stats)
    {
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = $startDate->copy()->endOfYear();

        $focusedSessions = $user->focusedSessions()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($session) {
                return $session->started_at->format('Y-m');
            });

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
        $focusedSessions = $user->focusedSessions()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($session) {
                return $session->started_at->format('Y-m-d');
            });

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
