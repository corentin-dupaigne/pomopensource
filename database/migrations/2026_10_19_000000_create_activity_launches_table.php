<?php

use App\Models\ActivityLaunch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Where and when calls used the timer, for the usage statistics. The
    // calls still kept are recorded too.
    public function up(): void
    {
        Schema::create('activity_launches', function (Blueprint $table) {
            $table->id();
            $table->string('instance_id', 100)->unique();
            $table->string('kind', 16);
            $table->string('guild_id', 32)->nullable()->index();
            $table->string('channel_id', 32)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        DB::table('activity_rooms')->orderBy('id')->each(function ($room) {
            ActivityLaunch::record($room->instance_id, $room->created_at);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_launches');
    }
};
