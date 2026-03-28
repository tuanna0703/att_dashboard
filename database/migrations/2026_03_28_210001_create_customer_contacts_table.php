<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable()->comment('Chức danh: Giám đốc, Trưởng phòng...');
            $table->enum('role', [
                'management',   // Quản lý / Sếp
                'contract',     // Phụ trách hợp đồng
                'booking',      // Phụ trách booking
                'payment',      // Phụ trách hóa đơn & thanh toán
                'other',        // Khác
            ])->default('other');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false)->comment('Người liên hệ chính');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
    }
};
