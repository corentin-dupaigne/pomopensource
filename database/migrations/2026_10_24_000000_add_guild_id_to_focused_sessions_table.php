<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Discord server whose call the session was focused in, for a
     * leaderboard per server. Null on the website and in private calls.
     */
    public function up(): void
    {
        Schema::table('focused_sessions', function (Blueprint $table) {
            $table->string('guild_id', 32)->nullable();
            $table->index(['guild_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('focused_sessions', function (Blueprint $table) {
            $table->dropIndex(['guild_id', 'started_at']);
            $table->dropColumn('guild_id');
        });
    }
};
