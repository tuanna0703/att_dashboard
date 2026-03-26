<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('contract_type', ['ads', 'project', 'subscription']);
            $table->date('signed_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('total_value_estimated', 18, 2)->default(0);
            $table->string('currency', 3)->default('VND');
            $table->foreignId('sale_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finance_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->text('note')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
