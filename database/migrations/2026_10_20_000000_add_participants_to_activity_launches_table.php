<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // How many accounts used the timer in each call. Only the count is kept
    // for good: the accounts themselves are listed in the room, deleted a
    // day after the call. Calls from before have no count.
    public function up(): void
    {
        Schema::table('activity_rooms', function (Blueprint $table) {
            $table->json('participant_ids')->nullable()->after('durations_changed_by');
        });
        Schema::table('activity_launches', function (Blueprint $table) {
            $table->unsignedInteger('participants')->nullable()->after('channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_launches', function (Blueprint $table) {
            $table->dropColumn('participants');
        });
        Schema::table('activity_rooms', function (Blueprint $table) {
            $table->dropColumn('participant_ids');
        });
    }
};
