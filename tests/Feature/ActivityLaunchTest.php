<?php

use App\Models\ActivityRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;

const SERVER_CALL = 'i-1561011784507199509-gc-1387138239101407263-1387138241588756613';
const PRIVATE_CALL = 'i-1561028379702988930-pc-1547323791347154964';

function callMember(string $discordId = '1234'): User
{
    return User::create(['discord_id' => $discordId, 'name' => "Member {$discordId}"]);
}

function launches()
{
    return DB::table('activity_launches')->get();
}

test('a call in a server is recorded with its server and channel', function () {
    $this->freezeTime();

    $this->actingAs(callMember())->getJson('/activity-rooms/'.SERVER_CALL)->assertOk();

    expect(launches())->toHaveCount(1)
        ->and((array) launches()->first())->toMatchArray([
            'instance_id' => SERVER_CALL,
            'kind' => 'server',
            'guild_id' => '1387138239101407263',
            'channel_id' => '1387138241588756613',
            'created_at' => now()->toDateTimeString(),
        ]);
});

test('a private call is recorded with no server', function () {
    $this->actingAs(callMember())->getJson('/activity-rooms/'.PRIVATE_CALL)->assertOk();

    expect((array) launches()->sole())->toMatchArray([
        'kind' => 'private',
        'guild_id' => null,
        'channel_id' => '1547323791347154964',
    ]);
});

test('a call is recorded once, however many members poll and act on it', function () {
    foreach (['1', '2'] as $id) {
        $member = callMember($id);
        $this->actingAs($member)->getJson('/activity-rooms/'.SERVER_CALL)->assertOk();
        $this->actingAs($member)->postJson('/activity-rooms/'.SERVER_CALL, ['action' => 'start'])->assertOk();
    }

    expect(launches())->toHaveCount(1);
});

test('an instance ID of another shape is still counted', function () {
    $this->actingAs(callMember())->getJson('/activity-rooms/i-1')->assertOk();

    expect((array) launches()->sole())->toMatchArray(['kind' => 'unknown', 'guild_id' => null, 'channel_id' => null]);
});

test('the record outlives the room', function () {
    $this->actingAs(callMember())->getJson('/activity-rooms/'.SERVER_CALL)->assertOk();

    $this->travel(2)->days();
    $this->artisan('model:prune', ['--model' => [ActivityRoom::class]])->assertSuccessful();

    expect(ActivityRoom::count())->toBe(0)
        ->and(launches())->toHaveCount(1);
});

test('nothing in the record says who was in the call', function () {
    $this->actingAs(callMember())->getJson('/activity-rooms/'.SERVER_CALL)->assertOk();

    expect(array_keys((array) launches()->sole()))
        ->toEqualCanonicalizing(['id', 'instance_id', 'kind', 'guild_id', 'channel_id', 'participants', 'created_at']);
});

test('a call counts each account that uses its timer once', function () {
    $alice = callMember('1');
    $bob = callMember('2');

    // Each device polls every 2 seconds.
    foreach ([$alice, $alice, $bob, $alice, $bob] as $member) {
        $this->actingAs($member)->getJson('/activity-rooms/'.SERVER_CALL)->assertOk();
    }

    expect(launches()->sole()->participants)->toBe(2);
});

test('someone alone in a call counts as one', function () {
    $this->actingAs(callMember())->getJson('/activity-rooms/'.SERVER_CALL)->assertOk();
    $this->actingAs(callMember('1'))->getJson('/activity-rooms/'.PRIVATE_CALL)->assertOk();

    expect(launches()->pluck('participants')->all())->toBe([1, 1]);
});

test('counting a newcomer changes nothing for the others', function () {
    $alice = callMember('1');
    $this->actingAs($alice)->getJson('/activity-rooms/'.SERVER_CALL)->assertOk();
    $this->actingAs($alice)->postJson('/activity-rooms/'.SERVER_CALL, ['action' => 'start'])->assertOk();
    $version = ActivityRoom::sole()->version;

    $this->actingAs(callMember('2'))->getJson('/activity-rooms/'.SERVER_CALL)
        ->assertOk()
        ->assertJsonPath('version', $version)
        ->assertJsonPath('status', 'running');
});

test('who was in a call goes with the room, the count stays', function () {
    foreach (['1', '2', '3'] as $id) {
        $this->actingAs(callMember($id))->getJson('/activity-rooms/'.SERVER_CALL)->assertOk();
    }

    $this->travel(2)->days();
    $this->artisan('model:prune', ['--model' => [ActivityRoom::class]])->assertSuccessful();

    expect(ActivityRoom::count())->toBe(0)
        ->and(launches()->sole()->participants)->toBe(3);
});
