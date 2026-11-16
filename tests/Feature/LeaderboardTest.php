<?php

use App\Models\FocusedSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSetting;
use Database\Seeders\SettingsSeeder;

const BOARD_GUILD = '900000000000000001';
const BOARD_CALL = 'i-1427-gc-900000000000000001-900000000000000002';

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    $this->viewer = boardMember('Viewer');
});

function boardMember(string $name): User
{
    static $next = 1;

    return User::factory()->create(['name' => $name, 'discord_id' => (string) (100 + $next++), 'discord_avatar' => 'abc']);
}

function focusedInGuild(User $user, int $minutes, $startedAt = null, ?string $guild = BOARD_GUILD): void
{
    $start = $startedAt ?? now()->subHour();
    FocusedSession::create([
        'user_id' => $user->id,
        'started_at' => $start,
        'ended_at' => $start->copy()->addMinutes($minutes),
        'minute_focused' => $minutes,
        'guild_id' => $guild,
    ]);
}

function leaderboard($test, string $period = 'week', string $instance = BOARD_CALL)
{
    return $test->actingAs($test->viewer)->getJson("/leaderboard/{$instance}?period={$period}");
}

test('members are ranked by time focused in this server\'s calls', function () {
    focusedInGuild($ada = boardMember('Ada'), 50);
    focusedInGuild($linus = boardMember('Linus'), 120);
    focusedInGuild($this->viewer, 25);

    $response = leaderboard($this)->assertOk();

    expect($response->json('entries.*.user.global_name'))->toBe(['Linus', 'Ada', 'Viewer'])
        ->and($response->json('entries.*.minutes'))->toBe([120, 50, 25])
        ->and($response->json('entries.*.rank'))->toBe([1, 2, 3])
        ->and($response->json('entries.2.me'))->toBeTrue()
        ->and($response->json('entries.0.user'))->toBe(['id' => $linus->discord_id, 'username' => 'Linus', 'global_name' => 'Linus', 'avatar' => 'abc']);
});

test('only this server counts: not other servers, private calls or the website', function () {
    $ada = boardMember('Ada');
    focusedInGuild($ada, 30);
    focusedInGuild($ada, 100, guild: '900000000000000009');
    focusedInGuild($ada, 100, guild: null);

    expect(leaderboard($this)->json('entries'))->toHaveCount(1)
        ->and(leaderboard($this)->json('entries.0.minutes'))->toBe(30);
});

test('periods are rolling windows', function () {
    $ada = boardMember('Ada');
    focusedInGuild($ada, 10, now()->subHours(2));
    focusedInGuild($ada, 20, now()->subDays(3));
    focusedInGuild($ada, 40, now()->subDays(30));

    expect(leaderboard($this, 'day')->json('entries.0.minutes'))->toBe(10)
        ->and(leaderboard($this, 'week')->json('entries.0.minutes'))->toBe(30)
        ->and(leaderboard($this, 'all')->json('entries.0.minutes'))->toBe(70);
});

test('nobody is listed with no time, so the viewer only appears once they focused', function () {
    focusedInGuild(boardMember('Ada'), 30);

    expect(leaderboard($this)->json('entries.*.user.global_name'))->toBe(['Ada'])
        ->and(leaderboard($this)->json('me'))->toBeNull();
});

test('a day counts at most 12 hours', function () {
    $ada = boardMember('Ada');
    focusedInGuild($ada, 600, now()->subDays(2)->setTime(1, 0));
    focusedInGuild($ada, 600, now()->subDays(2)->setTime(12, 0));
    focusedInGuild($ada, 60, now()->subDay()->setTime(12, 0));

    expect(leaderboard($this)->json('entries.0.minutes'))->toBe(12 * 60 + 60);
});

test('equal time shares a rank', function () {
    focusedInGuild(boardMember('Ada'), 30);
    focusedInGuild(boardMember('Linus'), 30);
    focusedInGuild(boardMember('Grace'), 10);

    expect(leaderboard($this)->json('entries.*.rank'))->toBe([1, 1, 3]);
});

test('the top 10 are shown, and the viewer\'s own row when further down', function () {
    foreach (range(1, 11) as $i) {
        focusedInGuild(boardMember("M{$i}"), 100 + $i);
    }
    focusedInGuild($this->viewer, 5);

    $response = leaderboard($this);

    expect($response->json('entries'))->toHaveCount(10)
        ->and($response->json('me.rank'))->toBe(12)
        ->and($response->json('me.minutes'))->toBe(5);
});

test('those who turned it off are not listed, but still see the leaderboard', function () {
    focusedInGuild(boardMember('Ada'), 30);
    focusedInGuild($this->viewer, 60);
    UserSetting::create([
        'user_id' => $this->viewer->id,
        'setting_id' => Setting::where('key', 'show_in_leaderboards')->value('id'),
        'value' => '0',
    ]);

    $response = leaderboard($this)->assertOk();

    expect($response->json('entries.*.user.global_name'))->toBe(['Ada'])
        ->and($response->json('hidden'))->toBeTrue();
});

test('private calls have no leaderboard', function () {
    leaderboard($this, 'week', 'i-1427-pc-900000000000000002')->assertNotFound();
});

test('it is for accounts signed in through Discord', function () {
    $this->viewer = User::factory()->create();
    leaderboard($this)->assertForbidden();

    auth()->logout();
    $this->getJson('/leaderboard/'.BOARD_CALL.'?period=week')->assertUnauthorized();
});

test('the period must be one of the three', function () {
    leaderboard($this, 'month')->assertUnprocessable();
});
