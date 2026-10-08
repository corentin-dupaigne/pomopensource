<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // "Birds" was never shipped in public/sounds, so the default played nothing.
    public function up(): void
    {
        $setting = DB::table('settings')->where('key', 'alert_sound')->first();
        if (!$setting) return;

        DB::table('settings')->where('id', $setting->id)->where('default_value', 'Birds')
            ->update(['default_value' => 'Waves']);

        DB::table('user_settings')->where('setting_id', $setting->id)->where('value', 'Birds')
            ->update(['value' => 'Waves']);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'alert_sound')->where('default_value', 'Waves')
            ->update(['default_value' => 'Birds']);
    }
};
