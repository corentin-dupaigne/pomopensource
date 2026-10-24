<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->startedAt = now()->subMinutes(25)->startOfSecond()->toISOString();
});

test('a session started in a server call keeps the server', function () {
    $this->actingAs($this->user)->postJson('/focused-sessions', [
        'started_at' => $this->startedAt,
        'instance_id' => 'i-1427-gc-900000000000000001-900000000000000002',
    ])->assertOk();

    expect($this->user->focusedSessions()->sole()->guild_id)->toBe('900000000000000001');
});

test('a session in a private call or on the website has no server', function (?string $instanceId) {
    $this->actingAs($this->user)->postJson('/focused-sessions', [
        'started_at' => $this->startedAt,
        'instance_id' => $instanceId,
    ])->assertOk();

    expect($this->user->focusedSessions()->sole()->guild_id)->toBeNull();
})->with([
    'private call' => 'i-1427-pc-900000000000000002',
    'website' => null,
]);

test('a session whose start never reached the server still gets its server at the end', function () {
    $this->actingAs($this->user)->patchJson('/focused-sessions/current', [
        'started_at' => $this->startedAt,
        'ended_at' => now()->toISOString(),
        'time_focused' => 25 * 60,
        'instance_id' => 'i-1427-gc-900000000000000001-900000000000000002',
    ])->assertOk();

    expect($this->user->focusedSessions()->sole()->guild_id)->toBe('900000000000000001');
});
