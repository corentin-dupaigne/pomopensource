<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Which timer lined up in the room this is: a device reloading mid-pomodoro
    // keeps counting its focus session when the run is the same.
    public function up(): void
    {
        Schema::table('activity_rooms', function (Blueprint $table) {
            $table->unsignedInteger('run')->default(0)->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('activity_rooms', function (Blueprint $table) {
            $table->dropColumn('run');
        });
    }
};
