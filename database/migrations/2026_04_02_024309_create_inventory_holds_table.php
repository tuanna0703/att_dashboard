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
        Schema::create('inventory_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->constrained('screens')->cascadeOnDelete();
            $table->foreignId('booking_line_item_id')->constrained('booking_line_items')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('held_by')->constrained('users')->restrictOnDelete();

            $table->date('hold_start');
            $table->date('hold_end');
            $table->integer('spot_duration')->default(15);
            $table->integer('spots_per_hour')->nullable();

            $table->enum('hold_type', ['soft', 'hard'])->default('soft');
            $table->enum('status', ['active', 'released', 'expired', 'converted'])->default('active');
            $table->timestamp('expires_at')->nullable();  // soft holds expire, hard holds don't
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason')->nullable();

            $table->timestamps();

            $table->index(['screen_id', 'hold_start', 'hold_end', 'status'], 'inventory_holds_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_holds');
    }
};
