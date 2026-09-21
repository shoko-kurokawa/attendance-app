<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('break_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_correction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('break_id')->nullable()->constrained()->cascadeOnDelete();
            $table->time('break_start');
            $table->time('break_end');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('break_corrections');
    }
};
