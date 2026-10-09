<?php

namespace Database\Seeders;

use App\Models\FocusedSession;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FocusedSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $userId = 1; // Replace this with the actual user ID you want to add sessions to

        foreach (range(1, 50) as $index) {
            $startTime = Carbon::now()->subDays(rand(1, 30))->subMinutes(rand(0, 1440));

            $minutesFocused = rand(15, 240);
            $endTime = $startTime->copy()->addMinutes($minutesFocused);

            $breakTime = rand(5, 30);

            FocusedSession::create([
                'user_id' => $userId,
                'started_at' => $startTime,
                'ended_at' => $endTime,
                'minute_focused' => $minutesFocused,
                'break_time' => $breakTime,
            ]);
        }
    }
}
