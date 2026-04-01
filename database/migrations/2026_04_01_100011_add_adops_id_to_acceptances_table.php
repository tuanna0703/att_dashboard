<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acceptances', function (Blueprint $table) {
            $table->foreignId('adops_id')
                ->nullable()
                ->after('contract_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('acceptances', function (Blueprint $table) {
            $table->dropForeign(['adops_id']);
            $table->dropColumn('adops_id');
        });
    }
};
