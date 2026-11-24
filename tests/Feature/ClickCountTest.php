<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

function clicks(string $element, string $context = 'activity'): ?int
{
    return DB::table('click_counts')->where(['element' => $element, 'context' => $context])->value('clicks');
}

test('each click adds one to the day\'s count', function () {
    $this->postJson('/usage/clicks', ['element' => 'stats', 'context' => 'activity'])->assertNoContent();
    $this->postJson('/usage/clicks', ['element' => 'stats', 'context' => 'activity'])->assertNoContent();

    expect(clicks('stats'))->toBe(2);
});

test('counts are kept apart by element, context and day', function () {
    $this->postJson('/usage/clicks', ['element' => 'stats', 'context' => 'activity']);
    $this->postJson('/usage/clicks', ['element' => 'stats', 'context' => 'website']);
    $this->postJson('/usage/clicks', ['element' => 'settings', 'context' => 'activity']);
    $this->travel(1)->days();
    $this->postJson('/usage/clicks', ['element' => 'stats', 'context' => 'activity']);

    expect(DB::table('click_counts')->count())->toBe(4)
        ->and(DB::table('click_counts')->sum('clicks'))->toEqual(4);
});

test('nothing says who clicked', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/usage/clicks', ['element' => 'leaderboard', 'context' => 'activity'])
        ->assertNoContent();

    expect(array_keys((array) DB::table('click_counts')->first()))
        ->toBe(['id', 'day', 'context', 'element', 'clicks']);
});

test('only known elements and contexts are counted', function (array $payload) {
    $this->postJson('/usage/clicks', $payload)->assertUnprocessable();

    expect(DB::table('click_counts')->count())->toBe(0);
})->with([
    'unknown element' => [['element' => 'anything', 'context' => 'activity']],
    'unknown context' => [['element' => 'stats', 'context' => 'elsewhere']],
    'missing' => [[]],
]);

test('opening the stats calendar is counted, and the old detail tab still is', function () {
    $this->postJson('/usage/clicks', ['element' => 'stats_calendar', 'context' => 'activity'])->assertNoContent();
    $this->postJson('/usage/clicks', ['element' => 'stats_detail', 'context' => 'activity'])->assertNoContent();

    expect(clicks('stats_calendar'))->toBe(1)
        ->and(clicks('stats_detail'))->toBe(1);
});
