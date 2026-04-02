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
        Schema::create('booking_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('plan_line_item_id')->nullable()->constrained('plan_line_items')->nullOnDelete();
            $table->foreignId('screen_id')->nullable()->constrained('screens')->nullOnDelete();

            // Screen info snapshot (preserved even if screen is deleted/edited)
            $table->string('venue_name');
            $table->string('venue_type', 50)->nullable();
            $table->string('location_city', 100)->nullable();
            $table->string('screen_code', 50)->nullable();
            $table->json('screen_snapshot')->nullable(); // Full screen data at time of booking

            // Schedule
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('spot_duration')->default(15);
            $table->integer('spots_per_hour')->nullable();
            $table->integer('daily_hours')->nullable();
            $table->decimal('total_spots', 10, 0)->nullable();

            // Pricing
            $table->string('pricing_model', 20)->default('cpm');
            $table->decimal('rate_card_price', 15, 2)->nullable();
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('net_price', 15, 2)->nullable();
            $table->decimal('cpm', 10, 2)->nullable();
            $table->decimal('booked_impressions', 15, 0)->nullable();

            // Media buying status per line item
            $table->enum('buying_status', ['pending', 'partial', 'fully_bought', 'cancelled'])->default('pending');
            $table->decimal('bought_impressions', 15, 0)->default(0);

            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'buying_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_line_items');
    }
};
