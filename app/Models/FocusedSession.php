<?php

namespace App\Models;

use App\Http\Controllers\UserStatsController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FocusedSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'task_id',
        'started_at',
        'ended_at',
        'minute_focused',
        'break_time',
        'note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'minute_focused' => 'integer',
        'break_time' => 'integer',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    protected static function booted()
    {
        static::created(function ($focusedSession) {
            $userStatsController = new UserStatsController();
            $userStatsController->updateStats($focusedSession->user);
        });

        static::updated(function ($focusedSession) {
            $userStatsController = new UserStatsController();
            $userStatsController->updateStats($focusedSession->user);
        });
    }

}
