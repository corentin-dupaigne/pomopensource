<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One shared timer per Discord Activity instance, i.e. per call.
    public function up(): void
    {
        Schema::create('activity_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('instance_id', 100)->unique();
            $table->string('timer_type')->default('pomodoro');
            $table->string('status')->default('idle');
            $table->unsignedInteger('duration');
            $table->unsignedInteger('remaining');
            $table->unsignedBigInteger('ends_at_ms')->nullable();
            $table->json('durations');
            $table->unsignedInteger('completed_pomodoros')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->string('last_event')->nullable();
            $table->string('completed_type')->nullable();
            $table->unsignedBigInteger('completed_at_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_rooms');
    }
};
