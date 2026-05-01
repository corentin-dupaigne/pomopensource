<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        // Seed setting categories
        $categories = [
            ['id' => 1, 'name' => 'General', 'icon' => 'fas fa-cog', 'display_order' => 1],
            ['id' => 2, 'name' => 'Timers', 'icon' => 'fas fa-clock', 'display_order' => 2],
            ['id' => 3, 'name' => 'Sound', 'icon' => 'fas fa-volume-up', 'display_order' => 3],
        ];

        foreach ($categories as $category) {
            DB::table('setting_categories')->insertOrIgnore($category);
        }

        // Read theme image files from the directory
        $themeImages = glob(public_path('images/backgrounds/*.webp'));
        $themeOptions = [];

        foreach ($themeImages as $image) {
            $basename = basename($image, '.webp');
            if ($basename != "gf") {
                // Replace underscores with spaces and capitalize each word
                $formattedName = ucwords(str_replace('_', ' ', $basename));
                // Add to theme options
                $themeOptions[] = $formattedName;
            }
        }

        $sounds = glob(public_path('sounds/*.mp3'));
        $soundOptions = [];

        foreach ($sounds as $sound) {
            $basename = basename($sound, '.mp3');
            $formattedName = ucwords(str_replace('_', ' ', $basename));
            $soundOptions[] = $formattedName;

        }

        // Seed settings
        $settings = [
            // General settings
            [
                'category_id' => 1,
                'key' => 'theme',
                'name' => 'Theme',
                'type' => 'select',
                'options' => json_encode($themeOptions),
                'default_value' => $themeOptions[0] ?? 'Lofi Cafe',
                'display_order' => 1,
            ],
            // Timer settings
            [
                'category_id' => 2,
                'key' => 'pomodoro_duration',
                'name' => 'Pomodoro',
                'type' => 'number',
                'options' => null,
                'default_value' => '25',
                'display_order' => 1,
            ],
            [
                'category_id' => 2,
                'key' => 'short_break_duration',
                'name' => 'Short Break',
                'type' => 'number',
                'options' => null,
                'default_value' => '5',
                'display_order' => 2,
            ],
            [
                'category_id' => 2,
                'key' => 'long_break_duration',
                'name' => 'Long Break',
                'type' => 'number',
                'options' => null,
                'default_value' => '15',
                'display_order' => 3,
            ],
            [
                'category_id' => 2,
                'key' => 'auto_start_pomodoros',
                'name' => 'Auto Start Pomodoros',
                'type' => 'checkbox',
                'options' => null,
                'default_value' => 'false',
                'display_order' => 5,
            ],
            // Sound settings
            [
                'category_id' => 3,
                'key' => 'alert_sound',
                'name' => 'Alert Sound',
                'type' => 'select',
                'options' => json_encode($soundOptions),
                'default_value' => 'Birds',
                'display_order' => 1,
            ],
            [
                'category_id' => 3,
                'key' => 'alert_volume',
                'name' => 'Alert Volume',
                'type' => 'range',
                'options' => null,
                'default_value' => '50',
                'display_order' => 2,
            ],
            [
                'category_id' => 3,
                'key' => 'play_sound',
                'name' => 'Play sound when timer finishes',
                'type' => 'checkbox',
                'options' => null,
                'default_value' => 'true',
                'display_order' => 3,
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insertOrIgnore($setting);
        }
    }
}
