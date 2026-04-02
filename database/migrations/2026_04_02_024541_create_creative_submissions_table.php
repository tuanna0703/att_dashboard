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
        Schema::create('creative_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_no', 50)->unique(); // CR-YYYY-NNNN
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_qa_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('campaign_name');
            $table->integer('version')->default(1);
            $table->json('required_specs')->nullable(); // resolution, duration, format requirements

            $table->enum('status', [
                'draft',
                'submitted',
                'qa_in_progress',
                'approved',
                'rejected',
                'revision_pending',
                'superseded',
            ])->default('draft');

            $table->text('submission_note')->nullable();
            $table->text('qa_summary')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('qa_completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_submissions');
    }
};
