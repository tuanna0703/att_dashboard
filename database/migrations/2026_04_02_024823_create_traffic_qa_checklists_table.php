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
        Schema::create('traffic_qa_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_traffic_item_id')
                  ->constrained('campaign_traffic_items')->cascadeOnDelete();
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();

            $table->enum('check_type', [
                'video_plays',
                'correct_creative',
                'correct_duration',
                'audio_ok',
                'display_fullscreen',
                'schedule_correct',
                'loop_correct',
                'no_glitch',
            ]);
            $table->enum('result', ['pass', 'fail', 'warning']);
            $table->boolean('is_blocking')->default(true);
            $table->text('note')->nullable();
            $table->timestamp('checked_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traffic_qa_checklists');
    }
};
