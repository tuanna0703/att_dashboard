<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->string('invoice_no')->unique();
            $table->date('invoice_date');
            $table->decimal('invoice_value', 18, 2)->default(0);
            $table->decimal('vat_value', 18, 2)->default(0);
            $table->enum('status', ['draft', 'sent', 'partially_paid', 'paid', 'cancelled'])->default('draft');
            $table->string('file_path')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
