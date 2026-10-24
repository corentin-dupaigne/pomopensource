<?php

use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

// The setting as it was before it was folded into the volume.
beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    $this->playSound = DB::table('settings')->insertGetId([
        'category_id' => 3,
        'key' => 'play_sound',
        'name' => 'Play sound when timer finishes',
        'type' => 'checkbox',
        'default_value' => 'true',
        'display_order' => 3,
    ]);
    $this->volume = DB::table('settings')->where('key', 'alert_volume')->value('id');
    $this->migration = require database_path('migrations/2026_10_24_100000_fold_play_sound_into_alert_volume.php');
});

$volumeOf = fn (User $user) => DB::table('user_settings')
    ->where(['user_id' => $user->id, 'setting_id' => test()->volume])
    ->value('value');

test('those who turned the sound off keep a silent alert', function () use ($volumeOf) {
    $muted = User::factory()->create();
    $mutedLoud = User::factory()->create();
    DB::table('user_settings')->insert([
        ['user_id' => $muted->id, 'setting_id' => $this->playSound, 'value' => '0'],
        ['user_id' => $mutedLoud->id, 'setting_id' => $this->playSound, 'value' => 'false'],
        ['user_id' => $mutedLoud->id, 'setting_id' => $this->volume, 'value' => '80'],
    ]);

    $this->migration->up();

    expect($volumeOf($muted))->toBe('0')
        ->and($volumeOf($mutedLoud))->toBe('0');
});

test('the others keep their volume, and the setting is gone', function () use ($volumeOf) {
    $user = User::factory()->create();
    DB::table('user_settings')->insert([
        ['user_id' => $user->id, 'setting_id' => $this->playSound, 'value' => '1'],
        ['user_id' => $user->id, 'setting_id' => $this->volume, 'value' => '30'],
    ]);

    $this->migration->up();

    expect($volumeOf($user))->toBe('30')
        ->and(DB::table('settings')->where('key', 'play_sound')->exists())->toBeFalse()
        ->and(DB::table('user_settings')->where('setting_id', $this->playSound)->exists())->toBeFalse();
});

test('seeding no longer brings the setting back', function () {
    $this->migration->up();
    $this->seed(SettingsSeeder::class);

    expect(DB::table('settings')->where('key', 'play_sound')->exists())->toBeFalse();
});
