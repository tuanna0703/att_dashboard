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
        Schema::create('campaign_traffic_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_traffic_id')
                  ->constrained('campaign_traffics')->cascadeOnDelete();
            $table->foreignId('booking_line_item_id')
                  ->constrained('booking_line_items')->restrictOnDelete();
            $table->foreignId('screen_id')->constrained('screens')->restrictOnDelete();
            $table->foreignId('creative_asset_id')
                  ->constrained('creative_assets')->restrictOnDelete();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('dayparting_override')->nullable();
            $table->string('cms_placement_id')->nullable();
            $table->integer('spot_duration')->default(15);
            $table->integer('spots_per_hour')->nullable();

            $table->enum('status', [
                'draft',
                'uploaded',
                'scheduled',
                'qa_pending',
                'qa_passed',
                'qa_failed',
                'live',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->timestamp('test_played_at')->nullable();
            $table->string('test_played_by_name')->nullable();
            $table->text('qa_note')->nullable();
            $table->string('qa_screenshot_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_traffic_items');
    }
};
