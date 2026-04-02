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
        Schema::create('ready_to_air_gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();

            // 7 gate conditions — tất cả phải true thì booking mới được phát sóng
            $table->boolean('booking_fully_bought')->default(false);     // MBO đã xong
            $table->boolean('contract_signed')->default(false);          // HĐ đã ký
            $table->boolean('creative_approved')->default(false);        // Creative đã QA pass
            $table->boolean('campaign_trafficked')->default(false);      // Đã setup trên CMS
            $table->boolean('qa_passed')->default(false);                // Test on-screen pass
            $table->boolean('payment_received')->default(false);         // Đã thu tiền
            $table->boolean('content_compliance_ok')->default(false);    // Nội dung đúng quy định

            $table->boolean('all_conditions_met')->default(false);
            $table->timestamp('gate_passed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ready_to_air_gates');
    }
};
