<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'discord_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected $casts = [
        'show_subjects_notice' => 'boolean',
    ];

    /**
     * The user's subjects, the projects of before: what "What are you
     * working on?" was answered with. The most recently used come first.
     */
    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Project::class)->orderByDesc('updated_at')->orderByDesc('id');
    }

    /**
     * Those the field suggests: all but the ones removed as no longer
     * current, which the history and stats still name.
     */
    public function suggestedSubjects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->projects()->whereNull('removed_at');
    }

    public function focusedSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FocusedSession::class);
    }

    public function settings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    public function stats(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Stats::class);
    }


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
