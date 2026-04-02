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
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('venue_name')->nullable();
            $table->string('venue_type', 50)->nullable();   // mall, airport, hospital, outdoor, ...
            $table->string('location_city', 100)->nullable();
            $table->string('location_address')->nullable();
            $table->string('province', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('width_px')->nullable();
            $table->integer('height_px')->nullable();
            $table->string('resolution')->nullable();           // e.g. "1920x1080"
            $table->integer('total_slots_per_hour')->default(4); // e.g. 4 spots/hr = 15s each
            $table->integer('operational_hours')->default(18);  // hours/day screen is on
            $table->integer('slot_duration_seconds')->default(15);
            $table->decimal('rate_card_cpm', 10, 2)->nullable();
            $table->decimal('rate_card_daily', 10, 2)->nullable();
            $table->string('ad_network')->nullable();           // which AdNetwork this screen belongs to
            $table->foreignId('ad_network_id')->nullable()->constrained('ad_networks')->nullOnDelete();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};
