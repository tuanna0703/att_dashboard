<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('screen_availability_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->constrained('screens')->cascadeOnDelete();
            $table->date('date');

            $table->integer('total_slots_per_hour');
            $table->integer('operational_hours');
            $table->decimal('total_daily_slots', 10, 0);
            $table->decimal('sold_slots', 10, 0)->default(0);
            $table->decimal('held_slots', 10, 0)->default(0);
            $table->decimal('available_slots', 10, 0)->default(0);
            $table->decimal('fill_rate_pct', 5, 2)->default(0);

            $table->timestamp('calculated_at');

            $table->unique(['screen_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screen_availability_logs');
    }
};
