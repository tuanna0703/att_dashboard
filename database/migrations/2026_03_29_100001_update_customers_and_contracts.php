<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Xoá 3 cột contact cũ trong customers (thay bằng customer_contacts table)
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_email', 'contact_phone']);
        });

        // Thêm name cho contracts
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('name')->nullable()->after('contract_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
