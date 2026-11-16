<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Discord avatar's hash, for the server leaderboards: they list
     * members who are not in the call, whom the Activity knows nothing of.
     * Set on each sign-in with Discord.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_avatar', 64)->nullable()->after('discord_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('discord_avatar');
        });
    }
};
