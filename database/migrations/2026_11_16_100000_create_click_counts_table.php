<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How often each part of the interface is opened, per day, in the
     * Activity or on the website. Counts only: nothing says who clicked.
     */
    public function up(): void
    {
        Schema::create('click_counts', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('context', 16);
            $table->string('element', 32);
            $table->unsignedInteger('clicks')->default(0);
            $table->unique(['day', 'context', 'element']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_counts');
    }
};
