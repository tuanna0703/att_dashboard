<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->decimal('total_ar', 18, 2)->default(0);
            $table->decimal('total_overdue', 18, 2)->default(0);
            $table->decimal('due_this_month', 18, 2)->default(0);
            $table->decimal('forecast_30_days', 18, 2)->default(0);
            $table->integer('overdue_count')->default(0);
            $table->integer('due_soon_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_snapshots');
    }
};
