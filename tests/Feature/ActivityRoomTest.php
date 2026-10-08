<?php

use App\Models\ActivityRoom;
use App\Models\User;

function discordUser(): User
{
    return User::create(['discord_id' => '1234', 'name' => 'Pomo Fan']);
}

function act(User $user, array $payload, string $instance = 'i-1')
{
    return test()->actingAs($user)->postJson("/activity-rooms/{$instance}", $payload);
}

test('a new room starts as an idle pomodoro', function () {
    $this->actingAs(discordUser())
        ->getJson('/activity-rooms/i-1')
        ->assertOk()
        ->assertJson([
            'version' => 0,
            'timer_type' => 'pomodoro',
            'status' => 'idle',
            'duration' => 25 * 60,
            'remaining' => 25 * 60,
            'ends_at' => null,
        ]);
});

test('starting runs the timer until a shared end time', function () {
    $this->freezeTime();
    $user = discordUser();

    act($user, ['action' => 'start', 'durations' => ['pomodoro' => 50]])
        ->assertJson(['status' => 'running', 'version' => 1, 'event' => 'start', 'remaining' => 25 * 60])
        ->assertJsonPath('ends_at', now()->getTimestampMs() + 25 * 60 * 1000);

    // Starting twice changes nothing.
    act($user, ['action' => 'start'])->assertJson(['version' => 1]);
});

test('pausing keeps the time left, and starting resumes it', function () {
    $this->freezeTime();
    $user = discordUser();
    act($user, ['action' => 'start']);

    $this->travel(10)->minutes();
    act($user, ['action' => 'pause'])
        ->assertJson(['status' => 'paused', 'remaining' => 15 * 60, 'ends_at' => null]);

    $this->travel(1)->hour();
    act($user, ['action' => 'start'])
        ->assertJson(['status' => 'running', 'remaining' => 15 * 60]);
});

test('switching lines up a timer with the actor\'s duration', function () {
    act(discordUser(), ['action' => 'switch', 'timer_type' => 'short_break', 'durations' => ['short_break' => 7]])
        ->assertJson(['timer_type' => 'short_break', 'status' => 'idle', 'duration' => 7 * 60, 'event' => 'switch']);
});

test('resetting lines the current timer up again', function () {
    $user = discordUser();
    act($user, ['action' => 'start']);
    act($user, ['action' => 'reset'])
        ->assertJson(['status' => 'idle', 'remaining' => 25 * 60, 'event' => 'reset']);
});

test('a timer cannot be reported complete before it ends', function () {
    $user = discordUser();
    act($user, ['action' => 'start']);

    act($user, ['action' => 'complete'])->assertJson(['status' => 'running', 'version' => 1]);
});

test('completing a pomodoro lines up a break, and every fourth a long one', function () {
    $user = discordUser();

    foreach (['short_break', 'short_break', 'short_break', 'long_break'] as $expected) {
        act($user, ['action' => 'switch', 'timer_type' => 'pomodoro']);
        act($user, ['action' => 'start']);
        $this->travel(25)->minutes();

        act($user, ['action' => 'complete'])
            ->assertJson(['timer_type' => $expected, 'status' => 'idle', 'event' => 'complete', 'completed_type' => 'pomodoro']);
    }
});

test('a client may report completion slightly early', function () {
    $user = discordUser();
    act($user, ['action' => 'start']);
    $this->travel(25 * 60 - 1)->seconds();

    act($user, ['action' => 'complete'])->assertJson(['event' => 'complete']);
});

test('reading a room ends a timer that ran out', function () {
    $user = discordUser();
    act($user, ['action' => 'switch', 'timer_type' => 'short_break']);
    act($user, ['action' => 'start']);
    $this->travel(6)->minutes();

    $this->actingAs($user)->getJson('/activity-rooms/i-1')
        ->assertJson(['timer_type' => 'pomodoro', 'status' => 'idle', 'event' => 'complete', 'completed_type' => 'short_break']);
});

test('each instance has its own room', function () {
    $user = discordUser();
    act($user, ['action' => 'start'], 'i-1');

    $this->actingAs($user)->getJson('/activity-rooms/i-2')->assertJson(['status' => 'idle']);
    expect(ActivityRoom::count())->toBe(2);
});

test('actions are validated', function () {
    $user = discordUser();
    act($user, ['action' => 'explode'])->assertUnprocessable();
    act($user, ['action' => 'switch'])->assertUnprocessable();
    act($user, ['action' => 'switch', 'timer_type' => 'nap'])->assertUnprocessable();
    act($user, ['action' => 'start', 'durations' => ['pomodoro' => 0]])->assertUnprocessable();
    act($user, ['action' => 'start', 'durations' => ['nap' => 5]])->assertUnprocessable();
});

test('rooms are only for accounts signed in through Discord', function () {
    $this->getJson('/activity-rooms/i-1')->assertUnauthorized();

    $user = User::create(['name' => 'Web user', 'email' => 'web@example.com', 'password' => 'secret']);
    $this->actingAs($user)->getJson('/activity-rooms/i-1')->assertForbidden();
});

test('rooms of ended calls are pruned after a day', function () {
    act(discordUser(), ['action' => 'start']);
    $this->travel(2)->days();

    $this->artisan('model:prune', ['--model' => [ActivityRoom::class]]);

    expect(ActivityRoom::count())->toBe(0);
});
