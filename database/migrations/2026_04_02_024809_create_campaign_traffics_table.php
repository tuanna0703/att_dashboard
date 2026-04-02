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
        Schema::create('campaign_traffics', function (Blueprint $table) {
            $table->id();
            $table->string('traffic_no', 50)->unique(); // TRF-YYYY-NNNN
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('creative_submission_id')
                  ->constrained('creative_submissions')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('cms_campaign_id')->nullable();
            $table->string('cms_campaign_name')->nullable();
            $table->date('flight_start');
            $table->date('flight_end');

            $table->json('dayparting')->nullable();          // e.g. {"mon":[8,22],"tue":[8,22]}
            $table->integer('frequency_cap_per_hour')->nullable();
            $table->integer('frequency_cap_per_day')->nullable();
            $table->json('targeting_rules')->nullable();

            $table->enum('priority', ['low', 'normal', 'high', 'exclusive'])->default('normal');
            $table->enum('status', [
                'draft',
                'qa_pending',
                'qa_failed',
                'approved',
                'live',
                'paused',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->timestamp('go_live_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->text('setup_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_traffics');
    }
};
