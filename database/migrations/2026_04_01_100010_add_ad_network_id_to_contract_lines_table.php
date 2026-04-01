<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_lines', function (Blueprint $table) {
            $table->foreignId('ad_network_id')
                ->nullable()
                ->after('contract_id')
                ->constrained('ad_networks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_lines', function (Blueprint $table) {
            $table->dropForeign(['ad_network_id']);
            $table->dropColumn('ad_network_id');
        });
    }
};
