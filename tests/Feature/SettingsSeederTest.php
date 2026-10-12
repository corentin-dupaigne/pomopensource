<?php

use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

test('seeding again refreshes the choices from the files and keeps the rest', function () {
    $this->seed(SettingsSeeder::class);
    DB::table('settings')->where('key', 'alert_sound')->update([
        'options' => json_encode(['Waves']),
        'default_value' => 'Fire',
    ]);

    $this->seed(SettingsSeeder::class);

    $sounds = array_map(
        fn ($file) => ucwords(str_replace('_', ' ', basename($file, '.mp3'))),
        glob(public_path('sounds/*.mp3'))
    );
    $setting = DB::table('settings')->where('key', 'alert_sound')->first();
    expect(json_decode($setting->options, true))->toBe($sounds)
        ->and($setting->default_value)->toBe('Fire');
});

test('every alert sound on offer has its file', function () {
    $this->seed(SettingsSeeder::class);

    $options = json_decode(DB::table('settings')->where('key', 'alert_sound')->value('options'), true);
    foreach ($options as $sound) {
        expect(public_path('sounds/'.strtolower(str_replace(' ', '_', $sound)).'.mp3'))->toBeFile();
    }
});
