<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Discord Activity instance that used the shared timer: where the call
 * was, and when it opened. Kept for good, for the usage statistics: the
 * room itself is deleted a day after the call.
 *
 * Nothing here says who was in the call: only how many accounts used
 * its timer.
 */
class ActivityLaunch extends Model
{
    public const UPDATED_AT = null;

    public const SERVER = 'server';

    // A DM or group DM call, which has no server.
    public const PRIVATE = 'private';

    public const UNKNOWN = 'unknown';

    /**
     * Where a call was, from the instance ID Discord gave it:
     * "i-<launch>-gc-<guild>-<channel>" in a server's voice channel,
     * "i-<launch>-pc-<channel>" in a private call.
     *
     * @return array{kind: string, guild_id: ?string, channel_id: ?string}
     */
    public static function placeOf(string $instanceId): array
    {
        if (preg_match('/-gc-(\d+)-(\d+)$/', $instanceId, $match)) {
            return ['kind' => self::SERVER, 'guild_id' => $match[1], 'channel_id' => $match[2]];
        }
        if (preg_match('/-pc-(\d+)$/', $instanceId, $match)) {
            return ['kind' => self::PRIVATE, 'guild_id' => null, 'channel_id' => $match[1]];
        }

        return ['kind' => self::UNKNOWN, 'guild_id' => null, 'channel_id' => null];
    }

    /**
     * Record a call once, however many of its devices open it at once.
     */
    public static function record(string $instanceId, $openedAt = null): void
    {
        static::query()->insertOrIgnore([
            'instance_id' => $instanceId,
            ...static::placeOf($instanceId),
            'created_at' => $openedAt ?? now(),
        ]);
    }
}
