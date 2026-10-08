<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * The timer everyone in a Discord Activity instance shares.
 *
 * Clients send actions and poll the state. A running timer is stored as its
 * end time, so clients count down on their own between polls. Each change
 * bumps the version, which clients use to apply every change once.
 */
class ActivityRoom extends Model
{
    use MassPrunable;

    public const TIMER_TYPES = ['pomodoro', 'short_break', 'long_break'];

    public const DEFAULT_DURATIONS = ['pomodoro' => 25, 'short_break' => 5, 'long_break' => 15];

    public const LONG_BREAK_INTERVAL = 4;

    // A client reports completion from its own clock: accept it this early.
    private const COMPLETION_TOLERANCE_MS = 2000;

    protected $fillable = ['instance_id'];

    protected $attributes = [
        'timer_type' => 'pomodoro',
        'status' => 'idle',
        'duration' => 25 * 60,
        'remaining' => 25 * 60,
        'durations' => '{"pomodoro":25,"short_break":5,"long_break":15}',
    ];

    protected $casts = [
        'durations' => 'array',
        'duration' => 'integer',
        'remaining' => 'integer',
        'ends_at_ms' => 'integer',
        'completed_pomodoros' => 'integer',
        'version' => 'integer',
        'completed_at_ms' => 'integer',
    ];

    /**
     * Rooms of calls that ended a day ago.
     */
    public function prunable(): Builder
    {
        return static::where('updated_at', '<', now()->subDay());
    }

    /**
     * Apply an action from a participant. Returns whether anything changed.
     *
     * @param  array<string, int>|null  $durations  minutes per timer type, from the actor's settings
     */
    public function act(string $action, ?string $timerType = null, ?array $durations = null): bool
    {
        if ($durations) {
            $this->durations = array_merge($this->durations, $durations);
        }

        $now = now()->getTimestampMs();

        switch ($action) {
            case 'start':
                if ($this->status === 'running' || $this->remaining <= 0) {
                    return false;
                }
                $this->status = 'running';
                $this->ends_at_ms = $now + $this->remaining * 1000;
                break;

            case 'pause':
                if ($this->status !== 'running') {
                    return false;
                }
                $this->remaining = $this->secondsLeft($now);
                $this->status = 'paused';
                $this->ends_at_ms = null;
                break;

            case 'reset':
                $this->lineUp($this->timer_type);
                break;

            case 'switch':
                $this->lineUp($timerType);
                break;

            case 'complete':
                return $this->completeIfDue(self::COMPLETION_TOLERANCE_MS);
        }

        $this->recordEvent($action);

        return true;
    }

    /**
     * Finish a running timer whose end time has passed, and line up the next
     * one: a break after a pomodoro (a long one every few), else a pomodoro.
     */
    public function completeIfDue(int $toleranceMs = 0): bool
    {
        if (!$this->isDue($toleranceMs)) {
            return false;
        }

        $finished = $this->timer_type;
        $next = 'pomodoro';
        if ($finished === 'pomodoro') {
            $this->completed_pomodoros++;
            $next = $this->completed_pomodoros % self::LONG_BREAK_INTERVAL === 0 ? 'long_break' : 'short_break';
        }

        $this->completed_type = $finished;
        $this->completed_at_ms = $this->ends_at_ms;
        $this->lineUp($next);
        $this->recordEvent('complete');

        return true;
    }

    public function isDue(int $toleranceMs = 0): bool
    {
        return $this->status === 'running' && $this->ends_at_ms <= now()->getTimestampMs() + $toleranceMs;
    }

    public function state(): array
    {
        $now = now()->getTimestampMs();

        return [
            'version' => $this->version,
            'timer_type' => $this->timer_type,
            'status' => $this->status,
            'duration' => $this->duration,
            'remaining' => $this->status === 'running' ? $this->secondsLeft($now) : $this->remaining,
            'ends_at' => $this->ends_at_ms,
            'event' => $this->last_event,
            'completed_type' => $this->completed_type,
            'completed_at' => $this->completed_at_ms,
            'server_time' => $now,
        ];
    }

    private function lineUp(string $timerType): void
    {
        $this->timer_type = $timerType;
        $this->duration = (int) ($this->durations[$timerType] ?? self::DEFAULT_DURATIONS[$timerType]) * 60;
        $this->remaining = $this->duration;
        $this->status = 'idle';
        $this->ends_at_ms = null;
    }

    private function recordEvent(string $event): void
    {
        $this->last_event = $event;
        $this->version++;
    }

    private function secondsLeft(int $now): int
    {
        return max(0, (int) ceil(($this->ends_at_ms - $now) / 1000));
    }
}
