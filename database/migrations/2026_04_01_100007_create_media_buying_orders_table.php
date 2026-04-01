<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_buying_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();         // adops

            // Cấp 1: Trưởng phòng duyệt (coo / vice_ceo)
            $table->foreignId('dept_head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dept_head_approved_at')->nullable();

            // Cấp 2: Kết toán duyệt (finance_manager)
            $table->foreignId('finance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finance_approved_at')->nullable();

            // Cấp 3: Buyer thực thi
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('buyer_executed_at')->nullable();

            $table->decimal('total_amount', 18, 2)->default(0);
            $table->enum('status', [
                'draft',
                'pending_dept',
                'dept_approved',
                'pending_finance',
                'finance_approved',
                'sent_to_buyer',
                'executed',
                'completed',
                'rejected',
            ])->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->text('note')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_buying_orders');
    }
};
