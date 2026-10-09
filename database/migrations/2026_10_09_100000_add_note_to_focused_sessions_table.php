<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the session was about, in the user's words: "chapter 3", "two-sum".
     */
    public function up(): void
    {
        Schema::table('focused_sessions', function (Blueprint $table) {
            $table->string('note', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('focused_sessions', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
