<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_no', 50)->unique();
            $table->foreignId('brief_id')->constrained('briefs')->restrictOnDelete();
            $table->foreignId('brief_revision_id')->constrained('brief_revisions')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('adops_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('campaign_name', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_budget', 18, 2)->nullable();
            $table->enum('status', [
                'pending_contract',
                'contract_signed',
                'campaign_active',
                'campaign_completed',
                'acceptance_done',
                'closed',
                'cancelled',
            ])->default('pending_contract');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
