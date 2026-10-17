<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Who last set the call's durations, to tell the others.
    public function up(): void
    {
        Schema::table('activity_rooms', function (Blueprint $table) {
            $table->string('durations_changed_by')->nullable()->after('durations');
        });
    }

    public function down(): void
    {
        Schema::table('activity_rooms', function (Blueprint $table) {
            $table->dropColumn('durations_changed_by');
        });
    }
};
