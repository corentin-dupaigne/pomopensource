<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'minute_focused',
    ];

    protected $casts = [
        'minute_focused' => 'integer',
    ];


    /**
     * Get this project owner
     *
     * @return BelongsTo
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function focusedSessions()
    {
        return $this->hasMany(FocusedSession::class);
    }

    /**
     * A subject's name as typed, without stray whitespace.
     */
    public static function clean(?string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name ?? ''));
    }

    /**
     * How subjects are matched: "Biochem" and "biochem " are the same one.
     */
    public static function keyOf(string $name): string
    {
        return mb_strtolower(self::clean($name));
    }

    /**
     * The user's subject of this name, made the first time it is typed.
     * Touched, so the most recent come first among the suggestions.
     */
    public static function named(User $user, string $name): self
    {
        $name = self::clean($name);
        $key = self::keyOf($name);
        $project = $user->projects()->get()->first(fn ($p) => self::keyOf($p->name) === $key)
            ?? $user->projects()->create(['name' => $name]);
        // Removed from the suggestions before: typed again, it is back.
        $project->forceFill(['removed_at' => null])->touch();

        return $project;
    }
}
