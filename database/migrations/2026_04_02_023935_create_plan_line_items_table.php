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
        Schema::create('plan_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('screen_id')->nullable()->constrained('screens')->nullOnDelete();

            // Screen info (snapshot or manual input if no screen FK)
            $table->string('venue_name');
            $table->string('venue_type', 50)->nullable();
            $table->string('location_city', 100)->nullable();
            $table->string('screen_code', 50)->nullable();

            // Schedule
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('spot_duration')->default(15);     // seconds per spot
            $table->integer('spots_per_hour')->nullable();
            $table->integer('daily_hours')->nullable();
            $table->decimal('total_spots', 10, 0)->nullable(); // auto-calculated

            // Pricing
            $table->string('pricing_model', 20)->default('cpm'); // cpm | fixed | cpd
            $table->decimal('rate_card_price', 15, 2)->nullable();
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('net_price', 15, 2)->nullable();
            $table->decimal('cpm', 10, 2)->nullable();
            $table->decimal('estimated_impressions', 15, 0)->nullable();

            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_line_items');
    }
};
