<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('settings')->where('key', 'theme')->first();
        if (!$setting) return;

        $options = json_decode($setting->options, true) ?? [];
        $options = array_values(array_filter($options, fn($o) => $o !== 'Moscow Subway'));

        DB::table('settings')->where('key', 'theme')->update([
            'options' => json_encode($options),
            'default_value' => 'Lofi Cafe',
        ]);
    }

    public function down(): void
    {
        $setting = DB::table('settings')->where('key', 'theme')->first();
        if (!$setting) return;

        $options = json_decode($setting->options, true) ?? [];
        $options[] = 'Moscow Subway';
        sort($options);

        DB::table('settings')->where('key', 'theme')->update([
            'options' => json_encode(array_values($options)),
            'default_value' => $options[0] ?? 'Lofi Cafe',
        ]);
    }
};
