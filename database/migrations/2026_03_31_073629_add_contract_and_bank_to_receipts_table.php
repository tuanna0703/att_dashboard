<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('contract_id')
                ->nullable()
                ->after('recorded_by')
                ->constrained('contracts')
                ->nullOnDelete();

            $table->foreignId('company_bank_id')
                ->nullable()
                ->after('contract_id')
                ->constrained('company_banks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Contract::class);
            $table->dropForeignIdFor(\App\Models\CompanyBank::class);
        });
    }
};
