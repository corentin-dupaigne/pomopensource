<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Users signed in through a Discord Activity have a Discord ID but no
    // email or password.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_id')->nullable()->unique()->after('id');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['discord_id']);
            $table->dropColumn('discord_id');
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
