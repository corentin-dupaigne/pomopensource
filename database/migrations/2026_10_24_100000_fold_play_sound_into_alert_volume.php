<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Play sound when timer finishes" did what a volume of 0 does: those
     * who turned it off keep a silent alert, with the volume at 0, and the
     * setting goes. Its user values go with it (cascade).
     */
    public function up(): void
    {
        $playSound = DB::table('settings')->where('key', 'play_sound')->value('id');
        $volume = DB::table('settings')->where('key', 'alert_volume')->value('id');
        if (!$playSound) return;

        if ($volume) {
            $muted = DB::table('user_settings')
                ->where('setting_id', $playSound)
                ->whereIn('value', ['0', 'false'])
                ->pluck('user_id');

            foreach ($muted as $userId) {
                DB::table('user_settings')->updateOrInsert(
                    ['user_id' => $userId, 'setting_id' => $volume],
                    ['value' => '0', 'updated_at' => now()]
                );
            }
        }

        DB::table('user_settings')->where('setting_id', $playSound)->delete();
        DB::table('settings')->where('id', $playSound)->delete();
    }

    /**
     * The seeder brings the setting back, on for everyone.
     */
    public function down(): void
    {
        //
    }
};
