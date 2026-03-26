<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_schedule_id')->constrained()->restrictOnDelete();
            $table->decimal('allocated_amount', 18, 2);
            $table->timestamps();

            $table->unique(['receipt_id', 'payment_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
    }
};
